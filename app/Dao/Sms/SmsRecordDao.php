<?php

namespace App\Dao\Sms;

use App\Dao\BaseDao;
use App\Models\SmsRecord;

/**
 * 短信发送记录
 * Class SmsRecordDao
 *
 * @package App\Dao\Sms
 */
class SmsRecordDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    public function setModel(): string
    {
        return SmsRecord::class;
    }

    /**
     * 短信发送记录
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getRecordList(array $where, int $page, int $limit)
    {
        return $this->search($where)->page($page, $limit)->orderByRaw('add_time DESC')->get()->toArray();
    }
}
