<?php

namespace App\Services\App;

use App\Services\Service;
use App\Models\SystemApp;
use App\Models\AppAgreement;
use App\Models\AppAdvertisement;
use App\Dao\App\AdvertisementDao;
use App\Exceptions\AdminException;

/**
 * 协议service
 * Class AppsService
 */
class AdvertisementService extends Service
{
    /**
     * StoreBrandServices constructor.
     */
    public function __construct(AdvertisementDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * @throws AdminException
     */
    public function save($data)
    {
        if (!empty($data['id'])) {
            $info = $this->getRow($data['id']);
            if (empty($info)) {
                throw new AdminException("广告配置获取失败，请重试");
            }

            return $info->update($data);
        }

        return $this->dao->newQuery()->create($data);
    }

    public function tidyListData($list)
    {
        $typesMap = AppAdvertisement::typesMap();
        $channelsMap = SystemApp::marketChannelsMap();
        foreach ($list as &$item) {
            $item['type_name'] = $typesMap[$item['type']] ?? '';
            $item['market_channel'] = $channelsMap[$item['market_channel']] ?? '全部';
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
