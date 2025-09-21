<?php

/**
 * MembershipType Management Class
 * This class handles operations related to membership types and assignments in the church management system.
 * It includes methods for creating, updating, deleting, retrieving membership types, and managing assignments to members.
 */
class MembershipType
{
   /**
    * Creates a new membership type.
    * Validates input, checks for duplicates, and inserts into the database.
    * @param array $data The membership type data including name and description.
    * @return array The created membership type ID and status.
    */
   public static function create($data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'name' => 'required|string',
            'description' => 'string|nullable'
         ]);

         // Check for duplicate type name
         $existing = $orm->getWhere('membershiptype', ['MshipTypeName' => $data['name']]);
         if (!empty($existing)) {
            Helpers::logError('MembershipType create error: Membership type name already exists');
            Helpers::sendFeedback('Membership type name already exists', 400);
         }

         $typeId = $orm->insert('membershiptype', [
            'MshipTypeName' => $data['name'],
            'MshipTypeDescription' => $data['description'] ?? null
         ])['id'];

         // Create notification
         $orm->insert('communication', [
            'Title' => 'New Membership Type Created',
            'Message' => "Membership type '{$data['name']}' has been created.",
            'SentBy' => $data['created_by'] ?? 1,
            'TargetGroupID' => null
         ]);

         return ['status' => 'success', 'type_id' => $typeId];
      } catch (Exception $e) {
         Helpers::logError('MembershipType create error: ' . $e->getMessage());
         Helpers::sendFeedback('Membership type creation failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Updates an existing membership type.
    * Validates input, checks for duplicates, and updates the database.
    * @param int $typeId The ID of the membership type to update.
    * @param array $data The updated membership type data.
    * @return array The updated membership type ID and status.
    */
   public static function update($typeId, $data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'name' => 'string|nullable',
            'description' => 'string|nullable'
         ]);

         // Validate type exists
         $type = $orm->getWhere('membershiptype', ['MshipTypeID' => $typeId]);
         if (empty($type)) {
            Helpers::logError('MembershipType update error: Membership type not found');
            Helpers::sendFeedback('Membership type not found', 404);
         }

         $updateData = [];
         if (isset($data['name'])) {
            // Check for duplicate type name (excluding current)
            $existing = $orm->getWhere('membershiptype', ['MshipTypeName' => $data['name'], 'MshipTypeID !=' => $typeId]);
            if (!empty($existing)) {
               Helpers::logError('MembershipType update error: Membership type name already exists');
               Helpers::sendFeedback('Membership type name already exists', 400);
            }
            $updateData['MshipTypeName'] = $data['name'];
         }
         if (isset($data['description'])) {
            $updateData['MshipTypeDescription'] = $data['description'];
         }

         if (empty($updateData)) {
            return ['status' => 'success', 'type_id' => $typeId];
         }

         $orm->update('membershiptype', $updateData, ['MshipTypeID' => $typeId]);

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Membership Type Updated',
            'Message' => "Membership type '{$type[0]['MshipTypeName']}' has been updated.",
            'SentBy' => $data['created_by'] ?? 1,
            'TargetGroupID' => null
         ]);

         return ['status' => 'success', 'type_id' => $typeId];
      } catch (Exception $e) {
         Helpers::logError('MembershipType update error: ' . $e->getMessage());
         Helpers::sendFeedback('Membership type update failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Deletes a membership type.
    * Validates that the type exists and has no active assignments before deleting.
    * @param int $typeId The ID of the membership type to delete.
    * @return array The status of the deletion.
    */
   public static function delete($typeId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate type exists
         $type = $orm->getWhere('membershiptype', ['MshipTypeID' => $typeId]);
         if (empty($type)) {
            Helpers::logError('MembershipType delete error: Membership type not found');
            Helpers::sendFeedback('Membership type not found', 404);
         }

         // Check if type is assigned
         $assignments = $orm->getWhere('membermembershiptype', ['MshipTypeID' => $typeId]);
         if (!empty($assignments)) {
            Helpers::logError('MembershipType delete error: Cannot delete membership type with assignments');
            Helpers::sendFeedback('Cannot delete membership type with assignments', 400);
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->delete('membershiptype', ['MshipTypeID' => $typeId]);

         $orm->commit();
         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) {
            $orm->rollBack();
         }
         Helpers::logError('MembershipType delete error: ' . $e->getMessage());
         Helpers::sendFeedback('Membership type delete failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Retrieves a single membership type by ID.
    * @param int $typeId The ID of the membership type to retrieve.
    * @return array The membership type data.
    */
   public static function get($typeId)
   {
      $orm = new ORM();
      try {
         $type = $orm->getWhere('membershiptype', ['MshipTypeID' => $typeId])[0] ?? null;
         if (!$type) {
            Helpers::logError('MembershipType get error: Membership type not found');
            Helpers::sendFeedback('Membership type not found', 404);
         }
         return $type;
      } catch (Exception $e) {
         Helpers::logError('MembershipType get error: ' . $e->getMessage());
         Helpers::sendFeedback('Membership type retrieval failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Retrieves a list of all membership types with pagination and optional filters.
    * @param int $page The page number for pagination.
    * @param int $limit The number of types per page.
    * @param array $filters Optional filters for the type name.
    * @return array The list of membership types and pagination info.
    */
   public static function getAll($page = 1, $limit = 10, $filters = [])
   {
      $orm = new ORM();
      try {
         $offset = ($page - 1) * $limit;
         $conditions = [];
         $params = [];

         if (!empty($filters['name']) && is_string($filters['name']) && trim($filters['name']) !== '') {
            $conditions['MshipTypeName LIKE'] = ':name';
            $params[':name'] = '%' . trim($filters['name']) . '%';
         }

         $types = $orm->getWhere('membershiptype', $conditions, $params, $limit, $offset);

         $whereClause = '';
         if (!empty($conditions)) {
            $whereConditions = [];
            foreach ($conditions as $column => $placeholder) {
               $whereConditions[] = "$column $placeholder";
            }
            $whereClause = ' WHERE ' . implode(' AND ', $whereConditions);
         }

         $total = $orm->runQuery(
            "SELECT COUNT(*) as total FROM membershiptype" . $whereClause,
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
         Helpers::logError('MembershipType getAll error: ' . $e->getMessage());
         Helpers::sendFeedback('Membership type retrieval failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Assigns a membership type to a member.
    * Validates input, checks for existing assignments, and inserts the new assignment.
    * @param int $memberId The ID of the member to assign the type to.
    * @param array $data The assignment data including type ID and start date.
    * @return array The status of the assignment and assignment ID.
    */
   public static function assign($memberId, $data)
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
            Helpers::logError('MembershipType assign error: Invalid or inactive member');
            Helpers::sendFeedback('Invalid or inactive member', 400);
         }

         // Validate type
         $type = $orm->getWhere('membershiptype', ['MshipTypeID' => $data['type_id']]);
         if (empty($type)) {
            Helpers::logError('MembershipType assign error: Membership type not found');
            Helpers::sendFeedback('Membership type not found', 400);
         }

         // Check for active membership type
         $active = $orm->getWhere('membermembershiptype', [
            'MbrID' => $memberId,
            'EndDate' => null
         ]);
         if (!empty($active)) {
            Helpers::logError('MembershipType assign error: Member already has an active membership type');
            Helpers::sendFeedback('Member already has an active membership type', 400);
         }

         // Validate no overlapping assignments
         $overlaps = $orm->runQuery(
            "SELECT * FROM membermembershiptype 
             WHERE MbrID = :mbr_id 
             AND MshipTypeID = :type_id
             AND (EndDate IS NULL OR EndDate >= :start_date)
             AND StartDate <= :start_date",
            [
               ':mbr_id' => $memberId,
               ':type_id' => $data['type_id'],
               ':start_date' => $data['start_date']
            ]
         );
         if (!empty($overlaps)) {
            Helpers::logError('MembershipType assign error: Overlapping membership type assignment exists');
            Helpers::sendFeedback('Overlapping membership type assignment exists', 400);
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $assignmentId = $orm->insert('membermembershiptype', [
            'MbrID' => $memberId,
            'MshipTypeID' => $data['type_id'],
            'StartDate' => $data['start_date'],
            'EndDate' => null
         ])['id'];

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Membership Type Assigned',
            'Message' => "You have been assigned the membership type '{$type[0]['MshipTypeName']}' starting {$data['start_date']}.",
            'SentBy' => $data['created_by'] ?? 1,
            'TargetGroupID' => null
         ]);

         $orm->commit();
         return ['status' => 'success', 'assignment_id' => $assignmentId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) {
            $orm->rollBack();
         }
         Helpers::logError('MembershipType assign error: ' . $e->getMessage());
         Helpers::sendFeedback('Membership type assignment failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Updates an existing membership type assignment.
    * Validates input, checks if the assignment exists, and updates the end date if provided.
    * @param int $assignmentId The ID of the assignment to update.
    * @param array $data The updated assignment data including end date.
    * @return array The status of the update and assignment ID.
    */
   public static function updateAssignment($assignmentId, $data)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate input
         Helpers::validateInput($data, [
            'end_date' => 'date|nullable'
         ]);

         // Validate assignment exists
         $assignment = $orm->getWhere('membermembershiptype', ['MemberMshipTypeID' => $assignmentId]);
         if (empty($assignment)) {
            Helpers::logError('MembershipType updateAssignment error: Membership type assignment not found');
            Helpers::sendFeedback('Membership type assignment not found', 404);
         }

         // Validate end date if provided
         if (isset($data['end_date']) && $data['end_date'] < $assignment[0]['StartDate']) {
            Helpers::logError('MembershipType updateAssignment error: End date cannot be before start date');
            Helpers::sendFeedback('End date cannot be before start date', 400);
         }

         $updateData = [];
         if (isset($data['end_date'])) {
            $updateData['EndDate'] = $data['end_date'];
         }

         if (empty($updateData)) {
            return ['status' => 'success', 'assignment_id' => $assignmentId];
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->update('membermembershiptype', $updateData, ['MemberMshipTypeID' => $assignmentId]);

         // Create notification
         $type = $orm->getWhere('membershiptype', ['MshipTypeID' => $assignment[0]['MshipTypeID']]);
         $orm->insert('communication', [
            'Title' => 'Membership Type Assignment Updated',
            'Message' => "Your membership type '{$type[0]['MshipTypeName']}' assignment has been updated.",
            'SentBy' => $data['created_by'] ?? 1,
            'TargetGroupID' => null
         ]);

         $orm->commit();
         return ['status' => 'success', 'assignment_id' => $assignmentId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) {
            $orm->rollBack();
         }
         Helpers::logError('MembershipType updateAssignment error: ' . $e->getMessage());
         Helpers::sendFeedback('Membership type assignment update failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Retrieves all assignments for a specific member with optional filters.
    * @param int $memberId The ID of the member to retrieve assignments for.
    * @param array $filters Optional filters for active status and date range.
    * @return array The list of assignments for the member.
    */
   public static function getMemberAssignments($memberId, $filters = [])
   {
      $orm = new ORM();
      try {
         // Validate member exists
         $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
         if (empty($member)) {
            Helpers::logError('MembershipType getMemberAssignments error: Invalid member');
            Helpers::sendFeedback('Invalid member', 400);
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
            baseTable: 'membermembershiptype mmt',
            joins: [
               ['table' => 'membershiptype mt', 'on' => 'mmt.MshipTypeID = mt.MshipTypeID', 'type' => 'LEFT']
            ],
            fields: [
               'mmt.MemberMshipTypeID',
               'mmt.MshipTypeID',
               'mt.MshipTypeName',
               'mmt.StartDate',
               'mmt.EndDate'
            ],
            conditions: $conditions,
            params: $params
         );

         return ['data' => $assignments];
      } catch (Exception $e) {
         Helpers::logError('MembershipType getMemberAssignments error: ' . $e->getMessage());
         Helpers::sendFeedback('Membership assignments retrieval failed: ' . $e->getMessage(), 400);
      }
   }
}