<?php

/**
 * Role & Permission Management Class
 *
 * Full CRUD for roles, permissions, and assignments.
 *
 * @package AliveChMS\Core
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

declare(strict_types=1);

class Role
{
   /**
    * Create a new role
    */
   public static function create(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'name'        => 'required|max:100',
         'description' => 'max:500|nullable'
      ]);

      $existing = $orm->getWhere('churchrole', ['RoleName' => $data['name']]);
      if (!empty($existing)) {
         Helpers::sendFeedback('Role name already exists', 400);
      }

      $roleId = $orm->insert('churchrole', [
         'RoleName'    => $data['name'],
         'Description' => $data['description'] ?? null
      ])['id'];

      return ['status' => 'success', 'role_id' => $roleId];
   }

   /**
    * Update role
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
         $existing = $orm->getWhere('churchrole', ['RoleName' => $data['name'], 'RoleID!=' => $roleId]);
         if (!empty($existing)) {
            Helpers::sendFeedback('Role name already exists', 400);
         }
         $update['RoleName'] = $data['name'];
      }
      if (isset($data['description'])) {
         $update['Description'] = $data['description'];
      }

      if (!empty($update)) {
         $orm->update('churchrole', $update, ['RoleID' => $roleId]);
      }

      return ['status' => 'success', 'role_id' => $roleId];
   }

   /**
    * Delete role (only if no members assigned)
    */
   public static function delete(int $roleId): array
   {
      $orm = new ORM();

      $role = $orm->getWhere('churchrole', ['RoleID' => $roleId]);
      if (empty($role)) {
         Helpers::sendFeedback('Role not found', 404);
      }

      $assigned = $orm->getWhere('churchmember', ['ChurchRoleID' => $roleId]);
      if (!empty($assigned)) {
         Helpers::sendFeedback('Cannot delete role assigned to members', 400);
      }

      $orm->delete('churchrole', ['RoleID' => $roleId]);

      return ['status' => 'success'];
   }

   /**
    * Assign permissions to role (bulk)
    */
   public static function assignPermissions(int $roleId, array $permissionIds)
   {
      $orm = new ORM();

      $role = $orm->getWhere('churchrole', ['RoleID' => $roleId]);
      if (empty($role)) {
         Helpers::sendFeedback('Role not found', 404);
      }

      $orm->beginTransaction();
      try {
         // Clear existing
         $orm->delete('rolepermission', ['RoleID' => $roleId]);

         foreach ($permissionIds as $permId) {
            if (!is_numeric($permId)) continue;

            $perm = $orm->getWhere('permission', ['PermissionID' => (int)$permId]);
            if (empty($perm)) {
               throw new Exception("Invalid permission ID: $permId");
            }

            $orm->insert('rolepermission', [
               'RoleID'       => $roleId,
               'PermissionID' => (int)$permId
            ]);
         }

         $orm->commit();
         return ['status' => 'success'];
      } catch (Exception $e) {
         $orm->rollBack();
         Helpers::sendFeedback($e->getMessage(), 400);
      }
   }

   /**
    * Get role with permissions
    */
   public static function get(int $roleId): array
   {
      $orm = new ORM();

      $roles = $orm->selectWithJoin(
         baseTable: 'churchrole r',
         joins: [
            ['table' => 'rolepermission rp', 'on' => 'r.RoleID = rp.RoleID', 'type' => 'LEFT'],
            ['table' => 'permission p', 'on' => 'rp.PermissionID = p.PermissionID', 'type' => 'LEFT']
         ],
         fields: ['r.*', 'p.PermissionID', 'p.PermissionName'],
         conditions: ['r.RoleID' => ':id'],
         params: [':id' => $roleId]
      );

      if (empty($roles)) {
         Helpers::sendFeedback('Role not found', 404);
      }

      $role = $roles[0];
      $permissions = [];
      foreach ($roles as $row) {
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
    * Get all roles with permissions
    */
   public static function getAll(): array
   {
      $orm = new ORM();

      $roles = $orm->selectWithJoin(
         baseTable: 'churchrole r',
         joins: [
            ['table' => 'rolepermission rp', 'on' => 'r.RoleID = rp.RoleID', 'type' => 'LEFT'],
            ['table' => 'permission p', 'on' => 'rp.PermissionID = p.PermissionID', 'type' => 'LEFT']
         ],
         fields: ['r.RoleID', 'r.RoleName', 'r.Description', 'p.PermissionID', 'p.PermissionName'],
         orderBy: ['r.RoleName' => 'ASC']
      );

      $result = [];
      foreach ($roles as $row) {
         $roleId = $row['RoleID'];
         if (!isset($result[$roleId])) {
            $result[$roleId] = [
               'role_id'     => $roleId,
               'role_name'   => $row['RoleName'],
               'description' => $row['Description'],
               'permissions' => []
            ];
         }
         if ($row['PermissionID']) {
            $result[$roleId]['permissions'][] = [
               'permission_id'   => (int)$row['PermissionID'],
               'permission_name' => $row['PermissionName']
            ];
         }
      }

      return ['data' => array_values($result)];
   }

   /**
    * Assign role to member
    */
   public static function assignToMember(int $memberId, int $roleId): array
   {
      $orm = new ORM();

      $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
      if (empty($member)) {
         Helpers::sendFeedback('Member not found', 404);
      }

      $role = $orm->getWhere('churchrole', ['RoleID' => $roleId]);
      if (empty($role)) {
         Helpers::sendFeedback('Role not found', 404);
      }

      $orm->update('churchmember', ['ChurchRoleID' => $roleId], ['MbrID' => $memberId]);

      return ['status' => 'success'];
   }
}