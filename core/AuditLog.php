<?php

/**
 * Audit Logging
 * 
 * Tracks sensitive operations and changes for security and compliance.
 * Creates an immutable audit trail of all critical system actions.
 *
 * @package AliveChMS\Core
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-12-19
 */

declare(strict_types=1);

class AuditLog
{
   /**
    * Log an action
    * 
    * @param string $action Action performed (e.g., 'create', 'update', 'delete', 'approve')
    * @param string $entity Entity type (e.g., 'member', 'expense', 'budget')
    * @param int $entityId Entity ID
    * @param array $changes What changed (old and new values)
    * @param array $metadata Additional context
    */
   public static function log(
      string $action,
      string $entity,
      int $entityId,
      array $changes = [],
      array $metadata = []
   ): void {
      $orm = new ORM();

      try {
         $userId = Auth::getCurrentUserId();
      } catch (Exception $e) {
         $userId = null; // System action or unauthenticated
      }

      $logData = [
         'user_id' => $userId,
         'action' => $action,
         'entity_type' => $entity,
         'entity_id' => $entityId,
         'changes' => !empty($changes) ? json_encode($changes) : null,
         'metadata' => !empty($metadata) ? json_encode($metadata) : null,
         'ip_address' => Helpers::getClientIp(),
         'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
         'created_at' => date('Y-m-d H:i:s')
      ];

      try {
         $orm->insert('audit_log', $logData);
      } catch (Exception $e) {
         // Log to file if database insert fails
         Helpers::logError('Audit log failed: ' . $e->getMessage() . ' | Data: ' . json_encode($logData));
      }
   }

   /**
    * Log member-related action
    */
   public static function logMember(string $action, int $memberId, array $changes = []): void
   {
      self::log($action, 'member', $memberId, $changes);
   }

   /**
    * Log financial action
    */
   public static function logFinancial(string $action, string $type, int $id, array $changes = []): void
   {
      self::log($action, $type, $id, $changes, ['category' => 'financial']);
   }

   /**
    * Log approval action
    */
   public static function logApproval(string $entity, int $entityId, bool $approved, ?string $remarks = null): void
   {
      self::log(
         $approved ? 'approve' : 'reject',
         $entity,
         $entityId,
         ['status' => $approved ? 'Approved' : 'Rejected', 'remarks' => $remarks]
      );
   }

   /**
    * Log login attempt
    */
   public static function logLogin(string $username, bool $success, ?int $userId = null): void
   {
      $orm = new ORM();

      $orm->insert('login_log', [
         'user_id' => $userId,
         'username' => $username,
         'success' => $success ? 1 : 0,
         'ip_address' => Helpers::getClientIp(),
         'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
         'created_at' => date('Y-m-d H:i:s')
      ]);
   }

   /**
    * Get audit logs for an entity
    * 
    * @param string $entity Entity type
    * @param int $entityId Entity ID
    * @param int $limit Maximum number of logs to return
    * @return array Audit logs
    */
   public static function getEntityLogs(string $entity, int $entityId, int $limit = 50): array
   {
      $orm = new ORM();

      return $orm->selectWithJoin(
         baseTable: 'audit_log a',
         joins: [
            ['table' => 'churchmember m', 'on' => 'a.user_id = m.MbrID', 'type' => 'LEFT']
         ],
         fields: [
            'a.*',
            'm.MbrFirstName',
            'm.MbrFamilyName',
            'm.MbrEmailAddress'
         ],
         conditions: [
            'a.entity_type' => ':entity',
            'a.entity_id' => ':entity_id'
         ],
         params: [
            ':entity' => $entity,
            ':entity_id' => $entityId
         ],
         orderBy: ['a.created_at' => 'DESC'],
         limit: $limit
      );
   }

   /**
    * Get user's activity logs
    * 
    * @param int $userId User ID
    * @param int $limit Maximum number of logs
    * @return array Activity logs
    */
   public static function getUserActivity(int $userId, int $limit = 100): array
   {
      $orm = new ORM();

      return $orm->getWhere(
         'audit_log',
         ['user_id' => $userId],
         [],
         $limit
      );
   }

   /**
    * Search audit logs
    * 
    * @param array $filters Search filters
    * @param int $page Page number
    * @param int $limit Items per page
    * @return array Paginated audit logs
    */
   public static function search(array $filters = [], int $page = 1, int $limit = 50): array
   {
      $orm = new ORM();
      $offset = ($page - 1) * $limit;

      $conditions = [];
      $params = [];

      if (!empty($filters['user_id'])) {
         $conditions['a.user_id'] = ':user_id';
         $params[':user_id'] = (int)$filters['user_id'];
      }

      if (!empty($filters['action'])) {
         $conditions['a.action'] = ':action';
         $params[':action'] = $filters['action'];
      }

      if (!empty($filters['entity_type'])) {
         $conditions['a.entity_type'] = ':entity_type';
         $params[':entity_type'] = $filters['entity_type'];
      }

      if (!empty($filters['start_date'])) {
         $conditions['a.created_at >='] = ':start_date';
         $params[':start_date'] = $filters['start_date'];
      }

      if (!empty($filters['end_date'])) {
         $conditions['a.created_at <='] = ':end_date';
         $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
      }

      $logs = $orm->selectWithJoin(
         baseTable: 'audit_log a',
         joins: [
            ['table' => 'churchmember m', 'on' => 'a.user_id = m.MbrID', 'type' => 'LEFT']
         ],
         fields: [
            'a.*',
            'm.MbrFirstName',
            'm.MbrFamilyName'
         ],
         conditions: $conditions,
         params: $params,
         orderBy: ['a.created_at' => 'DESC'],
         limit: $limit,
         offset: $offset
      );

      $total = $orm->count('audit_log', array_combine(
         array_keys($conditions),
         array_values($params)
      ));

      return [
         'data' => $logs,
         'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int)ceil($total / $limit)
         ]
      ];
   }

   /**
    * Clean up old audit logs
    * Should be run periodically via cron
    * 
    * @param int $daysToKeep Number of days to keep logs (default 365)
    * @return int Number of records deleted
    */
   public static function cleanup(int $daysToKeep = 365): int
   {
      $orm = new ORM();

      $cutoffDate = date('Y-m-d', strtotime("-$daysToKeep days"));

      $result = $orm->runQuery(
         "DELETE FROM audit_log WHERE created_at < :cutoff",
         [':cutoff' => $cutoffDate]
      );

      return count($result);
   }
}
