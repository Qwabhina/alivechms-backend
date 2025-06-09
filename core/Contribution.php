<?php

/** Contribution Management Class
 * Handles contribution creation, updating, deletion, retrieval, and listing
 * Validates inputs and ensures data integrity
 * Implements error handling and transaction management
 * @package Contribution
 */
class Contribution
{
   /**
    * Create a new contribution entry
    * @param array $data Contribution data including amount, date, contribution type, and member ID
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function create($data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'contribution_type_id' => 'required|numeric',
            'member_id' => 'required|numeric'
         ]);

         // Validate amount is positive
         if ($data['amount'] <= 0) Helpers::sendError('Contribution amount must be positive');

         // Validate contribution date format (YYYY-MM-DD)
         if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) Helpers::sendError('Invalid date format (YYYY-MM-DD)');

         // Validate member exists
         $member = $orm->getWhere('churchmember', ['MbrID' => $data['member_id'], 'Deleted' => 0]);
         if (empty($member)) Helpers::sendError('Invalid member ID');

         // Validate contribution type exists
         $contributionType = $orm->getWhere('contributiontype', ['ContributionTypeID' => $data['contribution_type_id']]);
         if (empty($contributionType)) Helpers::sendError('Invalid contribution type');

         $orm->beginTransaction();
         $transactionStarted = true;

         $contributionId = $orm->insert('contribution', [
            'ContributionAmount' => $data['amount'],
            'ContributionDate' => $data['date'],
            'ContributionTypeID' => $data['contribution_type_id'],
            'MbrID' => $data['member_id'],
            'Purpose' => $data['purpose'] ?? null
         ])['id'];

         // Create notification for admins
         $adminUsers = $orm->selectWithJoin(
            baseTable: 'userauthentication u',
            joins: [
               ['table' => 'memberrole mr', 'on' => 'u.MbrID = mr.MbrID'],
               ['table' => 'churchrole cr', 'on' => 'mr.ChurchRoleID = cr.RoleID'],
               ['table' => 'rolepermission rp', 'on' => 'cr.RoleID = rp.RoleID'],
               ['table' => 'permission p', 'on' => 'rp.PermissionID = p.PermissionID']
            ],
            fields: ['u.MbrID'],
            conditions: ['p.PermissionName' => ':permission'],
            params: [':permission' => 'view_contributions']
         );

         foreach ($adminUsers as $admin) {
            $orm->insert('communication', [
               'Title' => 'New Contribution Submitted',
               'Message' => "Contribution of {$data['amount']} submitted by member ID {$data['member_id']}.",
               'SentBy' => $data['member_id'],
               'TargetGroupID' => null
            ]);
         }
         $orm->commit();

         return ['status' => 'success', 'contribution_id' => $contributionId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();
         Helpers::logError('Contribution create error: ' . $e->getMessage());
         Helpers::sendError('Contribution creation error.');
      }
   }

   /**
    * Update an existing contribution entry
    * @param int $contributionId The ID of the contribution to update
    * @param array $data Updated contribution data
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function update($contributionId, $data)
   {
      $orm = new ORM();
      $transactionStarted = false;

      try {
         // Validate input
         Helpers::validateInput($data, [
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'contribution_type_id' => 'required|numeric'
         ]);

         // Validate amount is positive
         if ($data['amount'] <= 0) Helpers::sendError('Contribution amount must be positive');

         // Validate contribution date format (YYYY-MM-DD)
         if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) Helpers::sendError('Invalid date format (YYYY-MM-DD)');

         // Validate contribution exists
         $contribution = $orm->getWhere('contribution', ['ContributionID' => $contributionId]);
         if (empty($contribution)) Helpers::sendError('Contribution not found');

         // Validate contribution type exists
         $contributionType = $orm->getWhere('contributiontype', ['ContributionTypeID' => $data['contribution_type_id']]);
         if (empty($contributionType)) Helpers::sendError('Invalid contribution type');

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->update('contribution', [
            'ContributionAmount' => $data['amount'],
            'ContributionDate' => $data['date'],
            'ContributionTypeID' => $data['contribution_type_id'],
            'Purpose' => $data['purpose'] ?? null
         ], ['ContributionID' => $contributionId]);
         $orm->commit();

         return ['status' => 'success', 'contribution_id' => $contributionId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();
         Helpers::logError('Contribution update error: ' . $e->getMessage());
         Helpers::sendError('Contribution update error.');
      }
   }

   /**
    * Delete a contribution entry
    * @param int $contributionId The ID of the contribution to delete
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function delete($contributionId)
   {
      $orm = new ORM();
      try {
         // Validate contribution exists
         $contribution = $orm->getWhere('contribution', ['ContributionID' => $contributionId]);
         if (empty($contribution)) Helpers::sendError('Contribution not found');

         $orm->beginTransaction();
         $orm->delete('contribution', ['ContributionID' => $contributionId]);
         $orm->commit();

         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($orm->inTransaction()) $orm->rollBack();
         Helpers::logError('Contribution delete error: ' . $e->getMessage());
         Helpers::sendError('Contribution delete failed.');
      }
   }

   /**
    * Get a single contribution entry by ID
    * @param int $contributionId The ID of the contribution to retrieve
    * @return array|null The contribution data or null if not found
    * @throws Exception if database operations fail
    */
   public static function get($contributionId)
   {
      $orm = new ORM();
      try {
         $contribution = $orm->selectWithJoin(
            baseTable: 'contribution c',
            joins: [
               ['table' => 'churchmember m', 'on' => 'c.MbrID = m.MbrID', 'type' => 'LEFT'],
               ['table' => 'contributiontype ct', 'on' => 'c.ContributionTypeID = ct.ContributionTypeID', 'type' => 'LEFT']
            ],
            fields: [
               'c.*',
               'm.MbrFirstName',
               'm.MbrFamilyName',
               'ct.ContributionTypeName'
            ],
            conditions: ['c.ContributionID' => ':id'],
            params: [':id' => $contributionId]
         )[0] ?? null;

         if (!$contribution) Helpers::sendError('Contribution not found');

         return $contribution;
      } catch (Exception $e) {
         Helpers::logError('Contribution get error: ' . $e->getMessage());
         Helpers::sendError('Contribution retrieval error.');
      }
   }

