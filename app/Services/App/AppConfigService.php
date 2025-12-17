<?php

namespace App\Services\App;

use App\Services\Service;
use App\Models\SystemApp;
use App\Dao\App\AppConfigDao;
use App\Exceptions\AdminException;
use App\Support\Services\FormOptions;
use App\Support\Services\FormBuilder as Form;

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
}
