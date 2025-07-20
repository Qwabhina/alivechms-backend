<?php

/**
 * GroupType Class
 * This class handles operations related to group types in the church management system.
 * It includes methods for creating, updating, deleting, retrieving a single group type, and listing all group types with pagination.
 * @package GroupType
 * @version 1.0
 */
class GroupType
{
   /**
    * Creates a new group type.
    * Validates input, checks for duplicates, and inserts into the database.
    * @param array $data The group type data to create.
    * @return array The created group type ID and status.
    * @throws Exception If validation fails or database operations fail.
    */
   public static function create($data)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate input
         Helpers::validateInput($data, ['name' => 'required']);

         // Check for duplicate group type name
         $existing = $orm->getWhere('grouptype', ['GroupTypeName' => $data['name']]);
         if (!empty($existing)) {
            throw new Exception('Group type name already exists');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $groupTypeId = $orm->insert('grouptype', [
            'GroupTypeName' => $data['name']
         ])['id'];

         $orm->commit();
         return ['status' => 'success', 'group_type_id' => $groupTypeId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) {
            $orm->rollBack();
         }
         Helpers::logError('GroupType create error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Updates an existing group type.
    * Validates input, checks for duplicates, and updates the database.
    * @param int $groupTypeId The ID of the group type to update.
    * @param array $data The group type data to update.
    * @return array The updated group type ID and status.
    * @throws Exception If validation fails, group type not found, or database operations fail.
    */
   public static function update($groupTypeId, $data)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate input
         Helpers::validateInput($data, ['name' => 'required']);

         // Validate group type exists
         $groupType = $orm->getWhere('grouptype', ['GroupTypeID' => $groupTypeId]);
         if (empty($groupType)) {
            throw new Exception('Group type not found');
         }

         // Check for duplicate group type name (excluding current)
         $existing = $orm->getWhere('grouptype', ['GroupTypeName' => $data['name'], 'GroupTypeID != ' => $groupTypeId]);
         if (!empty($existing)) {
            throw new Exception('Group type name already exists');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->update('grouptype', [
            'GroupTypeName' => $data['name']
         ], ['GroupTypeID' => $groupTypeId]);

         $orm->commit();
         return ['status' => 'success', 'group_type_id' => $groupTypeId];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) {
            $orm->rollBack();
         }
         Helpers::logError('GroupType update error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Deletes a group type.
    * Validates that the group type exists and is not referenced by any groups.
    * @param int $groupTypeId The ID of the group type to delete.
    * @return array The status of the deletion.
    * @throws Exception If validation fails or database operations fail.
    */
   public static function delete($groupTypeId)
   {
      $orm = new ORM();
      $transactionStarted = false;
      try {
         // Validate group type exists
         $groupType = $orm->getWhere('grouptype', ['GroupTypeID' => $groupTypeId]);
         if (empty($groupType)) {
            throw new Exception('Group type not found');
         }

         // Check if referenced by groups
         $referenced = $orm->getWhere('churchgroup', ['GroupTypeID' => $groupTypeId]);
         if (!empty($referenced)) {
            throw new Exception('Cannot delete group type used by existing groups');
         }

         $orm->beginTransaction();
         $transactionStarted = true;

         $orm->delete('grouptype', ['GroupTypeID' => $groupTypeId]);

         $orm->commit();
         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($transactionStarted && $orm->inTransaction()) {
            $orm->rollBack();
         }
         Helpers::logError('GroupType delete error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Retrieves a single group type by ID.
    * @param int $groupTypeId The ID of the group type to retrieve.
    * @return array The group type data.
    * @throws Exception If the group type is not found or database operations fail.
    */
   public static function get($groupTypeId)
   {
      $orm = new ORM();
      try {
         $groupType = $orm->getWhere('grouptype', ['GroupTypeID' => $groupTypeId])[0] ?? null;
         if (!$groupType) {
            throw new Exception('Group type not found');
         }
         return $groupType;
      } catch (Exception $e) {
         Helpers::logError('GroupType get error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Retrieves all group types with pagination.
    * @param int $page The page number for pagination.
    * @param int $limit The number of items per page.
    * @return array An array containing the group types and pagination info.
    * @throws Exception If database operations fail.
    */
   public static function getAll($page = 1, $limit = 10)
   {
      $orm = new ORM();
      try {
         $offset = ($page - 1) * $limit;

         $groupTypes = $orm->selectWithJoin(
            baseTable: 'grouptype gt',
            fields: ['gt.*'],
            limit: $limit,
            offset: $offset
         );

         $total = $orm->runQuery('SELECT COUNT(*) as total FROM grouptype')[0]['total'];

         return [
            'data' => $groupTypes,
            'pagination' => [
               'page' => $page,
               'limit' => $limit,
               'total' => $total,
               'pages' => ceil($total / $limit)
            ]
         ];
      } catch (Exception $e) {
         Helpers::logError('GroupType getAll error: ' . $e->getMessage());
         throw $e;
      }
   }
}
