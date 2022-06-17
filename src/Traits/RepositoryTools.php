<?php

declare(strict_types=1);

namespace Belief\Hyperf\Traits;

trait RepositoryTools
{
    /**
     * 获取表名称.
     */
    public function getTableName(): string
    {
        return $this->getModel()->getTable();
    }

    /**
     * 通过主键id/ids获取信息.
     *
     * @param array|int $id id/id数组
     * @param array $cols 查询的列
     * @param bool $useCache 是否使用模型缓存
     * @return array 查询信息
     */
    public function find($id, $cols = ['*'], $useCache = false)
    {
        $instance = $this->getModel();

        if ($useCache === true) {
            $modelCache = is_array($id) ? $instance->findManyFromCache($id) : $instance->findFromCache($id);
            return isset($modelCache) && $modelCache ? $modelCache->toArray() : [];
        }

        $query = $instance->query();
        if (is_array($cols) && $cols[0] != '*') {
            $query->select($cols);
        }

        $result = $query->find($id);
        return  $result ? $result->toArray() : [];
    }

    /**
     * 通过主键id/ids指定字段获取信息.
     *
     * @param array|int $id id/id数组
     * @param array $cols 查询字段
     * @return array 查询信息
     */
    public function findForSelect($id, $cols = ['*'])
    {
        $instance = $this->getModel();
        $query = $instance->query();

        if (is_array($cols) && $cols[0] != '*') {
            $query->select($cols);
        }
        $result = $query->find($id);
        return $result ? $result->toArray() : [];
    }

    /**
     * 创建数据.
     *
     * @param array $data 保存数据
     * @param bool $type 是否强制写入，适用于主键是规则生成情况
     * @return array 创建成功数据
     */
    public function saveData($data, $type = false)
    {
        $id = null;
        $result = [];
        $instance = $this->getModel();
        $primaryKey = $instance->getKeyName();
        if (isset($data[$primaryKey]) && $data[$primaryKey] && ! $type) {
            $id = $data[$primaryKey];
            unset($data[$primaryKey]);
            $query = $instance->query()->find($id);
            foreach ($data as $k => $v) {
                $query->{$k} = $v;
            }
            $query->save();
            return $query ? $query->toArray() : [];
        }
        foreach ($data as $k => $v) {
            if ($k === $primaryKey) {
                $id = $v;
            }
            $instance->{$k} = $v;
        }
        $instance->save();
        return $instance ? $instance->toArray() : [];
    }

    /**
     * 新增多条记录.
     */
    public function saveManyData(array $data): bool
    {
        $instance = $this->getModel();
        return $instance->insert($data);
    }

    /**
     * 更新数据表字段数据.
     *
     * @param array $filter 筛选条件
     * @param array $data 更新数据
     * @return array 更新成功数据
     */
    public function updateOneBy($filter, $data)
    {
        $instance = $this->getModel();
        if (is_array($filter) && ! empty($filter)) {
            foreach ($filter as $k => $v) {
                $instance = $this->whereFunc($k, $v, $instance);
            }
        }

        $query = $instance->first();
        foreach ($data as $k => $v) {
            $query->{$k} = $v;
        }
        $query->save();
        return $query ? $query->toArray() : [];
    }

    /**
     * 批量更新数据表字段数据.
     * @param array $filter 筛选条件
     * @param array $data 更新数据
     * @return int 更新成功的条数
     */
    public function updateBy($filter, $data)
    {
        $instance = $this->getModel();
        if (is_array($filter) && ! empty($filter)) {
            foreach ($filter as $k => $v) {
                $instance = $this->whereFunc($k, $v, $instance);
            }
        }
        return $instance->update($data);
    }

    /**
     * 根据条件获取一条结果.
     *
     * @param array $filter 查询条件
     * @param array $cols 显示的字段
     * @param mixed $order_by
     * @return array 查询结果
     */
    public function findOneBy($filter, $cols = ['*'], $order_by = [])
    {
        $instance = $this->getModel();

        if (is_array($filter) && ! empty($filter)) {
            foreach ($filter as $k => $v) {
                $instance = $this->whereFunc($k, $v, $instance);
            }
        }

        if (is_array($cols) && $cols[0] != '*') {
            $instance = $instance->select($cols);
        }

        if (! empty($order_by) || ! is_array($order_by)) {
            foreach ($order_by as $field => $ascdesc) {
                $instance->orderBy($field, $ascdesc);
            }
        }

        $query = $instance->first();

        return empty($query) ? [] : $query->toArray();
    }

    /**
     * 根据条件获取一条结果包含软删除数据.
     *
     * @param array $filter 查询条件
     * @param array $cols 显示的字段
     * @param mixed $order_by
     * @return array 查询结果
     */
    public function findOneByWithTrashed($filter, $cols = ['*'], $order_by = [])
    {
        $instance = $this->getModel()->withTrashed();

        if (is_array($filter) && ! empty($filter)) {
            foreach ($filter as $k => $v) {
                $instance = $this->whereFunc($k, $v, $instance);
            }
        }

        if (is_array($cols) && $cols[0] != '*') {
            $instance = $instance->select($cols);
        }

        if (! empty($order_by) || ! is_array($order_by)) {
            foreach ($order_by as $field => $ascdesc) {
                $instance->orderBy($field, $ascdesc);
            }
        }
        $instance->limit(1);

        $query = $instance->get();

        return $query->isEmpty() ? [] : $query->toArray()[0];
    }

