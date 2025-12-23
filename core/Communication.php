<?php

/**
 * Communication & Notification System
 *
 * Unified messaging engine supporting:
 * - In-App notifications (instant)
 * - SMS delivery (via SMSGateway)
 * - Email delivery (via EmailGateway)
 * - Group broadcasting
 * - Delivery queue with status tracking
 *
 * Designed for high-volume, reliable, auditable church communication.
 * All messages are stored centrally and delivered asynchronously where needed.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class Communication
{
   /**
    * Send a notification/message to one member or an entire group
    *
    * Supports three delivery channels:
    * - InApp: Instant, stored in communication_delivery (for UI display)
    * - SMS: Queued for background delivery via SMSGateway
    * - Email: Queued for background delivery via EmailGateway
    *
    * Group messages are automatically expanded to individual deliveries.
    *
    * @param array{
    *     title:string,
    *     message:string,
    *     channel:'InApp'|'SMS'|'Email',
    *     member_id?:int,
    *     group_id?:int
    * } $data Message payload
    * @return array{status:string, communication_id:int} Success response with stored message ID
    */
   public static function send(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'title'   => 'required|max:200',
         'message' => 'required',
         'channel' => 'required|in:InApp,SMS,Email'
      ]);

      if (empty($data['member_id']) && empty($data['group_id'])) {
         Helpers::sendFeedback('Either member_id or group_id is required', 400);
      }

      if (!empty($data['member_id']) && !empty($data['group_id'])) {
         Helpers::sendFeedback('Cannot specify both member_id and group_id', 400);
      }

      $sentBy = Auth::getCurrentUserId();

      $commId = $orm->insert('communication', [
         'Title'          => $data['title'],
         'Message'        => $data['message'],
         'SentBy'         => $sentBy,
         'TargetMemberID' => !empty($data['member_id']) ? (int)$data['member_id'] : null,
         'TargetGroupID'  => !empty($data['group_id']) ? (int)$data['group_id'] : null,
         'Channel'        => $data['channel'],
         'Status'         => 'Pending',
         'CreatedAt'      => date('Y-m-d H:i:s')
      ])['id'];

      // Queue delivery
      if (!empty($data['member_id'])) {
         self::queueIndividualDelivery($commId, (int)$data['member_id'], $data['channel'], $orm);
      } else {
         self::queueGroupDelivery($commId, (int)$data['group_id'], $data['channel'], $orm);
      }

      Helpers::logError("Communication queued: ID $commId | Channel: {$data['channel']} | Recipients: " .
         (!empty($data['member_id']) ? "Member {$data['member_id']}" : "Group {$data['group_id']}"));

      return ['status' => 'success', 'communication_id' => $commId];
   }

   /**
    * Queue delivery for a single member
    *
    * @param int    $commId   Communication record ID
    * @param int    $memberId Target member ID
    * @param string $channel  Delivery channel
    * @param ORM    $orm      ORM instance (for transaction safety)
    * @return void
    */
   private static function queueIndividualDelivery(int $commId, int $memberId, string $channel, ORM $orm): void
   {
      $orm->insert('communication_delivery', [
         'CommID'  => $commId,
         'MbrID'   => $memberId,
         'Channel' => $channel,
         'Status'  => 'Pending'
      ]);
   }

   /**
    * Queue delivery for all members of a group
    *
    * Expands group membership and creates one delivery record per member.
    *
    * @param int    $commId  Communication record ID
    * @param int    $groupId Target group ID
    * @param string $channel Delivery channel
    * @param ORM    $orm     ORM instance (for transaction safety)
    * @return void
    */
   private static function queueGroupDelivery(int $commId, int $groupId, string $channel, ORM $orm): void
   {
      $members = $orm->runQuery(
         "SELECT MbrID FROM groupmember WHERE GroupID = :gid",
         [':gid' => $groupId]
      );

      foreach ($members as $member) {
         $orm->insert('communication_delivery', [
            'CommID'  => $commId,
            'MbrID'   => (int)$member['MbrID'],
            'Channel' => $channel,
            'Status'  => 'Pending'
         ]);
      }
   }

   /**
    * Retrieve current user's unread + read notifications
    *
    * Supports pagination. InApp messages are instantly marked as delivered.
    *
    * @param int $page  Page number (1-based)
    * @param int $limit Items per page (default 20)
    * @return array{data:array, pagination:array} Paginated notifications
    */
   public static function getMyNotifications(int $page = 1, int $limit = 20): array
   {
      $orm       = new ORM();
      $userId    = Auth::getCurrentUserId();
      $offset    = ($page - 1) * $limit;

      $notifications = $orm->selectWithJoin(
         baseTable: 'communication_delivery cd',
         joins: [['table' => 'communication c', 'on' => 'cd.CommID = c.CommID']],
         fields: [
            'c.CommID',
            'c.Title',
            'c.Message',
            'c.Channel',
            'c.CreatedAt',
            'cd.Status',
            'cd.DeliveredAt'
         ],
         conditions: ['cd.MbrID' => ':user_id'],
         params: [':user_id' => $userId],
         orderBy: ['c.CreatedAt' => 'DESC'],
         limit: $limit,
         offset: $offset
      );

      $total = $orm->runQuery(
         "SELECT COUNT(*) AS total FROM communication_delivery WHERE MbrID = :uid",
         [':uid' => $userId]
      )[0]['total'];

      return [
         'data' => $notifications,
         'pagination' => [
            'page'   => $page,
            'limit'  => $limit,
            'total'  => (int)$total,
            'pages'  => (int)ceil($total / $limit)
            ]
      ];
   }

   /**
    * Mark a notification as read/delivered
    *
    * For InApp: marks as Sent
    * For SMS/Email: can be used after actual delivery confirmation
    *
    * @param int $commId Communication record ID
    * @return array{status:string} Success response
    */
   public static function markAsRead(int $commId): array
   {
      $orm    = new ORM();
      $userId = Auth::getCurrentUserId();

      $affected = $orm->update('communication_delivery', [
         'Status'      => 'Sent',
         'DeliveredAt' => date('Y-m-d H:i:s')
      ], ['CommID' => $commId, 'MbrID' => $userId]);

      if ($affected === 0) {
         Helpers::sendFeedback('Notification not found or already read', 404);
      }

      return ['status' => 'success'];
   }
}