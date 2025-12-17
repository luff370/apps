<?php

namespace App\Services\App;

use App\Services\Service;
use App\Models\AppPayment;
use App\Dao\App\PaymentDao;
use App\Exceptions\AdminException;

class PaymentService extends Service
{
    /**
     * StoreBrandServices constructor.
     */
    public function __construct(PaymentDao $dao)
    {
        $this->dao = $dao;
    }

    public function save($data)
    {
        if (!empty($data['id'])) {
            return $this->update($data['id'], $data);
        }

        return $this->dao->newQuery()->create($data);
    }

    public function tidyListData($list)
    {
        $channelsMap = AppPayment::payChannelMap();
        $typesMap = AppPayment::payTypeMap();
        foreach ($list as &$item) {
            $item['pay_type_name'] = $typesMap[$item['pay_type']] ?? '';
            $item['pay_channel_name'] = $channelsMap[$item['pay_channel']] ?? '';
        }

        return $list;
    }

    /**
     * 修改协议状态
     *
     * @param int $id
     *
     * @return boolean
     * @throws \App\Exceptions\AdminException
     */
    public function setStatus(int $id, $status): bool
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(400594);
        }

        $updateData = ['status' => $status];
        $this->dao->update($id, $updateData);

        return true;
    }
}
