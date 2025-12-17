<?php

namespace App\Dao;

use Illuminate\Support\Str;
use App\Support\Traits\QueryTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Class BaseDao
 *
 * @package app\dao
 */
abstract class BaseDao
{
    use QueryTrait;

    /**
     * 当前表名别名
     *
     * @var string
     */
    protected $alias;

    /**
     * join表别名
     *
     * @var string
     */
    protected $joinAlis;

    /**
     * 获取当前模型
     *
     * @return string
     */
    abstract protected function setModel(): string;

    /**
     * 获取模型
     *
     * @return Model
     */
    public function getModel(): Model
    {
        return app($this->setModel());
    }

    public function newQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->getModel()->newQuery();
    }

    /**
     * 获取主键
     *
     * @return string
     */
    protected function getPk(): string
    {
        return $this->getModel()->getKeyName();
    }

    /**
     * 读取数据条数
     *
     * @param array $where
     *
     * @return int
     */
    public function count(array $where = []): int
    {
        return $this->search($where)->count();
    }

    /**
     * 获取某些条件数据
     *
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     * @param bool $search
     *
     */
    public function selectList(array $where, string $field = '*', int $page = 0, int $limit = 0, string $order = '', bool $search = false, $with = [])
    {
        if ($search) {
            $model = $this->search($where);
        } else {
            $model = $this->getModel()->newQuery()->where($where);
        }

        return $model->selectRaw($field)->when(!empty($with), function ($query) use ($with) {
            $query->with($with);
        })->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->when($order !== '', function ($query) use ($order) {
            $query->orderByRaw($order);
        })->get();
    }

    /**
     * 获取某些条件总数
     *
     * @param array $where
     *
     * @return int
     */
    public function getCount(array $where): int
    {
        // return $this->getModel()->newQuery()->where($where)->count();

        return $this->count($where);
    }

    public function getAll(array $args = [], array $fields = ['*'], array $orders = [], $with = [], int $limit = 100, int $page = 0): \Illuminate\Database\Eloquent\Collection|array
    {
        $query = $this->search($args)->select($fields)->with($with);
        // $query = $this->queryArgs($query, $args);
        $query = $this->queryOrders($query, $orders);
        $query = $this->queryPage($query, $limit, $page);

        return $query->get();
    }

    /**
     * 获取一条数据
     *
     * @param $id
     * @param array $field
     * @param array $with
     *
     * @return Model|null
     */
    public function get($id, array $field = ['*'], array $with = []): ?Model
    {
        if (is_array($id)) {
            $where = $id;
        } else {
            $where = [$this->getPk() => $id];
        }

        return $this->getModel()->newQuery()
            ->select($field)
            ->where($where)
            ->when(count($with), function ($query) use ($with) {
                $query->with($with);
            })
            ->first();
    }

    /**
     * 获取一条数据
     *
     * @param $id
     * @param array $field
     *
     * @return array
     */
    public function getRowByCache($id, array $field = ['*']): array
    {
        $cacheKey = $this->getModel()->getTable() . ":$id";
        $data = json_decode(cache($cacheKey), true);

        if (empty($data)) {
            $data = $this->get($id, $field);
            if ($data) {
                $data = $data->toArray();
                cache()->put($cacheKey, json_encode($data), 3600 * 24);
            }
        }

        return $data ?? [];
    }

    /**
     * 清除缓存
     */
    public function delCacheById($id): bool
    {
        $cacheKey = $this->getModel()->getTable() . ":$id";

        return cache()->forget($cacheKey);
    }

    /**
     * 查询一条数据是否存在
     *
     * @param $map
     * @param string $field
     *
     * @return bool 是否存在
     */
    public function be($map, string $field = ''): bool
    {
        if (!is_array($map) && empty($field)) {
            $field = $this->getPk();
        }
        $map = !is_array($map) ? [$field => $map] : $map;

        return 0 < $this->getModel()->newQuery()->where($map)->count();
    }

    /**
     * 根据条件获取一条数据
     */
    public function getOne(array $where, string $field = '*', array $with = [])
    {
        $field = explode(',', $field);

        return $this->get($where, $field, $with);
    }

    /**
     * 获取单个字段值
     */
    public function value(array $where, string $field = '')
    {
        $pk = $this->getPk();

        return $this->getModel()->newQuery()->where($where)->value($field ?: $pk);
    }

    /**
     * 获取某个字段数组
     *
     * @param array $where
     * @param string $field
     * @param string $key
     *
     * @return array
     */
    public function getColumn(array $where, string $field, string $key = ''): array
    {
        return $this->getModel()->newQuery()->where($where)->pluck($field, empty($key) ? null : $key)->toArray();
    }

    /**
     * @param array $where
     * @param array|string[] $fields
     * @param string $key
     *
     * @return array
     */
    public function getAllByKey(array $where, array $fields = ['*'], string $key = 'id'): array
    {
        return $this->getModel()->newQuery()->where($where)->select($fields)->get()->keyBy($key)->toArray();
    }

    /**
     * 删除
     */
    public function delete($id, string $key = null)
    {
        if (is_array($id)) {
            $where = $id;
        } else {
            $where = [is_null($key) ? $this->getPk() : $key => $id];
        }

        return $this->getModel()->newQuery()->where($where)->delete();
    }

    /**
     * 软删除
     */
    public function softDel($id, string $key = null): int
    {
        if (is_array($id)) {
            $where = $id;
        } else {
            $where = [is_null($key) ? $this->getPk() : $key => $id];
        }

        return $this->getModel()->newQuery()->where($where)->update(['is_del' => 1]);
    }

    /**
     * 更新数据
     *
     * @param int|string|array $id
     * @param array $data
     * @param string $key
     *
     * @return int
     */
    public function update($id, array $data, string $key = ''): int
    {
        if (is_array($id)) {
            $where = $id;
        } else {
            $where = [empty($key) ? $this->getPk() : $key => $id];
        }

        return $this->getModel()->newQuery()->where($where)->update($data);
    }

    /**
     * 批量更新数据
     *
     * @param array $ids
     * @param array $data
     * @param string|null $key
     *
     * @return int
     */
    public function batchUpdate(array $ids, array $data, string $key = null): int
    {
        return $this->getModel()->newQuery()->whereIn(is_null($key) ? $this->getPk() : $key, $ids)->update($data);
    }

    /**
     * 插入数据
     *
     * @param array $data
     *
     * @return Model
     */
    public function save(array $data): Model
    {
        return $this->getModel()->newQuery()->create($data);
    }

    /**
     * 插入数据
     *
     * @param array $data
     *
     * @return bool
     */
    public function saveAll(array $data): bool
    {
        logger('-------saveAll--------', $data);

        return $this->getModel()->newQuery()->insert($data);
    }

    /**
     * 搜索
     */
    public function search(array $where = []): \Illuminate\Database\Eloquent\Builder
    {
        $model = $this->getModel();
        $query = $model->newQuery();
        $fields = $model->getFillable();

        if ($where) {
            // 检测搜索器
            foreach ($where as $fieldName => $value) {
                $method = 'search' . Str::studly($fieldName) . 'Attr';
                if (method_exists($model, $method)) {
                    $model->$method($query, $value);
                } elseif (in_array($fieldName, $fields) && $value !== '') {
                    $query->where($fieldName, $value);
                }
            }
        }

        return $query;
    }

    /**
     * 求和
     *
     * @param array $where
     * @param string $field
     * @param bool $search
     *
     * @return int|mixed
     */
    public function sum(array $where, string $field, bool $search = false)
    {
        if ($search) {
            return $this->search($where)->sum($field);
        } else {
            return $this->getModel()->newQuery()->where($where)->sum($field);
        }
    }

    /**
     * 高精度加法
     *
     * @param $key
     * @param string $incField
     * @param string $inc
     * @param string|null $keyField
     * @param int $acc
     *
     * @return bool
     */
    public function bcInc($key, string $incField, string $inc, string $keyField = null, int $acc = 2): bool
    {
        return $this->bc($key, $incField, $inc, $keyField, 1);
    }

    /**
     * 高精度 减法
     *
     * @param $key
     * @param string $decField
     * @param string $dec
     * @param string|null $keyField
     * @param int $acc
     *
     * @return bool
     */
    public function bcDec($key, string $decField, string $dec, string $keyField = null, int $acc = 2): bool
    {
        return $this->bc($key, $decField, $dec, $keyField, 2);
    }

    /**
     * 高精度计算并保存
     *
     * @param $key
     * @param string $incField
     * @param string $inc
     * @param string|null $keyField
     * @param int $type
     * @param int $acc
     *
     * @return bool
     */
    public function bc($key, string $incField, string $inc, string $keyField = null, int $type = 1, int $acc = 2)
    {
        if ($keyField === null) {
            $result = $this->get($key);
        } else {
            $result = $this->getOne([$keyField => $key]);
        }
        if (!$result) {
            return false;
        }
        $new = 0;
        if ($type === 1) {
            $new = bcadd($result[$incField], $inc, $acc);
        } else {
            if ($type === 2) {
                if ($result[$incField] < $inc) {
                    return false;
                }
                $new = bcsub($result[$incField], $inc, $acc);
            }
        }
        $result->{$incField} = $new;

        return false !== $result->save();
    }

    /**
     * 减库存加销量
     *
     * @param array $where
     * @param int $num
     * @param string $stock
     * @param string $sales
     *
     * @return false
     */
    public function decStockIncSales(array $where, int $num, string $stock = 'stock', string $sales = 'sales'): bool
    {
        $isQuota = false;
        if (isset($where['type']) && $where['type']) {
            $isQuota = true;
            if (count($where) == 2) {
                unset($where['type']);
            }
        }
        $field = $isQuota ? 'stock,quota' : 'stock';
        $product = $this->getModel()->newQuery()->where($where)->select($field)->first();
        if ($product) {
            return $this->getModel()->newQuery()->where($where)->when($isQuota, function ($query) use ($num) {
                $query->dec('quota', $num);
            })->dec($stock, $num)->inc($sales, $num)->update();
        }

        return false;
    }

    /**
     * 加库存减销量
     *
     * @param array $where
     * @param int $num
     * @param string $stock
     * @param string $sales
     *
     * @return bool
     */
    public function incStockDecSales(array $where, int $num, string $stock = 'stock', string $sales = 'sales'): bool
    {
        $isQuota = false;
        if (isset($where['type']) && $where['type']) {
            $isQuota = true;
            if (count($where) == 2) {
                unset($where['type']);
            }
        }
        $salesOne = $this->getModel()->newQuery()->where($where)->value($sales);
        if ($salesOne) {
            $salesNum = $num;
            if ($num > $salesOne) {
                $salesNum = $salesOne;
            }

            return $this->getModel()->newQuery()->where($where)->when($isQuota, function ($query) use ($num) {
                $query->inc('quota', $num);
            })->inc($stock, $num)->dec($sales, $salesNum)->update();
        }

        return true;
    }

    /**
     * 获取条件数据中的某个值的最大值
     *
     * @param array $where
     * @param string $field
     *
     * @return mixed
     */
    public function getMax(array $where = [], string $field = '')
    {
        return $this->getModel()->newQuery()->where($where)->max($field);
    }

    /**
     * 获取指定条件下的包邮列表
     *
     * @param array $where
     * @param bool $group
     *
     * @return array
     */
    public function getUniqidList(array $where, bool $group = true, $joinField = 'city_id'): array
    {
        $tableName = $this->getModel()->getTable();
        $cityTable = SystemCity::query()->getModel()->getTable();

        return $this->getModel()->newQuery()
            ->select([$this->alias . '.province_id', $this->joinAlis . '.name', $this->alias . '.city_id'])
            ->from("{$tableName} as {$this->alias}")
            ->join("{$cityTable} as {$this->joinAlis}", "{$this->alias}.{$joinField}", '=', "{$this->joinAlis}.area_code")
            ->when(isset($where['uniqid']), function ($query) use ($where) {
                $query->where($this->alias . '.uniqid', $where['uniqid']);
            })->when(isset($where['province_id']), function ($query) use ($where) {
                $query->where($this->alias . '.province_id', $where['province_id']);
            })->when($group, function ($query) {
                $query->groupBy($this->alias . '.province_id');
            })
            ->get()
            ->toArray();
    }
}
