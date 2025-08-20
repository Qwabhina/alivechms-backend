<?php

/** Budget Management Class
 * This class provides methods for managing church budgets, including creating, updating, deleting, and viewing budgets and budget items.
 * It also handles budget submission for approval and budget approvals.
 * It requires authentication and permission checks for each operation.
 */
class Budget
{
   /**
    * Create a new budget entry in churchbudget
    * @param array $data Budget data including title, fiscal_year, summary, and optional items
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function create($data)
   {
      $orm = new ORM();
      $transactionStarted = false;

      try {
         Helpers::validateInput($data, [
            'title' => 'required|string',
            'fiscal_year' => 'required|numeric',
            'summary' => 'string|nullable '
         ]);

         $orm->beginTransaction();
         $transactionStarted = true;

         $budgetId = $orm->insert('churchbudget', [
            'BudgetTitle' => $data['title'],
            'FiscalYearID' => $data['fiscal_year'],
            'BudgetSummary' => $data['summary'] ?? null,
            'BudgetStatus' => 'Draft'
         ])['id'];

         // Handle optional budget items
         if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
               self::createItem($budgetId, $item);
            }
         }

         $orm->commit();
         $transactionStarted = false;

         return ['status' => 'success', 'BudgetID ' => $budgetId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();
         Helpers::logError('Budget create error: ' . $e->getMessage());
         Helpers::sendFeedback('Budget creation failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Create a single budget item
    * @param int $budgetId ID of the budget
    * @param array $data Item data including item_name, amount, category, subcategory_id
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function createItem($budgetId, $data)
   {
      $orm = new ORM();
      try {
         Helpers::validateInput($data, [
            'item_name' => 'required|string|max:100',
            'amount' => 'required|numeric',
            'category' => 'required|in:Income,Expense',
            'subcategory_id' => 'required|numeric'
         ]);

         // Validate amount is positive
         if ($data['amount'] <= 0) Helpers::sendFeedback('Item amount must be positive', 400);

         // Validate budget exists
         $budget = $orm->getWhere('churchbudget', ['BudgetID' => $budgetId]);
         if (empty($budget)) Helpers::sendFeedback('Budget not found', 404);

         // Validate subcategory exists and matches category
         $subcategory = $orm->getWhere(
            'budget_item_category',
            [
               'SubcategoryID' => $data['subcategory_id'],
               'Category' => $data['category']
            ]
         );
         if (empty($subcategory)) Helpers::sendFeedback('Invalid subcategory for category', 400);

         $itemId = $orm->insert('budget_items', [
            'BudgetID' => $budgetId,
            'ItemName' => $data['item_name'],
            'Amount' => $data['amount'],
            'Category' => $data['category'],
            'SubcategoryID' => $data['subcategory_id']
         ])['id'];

         return ['status' => 'success', 'item_id' => $itemId];
      } catch (Exception $e) {
         Helpers::logError('Budget item create error: ' . $e->getMessage());
         Helpers::sendFeedback('Budget item creation failed: ' . $e->getMessage(), 400);
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
      $transactionStarted = false;
      try {
         Helpers::validateInput($data, [
            'title' => 'string|max:100|nullable',
            'summary' => 'string|nullable',
            'status' => 'string|in:Draft,Pending,Approved,Rejected|nullable'
         ]);

         // Validate budget exists
         $budget = $orm->getWhere('churchbudget', ['BudgetID' => $budgetId]);
         if (empty($budget)) Helpers::sendFeedback('Budget not found', 404);

         // Prevent updating approved budgets
         if ($budget[0]['BudgetStatus'] === 'Approved') Helpers::sendFeedback('Cannot update an approved budget', 400);

         $orm->beginTransaction();
         $transactionStarted = true;

         $updateData = [];
         if (isset($data['title'])) $updateData['BudgetTitle'] = $data['title'];
         if (isset($data['summary'])) $updateData['BudgetSummary'] = $data['summary'];
         if (isset($data['status'])) $updateData['BugetStatus'] = $data['status'];

         if (!empty($updateData)) $orm->update('churchbudget', $updateData, ['BudgetID' => $budgetId]);

         // Handle budget items updates
         if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
               if (isset($item['item_id'])) {
                  self::updateItem($item['item_id'], $item);
               } else {
                  self::createItem($budgetId, $item);
               }
            }
         }

         $orm->commit();
         return ['status' => 'success', 'BudgetID ' => $budgetId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();
         Helpers::logError('Budget update error: ' . $e->getMessage());
         Helpers::sendFeedback('Budget update failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Update an existing budget item
    * @param int $itemId ID of the budget item to update
    * @param array $data Updated item data
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function updateItem($itemId, $data)
   {
      $orm = new ORM();
      try {
         Helpers::validateInput($data, [
            'item_name' => 'string|max:100|nullable',
            'amount' => 'numeric|nullable',
            'category' => 'string|in:Income,Expense|nullable',
            'subcategory_id' => 'numeric|nullable'
         ]);

         // Validate item exists
         $item = $orm->getWhere('budget_items', ['ItemID' => $itemId]);
         if (empty($item)) Helpers::sendFeedback('Budget item not found', 404);

         // Validate budget is not approved
         $budget = $orm->getWhere('churchbudget', ['BudgetID' => $item[0]['BudgetID']]);
         if ($budget[0]['BudgetStatus'] === 'Approved') Helpers::sendFeedback('Cannot edit items of approved budget', 400);

         // Validate subcategory if provided
         if (isset($data['subcategory_id']) && isset($data['category'])) {
            $subcategory = $orm->getWhere('budget_item_category', ['SubcategoryID' => $data['subcategory_id'], 'Category' => $data['category']]);
            if (empty($subcategory)) Helpers::sendFeedback('Invalid subcategory for category', 400);
         }

         $updateData = [];
         if (isset($data['item_name'])) $updateData['ItemName'] = $data['item_name'];
         if (isset($data['amount'])) {
            if ($data['amount'] <= 0) Helpers::sendFeedback('Item amount must be positive', 400);
            $updateData['Amount'] = $data['amount'];
         }
         if (isset($data['category'])) $updateData['Category'] = $data['category'];
         if (isset($data['subcategory_id'])) $updateData['SubcategoryID'] = $data['subcategory_id'];

         if (!empty($updateData))  $orm->update('budget_items', $updateData, ['ItemID' => $itemId]);

         return ['status' => 'success', 'item_id' => $itemId];
      } catch (Exception $e) {
         Helpers::logError('Budget item update error: ' . $e->getMessage());
         Helpers::sendFeedback('Budget item update failed: ' . $e->getMessage(), 400);
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
      $transactionStarted = false;
      try {
         $budget = $orm->getWhere('churchbudget', ['BudgetID' => $budgetId]);
         if (empty($budget)) Helpers::sendFeedback('Budget not found', 404);

         if ($budget[0]['BudgetStatus'] === 'Approved') Helpers::sendFeedback('Cannot delete approved budget', 400);

         $orm->beginTransaction();
         $transactionStarted = true;
         $orm->delete('churchbudget', ['BudgetID' => $budgetId]); // Cascades to budget_items
         $orm->commit();

         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();
         Helpers::logError('Budget delete error: ' . $e->getMessage());
         Helpers::sendFeedback('Budget delete failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Delete a budget item
    * @param int $itemId ID of the budget item to delete
    * @return array Result of the operation
    * @throws Exception if item does not exist or budget is approved
    */
   public static function deleteItem($itemId)
   {
      $orm = new ORM();
      try {
         $item = $orm->getWhere('budget_items', ['ItemID' => $itemId]);
         if (empty($item)) Helpers::sendFeedback('Budget item not found', 404);

         $budget = $orm->getWhere('churchbudget', ['BudgetID' => $item[0]['BudgetID']]);
         if ($budget[0]['status'] === 'Approved') Helpers::sendFeedback('Cannot delete items of approved budget', 400);

         $orm->delete('budget_items', ['ItemID' => $itemId]);
         return ['status' => 'success'];
      } catch (Exception $e) {
         Helpers::logError('Budget item delete error: ' . $e->getMessage());
         Helpers::sendFeedback('Budget item delete failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Submit a budget for approval
    * @param int $budgetId ID of the budget to submit
    * @param array $approvers List of user IDs to assign as approvers
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function submitForApproval($budgetId, $approvers)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         $budget = $orm->getWhere('churchbudget', ['BudgetID' => $budgetId]);
         if (empty($budget)) Helpers::sendFeedback('Budget not found', 404);

         if ($budget[0]['BudgetStatus'] !== 'Draft') Helpers::sendFeedback('Only draft budgets can be submitted for approval', 400);

         // Validate approvers
         if (!is_array($approvers) || empty($approvers)) Helpers::sendFeedback('At least one approver is required', 400);

         foreach ($approvers as $userId) {
            $user = $orm->getWhere('churchmember', ['MbrID' => $userId]);
            if (empty($user)) Helpers::sendFeedback("Invalid user ID: $userId", 400);
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         // Update budget status to Pending
         $orm->update('churchbudget', ['BudgetStatus' => 'Pending'], ['BudgetID' => $budgetId]);

         // Create approval records
         foreach ($approvers as $userId) {
            $orm->insert('budget_approvals', [
               'BudgetID' => $budgetId,
               'ApprovedBy' => $userId,
               'ApprovalStatus' => 'Pending'
            ]);
         }

         $orm->commit();
         return ['status' => 'success', 'BudgetID' => $budgetId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();
         Helpers::logError('Budget submit error: ' . $e->getMessage());
         Helpers::sendFeedback('Budget submission failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Approve or reject a budget
    * @param int $approvalId ID of the approval record
    * @param array $data Approval data including status and comments
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function approve($approvalId, $data)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         Helpers::validateInput($data, [
            'status' => 'required|string|in:Approved,Rejected',
            'comments' => 'string|nullable'
         ]);

         $approval = $orm->getWhere('budget_approvals', ['ApprovalID' => $approvalId]);
         if (empty($approval)) Helpers::sendFeedback('Approval record not found', 404);

         $budget = $orm->getWhere('churchbudget', ['BudgetID' => $approval[0]['BudgetID']]);
         if ($budget[0]['BudgetStatus'] !== 'Pending') Helpers::sendFeedback('Budget is not pending approval', 400);

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->update('budget_approvals', [
            'ApprovalStatus' => $data['status'],
            'ApprovalComments' => $data['comments'] ?? null,
            'ApprovedAt' => date('Y-m-d H:i:s')
         ], ['ApprovalID' => $approvalId]);

         // Check if all approvals are complete
         $pendingApprovals = $orm->getWhere('budget_approvals', ['BudgetID' => $approval[0]['BudgetID'], 'ApprovalStatus' => 'Pending']);
         $rejectedApprovals = $orm->getWhere('budget_approvals', ['BudgetID' => $approval[0]['BudgetID'], 'ApprovalStatus' => 'Rejected']);
         if (empty($pendingApprovals)) {
            $newStatus = empty($rejectedApprovals) ? 'Approved' : 'Rejected';
            $orm->update('churchbudget', ['BudgetStatus' => $newStatus], ['BudgetID' => $approval[0]['BudgetID']]);
         }

         $orm->commit();
         return ['status' => 'success', 'approval_id' => $approvalId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();
         Helpers::logError('Budget approval error: ' . $e->getMessage());
         Helpers::sendFeedback('Budget approval failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Get a specific budget entry by ID
    * @param int $budgetId ID of the budget to retrieve
    * @return array Budget details including items and approvals
    * @throws Exception if budget not found or database operation fails
    */
   public static function get($budgetId)
   {
      $orm = new ORM();
      try {
         $budget = $orm->selectWithJoin(
            baseTable: 'churchbudget b',
            joins: [],
            fields: ['b.*'],
            conditions: ['b.BudgetID' => ':id'],
            params: [':id' => $budgetId]
         )[0] ?? null;

         if (!$budget) Helpers::sendFeedback('Budget not found', 404);

         // Fetch budget items
         $items = $orm->selectWithJoin(
            baseTable: 'budget_items bi',
            joins: [
               ['table' => 'budget_item_category bic', 'on' => 'bi.SubcategoryID = bic.SubcategoryID', 'type' => 'LEFT']
            ],
            fields: [
               'bi.ItemID',
               'bi.ItemName',
               'bi.Amount',
               'bi.Category',
               'bi.SubcategoryID',
               'bic.SubcategoryName'
            ],
            conditions: ['bi.BudgetID' => ':id'],
            params: [':id' => $budgetId]
         );

         // Fetch approvals
         $approvals = $orm->selectWithJoin(
            baseTable: 'budget_approvals ba',
            joins: [
               ['table' => 'churchmember u', 'on' => 'ba.ApprovedBy = u.MbrID', 'type' => 'LEFT'],
               ['table' => 'memberrole mr', 'on' => 'u.MbrID = mr.MbrID', 'type' => 'LEFT'],
               ['table' => 'churchrole cr', 'on' => 'cr.RoleID = mr.ChurchRoleID', 'type' => 'LEFT'],
               ['table' => 'userauthentication auth', 'on' => 'auth.MbrID = u.MbrID', 'type' => 'LEFT']
            ],
            fields: [
               'ba.ApprovalID',
               'ba.ApprovalStatus',
               'ba.ApprovalComments',
               'ba.ApprovedAt',
               'Auth.Username',
               'CONCAT(u.MbrFirstName, u.MbrFamilyName) AS FullName',
               'cr.RoleName'
            ],
            conditions: ['ba.BudgetID' => ':id'],
            params: [':id' => $budgetId]
         );

         return [
            'budget' => $budget,
            'items' => $items,
            'approvals' => $approvals
         ];
      } catch (Exception $e) {
         Helpers::logError('Budget get error: ' . $e->getMessage());
         Helpers::sendFeedback('Budget retrieval failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Get all budgets with pagination and optional filters
    * @param int $page Page number for pagination
    * @param int $limit Number of records per page
    * @param array $filters Optional filters for FiscalYear, Status
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

         if (!empty($filters['FiscalYear'])) {
            $conditions['b.FiscalYearID'] = ':fiscal_year';
            $params[':fiscal_year'] = $filters['FiscalYear'];
         }
         if (!empty($filters['Status'])) {
            $conditions['b.BudgetStatus'] = ':status';
            $params[':status'] = $filters['Status'];
         }

         $budgets = $orm->selectWithJoin(
            baseTable: 'churchbudget b',
            joins: [],
            fields: ['b.*'],
            conditions: $conditions,
            params: $params,
            limit: $limit,
            offset: $offset
         );

         foreach ($budgets as &$budget) {
            if (!isset($budget['BudgetID'])) {
               Helpers::logError('BudgetID missing in budget data: ' . json_encode($budget));
               continue;
            }

            $budget['items'] = $orm->selectWithJoin(
               baseTable: 'budget_items bi',
               joins: [
                  ['table' => 'budget_item_category bic', 'on' => 'bi.SubcategoryID = bic.SubcategoryID', 'type' => 'LEFT']
               ],
               fields: [
                  'bi.ItemID',
                  'bi.ItemName',
                  'bi.Amount',
                  'bi.Category',
                  'bi.SubcategoryID',
                  'bic.SubcategoryName'
               ],
               conditions: ['bi.BudgetID' => ':id'],
               params: [':id' => $budget['BudgetID']]
            );

            $budget['approvals'] = $orm->selectWithJoin(
               baseTable: 'budget_approvals ba',
               joins: [
                  ['table' => 'churchmember u', 'on' => 'ba.ApprovedBy = u.MbrID', 'type' => 'LEFT'],
                  ['table' => 'memberrole mr', 'on' => 'u.MbrID = mr.MbrID', 'type' => 'LEFT'],
                  ['table' => 'churchrole cr', 'on' => 'cr.RoleID = mr.ChurchRoleID', 'type' => 'LEFT'],
                  ['table' => 'userauthentication auth', 'on' => 'auth.MbrID = u.MbrID', 'type' => 'LEFT']
               ],
               fields: [
                  'ba.ApprovalID',
                  'ba.ApprovalStatus',
                  'ba.ApprovalComments',
                  'ba.ApprovedAt',
                  'auth.Username',
                  'CONCAT(u.MbrFirstName, u.MbrFamilyName) AS FullName',
                  'cr.RoleName'
               ],
               conditions: ['ba.BudgetID' => ':id'],
               params: [':id' => $budget['BudgetID']]
            );
         }
         unset($budget); // Unset reference to avoid side effects

         // Fix the total count query
         $whereClause = '';
         if (!empty($conditions)) {
            $whereConditions = [];
            foreach ($conditions as $column => $placeholder) {
               $whereConditions[] = "$column = $placeholder";
            }
            $whereClause = ' WHERE ' . implode(' AND ', $whereConditions);
         }

         $total = $orm->runQuery(
            "SELECT COUNT(*) as total FROM churchbudget b" . $whereClause,
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
         Helpers::sendFeedback('Budget retrieval failed: ' . $e->getMessage(), 400);
      }
   }
}