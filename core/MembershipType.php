<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ORM.php';
require_once __DIR__ . '/Helpers.php';

class MembershipType
{
   public static function createType($data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'name' => 'required|alphanumeric_underscore',
            'description' => 'optional'
         ]);

         // Check for duplicate type name
         $existing = $orm->getWhere('membership_type', ['TypeName' => $data['name']]);
         if (!empty($existing)) {
            throw new Exception('Membership type name already exists');
         }

         $typeId = $orm->insert('membership_type', [
            'TypeName' => $data['name'],
            'Description' => $data['description'] ?? null
         ])['id'];

         // Create notification
         $orm->insert('communication', [
            'Title' => 'New Membership Type Created',
            'Message' => "Membership type '{$data['name']}' has been created.",
            'SentBy' => $data['created_by'] ?? 0,
            'TargetGroupID' => null
         ]);

         return ['status' => 'success', 'type_id' => $typeId];
      } catch (Exception $e) {
         Helpers::logError('MembershipType createType error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function updateType($typeId, $data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'name' => 'required|alphanumeric_underscore',
            'description' => 'optional'
         ]);

         // Validate type exists
         $type = $orm->getWhere('membership_type', ['MembershipTypeID' => $typeId]);
         if (empty($type)) {
            throw new Exception('Membership type not found');
         }

         // Check for duplicate type name
         $existing = $orm->getWhere('membership_type', ['TypeName' => $data['name'], 'MembershipTypeID != ' => $typeId]);
         if (!empty($existing)) {
            throw new Exception('Membership type name already exists');
         }

         $orm->update('membership_type', [
            'TypeName' => $data['name'],
            'Description' => $data['description'] ?? null
         ], ['MembershipTypeID' => $typeId]);

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Membership Type Updated',
            'Message' => "Membership type '{$data['name']}' has been updated.",
            'SentBy' => $data['created_by'] ?? 0,
            'TargetGroupID' => null
         ]);

         return ['status' => 'success', 'type_id' => $typeId];
      } catch (Exception $e) {
         Helpers::logError('MembershipType updateType error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function deleteType($typeId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate type exists
         $type = $orm->getWhere('membership_type', ['MembershipTypeID' => $typeId]);
         if (empty($type)) {
            throw new Exception('Membership type not found');
         }

         // Check if type is assigned
         $assignments = $orm->getWhere('member_membership_type', ['MembershipTypeID' => $typeId]);
         if (!empty($assignments)) {
            throw new Exception('Cannot delete membership type with assignments');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->delete('membership_type', ['MembershipTypeID' => $typeId]);

         $orm->commit();
         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('MembershipType deleteType error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function getType($typeId)
   {
      $orm = new ORM();
      try {
         $type = $orm->getWhere('membership_type', ['MembershipTypeID' => $typeId])[0] ?? null;
         if (!$type) {
            throw new Exception('Membership type not found');
         }
         return $type;
      } catch (Exception $e) {
         Helpers::logError('MembershipType getType error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function getAllTypes($page = 1, $limit = 10, $filters = [])
   {
      $orm = new ORM();
      try {
         $offset = ($page - 1) * $limit;
         $conditions = [];
         $params = [];

         if (!empty($filters['name']) && is_string($filters['name']) && trim($filters['name']) !== '') {
            $conditions['TypeName LIKE'] = ':name';
            $params[':name'] = '%' . trim($filters['name']) . '%';
         }

         $types = $orm->getWhere('membership_type', $conditions, $params, $limit, $offset);

         $total = $orm->runQuery(
            "SELECT COUNT(*) as total FROM membership_type" .
               (!empty($conditions) ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
            $params
         )[0]['total'];

         return [
            'data' => $types,
            'pagination' => [
               'page' => $page,
               'limit' => $limit,
               'total' => $total,
               'pages' => ceil($total / $limit)
            ]
         ];
      } catch (Exception $e) {
         Helpers::logError('MembershipType getAllTypes error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function assignType($memberId, $data)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate input
         Helpers::validateInput($data, [
            'type_id' => 'required|numeric',
            'start_date' => 'required|date'
         ]);

         // Validate member
         $member = $orm->getWhere('churchmember', [
            'MbrID' => $memberId,
            'MbrMembershipStatus' => 'Active',
            'Deleted' => 0
         ]);
         if (empty($member)) {
            throw new Exception('Invalid or inactive member');
         }

         // Validate type
         $type = $orm->getWhere('membership_type', ['MembershipTypeID' => $data['type_id']]);
         if (empty($type)) {
            throw new Exception('Membership type not found');
         }

         // Check for active membership type
         $active = $orm->getWhere('member_membership_type', [
            'MbrID' => $memberId,
            'EndDate' => null
         ]);
         if (!empty($active)) {
            throw new Exception('Member already has an active membership type');
         }

         // Validate no overlapping assignments
         $overlaps = $orm->runQuery(
            "SELECT * FROM member_membership_type 
                 WHERE MbrID = :mbr_id 
                 AND MembershipTypeID = :type_id
                 AND (EndDate IS NULL OR EndDate >= :start_date)
                 AND StartDate <= :start_date",
            [
               ':mbr_id' => $memberId,
               ':type_id' => $data['type_id'],
               ':start_date' => $data['start_date']
            ]
         );
         if (!empty($overlaps)) {
            throw new Exception('Overlapping membership type assignment exists');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $assignmentId = $orm->insert('member_membership_type', [
            'MbrID' => $memberId,
            'MembershipTypeID' => $data['type_id'],
            'StartDate' => $data['start_date'],
            'EndDate' => null
         ])['id'];

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Membership Type Assigned',
            'Message' => "You have been assigned the membership type '{$type[0]['TypeName']}' starting {$data['start_date']}.",
            'SentBy' => $data['created_by'] ?? 0,
            'TargetGroupID' => null
         ]);

         $orm->commit();
         return ['status' => 'success', 'assignment_id' => $assignmentId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('MembershipType assignType error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function updateAssignment($assignmentId, $data)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate input
         Helpers::validateInput($data, [
            'end_date' => 'optional|date'
         ]);

         // Validate assignment exists
         $assignment = $orm->getWhere('member_membership_type', ['MemberMembershipTypeID' => $assignmentId]);
         if (empty($assignment)) {
            throw new Exception('Membership type assignment not found');
         }

         // Validate end date if provided
         if (isset($data['end_date']) && $data['end_date'] < $assignment[0]['StartDate']) {
            throw new Exception('End date cannot be before start date');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $updateData = [];
         if (isset($data['end_date'])) {
            $updateData['EndDate'] = $data['end_date'];
         }

         if (!empty($updateData)) {
            $orm->update('member_membership_type', $updateData, ['MemberMembershipTypeID' => $assignmentId]);

            // Create notification
            $type = $orm->getWhere('membership_type', ['MembershipTypeID' => $assignment[0]['MembershipTypeID']]);
            $orm->insert('communication', [
               'Title' => 'Membership Type Assignment Updated',
               'Message' => "Your membership type '{$type[0]['TypeName']}' assignment has been updated.",
               'SentBy' => $data['created_by'] ?? 0,
               'TargetGroupID' => null
            ]);
         }

         $orm->commit();
         return ['status' => 'success', 'assignment_id' => $assignmentId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('MembershipType updateAssignment error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function getMemberAssignments($memberId, $filters = [])
   {
      $orm = new ORM();
      try {
         // Validate member exists
         $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
         if (empty($member)) {
            throw new Exception('Invalid member');
         }

         $conditions = ['mmt.MbrID' => ':mbr_id'];
         $params = [':mbr_id' => $memberId];

         if (!empty($filters['active']) && $filters['active'] === true) {
            $conditions['mmt.EndDate'] = 'IS NULL';
         }
         if (!empty($filters['start_date'])) {
            $conditions['mmt.StartDate >='] = ':start_date';
            $params[':start_date'] = $filters['start_date'];
         }
         if (!empty($filters['end_date'])) {
            $conditions['mmt.EndDate <='] = ':end_date';
            $params[':end_date'] = $filters['end_date'];
         }

         $assignments = $orm->selectWithJoin(
            baseTable: 'member_membership_type mmt',
            joins: [
               ['table' => 'membership_type mt', 'on' => 'mmt.MembershipTypeID = mt.MembershipTypeID', 'type' => 'LEFT']
            ],
            fields: [
               'mmt.MemberMembershipTypeID',
               'mmt.MembershipTypeID',
               'mt.TypeName',
               'mmt.StartDate',
               'mmt.EndDate'
            ],
            conditions: $conditions,
            params: $params
         );

         return ['data' => $assignments];
      } catch (Exception $e) {
         Helpers::logError('MembershipType getMemberAssignments error: ' . $e->getMessage());
         throw $e;
      }
   }
}
