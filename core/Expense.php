<?php

/**
 * Expense Management Class – Full Featured
 *
 * Complete expense lifecycle with approval workflow, categories,
 * fiscal year integration, and audit trail.
 *
 * @package AliveChMS\Core
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

declare(strict_types=1);

class Expense
{
   private const STATUS_PENDING   = 'Pending Approval';
   private const STATUS_APPROVED  = 'Approved';
   private const STATUS_REJECTED  = 'Rejected';
   private const STATUS_CANCELLED = 'Cancelled';

   /**
    * Create a new expense request
    *
    * @param array $data Expense data
    * @return array Success response with expense_id
    */
   public static function create(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'title'          => 'required|max:150',
         'amount'         => 'required|numeric',
         'expense_date'   => 'required|date',
         'category_id'    => 'required|numeric',
            'fiscal_year_id' => 'required|numeric',
         'branch_id'      => 'required|numeric',
         'description'    => 'max:1000|nullable',
      ]);

      $amount       = (float)$data['amount'];
      $expenseDate  = $data['expense_date'];
      $categoryId   = (int)$data['category_id'];
      $fiscalYearId = (int)$data['fiscal_year_id'];
      $branchId     = (int)$data['branch_id'];

      if ($amount <= 0) {
         Helpers::sendFeedback('Expense amount must be greater than zero', 400);
      }

      if ($expenseDate > date('Y-m-d')) {
         Helpers::sendFeedback('Expense date cannot be in the future', 400);
      }

      // Validate references
      $valid = $orm->runQuery(
         "SELECT 
                (SELECT COUNT(*) FROM expense_category WHERE ExpenseCategoryID = :cat AND Deleted = 0) AS cat_ok,
                (SELECT COUNT(*) FROM fiscalyear WHERE FiscalYearID = :fy AND Status = 'Active') AS fy_ok,
                (SELECT COUNT(*) FROM branch WHERE BranchID = :br) AS br_ok",
         [':cat' => $categoryId, ':fy' => $fiscalYearId, ':br' => $branchId]
      )[0];

      if ($valid['cat_ok'] == 0) Helpers::sendFeedback('Invalid expense category', 400);
      if ($valid['fy_ok'] == 0)   Helpers::sendFeedback('Invalid or inactive fiscal year', 400);
      if ($valid['br_ok'] == 0)   Helpers::sendFeedback('Invalid branch', 400);

      $expenseId = $orm->insert('expense', [
         'ExpenseTitle'       => $data['title'],
         'ExpenseDescription' => $data['description'] ?? null,
         'ExpenseAmount'      => $amount,
         'ExpenseDate'        => $expenseDate,
         'ExpenseCategoryID'  => $categoryId,
         'FiscalYearID'       => $fiscalYearId,
         'BranchID'           => $branchId,
         'ExpenseStatus'      => self::STATUS_PENDING,
         'RequestedBy'        => Auth::getCurrentUserId($token ?? ''),
         'RequestedAt'        => date('Y-m-d H:i:s')
      ])['id'];

      Helpers::logError("New expense request: ExpenseID $expenseId | Amount $amount");

      return ['status' => 'success', 'expense_id' => $expenseId];
   }

   /**
    * Approve or reject an expense
    *
    * @param int    $expenseId Expense ID
    * @param string $action    'approve' or 'reject'
    * @param string|null $remarks Optional remarks
    * @return array Success response
    */
   public static function review(int $expenseId, string $action, ?string $remarks = null): array
   {
      $orm = new ORM();

      $expense = $orm->getWhere('expense', ['ExpenseID' => $expenseId])[0] ?? null;
      if (!$expense) {
         Helpers::sendFeedback('Expense not found', 404);
      }

      if ($expense['ExpenseStatus'] !== self::STATUS_PENDING) {
         Helpers::sendFeedback('Only pending expenses can be reviewed', 400);
      }

      if (!in_array($action, ['approve', 'reject'])) {
         Helpers::sendFeedback('Action must be approve or reject', 400);
      }

      $newStatus = ($action === 'approve') ? self::STATUS_APPROVED : self::STATUS_REJECTED;

      $orm->update('expense', [
         'ExpenseStatus'   => $newStatus,
         'ApprovedBy'      => Auth::getCurrentUserId($token ?? ''),
         'ApprovedAt'      => date('Y-m-d H:i:s'),
         'ApprovalRemarks' => $remarks
      ], ['ExpenseID' => $expenseId]);

      Helpers::logError("Expense {$action}d: ExpenseID $expenseId");

      return ['status' => 'success', 'message' => "Expense has been {$action}d"];
   }

   /**
    * Retrieve a single expense with full details
    *
    * @param int $expenseId Expense ID
    * @return array Expense data
    */
   public static function get(int $expenseId): array
   {
      $orm = new ORM();

      $expenses = $orm->selectWithJoin(
            baseTable: 'expense e',
            joins: [
            ['table' => 'expense_category ec', 'on' => 'e.ExpenseCategoryID = ec.ExpenseCategoryID'],
            ['table' => 'fiscalyear fy',       'on' => 'e.FiscalYearID = fy.FiscalYearID'],
            ['table' => 'branch b',            'on' => 'e.BranchID = b.BranchID'],
            ['table' => 'churchmember r',      'on' => 'e.RequestedBy = r.MbrID', 'type' => 'LEFT'],
            ['table' => 'churchmember a',      'on' => 'e.ApprovedBy = a.MbrID', 'type' => 'LEFT']
            ],
            fields: [
            'e.*',
            'ec.CategoryName',
            'fy.YearName AS FiscalYear',
            'b.BranchName',
            'r.MbrFirstName AS RequesterFirstName',
            'r.MbrFamilyName AS RequesterFamilyName',
            'a.MbrFirstName AS ApproverFirstName',
            'a.MbrFamilyName AS ApproverFamilyName'
            ],
         conditions: ['e.ExpenseID' => ':id'],
            params: [':id' => $expenseId]
      );

      if (empty($expenses)) {
         Helpers::sendFeedback('Expense not found', 404);
      }

      return $expenses[0];
   }

   /**
    * Retrieve paginated list of expenses with filters
    *
    * @param int   $page    Page number
    * @param int   $limit   Items per page
    * @param array $filters Filters
    * @return array Paginated result
    */
   public static function getAll(int $page = 1, int $limit = 10, array $filters = []): array
   {
      $orm = new ORM();
      $offset = ($page - 1) * $limit;

      $conditions = [];
      $params = [];

      if (!empty($filters['fiscal_year_id'])) {
         $conditions['e.FiscalYearID'] = ':fy';
         $params[':fy'] = (int)$filters['fiscal_year_id'];
      }
      if (!empty($filters['branch_id'])) {
         $conditions['e.BranchID'] = ':br';
         $params[':br'] = (int)$filters['branch_id'];
      }
      if (!empty($filters['category_id'])) {
         $conditions['e.ExpenseCategoryID'] = ':cat';
         $params[':cat'] = (int)$filters['category_id'];
      }
      if (!empty($filters['status'])) {
         $conditions['e.ExpenseStatus'] = ':status';
            $params[':status'] = $filters['status'];
      }
      if (!empty($filters['start_date'])) {
         $conditions['e.ExpenseDate >='] = ':start';
         $params[':start'] = $filters['start_date'];
      }
      if (!empty($filters['end_date'])) {
         $conditions['e.ExpenseDate <='] = ':end';
         $params[':end'] = $filters['end_date'];
      }

      $expenses = $orm->selectWithJoin(
            baseTable: 'expense e',
            joins: [
            ['table' => 'expense_category ec', 'on' => 'e.ExpenseCategoryID = ec.ExpenseCategoryID'],
            ['table' => 'fiscalyear fy',       'on' => 'e.FiscalYearID = fy.FiscalYearID'],
            ['table' => 'branch b',            'on' => 'e.BranchID = b.BranchID']
            ],
            fields: [
            'e.ExpenseID',
            'e.ExpenseTitle',
            'e.ExpenseAmount',
            'e.ExpenseDate',
            'e.ExpenseStatus',
            'ec.CategoryName',
            'fy.YearName AS FiscalYear',
            'b.BranchName'
            ],
            conditions: $conditions,
            params: $params,
         orderBy: ['e.ExpenseDate' => 'DESC'],
            limit: $limit,
            offset: $offset
      );

      $total = $orm->runQuery(
         "SELECT COUNT(*) AS total FROM expense e" . ($conditions ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
            $params
      )[0]['total'];

      return [
            'data' => $expenses,
            'pagination' => [
            'page'  => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => (int)ceil($total / $limit)
            ]
      ];
   }

   /**
    * Cancel a pending expense request
    *
    * Only pending (not yet approved) expenses can be cancelled.
    * Approved expenses must be reversed via a separate correcting entry.
    *
    * @param int    $expenseId Expense ID
    * @param string $reason    Reason for cancellation (required for audit)
    * @return array Success response
    */
   public static function cancel(int $expenseId, string $reason): array
   {
      if (trim($reason) === '') {
         Helpers::sendFeedback('Cancellation reason is required', 400);
      }

      $orm = new ORM();

      $expense = $orm->getWhere('expense', ['ExpenseID' => $expenseId])[0] ?? null;
      if (!$expense) {
         Helpers::sendFeedback('Expense not found', 404);
      }

      if ($expense['ExpenseStatus'] === self::STATUS_APPROVED) {
         Helpers::sendFeedback('Approved expenses cannot be cancelled. Use a reversing entry instead.', 400);
      }

      if ($expense['ExpenseStatus'] === self::STATUS_CANCELLED) {
         Helpers::sendFeedback('Expense is already cancelled', 400);
      }

      $orm->update('expense', [
         'ExpenseStatus'       => self::STATUS_CANCELLED,
         'CancellationReason'  => $reason,
         'CancelledBy'         => Auth::getCurrentUserId($token ?? ''),
         'CancelledAt'         => date('Y-m-d H:i:s')
      ], ['ExpenseID' => $expenseId]);

      Helpers::logError("Expense cancelled: ExpenseID $expenseId | Reason: $reason");

      return [
         'status'  => 'success',
         'message' => 'Expense has been cancelled'
      ];
   }
}