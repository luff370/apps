<?php

namespace App\Services\System\Config;

use App\Models\SystemApp;
use App\Models\SystemConfig;
use App\Models\SystemConfigTab;
use App\Services\Service;
use App\Exceptions\AdminException;
use App\Support\Services\FormBuilder as Form;
use App\Dao\System\Config\SystemConfigTabDao;
use App\Support\Services\FormOptions;
use Overtrue\Pinyin\Pinyin;

/**
 * 系统配置分类
 * Class SystemConfigTabServices
 *
 * @package App\Services\System\Config
 * @method save(array $data) 写入数据
 * @method update($id, array $data, ?string $key = null) 修改数据
 * @method delete($id, ?string $key = null) 删除数据
 */
class SystemConfigTabServices extends Service
{
    /**
     * SystemConfigTabServices constructor.
     *
     * @param SystemConfigTabDao $dao
     */
    public function __construct(SystemConfigTabDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 系统设置头部分类读取
     *
     * @param int $pid
     * @return array
     */
    public function getConfigTab(int $pid, int $type = 0, int $appId = 0): array
    {
        $list = $this->dao->getConfigTabAll(['status' => 1, 'pid' => $pid, 'app_id' => $appId], ['id', 'id as value', 'title as label', 'pid', 'icon', 'type'], $pid ? [] : [['type', '=', $type]]);

        return get_tree_children($list);
    }

    /**
     * 获取配置分类列表
     *
     * @param array $where
     *
     * @return array
     */
    public function getConfigTabList(array $where): array
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getConfigTabList($where, $page, $limit);
        $count = $this->dao->count($where);
        $menusValue = [];
        foreach ($list as $item) {
            $menusValue[] = $item->toArray();
        }
        $list = get_tree_children($menusValue);

        return compact('list', 'count');
    }

    /**
     * 获取配置分类选择下拉树
     *
     * @return array
     */
    public function getSelectForm(): array
    {
        $menuList = $this->dao->getConfigTabAll(['pid' => 0], ['id', 'pid', 'title']);
        $list = sort_list_tier($menuList);
        $menus = [['value' => 0, 'label' => '顶级按钮']];
        foreach ($list as $menu) {
            $menus[] = ['value' => $menu['id'], 'label' => $menu['html'] . $menu['title']];
        }

        return $menus;
    }

    /**
     * 创建form表单
     *
     * @param array $formData
     *
     * @return array
     */
    public function createConfigTabForm(array $formData = []): array
    {
        $form[] = Form::select('pid', '父级分类', isset($formData['pid']) ? (string)$formData['pid'] : '')->setOptions($this->getSelectForm())->filterable(true);
        $form[] = Form::select('app_id', '所属应用', isset($formData['app_id']) ? (string)$formData['app_id'] : '')->setOptions(FormOptions::systemApps(['label' => '顶级分类，应用类型必选', 'value' => 0]))->filterable(true);
        $form[] = Form::input('title', '分类名称', $formData['title'] ?? '')->required('分类名称不能为空');
        $form[] = Form::input('eng_title', '分类字段英文', $formData['eng_title'] ?? '')->required('分类名称不能为空');
        $form[] = Form::frameInput('icon', '图标', url('/admin/widget.widgets/icon', ['fodder' => 'icon'], true), $formData['icon'] ?? '')->icon('ios-ionic')->height('505px')->modal(['footer-hide' => true]);
        $form[] = Form::radio('type', '类型', $formData['type'] ?? 0)->options([
            ['value' => 0, 'label' => '系统'],
            ['value' => 1, 'label' => '应用'],
            ['value' => 3, 'label' => '其它'],
        ]);
        $form[] = Form::radio('status', '状态', $formData['status'] ?? 1)->options([['value' => 1, 'label' => '显示'], ['value' => 2, 'label' => '隐藏']]);
        $form[] = Form::number('sort', '排序', (int)($formData['sort'] ?? 0))->precision(0);

        return $form;
    }

