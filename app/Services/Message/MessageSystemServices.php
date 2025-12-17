<?php

declare (strict_types = 1);

namespace App\Services\Message;

use App\Services\Service;
use App\Exceptions\ApiException;
use App\Dao\system\MessageSystemDao;

/**
 * 站内信services类
 * Class MessageSystemServices
 *
 * @package App\Services\system
 * @method save(array $data) 保存数据
 * @method mixed saveAll(array $data) 批量保存数据
 * @method update($id, array $data, ?string $key = null) 修改数据
 *
 */
class MessageSystemServices extends Service
{
    /**
     * SystemNotificationServices constructor.
     *
     * @param MessageSystemDao $dao
     */
    public function __construct(MessageSystemDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 站内信列表
     *
     * @param $uid
     *
     * @return array
     */
    public function getMessageSystemList($uid)
    {
        [$page, $limit] = $this->getPageValue();
        $where['is_del'] = 0;
        $where['uid'] = $uid;
        $list = $this->dao->getMessageList($where, '*', $page, $limit);
        $count = $this->dao->getCount($where);
        if (!$list) {
            return ['list' => [], 'count' => 0];
        }
        foreach ($list as &$item) {
            $item['add_time'] = time_tran($item['add_time']);
        }

        return ['list' => $list, 'count' => $count];
    }

    /**
     * 站内信详情
     *
     * @param $where
     *
     * @return array
     */
    public function getInfo($where)
    {
        $info = $this->dao->getOne($where);
        if (!$info || $info['is_del'] == 1) {
            throw new ApiException(100026);
        }
        $info = $info->toArray();
        if ($info['look'] == 0) {
            $this->update($info['id'], ['look' => 1]);
        }
        $info['add_time'] = time_tran($info['add_time']);

        return $info;
    }
}
