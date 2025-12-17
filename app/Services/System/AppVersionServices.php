<?php

declare (strict_types = 1);

namespace App\Services\System;

use App\Services\Service;
use App\Models\SystemApp;
use App\Models\AppVersion;
use App\Dao\System\AppVersionDao;
use App\Exceptions\AdminException;
use App\Support\Services\FormOptions;
use App\Support\Services\FormBuilder as Form;

/**
 * Class AppVersionServices
 *
 * @package App\Services\System
 */
class AppVersionServices extends Service
{
    /**
     * DiyServices constructor.
     *
     * @param AppVersionDao $dao
     */
    public function __construct(AppVersionDao $dao)
    {
        $this->dao = $dao;
    }

    public function tidyListData($list)
    {
        $auditStatusMap = AppVersion::auditStatusMap();
        $marketChannelMap = SystemApp::marketChannelsMap();
        foreach ($list as &$item) {
            $item['audit_status_name'] = $auditStatusMap[$item['audit_status']] ?? '';
            $item['platform'] = $marketChannelMap[$item['platform']] ?? '';
        }

        return $list;
    }

    /**
     * 添加版本表单
     *
     * @param int $id
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     */
    public function createForm($id = 0): array
    {
        if ($id) {
            $info = $this->dao->get($id);
        }
        $field[] = Form::hidden('id', $info['id'] ?? 0);
        $field[] = Form::select('app_id', '所属应用', $info['app_id'] ?? 0)->options(FormOptions::systemApps())->requiredNum();
        $field[] = Form::select('platform', '上架渠道', $info['platform'] ?? 0)->options(FormOptions::marketChannel())->required();
        $field[] = Form::input('version', '版本号', $info['version'] ?? '')->col(24)->required();
        $field[] = Form::input('info', '版本介绍', $info['info'] ?? '')->type('textarea');
        $field[] = Form::input('url', '下载链接', $info['url'] ?? '');
        $field[] = Form::radio('is_force', '强制', $info['is_force'] ?? 1)->options([['label' => '开启', 'value' => 1], ['label' => '关闭', 'value' => 0]]);
        $field[] = Form::radio('is_new', '是否最新', $info['is_new'] ?? 1)->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]]);
        $field[] = Form::radio('audit_status', '审核状态', $info['audit_status'] ?? 0)->options($this->toFormSelect(AppVersion::auditStatusMap()));
        $field[] = Form::input('remark', '备注', $info['remark'] ?? '')->type('textarea');

        return create_form((empty($id) ? '添加' : '编辑') . '版本信息', $field, url('/admin/app/version'), 'POST');
    }

    /**
     * 保存数据
     *
     * @param $id
     * @param $data
     *
     * @return mixed
     * @throws \App\Exceptions\AdminException
     */
    public function versionSave($id, $data): mixed
    {
        try {
            if ($id) {
                return \DB::transaction(function () use ($data, $id) {
                    if ($data['is_new']) {
                        $this->dao->update(['platform' => $data['platform'], 'app_id' => $data['app_id']], ['is_new' => 0]);
                    }

                    return $this->dao->update($id, $data);
                });
            } else {
                return \DB::transaction(function () use ($data) {
                    $this->dao->update(['platform' => $data['platform'], 'app_id' => $data['app_id']], ['is_new' => 0]);

                    return $this->dao->save($data);
                });
            }
        } catch (\Throwable $e) {
            logger()->error('版本信息保存失败：' . $e->getMessage());
            throw new AdminException('保存失败');
        }
    }

    public static function getAuditStatusByVersion($appId, $marketChannel, $version): int
    {
        $cacheKey = 'app_audit_status:' . $appId . '-' . $marketChannel . '-' . $version;

        $isAudit = cache($cacheKey);
        if ($isAudit === null) {
            $isAudit = 0;
            $info = AppVersion::query()->where('app_id', $appId)
                ->where('platform', $marketChannel)
                ->where('version', $version)
                ->first(['id', 'audit_status']);
            if ($info && $info['audit_status'] == 0) {
                $isAudit = 1;
            }

            cache()->put($cacheKey, $isAudit, 600);
        }

        return (int) $isAudit;
    }
}
