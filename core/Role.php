<?php

/**
 * Role Management – Role-Based Access Control (RBAC) Core
 *
 * Manages church roles (e.g., Pastor, Treasurer, Admin, Member) and their permissions.
 * This is the central pillar of the entire authorization system.
 *
 * Features:
 * - Full CRUD for roles
 * - Bulk permission assignment (replace all)
 * - Role-to-member assignment
 * - Retrieval with full permission list
 * - Deletion protection when members are assigned
 * - Comprehensive audit logging
 *
 * All operations are atomic, secure, and strictly typed.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class Role
{
   /**
    * Create a new church role
    *
    * Role names must be unique system-wide.
    *
    * @param array{name:string, description?:string} $data Role payload
    * @return array{status:string, role_id:int} Success response with created role ID
    * @throws Exception On validation failure or database error
    */
   public static function create(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'name'        => 'required|max:100',
         'description' => 'max:500|nullable'
      ]);

      $name = trim($data['name']);

      if (!empty($orm->getWhere('churchrole', ['RoleName' => $name]))) {
         Helpers::sendFeedback('Role name already exists', 400);
      }

      $roleId = $orm->insert('churchrole', [
         'RoleName'    => $name,
         'Description' => $data['description'] ?? null
      ])['id'];

      Helpers::logError("New role created: ID $roleId – $name");
      return ['status' => 'success', 'role_id' => $roleId];
   }

   /**
    * Update an existing role
    *
    * Only name and description can be modified. Permissions are managed separately.
    *
    * @param int $roleId The primary key of the role
    * @param array{name?:string, description?:string} $data Updated fields
    * @return array{status:string, role_id:int} Success response
    */
   public static function update(int $roleId, array $data): array
   {
      $orm = new ORM();

      $role = $orm->getWhere('churchrole', ['RoleID' => $roleId]);
      if (empty($role)) {
         Helpers::sendFeedback('Role not found', 404);
      }

      $update = [];

      if (!empty($data['name'])) {
         $newName = trim($data['name']);
         if (!empty($orm->getWhere('churchrole', ['RoleName' => $newName, 'RoleID <>' => $roleId]))) {
            Helpers::sendFeedback('Role name already exists', 400);
         }
         $update['RoleName'] = $newName;
      }

      if (isset($data['description'])) {
         $update['Description'] = $data['description'];
      }

      if (!empty($update)) {
         $orm->update('churchrole', $update, ['RoleID' => $roleId]);
         Helpers::logError("Role updated: ID $roleId");
      }

      return ['status' => 'success', 'role_id' => $roleId];
   }

   /**
    * Delete a role
    *
    * Deletion is blocked if any member is currently assigned this role.
    *
    * @param int $roleId The primary key of the role to delete
    * @return array{status:string} Success response
    */
   public static function delete(int $roleId): array
   {
      $orm = new ORM();

      $role = $orm->getWhere('churchrole', ['RoleID' => $roleId]);
      if (empty($role)) {
         Helpers::sendFeedback('Role not found', 404);
      }

      // Check if role is assigned to any member
      $assigned = $orm->getWhere('churchmember', ['ChurchRoleID' => $roleId]);
      if (!empty($assigned)) {
         Helpers::sendFeedback('Cannot delete role assigned to one or more members', 400);
      }

      $orm->beginTransaction();
      try {
         $orm->delete('rolepermission', ['ChurchRoleID' => $roleId]);  // Clean up permissions
         $orm->delete('churchrole', ['RoleID' => $roleId]);
         $orm->commit();

         Helpers::logError("Role deleted: ID $roleId – {$role[0]['RoleName']}");
      } catch (Exception $e) {
         $orm->rollBack();
         throw $e;
      }

      return ['status' => 'success'];
   }

   /**
    * Assign multiple permissions to a role (replaces all existing)
    *
    * This is the canonical way to set role permissions.
    * All previous permissions are removed and replaced atomically.
    *
    * @param int   $roleId         The target role ID
    * @param array $permissionIds  Array of valid PermissionID values
    * @return array{status:string} Success response
    */
   public static function assignPermissions(int $roleId, array $permissionIds): array
   {
      $orm = new ORM();

      // Validate role exists
      if (empty($orm->getWhere('churchrole', ['RoleID' => $roleId]))) {
         Helpers::sendFeedback('Role not found', 404);
      }

      // Validate all permission IDs exist
      foreach ($permissionIds as $permId) {
         if (!is_numeric($permId) || empty($orm->getWhere('permission', ['PermissionID' => (int)$permId]))) {
            Helpers::sendFeedback("Invalid permission ID: $permId", 400);
         }
      }

      $orm->beginTransaction();
      try {
         // Remove all existing permissions
         $orm->delete('rolepermission', ['ChurchRoleID' => $roleId]);

         // Insert new ones
         foreach ($permissionIds as $permId) {
            $orm->insert('rolepermission', [
               'ChurchRoleID'  => $roleId,
               'PermissionID'  => (int)$permId
            ]);
         }

         $orm->commit();
         Helpers::logError("Permissions updated for Role ID $roleId");
      } catch (Exception $e) {
         $orm->rollBack();
         throw $e;
      }

      return ['status' => 'success'];
   }

   /**
    * Retrieve a single role with its complete permission set
    *
    * @param int $roleId The role ID
    * @return array Full role data including permissions array
    */
   public static function get(int $roleId): array
   {
      $orm = new ORM();

      $result = $orm->selectWithJoin(
         baseTable: 'churchrole r',
         joins: [
            ['table' => 'rolepermission rp', 'on' => 'r.RoleID = rp.ChurchRoleID', 'type' => 'LEFT'],
            ['table' => 'permission p',      'on' => 'rp.PermissionID = p.PermissionID', 'type' => 'LEFT']
         ],
         fields: ['r.*', 'p.PermissionID', 'p.PermissionName'],
         conditions: ['r.RoleID' => ':id'],
         params: [':id' => $roleId]
      );

      if (empty($result)) {
         Helpers::sendFeedback('Role not found', 404);
      }

      $role = $result[0];
      $permissions = [];

      foreach ($result as $row) {
         if ($row['PermissionID']) {
            $permissions[] = [
               'permission_id'   => (int)$row['PermissionID'],
               'permission_name' => $row['PermissionName']
            ];
         }
      }

      unset($role['PermissionID'], $role['PermissionName']);
      $role['permissions'] = $permissions;

      return $role;
   }

   /**
    * Retrieve all roles with their permissions
    *
    * @return array{data:array} List of all roles with nested permissions
    */
   public static function getAll(): array
   {
      $orm = new ORM();

      $rows = $orm->selectWithJoin(
         baseTable: 'churchrole r',
         joins: [
            ['table' => 'rolepermission rp', 'on' => 'r.RoleID = rp.ChurchRoleID', 'type' => 'LEFT'],
            ['table' => 'permission p',      'on' => 'rp.PermissionID = p.PermissionID', 'type' => 'LEFT']
         ],
         fields: ['r.RoleID', 'r.RoleName', 'r.Description', 'p.PermissionID', 'p.PermissionName'],
         orderBy: ['r.RoleName' => 'ASC']
      );

      $roles = [];
      foreach ($rows as $row) {
         $id = $row['RoleID'];
         if (!isset($roles[$id])) {
            $roles[$id] = [
               'role_id'     => $id,
               'role_name'   => $row['RoleName'],
               'description' => $row['Description'],
               'permissions' => []
            ];
         }
         if ($row['PermissionID']) {
            $roles[$id]['permissions'][] = [
               'permission_id'   => (int)$row['PermissionID'],
               'permission_name' => $row['PermissionName']
            ];
         }
      }

      return ['data' => array_values($roles)];
   }

   /**
    * Assign a role to a church member
    *
    * Overwrites any existing role assignment for the member.
    *
    * @param int $memberId Member ID (MbrID)
    * @param int $roleId   Role ID to assign
    * @return array{status:string} Success response
    */
   public static function assignToMember(int $memberId, int $roleId): array
   {
      $orm = new ORM();

      // Validate member
      $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
      if (empty($member)) {
         Helpers::sendFeedback('Member not found', 404);
      }

      // Validate role
      if (empty($orm->getWhere('churchrole', ['RoleID' => $roleId]))) {
         Helpers::sendFeedback('Role not found', 404);
      }

      $orm->update('churchmember', ['ChurchRoleID' => $roleId], ['MbrID' => $memberId]);

      Helpers::logError("Role $roleId assigned to Member ID $memberId");
      return ['status' => 'success'];
   }
}