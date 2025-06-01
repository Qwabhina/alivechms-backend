<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ORM.php';
require_once __DIR__ . '/Helpers.php';

class Role
{
   public static function create($data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'name' => 'required|alphanumeric_underscore'
         ]);

         // Check for duplicate role name
         $existing = $orm->getWhere('role', ['RoleName' => $data['name']]);
         if (!empty($existing)) {
            throw new Exception('Role name already exists');
         }

         $roleId = $orm->insert('role', [
            'RoleName' => $data['name']
         ])['id'];

         // Create notification
         $orm->insert('communication', [
            'Title' => 'New Role Created',
            'Message' => "Role '{$data['name']}' has been created.",
            'SentBy' => $data['created_by'] ?? 0,
            'TargetGroupID' => null
         ]);

         return ['status' => 'success', 'role_id' => $roleId];
      } catch (Exception $e) {
         Helpers::logError('Role create error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function update($roleId, $data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'name' => 'required|alphanumeric_underscore'
         ]);

         // Validate role exists
         $role = $orm->getWhere('role', ['RoleID' => $roleId]);
         if (empty($role)) {
            throw new Exception('Role not found');
         }

         // Check for duplicate role name
         $existing = $orm->getWhere('role', ['RoleName' => $data['name'], 'RoleID != ' => $roleId]);
         if (!empty($existing)) {
            throw new Exception('Role name already exists');
         }

         $orm->update('role', [
            'RoleName' => $data['name']
         ], ['RoleID' => $roleId]);

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Role Updated',
            'Message' => "Role '{$data['name']}' has been updated.",
            'SentBy' => $data['created_by'] ?? 0,
            'TargetGroupID' => null
         ]);

         return ['status' => 'success', 'role_id' => $roleId];
      } catch (Exception $e) {
         Helpers::logError('Role update error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function delete($roleId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate role exists
         $role = $orm->getWhere('role', ['RoleID' => $roleId]);
         if (empty($role)) {
            throw new Exception('Role not found');
         }

         // Check if role is assigned to members
         $members = $orm->getWhere('churchmember', ['RoleID' => $roleId]);
         if (!empty($members)) {
            throw new Exception('Cannot delete role assigned to members');
         }

         // Check if role has permissions
         $permissions = $orm->getWhere('role_permission', ['RoleID' => $roleId]);
         if (!empty($permissions)) {
            throw new Exception('Cannot delete role with assigned permissions');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->delete('role', ['RoleID' => $roleId]);

         $orm->commit();
         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Role delete error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function get($roleId)
   {
      $orm = new ORM();
      try {
         $role = $orm->getWhere('role', ['RoleID' => $roleId])[0] ?? null;
         if (!$role) {
            throw new Exception('Role not found');
         }

         // Get permissions
         $permissions = $orm->selectWithJoin(
            baseTable: 'role_permission rp',
            joins: [
               ['table' => 'permission p', 'on' => 'rp.PermissionID = p.PermissionID', 'type' => 'LEFT']
            ],
            fields: ['p.PermissionID', 'p.PermissionName'],
            conditions: ['rp.RoleID' => ':role_id'],
            params: [':role_id' => $roleId]
         );

         $role['Permissions'] = $permissions;
         return $role;
      } catch (Exception $e) {
         Helpers::logError('Role get error: ' . $e->getMessage());
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

         if (!empty($filters['name']) && is_string($filters['name']) && trim($filters['name']) !== '') {
            $conditions['RoleName LIKE'] = ':name';
            $params[':name'] = '%' . trim($filters['name']) . '%';
         }

         $roles = $orm->getWhere('role', $conditions, $params, $limit, $offset);

         foreach ($roles as &$role) {
            $permissions = $orm->selectWithJoin(
               baseTable: 'role_permission rp',
               joins: [
                  ['table' => 'permission p', 'on' => 'rp.PermissionID = p.PermissionID', 'type' => 'LEFT']
               ],
               fields: ['p.PermissionID', 'p.PermissionName'],
               conditions: ['rp.RoleID' => ':role_id'],
               params: [':role_id' => $role['RoleID']]
            );
            $role['Permissions'] = $permissions;
         }

         $total = $orm->runQuery(
            "SELECT COUNT(*) as total FROM role" .
               (!empty($conditions) ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
            $params
         )[0]['total'];

         return [
            'data' => $roles,
            'pagination' => [
               'page' => $page,
               'limit' => $limit,
               'total' => $total,
               'pages' => ceil($total / $limit)
            ]
         ];
      } catch (Exception $e) {
         Helpers::logError('Role getAll error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function assignPermission($roleId, $permissionId)
   {
      $orm = new ORM();
      try {
         // Validate role exists
         $role = $orm->getWhere('role', ['RoleID' => $roleId]);
         if (empty($role)) {
            throw new Exception('Role not found');
         }

         // Validate permission exists
         $permission = $orm->getWhere('permission', ['PermissionID' => $permissionId]);
         if (empty($permission)) {
            throw new Exception('Permission not found');
         }

         // Check if permission is already assigned
         $existing = $orm->getWhere('role_permission', ['RoleID' => $roleId, 'PermissionID' => $permissionId]);
         if (!empty($existing)) {
            throw new Exception('Permission already assigned to role');
         }

         $orm->insert('role_permission', [
            'RoleID' => $roleId,
            'PermissionID' => $permissionId
         ]);

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Permission Assigned to Role',
            'Message' => "Permission '{$permission[0]['PermissionName']}' assigned to role '{$role[0]['RoleName']}'.",
            'SentBy' => $data['created_by'] ?? 0,
            'TargetGroupID' => null
         ]);

         return ['status' => 'success', 'role_id' => $roleId, 'permission_id' => $permissionId];
      } catch (Exception $e) {
         Helpers::logError('Role assignPermission error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function removePermission($roleId, $permissionId)
   {
      $orm = new ORM();
      try {
         // Validate role exists
         $role = $orm->getWhere('role', ['RoleID' => $roleId]);
         if (empty($role)) {
            throw new Exception('Role not found');
         }

         // Validate permission exists
         $permission = $orm->getWhere('permission', ['PermissionID' => $permissionId]);
         if (empty($permission)) {
            throw new Exception('Permission not found');
         }

         // Check if permission is assigned
         $existing = $orm->getWhere('role_permission', ['RoleID' => $roleId, 'PermissionID' => $permissionId]);
         if (empty($existing)) {
            throw new Exception('Permission not assigned to role');
         }

         $orm->delete('role_permission', ['RoleID' => $roleId, 'PermissionID' => $permissionId]);

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Permission Removed from Role',
            'Message' => "Permission '{$permission[0]['PermissionName']}' removed from role '{$role[0]['RoleName']}'.",
            'SentBy' => $data['created_by'] ?? 0,
            'TargetGroupID' => null
         ]);

         return ['status' => 'success', 'role_id' => $roleId, 'permission_id' => $permissionId];
      } catch (Exception $e) {
         Helpers::logError('Role removePermission error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function assignToMember($memberId, $roleId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate member exists
         $member = $orm->getWhere('churchmember', [
            'MbrID' => $memberId,
            'MbrMembershipStatus' => 'Active',
            'Deleted' => 0
         ]);
         if (empty($member)) {
            throw new Exception('Invalid or inactive member');
         }

         // Validate role exists
         $role = $orm->getWhere('role', ['RoleID' => $roleId]);
         if (empty($role)) {
            throw new Exception('Role not found');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->update('churchmember', [
            'RoleID' => $roleId
         ], ['MbrID' => $memberId]);

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Role Assigned',
            'Message' => "You have been assigned the role '{$role[0]['RoleName']}'.",
            'SentBy' => $data['created_by'] ?? 0,
            'TargetGroupID' => null
         ]);

         $orm->commit();
         return ['status' => 'success', 'member_id' => $memberId, 'role_id' => $roleId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Role assignToMember error: ' . $e->getMessage());
         throw $e;
      }
   }

   public static function removeFromMember($memberId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate member exists
         $member = $orm->getWhere('churchmember', [
            'MbrID' => $memberId,
            'Deleted' => 0
         ]);
         if (empty($member)) {
            throw new Exception('Invalid member');
         }

         // Check if member has a role
         if (empty($member[0]['RoleID'])) {
            throw new Exception('Member has no role assigned');
         }

         $role = $orm->getWhere('role', ['RoleID' => $member[0]['RoleID']]);

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->update('churchmember', [
            'RoleID' => null
         ], ['MbrID' => $memberId]);

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Role Removed',
            'Message' => "Your role '{$role[0]['RoleName']}' has been removed.",
            'SentBy' => $data['created_by'] ?? 0,
            'TargetGroupID' => null
         ]);

         $orm->commit();
         return ['status' => 'success', 'member_id' => $memberId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Role removeFromMember error: ' . $e->getMessage());
         throw $e;
      }
   }
}
