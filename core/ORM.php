<?php

/**
 * Lightweight Object-Relational Mapper (ORM)
 *
 * Provides secure, prepared-statement-based CRUD operations,
 * flexible joins, transactions, pagination, and soft-delete support.
 *
 * All table/column names are automatically escaped with backticks
 * only when necessary. Aliases in joins are preserved correctly.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class ORM
{
    private PDO $pdo;

    /**
     * Initialise PDO connection via Database singleton
     */
    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Begin a transaction
     *
     * @return void
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Commit the current transaction
     *
     * @return void
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Roll back the current transaction if active
     *
     * @return void
     */
    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Check if a transaction is currently active
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Execute raw query with parameters
     *
     * @param string $sql    SQL statement
     * @param array  $params Associative array of parameters
     * @return array Result set as associative arrays
     */
    public function runQuery(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Helpers::logError("ORM runQuery failed: " . $e->getMessage() . " | SQL: $sql | Params: " . json_encode($params));
            throw $e;
        }
    }

    /**
     * Insert record and return inserted ID
     *
     * @param string $table Table name
     * @param array  $data  Associative column => value array
     * @return array ['id' => lastInsertId]
     */
    public function insert(string $table, array $data): array
    {
        $columns      = implode('`, `', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO `$table` (`$columns`) VALUES ($placeholders)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            return ['id' => (int)$this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            Helpers::logError("ORM insert failed on table `$table`: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update records matching conditions
     *
     * @param string $table      Table name
     * @param array  $data       Columns to update
     * @param array  $conditions WHERE conditions (column => value)
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, array $conditions): int
    {
        if (empty($data) || empty($conditions)) {
            return 0;
        }

        $setClause = implode(', ', array_map(fn($k) => "`$k` = :set_$k", array_keys($data)));
        $whereClause = implode(' AND ', array_map(fn($k) => "`$k` = :where_$k", array_keys($conditions)));

        $params = [];
        foreach ($data as $k => $v) {
            $params["set_$k"] = $v;
        }
        foreach ($conditions as $k => $v) {
            $params["where_$k"] = $v;
        }

        $sql = "UPDATE `$table` SET $setClause WHERE $whereClause";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            Helpers::logError("ORM update failed on table `$table`: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Hard delete records matching conditions
     *
     * @param string $table      Table name
     * @param array  $conditions WHERE conditions
     * @return int Number of affected rows
     */
    public function delete(string $table, array $conditions): int
    {
        $whereClause = implode(' AND ', array_map(fn($k) => "`$k` = :$k", array_keys($conditions)));

        $sql = "DELETE FROM `$table` WHERE $whereClause";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($conditions);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            Helpers::logError("ORM delete failed on table `$table`: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Soft delete by setting Deleted = 1
     *
     * @param string $table  Table name
     * @param int    $id     Primary key value
     * @param string $column Primary key column (default 'id')
     * @return int Number of affected rows
     */
    public function softDelete(string $table, int $id, string $column = 'id'): int
    {
        $sql = "UPDATE `$table` SET `Deleted` = 1 WHERE `$column` = :id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            Helpers::logError("ORM softDelete failed on table `$table` (ID: $id): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Flexible SELECT with joins, conditions, ordering, grouping and pagination
     *
     * @param string $baseTable   Base table (with alias if needed)
     * @param array  $joins       Join definitions: ['table' => ..., 'on' => ..., 'type' => 'LEFT|INNER']
     * @param array  $fields      Fields to select (default *)
     * @param array  $conditions  Column => placeholder mapping
     * @param array  $params      Bound parameters
     * @param array  $orderBy     ['column' => 'ASC|DESC']
     * @param array  $groupBy     Columns for GROUP BY
     * @param int    $limit       LIMIT value
     * @param int    $offset      OFFSET value
     * @return array Result set
     */
    public function selectWithJoin(
        string $baseTable,
        array $joins = [],
        array $fields = ['*'],
        array $conditions = [],
        array $params = [],
        array $orderBy = [],
        array $groupBy = [],
        int $limit = 0,
        int $offset = 0
    ): array {
        $select = implode(', ', $fields);
        $sql = "SELECT " . trim($select) . " FROM $baseTable";

        foreach ($joins as $join) {
            $type = strtoupper($join['type'] ?? 'INNER');
            $sql .= " $type JOIN {$join['table']} ON {$join['on']}";
        }

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $column => $placeholder) {
                $where[] = is_null($placeholder) ? "$column IS NULL" : "$column = $placeholder";
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if (!empty($groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', array_map(fn($c) => "$c", $groupBy));
        }

        if (!empty($orderBy)) {
            $order = [];
            foreach ($orderBy as $col => $dir) {
                $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
                $order[] = "$col $dir";
            }
            $sql .= ' ORDER BY ' . implode(', ', $order);
        }

        if ($limit > 0) {
            $sql .= " LIMIT $limit";
            if ($offset > 0) {
                $sql .= " OFFSET $offset";
            }
        }

        return $this->runQuery($sql, $params);
    }

    /**
     * Simple WHERE query wrapper
     *
     * @param string $table      Table name
     * @param array  $conditions Column => value
     * @param array  $params     Optional parameter override
     * @param int    $limit      Optional limit
     * @param int    $offset     Optional offset
     * @return array Result set
     */
    public function getWhere(string $table, array $conditions, array $params = [], int $limit = 0, int $offset = 0): array
    {
        $placeholders = [];
        foreach ($conditions as $col => $val) {
            $ph = ":ph_$col";
            $placeholders[] = "`$col` = $ph";
            $params[$ph] = $val;
        }

        $sql = "SELECT * FROM `$table`";
        if (!empty($placeholders)) {
            $sql .= " WHERE " . implode(' AND ', $placeholders);
        }

        if ($limit > 0) {
            $sql .= " LIMIT $limit";
            if ($offset > 0) {
                $sql .= " OFFSET $offset";
            }
        }

        return $this->runQuery($sql, $params);
    }

    /**
     * Retrieve all records from a table with optional pagination
     *
     * @param string $table  Table name
     * @param int    $limit  Optional limit
     * @param int    $offset Optional offset
     * @return array Result set
     */
    public function getAll(string $table, int $limit = 0, int $offset = 0): array
    {
        $sql = "SELECT * FROM `$table`";
        if ($limit > 0) {
            $sql .= " LIMIT $limit";
            if ($offset > 0) {
                $sql .= " OFFSET $offset";
            }
        }
        return $this->runQuery($sql);
    }
}