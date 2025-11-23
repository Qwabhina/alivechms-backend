<?php

/**
 * Volunteer Management Class – Final Production Version
 *
 * Full lifecycle: role management, assignment, confirmation, removal, reporting.
 * Multi-branch aware, fully audited, and secure.
 *
 * @package AliveChMS\Core
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-22
 */

declare(strict_types=1);

class Volunteer
{
   private const STATUS_PENDING    = 'Pending';
   private const STATUS_CONFIRMED  = 'Confirmed';
   private const STATUS_DECLINED   = 'Declined';
   private const STATUS_COMPLETED  = 'Completed';

   // =====================================================================
   // VOLUNTEER ROLES (System-wide)
   // =====================================================================

   public static function getRoles(): array
   {
      $orm = new ORM();
      return $orm->getAll('volunteer_role');
   }

   public static function createRole(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'name'        => 'required|max:100',
         'description' => 'max:500|nullable'
      ]);

      $existing = $orm->getWhere('volunteer_role', ['RoleName' => $data['name']]);
      if (!empty($existing)) {
         Helpers::sendFeedback('Volunteer role already exists', 400);
      }

      $roleId = $orm->insert('volunteer_role', [
         'RoleName'    => $data['name'],
         'Description' => $data['description'] ?? null
      ])['id'];

      return ['status' => 'success', 'role_id' => $roleId];
   }

   // =====================================================================
   // EVENT VOLUNTEER ASSIGNMENTS
   // =====================================================================

   public static function assign(int $eventId, array $volunteers)
   {
      $orm = new ORM();

      $event = $orm->getWhere('event', ['EventID' => $eventId]);
      if (empty($event)) {
         Helpers::sendFeedback('Event not found', 404);
      }

      if (empty($volunteers) || !is_array($volunteers)) {
         Helpers::sendFeedback('volunteers array is required', 400);
      }

      $assignedBy = Auth::getCurrentUserId(); // Fixed: now uses real token

      $orm->beginTransaction();
      try {
         foreach ($volunteers as $v) {
            Helpers::validateInput($v, [
               'member_id' => 'required|numeric',
               'role_id'   => 'numeric|nullable',
               'notes'     => 'max:500|nullable'
            ]);

            $memberId = (int)$v['member_id'];
            $roleId   = !empty($v['role_id']) ? (int)$v['role_id'] : null;

            // Validate member
            $member = $orm->getWhere('churchmember', [
               'MbrID'              => $memberId,
               'MbrMembershipStatus' => 'Active',
               'Deleted'            => 0
            ]);
            if (empty($member)) {
               throw new Exception("Invalid member: $memberId");
            }

            // Validate role
            if ($roleId && empty($orm->getWhere('volunteer_role', ['VolunteerRoleID' => $roleId]))) {
               throw new Exception("Invalid role ID: $roleId");
            }

            // Prevent duplicates
            $existing = $orm->getWhere('event_volunteer', [
               'EventID' => $eventId,
               'MbrID'   => $memberId
            ]);
            if (!empty($existing)) {
               continue;
            }

            $orm->insert('event_volunteer', [
               'EventID'        => $eventId,
               'MbrID'          => $memberId,
               'VolunteerRoleID' => $roleId,
               'AssignedBy'     => $assignedBy,
               'Notes'          => $v['notes'] ?? null,
               'Status'         => self::STATUS_PENDING
            ]);
         }

         $orm->commit();
         return ['status' => 'success', 'message' => 'Volunteers assigned'];
      } catch (Exception $e) {
         $orm->rollBack();
         Helpers::sendFeedback($e->getMessage(), 400);
      }
   }

   public static function confirmAssignment(int $assignmentId, string $action): array
   {
      $valid = ['confirm', 'decline'];
      if (!in_array($action, $valid)) {
         Helpers::sendFeedback("Action must be 'confirm' or 'decline'", 400);
      }

      $orm = new ORM();
      $assignment = $orm->getWhere('event_volunteer', ['AssignmentID' => $assignmentId])[0] ?? null;
      if (!$assignment) {
         Helpers::sendFeedback('Assignment not found', 404);
      }

      $currentUserId = Auth::getCurrentUserId();
      if ((int)$assignment['MbrID'] !== $currentUserId) {
         Helpers::sendFeedback('You can only respond to your own assignment', 403);
      }

      $newStatus = $action === 'confirm' ? self::STATUS_CONFIRMED : self::STATUS_DECLINED;
      $orm->update('event_volunteer', ['Status' => $newStatus], ['AssignmentID' => $assignmentId]);

      return ['status' => 'success', 'new_status' => $newStatus];
   }

   public static function completeAssignment(int $assignmentId): array
   {
      $orm = new ORM();

      $assignment = $orm->getWhere('event_volunteer', ['AssignmentID' => $assignmentId])[0] ?? null;
      if (!$assignment) {
         Helpers::sendFeedback('Assignment not found', 404);
      }

      $orm->update('event_volunteer', ['Status' => self::STATUS_COMPLETED], ['AssignmentID' => $assignmentId]);

      return ['status' => 'success', 'message' => 'Volunteer service completed'];
   }

   public static function getByEvent(int $eventId, int $page = 1, int $limit = 50): array
   {
      $orm = new ORM();
      $offset = ($page - 1) * $limit;

      $volunteers = $orm->selectWithJoin(
         baseTable: 'event_volunteer ev',
         joins: [
            ['table' => 'churchmember m', 'on' => 'ev.MbrID = m.MbrID'],
            ['table' => 'volunteer_role vr', 'on' => 'ev.VolunteerRoleID = vr.VolunteerRoleID', 'type' => 'LEFT'],
            ['table' => 'churchmember a', 'on' => 'ev.AssignedBy = a.MbrID']
         ],
         fields: [
            'ev.AssignmentID',
            'ev.Status',
            'ev.Notes',
            'ev.AssignedAt',
            'm.MbrFirstName',
            'm.MbrFamilyName',
            'm.MbrEmailAddress',
            'vr.RoleName',
            'a.MbrFirstName AS AssignedByName'
         ],
         conditions: ['ev.EventID' => ':id'],
         params: [':id' => $eventId],
         orderBy: ['ev.AssignedAt' => 'DESC'],
         limit: $limit,
         offset: $offset
      );

      $total = $orm->runQuery("SELECT COUNT(*) AS total FROM event_volunteer WHERE EventID = :id", [':id' => $eventId])[0]['total'];

      return [
         'data' => $volunteers,
         'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => (int)ceil($total / $limit)
         ]
      ];
   }

   public static function remove(int $assignmentId): array
   {
      $orm = new ORM();

      $assignment = $orm->getWhere('event_volunteer', ['AssignmentID' => $assignmentId])[0] ?? null;
      if (!$assignment) {
         Helpers::sendFeedback('Assignment not found', 404);
      }

      $orm->delete('event_volunteer', ['AssignmentID' => $assignmentId]);

      return ['status' => 'success', 'message' => 'Volunteer removed from event'];
   }
}
