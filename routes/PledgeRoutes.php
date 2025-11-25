<?php

/**
 * Pledge API Routes – v1
 *
 * Complete pledge management:
 * - Create pledge
 * - View single pledge with payment history
 * - List pledges with filters
 * - Record payment
 * - Track fulfillment progress (percentage, balance, status)
 *
 * All operations fully permission-controlled.
 *
 * @package  AliveChMS\Routes
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Pledge.php';

// ---------------------------------------------------------------------
// AUTHENTICATION & AUTHORIZATION
// ---------------------------------------------------------------------
$token = Auth::getBearerToken();
if (!$token || Auth::verify($token) === false) {
   Helpers::sendFeedback('Unauthorized: Valid token required', 401);
}

// ---------------------------------------------------------------------
// ROUTE DISPATCHER
// ---------------------------------------------------------------------
match (true) {

   // CREATE PLEDGE
   $method === 'POST' && $path === 'pledge/create' => (function () use ($token) {
      Auth::checkPermission($token, 'manage_pledges');

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = Pledge::create($payload);
      echo json_encode($result);
   })(),

   // VIEW SINGLE PLEDGE (with payments & progress)
   $method === 'GET' && $pathParts[0] === 'pledge' && ($pathParts[1] ?? '') === 'view' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'view_pledges');

      $pledgeId = $pathParts[2];
      if (!is_numeric($pledgeId)) {
         Helpers::sendFeedback('Valid Pledge ID required', 400);
      }

      $pledge = Pledge::get((int)$pledgeId);
      echo json_encode($pledge);
   })(),

   // LIST ALL PLEDGES (Paginated + Filtered)
   $method === 'GET' && $path === 'pledge/all' => (function () use ($token) {
      Auth::checkPermission($token, 'view_pledges');

      $page  = max(1, (int)($_GET['page'] ?? 1));
      $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

      $filters = [];
      foreach (['member_id', 'status', 'fiscal_year_id'] as $key) {
         if (isset($_GET[$key]) && $_GET[$key] !== '') {
            $filters[$key] = $_GET[$key];
         }
      }

      $result = Pledge::getAll($page, $limit, $filters);
      echo json_encode($result);
   })(),

   // RECORD PAYMENT AGAINST PLEDGE
   $method === 'POST' && $pathParts[0] === 'pledge' && ($pathParts[1] ?? '') === 'payment' && ($pathParts[2] ?? '') === 'add' && isset($pathParts[3]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'record_pledge_payments');

      $pledgeId = $pathParts[3];
      if (!is_numeric($pledgeId)) {
         Helpers::sendFeedback('Valid Pledge ID required', 400);
      }

      $payload = json_decode(file_get_contents('php://input'), true);
      if (!is_array($payload)) {
         Helpers::sendFeedback('Invalid JSON payload', 400);
      }

      $result = Pledge::recordPayment((int)$pledgeId, $payload);
      echo json_encode($result);
   })(),

   // GET PLEDGE FULFILLMENT PROGRESS (Percentage, Balance, Status)
   $method === 'GET' && $pathParts[0] === 'pledge' && ($pathParts[1] ?? '') === 'progress' && isset($pathParts[2]) => (function () use ($token, $pathParts) {
      Auth::checkPermission($token, 'view_pledges');

      $pledgeId = $pathParts[2];
      if (!is_numeric($pledgeId)) {
         Helpers::sendFeedback('Valid Pledge ID required', 400);
      }

      $pledge = Pledge::get((int)$pledgeId);

      $pledgeAmount = (float)$pledge['PledgeAmount'];
      $totalPaid    = (float)$pledge['total_paid'];
      $balance      = (float)$pledge['balance'];

      $progress = $pledgeAmount > 0 ? round(($totalPaid / $pledgeAmount) * 100, 2) : 0;

      $status = match (true) {
         $progress >= 100 => 'Fulfilled',
         $progress > 0    => 'In Progress',
         default          => 'Not Started'
      };

      $result = [
         'pledge_id'        => (int)$pledge['PledgeID'],
         'pledge_amount'    => number_format($pledgeAmount, 2),
         'total_paid'       => number_format($totalPaid, 2),
         'balance'          => number_format($balance, 2),
         'progress_percent' => $progress,
         'status'           => $status,
         'payments_count'   => count($pledge['payments']),
         'last_payment_date' => !empty($pledge['payments']) ? $pledge['payments'][0]['PaymentDate'] : null
      ];

      echo json_encode($result);
   })(),

   // FALLBACK
   default => Helpers::sendFeedback('Pledge endpoint not found', 404),
};