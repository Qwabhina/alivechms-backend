<?php

/** Expense Management Class
 * Handles expense creation, updating, deletion, retrieval, and listing
 * Validates inputs and ensures data integrity
 * Implements error handling and transaction management
 * @package Expense
 */
class Expense
{
   /**
    * Create a new expense entry
    * @param array $data Expense data including title, amount, category, fiscal year, and member ID
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function create($data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'title' => 'required',
            'amount' => 'required|numeric',
            'category_id' => 'required|numeric',
            'fiscal_year_id' => 'required|numeric',
            'member_id' => 'required|numeric'
         ]);

         // Validate amount is positive
         if ($data['amount'] <= 0) Helpers::sendError('Expense amount must be positive');

         // Validate fiscal year is active
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $data['fiscal_year_id'], 'Status' => 'Active']);
         if (empty($fiscalYear)) Helpers::sendError('Selected fiscal year is not active');

         // Validate category exists
         $category = $orm->getWhere('expensecategory', ['ExpCategoryID' => $data['category_id']]);
         if (empty($category)) Helpers::sendError('Invalid expense category');

         // Validate member exists
         $member = $orm->getWhere('churchmember', ['MbrID' => $data['member_id'], 'Deleted' => 0]);
         if (empty($member)) Helpers::sendError('Invalid member ID');

         $orm->beginTransaction();
         $expenseId = $orm->insert('expense', [
            'ExpTitle' => $data['title'],
            'ExpPurpose' => $data['purpose'] ?? null,
            'ExpAmount' => $data['amount'],
            'ExpDate' => $data['date'] ?? date('Y-m-d'),
            'ExpStatus' => 'Pending Approval',
            'MbrID' => $data['member_id'],
            'FiscalYearID' => $data['fiscal_year_id'],
            'ExpCategoryID' => $data['category_id']
         ])['id'];

         // Create notification for admins
         $adminUsers = $orm->selectWithJoin(
            baseTable: 'userauthentication u',
            joins: [
               ['table' => 'memberrole mr', 'on' => 'u.MbrID = mr.MbrID'],
               ['table' => 'churchrole cr', 'on' => 'mr.ChurchRoleID = cr.RoleID'],
               ['table' => 'rolepermission rp', 'on' => 'cr.RoleID = rp.ChurchRoleID'],
               ['table' => 'permission p', 'on' => 'rp.PermissionID = p.PermissionID']
            ],
            fields: ['u.MbrID'],
            conditions: ['p.PermissionName' => ':permission'],
            params: [':permission' => 'approve_expense']
         );

         foreach ($adminUsers as $admin) {
            $orm->insert('communication', [
               'Title' => 'New Expense Submitted',
               'Message' => "Expense '{$data['title']}' for {$data['amount']} submitted by member ID {$data['member_id']} requires approval.",
               'SentBy' => $data['member_id'],
               'TargetGroupID' => null
            ]);
         }
         $orm->commit();

         return ['status' => 'success', 'expense_id' => $expenseId];
      } catch (Exception $e) {
         if ($orm->inTransaction()) $orm->rollBack();

         Helpers::logError('Expense create error: ' . $e->getMessage());
         Helpers::sendError('Expense creation error.');
      }
   }
   /**
    * Update an existing expense entry
    * @param int $expenseId The ID of the expense to update
    * @param array $data Updated expense data
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function update($expenseId, $data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'title' => 'required',
            'amount' => 'required|numeric',
            'category_id' => 'required|numeric',
            'fiscal_year_id' => 'required|numeric'
         ]);

         // Validate amount is positive
         if ($data['amount'] <= 0) Helpers::sendError('Expense amount must be positive');

         // Validate fiscal year is active
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $data['fiscal_year_id'], 'Status' => 'Active']);
         if (empty($fiscalYear)) Helpers::sendError('Selected fiscal year is not active');

         // Validate category exists
         $category = $orm->getWhere('expensecategory', ['ExpCategoryID' => $data['category_id']]);
         if (empty($category)) Helpers::sendError('Invalid expense category');

         // Validate expense exists and is pending
         $expense = $orm->getWhere('expense', ['ExpID' => $expenseId]);
         if (empty($expense)) Helpers::sendError('Expense not found');
         if ($expense[0]['ExpStatus'] !== 'Pending Approval') Helpers::sendError('Cannot update approved or declined expense');

         $orm->beginTransaction();
         $orm->update('expense', [
            'ExpTitle' => $data['title'],
            'ExpPurpose' => $data['purpose'] ?? null,
            'ExpAmount' => $data['amount'],
            'ExpDate' => $data['date'] ?? date('Y-m-d'),
            'FiscalYearID' => $data['fiscal_year_id'],
            'ExpCategoryID' => $data['category_id']
         ], ['ExpID' => $expenseId]);
         $orm->commit();

         return ['status' => 'success', 'expense_id' => $expenseId];
      } catch (Exception $e) {
         if ($orm->inTransaction())  $orm->rollBack();

         Helpers::logError('Expense update error: ' . $e->getMessage());
         Helpers::sendError('Expense update error.');
      }
   }
   /**
    * Delete an expense entry
    * @param int $expenseId The ID of the expense to delete
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function delete($expenseId)
   {
      $orm = new ORM();
      try {
         // Validate expense exists and is pending
         $expense = $orm->getWhere('expense', ['ExpID' => $expenseId]);
         if (empty($expense)) Helpers::sendError('Expense not found');

         if ($expense[0]['ExpStatus'] !== 'Pending Approval') Helpers::sendError('Cannot delete approved or declined expense');

         $orm->beginTransaction();
         $orm->delete('expense', ['ExpID' => $expenseId]);
         $orm->commit();

         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($orm->inTransaction()) $orm->rollBack();

         Helpers::logError('Expense delete error: ' . $e->getMessage());
         Helpers::sendError('Expense delete failed.');
      }
   }
   /**
    * Get a single expense entry by ID
    * @param int $expenseId The ID of the expense to retrieve
    * @return array|null The expense data or null if not found
    * @throws Exception if database operations fail
    */
   public static function get($expenseId)
   {
      $orm = new ORM();
      try {
         $expense = $orm->selectWithJoin(
            baseTable: 'expense e',
            joins: [
               ['table' => 'churchmember m', 'on' => 'e.MbrID = m.MbrID', 'type' => 'LEFT'],
               ['table' => 'expensecategory ec', 'on' => 'e.ExpCategoryID = ec.ExpCategoryID', 'type' => 'LEFT'],
               ['table' => 'expense_approval ea', 'on' => 'e.ExpID = ea.ExpID', 'type' => 'LEFT'],
               ['table' => 'churchmember ma', 'on' => 'ea.ApproverID = ma.MbrID', 'type' => 'LEFT']
            ],
            fields: [
               'e.*',
               'CONCAT(m.MbrFirstName, " ", m.MbrFamilyName) AS MemberName',
               'ec.ExpCategoryName',
               'ea.ApprovalStatus',
               'ea.ApprovalDate',
               'ea.Comments',
               'CONCAT(ma.MbrFirstName, " ", ma.MbrFamilyName) AS ApproverName',
            ],
            conditions: ['e.ExpID' => ':id'],
            params: [':id' => $expenseId]
         )[0] ?? null;

         if (!$expense) Helpers::sendError('Expense not found');

         return $expense;
      } catch (Exception $e) {
         Helpers::logError('Expense get error: ' . $e->getMessage());
         Helpers::sendError('Expense retrieval error.');
      }
   }
   /**
    * Approve or decline an expense
    * @param int $expenseId The ID of the expense to approve/decline
    * @param int $approverId The ID of the member approving/declining
    * @param string $status 'Approved' or 'Declined'
    * @param string|null $comments Optional comments for the approval/decline
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function approve($expenseId, $approverId, $status, $comments = null)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate inputs
         if (!in_array($status, ['Approved', 'Declined'])) Helpers::sendError('Invalid approval status');
         if (!is_numeric($expenseId) || $expenseId <= 0) Helpers::sendError('Invalid expense ID');
         if (!is_numeric($approverId) || $approverId <= 0) Helpers::sendError('Invalid approver ID');

         // Validate expense exists and is pending
         $expense = $orm->getWhere('expense', ['ExpID' => $expenseId]);
         if (empty($expense)) Helpers::sendError('Expense not found');
         if ($expense[0]['ExpStatus'] !== 'Pending Approval') Helpers::sendError('Expense is already processed');

         // Validate approver exists
         $approver = $orm->getWhere('churchmember', ['MbrID' => $approverId, 'Deleted' => 0]);
         if (empty($approver)) Helpers::sendError('Invalid approver ID');

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->update('expense', [
            'ExpStatus' => $status
         ], ['ExpID' => $expenseId]);

         $orm->insert('expense_approval', [
            'ExpID' => $expenseId,
            'ApproverID' => $approverId,
            'ApprovalStatus' => $status,
            'ApprovalDate' => date('Y-m-d H:i:s'),
            'Comments' => $comments
         ]);

         // Create notification for submitter
         $message = "Your expense '{$expense[0]['ExpTitle']}' for {$expense[0]['ExpAmount']} has been {$status}.";
         if ($comments)  $message .= " Comments: {$comments}";

         $orm->insert('communication', [
            'Title' => "Expense {$status}",
            'Message' => $message,
            'SentBy' => $approverId,
            'TargetGroupID' => null
         ]);
         $orm->commit();

         return ['status' => 'success', 'expense_id' => $expenseId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();
         Helpers::logError('Expense approval error: ' . $e->getMessage());
         Helpers::sendError('Expense approval failed.');
         // throw $e;
      }
   }
   /**
    * Get all expenses with pagination and optional filters
    * @param int $page Page number for pagination
    * @param int $limit Number of records per page
    * @param array $filters Optional filters for fiscal year, category, and status
    * @return array List of expenses with pagination info
    * @throws Exception if database operations fail
    */
   public static function getAll($page = 1, $limit = 10, $filters = [])
   {
      $orm = new ORM();
      try {
         $offset = ($page - 1) * $limit;
         $conditions = [];
         $params = [];

         if (!empty($filters['fiscal_year_id']) && is_numeric($filters['fiscal_year_id'])) {
            $conditions['e.FiscalYearID'] = ':fiscal_year_id';
            $params[':fiscal_year_id'] = $filters['fiscal_year_id'];
         }
         if (!empty($filters['category_id']) && is_numeric($filters['category_id'])) {
            $conditions['e.ExpCategoryID'] = ':category_id';
            $params[':category_id'] = $filters['category_id'];
         }
         if (!empty($filters['status']) && in_array($filters['status'], ['Pending Approval', 'Approved', 'Declined'])) {
            $conditions['e.ExpStatus'] = ':status';
            $params[':status'] = $filters['status'];
         }

         $expenses = $orm->selectWithJoin(
            baseTable: 'expense e',
            joins: [
               ['table' => 'churchmember m', 'on' => 'e.MbrID = m.MbrID', 'type' => 'LEFT'],
               ['table' => 'expensecategory ec', 'on' => 'e.ExpCategoryID = ec.ExpCategoryID', 'type' => 'LEFT'],
               ['table' => 'expense_approval ea', 'on' => 'e.ExpID = ea.ExpID', 'type' => 'LEFT'],
               ['table' => 'churchmember ma', 'on' => 'ea.ApproverID = ma.MbrID', 'type' => 'LEFT']
            ],
            fields: [
               'e.*',
               'CONCAT(m.MbrFirstName, " ", m.MbrFamilyName) AS MemberName',
               'ec.ExpCategoryName',
               'ea.ApprovalStatus',
               'ea.ApprovalDate',
               'ea.Comments',
               'CONCAT(ma.MbrFirstName, " ", ma.MbrFamilyName) AS ApproverName',
            ],
            conditions: $conditions,
            params: $params,
            limit: $limit,
            offset: $offset
         );

         $whereClauses = [];
         foreach ($conditions as $column => $placeholder) $whereClauses[] = "{$column} = {$placeholder}";

         $whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

         $total = $orm->runQuery(
            "SELECT COUNT(*) as total FROM expense e" . $whereSql . '',
            $params
         )[0]['total'];

         return [
            'data' => $expenses,
            'pagination' => [
               'page' => $page,
               'limit' => $limit,
               'total' => $total,
               'pages' => ceil($total / $limit)
            ]
         ];
      } catch (Exception $e) {
         Helpers::logError('Expense getAll error: ' . $e->getMessage());
         Helpers::sendError('Expense retrieval failed');
      }
   }
   /**
    * Get expense reports based on type and optional filters
    * @param string $type Report type (by_category, by_fiscal_year, pending_vs_approved, by_month)
    * @param array $filters Optional filters for the report
    * @return array Report data
    * @throws Exception if report type is invalid or database operations fail
    */
   public static function getReports($type, $filters = [])
   {
      $orm = new ORM();
      try {
         $params = [];
         $sql = '';

         switch ($type) {
            case 'by_category':
               $sql = "SELECT ec.ExpCategoryName, SUM(e.ExpAmount) as total, COUNT(e.ExpID) as count 
                            FROM expense e 
                            JOIN expensecategory ec ON e.ExpCategoryID = ec.ExpCategoryID";
               if (!empty($filters['fiscal_year_id'])) {
                  $sql .= " WHERE e.FiscalYearID = :fiscal_year_id";
                  $params[':fiscal_year_id'] = $filters['fiscal_year_id'];
               }
               $sql .= " GROUP BY ec.ExpCategoryID";
               break;

            case 'by_fiscal_year':
               $sql = "SELECT fy.FiscalYearStartDate, fy.FiscalYearEndDate, SUM(e.ExpAmount) as total, COUNT(e.ExpID) as count 
                            FROM expense e 
                            JOIN fiscalyear fy ON e.FiscalYearID = fy.FiscalYearID 
                            GROUP BY fy.FiscalYearID";
               break;

            case 'pending_vs_approved':
               $sql = "SELECT e.ExpStatus, SUM(e.ExpAmount) as total, COUNT(e.ExpID) as count 
                            FROM expense e";
               if (!empty($filters['fiscal_year_id'])) {
                  $sql .= " WHERE e.FiscalYearID = :fiscal_year_id";
                  $params[':fiscal_year_id'] = $filters['fiscal_year_id'];
               }
               $sql .= " GROUP BY e.ExpStatus";
               break;

            case 'by_month':
               $sql = "SELECT DATE_FORMAT(e.ExpDate, '%Y-%m') as month, SUM(e.ExpAmount) as total, COUNT(e.ExpID) as count 
                            FROM expense e";
               if (!empty($filters['year'])) {
                  $sql .= " WHERE YEAR(e.ExpDate) = :year";
                  $params[':year'] = $filters['year'];
               }
               $sql .= " GROUP BY DATE_FORMAT(e.ExpDate, '%Y-%m')";
               break;

            default:
               Helpers::sendError('Invalid report type');
         }

         $results = $orm->runQuery($sql, $params);

         return ['data' => $results];
      } catch (Exception $e) {
         Helpers::logError('Expense report error: ' . $e->getMessage());
         Helpers::sendError('Expense report failed.');
      }
   }
}
?>