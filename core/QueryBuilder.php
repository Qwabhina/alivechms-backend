<?php

/**
 * Query Builder - Optimized Database Operations
 *
 * Provides fluent interface for building queries with:
 * - Query result caching
 * - Batch insert/update operations
 * - Optimized joins
 * - Index hints
 * - Query logging and profiling
 *
 * @package  AliveChMS\Core
 * @version  2.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-December
 */

declare(strict_types=1);

class QueryBuilder
{
   private PDO $pdo;
   private string $table = '';
   private array $joins = [];
   private array $wheres = [];
   private array $bindings = [];
   private array $selects = ['*'];
   private array $orderBy = [];
   private array $groupBy = [];
   private ?int $limit = null;
   private ?int $offset = null;
   private bool $useCache = false;
   private int $cacheTtl = 600;
   private array $cacheTags = [];

   public function __construct(?PDO $pdo = null)
   {
      $this->pdo = $pdo ?? Database::getInstance()->getConnection();
   }

   /**
    * Set table name
    * 
    * @param string $table Table name
    * @return self
    */
   public function table(string $table): self
   {
      $this->table = $table;
      return $this;
   }

   /**
    * Set columns to select
    * 
    * @param array|string $columns Columns to select
    * @return self
    */
   public function select($columns = '*'): self
   {
      $this->selects = is_array($columns) ? $columns : [$columns];
      return $this;
   }

   /**
    * Add WHERE condition
    * 
    * @param string $column Column name
    * @param mixed $operator Operator or value
    * @param mixed $value Value (optional if operator is actually the value)
    * @return self
    */
   public function where(string $column, $operator, $value = null): self
   {
      if ($value === null) {
         $value = $operator;
         $operator = '=';
      }

      $placeholder = ':where_' . count($this->bindings);
      $this->wheres[] = "`{$column}` {$operator} {$placeholder}";
      $this->bindings[$placeholder] = $value;

      return $this;
   }

   /**
    * Add WHERE IN condition
    * 
    * @param string $column Column name
    * @param array $values Values
    * @return self
    */
   public function whereIn(string $column, array $values): self
   {
      if (empty($values)) {
         return $this;
      }

      $placeholders = [];
      foreach ($values as $i => $value) {
         $placeholder = ':wherein_' . count($this->bindings) . '_' . $i;
         $placeholders[] = $placeholder;
         $this->bindings[$placeholder] = $value;
      }

      $this->wheres[] = "`{$column}` IN (" . implode(', ', $placeholders) . ")";

      return $this;
   }

   /**
    * Add JOIN
    * 
    * @param string $table Table to join
    * @param string $first First column
    * @param string $operator Operator
    * @param string $second Second column
    * @param string $type Join type (INNER, LEFT, RIGHT)
    * @return self
    */
   public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
   {
      $this->joins[] = [
         'type' => strtoupper($type),
         'table' => $table,
         'condition' => "`{$first}` {$operator} `{$second}`"
      ];

      return $this;
   }

   /**
    * Add LEFT JOIN
    */
   public function leftJoin(string $table, string $first, string $operator, string $second): self
   {
      return $this->join($table, $first, $operator, $second, 'LEFT');
   }

   /**
    * Add ORDER BY
    * 
    * @param string $column Column name
    * @param string $direction Direction (ASC|DESC)
    * @return self
    */
   public function orderBy(string $column, string $direction = 'ASC'): self
   {
      $this->orderBy[] = "`{$column}` " . strtoupper($direction);
      return $this;
   }

   /**
    * Add GROUP BY
    * 
    * @param string $column Column name
    * @return self
    */
   public function groupBy(string $column): self
   {
      $this->groupBy[] = "`{$column}`";
      return $this;
   }

   /**
    * Set LIMIT
    * 
    * @param int $limit Limit value
    * @return self
    */
   public function limit(int $limit): self
   {
      $this->limit = $limit;
      return $this;
   }

   /**
    * Set OFFSET
    * 
    * @param int $offset Offset value
    * @return self
    */
   public function offset(int $offset): self
   {
      $this->offset = $offset;
      return $this;
   }

   /**
    * Enable query result caching
    * 
    * @param int $ttl Cache TTL in seconds
    * @param array $tags Cache tags
    * @return self
    */
   public function cache(int $ttl = 600, array $tags = []): self
   {
      $this->useCache = true;
      $this->cacheTtl = $ttl;
      $this->cacheTags = $tags;
      return $this;
   }

