<?php
require_once __DIR__ . '/../index.php'; // Loads everything

$orm = new ORM();

// Get pending deliveries
$pending = $orm->runQuery(
   "SELECT cd.DeliveryID, cd.Channel, cd.MbrID, c.Message, m.MbrPhoneNumber, m.MbrEmailAddress
     FROM communication_delivery cd
     JOIN communication c ON cd.CommID = c.CommID
     JOIN churchmember m ON cd.MbrID = m.MbrID
     WHERE cd.Status = 'Pending'
     LIMIT 100"
);

foreach ($pending as $p) {
   $success = false;

   if ($p['Channel'] === 'SMS' && !empty($p['MbrPhoneNumber'])) {
      $success = SMSGateway::send($p['MbrPhoneNumber'], $p['Message']);
   }

   if ($p['Channel'] === 'Email' && !empty($p['MbrEmailAddress'])) {
      $success = EmailGateway::send(
         $p['MbrEmailAddress'],
         "Message from Alive Church",
         nl2br(htmlspecialchars($p['Message']))
      );
   }

   if ($p['Channel'] === 'InApp') {
      $success = true; // In-app is instant
   }

   $orm->update('communication_delivery', [
      'Status'      => $success ? 'Sent' : 'Failed',
      'DeliveredAt' => $success ? date('Y-m-d H:i:s') : null,
      'ErrorMessage' => $success ? null : 'Gateway failed'
   ], ['DeliveryID' => $p['DeliveryID']]);
}
