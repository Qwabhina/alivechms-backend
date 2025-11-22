<?php

/**
 * Group Management Class
 *
 * Manages church groups (e.g., choir, youth, ushering) including creation,
 * updates, membership, group types, and internal messaging.
 *
 * @package AliveChMS\Core
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-20
 */

declare(strict_types=1);

class Group
{
   /**
    * Create a new church group
    *
    * @param array $data Group creation data
    * @return array Success response with group_id
    * @throws Exception On validation or database failure
    */
   public static function create(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'name'        => 'required|max:100',
         'leader_id'   => 'required|numeric',
         'type_id'     => 'required|numeric',
         'description' => 'max:500|nullable',
      ]);

      $leaderId = (int)$data['leader_id'];
      $typeId   = (int)$data['type_id'];

      // Validate leader exists and is active
      $leader = $orm->getWhere('churchmember', [
         'MbrID'              => $leaderId,
         'MbrMembershipStatus' => 'Active',
         'Deleted'            => 0
      ]);

      if (empty($leader)) {
         Helpers::sendFeedback('Invalid or inactive group leader', 400);
      }

      // Validate group type exists
      $type = $orm->getWhere('grouptype', ['GroupTypeID' => $typeId]);
      if (empty($type)) {
         Helpers::sendFeedback('Invalid group type', 400);
      }

      // Check for duplicate group name
      $existing = $orm->getWhere('churchgroup', ['GroupName' => $data['name']]);
      if (!empty($existing)) {
         Helpers::sendFeedback('Group name already exists', 400);
      }

      $orm->beginTransaction();
      try {
         $groupId = $orm->insert('churchgroup', [
            'GroupName'        => $data['name'],
            'GroupLeaderID'    => $leaderId,
            'GroupDescription' => $data['description'] ?? null,
            'GroupTypeID'      => $typeId,
            'CreatedAt'        => date('Y-m-d H:i:s')
         ])['id'];

         // Automatically add leader as member
         $orm->insert('groupmember', [
            'GroupID'   => $groupId,
            'MbrID'     => $leaderId,
            'JoinedAt'  => date('Y-m-d H:i:s')
         ]);

         $orm->commit();

         Helpers::logError("New group created: GroupID $groupId - {$data['name']}");

         return ['status' => 'success', 'group_id' => $groupId];
      } catch (Exception $e) {
            $orm->rollBack();
         Helpers::logError("Group creation failed: " . $e->getMessage());
         throw $e;
      }
   }

   /**
    * Update group details
    *
    * @param int   $groupId Group ID
    * @param array $data    Updated data
    * @return array Success response
    */
   public static function update(int $groupId, array $data): array
   {
      $orm = new ORM();

      $group = $orm->getWhere('churchgroup', ['GroupID' => $groupId]);
      if (empty($group)) {
         Helpers::sendFeedback('Group not found', 404);
      }

      $updateData = [];

      if (!empty($data['name'])) {
         Helpers::validateInput($data, ['name' => 'required|max:100']);
         $existing = $orm->getWhere('churchgroup', ['GroupName' => $data['name'], 'GroupID!=' => $groupId]);
         if (!empty($existing)) {
            Helpers::sendFeedback('Group name already exists', 400);
         }
         $updateData['GroupName'] = $data['name'];
      }

      if (!empty($data['leader_id'])) {
         $leader = $orm->getWhere('churchmember', [
            'MbrID'              => (int)$data['leader_id'],
            'MbrMembershipStatus' => 'Active',
            'Deleted'            => 0
         ]);
         if (empty($leader)) {
            Helpers::sendFeedback('Invalid or inactive leader', 400);
         }
         $updateData['GroupLeaderID'] = (int)$data['leader_id'];
      }

      if (!empty($data['type_id'])) {
         $type = $orm->getWhere('grouptype', ['GroupTypeID' => (int)$data['type_id']]);
         if (empty($type)) {
            Helpers::sendFeedback('Invalid group type', 400);
         }
         $updateData['GroupTypeID'] = (int)$data['type_id'];
      }

      if (!empty($data['description'])) {
         $updateData['GroupDescription'] = $data['description'];
      }

      if (empty($updateData)) {
         return ['status' => 'success', 'group_id' => $groupId];
      }

      $orm->update('churchgroup', $updateData, ['GroupID' => $groupId]);

      return ['status' => 'success', 'group_id' => $groupId];
   }

   /**
    * Delete a group (only if no members or communications)
    *
    * @param int $groupId Group ID
    * @return array Success response
    */
   public static function delete(int $groupId): array
   {
      $orm = new ORM();

      $group = $orm->getWhere('churchgroup', ['GroupID' => $groupId]);
      if (empty($group)) {
         Helpers::sendFeedback('Group not found', 404);
      }

      $members = $orm->getWhere('groupmember', ['GroupID' => $groupId]);
      $messages = $orm->getWhere('communication', ['TargetGroupID' => $groupId]);

      if (!empty($members) || !empty($messages)) {
         Helpers::sendFeedback('Cannot delete group with members or messages', 400);
      }

      $orm->delete('churchgroup', ['GroupID' => $groupId]);

      return ['status' => 'success'];
   }

   /**
    * Retrieve a single group with details
    *
    * @param int $groupId Group ID
    * @return array Group data
    */
   public static function get(int $groupId): array
   {
      $orm = new ORM();

      $groups = $orm->selectWithJoin(
            baseTable: 'churchgroup g',
            joins: [
            ['table' => 'churchmember l', 'on' => 'g.GroupLeaderID = l.MbrID'],
            ['table' => 'grouptype t', 'on' => 'g.GroupTypeID = t.GroupTypeID']
            ],
            fields: [
            'g.*',
            'l.MbrFirstName AS LeaderFirstName',
            'l.MbrFamilyName AS LeaderFamilyName',
            't.GroupTypeName',
            '(SELECT COUNT(*) FROM groupmember gm WHERE gm.GroupID = g.GroupID) AS MemberCount'
            ],
            conditions: ['g.GroupID' => ':id'],
            params: [':id' => $groupId]
      );

      if (empty($groups)) {
         Helpers::sendFeedback('Group not found', 404);
      }

      return $groups[0];
   }

   /**
    * Retrieve paginated list of groups
    *
    * @param int   $page    Page number
    * @param int   $limit   Items per page
    * @param array $filters Optional filters
    * @return array Paginated result
    */
   public static function getAll(int $page = 1, int $limit = 10, array $filters = []): array
   {
      $orm = new ORM();
      $offset = ($page - 1) * $limit;

      $conditions = [];
      $params     = [];

      if (!empty($filters['type_id'])) {
         $conditions['g.GroupTypeID'] = ':type_id';
         $params[':type_id'] = (int)$filters['type_id'];
      }
      if (!empty($filters['branch_id'])) {
         $conditions['l.BranchID'] = ':branch_id';
         $params[':branch_id'] = (int)$filters['branch_id'];
      }
      if (!empty($filters['name'])) {
            $conditions['g.GroupName LIKE'] = ':name';
         $params[':name'] = '%' . $filters['name'] . '%';
      }

      $groups = $orm->selectWithJoin(
            baseTable: 'churchgroup g',
            joins: [
            ['table' => 'churchmember l', 'on' => 'g.GroupLeaderID = l.MbrID'],
            ['table' => 'grouptype t', 'on' => 'g.GroupTypeID = t.GroupTypeID']
            ],
            fields: [
            'g.GroupID',
            'g.GroupName',
            'g.GroupDescription',
            'g.GroupLeaderID',
            'l.MbrFirstName AS LeaderFirstName',
            'l.MbrFamilyName AS LeaderFamilyName',
            't.GroupTypeName',
            '(SELECT COUNT(*) FROM groupmember gm WHERE gm.GroupID = g.GroupID) AS MemberCount'
            ],
            conditions: $conditions,
            params: $params,
         orderBy: ['g.GroupName' => 'ASC'],
            limit: $limit,
            offset: $offset
      );

      $total = $orm->runQuery(
         "SELECT COUNT(*) AS total FROM churchgroup g" .
            (!empty($conditions) ? ' LEFT JOIN churchmember l ON g.GroupLeaderID = l.MbrID WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
            $params
      )[0]['total'];

      return [
            'data' => $groups,
            'pagination' => [
            'page'  => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => (int)ceil($total / $limit)
            ]
      ];
   }

   /**
    * Add member to group
    *
    * @param int $groupId  Group ID
    * @param int $memberId Member ID
    * @return array Success response
    */
   public static function addMember(int $groupId, int $memberId): array
   {
      $orm = new ORM();

      $group = $orm->getWhere('churchgroup', ['GroupID' => $groupId]);
      if (empty($group)) {
         Helpers::sendFeedback('Group not found', 404);
      }

      $member = $orm->getWhere('churchmember', [
         'MbrID'              => $memberId,
         'MbrMembershipStatus' => 'Active',
         'Deleted'            => 0
      ]);
      if (empty($member)) {
         Helpers::sendFeedback('Invalid or inactive member', 400);
      }

      $existing = $orm->getWhere('groupmember', ['GroupID' => $groupId, 'MbrID' => $memberId]);
      if (!empty($existing)) {
         Helpers::sendFeedback('Member already in group', 400);
      }

      $orm->insert('groupmember', [
         'GroupID'  => $groupId,
         'MbrID'    => $memberId,
         'JoinedAt' => date('Y-m-d H:i:s')
      ]);

      return ['status' => 'success', 'group_id' => $groupId, 'member_id' => $memberId];
   }

   /**
    * Remove member from group
    *
    * @param int $groupId  Group ID
    * @param int $memberId Member ID
    * @return array Success response
    */
   public static function removeMember(int $groupId, int $memberId): array
   {
      $orm = new ORM();

      $group = $orm->getWhere('churchgroup', ['GroupID' => $groupId]);
      if (empty($group)) {
         Helpers::sendFeedback('Group not found', 404);
      }

      if ($memberId === (int)$group[0]['GroupLeaderID']) {
         Helpers::sendFeedback('Cannot remove group leader', 400);
      }

      $existing = $orm->getWhere('groupmember', ['GroupID' => $groupId, 'MbrID' => $memberId]);
      if (empty($existing)) {
         Helpers::sendFeedback('Member not in group', 400);
      }

      $orm->delete('groupmember', ['GroupID' => $groupId, 'MbrID' => $memberId]);

      return ['status' => 'success', 'group_id' => $groupId, 'member_id' => $memberId];
   }

   /**
    * Retrieve group members with pagination
    *
    * @param int $groupId Group ID
    * @param int $page    Page number
    * @param int $limit   Items per page
    * @return array Paginated members
    */
   public static function getMembers(int $groupId, int $page = 1, int $limit = 10): array
   {
      $orm = new ORM();
      $offset = ($page - 1) * $limit;

      $members = $orm->selectWithJoin(
            baseTable: 'groupmember gm',
         joins: [['table' => 'churchmember m', 'on' => 'gm.MbrID = m.MbrID']],
         fields: [
            'm.MbrID',
            'm.MbrFirstName',
            'm.MbrFamilyName',
            'm.MbrEmailAddress',
            'gm.JoinedAt'
            ],
            conditions: ['gm.GroupID' => ':group_id'],
            params: [':group_id' => $groupId],
         orderBy: ['gm.JoinedAt' => 'DESC'],
            limit: $limit,
            offset: $offset
      );

      $total = $orm->runQuery("SELECT COUNT(*) AS total FROM groupmember WHERE GroupID = :id", [':id' => $groupId])[0]['total'];

      return [
            'data' => $members,
            'pagination' => [
            'page'  => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => (int)ceil($total / $limit)
            ]
      ];
   }
}