   /**
    * Get all contributions with pagination and optional filters
    * @param int $page Page number for pagination
    * @param int $limit Number of records per page
    * @param array $filters Optional filters for contribution type or date range
    * @return array List of contributions with pagination info
    * @throws Exception if database operations fail
    */
   public static function getAll($page = 1, $limit = 10, $filters = [])
   {
      $orm = new ORM();
      try {
         $offset = ($page - 1) * $limit;
         $conditions = [];
         $params = [];

         // Apply filters
         if (!empty($filters['contribution_type_id']) && is_numeric($filters['contribution_type_id'])) {
            $conditions['c.ContributionTypeID'] = ':contribution_type_id';
            $params[':contribution_type_id'] = $filters['contribution_type_id'];
         }
         if (!empty($filters['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['start_date'])) {
            $conditions['c.ContributionDate >='] = ':start_date';
            $params[':start_date'] = $filters['start_date'];
         }
         if (!empty($filters['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['end_date'])) {
            $conditions['c.ContributionDate <='] = ':end_date';
            $params[':end_date'] = $filters['end_date'];
         }

         $contributions = $orm->selectWithJoin(
            baseTable: 'contribution c',
            joins: [
               ['table' => 'churchmember m', 'on' => 'c.MbrID = m.MbrID', 'type' => 'LEFT'],
               ['table' => 'contributiontype ct', 'on' => 'c.ContributionTypeID = ct.ContributionTypeID', 'type' => 'LEFT']
            ],
            fields: [
               'c.*',
               'm.MbrFirstName',
               'm.MbrFamilyName',
               'ct.ContributionTypeName'
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
            "SELECT COUNT(*) as total FROM contribution c" . $whereSql,
            $params
         )[0]['total'];

         return [
            'data' => $contributions,
            'pagination' => [
               'page' => $page,
               'limit' => $limit,
               'total' => $total,
               'pages' => ceil($total / $limit)
            ]
         ];
      } catch (Exception $e) {
         Helpers::logError('Contribution getAll error: ' . $e->getMessage());
         Helpers::sendError('Failed to retrieve contributions.');
      }
   }

   /**
    * Get the average contribution amount for a specific month
    * @param string $month Month in YYYY-MM format
    * @return array Average contribution amount
    * @throws Exception if validation fails or database operations fail
    */
   public static function getAverage($month)
   {
      $orm = new ORM();
      try {
         // Validate month format
         if (!preg_match('/^\d{4}-\d{2}$/', $month)) Helpers::sendError('Invalid month format (YYYY-MM)');

         $contributions = $orm->runQuery(
            'SELECT ContributionAmount FROM contribution WHERE DATE_FORMAT(ContributionDate, "%Y-%m") = :month',
            ['month' => $month]
         );

         $total = array_sum(array_column($contributions, 'ContributionAmount'));
         $count = count($contributions);
         $average = $count ? $total / $count : 0;

         return ['average_contribution' => number_format($average, 2)];
      } catch (Exception $e) {
         Helpers::logError('Contribution average error: ' . $e->getMessage());
         Helpers::sendError('Failed to retrieve average contribution.');
      }
   }
}
