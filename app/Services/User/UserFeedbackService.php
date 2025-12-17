<?php

namespace App\Services\User;

use App\Models\SystemApp;
use App\Dao\User\UserFeedbackDao;
use App\Exceptions\AdminException;
use App\Services\Service;
use App\Support\Services\FormBuilder as Form;
use App\Support\Services\FormOptions;

/**
 * Class UserFeedbackService
 */
class UserFeedbackService extends Service
{
    /**
     * UserFeedbackService constructor.
     */
    public function __construct(UserFeedbackDao $dao)
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
        }

        return $list;
    }






}
