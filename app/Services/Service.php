<?php

namespace App\Services;

use App\Support\Traits\QueryTrait;
use App\Support\Traits\ServicesTrait;
use App\Support\Traits\CommonArgsTrait;

/**
 * Trait Services
 * @method \Illuminate\Database\Eloquent\Model|null get($id, array $field = []) 获取一条数据
 * @method \Illuminate\Database\Eloquent\Model|null getOne(array $where, string $field = '*') 获取一条数据（不走搜素器）
 * @method string|null batchUpdate(array $ids, array $data, string $key = '') 批量修改
 * @method float sum(array $where, string $field, bool $search = false) 求和
 * @method mixed update($id, array $data, string $field = '') 修改数据
 * @method bool be($map, string $field = '') 查询一条数据是否存在
 * @method mixed value(array $where, string $field) 获取指定条件下的数据
 * @method int count(array $where = []) 读取数据条数
 * @method int getCount(array $where = []) 获取某些条件总数（不走搜素器）
 * @method array getColumn(array $where, string $field, string $key = '') 获取某个字段数组（不走搜素器）
 * @method array getAllByKey(array $where, array $fields = ['*'], string $key = 'id')
 * @method mixed delete($id, ?string $key = null) 删除
 * @method mixed softDel($id, ?string $key = null) 软删除
 * @method \Illuminate\Database\Eloquent\Model save(array $data) 保存数据
 * @method bool saveAll(array $data) 批量保存数据
 * @method bool bcInc($key, string $incField, string $inc, string $keyField = null, int $acc = 2) 高精度加法
 * @method bool bcDec($key, string $decField, string $dec, string $keyField = null, int $acc = 2) 高精度 减法
 * @method mixed decStockIncSales(array $where, int $num, string $stock = 'stock', string $sales = 'sales') 减库存加销量
 * @method mixed incStockDecSales(array $where, int $num, string $stock = 'stock', string $sales = 'sales') 加库存减销量
 * @method mixed getAll(array $args = [], array $fields = ['*'], array $orders = [], $with = [], int $limit = 100, int $page = 0) 根据搜索条件获取数据
 * @method array getRowByCache($id, array $field = ['*']) 通过缓存获取一条数据
 * @method bool delCacheById($id) 通过id清除缓存
 */
class Service
{
    use ServicesTrait, QueryTrait, CommonArgsTrait;

    /**
     * @var int
     */
    protected $perPage = 15;

    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * @var \App\Dao\BaseDao
     */
    protected $dao;

    public function search($filter = []): \Illuminate\Database\Eloquent\Builder
    {
        return $this->dao->search($filter);
    }

    public function getPageValue(): array
    {
        $page = (int)request()->get('page', 1);
        $limit = (int)request()->get('limit', $this->perPage);

        return [$page, $limit];
    }

    /**
     * 分页获取列表数据
     *
     * @return array
     */
    public function getAllByPage(array $args, $fields = ['*'], $orders = ['id' => 'desc'], $with = []): array
    {
        [$page, $limit] = $this->getPageValue();
        $count = $this->dao->count($args);
        $list = [];
        if ($count > 0) {
            $list = $this->dao->getAll($args, $fields, $orders, $with, $limit, $page);
            $list = $this->tidyListData($list);
        }

        return compact('list', 'count');
    }

    public function tidyListData($list)
    {
        return $list;
    }

    /**
     * 通过ID获取一条数据
     *
     * @param $id
     * @param array $field
     * @param array $with
     *
     * @return mixed
     */
    public function getRow($id, array $field = ['*'], array $with = [])
    {
        return $this->dao->get($id, $field, $with);
    }

    /**
     * 密码hash加密
     *
     * @param string $password
     *
     * @return false|string|null
     */
    public function passwordHash(string $password)
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * 格式化select下拉表单数据
     *
     * @param array $idNameArr key=>name 数组
     *
     * @return array
     */
    public function toFormSelect(array $idNameArr, $isSameName = false): array
    {
        $result = [];
        foreach ($idNameArr as $id => $name) {
            $result[] = [
                'label' => $name,
                'value' => $isSameName ? $name : $id,
            ];
        }

        return $result;
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        // TODO: Implement __call() method.
        return call_user_func_array([$this->dao, $name], $arguments);
    }
}
