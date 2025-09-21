<?php

/** Fiscal Year Management Class
 * This class provides methods for managing fiscal years, including creating, updating, deleting, and viewing fiscal years.
 * It also handles fiscal year closure.
 * It requires authentication and permission checks for each operation.
 */
class FiscalYear
{
   /**
    * Create a new fiscal year entry
    * @param array $data Fiscal year data including start_date, end_date, branch_id, and optional status
    * @return array Result of the operation
    */
   public static function create($data)
   {
      $orm = new ORM();
      $transactionStarted = false;

      try {
         Helpers::validateInput($data, [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'branch_id' => 'required|numeric',
            'status' => 'string|in:Active,Closed|nullable'
         ]);

         // Validate dates
         if (strtotime($data['start_date']) >= strtotime($data['end_date'])) {
            Helpers::logError('Fiscal year create error: Start date must be before end date');
            Helpers::sendFeedback('Start date must be before end date', 400);
         }

         // Validate branch exists
         $branch = $orm->getWhere('branch', ['BranchID' => $data['branch_id']]);
         if (empty($branch)) {
            Helpers::logError('Fiscal year create error: Invalid branch ID');
            Helpers::sendFeedback('Invalid branch ID', 400);
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
            Helpers::logError('Fiscal year create error: Fiscal year overlaps with an existing active fiscal year');
            Helpers::sendFeedback('Fiscal year overlaps with an existing active fiscal year', 400);
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
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();

         Helpers::logError('Fiscal year create error: ' . $e->getMessage());
         Helpers::sendFeedback('Fiscal year creation failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Update an existing fiscal year entry
    * @param int $fiscalYearId ID of the fiscal year to update
    * @param array $data Updated fiscal year data
    * @return array Result of the operation
    */
   public static function update($fiscalYearId, $data)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         Helpers::validateInput($data, [
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable',
            'branch_id' => 'numeric|nullable',
            'status' => 'string|in:Active,Closed|nullable'
         ]);

         // Validate fiscal year exists
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
         if (empty($fiscalYear)) {
            Helpers::logError('Fiscal year update error: Fiscal year not found');
            Helpers::sendFeedback('Fiscal year not found', 404);
         }

         // Prevent updating closed fiscal years if necessary
         if ($fiscalYear[0]['Status'] === 'Closed' && (isset($data['start_date']) || isset($data['end_date']))) {
            Helpers::logError('Fiscal year update error: Cannot update dates of a closed fiscal year');
            Helpers::sendFeedback('Cannot update dates of a closed fiscal year', 400);
         }

         $updateData = [];
         $branchId = $data['branch_id'] ?? $fiscalYear[0]['BranchID'];
         $startDate = $data['start_date'] ?? $fiscalYear[0]['FiscalYearStartDate'];
         $endDate = $data['end_date'] ?? $fiscalYear[0]['FiscalYearEndDate'];

         // Validate dates if provided
         if (isset($data['start_date']) || isset($data['end_date'])) {
            if (strtotime($startDate) >= strtotime($endDate)) {
               Helpers::logError('Fiscal year update error: Start date must be before end date');
               Helpers::sendFeedback('Start date must be before end date', 400);
            }
         }

         // Validate branch if provided
         if (isset($data['branch_id'])) {
            $branch = $orm->getWhere('branch', ['BranchID' => $data['branch_id']]);
            if (empty($branch)) {
               Helpers::logError('Fiscal year update error: Invalid branch ID');
               Helpers::sendFeedback('Invalid branch ID', 400);
            }
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
               ':branch_id' => $branchId,
               ':id' => $fiscalYearId,
               ':start_date' => $startDate,
               ':end_date' => $endDate
            ]
         );
         if (!empty($overlap)) {
            Helpers::logError('Fiscal year update error: Fiscal year overlaps with an existing active fiscal year');
            Helpers::sendFeedback('Fiscal year overlaps with an existing active fiscal year', 400);
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         if (isset($data['start_date'])) $updateData['FiscalYearStartDate'] = $data['start_date'];
         if (isset($data['end_date'])) $updateData['FiscalYearEndDate'] = $data['end_date'];
         if (isset($data['branch_id'])) $updateData['BranchID'] = $data['branch_id'];
         if (isset($data['status'])) $updateData['Status'] = $data['status'];

         if (!empty($updateData)) {
            $orm->update('fiscalyear', $updateData, ['FiscalYearID' => $fiscalYearId]);
         }

         // Create notification if changes were made
         if (!empty($updateData)) {
            $branch = $orm->getWhere('branch', ['BranchID' => $branchId]);
            $orm->insert('communication', [
               'Title' => 'Fiscal Year Updated',
               'Message' => "Fiscal year {$startDate} to {$endDate} updated for branch {$branch[0]['BranchName']}.",
               'SentBy' => $data['updated_by'] ?? 1,
               'TargetGroupID' => null
            ]);
         }

         $orm->commit();
         return ['status' => 'success', 'fiscal_year_id' => $fiscalYearId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Fiscal year update error: ' . $e->getMessage());
         Helpers::sendFeedback('Fiscal year update failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Delete a fiscal year entry
    * @param int $fiscalYearId ID of the fiscal year to delete
    * @return array Result of the operation
    */
   public static function delete($fiscalYearId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
         if (empty($fiscalYear)) {
            Helpers::logError('Fiscal year delete error: Fiscal year not found');
            Helpers::sendFeedback('Fiscal year not found', 404);
         }

         // Check if referenced
         $referenced = $orm->runQuery(
            "SELECT (SELECT COUNT(*) FROM budget WHERE FiscalYearID = :id) +
                    (SELECT COUNT(*) FROM contribution WHERE FiscalYearID = :id) +
                    (SELECT COUNT(*) FROM expense WHERE FiscalYearID = :id) as ref_count",
            [':id' => $fiscalYearId]
         )[0]['ref_count'];
         if ($referenced > 0) {
            Helpers::logError('Fiscal year delete error: Cannot delete fiscal year with associated budgets, contributions, or expenses');
            Helpers::sendFeedback('Cannot delete fiscal year with associated budgets, contributions, or expenses', 400);
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->delete('fiscalyear', ['FiscalYearID' => $fiscalYearId]);

         $orm->commit();
         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();

         Helpers::logError('Fiscal year delete error: ' . $e->getMessage());
         Helpers::sendFeedback('Fiscal year delete failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Get a specific fiscal year entry by ID
    * @param int $fiscalYearId ID of the fiscal year to retrieve
    * @return array Fiscal year details
    */
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
            Helpers::logError('Fiscal year get error: Fiscal year not found');
            Helpers::sendFeedback('Fiscal year not found', 404);
         }
         return $fiscalYear;
      } catch (Exception $e) {
         Helpers::logError('Fiscal year get error: ' . $e->getMessage());
         Helpers::sendFeedback('Fiscal year retrieval failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Get all fiscal years with pagination and optional filters
    * @param int $page Page number for pagination
    * @param int $limit Number of records per page
    * @param array $filters Optional filters for branch_id, status, date_from, date_to
    * @return array List of fiscal years with pagination info
    */
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
               Helpers::logError('Fiscal year getAll error: Invalid status filter');
               Helpers::sendFeedback('Invalid status filter', 400);
            }
            $conditions['fy.Status ='] = ':status';
            $params[':status'] = $filters['status'];
         }
         if (!empty($filters['date_from'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
               Helpers::logError('Fiscal year getAll error: Invalid date_from format. Use YYYY-MM-DD');
               Helpers::sendFeedback('Invalid date_from format. Use YYYY-MM-DD', 400);
            }
            $conditions['fy.FiscalYearEndDate >='] = ':date_from';
            $params[':date_from'] = $filters['date_from'];
         }
         if (!empty($filters['date_to'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
               Helpers::logError('Fiscal year getAll error: Invalid date_to format. Use YYYY-MM-DD');
               Helpers::sendFeedback('Invalid date_to format. Use YYYY-MM-DD', 400);
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

         $whereClause = '';
         if (!empty($conditions)) {
            $whereConditions = [];
            foreach ($conditions as $column => $placeholder) {
               $whereConditions[] = "$column $placeholder";
            }
            $whereClause = ' WHERE ' . implode(' AND ', $whereConditions);
         }

         $total = $orm->runQuery(
            "SELECT COUNT(*) as total FROM fiscalyear fy" . $whereClause,
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
         Helpers::sendFeedback('Fiscal year retrieval failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Close a fiscal year
    * @param int $fiscalYearId ID of the fiscal year to close
    * @return array Result of the close operation
    */
   public static function close($fiscalYearId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate fiscal year exists
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
         if (empty($fiscalYear)) {
            Helpers::logError('Fiscal year close error: Fiscal year not found');
            Helpers::sendFeedback('Fiscal year not found', 404);
         }

         if ($fiscalYear[0]['Status'] === 'Closed') {
            Helpers::logError('Fiscal year close error: Fiscal year is already closed');
            Helpers::sendFeedback('Fiscal year is already closed', 400);
         }

         $branch = $orm->getWhere('branch', ['BranchID' => $fiscalYear[0]['BranchID']]);
         if (empty($branch)) {
            Helpers::logError('Fiscal year close error: Associated branch not found');
            Helpers::sendFeedback('Associated branch not found', 400);
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
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();

         Helpers::logError('Fiscal year close error: ' . $e->getMessage());
         Helpers::sendFeedback('Fiscal year close failed: ' . $e->getMessage(), 400);
      }
   }
}