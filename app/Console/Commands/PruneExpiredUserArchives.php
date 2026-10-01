<?php

namespace App\Console\Commands;

use App\Services\User\UserArchiveService;
use Illuminate\Console\Command;

class PruneExpiredUserArchives extends Command
{
    protected $signature = 'app:user-archive-prune-expired';

    protected $description = '会员过期超过宽限期后，删除超出非会员上限的旧档案，保留最新若干条';

    public function handle(UserArchiveService $archiveService): int
    {
        $deleted = $archiveService->pruneExpiredExtraArchives();
        $this->info('已清理超量档案 ' . $deleted . ' 条');

        return self::SUCCESS;
    }
}