    /**
     * 根据条件获取结果，包含软删除数据.
     *
     * @param array $filter 查询条件
     * @param array $cols 显示的字段
     * @param int $page 页码
     * @param int $page_size 每页数量
     * @param array $order_by 排序方式
     * @param bool $total 是否查询数量
     */
    public function getListWithTrashed($filter, $cols = ['*'], $page = 1, $page_size = 10, $order_by = [], $total = true)
    {
        $instance = $this->getModel()->withTrashed();

        if (is_array($filter) && ! empty($filter)) {
            foreach ($filter as $k => $v) {
                $instance = $this->whereFunc($k, $v, $instance);
            }
        }

        if (is_array($cols) && $cols[0] != '*') {
            $instance = $instance->select($cols);
        }

        if ($total) {
            $count = $this->getCount($filter);
        }

        if (! empty($order_by) || ! is_array($order_by)) {
            foreach ($order_by as $field => $ascdesc) {
                $instance->orderBy($field, $ascdesc);
            }
        }

        if ($page > 0) {
            $instance->offset(($page - 1) * $page_size)->limit($page_size);
        }

        $query = $instance->get();

        $list = empty($query) ? [] : $query->toArray();
        $result = ['list' => $list];
        $total ? $result['total_count'] = $count : [];

        return $result;
    }

    /**
     * 根据条件获取结果.
     *
     * @param array $filter 查询条件
     * @param array $cols 显示的字段
     * @param int $page 页码
     * @param int $page_size 每页数量
     * @param array $order_by 排序方式
     * @param bool $total 是否查询数量
     */
    public function getList($filter, $cols = ['*'], $page = 1, $page_size = 10, $order_by = [], $total = true)
    {
        $instance = $this->getModel();

        if (is_array($filter) && ! empty($filter)) {
            foreach ($filter as $k => $v) {
                $instance = $this->whereFunc($k, $v, $instance);
            }
        }

        if (is_array($cols) && $cols[0] != '*') {
            $instance = $instance->select($cols);
        }

        if ($total) {
            $count = $this->getCount($filter);
        }

        if (! empty($order_by) || ! is_array($order_by)) {
            foreach ($order_by as $field => $ascdesc) {
                $instance->orderBy($field, $ascdesc);
            }
        }

        if ($page > 0) {
            $instance->offset(($page - 1) * $page_size)->limit($page_size);
        }

        $query = $instance->get();

        $list = empty($query) ? [] : $query->toArray();
        $result = ['list' => $list];

        if ($total) {
            $result['total_count'] = $count;
        }

        return $result;
    }

    /**
     * 根据条件获取条数.
     *
     * @param array $filter 查询条件
     * @return int 查询条数
     */
    public function getCount(array $filter): int
    {
        $instance = $this->getModel();

        if (is_array($filter) && ! empty($filter)) {
            foreach ($filter as $k => $v) {
                $instance = $this->whereFunc($k, $v, $instance);
            }
        }
        $count = $instance->count();

        return (int) ($count ?: 0);
    }

    /**
     * 根据ids删除.
     *
     * @param array $ids 删除的主键ids
     * @return int 删除行数
     */
    public function deleteByIds($ids)
    {
        $instance = $this->getModel();

        return $instance->destroy($ids);
    }

    /**
     * 根据条件删除.
     *
     * @param array $where 删除的条件
     * @return int 删除行数
     */
    public function deleteByWhere($where = [])
    {
        if (! $where) {
            return 0;
        }
        $instance = $this->getModel();

        foreach ($where as $k => $v) {
            $instance = $instance->whereFunc($k, $v, $instance);
        }

        return $instance->delete();
    }

    /**
     * 软删除恢复.
     *
     * @param array $where 删除的条数
     * @return int
     */
    public function softDeleteRestoreByWhere($where = [])
    {
        if (! $where) {
            return 0;
        }
        $instance = $this->getModel();

        $instance->withTrashed();

        foreach ($where as $k => $v) {
            $instance = $instance->whereFunc($k, $v, $instance);
        }

        return $instance->restore();
    }

    /**
     * 根据条件获取某个字段总和.
     * @param $column
     * @return mixed
     */
    public function getSum(array $filter, $column)
    {
        $instance = $this->getModel();

        if (is_array($filter) && ! empty($filter)) {
            foreach ($filter as $k => $v) {
                $instance = $this->whereFunc($k, $v, $instance);
            }
        }
        return $instance->sum($column);
    }

    /**
     * where用法.
     *
     * @param string $key where类型key
     * @param mixed $value
     * @param mixed $instance
     * @return null|string where方法名称
     */
    public function whereFunc($key, $value, $instance)
    {
        if (is_array($value) && empty($value)) {
            return $instance;
        }

        $whereType = (string) (is_array($value) ? strtolower($value[0]) : $value);

        switch ($whereType) {
            case 'or':
                $instance = $instance->orWhere($key, $value[1], $value[2] ?? null);
                break;
            case 'null':
                $instance = $instance->whereNull($key);
                break;
            case 'in':
                $instance = $instance->whereIn($key, $value[1]);
                break;
            case 'not_in':
                $instance = $instance->whereNotIn($key, $value[1]);
                break;
            case 'between':
                $instance = $instance->whereBetween($key, $value[1]);
                break;
            case 'not_between':
                $instance = $instance->whereNotBetween($key, $value[1]);
                break;
            case 'json_contains':
                $instance = $instance->whereJsonContains($key, $value[1]);
                break;
            case 'json_length':
                $instance = $instance->whereJsonLength($key, $value[1], $value[2]);
                break;
            default:
                if (is_array($value)) {
                    $instance = $instance->where($key, $value[0], $value[1] ?? null);
                } else {
                    $instance = $instance->where($key, $value);
                }
                break;
        }

        return $instance;
    }
}
