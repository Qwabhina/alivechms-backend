<?php

/** Event Management Class
 * Handles event creation, updating, deletion, retrieval, and attendance management
 * Validates inputs and ensures data integrity
 * @package Event
 * @version 1.0
 */
class Event
{
   /**
    * Create a new church event
    * @param array $data Event data including name, date, branch, and creator
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function create($data)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate input
         Helpers::validateInput($data, [
            'name' => 'required',
            'date' => 'required',
            'branch_id' => 'required|numeric',
            'created_by' => 'required|numeric'
         ]);

         // Validate date is in the future
         if (strtotime($data['date']) < time()) Helpers::sendFeedback('Event date must be in the future');

         // Validate branch exists
         $branch = $orm->getWhere('branch', ['BranchID' => $data['branch_id']]);
         if (empty($branch)) Helpers::sendFeedback('Invalid branch ID');

         // Validate creator exists
         $creator = $orm->getWhere('churchmember', ['MbrID' => $data['created_by'], 'Deleted' => 0]);
         if (empty($creator)) Helpers::sendFeedback('Invalid creator ID');

         $orm->beginTransaction();
         $transactionStarted = true;

         $eventId = $orm->insert('churchevent', [
            'EventName' => $data['name'],
            'EventDescription' => $data['description'] ?? null,
            'EventDateTime' => $data['date'],
            'EventTime' => $data['time'] ?? null,
            'Location' => $data['location'] ?? null,
            'BranchID' => $data['branch_id'],
            'CreatedBy' => $data['created_by'],
            'CreatedAt' => date('Y-m-d H:i:s'),
            'UpdatedAt' => date('Y-m-d H:i:s')
         ])['id'];

         // Create notification for members
         $orm->insert('communication', [
            'Title' => 'New Event Created',
            'Message' => "New event '{$data['name']}' scheduled for {$data['date']} at {$data['location']}.",
            'SentBy' => $data['created_by'],
            'TargetGroupID' => null
         ]);

         $orm->commit();
         return ['status' => 'success', 'event_id' => $eventId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();
         Helpers::logError('Event create error: ' . $e->getMessage());
         Helpers::sendFeedback('Event creation failed');
      }
   }
   /**
    * Update an existing church event
    * @param int $eventId ID of the event to update
    * @param array $data Updated event data
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function update($eventId, $data)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate input
         Helpers::validateInput($data, [
            'name' => 'required',
            'date' => 'required',
            'branch_id' => 'required|numeric'
         ]);

         // Validate date is in the future
         if (strtotime($data['date']) < time()) Helpers::sendFeedback('Event date must be in the future');

         // Validate branch exists
         $branch = $orm->getWhere('branch', ['BranchID' => $data['branch_id']]);
         if (empty($branch)) Helpers::sendFeedback('Invalid branch ID');

         // Validate event exists
         $event = $orm->getWhere('churchevent', ['EventID' => $eventId]);
         if (empty($event)) Helpers::sendFeedback('Event not found');

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->update('churchevent', [
            'EventName' => $data['name'],
            'EventDescription' => $data['description'] ?? null,
            'EventDate' => $data['date'],
            'EventTime' => $data['time'] ?? null,
            'Location' => $data['location'] ?? null,
            'BranchID' => $data['branch_id'],
            'UpdatedAt' => date('Y-m-d H:i:s')
         ], ['EventID' => $eventId]);

         // Create notification for updates
         $orm->insert('communication', [
            'Title' => 'Event Updated',
            'Message' => "Event '{$data['name']}' has been updated. New date: {$data['date']}, location: {$data['location']}.",
            'SentBy' => $data['created_by'] ?? $event[0]['CreatedBy'],
            'TargetGroupID' => null
         ]);

         $orm->commit();
         return ['status' => 'success', 'event_id' => $eventId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();

         Helpers::logError('Event update error: ' . $e->getMessage());
         Helpers::sendFeedback('Event update failed');
      }
   }
   /**
    * Delete an existing church event
    * @param int $eventId ID of the event to delete
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function delete($eventId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate event exists
         $event = $orm->getWhere('churchevent', ['EventID' => $eventId]);
         if (empty($event)) Helpers::sendFeedback('Event not found');

         $orm->beginTransaction();
         $transactionStarted = true;

         // Delete related attendance and volunteer records
         $orm->delete('eventattendance', ['EventID' => $eventId]);
         $orm->delete('volunteer', ['EventID' => $eventId]);
         $orm->delete('churchevent', ['EventID' => $eventId]);

         $orm->commit();
         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();
         Helpers::logError('Event delete error: ' . $e->getMessage());
         Helpers::sendFeedback('Event deletion failed');
      }
   }
   /**
    * Get details of a specific event by ID
    * @param int $eventId ID of the event to retrieve
    * @return array Event details
    * @throws Exception if event not found or database operations fail
    */
   public static function get($eventId)
   {
      $orm = new ORM();
      try {
         $event = $orm->selectWithJoin(
            baseTable: 'churchevent e',
            joins: [
               // ['table' => 'branch b', 'on' => 'e.BranchID = b.BranchID', 'type' => 'LEFT'],
               ['table' => 'churchmember m', 'on' => 'e.CreatedBy = m.MbrID', 'type' => 'LEFT']
            ],
            fields: [
               'e.*',
               // 'b.BranchName',
               'm.MbrFirstName as CreatorFirstName',
               'm.MbrFamilyName as CreatorFamilyName'
            ],
            conditions: ['e.EventID' => ':id'],
            params: [':id' => $eventId]
         )[0] ?? null;

         if (!$event) Helpers::sendFeedback('Event not found', 404);

         return $event;
      } catch (Exception $e) {
         Helpers::logError('Event get error: ' . $e->getMessage());
         Helpers::sendFeedback('Failed to retrieve event details');
      }
   }
   /**
    * Get all events with pagination and optional filters
    * @param int $page Page number for pagination
    * @param int $limit Number of events per page
    * @param array $filters Optional filters for branch ID and date range
    * @return array List of events with pagination info
    * @throws Exception if database operations fail
    */
   public static function getAll($page = 1, $limit = 10, $filters = [])
   {
      $orm = new ORM();
      try {
         $offset = ($page - 1) * $limit;
         $conditions = [];
         $params = [];

         if (!empty($filters['branch_id'])) {
            $conditions['e.BranchID'] = ':branch_id';
            $params[':branch_id'] = $filters['branch_id'];
         }
         if (!empty($filters['date_from'])) {
            $conditions['e.EventDate >='] = ':date_from';
            $params[':date_from'] = $filters['date_from'];
         }
         if (!empty($filters['date_to'])) {
            $conditions['e.EventDate <='] = ':date_to';
            $params[':date_to'] = $filters['date_to'];
         }

         $events = $orm->selectWithJoin(
            baseTable: 'churchevent e',
            joins: [
               // ['table' => 'branch b', 'on' => 'e.BranchID = b.BranchID', 'type' => 'LEFT'],
               ['table' => 'churchmember m', 'on' => 'e.CreatedBy = m.MbrID', 'type' => 'LEFT']
            ],
            fields: [
               'e.*',
               // 'b.BranchName',
               'm.MbrFirstName as CreatorFirstName',
               'm.MbrFamilyName as CreatorFamilyName'
            ],
            conditions: $conditions,
            params: $params,
            limit: $limit,
            offset: $offset
         );

         $total = $orm->runQuery(
            "SELECT COUNT(*) as total FROM churchevent e" .
               (!empty($conditions) ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
            $params
         )[0]['total'];

         return [
            'data' => $events,
            'pagination' => [
               'page' => $page,
               'limit' => $limit,
               'total' => $total,
               'pages' => ceil($total / $limit)
            ]
         ];
      } catch (Exception $e) {
         Helpers::logError('Event getAll error: ' . $e->getMessage());
         Helpers::sendFeedback('Failed to retrieve events');
      }
   }
   /**
    * Record attendance for a member at an event
    * @param int $eventId ID of the event
    * @param int $memberId ID of the member
    * @param string $status Attendance status (Present, Absent, Excused)
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function recordAttendance($eventId, $memberId, $status)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         if (!in_array($status, ['Present', 'Absent', 'Excused'])) Helpers::sendFeedback('Invalid attendance status');

         // Validate event exists
         $event = $orm->getWhere('churchevent', ['EventID' => $eventId]);
         if (empty($event)) Helpers::sendFeedback('Event not found');

         // Validate member exists
         $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
         if (empty($member)) Helpers::sendFeedback('Invalid member ID');

         $orm->beginTransaction();
         $transactionStarted = true;

         // Check if attendance record exists
         $existing = $orm->getWhere('eventattendance', ['EventID' => $eventId, 'MbrID' => $memberId]);
         if (!empty($existing)) {
            $orm->update('eventattendance', [
               'AttendanceStatus' => $status,
               'AttendanceDate' => date('Y-m-d H:i:s')
            ], ['EventID' => $eventId, 'MbrID' => $memberId]);
         } else {
            $orm->insert('eventattendance', [
               'EventID' => $eventId,
               'MbrID' => $memberId,
               'AttendanceStatus' => $status,
               'AttendanceDate' => date('Y-m-d H:i:s')
            ]);
         }
         $orm->commit();

         return ['status' => 'success', 'event_id' => $eventId, 'member_id' => $memberId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction())  $orm->rollBack();

         Helpers::logError('Event attendance error: ' . $e->getMessage());
         Helpers::sendFeedback('Failed to record attendance');
      }
   }
   /**
    * Records attendance for a member at a specific event.
    * Validates input, checks for existing attendance, and inserts into the database.
    * @param array $data The attendance data to record.
    * @return array The created attendance ID and status.
    * @throws Exception If validation fails, member or event not found, or attendance already recorded.
    */
   public static function recordAttendances($data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'event_id' => 'required|numeric',
            'member_id' => 'required|numeric',
            'attendance_date' => 'required|date'
         ]);

