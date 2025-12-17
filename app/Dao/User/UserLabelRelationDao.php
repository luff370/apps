<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserLabelRelation;

/**
 *
 * Class UserLabelRelationDao
 *
 * @package App\Dao\User
 */
class UserLabelRelationDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserLabelRelation::class;
    }

    /**
     * 获取用户个标签列表按照用户id进行分组
     *
     * @param array $uids
     *
     * @return array
     */
    public function getLabelList(array $uids)
    {
        return $this->search(['uid' => $uids])->with('label')->get()->toArray();
    }
}
