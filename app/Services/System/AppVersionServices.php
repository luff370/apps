<?php

declare (strict_types = 1);

namespace App\Services\System;

use App\Services\Service;
use App\Models\SystemApp;
use App\Models\AppVersion;
use App\Models\AppVersionPlanTask;
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
        $rows = [];
        foreach ($list as $item) {
            $row = $item instanceof AppVersion ? $item->toArray() : (array)$item;
            $platformCode = (string)($row['platform'] ?? '');
            // 强更记录页按 market_channel 展示渠道，这里保留原始渠道码，platform 仍给旧列表当中文名。
            $row['market_channel'] = $platformCode;
            $row['platform'] = $marketChannelMap[$platformCode] ?? $platformCode;
            $row['audit_status_name'] = $auditStatusMap[$row['audit_status'] ?? 0] ?? '';
            $row['app_name'] = (string)($row['app']['name'] ?? $row['name'] ?? '');
            $rows[] = $row;
        }

        return $rows;
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
        $copyFromId = (int)request('copy_from', 0);
        $info = [];
        if ($id) {
            $info = $this->dao->get($id);
            $info = $info ? $info->toArray() : [];
        } elseif ($copyFromId > 0) {
            $source = $this->dao->get($copyFromId);
            if (!$source) {
                throw new AdminException('版本信息不存在');
            }
            $info = $source->toArray();
            $info['id'] = 0;
            $info['is_force'] = (int)($info['is_force'] ?? 1);
            $info['is_new'] = (int)($info['is_new'] ?? 0);
            $info['audit_status'] = (int)($info['audit_status'] ?? 0);
        }
        if (!$id && empty($info['app_id'])) {
            $info['app_id'] = (int)request('app_id', 0);
        }

        $field[] = Form::hidden('id', $info['id'] ?? 0);
        $field[] = Form::select('app_id', '所属应用', $info['app_id'] ?? 0)->options(FormOptions::systemApps())->requiredNum();
        $field[] = Form::select('platform', '上架渠道', (string)($info['platform'] ?? ''))->options(FormOptions::marketChannel())->required();
        $field[] = Form::input('version', '版本号', $info['version'] ?? '')->col(24)->required();
        $field[] = Form::input('info', '版本介绍', $info['info'] ?? '')->type('textarea');
        $field[] = Form::uploadFile('url', '下载包', config('admin.url') . '/admin/file/upload',$info['url'] ?? '')
            ->headers(['Authori-Zation' => request()->header(config('cookie.token_name', 'Authori-zation'))]);
        $field[] = Form::radio('is_force', '强制', $info['is_force'] ?? 1)->options([['label' => '开启', 'value' => 1], ['label' => '关闭', 'value' => 0]]);
        $field[] = Form::radio('is_new', '是否最新', $info['is_new'] ?? 1)->options([['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]]);
        $field[] = Form::radio('audit_status', '审核状态', $info['audit_status'] ?? 0)->options($this->toFormSelect(AppVersion::auditStatusMap()));
        $field[] = Form::input('remark', '备注', $info['remark'] ?? '')->type('textarea');

        $title = $id ? '编辑版本信息' : ($copyFromId > 0 ? '复制版本信息' : '添加版本信息');

        return create_form($title, $field, url('/admin/app/version'), 'POST');
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
        $cacheKey = self::auditStatusCacheKey($appId, $marketChannel, $version);

        $isAudit = cache($cacheKey);
        if ($isAudit === null) {
            $isAudit = 0;
            $status = AppVersionPlanTask::query()
                ->join('app_version_plans', 'app_version_plans.id', '=', 'app_version_plan_tasks.plan_id')
                ->where('app_version_plans.app_id', $appId)
                ->where('app_version_plan_tasks.market_channel', $marketChannel)
                ->where('app_version_plan_tasks.version', $version)
                ->orderByDesc('app_version_plan_tasks.updated_at')
                ->orderByDesc('app_version_plan_tasks.id')
                ->value('app_version_plan_tasks.status');
            if ($status === '审核中') {
                $isAudit = 1;
            }

            cache()->put($cacheKey, $isAudit, 600);
        }

        return (int) $isAudit;
    }

    public static function auditStatusCacheKey($appId, $marketChannel, $version): string
    {
        return 'app_audit_status:' . $appId . '-' . $marketChannel . '-' . $version;
    }
}
