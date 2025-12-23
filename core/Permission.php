<?php

/**
 * Permission Management
 *
 * Provides complete CRUD operations for system permissions.
 * Permissions are the atomic building blocks of the role-based access control (RBAC) system.
 *
 * Each permission represents a specific action (e.g., "view_members", "approve_expenses").
 * Permissions are assigned to roles via the rolepermission junction table.
 *
 * This class ensures:
 * - Unique permission names
 * - Prevention of deletion when in use by any role
 * - Full audit-ready responses
 * - Consistent, strict-typed, secure implementation
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class Permission
{
   /**
    * Create a new system permission
    *
    * The permission name must be unique across the entire system.
    * Typical format: lowercase_with_underscores (e.g., "manage_members", "view_financial_reports")
    *
    * @param array{name:string} $data Contains the permission name
    * @return array{status:string, permission_id:int} Success response with created ID
    * @throws Exception On validation failure or database error
    */
   public static function create(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'name' => 'required|max:100'
      ]);

      $name = trim($data['name']);

      // Enforce uniqueness
      if (!empty($orm->getWhere('permission', ['PermissionName' => $name]))) {
         Helpers::sendFeedback('Permission name already exists', 400);
      }

      $permissionId = $orm->insert('permission', [
         'PermissionName' => $name
      ])['id'];

      Helpers::logError("New permission created: ID $permissionId – $name");

      return [
         'status'       => 'success',
         'permission_id' => $permissionId
      ];
   }

   /**
    * Update an existing permission name
    *
    * Only the name can be updated. The ID remains immutable.
    * Uniqueness is enforced excluding the current record.
    *
    * @param int $permissionId The primary key of the permission to update
    * @param array{name:string} $data Contains the new name
    * @return array{status:string, permission_id:int} Success response
    */
   public static function update(int $permissionId, array $data): array
   {
      $orm = new ORM();

      $existing = $orm->getWhere('permission', ['PermissionID' => $permissionId]);
      if (empty($existing)) {
         Helpers::sendFeedback('Permission not found', 404);
      }

      if (empty($data['name'])) {
         return ['status' => 'success', 'permission_id' => $permissionId];
      }

      $newName = trim($data['name']);
      Helpers::validateInput(['name' => $newName], ['name' => 'required|max:100']);

      // Prevent duplicate names (excluding current record)
      $conflict = $orm->getWhere('permission', [
         'PermissionName'     => $newName,
         'PermissionID <>'    => $permissionId
      ]);

      if (!empty($conflict)) {
         Helpers::sendFeedback('Permission name already exists', 400);
      }

      $orm->update('permission', ['PermissionName' => $newName], ['PermissionID' => $permissionId]);

      Helpers::logError("Permission updated: ID $permissionId → $newName");

      return [
         'status'       => 'success',
         'permission_id' => $permissionId
      ];
   }

   /**
    * Delete a permission
    *
    * Deletion is blocked if the permission is currently assigned to any role.
    * This prevents accidental removal of active access rights.
    *
    * @param int $permissionId The primary key of the permission to delete
    * @return array{status:string} Success response
    */
   public static function delete(int $permissionId): array
   {
      $orm = new ORM();

      $permission = $orm->getWhere('permission', ['PermissionID' => $permissionId]);
      if (empty($permission)) {
         Helpers::sendFeedback('Permission not found', 404);
      }

      // Check if permission is in use
      $inUse = $orm->getWhere('rolepermission', ['PermissionID' => $permissionId]);
      if (!empty($inUse)) {
         Helpers::sendFeedback('Cannot delete permission assigned to one or more roles', 400);
      }

      $orm->delete('permission', ['PermissionID' => $permissionId]);

      Helpers::logError("Permission deleted: ID $permissionId – {$permission[0]['PermissionName']}");

      return ['status' => 'success'];
   }

   /**
    * Retrieve a single permission with its assigned roles
    *
    * Returns the permission details along with a list of roles that currently have this permission.
    *
    * @param int $permissionId The primary key of the permission
    * @return array Permission data with assigned roles
    */
   public static function get(int $permissionId): array
   {
      $orm = new ORM();

      $permission = $orm->getWhere('permission', ['PermissionID' => $permissionId]);
      if (empty($permission)) {
         Helpers::sendFeedback('Permission not found', 404);
      }

      $roles = $orm->selectWithJoin(
         baseTable: 'rolepermission rp',
         joins: [['table' => 'churchrole r', 'on' => 'rp.ChurchRoleID = r.RoleID']],
            fields: ['r.RoleID', 'r.RoleName'],
            conditions: ['rp.PermissionID' => ':permission_id'],
            params: [':permission_id' => $permissionId]
      );

      $result = $permission[0];
      $result['assigned_roles'] = $roles;

      return $result;
   }

   /**
    * Retrieve all permissions with pagination and optional filtering
    *
    * Supports filtering by partial permission name.
    * Each permission includes its currently assigned roles.
    *
    * @param int   $page    Current page number (1-based)
    * @param int   $limit   Number of records per page
    * @param array $filters Optional filters (e.g., ['name' => 'view'])
    * @return array{data:array, pagination:array} Paginated result with metadata
    */
   public static function getAll(int $page = 1, int $limit = 20, array $filters = []): array
   {
      $orm    = new ORM();
      $offset = ($page - 1) * $limit;

      $conditions = [];
      $params     = [];

      if (!empty($filters['name'])) {
            $conditions['PermissionName LIKE'] = ':name';
            $params[':name'] = '%' . trim($filters['name']) . '%';
      }

      $permissions = $orm->getWhere('permission', $conditions, $params, $limit, $offset);

      // Attach assigned roles to each permission
      foreach ($permissions as &$perm) {
            $roles = $orm->selectWithJoin(
            baseTable: 'rolepermission rp',
            joins: [['table' => 'churchrole r', 'on' => 'rp.ChurchRoleID = r.RoleID']],
            fields: ['r.RoleID', 'r.RoleName'],
            conditions: ['rp.PermissionID' => ':permission_id'],
            params: [':permission_id' => $perm['PermissionID']]
            );

         $perm['assigned_roles'] = $roles;
      }
      unset($perm);

      $total = $orm->runQuery(
         "SELECT COUNT(*) AS total FROM permission" .
            (!empty($conditions) ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
            $params
      )[0]['total'];

      return [
            'data' => $permissions,
            'pagination' => [
            'page'   => $page,
            'limit'  => $limit,
            'total'  => (int)$total,
            'pages'  => (int)ceil($total / $limit)
            ]
      ];
   }
}