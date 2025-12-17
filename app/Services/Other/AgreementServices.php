<?php

namespace App\Services\Other;

use App\Services\Service;
use App\Dao\Other\AgreementDao;
use App\Exceptions\AdminException;

/**
 * Class AgreementServices
 *
 * @package App\Services\Other
 */
class AgreementServices extends Service
{
    public function __construct(AgreementDao $dao)
    {
        $this->dao = $dao;
    }

    /** 修改协议内容
     *
     * @param array $where
     * @param $content
     *
     * @return bool|\crmeb\basic\BaseModel
     */
    public function saveAgreement(array $data, $id = 0)
    {
        if (!$data) {
            return false;
        }
        if (!isset($data['type']) || !$data['type'] || $data['type'] == 0) {
            throw new AdminException(400548);
        }
        if (!isset($data['title']) || !$data['title']) {
            throw new AdminException(400549);
        }
        if (!isset($data['content']) || !$data['content']) {
            throw new AdminException(400550);
        }
        if (!$id) {
            $getOne = $this->getAgreementBytype($data['type']);
            if ($getOne) {
                throw new AdminException(400551);
            }
        }

        return $this->dao->saveAgreement($data, $id);
    }

    /**获取会员协议
     *
     * @param $type
     *
     * @return array|\think\Model|null
     */
    public function getAgreementBytype($type)
    {
        if (!$type) {
            return [];
        }
        $data = $this->dao->getOne(['type' => $type]);

        return $data ? $data->toArray() : [];
    }
}
