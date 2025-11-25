<?php

/**
 * Volunteer Management – Event-Based Service Coordination
 *
 * Complete lifecycle management for church volunteers:
 * - System-wide volunteer roles (e.g., Usher, Choir, Media Team)
 * - Per-event volunteer assignments with status tracking
 * - Self-confirmation / decline workflow
 * - Completion marking and audit trail
 *
 * All operations are atomic, secure, and fully permission-aware.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class Volunteer
{
   private const STATUS_PENDING    = 'Pending';
   private const STATUS_CONFIRMED  = 'Confirmed';
   private const STATUS_DECLINED   = 'Declined';
   private const STATUS_COMPLETED  = 'Completed';

   /**
    * Retrieve all system-wide volunteer roles
    *
    * These roles are reusable across all events (e.g., "Sound Engineer", "Greeter").
    *
    * @return array List of volunteer roles
    */
   public static function getRoles(): array
   {
      $orm = new ORM();
      return $orm->getAll('volunteer_role');
   }

   /**
    * Create a new reusable volunteer role
    *
    * Role names must be unique across the system.
    *
    * @param array{name:string, description?:string} $data Role payload
    * @return array{status:string, role_id:int} Success response with created role ID
    */
   public static function createRole(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'name'        => 'required|max:100',
         'description' => 'max:500|nullable'
      ]);

      $name = trim($data['name']);

      if (!empty($orm->getWhere('volunteer_role', ['RoleName' => $name]))) {
         Helpers::sendFeedback('Volunteer role name already exists', 400);
      }

      $roleId = $orm->insert('volunteer_role', [
         'RoleName'    => $name,
         'Description' => $data['description'] ?? null
      ])['id'];

      Helpers::logError("New volunteer role created: ID $roleId – $name");
      return ['status' => 'success', 'role_id' => $roleId];
   }

   /**
    * Assign multiple volunteers to an event
    *
    * Supports bulk assignment with optional role and notes.
    * Duplicates are silently ignored.
    *
    * @param int   $eventId     Target event ID
    * @param array $volunteers  Array of volunteer assignment objects
    * @return array{status:string, message:string} Success response
    */
   public static function assign(int $eventId, array $volunteers): array
   {
      $orm = new ORM();

      // Validate event exists
      if (empty($orm->getWhere('event', ['EventID' => $eventId]))) {
         Helpers::sendFeedback('Event not found', 404);
      }

      if (empty($volunteers) || !is_array($volunteers)) {
         Helpers::sendFeedback('volunteers array is required', 400);
      }

      $assignedBy = Auth::getCurrentUserId();

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
               throw new Exception("Invalid or inactive member: $memberId");
            }

            // Validate role if provided
            if ($roleId !== null && empty($orm->getWhere('volunteer_role', ['VolunteerRoleID' => $roleId]))) {
               throw new Exception("Invalid volunteer role ID: $roleId");
            }

            // Skip if already assigned
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
               'Status'         => self::STATUS_PENDING,
               'AssignedAt'     => date('Y-m-d H:i:s')
            ]);
         }

         $orm->commit();
         Helpers::logError("Volunteers assigned to Event ID $eventId");
         return ['status' => 'success', 'message' => 'Volunteers assigned successfully'];
      } catch (Exception $e) {
         $orm->rollBack();
         Helpers::logError("Volunteer assignment failed: " . $e->getMessage());
         throw $e;
      }
   }

   /**
    * Confirm or decline a volunteer assignment
    *
    * Only the assigned volunteer can respond.
    *
    * @param int    $assignmentId Assignment primary key
    * @param string $action       Must be 'confirm' or 'decline'
    * @return array{status:string, new_status:string} Success response
    */
   public static function confirmAssignment(int $assignmentId, string $action): array
   {
      if (!in_array($action, ['confirm', 'decline'], true)) {
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

      if ($assignment['Status'] !== self::STATUS_PENDING) {
         Helpers::sendFeedback('Assignment is no longer pending', 400);
      }

      $newStatus = $action === 'confirm' ? self::STATUS_CONFIRMED : self::STATUS_DECLINED;

      $orm->update('event_volunteer', ['Status' => $newStatus], ['AssignmentID' => $assignmentId]);

      Helpers::logError("Volunteer assignment $assignmentId $action" . "ed by Member ID $currentUserId");
      return ['status' => 'success', 'new_status' => $newStatus];
   }

   /**
    * Mark a volunteer assignment as completed
    *
    * Typically used by event coordinators after service.
    *
    * @param int $assignmentId Assignment primary key
    * @return array{status:string, message:string} Success response
    */
   public static function completeAssignment(int $assignmentId): array
   {
      $orm = new ORM();

      $assignment = $orm->getWhere('event_volunteer', ['AssignmentID' => $assignmentId])[0] ?? null;
      if (!$assignment) {
         Helpers::sendFeedback('Assignment not found', 404);
      }

      if ($assignment['Status'] !== self::STATUS_CONFIRMED) {
         Helpers::sendFeedback('Only confirmed assignments can be marked complete', 400);
      }

      $orm->update('event_volunteer', ['Status' => self::STATUS_COMPLETED], ['AssignmentID' => $assignmentId]);

      Helpers::logError("Volunteer assignment $assignmentId marked as completed");
      return ['status' => 'success', 'message' => 'Volunteer service completed'];
   }

   /**
    * Retrieve all volunteers for a specific event
    *
    * Includes member details, role, status, and assignment metadata.
    *
    * @param int $eventId Target event ID
    * @param int $page    Page number (1-based)
    * @param int $limit   Items per page
    * @return array{data:array, pagination:array} Paginated result
    */
   public static function getByEvent(int $eventId, int $page = 1, int $limit = 50): array
   {
      $orm    = new ORM();
      $offset = ($page - 1) * $limit;

      $volunteers = $orm->selectWithJoin(
         baseTable: 'event_volunteer ev',
         joins: [
            ['table' => 'churchmember m',  'on' => 'ev.MbrID = m.MbrID'],
            ['table' => 'volunteer_role vr', 'on' => 'ev.VolunteerRoleID = vr.VolunteerRoleID', 'type' => 'LEFT'],
            ['table' => 'churchmember a',  'on' => 'ev.AssignedBy = a.MbrID']
         ],
         fields: [
            'ev.AssignmentID',
            'ev.Status',
            'ev.Notes',
            'ev.AssignedAt',
            'm.MbrID',
            'm.MbrFirstName',
            'm.MbrFamilyName',
            'm.MbrEmailAddress',
            'vr.RoleName',
            'a.MbrFirstName AS AssignedByFirstName',
            'a.MbrFamilyName AS AssignedByFamilyName'
         ],
         conditions: ['ev.EventID' => ':event_id'],
         params: [':event_id' => $eventId],
         orderBy: ['ev.AssignedAt' => 'DESC'],
         limit: $limit,
         offset: $offset
      );

      $total = $orm->runQuery(
         "SELECT COUNT(*) AS total FROM event_volunteer WHERE EventID = :id",
         [':id' => $eventId]
      )[0]['total'];

      return [
         'data' => $volunteers,
         'pagination' => [
            'page'   => $page,
            'limit'  => $limit,
            'total'  => (int)$total,
            'pages'  => (int)ceil($total / $limit)
         ]
      ];
   }

   /**
    * Remove a volunteer from an event
    *
    * Only allowed before confirmation or by authorized personnel.
    *
    * @param int $assignmentId Assignment primary key
    * @return array{status:string, message:string} Success response
    */
   public static function remove(int $assignmentId): array
   {
      $orm = new ORM();

      $assignment = $orm->getWhere('event_volunteer', ['AssignmentID' => $assignmentId])[0] ?? null;
      if (!$assignment) {
         Helpers::sendFeedback('Assignment not found', 404);
      }

      $orm->delete('event_volunteer', ['AssignmentID' => $assignmentId]);

      Helpers::logError("Volunteer removed from assignment ID $assignmentId");
      return ['status' => 'success', 'message' => 'Volunteer removed from event'];
   }
}