<?php

/**
 * Event Management
 *
 * Complete church event lifecycle: creation, update, deletion,
 * bulk/single attendance recording, and detailed retrieval.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class Event
{
   /**
    * Create a new church event
    *
    * @param array $data Event payload
    * @return array ['status' => 'success', 'event_id' => int]
    * @throws Exception On validation or database failure
    */
   public static function create(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'title'       => 'required|max:150',
         'event_date'  => 'required|date',
         'branch_id'   => 'required|numeric',
         'description' => 'max:1000|nullable',
         'start_time'  => 'nullable',
         'end_time'    => 'nullable',
         'location'    => 'max:200|nullable'
      ]);

      $branchId = (int)$data['branch_id'];

      if (empty($orm->getWhere('branch', ['BranchID' => $branchId]))) {
         Helpers::sendFeedback('Invalid branch', 400);
      }

      if ($data['event_date'] < date('Y-m-d')) {
         Helpers::sendFeedback('Event date cannot be in the past', 400);
      }

      $eventId = $orm->insert('event', [
         'EventTitle'       => $data['title'],
            'EventDescription' => $data['description'] ?? null,
         'EventDate'        => $data['event_date'],
         'StartTime'        => $data['start_time'] ?? null,
         'EndTime'          => $data['end_time'] ?? null,
         'Location'         => $data['location'] ?? null,
         'BranchID'         => $branchId,
         'CreatedBy'        => Auth::getCurrentUserId(),
         'CreatedAt'        => date('Y-m-d H:i:s')
      ])['id'];

      Helpers::logError("New event created: EventID $eventId – {$data['title']}");
      return ['status' => 'success', 'event_id' => $eventId];
   }

   /**
    * Update an existing event
    *
    * @param int   $eventId Event ID
    * @param array $data    Updated data
    * @return array ['status' => 'success', 'event_id' => int]
    */
   public static function update(int $eventId, array $data): array
   {
      $orm = new ORM();

      $event = $orm->getWhere('event', ['EventID' => $eventId]);
      if (empty($event)) {
         Helpers::sendFeedback('Event not found', 404);
      }

      $update = [];

      if (!empty($data['title']))       $update['EventTitle']       = $data['title'];
      if (isset($data['description']))  $update['EventDescription'] = $data['description'];
      if (!empty($data['event_date'])) {
         if ($data['event_date'] < date('Y-m-d')) {
            Helpers::sendFeedback('Event date cannot be in the past', 400);
         }
         $update['EventDate'] = $data['event_date'];
      }
      if (isset($data['start_time']))   $update['StartTime'] = $data['start_time'];
      if (isset($data['end_time']))     $update['EndTime']   = $data['end_time'];
      if (isset($data['location']))     $update['Location']  = $data['location'];
      if (!empty($data['branch_id'])) {
         if (empty($orm->getWhere('branch', ['BranchID' => (int)$data['branch_id']]))) {
            Helpers::sendFeedback('Invalid branch', 400);
         }
         $update['BranchID'] = (int)$data['branch_id'];
      }

      if (!empty($update)) {
         $orm->update('event', $update, ['EventID' => $eventId]);
      }

      return ['status' => 'success', 'event_id' => $eventId];
   }

   /**
    * Delete an event (only if no attendance recorded)
    *
    * @param int $eventId Event ID
    * @return array ['status' => 'success']
    */
   public static function delete(int $eventId): array
   {
      $orm = new ORM();

      $event = $orm->getWhere('event', ['EventID' => $eventId]);
      if (empty($event)) {
         Helpers::sendFeedback('Event not found', 404);
      }

      if (!empty($orm->getWhere('event_attendance', ['EventID' => $eventId]))) {
         Helpers::sendFeedback('Cannot delete event with recorded attendance', 400);
      }

      $orm->delete('event', ['EventID' => $eventId]);
      return ['status' => 'success'];
   }

   /**
    * Record bulk attendance for an event
    *
    * @param int   $eventId Event ID
    * @param array $data    Attendance payload with 'attendances' array
    * @return array ['status' => 'success', 'message' => string]
    */
   public static function recordBulkAttendance(int $eventId, array $data): array
   {
      $orm = new ORM();

      $event = $orm->getWhere('event', ['EventID' => $eventId]);
      if (empty($event)) {
         Helpers::sendFeedback('Event not found', 404);
      }

      if (empty($data['attendances']) || !is_array($data['attendances'])) {
         Helpers::sendFeedback('attendances array is required', 400);
      }

      $orm->beginTransaction();
      try {
         foreach ($data['attendances'] as $item) {
            Helpers::validateInput($item, [
               'member_id' => 'required|numeric',
               'status'    => 'required|in:Present,Absent,Late,Excused'
            ]);

            $memberId = (int)$item['member_id'];
            $status   = $item['status'];

            $existing = $orm->getWhere('event_attendance', [
               'EventID' => $eventId,
               'MbrID'   => $memberId
            ]);

            if (!empty($existing)) {
               $orm->update('event_attendance', [
                  'AttendanceStatus' => $status,
                  'RecordedAt'       => date('Y-m-d H:i:s')
               ], ['EventAttendanceID' => $existing[0]['EventAttendanceID']]);
            } else {
               $orm->insert('event_attendance', [
                  'EventID'          => $eventId,
                  'MbrID'            => $memberId,
                  'AttendanceStatus' => $status,
                  'RecordedAt'       => date('Y-m-d H:i:s')
               ]);
            }
         }

         $orm->commit();
         return ['status' => 'success', 'message' => 'Attendance recorded'];
      } catch (Exception $e) {
         $orm->rollBack();
         throw $e;
      }
   }

   /**
    * Record single attendance (mobile/self-check-in)
    *
    * @param int    $eventId  Event ID
    * @param int    $memberId Member ID
    * @param string $status   Default: 'Present'
    * @return array Success response
    */
   public static function recordSingleAttendance(int $eventId, int $memberId, string $status = 'Present'): array
   {
      $validStatuses = ['Present', 'Absent', 'Late', 'Excused'];
      if (!in_array($status, $validStatuses, true)) {
         Helpers::sendFeedback('Invalid attendance status', 400);
      }

      $orm = new ORM();

      $event = $orm->getWhere('event', ['EventID' => $eventId]);
      if (empty($event)) {
         Helpers::sendFeedback('Event not found', 404);
      }

      $member = $orm->getWhere('churchmember', [
         'MbrID'              => $memberId,
         'MbrMembershipStatus' => 'Active',
         'Deleted'            => 0
      ]);
      if (empty($member)) {
         Helpers::sendFeedback('Invalid or inactive member', 400);
      }

      $existing = $orm->getWhere('event_attendance', [
         'EventID' => $eventId,
         'MbrID'   => $memberId
      ]);

      if (!empty($existing)) {
         $orm->update('event_attendance', [
            'AttendanceStatus' => $status,
            'RecordedAt'       => date('Y-m-d H:i:s')
         ], ['EventAttendanceID' => $existing[0]['EventAttendanceID']]);
      } else {
         $orm->insert('event_attendance', [
            'EventID'          => $eventId,
            'MbrID'            => $memberId,
            'AttendanceStatus' => $status,
            'RecordedAt'       => date('Y-m-d H:i:s')
         ]);
      }

      return ['status' => 'success', 'message' => 'Attendance recorded'];
   }

   /**
    * Retrieve a single event with attendance summary
    *
    * @param int $eventId Event ID
    * @return array Event data with attendance stats
    */
   public static function get(int $eventId): array
   {
      $orm = new ORM();

      $result = $orm->selectWithJoin(
         baseTable: 'event e',
            joins: [
            ['table' => 'branch b',        'on' => 'e.BranchID = b.BranchID'],
            ['table' => 'churchmember c',  'on' => 'e.CreatedBy = c.MbrID', 'type' => 'LEFT']
            ],
            fields: [
            'e.*',
            'b.BranchName',
            'c.MbrFirstName AS CreatorFirstName',
            'c.MbrFamilyName AS CreatorFamilyName'
            ],
         conditions: ['e.EventID' => ':id'],
            params: [':id' => $eventId]
      );

      if (empty($result)) {
         Helpers::sendFeedback('Event not found', 404);
      }

      $stats = $orm->runQuery(
         "SELECT AttendanceStatus, COUNT(*) AS count 
             FROM event_attendance 
             WHERE EventID = :id 
             GROUP BY AttendanceStatus",
         [':id' => $eventId]
      );

      $summary = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0];
      foreach ($stats as $row) {
         $summary[$row['AttendanceStatus']] = (int)$row['count'];
      }

      $event = $result[0];
      $event['attendance_summary'] = $summary;
      $event['total_attendance']   = array_sum($summary);

      return $event;
   }

   /**
    * Retrieve paginated events with filters
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

      if (!empty($filters['branch_id'])) {
         $conditions['e.BranchID'] = ':branch';
         $params[':branch'] = (int)$filters['branch_id'];
      }
      if (!empty($filters['start_date'])) {
         $conditions['e.EventDate >='] = ':start';
         $params[':start'] = $filters['start_date'];
      }
      if (!empty($filters['end_date'])) {
         $conditions['e.EventDate <='] = ':end';
         $params[':end'] = $filters['end_date'];
      }

      $events = $orm->selectWithJoin(
         baseTable: 'event e',
         joins: [['table' => 'branch b', 'on' => 'e.BranchID = b.BranchID']],
         fields: [
            'e.EventID',
            'e.EventTitle',
            'e.EventDate',
            'e.StartTime',
            'e.EndTime',
            'e.Location',
            'b.BranchName'
         ],
         conditions: $conditions,
         params: $params,
         orderBy: ['e.EventDate' => 'DESC', 'e.StartTime' => 'ASC'],
         limit: $limit,
         offset: $offset
      );

      $total = $orm->runQuery(
         "SELECT COUNT(*) AS total FROM event e" .
            (!empty($conditions) ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
         $params
      )[0]['total'];

      return [
         'data' => $events,
         'pagination' => [
            'page'   => $page,
            'limit'  => $limit,
            'total'  => (int)$total,
            'pages'  => (int)ceil($total / $limit)
         ]
      ];
   }
}