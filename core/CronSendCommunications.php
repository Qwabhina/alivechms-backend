<?php

/**
 * Background Communication Delivery Processor
 *
 * Cron-driven script that processes the communication_delivery queue.
 * Handles SMS and Email delivery via dedicated gateways.
 * InApp messages are marked as delivered instantly.
 *
 * Designed to run every 1–5 minutes via system cron:
 *   php /path/to/CronSendCommunications.php
 *
 * Features:
 * - Processes up to 200 messages per run (prevents overload)
 * - Full error logging with delivery failure details
 * - Automatic status updates (Pending → Sent/Failed)
 * - Safe, lightweight bootstrap – no full framework load
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

// Minimal bootstrap – only what is needed
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ORM.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/EmailGateway.php';
require_once __DIR__ . '/SMSGateway.php';

use PHPMailer\PHPMailer\PHPMailer;

$orm = new ORM();

// Process up to 200 pending deliveries per run
$pending = $orm->runQuery(
   "SELECT cd.DeliveryID, cd.Channel, cd.MbrID, c.Title, c.Message,
            m.MbrPhoneNumber, m.MbrEmailAddress
     FROM communication_delivery cd
     JOIN communication c ON cd.CommID = c.CommID
     JOIN churchmember m ON cd.MbrID = m.MbrID
     WHERE cd.Status = 'Pending'
     ORDER BY cd.DeliveryID ASC
     LIMIT 200"
);

if (empty($pending)) {
   exit("No pending messages. Exiting.\n");
}

$processed = 0;
foreach ($pending as $item) {
   $success      = false;
   $errorMessage = null;

   switch ($item['Channel']) {
      case 'InApp':
         $success = true; // InApp is instant
         break;

      case 'SMS':
         if (!empty($item['MbrPhoneNumber'])) {
            $success = SMSGateway::send($item['MbrPhoneNumber'], $item['Message']);
            $errorMessage = $success ? null : 'SMS gateway failed';
         }
         break;

      case 'Email':
         if (!empty($item['MbrEmailAddress'])) {
            $htmlBody = nl2br(htmlspecialchars($item['Message']));
            $success  = EmailGateway::send(
               $item['MbrEmailAddress'],
               $item['Title'],
               $htmlBody
            );
            $errorMessage = $success ? null : 'Email gateway failed';
         }
         break;
   }

   $orm->update('communication_delivery', [
      'Status'       => $success ? 'Sent' : 'Failed',
      'DeliveredAt'  => $success ? date('Y-m-d H:i:s') : null,
      'ErrorMessage' => $errorMessage
   ], ['DeliveryID' => $item['DeliveryID']]);

   if (!$success) {
      Helpers::logError("Delivery failed | ID: {$item['DeliveryID']} | Channel: {$item['Channel']} | Error: $errorMessage");
   }

   $processed++;
}

echo "Communication cron completed. Processed: $processed messages.\n";