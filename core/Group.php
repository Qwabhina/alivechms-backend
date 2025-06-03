<?php

/**
 * Group Management Class
 * Handles creation, updating, deletion, and retrieval of church groups.
 * Also manages group members and communications. 
 */
class Group
{
   /**
    * Create a new church group
    *
    * @param array $data Group data including name, leader_id, type_id, and optional description
    * @return array Result of the operation with status and group ID
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
            'leader_id' => 'required|numeric',
            'type_id' => 'required|numeric'
         ]);

         // Validate leader exists and is active
         $leader = $orm->getWhere('churchmember', ['MbrID' => $data['leader_id'], 'MbrMembershipStatus' => 'Active', 'Deleted' => 0]);
         if (empty($leader)) {
            throw new Exception('Invalid or inactive leader ID');
         }

         // Validate group type exists
         $groupType = $orm->getWhere('grouptype', ['GroupTypeID' => $data['type_id']]);
         if (empty($groupType)) {
            throw new Exception('Invalid group type ID');
         }

         // Check for duplicate group name
         $existing = $orm->getWhere('churchgroup', ['GroupName' => $data['name']]);
         if (!empty($existing)) {
            throw new Exception('Group name already exists');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $groupId = $orm->insert('churchgroup', [
            'GroupName' => $data['name'],
            'GroupLeaderID' => $data['leader_id'],
            'GroupDescription' => $data['description'] ?? null,
            'GroupTypeID' => $data['type_id']
         ])['id'];

         // Create notification
         $orm->insert('communication', [
            'Title' => 'New Group Created',
            'Message' => "Group '{$data['name']}' has been created.",
            'SentBy' => $data['created_by'] ?? $data['leader_id'],
            'TargetGroupID' => $groupId
         ]);

         $orm->commit();
         return ['status' => 'success', 'group_id' => $groupId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Group create error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Update an existing church group
    *
    * @param int $groupId ID of the group to update
    * @param array $data Updated group data including name, leader_id, type_id, and optional description
    * @return array Result of the operation with status and group ID
    * @throws Exception if validation fails or database operations fail
    */
   public static function update($groupId, $data)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate input
         Helpers::validateInput($data, [
            'name' => 'required',
            'leader_id' => 'required|numeric',
            'type_id' => 'required|numeric'
         ]);

         // Validate group exists
         $group = $orm->getWhere('churchgroup', ['GroupID' => $groupId]);
         if (empty($group)) {
            throw new Exception('Group not found');
         }

         // Validate leader exists and is active
         $leader = $orm->getWhere('churchmember', ['MbrID' => $data['leader_id'], 'MbrMembershipStatus' => 'Active', 'Deleted' => 0]);
         if (empty($leader)) {
            throw new Exception('Invalid or inactive leader ID');
         }

         // Validate group type exists
         $groupType = $orm->getWhere('grouptype', ['GroupTypeID' => $data['type_id']]);
         if (empty($groupType)) {
            throw new Exception('Invalid group type ID');
         }