         // Validate member
         $member = $orm->getWhere('churchmember', [
            'MbrID' => $data['member_id'],
            'MbrMembershipStatus' => 'Active',
            'Deleted' => 0
         ]);
         if (empty($member)) {
            throw new Exception('Invalid or inactive member');
         }

         // Validate event
         $event = $orm->getWhere('event', ['EventID' => $data['event_id']]);
         if (empty($event)) {
            throw new Exception('Event not found');
         }

         // Check if already attended
         $existing = $orm->getWhere('event_attendance', [
            'EventID' => $data['event_id'],
            'MbrID' => $data['member_id'],
            'AttendanceDate' => $data['attendance_date']
         ]);
         if (!empty($existing)) {
            throw new Exception('Attendance already recorded');
         }

         $attendanceId = $orm->insert('event_attendance', [
            'EventID' => $data['event_id'],
            'MbrID' => $data['member_id'],
            'AttendanceDate' => $data['attendance_date']
         ])['id'];

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Event Attendance Recorded',
            'Message' => "Your attendance at '{$event[0]['EventName']}' on {$data['attendance_date']} has been recorded.",
            'SentBy' => $data['created_by'] ?? 0,
            'TargetMemberID' => $data['member_id'],
            'DeliveryStatus' => 'Pending'
         ]);

         return ['status' => 'success', 'attendance_id' => $attendanceId];
      } catch (Exception $e) {
         Helpers::logError('MemberEngagement recordAttendance error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Assign a volunteer to an event with a specific role
    * @param int $eventId ID of the event
    * @param int $memberId ID of the member
    * @param string $role Role of the volunteer
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function assignVolunteer($eventId, $memberId, $role)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate event exists
         $event = $orm->getWhere('churchevent', ['EventID' => $eventId]);
         if (empty($event)) Helpers::sendFeedback('Event not found');

         // Validate member exists
         $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
         if (empty($member)) Helpers::sendFeedback('Invalid member ID');

         $orm->beginTransaction();
         $transactionStarted = true;

         // Check if volunteer is already assigned
         $existing = $orm->getWhere('volunteer', ['EventID' => $eventId, 'MbrID' => $memberId]);
         if (!empty($existing)) {
            $orm->update('volunteer', [
               'Role' => $role,
               'AssignedAt' => date('Y-m-d H:i:s')
            ], ['EventID' => $eventId, 'MbrID' => $memberId]);
         } else {
            $orm->insert('volunteer', [
               'EventID' => $eventId,
               'MbrID' => $memberId,
               'Role' => $role,
               'AssignedAt' => date('Y-m-d H:i:s')
            ]);
         }

         // Create notification for volunteer
         $orm->insert('communication', [
            'Title' => 'Volunteer Assignment',
            'Message' => "You have been assigned as {$role} for event '{$event[0]['EventName']}' on {$event[0]['EventDate']}.",
            'SentBy' => $event[0]['CreatedBy'],
            'TargetGroupID' => null
         ]);
         $orm->commit();

         return ['status' => 'success', 'event_id' => $eventId, 'member_id' => $memberId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();

         Helpers::logError('Event volunteer error: ' . $e->getMessage());
         Helpers::sendFeedback('Failed to assign volunteer');
      }
   }
   /**
    * Get attendance records for a specific event with optional date filters
    * @param int $eventId ID of the event
    * @param array $filters Optional filters for date range
    * @return array List of attendance records
    * @throws Exception if validation fails or database operations fail
    */
   public static function getAttendance($eventId, $filters = [])
   {
      $orm = new ORM();
      try {
         $conditions = ['ea.EventID = :id'];
         $params = [':id' => $eventId];

         // Validate and add date range filters
         if (!empty($filters['date_from'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) Helpers::sendFeedback('Invalid date_from format. Use YYYY-MM-DD');

            $conditions[] = 'DATE(e.EventDateTime) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
         }
         if (!empty($filters['date_to'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) Helpers::sendFeedback('Invalid date_to format. Use YYYY-MM-DD');

            $conditions[] = 'DATE(e.EventDateTime) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
         }

         $attendance = $orm->selectWithJoin(
            baseTable: 'eventattendance ea',
            joins: [
               ['table' => 'churchmember m', 'on' => 'ea.MbrID = m.MbrID', 'type' => 'LEFT'],
               ['table' => 'churchevent e', 'on' => 'ea.EventID = e.EventID', 'type' => 'LEFT']
            ],
            fields: [
               'ea.*',
               'm.MbrFirstName',
               'm.MbrFamilyName',
               'm.MbrOtherNames',
               'e.EventName',
               'e.EventDateTime',
               'e.Location'
            ],
            conditions: $conditions,
            params: $params
         );

         if (empty($attendance)) Helpers::sendFeedback('No attendance records found for this event');

         return ['data' => $attendance];
      } catch (Exception $e) {
         Helpers::logError('Event attendance get error: ' . $e->getMessage());
         Helpers::sendFeedback('Failed to retrieve attendance records');
      }
   }
   /**
    * Get volunteers assigned to a specific event
    * @param int $eventId ID of the event
    * @return array List of volunteers with their details
    * @throws Exception if database operations fail
    */
   public static function getVolunteers($eventId)
   {
      $orm = new ORM();
      try {
         $volunteers = $orm->selectWithJoin(
            baseTable: 'volunteer v',
            joins: [
               ['table' => 'churchmember m', 'on' => 'v.MbrID = m.MbrID', 'type' => 'LEFT'],
               ['table' => 'churchevent e', 'on' => 'v.EventID = e.EventID', 'type' => 'LEFT']
            ],
            fields: [
               'v.*',
               'm.MbrFirstName',
               'm.MbrFamilyName',
               'e.EventName'
            ],
            conditions: ['v.EventID' => ':id'],
            params: [':id' => $eventId]
         );

         return ['data' => $volunteers];
      } catch (Exception $e) {
         Helpers::logError('Event volunteers get error: ' . $e->getMessage());
         Helpers::sendFeedback('Failed to retrieve volunteers for this event');
      }
   }
   /**
    * Generate reports based on event attendance and volunteer participation
    * @param string $type Type of report to generate
    * @param array $filters Optional filters for the report
    * @return array Report data
    * @throws Exception if report type is invalid or database operations fail
    */
   public static function getReports($type, $filters = [])
   {
      $orm = new ORM();
      try {
         $params = [];
         $sql = '';

         switch ($type) {
            case 'attendance_by_event':
               $sql = "SELECT e.EventName, e.EventDateTime, COUNT(ea.AttendanceID) as total_attendees, 
                            SUM(CASE WHEN ea.AttendanceStatus = 'Present' THEN 1 ELSE 0 END) as present_count
                            FROM churchevent e 
                            LEFT JOIN eventattendance ea ON e.EventID = ea.EventID";
               if (!empty($filters['date_from'])) {
                  $sql .= " WHERE e.EventDateTime >= :date_from";
                  $params[':date_from'] = $filters['date_from'];
               }
               if (!empty($filters['date_to'])) {
                  $sql .= (!empty($params) ? ' AND' : ' WHERE') . " e.EventDateTime <= :date_to";
                  $params[':date_to'] = $filters['date_to'];
               }
               $sql .= " GROUP BY e.EventID";
               break;

            case 'volunteer_participation':
               $sql = "SELECT m.MbrFirstName, m.MbrFamilyName, COUNT(v.VolunteerID) as total_events, 
                            GROUP_CONCAT(v.Role) as roles
                            FROM volunteer v 
                            JOIN churchmember m ON v.MbrID = m.MbrID
                            JOIN churchevent e ON v.EventID = e.EventID";
               if (!empty($filters['date_from'])) {
                  $sql .= " WHERE e.EventDateTime >= :date_from";
                  $params[':date_from'] = $filters['date_from'];
               }
               if (!empty($filters['date_to'])) {
                  $sql .= (!empty($params) ? ' AND' : ' WHERE') . " e.EventDateTime <= :date_to";
                  $params[':date_to'] = $filters['date_to'];
               }
               $sql .= " GROUP BY m.MbrID";
               break;

            case 'attendance_by_member':
               $sql = "SELECT m.MbrFirstName, m.MbrFamilyName, 
                            COUNT(ea.AttendanceID) as total_events, 
                            SUM(CASE WHEN ea.AttendanceStatus = 'Present' THEN 1 ELSE 0 END) as present_count,
                            SUM(CASE WHEN ea.AttendanceStatus = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                            SUM(CASE WHEN ea.AttendanceStatus = 'Excused' THEN 1 ELSE 0 END) as excused_count
                            FROM churchmember m 
                            LEFT JOIN eventattendance ea ON m.MbrID = ea.MbrID
                            LEFT JOIN churchevent e ON ea.EventID = e.EventID";
               if (!empty($filters['branch_id']) && is_numeric($filters['branch_id'])) {
                  $sql .= " WHERE e.BranchID = :branch_id";
                  $params[':branch_id'] = $filters['branch_id'];
               }
               if (!empty($filters['date_from'])) {
                  $sql .= (!empty($params) ? ' AND' : ' WHERE') . " e.EventDateTime >= :date_from";
                  $params[':date_from'] = $filters['date_from'];
               }
               if (!empty($filters['date_to'])) {
                  $sql .= (!empty($params) ? ' AND' : ' WHERE') . " e.EventDateTime <= :date_to";
                  $params[':date_to'] = $filters['date_to'];
               }
               $sql .= " GROUP BY m.MbrID";
               break;

            case 'participation_by_branch':
               $sql = "SELECT b.BranchName, 
                            COUNT(DISTINCT e.EventID) as total_events, 
                            COUNT(ea.AttendanceID) as total_attendees, 
                            COUNT(v.VolunteerID) as total_volunteers
                            FROM branch b 
                            LEFT JOIN churchevent e ON b.BranchName = e.Location
                            LEFT JOIN eventattendance ea ON e.EventID = ea.EventID
                            LEFT JOIN volunteer v ON e.EventID = v.EventID";

               if (!empty($filters['event_name'])) {
                  $sql .= " WHERE e.EventName LIKE :event_name";
                  $params[':event_name'] = '' . trim($filters['event_name']) . '%';
               }
               if (!empty($filters['date_from'])) {
                  $sql .= (!empty($params) ? ' AND' : ' WHERE') . " e.EventDate >= :date_from";
                  $params[':date_from'] = $filters['date_from'];
               }
               if (!empty($filters['date_to'])) {
                  $sql .= (!empty($params) ? ' AND' : ' WHERE') . " e.EventDate <= :date_to";
                  $params[':date_to'] = $filters['date_to'];
               }
               $sql .= " GROUP BY b.BranchID";
               break;

            default:
               throw new Exception('Invalid report type');
         }

         $results = $orm->runQuery($sql, $params);
         return ['data' => $results];
      } catch (Exception $e) {
         Helpers::logError('Event report error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Bulk attendance management for multiple members at an event
    * @param int $eventId ID of the event
    * @param array $attendances List of attendance records with member IDs and statuses
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function bulkAttendance($eventId, $attendances)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         $event = $orm->getWhere('churchevent', ['EventID' => $eventId]);
         if (empty($event)) {
            throw new Exception('Event not found');
         }
         $orm->beginTransaction();
         $transactionStarted = true;
         foreach ($attendances as $attendance) {
            if (!in_array($attendance['status'], ['Present', 'Absent', 'Excused'])) Helpers::sendFeedback('Invalid attendance status for member ' . $attendance['member_id']);

            $member = $orm->getWhere('churchmember', ['MbrID' => $attendance['member_id'], 'Deleted' => 0]);
            if (empty($member)) Helpers::sendFeedback('Invalid member ID: ' . $attendance['member_id']);

            $existing = $orm->getWhere('eventattendance', ['EventID' => $eventId, 'MbrID' => $attendance['member_id']]);
            if (!empty($existing)) {
               $orm->update('eventattendance', [
                  'AttendanceStatus' => $attendance['status'],
                  'AttendanceDate' => date('Y-m-d H:i:s')
               ], ['EventID' => $eventId, 'MbrID' => $attendance['member_id']]);
            } else {
               $orm->insert('eventattendance', [
                  'EventID' => $eventId,
                  'MbrID' => $attendance['member_id'],
                  'AttendanceStatus' => $attendance['status'],
                  'AttendanceDate' => date('Y-m-d H:i:s')
               ]);
            }
         }
         $orm->commit();
         return ['status' => 'success', 'event_id' => $eventId, 'count' => count($attendances)];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) $orm->rollBack();

         Helpers::logError('Bulk attendance error: ' . $e->getMessage());
         Helpers::sendFeedback('Failed to record bulk attendance');
      }
   }
   /**
    * Self-attendance management for a member at an event
    * @param int $eventId ID of the event
    * @param string $status Attendance status (Present, Absent, Excused)
    * @param int $userId ID of the member
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function selfAttendance($eventId, $status, $userId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         if (!in_array($status, ['Present', 'Absent', 'Excused'])) {
            throw new Exception('Invalid attendance status');
         }
         $event = $orm->getWhere('churchevent', ['EventID' => $eventId]);
         if (empty($event)) Helpers::sendFeedback('Event not found');

         $member = $orm->getWhere('churchmember', ['MbrID' => $userId, 'Deleted' => 0]);
         if (empty($member)) Helpers::sendFeedback('Invalid member ID');

         $orm->beginTransaction();
         $transactionStarted = true;
         $existing = $orm->getWhere('eventattendance', ['EventID' => $eventId, 'MbrID' => $userId]);
         if (!empty($existing)) {
            $orm->update('eventattendance', [
               'AttendanceStatus' => $status,
               'AttendanceDate' => date('Y-m-d H:i:s')
            ], ['EventID' => $eventId, 'MbrID' => $userId]);
         } else {
            $orm->insert('eventattendance', [
               'EventID' => $eventId,
               'MbrID' => $userId,
               'AttendanceStatus' => $status,
               'AttendanceDate' => date('Y-m-d H:i:s')
            ]);
         }
         $orm->commit();
         return ['status' => 'success', 'event_id' => $eventId, 'member_id' => $userId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Self attendance error: ' . $e->getMessage());
         throw $e;
      }
   }
}
