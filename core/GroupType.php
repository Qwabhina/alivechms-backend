<?php

/**
 * Group Type Management Class
 *
 * Handles CRUD operations for group categories (e.g., Youth, Choir, Ushering).
 * Simple lookup table with uniqueness enforcement.
 *
 * @package AliveChMS\Core
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-20
 */

declare(strict_types=1);

class GroupType
{
   /**
    * Create a new group type
    *
    * @param array $data Group type data (name required)
    * @return array Success response with type_id
    * @throws Exception On validation or database failure
    */
   public static function create(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'name' => 'required|max:100',
      ]);

      $name = trim($data['name']);

      // Enforce uniqueness
      $existing = $orm->getWhere('grouptype', ['GroupTypeName' => $name]);
      if (!empty($existing)) {
         Helpers::sendFeedback('Group type name already exists', 400);
      }

      $typeId = $orm->insert('grouptype', [
         'GroupTypeName' => $name
      ])['id'];

      return ['status' => 'success', 'type_id' => $typeId];
   }

   /**
    * Update an existing group type
    *
    * @param int   $typeId Group type ID
    * @param array $data   Updated data
    * @return array Success response
    */
   public static function update(int $typeId, array $data): array
   {
      $orm = new ORM();

      $type = $orm->getWhere('grouptype', ['GroupTypeID' => $typeId]);
      if (empty($type)) {
         Helpers::sendFeedback('Group type not found', 404);
      }

      if (empty($data['name'])) {
         return ['status' => 'success', 'type_id' => $typeId];
      }

      $name = trim($data['name']);
      Helpers::validateInput(['name' => $name], ['name' => 'required|max:100']);

      $existing = $orm->getWhere('grouptype', [
         'GroupTypeName'   => $name,
         'GroupTypeID!='   => $typeId
      ]);
      if (!empty($existing)) {
         Helpers::sendFeedback('Group type name already exists', 400);
      }

      $orm->update('grouptype', ['GroupTypeName' => $name], ['GroupTypeID' => $typeId]);

      return ['status' => 'success', 'type_id' => $typeId];
   }

   /**
    * Delete a group type (only if not used by any group)
    *
    * @param int $typeId Group type ID
    * @return array Success response
    */
   public static function delete(int $typeId): array
   {
      $orm = new ORM();

      $type = $orm->getWhere('grouptype', ['GroupTypeID' => $typeId]);
      if (empty($type)) {
         Helpers::sendFeedback('Group type not found', 404);
      }

      $used = $orm->getWhere('churchgroup', ['GroupTypeID' => $typeId]);
      if (!empty($used)) {
         Helpers::sendFeedback('Cannot delete group type in use', 400);
      }

      $orm->delete('grouptype', ['GroupTypeID' => $typeId]);

      return ['status' => 'success'];
   }

   /**
    * Retrieve a single group type
    *
    * @param int $typeId Group type ID
    * @return array Group type data
    */
   public static function get(int $typeId): array
   {
      $orm = new ORM();

      $type = $orm->getWhere('grouptype', ['GroupTypeID' => $typeId]);
      if (empty($type)) {
         Helpers::sendFeedback('Group type not found', 404);
      }

      return $type[0];
   }

   /**
    * Retrieve all group types (paginated)
    *
    * @param int $page  Page number
    * @param int $limit Items per page
    * @return array Paginated result
    */
   public static function getAll(int $page = 1, int $limit = 50): array
   {
      $orm = new ORM();
      $offset = ($page - 1) * $limit;

      $types = $orm->getAll('grouptype', $limit, $offset);

      $total = $orm->runQuery('SELECT COUNT(*) AS total FROM grouptype')[0]['total'];

      return [
         'data' => $types,
            'pagination' => [
            'page'  => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => (int)ceil($total / $limit)
            ]
      ];
   }
}