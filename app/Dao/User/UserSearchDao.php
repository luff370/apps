<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserSearch;

/**
 * 用户搜索
 * Class UserSearchDao
 *
 * @package App\Dao\User
 */
class UserSearchDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserSearch::class;
    }

    /**
     * 获取列表
     *
     * @param array $where
     * @param string $order
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getList(array $where = [], string $order = 'id desc', int $page = 0, int $limit = 0): array
    {
        return $this->search($where)->orderByRaw($order)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->get()->toArray();
    }

    /**
     * * 获取全局|用户某个关键词搜素结果
     *
     * @param int $uid
     * @param string $keyword 关键词
     * @param int $preTime 多长时间内认为结果集有效
     *
     * @return array|\Illuminate\Database\Eloquent\Model
     */
    public function getKeywordResult(int $uid, string $keyword, int $preTime = 7200)
    {
        if (!$keyword) {
            return [];
        }
        $where = ['keyword' => $keyword];
        if ($uid) {
            $where['uid'] = $uid;
        }

        return $this->search($where)->when($uid && $preTime == 0, function ($query) {
                $query->where('is_del', 0);
            })->when($preTime > 0, function ($query) use ($preTime) {
                $query->where('add_time', '>', time() - $preTime);
            })->orderByRaw('add_time desc,id desc')->first() ?? [];
    }
}
