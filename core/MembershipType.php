<?php

/**
 * MembershipType Management Class
 * This class handles operations related to membership types in the church management system.
 * It includes methods for creating, updating, deleting, retrieving a single type, and listing all types with pagination.
 * @package MembershipType
 * @version 1.0
 */
class MembershipType
{
   /**
    * Creates a new membership type.
    * Validates input, checks for duplicates, and inserts into the database.
    * @param array $data The membership type data to create.
    * @return array The created membership type ID and status.
    * @throws Exception If validation fails or database operations fail.
    */
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
   /**
    * Updates an existing membership type.
    * Validates input, checks for duplicates, and updates the database.
    * @param int $typeId The ID of the membership type to update.
    * @param array $data The updated membership type data.
    * @return array The updated membership type ID and status.
    * @throws Exception If validation fails or database operations fail.
    */
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
   /**
    * Deletes a membership type.
    * Validates that the type exists and has no active assignments before deleting.
    * @param int $typeId The ID of the membership type to delete.
    * @return array The status of the deletion.
    * @throws Exception If validation fails or database operations fail.
    */
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
   /**
    * Retrieves a single membership type by ID.
    * @param int $typeId The ID of the membership type to retrieve.
    * @return array The membership type data.
    * @throws Exception If the type is not found or database operations fail.
    */
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
   /**
    * Retrieves a list of all membership types with pagination and optional filters.
    * @param int $page The page number for pagination.
    * @param int $limit The number of types per page.
    * @param array $filters Optional filters for the type name.
    * @return array The list of membership types and pagination info.
    * @throws Exception If database operations fail.
    */
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
   /**
    * Assigns a membership type to a member.
    * Validates input, checks for existing assignments, and inserts the new assignment.
    * @param int $memberId The ID of the member to assign the type to.
    * @param array $data The assignment data including type ID and start date.
    * @return array The status of the assignment and assignment ID.
    * @throws Exception If validation fails or database operations fail.
    */
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
   /**
    * Updates an existing membership type assignment.
    * Validates input, checks if the assignment exists, and updates the end date if provided.
    * @param int $assignmentId The ID of the assignment to update.
    * @param array $data The updated assignment data including end date.
    * @return array The status of the update and assignment ID.
    * @throws Exception If validation fails or database operations fail.
    */
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
   /**
    * Retrieves all assignments for a specific member with optional filters.
    * @param int $memberId The ID of the member to retrieve assignments for.
    * @param array $filters Optional filters for active status and date range.
    * @return array The list of assignments for the member.
    * @throws Exception If the member is not found or database operations fail.
    */
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
