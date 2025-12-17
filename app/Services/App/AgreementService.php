<?php

namespace App\Services\App;

use App\Services\Service;
use App\Models\SystemApp;
use App\Models\AppAgreement;
use App\Dao\Other\AgreementDao;
use App\Exceptions\AdminException;

/**
 * 协议service
 * Class AppsService
 */
class AgreementService extends Service
{
    /**
     * StoreBrandServices constructor.
     */
    public function __construct(AgreementDao $dao)
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
        $typesMap = AppAgreement::typesMap();
        $channelsMap = SystemApp::marketChannelsMap();
        foreach ($list as &$item) {
            $item['url'] = url(sprintf('/agreement/%s/%s/%s', $item['type'], $item['app_id'], $item['platform']));
            $item['type_name'] = $typesMap[$item['type']] ?? '';
            $item['platform'] = $channelsMap[$item['platform']] ?? '全部';
            $item['version'] = $item['version'] == 'all' ? '全部' : $item['version'];
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