   /**
    * Execute SELECT query
    * 
    * @return array Query results
    */
   public function get(): array
   {
      $sql = $this->buildSelectQuery();

      if ($this->useCache) {
         $cacheKey = 'query:' . md5($sql . serialize($this->bindings));
         return Cache::remember($cacheKey, fn() => $this->execute($sql), $this->cacheTtl, $this->cacheTags);
      }

      return $this->execute($sql);
   }

   /**
    * Execute query and return first result
    * 
    * @return array|null First result or null
    */
   public function first(): ?array
   {
      $this->limit(1);
      $results = $this->get();
      return $results[0] ?? null;
   }

   /**
    * Get count of records
    * 
    * @return int Count
    */
   public function count(): int
   {
      $originalSelects = $this->selects;
      $this->selects = ['COUNT(*) as count'];

      $result = $this->first();
      $this->selects = $originalSelects;

      return (int)($result['count'] ?? 0);
   }

   /**
    * Batch insert records (optimized for large datasets)
    * 
    * @param array $records Array of records to insert
    * @param int $batchSize Records per batch
    * @return int Total inserted
    */
   public function batchInsert(array $records, int $batchSize = 100): int
   {
      if (empty($records)) {
         return 0;
      }

      $chunks = array_chunk($records, $batchSize);
      $totalInserted = 0;

      foreach ($chunks as $chunk) {
         $columns = array_keys($chunk[0]);
         $placeholders = [];
         $values = [];

         foreach ($chunk as $i => $record) {
            $rowPlaceholders = [];
            foreach ($columns as $column) {
               $placeholder = ":batch_{$i}_{$column}";
               $rowPlaceholders[] = $placeholder;
               $values[$placeholder] = $record[$column];
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
         }

         $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $columns) . "`) VALUES " . implode(', ', $placeholders);

         $stmt = $this->pdo->prepare($sql);
         $stmt->execute($values);
         $totalInserted += $stmt->rowCount();
      }

      return $totalInserted;
   }

   /**
    * Batch update records using CASE WHEN
    * 
    * @param array $records Array of records [id => [column => value]]
    * @param string $idColumn Primary key column
    * @return int Affected rows
    */
   public function batchUpdate(array $records, string $idColumn = 'id'): int
   {
      if (empty($records)) {
         return 0;
      }

      $ids = array_keys($records);
      $columns = array_keys(reset($records));

      $cases = [];
      $bindings = [];

      foreach ($columns as $column) {
         $whenClauses = [];
         foreach ($records as $id => $data) {
            $placeholder = ":update_{$id}_{$column}";
            $whenClauses[] = "WHEN `{$idColumn}` = :id_{$id} THEN {$placeholder}";
            $bindings[$placeholder] = $data[$column];
         }

         $cases[] = "`{$column}` = CASE " . implode(' ', $whenClauses) . " ELSE `{$column}` END";
      }

      // Add ID bindings
      foreach ($ids as $id) {
         $bindings[":id_{$id}"] = $id;
      }

      $sql = "UPDATE `{$this->table}` SET " . implode(', ', $cases) . " WHERE `{$idColumn}` IN (" . implode(', ', array_keys($bindings)) . ")";

      $stmt = $this->pdo->prepare($sql);
      $stmt->execute($bindings);

      return $stmt->rowCount();
   }

   /**
    * Build SELECT query
    * 
    * @return string SQL query
    */
   private function buildSelectQuery(): string
   {
      $sql = 'SELECT ' . implode(', ', $this->selects) . ' FROM `' . $this->table . '`';

      foreach ($this->joins as $join) {
         $sql .= " {$join['type']} JOIN `{$join['table']}` ON {$join['condition']}";
      }

      if (!empty($this->wheres)) {
         $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
      }

      if (!empty($this->groupBy)) {
         $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
      }

      if (!empty($this->orderBy)) {
         $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
      }

      if ($this->limit !== null) {
         $sql .= ' LIMIT ' . $this->limit;
      }

      if ($this->offset !== null) {
         $sql .= ' OFFSET ' . $this->offset;
      }

      return $sql;
   }

   /**
    * Execute query with bindings
    * 
    * @param string $sql SQL query
    * @return array Results
    */
   private function execute(string $sql): array
   {
      try {
         $stmt = $this->pdo->prepare($sql);
         $stmt->execute($this->bindings);
         return $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
         Helpers::logError("QueryBuilder execution failed: " . $e->getMessage() . " | SQL: $sql");
         throw $e;
      }
   }
}
