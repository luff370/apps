<?php

namespace App\Dao\Other;

use App\Dao\BaseDao;
use App\Models\AppAgreement;

/**
 * Class AgreementDao
 *
 * @package App\Dao\Other
 */
class AgreementDao extends BaseDao
{
    /**
     * @return string
     */
    public function setModel(): string
    {
        return AppAgreement::class;
    }

    /**
     * 修改协议内容
     */
    public function saveAgreement(array $agreement, $id = 0)
    {
        if (!$agreement) {
            return false;
        }
        if ($id) {
            return $this->getModel()->update($agreement, ['id' => $id]);
        }

        return $this->getModel()->save($agreement);
    }
}
