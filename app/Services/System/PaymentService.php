<?php

namespace App\Services\System;

use DB;
use App\Services\Service;
use App\Models\SystemPayment;
use App\Dao\System\PaymentDao;

class PaymentService extends Service
{
    /**
     * ContentService constructor.
     */
    public function __construct(PaymentDao $dao)
    {
        $this->dao = $dao;
    }

    public function tidyListData($list)
    {
        $typesMap = SystemPayment::typesMap();
        foreach ($list as &$item) {
            $item['type_name'] = $typesMap[$item['type']] ?? '';
        }

        return $list;
    }

    /**
     * 新增编辑文章
     *
     * @param array $data
     *
     * @return mixed
     * @throws \Throwable
     */
    public function save(array $data): mixed
    {
        return DB::transaction(function () use ($data) {
            if (!empty($data['id'])) {
                $info = $this->dao->newQuery()->findOrFail($data['id']);
                $info->fill($data);

                $info->save();
            } else {
                $info = $this->dao->save($data);
            }

            return $info;
        });
    }



}
