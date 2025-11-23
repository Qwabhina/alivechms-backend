<?php

/**
 * Communication & Notification System
 *
 * Supports In-App, SMS, and Email notifications with group broadcasting.
 *
 * @package AliveChMS\Core
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

declare(strict_types=1);

class Communication
{
   /**
    * Send notification to individual or group
    *
    * @param array $data Message data
    * @return array Success response
    */
   public static function send(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'title'    => 'required|max:200',
         'message'  => 'required',
         'channel'  => 'required|in:InApp,SMS,Email',
      ]);

      $sentBy = Auth::getCurrentUserId($token ?? '');

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
         self::queueIndividual($commId, (int)$data['member_id'], $data['channel'], $orm);
      } elseif (!empty($data['group_id'])) {
         self::queueGroup($commId, (int)$data['group_id'], $data['channel'], $orm);
      }

      Helpers::logError("Notification queued: CommID $commId");

      return ['status' => 'success', 'communication_id' => $commId];
   }

   private static function queueIndividual(int $commId, int $memberId, string $channel, ORM $orm): void
   {
      $orm->insert('communication_delivery', [
         'CommID'   => $commId,
         'MbrID'    => $memberId,
         'Channel'  => $channel
      ]);
   }

   private static function queueGroup(int $commId, int $groupId, string $channel, ORM $orm): void
   {
      $members = $orm->runQuery(
         "SELECT MbrID FROM groupmember WHERE GroupID = :gid",
         [':gid' => $groupId]
      );

      foreach ($members as $m) {
         $orm->insert('communication_delivery', [
            'CommID'  => $commId,
            'MbrID'   => (int)$m['MbrID'],
            'Channel' => $channel
         ]);
      }
   }

   /**
    * Get notifications for current user
    *
    * @param int $page  Page number
    * @param int $limit Items per page
    * @return array Notifications
    */
   public static function getMyNotifications(int $page = 1, int $limit = 20): array
   {
      $orm = new ORM();
      $userId = Auth::getCurrentUserId($token ?? '');
      $offset = ($page - 1) * $limit;

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
         conditions: ['cd.MbrID' => ':uid'],
         params: [':uid' => $userId],
         orderBy: ['c.CreatedAt' => 'DESC'],
         limit: $limit,
         offset: $offset
      );

      $total = $orm->runQuery("SELECT COUNT(*) AS total FROM communication_delivery WHERE MbrID = :uid", [':uid' => $userId])[0]['total'];

      return [
         'data' => $notifications,
         'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => (int)ceil($total / $limit)
            ]
      ];
   }

   /**
    * Mark notification as read
    *
    * @param int $commId Communication ID
    * @return array Success response
    */
   public static function markAsRead(int $commId): array
   {
      $orm = new ORM();
      $userId = Auth::getCurrentUserId($token ?? '');

      $orm->update('communication_delivery', [
         'Status'      => 'Sent',
         'DeliveredAt' => date('Y-m-d H:i:s')
      ], ['CommID' => $commId, 'MbrID' => $userId]);

      return ['status' => 'success'];
   }
}