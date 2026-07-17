<?php

namespace App\Services\App;

use App\Services\Service;
use App\Models\SystemApp;
use App\Dao\App\AppConfigDao;
use App\Exceptions\AdminException;
use App\Support\Services\FormOptions;
use App\Support\Services\FormBuilder as Form;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AppConfigService
 */
class AppConfigService extends Service
{
    /**
     * AppConfigService constructor.
     */
    public function __construct(AppConfigDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 列表数据处理
     */
    public function tidyListData($list)
    {
        $apps = SystemApp::idToNameMap();
        $channels = SystemApp::marketChannelsMap();
        foreach ($list as &$item) {
            $item['app_name'] = $apps[$item['app_id']] ?? '';
            $item['channel']  = $channels[$item['channel']] ?? '全部';
        }

        return $list;
    }

    /**
     * 新增表单获取
     *
     * @param array $params
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     */
    public function createForm($params=[]): array
    {
        return create_form('添加', $this->createUpdateForm($params), url('/admin/app/app_config'));
    }

    /**
     * 编辑表单获取
     *
     * @param int $id
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     */
    public function updateForm(int $id): array
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(400594);
        }

        return create_form('修改', $this->createUpdateForm($info->toArray()), url('/admin/app/app_config/' . $id), 'PUT');
    }

    /**
     * 生成form表单
     */
    public function createUpdateForm(array $info = []): array
    {
        $f[] = Form::select('app_id', '应用', $info['app_id'] ?? '')->options(FormOptions::systemApps())->filterable(true)->requiredNum();
        $f[] = Form::select('channel', '渠道', $info['channel'] ?? 'all')->options(FormOptions::marketChannel(['label' => '全部', 'value' => 'all']));
        $f[] = Form::text('version', '版本', $info['version'] ?? 'all');
        $f[] = Form::text('name', '参数名称', $info['name'] ?? '')->required();
        $f[] = Form::text('key', '参数key', $info['key'] ?? '')->required();
        $f[] = Form::text('value', '参数值', $info['value'] ?? '')->required();
        $f[] = Form::textarea('remark', '备注', $info['remark'] ?? '');
        $f[] = Form::radio('is_enable', '是否启用', $info['is_enable'] ?? 1)->options(FormOptions::isEnable());

        return $f;
    }

    /**
     * 保存应用参数配置
     */
    public function save(array $data): Model
    {
        $data = $this->normalizeUniqueFields($data);
        $this->ensureKeyUnique($data);

        return $this->dao->save($data);
    }

    /**
     * 更新应用参数配置
     */
    public function update($id, array $data, string $key = ''): int
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(400594);
        }

        $data = $this->normalizeUniqueFields($data, $info->toArray());
        $this->ensureKeyUnique($data, (int)$info->id);

        return $this->dao->update($id, $data, $key);
    }

    /**
     * 复制应用参数配置表单
     */
    public function copyForm(int $id): array
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(400594);
        }

        $data = $info->only([
            'app_id',
            'channel',
            'version',
            'name',
            'key',
            'value',
            'remark',
        ]);
        $data['is_enable'] = 0;

        return $this->createForm($data);
    }

    private function normalizeUniqueFields(array $data, array $origin = []): array
    {
        foreach (['app_id', 'channel', 'version', 'key'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }

            if ((!array_key_exists($field, $data) || $data[$field] === '') && array_key_exists($field, $origin)) {
                $data[$field] = $origin[$field];
            }
        }

        if (!array_key_exists('channel', $data) || $data['channel'] === '') {
            $data['channel'] = 'all';
        }

        if (!array_key_exists('version', $data) || $data['version'] === '') {
            $data['version'] = 'all';
        }

        return $data;
    }

    private function ensureKeyUnique(array $data, int $ignoreId = 0): void
    {
        if ($this->dao->existsByUniqueKey($data, $ignoreId)) {
            throw new AdminException('同一应用、同一版本、同一渠道下参数key不能重复');
        }
    }
}