    /**
     * 添加配置分类表单
     *
     * @return array
     * @throws AdminException
     */
    public function createForm(): array
    {
        return create_form('添加配置分类', $this->createConfigTabForm(), url('/admin/setting/config_class'));
    }

    /**
     * 修改配置分类表单
     *
     * @param int $id
     *
     * @return array
     * @throws AdminException
     */
    public function updateForm(int $id): array
    {
        $configTabInfo = $this->dao->get($id);
        if (!$configTabInfo) {
            throw new AdminException(100026);
        }

        return create_form('编辑配置分类', $this->createConfigTabForm($configTabInfo->toArray()), url('/admin/setting/config_class/' . $id), 'PUT');
    }

    /**
     * 同步另一个应用配置到当前应用
     *
     * @throws AdminException
     */
    public function syncFromOtherAppConfig(int $fromAppId, int $toAppId): bool
    {
        $fromParentConfigTab = SystemConfigTab::query()
            ->where('app_id', $fromAppId)
            ->where('pid', 0)
            ->first();
        if (!$fromParentConfigTab) {
            throw new AdminException('同步应用无配置数据');
        }

        $fromConfigTabs = SystemConfigTab::query()
            ->where('app_id', $fromAppId)
            ->where('pid', $fromParentConfigTab['id'])
            ->orderBy('id','asc')
            ->get();
        if (!$fromConfigTabs) {
            throw new AdminException('同步应用无配置数据');
        }

        // 顶级配置栏目获取
        $parentConfigTab = SystemConfigTab::query()->where('app_id', $toAppId)->first();
        if (!$parentConfigTab) {
            $appInfo = SystemApp::query()->find($toAppId);
            $parentConfigTab = new SystemConfigTab();
            $parentConfigTab->app_id = $toAppId;
            $parentConfigTab->title = $appInfo['name'];
            $parentConfigTab->eng_title = Pinyin::fullSentence($appInfo['name'], 'none')->join('-');
            $parentConfigTab->type = 1;
            $parentConfigTab->save();
        }

        foreach ($fromConfigTabs as $fromConfigTab) {
            $fromConfigs = SystemConfig::query()->where('config_tab_id', $fromConfigTab['id'])->orderBy('id')->get()->toArray();
            if (!empty($fromConfigs)) {
                // 同步配置分类
                $configTab = $this->getOrCreateConfigTab($parentConfigTab, $fromConfigTab);
                foreach ($fromConfigs as $fromConfig) {
                    $this->syncConfig($fromConfig, $configTab);
                }
            }
        }

        return true;
    }

    // 获取或创建配置栏目
    public function getOrCreateConfigTab($parentConfigTab, $fromConfigTab): SystemConfigTab
    {
        $configTab = SystemConfigTab::query()->where('app_id', $parentConfigTab['app_id'])
            ->where('eng_title', $fromConfigTab['eng_title'])
            ->first();

        if (!$configTab) {
            $configTab = new SystemConfigTab();
            $configTab->app_id = $parentConfigTab['app_id'];
            $configTab->title = $fromConfigTab['title'];
            $configTab->eng_title = $fromConfigTab['eng_title'];
            $configTab->pid = $parentConfigTab['id'];
            $configTab->type = $parentConfigTab['type'];
            $configTab->save();
        }

        return $configTab;
    }

    // 同步配置项
    public function syncConfig($fromConfig, $parentConfigTab): \Illuminate\Database\Eloquent\Model
    {
        $config = SystemConfig::query()->where('menu_name', $fromConfig['menu_name'])
            ->where('app_id', $parentConfigTab['app_id'])
            ->first();
        if ($config) {
            return $config;
        }

        $config = $fromConfig;
        $config['app_id'] = $parentConfigTab['app_id'];
        $config['config_tab_id'] = $parentConfigTab['id'];
        unset($config['id']);

        return SystemConfig::query()->create($config);
    }
}
