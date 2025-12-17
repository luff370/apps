<?php

namespace App\Services\User;

use App\Dao\User\UserWhitelistLogDao;
use App\Models\SystemApp;
use App\Models\UserWhitelist;
use App\Services\Service;

class UserWhiteListLogService extends Service
{
    public function __construct(UserWhitelistLogDao $dao)
    {
        $this->dao = $dao;
    }

    public function tidyListData($list)
    {
        $apps = SystemApp::idToNameMap();
        $platforms = SystemApp::platformsMap();
        $marketChannels = SystemApp::marketChannelsMap();
        $sourceTypeMap = UserWhitelist::sourceWayMap();
        if (!empty($list)) {
            foreach ($list as &$item) {
                $item['app_name'] = $apps[$item['app_id']] ?? '';
                // $item['type_name'] = UserWhitelistService::conversionTypeName($item['type']);
                $item['platform_name'] = $platforms[$item['platform']] ?? '';
                $item['market_channel'] = $marketChannels[$item['market_channel']] ?? $item['market_channel'];
                $item['source_type_name'] = $sourceTypeMap[$item['source_type']] ?? '';
                $item['user_id'] = $item['user']['id'] ?? '--';
                $item['user_account'] = $item['user']['account'] ?? '--';
            }
        }

        return $list;
    }

}
