<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ORM.php';
require_once __DIR__ . '/Helpers.php';

class FiscalYear
{
   public static function create($data)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate input
         Helpers::validateInput($data, [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'branch_id' => 'required|numeric'
         ]);

         // Validate dates
         if (strtotime($data['start_date']) >= strtotime($data['end_date'])) {
            throw new Exception('Start date must be before end date');
         }

         // Validate branch exists
         $branch = $orm->getWhere('branch', ['BranchID' => $data['branch_id']]);
         if (empty($branch)) {
            throw new Exception('Invalid branch ID');
         }

         // Check for overlapping fiscal years
         $overlap = $orm->runQuery(
            "SELECT FiscalYearID FROM fiscalyear 
                 WHERE BranchID = :branch_id 
                 AND Status = 'Active'
                 AND (
                     (:start_date BETWEEN FiscalYearStartDate AND FiscalYearEndDate)
                     OR (:end_date BETWEEN FiscalYearStartDate AND FiscalYearEndDate)
                     OR (FiscalYearStartDate BETWEEN :start_date AND :end_date)
                 )",
            [
               ':branch_id' => $data['branch_id'],
               ':start_date' => $data['start_date'],
               ':end_date' => $data['end_date']
            ]
         );
         if (!empty($overlap)) {
            throw new Exception('Fiscal year overlaps with an existing active fiscal year');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $fiscalYearId = $orm->insert('fiscalyear', [
            'FiscalYearStartDate' => $data['start_date'],
            'FiscalYearEndDate' => $data['end_date'],
            'BranchID' => $data['branch_id'],
            'Status' => $data['status'] ?? 'Active'
         ])['id'];

         // Create notification
         $orm->insert('communication', [
            'Title' => 'New Fiscal Year Created',
            'Message' => "Fiscal year {$data['start_date']} to {$data['end_date']} created for branch {$branch[0]['BranchName']}.",
            'SentBy' => $data['created_by'] ?? 1, // Assume admin ID 1 for now
            'TargetGroupID' => null
         ]);

         $orm->commit();
         return ['status' => 'success', 'fiscal_year_id' => $fiscalYearId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Fiscal year create error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function update($fiscalYearId, $data)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate input
         Helpers::validateInput($data, [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'branch_id' => 'required|numeric'
         ]);

         // Validate dates
         if (strtotime($data['start_date']) >= strtotime($data['end_date'])) {
            throw new Exception('Start date must be before end date');
         }

         // Validate branch exists
         $branch = $orm->getWhere('branch', ['BranchID' => $data['branch_id']]);
         if (empty($branch)) {
            throw new Exception('Invalid branch ID');
         }

         // Validate fiscal year exists
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
         if (empty($fiscalYear)) {
            throw new Exception('Fiscal year not found');
         }

         // Check for overlapping fiscal years (excluding current)
         $overlap = $orm->runQuery(
            "SELECT FiscalYearID FROM fiscalyear 
                 WHERE BranchID = :branch_id 
                 AND Status = 'Active'
                 AND FiscalYearID != :id
                 AND (
                     (:start_date BETWEEN FiscalYearStartDate AND FiscalYearEndDate)
                     OR (:end_date BETWEEN FiscalYearStartDate AND FiscalYearEndDate)
                     OR (FiscalYearStartDate BETWEEN :start_date AND :end_date)
                 )",
            [
               ':branch_id' => $data['branch_id'],
               ':id' => $fiscalYearId,
               ':start_date' => $data['start_date'],
               ':end_date' => $data['end_date']
            ]
         );
         if (!empty($overlap)) {
            throw new Exception('Fiscal year overlaps with an existing active fiscal year');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->update('fiscalyear', [
            'FiscalYearStartDate' => $data['start_date'],
            'FiscalYearEndDate' => $data['end_date'],
            'BranchID' => $data['branch_id'],
            'Status' => $data['status'] ?? $fiscalYear[0]['Status']
         ], ['FiscalYearID' => $fiscalYearId]);

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Fiscal Year Updated',
            'Message' => "Fiscal year {$data['start_date']} to {$data['end_date']} updated for branch {$branch[0]['BranchName']}.",
            'SentBy' => $data['created_by'] ?? 1,
            'TargetGroupID' => null
         ]);

         $orm->commit();
         return ['status' => 'success', 'fiscal_year_id' => $fiscalYearId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Fiscal year update error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function delete($fiscalYearId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate fiscal year exists
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
         if (empty($fiscalYear)) {
            throw new Exception('Fiscal year not found');
         }

         // Check if referenced
         $referenced = $orm->runQuery(
            "SELECT (SELECT COUNT(*) FROM budget WHERE FiscalYearID = :id) +
                        (SELECT COUNT(*) FROM contribution WHERE FiscalYearID = :id) +
                        (SELECT COUNT(*) FROM expense WHERE FiscalYearID = :id) as ref_count",
            [':id' => $fiscalYearId]
         )[0]['ref_count'];
         if ($referenced > 0) {
            throw new Exception('Cannot delete fiscal year with associated budgets, contributions, or expenses');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->delete('fiscalyear', ['FiscalYearID' => $fiscalYearId]);

         $orm->commit();
         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Fiscal year delete error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function get($fiscalYearId)
   {
      $orm = new ORM();
      try {
         $fiscalYear = $orm->selectWithJoin(
            baseTable: 'fiscalyear fy',
            joins: [
               ['table' => 'branch b', 'on' => 'fy.BranchID = b.BranchID', 'type' => 'LEFT']
            ],
            fields: [
               'fy.*',
               'b.BranchName'
            ],
            conditions: ['fy.FiscalYearID' => ':id'],
            params: [':id' => $fiscalYearId]
         )[0] ?? null;

         if (!$fiscalYear) {
            throw new Exception('Fiscal year not found');
         }
         return $fiscalYear;
      } catch (Exception $e) {
         Helpers::logError('Fiscal year get error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function getAll($page = 1, $limit = 10, $filters = [])
   {
      $orm = new ORM();
      try {
         $offset = ($page - 1) * $limit;
         $conditions = [];
         $params = [];

         if (!empty($filters['branch_id'])) {
            $conditions['fy.BranchID ='] = ':branch_id';
            $params[':branch_id'] = $filters['branch_id'];
         }
         if (!empty($filters['status'])) {
            if (!in_array($filters['status'], ['Active', 'Closed'])) {
               throw new Exception('Invalid status filter');
            }
            $conditions['fy.Status ='] = ':status';
            $params[':status'] = $filters['status'];
         }
         if (!empty($filters['date_from'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
               throw new Exception('Invalid date_from format. Use YYYY-MM-DD');
            }
            $conditions['fy.FiscalYearEndDate >='] = ':date_from';
            $params[':date_from'] = $filters['date_from'];
         }
         if (!empty($filters['date_to'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
               throw new Exception('Invalid date_to format. Use YYYY-MM-DD');
            }
            $conditions['fy.FiscalYearStartDate <='] = ':date_to';
            $params[':date_to'] = $filters['date_to'];
         }

         $fiscalYears = $orm->selectWithJoin(
            baseTable: 'fiscalyear fy',
            joins: [
               ['table' => 'branch b', 'on' => 'fy.BranchID = b.BranchID', 'type' => 'LEFT']
            ],
            fields: [
               'fy.*',
               'b.BranchName'
            ],
            conditions: $conditions,
            params: $params,
            limit: $limit,
            offset: $offset
         );

         $total = $orm->runQuery(
            "SELECT COUNT(*) as total FROM fiscalyear fy" .
               (!empty($conditions) ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
            $params
         )[0]['total'];

         return [
            'data' => $fiscalYears,
            'pagination' => [
               'page' => $page,
               'limit' => $limit,
               'total' => $total,
               'pages' => ceil($total / $limit)
            ]
         ];
      } catch (Exception $e) {
         Helpers::logError('Fiscal year getAll error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function close($fiscalYearId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate fiscal year exists
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
         if (empty($fiscalYear)) {
            throw new Exception('Fiscal year not found');
         }

         if ($fiscalYear[0]['Status'] === 'Closed') {
            throw new Exception('Fiscal year is already closed');
         }

         $branch = $orm->getWhere('branch', ['BranchID' => $fiscalYear[0]['BranchID']]);
         if (empty($branch)) {
            throw new Exception('Associated branch not found');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->update('fiscalyear', [
            'Status' => 'Closed'
         ], ['FiscalYearID' => $fiscalYearId]);

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Fiscal Year Closed',
            'Message' => "Fiscal year {$fiscalYear[0]['FiscalYearStartDate']} to {$fiscalYear[0]['FiscalYearEndDate']} closed for branch {$branch[0]['BranchName']}.",
            'SentBy' => 1, // Assume admin ID 1
            'TargetGroupID' => null
         ]);

         $orm->commit();
         return ['status' => 'success', 'fiscal_year_id' => $fiscalYearId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Fiscal year close error: ' . $e->getMessage());
         throw $e;
      }
   }
}
