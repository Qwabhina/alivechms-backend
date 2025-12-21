<?php

/**
 * Object-Relational Mapper (ORM) - SECURED VERSION
 *
 * Lightweight, secure PDO-based ORM providing common database operations
 * with prepared statements, transactions, and flexible querying.
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
     * Quote identifier to prevent SQL injection
     * Properly escapes table and column names
     * 
     * @param string $identifier Table or column name
     * @return string Quoted identifier
     */
    private function quoteIdentifier(string $identifier): string
    {
        // Remove any existing backticks
        $identifier = str_replace('`', '', $identifier);

        // Remove any dangerous characters
        $identifier = preg_replace('/[^a-zA-Z0-9_.]/', '', $identifier);

        // Handle table.column or alias formats
        if (strpos($identifier, '.') !== false) {
            $parts = explode('.', $identifier);
            return '`' . implode('`.`', $parts) . '`';
        }

        // Handle "column AS alias" format
        if (stripos($identifier, ' AS ') !== false) {
            $parts = preg_split('/\s+AS\s+/i', $identifier);
            return $this->quoteIdentifier($parts[0]) . ' AS ' . $this->quoteIdentifier($parts[1]);
        }

        return '`' . $identifier . '`';
    }

    /**
     * Begin a database transaction
     *
     * @return void
     */
    public function beginTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    /**
     * Commit the current database transaction
     *
     * @return void
     */
    public function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
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
        $table = $this->quoteIdentifier($table);

        $columns = array_map([$this, 'quoteIdentifier'], array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES ($placeholders)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            return ['id' => (int)$this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            Helpers::logError("ORM insert failed on table $table: " . $e->getMessage());
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

        $table = $this->quoteIdentifier($table);

        $setClauses = [];
        foreach (array_keys($data) as $key) {
            $setClauses[] = $this->quoteIdentifier($key) . " = :set_$key";
        }
        $setClause = implode(', ', $setClauses);

        $whereClauses = [];
        foreach (array_keys($conditions) as $key) {
            // Handle special operators in key (e.g., 'MbrID!=')
            if (preg_match('/^(.+?)(!=|>=|<=|>|<|LIKE)$/', $key, $matches)) {
                $whereClauses[] = $this->quoteIdentifier($matches[1]) . " {$matches[2]} :where_$key";
            } else {
                $whereClauses[] = $this->quoteIdentifier($key) . " = :where_$key";
            }
        }
        $whereClause = implode(' AND ', $whereClauses);

        $params = [];
        foreach ($data as $k => $v) {
            $params["set_$k"] = $v;
        }
        foreach ($conditions as $k => $v) {
            $params["where_$k"] = $v;
        }

        $sql = "UPDATE $table SET $setClause WHERE $whereClause";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            Helpers::logError("ORM update failed on table $table: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete records matching conditions (hard delete)
     *
     * @param string $table      Table name
     * @param array  $conditions WHERE conditions
     * @return int Number of affected rows
     */
    public function delete(string $table, array $conditions): int
    {
        $table = $this->quoteIdentifier($table);

        $whereClauses = [];
        foreach (array_keys($conditions) as $key) {
            $whereClauses[] = $this->quoteIdentifier($key) . " = :$key";
        }
        $whereClause = implode(' AND ', $whereClauses);

        $sql = "DELETE FROM $table WHERE $whereClause";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($conditions);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            Helpers::logError("ORM delete failed on table $table: " . $e->getMessage());
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
        $table = $this->quoteIdentifier($table);
        $column = $this->quoteIdentifier($column);

        $sql = "UPDATE $table SET `Deleted` = 1 WHERE $column = :id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            Helpers::logError("ORM softDelete failed on table $table (ID: $id): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Flexible SELECT with joins, conditions, ordering, grouping and pagination
     * - All table/column names properly quoted
     * - Join types whitelisted
     * - Parameters properly bound
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
     * @return array              Result set
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
        // Handle base table with alias (e.g., "table t" or "table AS t")
        if (preg_match('/^(.+?)\s+(AS\s+)?(\w+)$/i', $baseTable, $matches)) {
            $tableName = $this->quoteIdentifier(trim($matches[1]));
            $alias = $this->quoteIdentifier(trim($matches[3]));
            $baseTable = "$tableName AS $alias";
        } else {
            $baseTable = $this->quoteIdentifier($baseTable);
        }

        // Build SELECT clause
        $select = [];
        foreach ($fields as $field) {
            if ($field === '*') {
                $select[] = '*';
            } elseif (stripos($field, ' AS ') !== false) {
                // Handle "column AS alias"
                $parts = preg_split('/\s+AS\s+/i', $field);
                if (count($parts) === 2) {
                    // Check if it's a function or subquery
                    if (preg_match('/^[\w.]+$/', trim($parts[0]))) {
                        $select[] = $this->quoteIdentifier(trim($parts[0])) . ' AS ' . $this->quoteIdentifier(trim($parts[1]));
                    } else {
                        // It's a function or expression - don't quote the first part
                        $select[] = trim($parts[0]) . ' AS ' . $this->quoteIdentifier(trim($parts[1]));
                    }
                } else {
                    $select[] = $field;
                }
            } elseif (preg_match('/^[a-zA-Z0-9_.]+$/', $field)) {
                // Simple column name
                $select[] = $this->quoteIdentifier($field);
            } else {
                // Function, subquery, or expression - use as-is
                $select[] = $field;
            }
        }
        $selectClause = implode(', ', $select);

        $sql = "SELECT $selectClause FROM $baseTable";

        // Build JOIN clauses with security
        foreach ($joins as $join) {
            $type = strtoupper($join['type'] ?? 'INNER');

            // SECURITY: Whitelist join types
            $allowedTypes = ['INNER', 'LEFT', 'RIGHT', 'OUTER', 'CROSS'];
            if (!in_array($type, $allowedTypes, true)) {
                throw new Exception('Invalid join type: ' . $type);
            }

            // Handle table with alias
            $joinTable = $join['table'];
            if (preg_match('/^(.+?)\s+(AS\s+)?(\w+)$/i', $joinTable, $matches)) {
                $tableName = $this->quoteIdentifier(trim($matches[1]));
                $alias = $this->quoteIdentifier(trim($matches[3]));
                $joinTable = "$tableName AS $alias";
            } else {
                $joinTable = $this->quoteIdentifier($joinTable);
            }

            $joinOn = $join['on']; // ON clause (e.g., "t1.id = t2.id")

            $sql .= " $type JOIN $joinTable ON $joinOn";
        }

        // Build WHERE clause
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $column => $placeholder) {
                // Handle special cases like "column IS NULL"
                if (is_null($placeholder)) {
                    $where[] = $this->quoteIdentifier($column) . " IS NULL";
                }
                // Handle operators in column name (e.g., "column >=")
                elseif (preg_match('/^(.+?)\s*(!=|>=|<=|>|<|LIKE)$/', $column, $matches)) {
                    $where[] = $this->quoteIdentifier(trim($matches[1])) . " {$matches[2]} $placeholder";
                } else {
                    $where[] = $this->quoteIdentifier($column) . " = $placeholder";
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        // Build GROUP BY clause
        if (!empty($groupBy)) {
            $groupColumns = array_map([$this, 'quoteIdentifier'], $groupBy);
            $sql .= ' GROUP BY ' . implode(', ', $groupColumns);
        }

        // Build ORDER BY clause
        if (!empty($orderBy)) {
            $order = [];
            foreach ($orderBy as $col => $dir) {
                $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
                $col = $this->quoteIdentifier($col);
                $order[] = "$col $dir";
            }
            $sql .= ' ORDER BY ' . implode(', ', $order);
        }

        // Add LIMIT and OFFSET
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset > 0) {
                $sql .= " OFFSET " . (int)$offset;
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
        $table = $this->quoteIdentifier($table);

        $whereClauses = [];
        $queryParams = [];

        foreach ($conditions as $col => $val) {
            $paramKey = ':param_' . md5($col . $val);

            // Handle operators in column name
            if (preg_match('/^(.+?)\s*(!=|>=|<=|>|<|LIKE)$/', $col, $matches)) {
                $whereClauses[] = $this->quoteIdentifier(trim($matches[1])) . " {$matches[2]} $paramKey";
                $queryParams[$paramKey] = $val;
            } else {
                $whereClauses[] = $this->quoteIdentifier($col) . " = $paramKey";
                $queryParams[$paramKey] = $val;
            }
        }

        $sql = "SELECT * FROM $table";

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        if ($limit > 0) {
            $sql .= " LIMIT $limit";
            if ($offset > 0) {
                $sql .= " OFFSET $offset";
            }
        }

        return $this->runQuery($sql, array_merge($queryParams, $params));
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
        $table = $this->quoteIdentifier($table);

        $sql = "SELECT * FROM $table";

        if ($limit > 0) {
            $sql .= " LIMIT $limit";
            if ($offset > 0) {
                $sql .= " OFFSET $offset";
            }
        }

        return $this->runQuery($sql);
    }

    /**
     * Count records with conditions
     * 
     * @param string $table      Table name
     * @param array  $conditions Column => value conditions
     * @return int Count of matching records
     */
    public function count(string $table, array $conditions = []): int
    {
        $table = $this->quoteIdentifier($table);

        $sql = "SELECT COUNT(*) as total FROM $table";
        $params = [];

        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $col => $val) {
                $paramKey = ':param_' . md5($col . $val);
                $whereClauses[] = $this->quoteIdentifier($col) . " = $paramKey";
                $params[$paramKey] = $val;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        $result = $this->runQuery($sql, $params);
        return (int)($result[0]['total'] ?? 0);
    }

    /**
     * Check if record exists in a table with given conditions
     * 
     * @param string $table      Table name
     * @param array  $conditions Column => value conditions
     * @return bool True if record exists, false otherwise
     */
    public function exists(string $table, array $conditions): bool
    {
        return $this->count($table, $conditions) > 0;
    }
}