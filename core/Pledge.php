<?php

/**
 * Pledge Management
 *
 * Full pledge lifecycle: creation, payment recording,
 * status tracking, fulfillment detection, and reporting.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class Pledge
{
   private const STATUS_ACTIVE     = 'Active';
   private const STATUS_FULFILLED  = 'Fulfilled';
   private const STATUS_CANCELLED  = 'Cancelled';

   /**
    * Create a new pledge
    *
    * @param array $data Pledge payload
    * @return array ['status' => 'success', 'pledge_id' => int]
    * @throws Exception On validation or database failure
    */
   public static function create(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'member_id'       => 'required|numeric',
         'pledge_type_id'  => 'required|numeric',
         'fiscal_year_id'  => 'required|numeric',
         'amount'          => 'required|numeric',
         'pledge_date'     => 'required|date',
         'due_date'        => 'date|nullable',
         'description'     => 'max:500|nullable'
      ]);

      $memberId     = (int)$data['member_id'];
      $typeId       = (int)$data['pledge_type_id'];
      $fiscalYearId = (int)$data['fiscal_year_id'];
      $amount       = (float)$data['amount'];

      if ($amount <= 0) {
         Helpers::sendFeedback('Pledge amount must be greater than zero', 400);
      }

      // Validate foreign keys
      $valid = $orm->runQuery(
         "SELECT
                (SELECT COUNT(*) FROM churchmember WHERE MbrID = :mid AND Deleted = 0 AND MbrMembershipStatus = 'Active') AS member_ok,
                (SELECT COUNT(*) FROM pledge_type WHERE PledgeTypeID = :tid) AS type_ok,
                (SELECT COUNT(*) FROM fiscalyear WHERE FiscalYearID = :fyid AND Status = 'Active') AS fy_ok",
         [':mid' => $memberId, ':tid' => $typeId, ':fyid' => $fiscalYearId]
      )[0];

      if ($valid['member_ok'] == 0) Helpers::sendFeedback('Invalid member', 400);
      if ($valid['type_ok'] == 0)   Helpers::sendFeedback('Invalid pledge type', 400);
      if ($valid['fy_ok'] == 0)     Helpers::sendFeedback('Invalid fiscal year', 400);

      $pledgeId = $orm->insert('pledge', [
         'MbrID'          => $memberId,
         'PledgeTypeID'   => $typeId,
         'FiscalYearID'   => $fiscalYearId,
         'PledgeAmount'   => $amount,
         'PledgeDate'     => $data['pledge_date'],
         'DueDate'        => $data['due_date'] ?? null,
         'PledgeStatus'   => self::STATUS_ACTIVE,
         'Description'    => $data['description'] ?? null,
         'CreatedBy'      => Auth::getCurrentUserId(),
         'CreatedAt'      => date('Y-m-d H:i:s')
      ])['id'];

      Helpers::logError("New pledge created: PledgeID $pledgeId | Amount $amount | Member $memberId");
      return ['status' => 'success', 'pledge_id' => $pledgeId];
   }

   /**
    * Record payment against a pledge
    *
    * @param int   $pledgeId Pledge ID
    * @param array $data     Payment data
    * @return array ['status' => 'success', 'message' => string]
    * @throws Exception On failure
    */
   public static function recordPayment(int $pledgeId, array $data): array
   {
      $orm = new ORM();

      $pledge = $orm->getWhere('pledge', ['PledgeID' => $pledgeId, 'PledgeStatus' => self::STATUS_ACTIVE])[0] ?? null;
      if (!$pledge) {
         Helpers::sendFeedback('Active pledge not found', 404);
      }

      Helpers::validateInput($data, [
         'amount'         => 'required|numeric',
         'payment_date'   => 'required|date',
         'contribution_id' => 'numeric|nullable'
      ]);

      $paymentAmount = (float)$data['amount'];
      if ($paymentAmount <= 0) {
         Helpers::sendFeedback('Payment amount must be greater than zero', 400);
      }

      $orm->beginTransaction();
      try {
         $orm->insert('pledge_payment', [
            'PledgeID'       => $pledgeId,
            'ContributionID' => !empty($data['contribution_id']) ? (int)$data['contribution_id'] : null,
            'PaymentAmount'  => $paymentAmount,
            'PaymentDate'    => $data['payment_date'],
            'RecordedBy'     => Auth::getCurrentUserId(),
            'RecordedAt'     => date('Y-m-d H:i:s')
         ]);

         // Check fulfillment
         $paid = $orm->runQuery(
            "SELECT COALESCE(SUM(PaymentAmount), 0) AS paid FROM pledge_payment WHERE PledgeID = :id",
            [':id' => $pledgeId]
         )[0]['paid'];

         if ($paid >= (float)$pledge['PledgeAmount']) {
            $orm->update('pledge', ['PledgeStatus' => self::STATUS_FULFILLED], ['PledgeID' => $pledgeId]);
         }

         $orm->commit();
         return ['status' => 'success', 'message' => 'Payment recorded'];
      } catch (Exception $e) {
         $orm->rollBack();
         throw $e;
      }
   }

   /**
    * Retrieve a single pledge with payment progress
    *
    * @param int $pledgeId Pledge ID
    * @return array Pledge details with payments
    */
   public static function get(int $pledgeId): array
   {
      $orm = new ORM();

      $result = $orm->selectWithJoin(
         baseTable: 'pledge p',
         joins: [
            ['table' => 'churchmember m',  'on' => 'p.MbrID = m.MbrID'],
            ['table' => 'pledge_type pt',  'on' => 'p.PledgeTypeID = pt.PledgeTypeID'],
            ['table' => 'fiscalyear fy',   'on' => 'p.FiscalYearID = fy.FiscalYearID']
         ],
         fields: [
            'p.*',
            'm.MbrFirstName',
            'm.MbrFamilyName',
            'pt.PledgeTypeName',
            'fy.YearName AS FiscalYear'
         ],
         conditions: ['p.PledgeID' => ':id'],
         params: [':id' => $pledgeId]
      );

      if (empty($result)) {
         Helpers::sendFeedback('Pledge not found', 404);
      }

      $payments = $orm->getWhere('pledge_payment', ['PledgeID' => $pledgeId]);
      $totalPaid = array_sum(array_column($payments, 'PaymentAmount'));

      $pledge = $result[0];
      $pledge['total_paid'] = $totalPaid;
      $pledge['balance']    = (float)$pledge['PledgeAmount'] - $totalPaid;
      $pledge['payments']   = $payments;

      return $pledge;
   }

   /**
    * Retrieve paginated pledges with filters
    *
    * @param int   $page    Page number
    * @param int   $limit   Items per page
    * @param array $filters Optional filters
    * @return array Paginated result
    */
   public static function getAll(int $page = 1, int $limit = 10, array $filters = []): array
   {
      $orm    = new ORM();
      $offset = ($page - 1) * $limit;

      $conditions = [];
      $params     = [];

      if (!empty($filters['member_id'])) {
         $conditions['p.MbrID'] = ':mid';
         $params[':mid'] = (int)$filters['member_id'];
      }
      if (!empty($filters['status'])) {
         $conditions['p.PledgeStatus'] = ':status';
         $params[':status'] = $filters['status'];
      }
      if (!empty($filters['fiscal_year_id'])) {
         $conditions['p.FiscalYearID'] = ':fy';
         $params[':fy'] = (int)$filters['fiscal_year_id'];
      }

      $pledges = $orm->selectWithJoin(
         baseTable: 'pledge p',
         joins: [
            ['table' => 'churchmember m', 'on' => 'p.MbrID = m.MbrID'],
            ['table' => 'pledge_type pt', 'on' => 'p.PledgeTypeID = pt.PledgeTypeID'],
            ['table' => 'fiscalyear fy',  'on' => 'p.FiscalYearID = fy.FiscalYearID']
         ],
         fields: [
            'p.PledgeID',
            'p.PledgeAmount',
            'p.PledgeDate',
            'p.DueDate',
            'p.PledgeStatus',
            'm.MbrFirstName',
            'm.MbrFamilyName',
            'pt.PledgeTypeName',
            'fy.YearName'
         ],
         conditions: $conditions,
         params: $params,
         orderBy: ['p.PledgeDate' => 'DESC'],
         limit: $limit,
         offset: $offset
      );

      $total = $orm->runQuery(
         "SELECT COUNT(*) AS total FROM pledge p" .
            (!empty($conditions) ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
         $params
      )[0]['total'];

      return [
         'data' => $pledges,
         'pagination' => [
            'page'   => $page,
            'limit'  => $limit,
            'total'  => (int)$total,
            'pages'  => (int)ceil($total / $limit)
         ]
      ];
   }
}