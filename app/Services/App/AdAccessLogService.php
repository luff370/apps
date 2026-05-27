<?php

namespace App\Services\App;

use App\Dao\App\AdAccessLogDao;
use App\Models\AdAccessLog;
use App\Models\AppAdvertisement;
use App\Models\SystemApp;
use App\Services\Service;

class AdAccessLogService extends Service
{
    public function __construct(AdAccessLogDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 广告访问统计列表
     */
    public function getStatByPage(array $filter): array
    {
        [$page, $limit] = $this->getPageValue();
        $data = $this->dao->getStatByPage($filter, $page, $limit);
        $data['list'] = $this->tidyStatListData($data['list']);

        return $data;
    }

    /**
     * 明细列表数据处理
     */
    public function tidyListData($list)
    {
        $apps = SystemApp::idToNameMap();
        $marketChannels = SystemApp::marketChannelsMap();
        $adChannels = AppAdvertisement::adChannelsMap();

        foreach ($list as &$item) {
            $item = is_array($item) ? $item : $item->toArray();
            $item['app_name'] = $apps[$item['app_id']] ?? '';
            $item['market_channel_name'] = $marketChannels[$item['market_channel']] ?? $item['market_channel'];
            $item['ad_channel_name'] = $adChannels[$item['ad_channel']] ?? $item['ad_channel'];
            $item['status_name'] = (int)$item['status'] === AdAccessLog::STATUS_SUCCESS ? '成功' : '失败';
        }

        return $list;
    }

    /**
     * 统计列表数据处理
     */
    public function tidyStatListData($list): array
    {
        $apps = SystemApp::idToNameMap();
        $marketChannels = SystemApp::marketChannelsMap();
        $adChannels = AppAdvertisement::adChannelsMap();

        foreach ($list as &$item) {
            $item = is_array($item) ? $item : (array)$item;
            $item['app_name'] = $apps[$item['app_id']] ?? '';
            $item['market_channel_name'] = $marketChannels[$item['market_channel']] ?? $item['market_channel'];
            $item['ad_channel_name'] = $adChannels[$item['ad_channel']] ?? $item['ad_channel'];
            $item['total_count'] = (int)($item['total_count'] ?? 0);
            $item['success_count'] = (int)($item['success_count'] ?? 0);
            $item['fail_count'] = (int)($item['fail_count'] ?? 0);
        }

        return $list;
    }
}
