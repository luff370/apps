<?php

namespace App\Services\User;

use App\Models\User;
use App\Services\Service;
use App\Models\SystemApp;
use App\Models\UserAccessLog;
use App\Support\Utils\Token;
use App\Dao\User\UserAccessLogDao;
use Illuminate\Support\Facades\DB;

/**
 * Class UserAccessLogService
 */
class UserAccessLogService extends Service
{
    /**
     * UserAccessLogService constructor.
     */
    public function __construct(UserAccessLogDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 列表数据处理
     */
    public function tidyListData($list)
    {
        $apps = SystemApp::idToNameMap();
        $marketChannels = SystemApp::marketChannelsMap();
        foreach ($list as &$item) {
            $item['app_name'] = $apps[$item['app_id']] ?? '';
            $item['market_channel'] = $marketChannels[$item['market_channel']] ?? $item['market_channel'];
            $item['user_id'] = $item['user']['id'] ?? 0;
            $item['user_account'] = $item['user']['account'] ?? '--';
        }

        return $list;
    }

    public static function record($uid, $appId, $marketChannel, $version, $os, $uuid, $device, $ip, $source, $returnData = [], ?string $token = null)
    {
        if(empty($uuid)) {
            return;
        }

        if ((int) $uid <= 0) {
            $uid = self::resolveUserId((int) $appId, (string) $uuid, $token);
        }

        UserAccessLog::query()->create([
            'user_id' => $uid,
            'app_id' => $appId,
            'market_channel' => $marketChannel,
            'version' => $version,
            'os' => $os,
            'uuid' => $uuid,
            'device' => $device,
            'ip' => $ip,
            'region' => ip2region($ip),
            'source' => $source,
            'return_data' => $returnData,
        ]);

        // 根据uuid 记录用户最后登录时间、IP
        User::query()->where('uuid', $uuid)->where('app_id', $appId)
            ->orderBy("id", 'desc')
            ->limit(1)
            ->update([
                'last_time' => time(),
                'last_ip' => $ip,
                'login_count' => DB::raw('login_count + 1'),
            ]);
    }

    private static function resolveUserId(int $appId, string $uuid, ?string $token): int
    {
        // 在响应后的日志任务中执行；Token 只读已有缓存，不续期或重建。
        try {
            $userId = Token::cachedUserId($token);
            if ($userId > 0) {
                return $userId;
            }
        } catch (\Throwable $exception) {
            logger()->warning('访问日志读取Token用户ID失败', ['app_id' => $appId]);
        }

        if ($appId <= 0 || $uuid === '') {
            return 0;
        }

        try {
            // uuid 已有索引，只取当前应用下最新的未删除用户 ID。
            return (int) User::query()->where('uuid', $uuid)
                ->where('app_id', $appId)
                ->where('is_del', 0)
                ->orderByDesc('id')
                ->value('id');
        } catch (\Throwable $exception) {
            logger()->warning('访问日志解析UUID用户ID失败', ['app_id' => $appId]);

            return 0;
        }
    }
}
