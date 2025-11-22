<?php

/**
 * Budget Management Class
 *
 * Complete budget lifecycle including individual line-item management,
 * submission, approval workflow, and audit trail.
 *
 * @package AliveChMS\Core
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

declare(strict_types=1);

class Budget
{
   private const STATUS_DRAFT     = 'Draft';
   private const STATUS_SUBMITTED = 'Submitted';
   private const STATUS_APPROVED  = 'Approved';
   private const STATUS_REJECTED  = 'Rejected';

   // =====================================================================
   // BUDGET LEVEL OPERATIONS
   // =====================================================================

   public static function create(array $data)
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'fiscal_year_id' => 'required|numeric',
         'branch_id'      => 'required|numeric',
         'title'          => 'required|max:150',
         'description'    => 'max:500',
         'items'          => 'required'
      ]);

      if (!is_array($data['items']) || empty($data['items'])) {
         Helpers::sendFeedback('At least one budget item is required', 400);
      }

      $fiscalYearId = (int)$data['fiscal_year_id'];
      $branchId     = (int)$data['branch_id'];

      // Validate references
      if (empty($orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId, 'Status' => 'Active']))) {
         Helpers::sendFeedback('Invalid or inactive fiscal year', 400);
      }
      if (empty($orm->getWhere('branch', ['BranchID' => $branchId]))) {
         Helpers::sendFeedback('Invalid branch', 400);
      }

      $orm->beginTransaction();
      try {
         $budgetId = $orm->insert('budget', [
            'FiscalYearID'       => $fiscalYearId,
            'BranchID'           => $branchId,
            'BudgetTitle'        => $data['title'],
            'BudgetDescription'  => $data['description'] ?? null,
            'BudgetStatus'       => self::STATUS_DRAFT,
            'CreatedBy'          => Auth::getCurrentUserId($token ?? ''),
            'CreatedAt'          => date('Y-m-d H:i:s'),
            'TotalAmount'        => 0
         ])['id'];

         $total = self::recalculateTotal($budgetId, $data['items'], $orm);

         $orm->update('budget', ['TotalAmount' => $total], ['BudgetID' => $budgetId]);
         $orm->commit();

         return ['status' => 'success', 'budget_id' => $budgetId];
      } catch (Exception $e) {
         $orm->rollBack();
         Helpers::sendFeedback($e->getMessage(), 400);
      }
   }

   public static function update(int $budgetId, array $data): array
   {
      $orm = new ORM();
      self::ensureDraft($budgetId);

      $update = [];
      if (!empty($data['title']))       $update['BudgetTitle'] = $data['title'];
      if (isset($data['description']))  $update['BudgetDescription'] = $data['description'];

      if (!empty($update)) {
         $orm->update('budget', $update, ['BudgetID' => $budgetId]);
      }

      return ['status' => 'success', 'budget_id' => $budgetId];
   }

   public static function submitForApproval(int $budgetId): array
   {
      self::ensureDraft($budgetId);
      $orm = new ORM();

      $orm->update('budget', [
         'BudgetStatus' => self::STATUS_SUBMITTED,
         'SubmittedAt'  => date('Y-m-d H:i:s')
      ], ['BudgetID' => $budgetId]);

      Helpers::logError("Budget submitted: BudgetID $budgetId");
      return ['status' => 'success', 'message' => 'Budget submitted for approval'];
   }

   public static function review(int $budgetId, string $action, ?string $remarks = null): array
   {
      $orm = new ORM();
      $budget = $orm->getWhere('budget', ['BudgetID' => $budgetId])[0] ?? null;
      if (!$budget || $budget['BudgetStatus'] !== self::STATUS_SUBMITTED) {
         Helpers::sendFeedback('Only submitted budgets can be reviewed', 400);
      }

      $newStatus = ($action === 'approve') ? self::STATUS_APPROVED : self::STATUS_REJECTED;

      $orm->update('budget', [
         'BudgetStatus'     => $newStatus,
         'ApprovedBy'       => Auth::getCurrentUserId($token ?? ''),
         'ApprovedAt'       => date('Y-m-d H:i:s'),
         'ApprovalRemarks'  => $remarks
      ], ['BudgetID' => $budgetId]);

      return ['status' => 'success', 'message' => "Budget has been {$action}d"];
   }

   public static function get(int $budgetId): array
   {
      $orm = new ORM();

      $budgets = $orm->selectWithJoin(
         baseTable: 'budget b',
         joins: [
            ['table' => 'fiscalyear f',  'on' => 'b.FiscalYearID = f.FiscalYearID'],
            ['table' => 'branch br',     'on' => 'b.BranchID = br.BranchID'],
            ['table' => 'churchmember c', 'on' => 'b.CreatedBy = c.MbrID', 'type' => 'LEFT'],
            ['table' => 'churchmember a', 'on' => 'b.ApprovedBy = a.MbrID', 'type' => 'LEFT']
         ],
         fields: [
            'b.*',
            'f.YearName AS FiscalYear',
            'br.BranchName',
            'c.MbrFirstName AS CreatorFirstName',
            'c.MbrFamilyName AS CreatorFamilyName',
            'a.MbrFirstName AS ApproverFirstName',
            'a.MbrFamilyName AS ApproverFamilyName'
         ],
         conditions: ['b.BudgetID' => ':id'],
         params: [':id' => $budgetId]
      );

      if (empty($budgets)) Helpers::sendFeedback('Budget not found', 404);

      $items = $orm->getWhere('budget_items', ['BudgetID' => $budgetId]);
      $budget = $budgets[0];
      $budget['items'] = $items;

      return $budget;
   }

   public static function getAll(int $page = 1, int $limit = 10, array $filters = []): array
   {
      $orm = new ORM();
      $offset = ($page - 1) * $limit;

      $conditions = [];
      $params = [];
      if (!empty($filters['fiscal_year_id'])) {
         $conditions['b.FiscalYearID'] = ':fy';
         $params[':fy'] = $filters['fiscal_year_id'];
      }
      if (!empty($filters['branch_id'])) {
         $conditions['b.BranchID']     = ':br';
         $params[':br'] = $filters['branch_id'];
      }
      if (!empty($filters['status'])) {
         $conditions['b.BudgetStatus'] = ':st';
         $params[':st'] = $filters['status'];
      }

      $list = $orm->selectWithJoin(
         baseTable: 'budget b',
         joins: [
            ['table' => 'fiscalyear f', 'on' => 'b.FiscalYearID = f.FiscalYearID'],
            ['table' => 'branch br',    'on' => 'b.BranchID = br.BranchID']
         ],
         fields: ['b.BudgetID', 'b.BudgetTitle', 'b.TotalAmount', 'b.BudgetStatus', 'b.CreatedAt', 'f.YearName', 'br.BranchName'],
         conditions: $conditions,
         params: $params,
         orderBy: ['b.CreatedAt' => 'DESC'],
         limit: $limit,
         offset: $offset
      );

      $total = $orm->runQuery("SELECT COUNT(*) AS total FROM budget b" . ($conditions ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''), $params)[0]['total'];

      return [
         'data' => $list,
         'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => (int)ceil($total / $limit)
         ]
      ];
   }

   // =====================================================================
   // BUDGET ITEMS MANAGEMENT
   // =====================================================================

   public static function addItem(int $budgetId, array $item): array
   {
      $orm = new ORM();
      self::ensureDraft($budgetId);

      Helpers::validateInput($item, [
         'category'    => 'required|max:100',
         'description' => 'max:300',
         'amount'      => 'required|numeric'
      ]);

      $amount = (float)$item['amount'];
      if ($amount <= 0) Helpers::sendFeedback('Amount must be greater than zero', 400);

      $orm->beginTransaction();
      try {
         $orm->insert('budget_items', [
            'BudgetID'    => $budgetId,
            'Category'    => $item['category'],
            'Description' => $item['description'] ?? null,
            'Amount'      => $amount
         ]);

         self::recalculateTotal($budgetId, [], $orm); // pass empty array → just recalc
         $orm->commit();

         return ['status' => 'success', 'message' => 'Item added'];
      } catch (Exception $e) {
         $orm->rollBack();
         Helpers::sendFeedback('Failed to add item', 400);
      }
   }

   public static function updateItem(int $itemId, array $data): array
   {
      $orm = new ORM();
      $item = $orm->getWhere('budget_items', ['ItemID' => $itemId])[0] ?? null;
      if (!$item) Helpers::sendFeedback('Item not found', 404);

      self::ensureDraft((int)$item['BudgetID']);

      $update = [];
      if (!empty($data['category']))    $update['Category']    = $data['category'];
      if (isset($data['description']))  $update['Description'] = $data['description'];
      if (!empty($data['amount'])) {
         $amount = (float)$data['amount'];
         if ($amount <= 0) Helpers::sendFeedback('Amount must be > 0', 400);
         $update['Amount'] = $amount;
      }

      if (empty($update)) return ['status' => 'success', 'message' => 'No changes'];

      $orm->beginTransaction();
      try {
         $orm->update('budget_items', $update, ['ItemID' => $itemId]);
         self::recalculateTotal((int)$item['BudgetID'], [], $orm);
         $orm->commit();

         return ['status' => 'success', 'message' => 'Item updated'];
      } catch (Exception $e) {
         $orm->rollBack();
         Helpers::sendFeedback('Failed to update item', 400);
      }
   }

   public static function deleteItem(int $itemId): array
   {
      $orm = new ORM();
      $item = $orm->getWhere('budget_items', ['ItemID' => $itemId])[0] ?? null;
      if (!$item) Helpers::sendFeedback('Item not found', 404);

      self::ensureDraft((int)$item['BudgetID']);

      $orm->beginTransaction();
      try {
         $orm->delete('budget_items', ['ItemID' => $itemId]);
         self::recalculateTotal((int)$item['BudgetID'], [], $orm);
         $orm->commit();

         return ['status' => 'success', 'message' => 'Item deleted'];
      } catch (Exception $e) {
         $orm->rollBack();
         Helpers::sendFeedback('Failed to delete item', 400);
      }
   }

   // =====================================================================
   // Helper Methods
   // =====================================================================

   private static function ensureDraft(int $budgetId): void
   {
      $orm = new ORM();
      $b = $orm->getWhere('budget', ['BudgetID' => $budgetId])[0] ?? null;
      if (!$b) Helpers::sendFeedback('Budget not found', 404);
      if ($b['BudgetStatus'] !== self::STATUS_DRAFT) {
         Helpers::sendFeedback('Only draft budgets can be modified', 400);
      }
   }

   private static function recalculateTotal(int $budgetId, array $newItems, ORM $orm): float
   {
      $existing = $orm->getWhere('budget_items', ['BudgetID' => $budgetId]);
      $items = !empty($newItems) ? $newItems : $existing;

      $total = 0;
      foreach ($items as $i) {
         $total += (float)($i['Amount'] ?? 0);
      }

      $orm->update('budget', ['TotalAmount' => $total], ['BudgetID' => $budgetId]);
      return $total;
   }
}