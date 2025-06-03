<?php

/** Object Relational Mapping Class
 * Provides methods for database operations using PDO
 * Implements basic CRUD operations and transactions
 * Supports soft deletes and complex queries with joins
 */
class ORM
{
    /**
     * @var PDO The PDO connection instance
     */
    private $pdo;
    /**
     * Constructor initializes the PDO connection
     * Sets error mode to exception for better error handling
     *  * Get the PDO connection instance
     * @return PDO
     */
    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    /**
     * Begin a transaction
     * @throws Exception if the transaction cannot be started
     */
    public function beginTransaction()
    {
        $this->pdo->beginTransaction();
    }
    /**
     * Commit the current transaction
     * @throws Exception if the commit fails
     */
    public function commit()
    {
        $this->pdo->commit();
    }
    /**
     * Roll back the current transaction
     * @throws Exception if the rollback fails
     */
    public function rollBack()
    {
        $this->pdo->rollBack();
    }
    /**
     * Check if a transaction is currently active
     * @return bool True if in transaction, false otherwise
     */
    public function in_transaction()
    {
        return $this->pdo->inTransaction();
    }
    /**
     * Get all records from a specified table
     * @param string $table The name of the table
     * @return array An array of all records in the table
     */
    public function getAll(string $table)
    {
        $stmt = $this->pdo->query("SELECT * FROM `$table`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Get a record by its ID from a specified table
     * @param string $table The name of the table
     * @param mixed $id The ID of the record to retrieve
     * @return array The record with the specified ID
     */
    public function getById(string $table, $id)
    {
        $sql = "SELECT * FROM `$table` WHERE id = :id";
        return $this->runQuery($sql, ['id' => $id]);
    }
    /**
     * Get records by a specific column value
     * @param string $table The name of the table
     * @param string $column The column to filter by
     * @param mixed $value The value to match in the column
     * @return array An array of records matching the criteria
     */
    public function getByColumn(string $table, string $column, $value)
    {
        $sql = "SELECT * FROM `$table` WHERE `$column` = :value";
        return $this->runQuery($sql, ['value' => $value]);
    }
    /**
     * Get records matching multiple conditions
     * @param string $table The name of the table
     * @param array $conditions An associative array of column-value pairs for filtering
     * @return array An array of records matching the conditions
     */
    public function getWhere(string $table, array $conditions)
    {
        $whereClause = implode(' AND ', array_map(fn($k) => "`$k` = :$k", array_keys($conditions)));
        $sql = "SELECT * FROM `$table` WHERE $whereClause";
        return $this->runQuery($sql, $conditions);
    }
    /**
     * Insert a new record into a specified table
     * @param string $table The name of the table
     * @param array $data An associative array of column-value pairs to insert
     * @return array An array containing the ID of the newly inserted record
     */
    public function insert(string $table, array $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return ['id' => $this->pdo->lastInsertId()];
    }
    /**
     * Update records in a specified table
     * @param string $table The name of the table
     * @param array $data An associative array of column-value pairs to update
     * @param array $conditions An associative array of column-value pairs for filtering which records to update
     * @return array An array containing the number of rows affected by the update
     */
    public function update(string $table, array $data, array $conditions)
    {
        $setClause = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($data)));
        $whereClause = implode(' AND ', array_map(fn($k) => "`$k` = :cond_$k", array_keys($conditions)));

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
    /**
     * Delete records from a specified table
     * @param string $table The name of the table
     * @param array $conditions An associative array of column-value pairs for filtering which records to delete
     * @return array An array containing the number of rows affected by the delete operation
     */
    public function delete(string $table, array $conditions)
    {
        $whereClause = implode(' AND ', array_map(fn($k) => "`$k` = :$k", array_keys($conditions)));
        $sql = "DELETE FROM `$table` WHERE $whereClause";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($conditions);
        return ['rows_affected' => $stmt->rowCount()];
    }
    /**
     * Soft delete a record by setting a 'Deleted' flag
     * @param string $table The name of the table
     * @param mixed $value The value of the record to soft delete
     * @param string $column The column to match (default is 'id')
     * @return array An array containing the number of rows affected by the soft delete
     */
    public function softDelete(string $table, $value, $column = 'id')
    {
        $sql = "UPDATE `$table` SET `Deleted` = 1 WHERE `$column` = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $value]);
        Helpers::logError("Deleted Member with ID $value");
        return ['rows_affected' => $stmt->rowCount()];
    }
    /**
     * Run a custom SQL query with parameters
     * @param string $sql The SQL query to execute
     * @param array $params An associative array of parameters to bind to the query
     * @return array The result set as an associative array
     */
    public function runQuery(string $sql, array $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Select records with optional joins, conditions, and pagination
     * @param string $baseTable The base table to select from
     * @param array $joins An array of join definitions
     * @param array $fields The fields to select
     * @param array $conditions Conditions for the WHERE clause
     * @param array $params Parameters for the query
     * @param array $orderBy Order by clauses
     * @param array $groupBy Group by clauses
     * @param int $limit Limit for pagination
     * @param int $offset Offset for pagination
     * @return array The result set as an associative array
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
        $sql = "SELECT {$select} FROM {$baseTable}";

        foreach ($joins as $join) {
            $type = strtoupper($join['type'] ?? 'INNER');
            $table = $join['table'];
            $on = $join['on'];
            $sql .= " {$type} JOIN {$table} ON {$on}";
        }

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $column => $placeholder) {
                if (is_null($placeholder)) {
                    $where[] = $column;
                } else {
                    $where[] = "{$column} = {$placeholder}";
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if (!empty($groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $groupBy);
        }

        if (!empty($orderBy)) {
            $orderClauses = [];
            foreach ($orderBy as $column => $direction) {
                $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $orderClauses[] = "{$column} {$direction}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }

        $stmt = self::runQuery($sql, $params);
        return $stmt;
    }
}
?>