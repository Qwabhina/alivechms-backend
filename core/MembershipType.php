<?php

/**
 * Membership Type Management
 *
 * Full CRUD for membership tiers (e.g., Full Member, Associate)
 * and member assignment with start/end dates and overlap protection.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class MembershipType
{
   /**
    * Create a new membership type
    *
    * @param array $data Type payload
    * @return array ['status' => 'success', 'type_id' => int]
    */
   public static function create(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'name'        => 'required|max:100',
         'description' => 'max:500|nullable'
      ]);

      $name = trim($data['name']);

      if (!empty($orm->getWhere('membershiptype', ['MshipTypeName' => $name]))) {
            Helpers::sendFeedback('Membership type name already exists', 400);
      }

      $typeId = $orm->insert('membershiptype', [
         'MshipTypeName'        => $name,
            'MshipTypeDescription' => $data['description'] ?? null
      ])['id'];

      return ['status' => 'success', 'type_id' => $typeId];
   }

   /**
    * Update an existing membership type
    *
    * @param int   $typeId Type ID
    * @param array $data   Updated data
    * @return array ['status' => 'success', 'type_id' => int]
    */
   public static function update(int $typeId, array $data): array
   {
      $orm = new ORM();

      $type = $orm->getWhere('membershiptype', ['MshipTypeID' => $typeId]);
      if (empty($type)) {
            Helpers::sendFeedback('Membership type not found', 404);
      }

      $update = [];

      if (!empty($data['name'])) {
         $name = trim($data['name']);
         if (!empty($orm->getWhere('membershiptype', [
            'MshipTypeName'   => $name,
            'MshipTypeID <>'  => $typeId
         ]))) {
            Helpers::sendFeedback('Membership type name already exists', 400);
            }
         $update['MshipTypeName'] = $name;
      }

      if (isset($data['description'])) {
         $update['MshipTypeDescription'] = $data['description'];
      }

      if (!empty($update)) {
         $orm->update('membershiptype', $update, ['MshipTypeID' => $typeId]);
      }

      return ['status' => 'success', 'type_id' => $typeId];
   }

   /**
    * Delete a membership type (only if no active assignments)
    *
    * @param int $typeId Type ID
    * @return array ['status' => 'success']
    */
   public static function delete(int $typeId): array
   {
      $orm = new ORM();

      $type = $orm->getWhere('membershiptype', ['MshipTypeID' => $typeId]);
      if (empty($type)) {
            Helpers::sendFeedback('Membership type not found', 404);
      }

      if (!empty($orm->getWhere('membermembershiptype', ['MshipTypeID' => $typeId]))) {
            Helpers::sendFeedback('Cannot delete membership type with assignments', 400);
      }

      $orm->delete('membershiptype', ['MshipTypeID' => $typeId]);
      return ['status' => 'success'];
   }

   /**
    * Retrieve a single membership type
    *
    * @param int $typeId Type ID
    * @return array Type data
    */
   public static function get(int $typeId): array
   {
      $orm = new ORM();

      $type = $orm->getWhere('membershiptype', ['MshipTypeID' => $typeId]);
      if (empty($type)) {
            Helpers::sendFeedback('Membership type not found', 404);
      }

      return $type[0];
   }

   /**
    * Retrieve paginated membership types with optional name filter
    *
    * @param int   $page    Page number
    * @param int   $limit   Items per page
    * @param array $filters Optional filters
    * @return array Paginated result
    */
   public static function getAll(int $page = 1, int $limit = 10, array $filters = []): array
   {
      $orm    = new ORM();
      $offset = ($page - 1) * $limit;

      $conditions = [];
      $params     = [];

      if (!empty($filters['name'])) {
            $conditions['MshipTypeName LIKE'] = ':name';
            $params[':name'] = '%' . trim($filters['name']) . '%';
      }

      $types = $orm->getWhere('membershiptype', $conditions, $params, $limit, $offset);

      $total = $orm->runQuery(
         "SELECT COUNT(*) AS total FROM membershiptype" .
            (!empty($conditions) ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
            $params
      )[0]['total'];

      return [
            'data' => $types,
            'pagination' => [
            'page'   => $page,
            'limit'  => $limit,
            'total'  => (int)$total,
            'pages'  => (int)ceil($total / $limit)
            ]
      ];
   }

   /**
    * Assign membership type to a member
    *
    * @param int   $memberId Member ID
    * @param array $data     Assignment payload
    * @return array ['status' => 'success', 'assignment_id' => int]
    */
   public static function assign(int $memberId, array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
         'type_id'     => 'required|numeric',
         'start_date'  => 'required|date'
      ]);

      // Validate member
      $member = $orm->getWhere('churchmember', [
         'MbrID'              => $memberId,
            'MbrMembershipStatus' => 'Active',
         'Deleted'            => 0
      ]);
      if (empty($member)) {
            Helpers::sendFeedback('Invalid or inactive member', 400);
      }

      // Validate type
      if (empty($orm->getWhere('membershiptype', ['MshipTypeID' => (int)$data['type_id']]))) {
         Helpers::sendFeedback('Invalid membership type', 400);
      }

      // Prevent multiple active assignments
      if (!empty($orm->getWhere('membermembershiptype', [
         'MbrID'    => $memberId,
         'EndDate'  => null
      ]))) {
            Helpers::sendFeedback('Member already has an active membership type', 400);
      }

      $assignmentId = $orm->insert('membermembershiptype', [
         'MbrID'       => $memberId,
         'MshipTypeID' => (int)$data['type_id'],
         'StartDate'   => $data['start_date'],
         'EndDate'     => null
      ])['id'];

      return ['status' => 'success', 'assignment_id' => $assignmentId];
   }

   /**
    * Update membership assignment (typically end date)
    *
    * @param int   $assignmentId Assignment ID
    * @param array $data         Updated data
    * @return array ['status' => 'success', 'assignment_id' => int]
    */
   public static function updateAssignment(int $assignmentId, array $data): array
   {
      $orm = new ORM();

      $assignment = $orm->getWhere('membermembershiptype', ['MemberMshipTypeID' => $assignmentId]);
      if (empty($assignment)) {
         Helpers::sendFeedback('Assignment not found', 404);
      }

      $update = [];
      if (isset($data['end_date'])) {
         if ($data['end_date'] < $assignment[0]['StartDate']) {
            Helpers::sendFeedback('End date cannot be before start date', 400);
         }
         $update['EndDate'] = $data['end_date'];
      }

      if (!empty($update)) {
         $orm->update('membermembershiptype', $update, ['MemberMshipTypeID' => $assignmentId]);
      }

      return ['status' => 'success', 'assignment_id' => $assignmentId];
   }

   /**
    * Retrieve all assignments for a member
    *
    * @param int   $memberId Member ID
    * @param array $filters  Optional filters
    * @return array ['data' => array]
    */
   public static function getMemberAssignments(int $memberId, array $filters = []): array
   {
      $orm = new ORM();

      if (empty($orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]))) {
            Helpers::sendFeedback('Invalid member', 400);
      }

      $conditions = ['mmt.MbrID' => ':mid'];
      $params     = [':mid' => $memberId];

      if (!empty($filters['active'])) {
         $conditions['mmt.EndDate'] = null;
      }
      if (!empty($filters['start_date'])) {
         $conditions['mmt.StartDate >='] = ':start';
         $params[':start'] = $filters['start_date'];
      }
      if (!empty($filters['end_date'])) {
         $conditions['mmt.EndDate <='] = ':end';
         $params[':end'] = $filters['end_date'];
      }

      return [
         'data' => $orm->selectWithJoin(
            baseTable: 'membermembershiptype mmt',
            joins: [['table' => 'membershiptype mt', 'on' => 'mmt.MshipTypeID = mt.MshipTypeID']],
            fields: [
               'mmt.MemberMshipTypeID',
               'mmt.MshipTypeID',
               'mt.MshipTypeName',
               'mmt.StartDate',
               'mmt.EndDate'
            ],
            conditions: $conditions,
            params: $params,
            orderBy: ['mmt.StartDate' => 'DESC']
         )
      ];
   }
}