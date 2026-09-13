<?php

namespace App\Console\Commands;

use App\Support\Services\AppPackageMap;
use Illuminate\Console\Command;

class RebuildAppPackageMap extends Command
{
    protected $signature = 'app:rebuild-package-map';

    protected $description = '根据 system_apps 重写 config/app_package_map.php 包名到应用 ID 映射';

    public function handle(): int
    {
        $count = AppPackageMap::rebuild();
        $this->info("已写入 {$count} 条包名映射");

        return self::SUCCESS;
    }
}
