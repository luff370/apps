<?php

namespace App\Services\App;

use App\Dao\App\DomainDao;
use App\Exceptions\AdminException;
use App\Models\AppDomain;
use App\Services\Service;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Class DomainService
 */
class DomainService extends Service
{
    public function __construct(DomainDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 列表数据处理
     */
    public function tidyListData($list)
    {
        $rows = [];
        foreach ($list as $item) {
            $row = is_array($item) ? $item : $item->toArray();
            $expireStatus = $this->expireStatus($row['expire_at'] ?? '');
            $row['expire_at'] = $this->formatDate($row['expire_at'] ?? '');
            $row['created_at'] = $this->formatDateTime($row['created_at'] ?? '');
            $row['is_expired'] = $expireStatus['is_expired'];
            $row['is_expire_soon'] = $expireStatus['is_expire_soon'];
            $row['risk_level'] = $this->normalizeRiskLevel($row['risk_level'] ?? AppDomain::RISK_LOW);
            $row['risk_level_name'] = AppDomain::riskLevelMap()[$row['risk_level']] ?? '';
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * 保存域名
     *
     * @throws AdminException
     */
    public function saveDomain(array $data): void
    {
        $id = (int) ($data['id'] ?? 0);
        $domain = trim((string) ($data['domain'] ?? ''));
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = rtrim($domain, '/');
        $subject = trim((string) ($data['subject'] ?? ''));
        if ($domain === '') {
            throw new AdminException('请输入域名');
        }
        if ($subject === '') {
            throw new AdminException('请选择或输入主体');
        }

        $exists = $this->dao->newQuery()->where('domain', $domain);
        if ($id > 0) {
            $exists->where('id', '<>', $id);
        }
        if ($exists->exists()) {
            throw new AdminException('该域名已存在');
        }

        $payload = [
            'domain' => $domain,
            'subject' => $subject,
            'icp_website' => trim((string) ($data['icp_website'] ?? '')),
            'icp_number' => trim((string) ($data['icp_number'] ?? '')),
            'expire_at' => $this->formatDate($data['expire_at'] ?? '') ?: null,
            'status' => (int) ($data['status'] ?? 1) === 1 ? 1 : 0,
            'risk_level' => $this->normalizeRiskLevel($data['risk_level'] ?? AppDomain::RISK_LOW),
            'remark' => trim((string) ($data['remark'] ?? '')),
        ];

        if ($id > 0) {
            if (!$this->dao->get($id)) {
                throw new AdminException(100026);
            }
            $this->dao->update($id, $payload);
            return;
        }

        $this->dao->save($payload);
    }

    public function normalizeRiskLevel($value): int
    {
        $level = (int) $value;
        if (!isset(AppDomain::riskLevelMap()[$level])) {
            return AppDomain::RISK_LOW;
        }

        return $level;
    }

    /**
     * 首页滚动提醒：已过期 + 近一个月内到期
     */
    public function expiryNotices(): array
    {
        $soonUntil = Carbon::today()->addMonth()->toDateString();
        $rows = $this->dao->newQuery()
            ->whereNotNull('expire_at')
            ->whereDate('expire_at', '<=', $soonUntil)
            ->orderBy('expire_at')
            ->orderBy('id')
            ->get()
            ->toArray();

        $list = [];
        foreach ($this->tidyListData($rows) as $row) {
            if (!(int) ($row['is_expired'] ?? 0) && !(int) ($row['is_expire_soon'] ?? 0)) {
                continue;
            }
            $expireAt = (string) ($row['expire_at'] ?? '');
            $days = $this->expireDays($expireAt);
            $list[] = [
                'id' => (int) ($row['id'] ?? 0),
                'domain' => (string) ($row['domain'] ?? ''),
                'subject' => (string) ($row['subject'] ?? ''),
                'expire_at' => $expireAt,
                'is_expired' => (int) ($row['is_expired'] ?? 0),
                'is_expire_soon' => (int) ($row['is_expire_soon'] ?? 0),
                'days' => $days,
            ];
        }

        usort($list, function ($a, $b) {
            if ((int) $a['is_expired'] !== (int) $b['is_expired']) {
                return (int) $b['is_expired'] <=> (int) $a['is_expired'];
            }

            return strcmp((string) $a['expire_at'], (string) $b['expire_at']);
        });

        return ['list' => $list, 'count' => count($list)];
    }

    public function expireDays(string $expireAt): ?int
    {
        if ($expireAt === '' || $expireAt === '-') {
            return null;
        }

        try {
            $expireDay = Carbon::parse($expireAt)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($expireDay, false);
    }

    private function expireStatus($expireAt): array
    {
        $status = ['is_expired' => 0, 'is_expire_soon' => 0];
        if (empty($expireAt) || $expireAt === '-') {
            return $status;
        }

        try {
            $time = $expireAt instanceof DateTimeInterface ? Carbon::instance($expireAt) : Carbon::parse($expireAt);
        } catch (\Throwable $e) {
            return $status;
        }

        $today = Carbon::today();
        $expireDay = $time->copy()->startOfDay();
        if ($expireDay->lt($today)) {
            $status['is_expired'] = 1;
        } elseif ($expireDay->lte($today->copy()->addMonth())) {
            $status['is_expire_soon'] = 1;
        }

        return $status;
    }

    private function formatDate($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return '';
        }

        try {
            return Carbon::parse($text)->toDateString();
        } catch (\Throwable $e) {
            return substr($text, 0, 10);
        }
    }

    private function formatDateTime($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (empty($value) || $value === '-') {
            return '';
        }

        return (string) $value;
    }
}
