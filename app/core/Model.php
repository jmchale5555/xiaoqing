<?php

namespace Model;

trait Model
{
    use \Model\Database;

    public $errors          = [];

    public function paginate(
        array $where = [],
        int $page = 1,
        int $perPage = 20,
        string $orderBy = 'id',
        string $orderDir = 'desc',
        ?array $allowedOrderColumns = null
    ): array
    {
        $page = $page < 1 ? 1 : $page;
        $perPage = $perPage < 1 ? 1 : $perPage;
        $perPage = $perPage > 100 ? 100 : $perPage;

        [$orderBy, $orderDir] = $this->sanitizePaginationOrder($orderBy, $orderDir, $allowedOrderColumns);
        [$whereSql, $params] = $this->buildPaginateWhere($where);

        $offset = ($page - 1) * $perPage;

        $countQuery = "select count(*) as total from $this->table" . $whereSql;
        $countRows = $this->query($countQuery, $params);
        $total = (int)($countRows[0]->total ?? 0);

        $dataQuery = "select * from $this->table" . $whereSql . " order by {$orderBy} {$orderDir} limit {$perPage} offset {$offset}";
        $items = $this->query($dataQuery, $params);
        if (!is_array($items))
        {
            $items = [];
        }

        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $totalPages > 0 ? $page < $totalPages : false,
                'has_prev' => $page > 1,
            ],
        ];
    }

    public function all(
        int $limit = 70,
        int $offset = 0,
        string $orderBy = 'id',
        string $orderDir = 'desc',
        ?array $allowedOrderColumns = null
    )
    {
        $limit = $this->normalizeLimit($limit, 70, 1000);
        $offset = $this->normalizeOffset($offset);
        [$orderBy, $orderDir] = $this->sanitizePaginationOrder($orderBy, $orderDir, $allowedOrderColumns ?? ['id']);

        $query = "select * from $this->table order by {$orderBy} {$orderDir} limit {$limit} offset {$offset}";

        return $this->query($query);
    }

    public function where(
        array $where_array = [],
        array $where_not_array = [],
        array $greater_than_array = [],
        int $limit = 70,
        int $offset = 0,
        string $orderBy = 'id',
        string $orderDir = 'desc',
        ?array $allowedOrderColumns = null
    ): array|bool
    {
        $limit = $this->normalizeLimit($limit, 70, 1000);
        $offset = $this->normalizeOffset($offset);
        [$orderBy, $orderDir] = $this->sanitizePaginationOrder($orderBy, $orderDir, $allowedOrderColumns ?? ['id']);

        $query = "select * from $this->table";
        $clauses = [];
        $data = [];

        if (!empty($where_array))
        {
            foreach ($where_array as $key => $value)
            {
                if (!$this->isSafeIdentifier((string)$key))
                {
                    continue;
                }

                $param = 'eq_' . $key;
                $clauses[] = $key . " = :" . $param;
                $data[$param] = $value;
            }
        }

        if (!empty($where_not_array))
        {
            foreach ($where_not_array as $key => $value)
            {
                if (!$this->isSafeIdentifier((string)$key))
                {
                    continue;
                }

                $param = 'neq_' . $key;
                $clauses[] = $key . " != :" . $param;
                $data[$param] = $value;
            }
        }

        if (!empty($greater_than_array))
        {
            foreach ($greater_than_array as $key => $value)
            {
                if (!$this->isSafeIdentifier((string)$key))
                {
                    continue;
                }

                $param = 'gt_' . $key;
                $clauses[] = $key . " > :" . $param;
                $data[$param] = $value;
            }
        }

        if (!empty($clauses))
        {
            $query .= ' where ' . implode(' AND ', $clauses);
        }

        $query .= " order by {$orderBy} {$orderDir} limit {$limit} offset {$offset}";

        return $this->query($query, $data);
    }

    // public function where($data, $data_not = [])
    // {
    //     $keys = array_keys($data);
    //     $keys_not = array_keys($data_not);
    //     $query = "select * from $this->table where ";
    //     foreach ($keys as $key)
    //     {
    //         $query .= $key . "= :" . $key . " && ";
    //     }
    //     foreach ($keys_not as $key)
    //     {
    //         $query .= $key . "!= :" . $key . " && ";
    //     }

    //     $query = trim($query, " && ");
    //     $query .= " order by $this->order_column $this->order_type limit $this->limit offset $this->offset";
    //     $data = array_merge($data, $data_not);
    //     return $this->query($query, $data);
    // }


    public function first(array $data, array $data_not = [], string $orderBy = 'id', string $orderDir = 'desc')
    {
        $result = $this->where($data, $data_not, [], 1, 0, $orderBy, $orderDir, [$orderBy, 'id']);
        if ($result)
        {
            return $result[0];
        }

        return false;
    }

    public function between(array $dataGreater = [], array $dataLess = [], int $limit = 70, int $offset = 0): array|bool
    {
        $limit = $this->normalizeLimit($limit, 70, 1000);
        $offset = $this->normalizeOffset($offset);

        $query = "select * from $this->table";
        $clauses = [];
        $params = [];

        foreach ($dataGreater as $key => $value)
        {
            if (!$this->isSafeIdentifier((string)$key))
            {
                continue;
            }

            $param = 'gt_' . $key;
            $clauses[] = $key . " > :" . $param;
            $params[$param] = $value;
        }

        foreach ($dataLess as $key => $value)
        {
            if (!$this->isSafeIdentifier((string)$key))
            {
                continue;
            }

            $param = 'lt_' . $key;
            $clauses[] = $key . " < :" . $param;
            $params[$param] = $value;
        }

        if (!empty($clauses))
        {
            $query .= ' where ' . implode(' AND ', $clauses);
        }

        $query .= " limit {$limit} offset {$offset}";

        $result = $this->query($query, $params);
        if ($result)
        {
            return $result;
        }

        return false;
    }

    public function insert($data)
    {
        // ** remove unwanted data **/
        if (!empty($this->allowedColumns))
        {
            foreach ($data as $key => $value)
            {
                if (!in_array($key, $this->allowedColumns))
                {
                    unset($data[$key]);
                }
            }
        }
        $keys = array_keys($data);
        $query = "insert into $this->table (" . implode(",", $keys) . " ) values (:" . implode(",:", $keys) . ")";
        $this->query($query, $data);

        return false;
    }

    public function update($id, $data, $id_column = 'id')
    {
        // ** remove disallowed data **/
        if (!empty($this->allowedColumns))
        {
            foreach ($data as $key => $value)
            {
                if (!in_array($key, $this->allowedColumns))
                {
                    unset($data[$key]);
                }
            }
        }
        $keys = array_keys($data);
        $query = "update $this->table set ";
        foreach ($keys as $key)
        {
            $query .= $key . " = :" . $key . ", ";
        }

        $query = trim($query, ", ");
        $query .= " where $id_column = :$id_column";

        $data[$id_column] = $id;

        $this->query($query, $data);
        return false;
    }

    public function delete($id, $id_column = 'id')
    {
        $data[$id_column] = $id;
        $query = "delete from $this->table where $id_column = :$id_column";

        $data = array_merge($data);
        // echo $query;
        $this->query($query, $data);
        return false;
    }

    private function sanitizePaginationOrder(string $orderBy, string $orderDir, ?array $allowedOrderColumns): array
    {
        $fallback = 'id';

        if (!is_array($allowedOrderColumns) || empty($allowedOrderColumns))
        {
            $allowedOrderColumns = [$fallback];
        }

        $allowed = [];
        foreach ($allowedOrderColumns as $column)
        {
            $column = trim((string)$column);
            if ($this->isSafeIdentifier($column))
            {
                $allowed[] = $column;
            }
        }

        if (empty($allowed))
        {
            $allowed = [$fallback];
        }

        if (!in_array($orderBy, $allowed, true))
        {
            $orderBy = $allowed[0];
        }

        $orderDir = strtolower(trim($orderDir)) === 'asc' ? 'asc' : 'desc';

        return [$orderBy, $orderDir];
    }

    private function buildPaginateWhere(array $where): array
    {
        if (empty($where))
        {
            return ['', []];
        }

        $clauses = [];
        $params = [];
        $index = 0;

        foreach ($where as $column => $value)
        {
            $column = trim((string)$column);
            if (!$this->isSafeIdentifier($column))
            {
                continue;
            }

            $param = 'paginate_' . $index;
            $clauses[] = $column . ' = :' . $param;
            $params[$param] = $value;
            $index++;
        }

        if (empty($clauses))
        {
            return ['', []];
        }

        return [' where ' . implode(' && ', $clauses), $params];
    }

    private function isSafeIdentifier(string $value): bool
    {
        return (bool)preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value);
    }

    private function normalizeLimit(int $limit, int $default = 70, int $max = 1000): int
    {
        if ($limit < 1)
        {
            $limit = $default;
        }

        if ($limit > $max)
        {
            $limit = $max;
        }

        return $limit;
    }

    private function normalizeOffset(int $offset): int
    {
        return $offset < 0 ? 0 : $offset;
    }
}