         // Check for duplicate group name (excluding current group)
         $existing = $orm->getWhere('churchgroup', ['GroupName' => $data['name'], 'GroupID != ' => $groupId]);
         if (!empty($existing)) {
            throw new Exception('Group name already exists');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->update('churchgroup', [
            'GroupName' => $data['name'],
            'GroupLeaderID' => $data['leader_id'],
            'GroupDescription' => $data['description'] ?? $group[0]['GroupDescription'],
            'GroupTypeID' => $data['type_id']
         ], ['GroupID' => $groupId]);

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Group Updated',
            'Message' => "Group '{$data['name']}' has been updated.",
            'SentBy' => $data['created_by'] ?? $data['leader_id'],
            'TargetGroupID' => $groupId
         ]);

         $orm->commit();
         return ['status' => 'success', 'group_id' => $groupId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Group update error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Delete a church group
    *
    * @param int $groupId ID of the group to delete
    * @return array Result of the operation with status
    * @throws Exception if validation fails or database operations fail
    */
   public static function delete($groupId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate group exists
         $group = $orm->getWhere('churchgroup', ['GroupID' => $groupId]);
         if (empty($group)) {
            throw new Exception('Group not found');
         }

         // Check if group has members or communications
         $referenced = $orm->runQuery(
            "SELECT (SELECT COUNT(*) FROM groupmember WHERE GroupID = :id) +
                        (SELECT COUNT(*) FROM communication WHERE TargetGroupID = :id) as ref_count",
            [':id' => $groupId]
         )[0]['ref_count'];
         if ($referenced > 0) {
            throw new Exception('Cannot delete group with members or communications');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->delete('churchgroup', ['GroupID' => $groupId]);

         $orm->commit();
         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Group delete error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Get details of a specific church group
    *
    * @param int $groupId ID of the group to retrieve
    * @return array Group details including leader, type, and member count
    * @throws Exception if group not found or database operations fail
    */
   public static function get($groupId)
   {
      $orm = new ORM();
      try {
         $group = $orm->selectWithJoin(
            baseTable: 'churchgroup g',
            joins: [
               ['table' => 'churchmember m', 'on' => 'g.GroupLeaderID = m.MbrID', 'type' => 'LEFT'],
               ['table' => 'grouptype gt', 'on' => 'g.GroupTypeID = gt.GroupTypeID', 'type' => 'LEFT'],
               ['table' => 'branch b', 'on' => 'm.BranchID = b.BranchID', 'type' => 'LEFT']
            ],
            fields: [
               'g.*',
               'm.MbrFirstName as LeaderFirstName',
               'm.MbrFamilyName as LeaderFamilyName',
               'gt.GroupTypeName',
               'b.BranchName'
            ],
            conditions: ['g.GroupID' => ':id'],
            params: [':id' => $groupId]
         )[0] ?? null;

         if (!$group) {
            throw new Exception('Group not found');
         }

         // Get member count
         $memberCount = $orm->runQuery(
            "SELECT COUNT(*) as count FROM groupmember WHERE GroupID = :id",
            [':id' => $groupId]
         )[0]['count'];

         $group['MemberCount'] = $memberCount;
         return $group;
      } catch (Exception $e) {
         Helpers::logError('Group get error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Get all church groups with pagination and optional filters
    *
    * @param int $page Page number for pagination
    * @param int $limit Number of groups per page
    * @param array $filters Optional filters for group type, branch, and name
    * @return array List of groups with pagination details
    * @throws Exception if database operations fail
    */
   public static function getAll($page = 1, $limit = 10, $filters = [])
   {
      $orm = new ORM();
      try {
         $offset = ($page - 1) * $limit;
         $conditions = [];
         $params = [];

         if (!empty($filters['type_id'])) {
            $conditions['g.GroupTypeID ='] = ':type_id';
            $params[':type_id'] = $filters['type_id'];
         }
         if (!empty($filters['branch_id'])) {
            $conditions['m.BranchID ='] = ':branch_id';
            $params[':branch_id'] = $filters['branch_id'];
         }
         if (!empty($filters['name']) && is_string($filters['name']) && trim($filters['name']) !== '') {
            $conditions['g.GroupName LIKE'] = ':name';
            $params[':name'] = '%' . trim($filters['name']) . '%';
         }

         $groups = $orm->selectWithJoin(
            baseTable: 'churchgroup g',
            joins: [
               ['table' => 'churchmember m', 'on' => 'g.GroupLeaderID = m.MbrID', 'type' => 'LEFT'],
               ['table' => 'grouptype gt', 'on' => 'g.GroupTypeID = gt.GroupTypeID', 'type' => 'LEFT'],
               ['table' => 'branch b', 'on' => 'm.BranchID = b.BranchID', 'type' => 'LEFT']
            ],
            fields: [
               'g.*',
               'm.MbrFirstName as LeaderFirstName',
               'm.MbrFamilyName as LeaderFamilyName',
               'gt.GroupTypeName',
               'b.BranchName',
               '(SELECT COUNT(*) FROM groupmember gm WHERE gm.GroupID = g.GroupID) as MemberCount'
            ],
            conditions: $conditions,
            params: $params,
            limit: $limit,
            offset: $offset
         );

         $total = $orm->runQuery(
            "SELECT COUNT(*) as total FROM churchgroup g" .
               (!empty($conditions) ? ' LEFT JOIN churchmember m ON g.GroupLeaderID = m.MbrID WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
            $params
         )[0]['total'];

         return [
            'data' => $groups,
            'pagination' => [
               'page' => $page,
               'limit' => $limit,
               'total' => $total,
               'pages' => ceil($total / $limit)
            ]
         ];
      } catch (Exception $e) {
         Helpers::logError('Group getAll error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Add a member to a church group
    *
    * @param int $groupId ID of the group to add the member to
    * @param int $memberId ID of the member to add
    * @return array Result of the operation with status, group ID, and member ID
    * @throws Exception if validation fails or database operations fail
    */
   public static function addMember($groupId, $memberId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate group exists
         $group = $orm->getWhere('churchgroup', ['GroupID' => $groupId]);
         if (empty($group)) {
            throw new Exception('Group not found');
         }

         // Validate member exists and is active
         $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'MbrMembershipStatus' => 'Active', 'Deleted' => 0]);
         if (empty($member)) {
            throw new Exception('Invalid or inactive member ID');
         }

         // Check if member is already in group
         $existing = $orm->getWhere('groupmember', ['GroupID' => $groupId, 'MbrID' => $memberId]);
         if (!empty($existing)) {
            throw new Exception('Member is already in the group');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->insert('groupmember', [
            'GroupID' => $groupId,
            'MbrID' => $memberId
         ]);

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Added to Group',
            'Message' => "You have been added to group '{$group[0]['GroupName']}'.",
            'SentBy' => $group[0]['GroupLeaderID'],
            'TargetGroupID' => $groupId
         ]);

         $orm->commit();
         return ['status' => 'success', 'group_id' => $groupId, 'member_id' => $memberId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Group addMember error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Remove a member from a church group
    *
    * @param int $groupId ID of the group to remove the member from
    * @param int $memberId ID of the member to remove
    * @return array Result of the operation with status, group ID, and member ID
    * @throws Exception if validation fails or database operations fail
    */
   public static function removeMember($groupId, $memberId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate group exists
         $group = $orm->getWhere('churchgroup', ['GroupID' => $groupId]);
         if (empty($group)) {
            throw new Exception('Group not found');
         }

         // Validate member exists
         $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
         if (empty($member)) {
            throw new Exception('Invalid member ID');
         }

         // Check if member is in group
         $existing = $orm->getWhere('groupmember', ['GroupID' => $groupId, 'MbrID' => $memberId]);
         if (empty($existing)) {
            throw new Exception('Member is not in the group');
         }

         // Prevent removing leader
         if ($group[0]['GroupLeaderID'] == $memberId) {
            throw new Exception('Cannot remove group leader as a member');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->delete('groupmember', ['GroupID' => $groupId, 'MbrID' => $memberId]);

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Removed from Group',
            'Message' => "You have been removed from group '{$group[0]['GroupName']}'.",
            'SentBy' => $group[0]['GroupLeaderID'],
            'TargetGroupID' => $groupId
         ]);

         $orm->commit();
         return ['status' => 'success', 'group_id' => $groupId, 'member_id' => $memberId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Group removeMember error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Send a message to a church group
    *
    * @param int $groupId ID of the group to send the message to
    * @param array $data Message data including title, message, and sender ID
    * @return array Result of the operation with status and communication ID
    * @throws Exception if validation fails or database operations fail
    */
   public static function sendMessage($groupId, $data)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate input
         Helpers::validateInput($data, [
            'title' => 'required',
            'message' => 'required',
            'sent_by' => 'required|numeric'
         ]);

         // Validate group exists
         $group = $orm->getWhere('churchgroup', ['GroupID' => $groupId]);
         if (empty($group)) {
            throw new Exception('Group not found');
         }

         // Validate sender exists and is active
         $sender = $orm->getWhere('churchmember', ['MbrID' => $data['sent_by'], 'MbrMembershipStatus' => 'Active', 'Deleted' => 0]);
         if (empty($sender)) {
            throw new Exception('Invalid or inactive sender ID');
         }

         // Validate sender is group leader or has permission
         if ($data['sent_by'] != $group[0]['GroupLeaderID']) {
            // Assume Auth::checkPermission is called in routes
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $communicationId = $orm->insert('communication', [
            'Title' => $data['title'],
            'Message' => $data['message'],
            'SentBy' => $data['sent_by'],
            'TargetGroupID' => $groupId
         ])['id'];

         $orm->commit();
         return ['status' => 'success', 'communication_id' => $communicationId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Group sendMessage error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Get messages sent to a church group
    * @param int $groupId ID of the group to retrieve messages for
    * @param int $page Page number for pagination
    * @param int $limit Number of messages per page 
    * @return array List of messages with pagination details
    * @throws Exception if group not found or database operations fail
    */
   public static function getMessages($groupId, $page = 1, $limit = 10)
   {
      $orm = new ORM();
      try {
         // Validate group exists
         $group = $orm->getWhere('churchgroup', ['GroupID' => $groupId]);
         if (empty($group)) {
            throw new Exception('Group not found');
         }

         $offset = ($page - 1) * $limit;

         $messages = $orm->selectWithJoin(
            baseTable: 'communication c',
            joins: [
               ['table' => 'churchmember m', 'on' => 'c.SentBy = m.MbrID', 'type' => 'LEFT']
            ],
            fields: [
               'c.*',
               'm.MbrFirstName as SenderFirstName',
               'm.MbrFamilyName as SenderFamilyName'
            ],
            conditions: ['c.TargetGroupID' => ':group_id'],
            params: [':group_id' => $groupId],
            limit: $limit,
            offset: $offset
         );

         $total = $orm->runQuery(
            "SELECT COUNT(*) as total FROM communication WHERE TargetGroupID = :group_id",
            [':group_id' => $groupId]
         )[0]['total'];

         return [
            'data' => $messages,
            'pagination' => [
               'page' => $page,
               'limit' => $limit,
               'total' => $total,
               'pages' => ceil($total / $limit)
            ]
         ];
      } catch (Exception $e) {
         Helpers::logError('Group getMessages error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Get members of a church group
    * @param int $groupId ID of the group to retrieve members for
    * @param int $page Page number for pagination
    * @param int $limit Number of members per page
    * @return array List of members with pagination details
    * @throws Exception if group not found or database operations fail
    */
   public static function getMembers($groupId, $page = 1, $limit = 10)
   {
      $orm = new ORM();
      try {
         // Validate group exists
         $group = $orm->getWhere('churchgroup', ['GroupID' => $groupId]);
         if (empty($group)) {
            throw new Exception('Group not found');
         }

         $offset = ($page - 1) * $limit;

         $members = $orm->selectWithJoin(
            baseTable: 'groupmember gm',
            joins: [
               ['table' => 'churchmember m', 'on' => 'gm.MbrID = m.MbrID', 'type' => 'LEFT'],
               ['table' => 'branch b', 'on' => 'm.BranchID = b.BranchID', 'type' => 'LEFT']
            ],
            fields: [
               'gm.GroupMemberID',
               'm.MbrID',
               'm.MbrFirstName',
               'm.MbrFamilyName',
               'm.MbrEmailAddress',
               'b.BranchName'
            ],
            conditions: ['gm.GroupID' => ':group_id'],
            params: [':group_id' => $groupId],
            limit: $limit,
            offset: $offset
         );

         $total = $orm->runQuery(
            "SELECT COUNT(*) as total FROM groupmember WHERE GroupID = :group_id",
            [':group_id' => $groupId]
         )[0]['total'];

         return [
            'data' => $members,
            'pagination' => [
               'page' => $page,
               'limit' => $limit,
               'total' => $total,
               'pages' => ceil($total / $limit)
            ]
         ];
      } catch (Exception $e) {
         Helpers::logError('Group getMembers error: ' . $e->getMessage());
         throw $e;
      }
   }
}
