<?php

namespace App\Dao\System\Statistics;

use App\Dao\BaseDao;
use App\Models\CapitalFlow;

class CapitalFlowDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return CapitalFlow::class;
    }

    /**
     * 资金流水
     *
     * @param $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getList($where, $page = 0, $limit = 0)
    {
        return $this->search($where)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->orderByRaw('id desc')->get()->toArray();
    }

    /**
     * 账单记录
     *
     * @param $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getRecordList($where, $page = 0, $limit = 0)
    {
        $model = $this->search($where)
            ->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
                $timeUnix = '%d';
                switch ($where['type']) {
                    case "day" :
                        $timeUnix = "%d";
                        break;
                    case "week" :
                        $timeUnix = "%u";
                        break;
                    case "month" :
                        $timeUnix = "%m";
                        break;
                }
                $query->selectRaw("FROM_UNIXTIME(add_time,'$timeUnix') as day,sum(if(price >= 0,price,0)) as income_price,sum(if(price < 0,price,0)) as exp_price,add_time,group_concat(id) as ids");
                $query->groupBy("FROM_UNIXTIME(add_time, '$timeUnix')");
            });
        $count = $model->count();
        $list = $model->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->orderByRaw('add_time desc')->get()->toArray();

        return compact('list', 'count');
    }
}
