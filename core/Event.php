<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ORM.php';
require_once __DIR__ . '/Helpers.php';

class Event
{
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
         if (strtotime($data['date']) < time()) {
            throw new Exception('Event date must be in the future');
         }

         // Validate branch exists
         $branch = $orm->getWhere('branch', ['BranchID' => $data['branch_id']]);
         if (empty($branch)) {
            throw new Exception('Invalid branch ID');
         }

         // Validate creator exists
         $creator = $orm->getWhere('churchmember', ['MbrID' => $data['created_by'], 'Deleted' => 0]);
         if (empty($creator)) {
            throw new Exception('Invalid creator ID');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $eventId = $orm->insert('churchevent', [
            'EventName' => $data['name'],
            'EventDescription' => $data['description'] ?? null,
            'EventDate' => $data['date'],
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
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Event create error: ' . $e->getMessage());
         throw $e;
      }
   }

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
         if (strtotime($data['date']) < time()) {
            throw new Exception('Event date must be in the future');
         }

         // Validate branch exists
         $branch = $orm->getWhere('branch', ['BranchID' => $data['branch_id']]);
         if (empty($branch)) {
            throw new Exception('Invalid branch ID');
         }

         // Validate event exists
         $event = $orm->getWhere('churchevent', ['EventID' => $eventId]);
         if (empty($event)) {
            throw new Exception('Event not found');
         }

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
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Event update error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function delete($eventId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate event exists
         $event = $orm->getWhere('churchevent', ['EventID' => $eventId]);
         if (empty($event)) {
            throw new Exception('Event not found');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         // Delete related attendance and volunteer records
         $orm->delete('eventattendance', ['EventID' => $eventId]);
         $orm->delete('volunteer', ['EventID' => $eventId]);
         $orm->delete('churchevent', ['EventID' => $eventId]);

         $orm->commit();
         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Event delete error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function get($eventId)
   {
      $orm = new ORM();
      try {
         $event = $orm->selectWithJoin(
            baseTable: 'churchevent e',
            joins: [
               ['table' => 'branch b', 'on' => 'e.BranchID = b.BranchID', 'type' => 'LEFT'],
               ['table' => 'churchmember m', 'on' => 'e.CreatedBy = m.MbrID', 'type' => 'LEFT']
            ],
            fields: [
               'e.*',
               'b.BranchName',
               'm.MbrFirstName as CreatorFirstName',
               'm.MbrFamilyName as CreatorFamilyName'
            ],
            conditions: ['e.EventID' => ':id'],
            params: [':id' => $eventId]
         )[0] ?? null;

         if (!$event) {
            throw new Exception('Event not found');
         }
         return $event;
      } catch (Exception $e) {
         Helpers::logError('Event get error: ' . $e->getMessage());
         throw $e;
      }
   }

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
               ['table' => 'branch b', 'on' => 'e.BranchID = b.BranchID', 'type' => 'LEFT'],
               ['table' => 'churchmember m', 'on' => 'e.CreatedBy = m.MbrID', 'type' => 'LEFT']
            ],
            fields: [
               'e.*',
               'b.BranchName',
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
         throw $e;
      }
   }

   public static function recordAttendance($eventId, $memberId, $status)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         if (!in_array($status, ['Present', 'Absent', 'Excused'])) {
            throw new Exception('Invalid attendance status');
         }

         // Validate event exists
         $event = $orm->getWhere('churchevent', ['EventID' => $eventId]);
         if (empty($event)) {
            throw new Exception('Event not found');
         }

         // Validate member exists
         $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
         if (empty($member)) {
            throw new Exception('Invalid member ID');
         }

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
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Event attendance error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function assignVolunteer($eventId, $memberId, $role)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate role
         $validRoles = ['Usher', 'Greeter', 'Worship Leader', 'Tech Support'];
         if (!in_array($role, $validRoles)) {
            throw new Exception('Invalid volunteer role');
         }

         // Validate event exists
         $event = $orm->getWhere('churchevent', ['EventID' => $eventId]);
         if (empty($event)) {
            throw new Exception('Event not found');
         }

         // Validate member exists
         $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
         if (empty($member)) {
            throw new Exception('Invalid member ID');
         }

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
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Event volunteer error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function getAttendance($eventId)
   {
      $orm = new ORM();
      try {
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
               'e.EventName'
            ],
            conditions: ['ea.EventID' => ':id'],
            params: [':id' => $eventId]
         );

         return ['data' => $attendance];
      } catch (Exception $e) {
         Helpers::logError('Event attendance get error: ' . $e->getMessage());
         throw $e;
      }
   }

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
         throw $e;
      }
   }

   public static function getReports($type, $filters = [])
   {
      $orm = new ORM();
      try {
         $params = [];
         $sql = '';

         switch ($type) {
            case 'attendance_by_event':
               $sql = "SELECT e.EventName, e.EventDate, COUNT(ea.AttendanceID) as total_attendees, 
                            SUM(CASE WHEN ea.AttendanceStatus = 'Present' THEN 1 ELSE 0 END) as present_count
                            FROM churchevent e 
                            LEFT JOIN eventattendance ea ON e.EventID = ea.EventID";
               if (!empty($filters['date_from'])) {
                  $sql .= " WHERE e.EventDate >= :date_from";
                  $params[':date_from'] = $filters['date_from'];
               }
               if (!empty($filters['date_to'])) {
                  $sql .= (!empty($params) ? ' AND' : ' WHERE') . " e.EventDate <= :date_to";
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
                  $sql .= " WHERE e.EventDate >= :date_from";
                  $params[':date_from'] = $filters['date_from'];
               }
               if (!empty($filters['date_to'])) {
                  $sql .= (!empty($params) ? ' AND' : ' WHERE') . " e.EventDate <= :date_to";
                  $params[':date_to'] = $filters['date_to'];
               }
               $sql .= " GROUP BY m.MbrID";
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
}
