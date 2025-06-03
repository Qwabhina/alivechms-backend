<?php

/**
 * Permission Class
 * This class handles operations related to permissions in the church management system.
 * It includes methods for creating, updating, deleting, retrieving a single permission, and listing all permissions with pagination.
 * @package Permission
 * @version 1.0
 */
class Permission
{
   /**
    * Creates a new permission.
    * Validates input, checks for duplicates, and inserts into the database.
    * @param array $data The permission data to create.
    * @return array The created permission ID and status.
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

         // Check for duplicate permission name
         $existing = $orm->getWhere('permission', ['PermissionName' => $data['name']]);
         if (!empty($existing)) {
            throw new Exception('Permission name already exists');
         }

         $permissionId = $orm->insert('permission', [
            'PermissionName' => $data['name']
         ])['id'];

         // Create notification
         $orm->insert('communication', [
            'Title' => 'New Permission Created',
            'Message' => "Permission '{$data['name']}' has been created.",
            'SentBy' => $data['created_by'] ?? 0,
            'TargetGroupID' => null
         ]);

         return ['status' => 'success', 'permission_id' => $permissionId];
      } catch (Exception $e) {
         Helpers::logError('Permission create error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Updates an existing permission.
    * Validates input, checks for duplicates, and updates the database.
    * @param int $permissionId The ID of the permission to update.
    * @param array $data The updated permission data.
    * @return array The updated permission ID and status.
    * @throws Exception If validation fails or database operations fail.
    */
   public static function update($permissionId, $data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'name' => 'required|alphanumeric_underscore'
         ]);

         // Validate permission exists
         $permission = $orm->getWhere('permission', ['PermissionID' => $permissionId]);
         if (empty($permission)) {
            throw new Exception('Permission not found');
         }

         // Check for duplicate permission name
         $existing = $orm->getWhere('permission', ['PermissionName' => $data['name'], 'PermissionID != ' => $permissionId]);
         if (!empty($existing)) {
            throw new Exception('Permission name already exists');
         }

         $orm->update('permission', [
            'PermissionName' => $data['name']
         ], ['PermissionID' => $permissionId]);

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Permission Updated',
            'Message' => "Permission '{$data['name']}' has been updated.",
            'SentBy' => $data['created_by'] ?? 0,
            'TargetGroupID' => null
         ]);

         return ['status' => 'success', 'permission_id' => $permissionId];
      } catch (Exception $e) {
         Helpers::logError('Permission update error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Deletes a permission.
    * Validates that the permission exists and is not assigned to any roles before deletion.
    * @param int $permissionId The ID of the permission to delete.
    * @return array The status of the deletion.
    * @throws Exception If validation fails or database operations fail.
    */
   public static function delete($permissionId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate permission exists
         $permission = $orm->getWhere('permission', ['PermissionID' => $permissionId]);
         if (empty($permission)) {
            throw new Exception('Permission not found');
         }

         // Check if permission is assigned to roles
         $roles = $orm->getWhere('role_permission', ['PermissionID' => $permissionId]);
         if (!empty($roles)) {
            throw new Exception('Cannot delete permission assigned to roles');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->delete('permission', ['PermissionID' => $permissionId]);

         $orm->commit();
         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->in_transaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Permission delete error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Retrieves a single permission by ID.
    * Includes assigned roles in the response.
    * @param int $permissionId The ID of the permission to retrieve.
    * @return array The permission data with assigned roles.
    * @throws Exception If permission not found or database operations fail.
    */
   public static function get($permissionId)
   {
      $orm = new ORM();
      try {
         $permission = $orm->getWhere('permission', ['PermissionID' => $permissionId])[0] ?? null;
         if (!$permission) {
            throw new Exception('Permission not found');
         }

         // Get assigned roles
         $roles = $orm->selectWithJoin(
            baseTable: 'role_permission rp',
            joins: [
               ['table' => 'role r', 'on' => 'rp.RoleID = r.RoleID', 'type' => 'LEFT']
            ],
            fields: ['r.RoleID', 'r.RoleName'],
            conditions: ['rp.PermissionID' => ':permission_id'],
            params: [':permission_id' => $permissionId]
         );

         $permission['Roles'] = $roles;
         return $permission;
      } catch (Exception $e) {
         Helpers::logError('Permission get error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Lists all permissions with pagination and optional filters.
    * Supports filtering by permission name.
    * @param int $page The page number for pagination.
    * @param int $limit The number of permissions per page.
    * @param array $filters Optional filters for the permission list.
    * @return array The list of permissions with pagination info.
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
            $conditions['PermissionName LIKE'] = ':name';
            $params[':name'] = '%' . trim($filters['name']) . '%';
         }

         $permissions = $orm->getWhere('permission', $conditions, $params, $limit, $offset);

         foreach ($permissions as &$permission) {
            $roles = $orm->selectWithJoin(
               baseTable: 'role_permission rp',
               joins: [
                  ['table' => 'role r', 'on' => 'rp.RoleID = r.RoleID', 'type' => 'LEFT']
               ],
               fields: ['r.RoleID', 'r.RoleName'],
               conditions: ['rp.PermissionID' => ':permission_id'],
               params: [':permission_id' => $permission['PermissionID']]
            );
            $permission['Roles'] = $roles;
         }

         $total = $orm->runQuery(
            "SELECT COUNT(*) as total FROM permission" .
               (!empty($conditions) ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
            $params
         )[0]['total'];

         return [
            'data' => $permissions,
            'pagination' => [
               'page' => $page,
               'limit' => $limit,
               'total' => $total,
               'pages' => ceil($total / $limit)
            ]
         ];
      } catch (Exception $e) {
         Helpers::logError('Permission getAll error: ' . $e->getMessage());
         throw $e;
      }
   }
}
