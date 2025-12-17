<?php

namespace App\Dao\System\Config;

use App\Dao\BaseDao;
use App\Models\SystemGroupData;

/**
 * 组合数据
 * Class SystemGroupDataDao
 *
 * @package App\Dao\System\Config
 */
class SystemGroupDataDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemGroupData::class;
    }

    /**
     * 获取组合数据列表
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     */
    public function getGroupDataList(array $where, int $page, int $limit): array
    {
        return $this->search($where)->offset(($page - 1) * $limit)->limit($limit)->orderByRaw('sort desc,id DESC')->get()->toArray();
    }

    /**
     * 获取某个gid下的组合数据
     *
     * @param int $gid
     * @param int $limit
     *
     * @return array
     */
    public function getGroupDate(int $gid, int $limit = 0): array
    {
        return $this->search(['gid' => $gid, 'status' => 1])
            ->when($limit, function ($query) use ($limit) {
                $query->limit($limit);
            })->selectRaw('value,id')
            ->orderByRaw('sort DESC,id asc')
            ->get()
            ->toArray();
    }

    /**
     * 根据id获取秒杀数据
     *
     * @param array $ids
     * @param string $field
     *
     * @return array
     */
    public function idByGroupList(array $ids, string $field)
    {
        return $this->getModel()->newQuery()->whereIn('id', $ids)->select($field)->get()->toArray();
    }

    /**
     * 根据gid删除组合数据
     *
     * @param int $gid
     *
     * @return bool
     */
    public function delGroupDate(int $gid)
    {
        return $this->getModel()->newQuery()->where('gid', $gid)->delete();
    }
}
