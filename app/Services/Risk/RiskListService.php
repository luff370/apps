<?php

namespace App\Services\Risk;

use App\Dao\Risk\RiskListDao;
use App\Exceptions\AdminException;
use App\Models\RiskList;
use App\Models\SystemApp;
use App\Services\Service;
use App\Support\Services\FormBuilder as Form;
use App\Support\Services\FormOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class RiskListService extends Service
{
    private const CACHE_KEY = 'risk_lists:active';

    public function __construct(RiskListDao $dao)
    {
        $this->dao = $dao;
    }

    public function tidyListData($list)
    {
        $apps = SystemApp::idToNameMap();
        foreach ($list as &$item) {
            $appId = (int) ($item['app_id'] ?? 0);
            $item['app_name'] = $appId ? ($apps[$appId] ?? '') : '全局';
            $item['operator'] = $item['operator'] ?? '';
        }

        return $list;
    }

    public function createForm(string $listType = RiskList::TYPE_BLACKLIST): array
    {
        return create_form('新增名单', $this->formFields(['list_type' => $listType]), url('/admin/risk/list'));
    }

    public function updateForm(int $id): array
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(100026);
        }

        return create_form('编辑名单', $this->formFields($info->toArray()), url('/admin/risk/list/' . $id), 'PUT');
    }

    public function saveRow(array $data, ?int $id = null): void
    {
        $payload = [
            'list_type' => $data['list_type'] === RiskList::TYPE_WATCHLIST ? RiskList::TYPE_WATCHLIST : RiskList::TYPE_BLACKLIST,
            'target_type' => $data['target_type'] ?: 'device_identity',
            'target_value' => trim((string) $data['target_value']),
            'app_id' => (int) ($data['app_id'] ?? 0),
            'decision' => $data['decision'] ?: ($data['list_type'] === RiskList::TYPE_WATCHLIST ? 'verify' : 'block'),
            'status' => isset($data['status']) && $data['status'] !== '' ? (int) $data['status'] : 1,
            'remark' => $data['remark'] ?? '',
            'operator' => $data['operator'] ?: (adminInfo()['account'] ?? 'admin'),
        ];
        if ($payload['target_value'] === '') {
            throw new AdminException('请填写目标值');
        }

        if ($id) {
            $this->dao->update($id, $payload);
        } else {
            $this->dao->save($payload);
        }
        self::forgetCache();
    }

    public function setField(int $id, string $field, $value): void
    {
        if (!in_array($field, ['status', 'decision', 'remark'], true)) {
            throw new AdminException('不支持的字段');
        }
        $this->dao->update($id, [$field => $value]);
        self::forgetCache();
    }

    public function remove(int $id): void
    {
        $this->dao->delete($id);
        self::forgetCache();
    }

    /**
     * 命中黑/观察名单时覆盖广告与合规决策。白名单仍走用户设备白名单，不在这里处理。
     */
    public static function overlayDecision(array $decision, array $probe, array $meta): array
    {
        $hit = self::match(
            (string) ($probe['uuid'] ?? ''),
            (string) ($probe['device_sn'] ?? ''),
            is_numeric($meta['app_id'] ?? null) ? (int) $meta['app_id'] : 0
        );
        if (!$hit) {
            return $decision;
        }

        $reasons = $decision['reasons'] ?? [];
        $reasons[] = $hit['list_type'] === RiskList::TYPE_WATCHLIST ? 'list_watchlist' : 'list_blacklist';
        $decision['reasons'] = array_values(array_unique($reasons));

        if ($hit['list_type'] === RiskList::TYPE_BLACKLIST) {
            if (($hit['decision'] ?? 'block') === 'block') {
                $decision['compliance_mode'] = 1;
                $decision['ad_switch'] = 0;
            } else {
                $decision['ad_switch'] = 0;
            }
        }

        return $decision;
    }

    public static function match(string $uuid, string $deviceSn, int $appId): ?array
    {
        if (!Schema::hasTable('risk_lists')) {
            return null;
        }

        $rows = Cache::remember(self::CACHE_KEY, 60, function () {
            return RiskList::query()
                ->where('status', 1)
                ->orderByDesc('id')
                ->get(['list_type', 'target_type', 'target_value', 'app_id', 'decision'])
                ->toArray();
        });

        $hardware = $deviceSn !== '' ? substr(hash('sha256', $deviceSn), 0, 16) : '';
        $candidates = array_filter([
            'device_identity' => $uuid,
            'install_id' => $deviceSn !== '' ? $deviceSn : $uuid,
            'hardware_hash' => $hardware,
        ]);

        $blackHit = null;
        $watchHit = null;
        foreach ($rows as $row) {
            $rowApp = (int) ($row['app_id'] ?? 0);
            if ($rowApp !== 0 && $rowApp !== $appId) {
                continue;
            }
            $expected = $candidates[$row['target_type']] ?? '';
            if ($expected === '' || (string) $row['target_value'] !== (string) $expected) {
                continue;
            }
            if ($row['list_type'] === RiskList::TYPE_BLACKLIST && $blackHit === null) {
                $blackHit = $row;
            }
            if ($row['list_type'] === RiskList::TYPE_WATCHLIST && $watchHit === null) {
                $watchHit = $row;
            }
        }

        return $blackHit ?: $watchHit;
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function formFields(array $info): array
    {
        $listType = $info['list_type'] ?? RiskList::TYPE_BLACKLIST;
        $apps = FormOptions::systemApps(['label' => '全局', 'value' => 0]);
        $field = [];
        $field[] = Form::select('list_type', '名单类型', $listType)->setOptions([
            ['label' => '黑名单', 'value' => RiskList::TYPE_BLACKLIST],
            ['label' => '观察名单', 'value' => RiskList::TYPE_WATCHLIST],
        ])->required();
        $field[] = Form::select('target_type', '目标类型', $info['target_type'] ?? 'device_identity')->setOptions([
            ['label' => '设备身份', 'value' => 'device_identity'],
            ['label' => '安装实例', 'value' => 'install_id'],
            ['label' => '账号', 'value' => 'account'],
            ['label' => '硬件指纹', 'value' => 'hardware_hash'],
        ])->required();
        $field[] = Form::input('target_value', '目标值', $info['target_value'] ?? '')->required();
        $field[] = Form::select('app_id', '应用', (int) ($info['app_id'] ?? 0))->setOptions($apps);
        $field[] = Form::select('decision', '强制决策', $info['decision'] ?? ($listType === RiskList::TYPE_WATCHLIST ? 'verify' : 'block'))->setOptions([
            ['label' => '正常访问', 'value' => 'pass'],
            ['label' => '增加验证', 'value' => 'verify'],
            ['label' => '限制功能', 'value' => 'limit'],
            ['label' => '阻断', 'value' => 'block'],
        ]);
        $field[] = Form::input('remark', '备注', $info['remark'] ?? '')->type('textarea');

        return $field;
    }
}
