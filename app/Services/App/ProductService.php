<?php

namespace App\Services\App;

use App\Services\Service;
use App\Dao\App\ProductDao;
use App\Models\MemberProduct;
use App\Exceptions\AdminException;

class ProductService extends Service
{
    /**
     * StoreBrandServices constructor.
     */
    public function __construct(ProductDao $dao)
    {
        $this->dao = $dao;
    }

    public function save($data)
    {
        if (isset($data['platform'])) {
            $data['platform'] = strtolower((string)$data['platform']);
        }

        if (!empty($data['id'])) {
            return $this->update($data['id'], $data);
        }

        return $this->dao->newQuery()->create($data);
    }

    public function tidyListData($list)
    {
        foreach ($list as &$item) {
            $item['lang'] = MemberProduct::$languages[$item['lang']] ?? '';
            $item['platform_name'] = MemberProduct::platformName((string)$item['platform']);
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
