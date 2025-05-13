<?php
require_once __DIR__ . '/Database.php';

class ORM
{
    private $pdo;

    public function __construct()
    {
        // $this->pdo = (new Database())->getConnection();
        $this->pdo = Database::getInstance()->getConnection();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getAll(string $table)
    {
        $stmt = $this->pdo->query("SELECT * FROM `$table`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(string $table, $id)
    {
        $sql = "SELECT * FROM `$table` WHERE id = :id";
        return $this->runQuery($sql, ['id' => $id]);
    }

    public function getByColumn(string $table, string $column, $value)
    {
        $sql = "SELECT * FROM `$table` WHERE `$column` = :value";
        return $this->runQuery($sql, ['value' => $value]);
    }

    public function getWhere(string $table, array $conditions)
    {
        $whereClause = implode(' AND ', array_map(fn($k) => "`$k` = :$k", array_keys($conditions)));
        $sql = "SELECT * FROM `$table` WHERE $whereClause";
        return $this->runQuery($sql, $conditions);
    }

    public function insert(string $table, array $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return ['id' => $this->pdo->lastInsertId()];
    }

    public function update(string $table, array $data, array $conditions)
    {
        $setClause = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($data)));
        $whereClause = implode(' AND ', array_map(fn($k) => "`$k` = :cond_$k", array_keys($conditions)));

        // Rename condition keys to avoid conflicts with data keys
        $params = array_merge(
            $data,
            array_combine(
                array_map(fn($k) => "cond_$k", array_keys($conditions)),
                array_values($conditions)
            )
        );

        $sql = "UPDATE `$table` SET $setClause WHERE $whereClause";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return ['rows_affected' => $stmt->rowCount()];
    }

    public function delete(string $table, array $conditions)
    {
        $whereClause = implode(' AND ', array_map(fn($k) => "`$k` = :$k", array_keys($conditions)));
        $sql = "DELETE FROM `$table` WHERE $whereClause";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($conditions);
        return ['rows_affected' => $stmt->rowCount()];
    }

    public function softDelete(string $table, $value, $column = 'id')
    {
        $sql = "UPDATE `$table` SET deleted_at = NOW() WHERE `$column` = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $value]);
        return ['rows_affected' => $stmt->rowCount()];
    }

    public function runQuery(string $sql, array $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function selectWithJoin(
        string $baseTable,
        array $joins = [],
        array $fields = ['*'],
        array $conditions = [],
        array $params = [],
        array $orderBy = [],
        int $limit = 0,
        int $offset = 0
    ): array {
        $select = implode(', ', $fields);
        $sql = "SELECT {$select} FROM {$baseTable}";

        // JOIN clauses
        foreach ($joins as $join) {
            $type = strtoupper($join['type'] ?? 'INNER');
            $table = $join['table'];
            $on = $join['on'];
            $sql .= " {$type} JOIN {$table} ON {$on}";
        }

        // WHERE clause
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $column => $placeholder) {
                $where[] = "{$column} = {$placeholder}";
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        // ORDER BY
        if (!empty($orderBy)) {
            $orderClauses = [];
            foreach ($orderBy as $column => $direction) {
                $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $orderClauses[] = "{$column} {$direction}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        // LIMIT and OFFSET
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
