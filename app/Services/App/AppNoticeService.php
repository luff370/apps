<?php

namespace App\Services\App;

use App\Dao\App\AppNoticeDao;
use App\Models\AppNotice;
use App\Services\Service;

class AppNoticeService extends Service
{
    public function __construct(AppNoticeDao $dao)
    {
        $this->dao = $dao;
    }

    public function findByApp(int $appId, int $id): ?AppNotice
    {
        return AppNotice::query()->where('app_id', $appId)->where('id', $id)->first();
    }

    public function enabledList(int $appId): array
    {
        return AppNotice::query()
            ->where('app_id', $appId)
            ->where('is_enable', 1)
            ->orderByDesc('sort')
            ->orderByDesc('id')
            ->get(['id', 'title', 'content', 'sort'])
            ->toArray();
    }
}
