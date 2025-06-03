<?php

/** Role Class
 * This class handles operations related to roles in the church management system.
 * It includes methods for creating, updating, deleting, retrieving a single role, and listing all roles with pagination.
 * @package Role
 * @version 1.0
 */
class Role
{
   /**
    * Creates a new role.
    * Validates input, checks for duplicates, and inserts into the database.
    * @param array $data The role data to create.
    * @return array The created role ID and status.
    * @throws Exception If validation fails or database operations fail.
    */
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
   /**
    * Updates an existing role.
    * Validates input, checks for duplicates, and updates the database.
    * @param int $roleId The ID of the role to update.
    * @param array $data The updated role data.
    * @return array The updated role ID and status.
    * @throws Exception If validation fails or database operations fail.
    */
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
   /**
    * Deletes a role.
    * Validates that the role exists, checks if it is assigned to any members or has permissions,
    * and deletes it from the database.
    * @param int $roleId The ID of the role to delete.
    * @return array The status of the deletion.
    * @throws Exception If validation fails or database operations fail.
    */
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
   /**
    * Retrieves a single role by ID.
    * Fetches the role details along with its permissions.
    * @param int $roleId The ID of the role to retrieve.
    * @return array The role details including permissions.
    * @throws Exception If the role is not found or database operations fail.
    */
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
   /**
    * Lists all roles with pagination and optional filters.
    * Supports filtering by role name.
    * @param int $page The page number for pagination.
    * @param int $limit The number of roles per page.
    * @param array $filters Optional filters for the role name.
    * @return array The list of roles with pagination details.
    * @throws Exception If database operations fail.
    */
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
   /**
    * Assigns a permission to a role.
    * Validates that the role and permission exist, checks if the permission is already assigned,
    * and inserts the assignment into the database.
    * @param int $roleId The ID of the role to assign the permission to.
    * @param int $permissionId The ID of the permission to assign.
    * @return array The status of the assignment.
    * @throws Exception If validation fails or database operations fail.
    */
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
   /**
    * Removes a permission from a role.
    * Validates that the role and permission exist, checks if the permission is assigned,
    * and deletes the assignment from the database. 
    * @param int $roleId The ID of the role to remove the permission from.
    * @param int $permissionId The ID of the permission to remove.
    * @return array The status of the removal.
    * @throws Exception If validation fails or database operations fail.
    */
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
   /**
    * Assigns a role to a church member.
    * Validates that the member exists and is active, checks if the role exists,
    * and updates the member's role in the database.
    * @param int $memberId The ID of the member to assign the role to.
    * @param int $roleId The ID of the role to assign.
    * @return array The status of the assignment.
    * @throws Exception If validation fails or database operations fail.
    */
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
   /**
    * Removes a role from a church member.
    * Validates that the member exists and has a role assigned,
    * retrieves the role details, and updates the member's role in the database.
    * @param int $memberId The ID of the member to remove the role from.
    * @return array The status of the removal.
    * @throws Exception If validation fails or database operations fail.
    */
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
