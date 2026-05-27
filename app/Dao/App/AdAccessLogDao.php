<?php

namespace App\Dao\App;

use App\Dao\BaseDao;
use App\Models\AdAccessLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdAccessLogDao extends BaseDao
{
    protected function setModel(): string
    {
        return AdAccessLog::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        foreach (AdAccessLog::groupFields() as $field) {
            if (isset($where[$field]) && $where[$field] !== '') {
                $query->where($field, $where[$field]);
            }
        }

        if (isset($where['status']) && $where['status'] !== '') {
            $query->where('status', $where['status']);
        }

        if (!empty($where['user_id'])) {
            $query->where('user_id', $where['user_id']);
        }

        if (!empty($where['uuid'])) {
            $query->where('uuid', $where['uuid']);
        }

        if (!empty($where['ad_id'])) {
            $query->where('ad_id', $where['ad_id']);
        }

        if (!empty($where['ad_code'])) {
            $query->where('ad_code', $where['ad_code']);
        }

        if (!empty($where['time'])) {
            $this->searchDate($query, 'created_at', $where['time']);
        }

        if (!empty($where['keyword'])) {
            $keyword = $where['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('uuid', $keyword)
                    ->orWhere('ad_code', 'like', $keyword . '%')
                    ->orWhere('version', 'like', $keyword . '%')
                    ->orWhere('error_code', 'like', $keyword . '%')
                    ->orWhere('error_msg', 'like', '%' . $keyword . '%');
            });
        }

        return $query;
    }

    /**
     * 分组统计分页列表
     */
    public function getStatByPage(array $where, int $page, int $limit): array
    {
        $groupFields = AdAccessLog::groupFields();
        $select = array_map(fn ($field) => $field, $groupFields);
        $select[] = DB::raw('COUNT(*) as total_count');
        $select[] = DB::raw('SUM(CASE WHEN status = ' . AdAccessLog::STATUS_SUCCESS . ' THEN 1 ELSE 0 END) as success_count');
        $select[] = DB::raw('SUM(CASE WHEN status != ' . AdAccessLog::STATUS_SUCCESS . ' THEN 1 ELSE 0 END) as fail_count');

        $query = $this->search($where)->select($select)->groupBy($groupFields);

        $countQuery = DB::table(DB::raw('(' . $query->toSql() . ') as ad_access_stat'))
            ->mergeBindings($query->getQuery());
        $count = $countQuery->count();

        $list = [];
        if ($count > 0) {
            $list = $query->orderByDesc('total_count')
                ->forPage($page, $limit)
                ->get()
                ->toArray();
        }

        return compact('list', 'count');
    }
}
