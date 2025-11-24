<?php

/**
 * Contribution Management Class
 *
 * Handles all operations related to member contributions (tithes, offerings, pledges, etc.)
 * including creation, updates, soft deletion, restoration, and comprehensive reporting.
 *
 * @package AliveChMS\Core
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

declare(strict_types=1);

class Contribution
{
   /**
    * Create a new contribution record
    *
    * @param array $data Contribution data
    * @return array Success response with contribution_id
    * @throws Exception On validation or database failure
    */
   public static function create(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'amount'              => 'required|numeric',
         'date'                => 'required|date',
         'contribution_type_id' => 'required|numeric',
         'member_id'           => 'required|numeric',
         'payment_option_id'   => 'required|numeric',
         'fiscal_year_id'      => 'required|numeric',
         'description'         => 'max:500|nullable',
      ]);

      $amount         = (float)$data['amount'];
      $contributionDate = $data['date'];
      $memberId       = (int)$data['member_id'];
      $typeId         = (int)$data['contribution_type_id'];
      $paymentId      = (int)$data['payment_option_id'];
      $fiscalYearId   = (int)$data['fiscal_year_id'];

      if ($amount <= 0) {
         Helpers::sendFeedback('Contribution amount must be greater than zero', 400);
      }

      // Validate date is not in the future
      if ($contributionDate > date('Y-m-d')) {
         Helpers::sendFeedback('Contribution date cannot be in the future', 400);
      }

      // Validate foreign keys exist
      $validations = $orm->runQuery(
         "SELECT 
                (SELECT COUNT(*) FROM churchmember WHERE MbrID = :member_id AND Deleted = 0 AND MbrMembershipStatus = 'Active') AS member_ok,
                (SELECT COUNT(*) FROM contributiontype WHERE ContributionTypeID = :type_id) AS type_ok,
                (SELECT COUNT(*) FROM paymentoption WHERE PaymentOptionID = :payment_id) AS payment_ok,
                (SELECT COUNT(*) FROM fiscalyear WHERE FiscalYearID = :fiscal_id AND Status = 'Active') AS fiscal_ok",
            [
            ':member_id' => $memberId,
            ':type_id'   => $typeId,
            ':payment_id' => $paymentId,
            ':fiscal_id' => $fiscalYearId
            ]
      )[0];

      if ($validations['member_ok'] == 0) Helpers::sendFeedback('Invalid or inactive member', 400);
      if ($validations['type_ok'] == 0)   Helpers::sendFeedback('Invalid contribution type', 400);
      if ($validations['payment_ok'] == 0) Helpers::sendFeedback('Invalid payment option', 400);
      if ($validations['fiscal_ok'] == 0)  Helpers::sendFeedback('Invalid or inactive fiscal year', 400);

      $orm->beginTransaction();
      try {
         $contributionId = $orm->insert('contribution', [
            'ContributionAmount'   => $amount,
            'ContributionDate'     => $contributionDate,
            'ContributionTypeID'   => $typeId,
            'PaymentOptionID'      => $paymentId,
            'MbrID'                => $memberId,
            'FiscalYearID'         => $fiscalYearId,
            'Description'          => $data['description'] ?? null,
            'Deleted'              => 0,
            'RecordedBy'           => Auth::getCurrentUserId($token ?? ''),
            'RecordedAt'           => date('Y-m-d H:i:s')
         ])['id'];

         $orm->commit();

         Helpers::logError("New contribution recorded: ID $contributionId | Amount $amount | Member $memberId");

         return ['status' => 'success', 'contribution_id' => $contributionId];
      } catch (Exception $e) {
         $orm->rollBack();
         Helpers::logError("Contribution creation failed: " . $e->getMessage());
         throw $e;
      }
   }

   /**
    * Update an existing contribution
    *
    * @param int   $contributionId Contribution ID
    * @param array $data           Updated data
    * @return array Success response
    */
   public static function update(int $contributionId, array $data): array
   {
      $orm = new ORM();

      $existing = $orm->getWhere('contribution', ['ContributionID' => $contributionId, 'Deleted' => 0]);
      if (empty($existing)) {
         Helpers::sendFeedback('Contribution not found or deleted', 404);
      }

      Helpers::validateInput($data, [
         'amount'              => 'numeric|nullable',
         'date'                => 'date|nullable',
         'contribution_type_id' => 'numeric|nullable',
         'payment_option_id'   => 'numeric|nullable',
         'description'         => 'max:500|nullable',
      ]);

      $update = [];
      if (isset($data['amount']) && (float)$data['amount'] > 0) {
         $update['ContributionAmount'] = (float)$data['amount'];
      }
      if (!empty($data['date'])) {
         if ($data['date'] > date('Y-m-d')) {
            Helpers::sendFeedback('Date cannot be in the future', 400);
         }
         $update['ContributionDate'] = $data['date'];
      }
      if (!empty($data['contribution_type_id'])) {
         $update['ContributionTypeID'] = (int)$data['contribution_type_id'];
      }
      if (!empty($data['payment_option_id'])) {
         $update['PaymentOptionID'] = (int)$data['payment_option_id'];
      }
      if (isset($data['description'])) {
         $update['Description'] = $data['description'];
      }

      if (!empty($update)) {
         $orm->update('contribution', $update, ['ContributionID' => $contributionId]);
      }

      return ['status' => 'success', 'contribution_id' => $contributionId];
   }

   /**
    * Soft delete a contribution
    *
    * @param int $contributionId Contribution ID
    * @return array Success response
    */
   public static function delete(int $contributionId): array
   {
      $orm = new ORM();

      $affected = $orm->update('contribution', ['Deleted' => 1], ['ContributionID' => $contributionId, 'Deleted' => 0]);
      if ($affected === 0) {
         Helpers::sendFeedback('Contribution not found or already deleted', 404);
      }

      return ['status' => 'success'];
   }

   /**
    * Restore a soft-deleted contribution
    *
    * @param int $contributionId Contribution ID
    * @return array Success response
    */
   public static function restore(int $contributionId): array
   {
      $orm = new ORM();

      $affected = $orm->update('contribution', ['Deleted' => 0], ['ContributionID' => $contributionId, 'Deleted' => 1]);
      if ($affected === 0) {
         Helpers::sendFeedback('Contribution not found or not deleted', 404);
      }

      return ['status' => 'success'];
   }

   /**
    * Retrieve a single contribution with related data
    *
    * @param int $contributionId Contribution ID
    * @return array Contribution details
    */
   public static function get(int $contributionId): array
   {
      $orm = new ORM();

      $contributions = $orm->selectWithJoin(
            baseTable: 'contribution c',
            joins: [
            ['table' => 'churchmember m', 'on' => 'c.MbrID = m.MbrID'],
            ['table' => 'contributiontype ct', 'on' => 'c.ContributionTypeID = ct.ContributionTypeID'],
            ['table' => 'paymentoption p', 'on' => 'c.PaymentOptionID = p.PaymentOptionID']
            ],
            fields: [
            'c.*',
            'm.MbrFirstName',
            'm.MbrFamilyName',
            'ct.ContributionTypeName',
            'p.PaymentOptionName'
            ],
         conditions: ['c.ContributionID' => ':id', 'c.Deleted' => 0],
         params: [':id' => $contributionId]
      );

      if (empty($contributions)) {
         Helpers::sendFeedback('Contribution not found', 404);
      }

      return $contributions[0];
   }

   /**
    * Retrieve paginated contributions with filters
    *
    * @param int   $page    Page number
    * @param int   $limit   Items per page
    * @param array $filters Optional filters
    * @return array Paginated result
    */
   public static function getAll(int $page = 1, int $limit = 10, array $filters = []): array
   {
      $orm = new ORM();
      $offset = ($page - 1) * $limit;

      $conditions = ['c.Deleted' => 0];
      $params = [];

      if (!empty($filters['contribution_type_id'])) {
         $conditions['c.ContributionTypeID'] = ':type_id';
         $params[':type_id'] = (int)$filters['contribution_type_id'];
      }
      if (!empty($filters['member_id'])) {
         $conditions['c.MbrID'] = ':member_id';
         $params[':member_id'] = (int)$filters['member_id'];
      }
      if (!empty($filters['fiscal_year_id'])) {
         $conditions['c.FiscalYearID'] = ':fy_id';
         $params[':fy_id'] = (int)$filters['fiscal_year_id'];
      }
      if (!empty($filters['start_date'])) {
         $conditions['c.ContributionDate >='] = ':start';
         $params[':start'] = $filters['start_date'];
      }
      if (!empty($filters['end_date'])) {
         $conditions['c.ContributionDate <='] = ':end';
         $params[':end'] = $filters['end_date'];
      }

      $contributions = $orm->selectWithJoin(
         baseTable: 'contribution c',
         joins: [
            ['table' => 'churchmember m', 'on' => 'c.MbrID = m.MbrID'],
            ['table' => 'contributiontype ct', 'on' => 'c.ContributionTypeID = ct.ContributionTypeID'],
            ['table' => 'paymentoption p', 'on' => 'c.PaymentOptionID = p.PaymentOptionID']
         ],
         fields: [
            'c.ContributionID',
            'c.ContributionAmount',
            'c.ContributionDate',
            'c.Description',
            'm.MbrFirstName',
            'm.MbrFamilyName',
            'ct.ContributionTypeName',
            'p.PaymentOptionName'
         ],
         conditions: $conditions,
         params: $params,
         orderBy: ['c.ContributionDate' => 'DESC'],
         limit: $limit,
         offset: $offset
      );

      $total = $orm->runQuery(
         "SELECT COUNT(*) AS total FROM contribution c WHERE c.Deleted = 0" .
            (!empty($conditions) ? ' AND ' . implode(' AND ', array_keys(array_diff_key($conditions, ['c.Deleted' => 0]))) : ''),
         array_diff_key($params, [':deleted' => 0])
      )[0]['total'];

      return [
            'data' => $contributions,
            'pagination' => [
            'page'  => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => (int)ceil($total / $limit)
            ]
      ];
   }

   /**
    * Get total contributions with filters
    *
    * @param array $filters Filters
    * @return array Total amount
    */
   public static function getTotal(array $filters = []): array
   {
      $orm = new ORM();
      $conditions = ['c.Deleted' => 0];
      $params = [];

      // Apply same filters as getAll
      if (!empty($filters['contribution_type_id'])) {
         $conditions['c.ContributionTypeID'] = ':type_id';
         $params[':type_id'] = (int)$filters['contribution_type_id'];
      }
      if (!empty($filters['fiscal_year_id'])) {
         $conditions['c.FiscalYearID'] = ':fy_id';
         $params[':fy_id'] = (int)$filters['fiscal_year_id'];
      }
      if (!empty($filters['start_date'])) {
         $conditions['c.ContributionDate >='] = ':start';
         $params[':start'] = $filters['start_date'];
      }
      if (!empty($filters['end_date'])) {
         $conditions['c.ContributionDate <='] = ':end';
         $params[':end'] = $filters['end_date'];
      }

      $result = $orm->runQuery(
         "SELECT COALESCE(SUM(c.ContributionAmount), 0) AS total FROM contribution c" .
            (!empty($conditions) ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
            $params
      )[0];

      return ['total_contribution' => number_format((float)$result['total'], 2)];
   }
}