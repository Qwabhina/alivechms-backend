<?php

/** Budget Management Class
 * Handles budget creation, updating, deletion, retrieval, and listing
 * Validates inputs and ensures data integrity
 * @package Budget
 */
class Budget
{
   /**
    * Create a new budget entry
    * @param array $data Budget data including fiscal year, category, branch, and amount
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function create($data)
   {
      $orm = new ORM();
      try {
         Helpers::validateInput($data, [
            'fiscal_year_id' => 'required|numeric',
            'category_id' => 'required|numeric',
            'branch_id' => 'required|numeric',
            'amount' => 'required|numeric'
         ]);

         // Validate amount is positive
         if ($data['amount'] <= 0) {
            throw new Exception('Budget amount must be positive');
         }

         // Validate fiscal year is active
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $data['fiscal_year_id'], 'Status' => 'Active']);
         if (empty($fiscalYear)) {
            throw new Exception('Selected fiscal year is not active');
         }

         // Validate category exists
         $category = $orm->getWhere('expensecategory', ['ExpCategoryID' => $data['category_id']]);
         if (empty($category)) {
            throw new Exception('Invalid expense category');
         }

         // Validate branch exists
         $branch = $orm->getWhere('branch', ['BranchID' => $data['branch_id']]);
         if (empty($branch)) {
            throw new Exception('Invalid branch');
         }

         $orm->beginTransaction();
         $budgetId = $orm->insert('budget', [
            'FiscalYearID' => $data['fiscal_year_id'],
            'ExpCategoryID' => $data['category_id'],
            'BranchID' => $data['branch_id'],
            'BudgetAmount' => $data['amount'],
            'Status' => 'Draft'
         ])['id'];
         $orm->commit();

         return ['status' => 'success', 'budget_id' => $budgetId];
      } catch (Exception $e) {
         $orm->rollBack();
         Helpers::logError('Budget create error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Update an existing budget entry
    * @param int $budgetId ID of the budget to update
    * @param array $data Updated budget data
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function update($budgetId, $data)
   {
      $orm = new ORM();
      try {
         Helpers::validateInput($data, [
            'amount' => 'required|numeric'
         ]);

         // Validate amount is positive
         if ($data['amount'] <= 0) {
            throw new Exception('Budget amount must be positive');
         }

         // Validate budget exists
         $budget = $orm->getWhere('budget', ['BudgetID' => $budgetId]);
         if (empty($budget)) {
            throw new Exception('Budget not found');
         }

         $orm->beginTransaction();
         $orm->update('budget', [
            'BudgetAmount' => $data['amount'],
            'Status' => $data['status'] ?? $budget[0]['Status']
         ], ['BudgetID' => $budgetId]);
         $orm->commit();

         return ['status' => 'success', 'budget_id' => $budgetId];
      } catch (Exception $e) {
         $orm->rollBack();
         Helpers::logError('Budget update error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Delete a budget entry
    * @param int $budgetId ID of the budget to delete
    * @return array Result of the operation
    * @throws Exception if budget is approved or does not exist
    */
   public static function delete($budgetId)
   {
      $orm = new ORM();
      try {
         // Validate budget exists
         $budget = $orm->getWhere('budget', ['BudgetID' => $budgetId]);
         if (empty($budget)) {
            throw new Exception('Budget not found');
         }
         if ($budget[0]['Status'] === 'Approved') {
            throw new Exception('Cannot delete approved budget');
         }

         $orm->beginTransaction();
         $orm->delete('budget', ['BudgetID' => $budgetId]);
         $orm->commit();

         return ['status' => 'success'];
      } catch (Exception $e) {
         $orm->rollBack();
         Helpers::logError('Budget delete error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Get a specific budget entry by ID
    * @param int $budgetId ID of the budget to retrieve
    * @return array Budget details
    * @throws Exception if budget not found or database operation fails
    */
   public static function get($budgetId)
   {
      $orm = new ORM();
      try {
         $budget = $orm->selectWithJoin(
            baseTable: 'budget b',
            joins: [
               ['table' => 'fiscalyear fy', 'on' => 'b.FiscalYearID = fy.FiscalYearID', 'type' => 'LEFT'],
               ['table' => 'expensecategory ec', 'on' => 'b.ExpCategoryID = ec.ExpCategoryID', 'type' => 'LEFT'],
               ['table' => 'branch br', 'on' => 'b.BranchID = br.BranchID', 'type' => 'LEFT']
            ],
            fields: [
               'b.*',
               'fy.FiscalYearStartDate',
               'fy.FiscalYearEndDate',
               'ec.ExpCategoryName',
               'br.BranchName'
            ],
            conditions: ['b.BudgetID' => ':id'],
            params: [':id' => $budgetId]
         )[0] ?? null;

         if (!$budget) {
            throw new Exception('Budget not found');
         }
         return $budget;
      } catch (Exception $e) {
         Helpers::logError('Budget get error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Get all budgets with pagination and optional filters
    * @param int $page Page number for pagination
    * @param int $limit Number of records per page
    * @param array $filters Optional filters for fiscal year and branch
    * @return array List of budgets with pagination info
    * @throws Exception if database operation fails
    */
   public static function getAll($page = 1, $limit = 10, $filters = [])
   {
      $orm = new ORM();
      try {
         $offset = ($page - 1) * $limit;
         $conditions = [];
         $params = [];

         if (!empty($filters['fiscal_year_id'])) {
            $conditions['b.FiscalYearID'] = ':fiscal_year_id';
            $params[':fiscal_year_id'] = $filters['fiscal_year_id'];
         }
         if (!empty($filters['branch_id'])) {
            $conditions['b.BranchID'] = ':branch_id';
            $params[':branch_id'] = $filters['branch_id'];
         }

         $budgets = $orm->selectWithJoin(
            baseTable: 'budget b',
            joins: [
               ['table' => 'fiscalyear fy', 'on' => 'b.FiscalYearID = fy.FiscalYearID', 'type' => 'LEFT'],
               ['table' => 'expensecategory ec', 'on' => 'b.ExpCategoryID = ec.ExpCategoryID', 'type' => 'LEFT'],
               ['table' => 'branch br', 'on' => 'b.BranchID = br.BranchID', 'type' => 'LEFT']
            ],
            fields: [
               'b.*',
               'fy.FiscalYearStartDate',
               'fy.FiscalYearEndDate',
               'ec.ExpCategoryName',
               'br.BranchName'
            ],
            conditions: $conditions,
            params: $params,
            limit: $limit,
            offset: $offset
         );

         $total = $orm->runQuery(
            "SELECT COUNT(*) as total FROM budget b" .
               (!empty($conditions) ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
            $params
         )[0]['total'];

         return [
            'data' => $budgets,
            'pagination' => [
               'page' => $page,
               'limit' => $limit,
               'total' => $total,
               'pages' => ceil($total / $limit)
            ]
         ];
      } catch (Exception $e) {
         Helpers::logError('Budget getAll error: ' . $e->getMessage());
         throw $e;
      }
   }
}
