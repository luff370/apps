<?php

namespace App\Services\Risk;

use App\Services\Service;
use App\Support\Services\FormBuilder as Form;

class RiskStrategyService extends Service
{
    public function list(array $filter = []): array
    {
        $row = $this->liveStrategy();
        $keyword = strtolower((string) ($filter['keyword'] ?? ''));
        if ($keyword !== '' && !str_contains(strtolower($row['name'] . ' ' . $row['scene']), $keyword)) {
            return ['list' => [], 'count' => 0];
        }
        if ($filter['app_id'] !== '' && $filter['app_id'] !== null && (int) $filter['app_id'] !== 0) {
            return ['list' => [], 'count' => 0];
        }

        return ['list' => [$row], 'count' => 1];
    }

    public function createForm(): array
    {
        return create_form('评分策略', $this->formFields($this->liveStrategy()), url('/admin/risk/strategy'));
    }

    public function editForm(int $id): array
    {
        return create_form('评分策略', $this->formFields($this->liveStrategy()), url('/admin/risk/strategy/' . $id), 'PUT');
    }

    public function liveStrategy(): array
    {
        $adBlock = (int) config('api_obfuscation.device_env.ad_block_score_threshold', 40);
        $compliance = (int) config('api_obfuscation.device_env.compliance_score_threshold', 60);

        return [
            'id' => 1,
            'name' => '默认 Device-Env 评分策略',
            'app_id' => 0,
            'app_name' => '全局',
            'scene' => 'ads',
            'normal_max' => 30,
            'watch_max' => $adBlock,
            'high_max' => $compliance,
            'action_normal' => 'pass',
            'action_watch' => 'limit',
            'action_high' => 'limit',
            'action_critical' => 'block',
            'status' => 1,
            'remark' => '对齐当前服务端：分数≥' . $adBlock . ' 关广告(ad_switch=0)，分数≥' . $compliance . ' 开合规(compliance_mode=1)。阈值来自 DEVICE_ENV_AD_BLOCK_SCORE / DEVICE_ENV_COMPLIANCE_SCORE。',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function formFields(array $info): array
    {
        $field = [];
        $field[] = Form::input('name', '策略名称', $info['name'])->disabled(true);
        $field[] = Form::input('scene', '场景', $info['scene'])->disabled(true);
        $field[] = Form::input('normal_max', '正常上限', (string) $info['normal_max'])->disabled(true);
        $field[] = Form::input('watch_max', '关广告阈值', (string) $info['watch_max'])->disabled(true);
        $field[] = Form::input('high_max', '合规阈值', (string) $info['high_max'])->disabled(true);
        $field[] = Form::input('remark', '说明', $info['remark'])->type('textarea')->disabled(true);

        return $field;
    }
}
