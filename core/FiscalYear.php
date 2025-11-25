<?php

/**
 * Fiscal Year Management
 *
 * Handles creation, update, deletion, closure, and retrieval
 * of fiscal years with overlap protection and full audit trail.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class FiscalYear
{
   /**
    * Create a new fiscal year
    *
    * @param array $data Fiscal year payload
    * @return array ['status' => 'success', 'fiscal_year_id' => int]
    * @throws Exception On validation or database failure
    */
   public static function create(array $data): array
   {
      $orm = new ORM();

      Helpers::validateInput($data, [
            'start_date' => 'required|date',
         'end_date'   => 'required|date',
         'branch_id'  => 'required|numeric',
         'status'     => 'in:Active,Closed|nullable'
      ]);

      $startDate = $data['start_date'];
      $endDate   = $data['end_date'];
      $branchId  = (int)$data['branch_id'];

      if (strtotime($startDate) >= strtotime($endDate)) {
            Helpers::sendFeedback('Start date must be before end date', 400);
      }

      self::validateBranch($branchId);
      self::preventOverlap($branchId, $startDate, $endDate);

      $orm->beginTransaction();
      try {
         $fiscalYearId = $orm->insert('fiscalyear', [
            'FiscalYearStartDate' => $startDate,
            'FiscalYearEndDate'   => $endDate,
            'BranchID'            => $branchId,
            'Status'              => $data['status'] ?? 'Active'
         ])['id'];

         self::createNotification("New Fiscal Year Created", "Fiscal year $startDate to $endDate created.", $branchId);
         $orm->commit();

         return ['status' => 'success', 'fiscal_year_id' => $fiscalYearId];
      } catch (Exception $e) {
         $orm->rollBack();
         Helpers::logError("Fiscal year creation failed: " . $e->getMessage());
         throw $e;
      }
   }

   /**
    * Update an existing fiscal year
    *
    * @param int   $fiscalYearId Fiscal Year ID
    * @param array $data         Updated data
    * @return array ['status' => 'success', 'fiscal_year_id' => int]
    */
   public static function update(int $fiscalYearId, array $data): array
   {
      $orm = new ORM();

      $existing = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
      if (empty($existing)) {
            Helpers::sendFeedback('Fiscal year not found', 404);
      }

      $current = $existing[0];
      if ($current['Status'] === 'Closed' && (isset($data['start_date']) || isset($data['end_date']))) {
            Helpers::sendFeedback('Cannot update dates of a closed fiscal year', 400);
      }

      $startDate = $data['start_date'] ?? $current['FiscalYearStartDate'];
      $endDate   = $data['end_date']   ?? $current['FiscalYearEndDate'];
      $branchId  = $data['branch_id']  ?? $current['BranchID'];

      if (isset($data['start_date']) || isset($data['end_date'])) {
         if (strtotime($startDate) >= strtotime($endDate)) {
            Helpers::sendFeedback('Start date must be before end date', 400);
            }
      }

      if (isset($data['branch_id'])) {
         self::validateBranch((int)$data['branch_id']);
      }

      if (isset($data['start_date']) || isset($data['end_date'])) {
         self::preventOverlap($branchId, $startDate, $endDate, $fiscalYearId);
      }

      $updateData = [];
      if (isset($data['start_date'])) $updateData['FiscalYearStartDate'] = $data['start_date'];
      if (isset($data['end_date']))   $updateData['FiscalYearEndDate']   = $data['end_date'];
      if (isset($data['branch_id']))  $updateData['BranchID']            = (int)$data['branch_id'];
      if (isset($data['status']))     $updateData['Status']              = $data['status'];

      if (!empty($updateData)) {
         $orm->beginTransaction();
         try {
            $orm->update('fiscalyear', $updateData, ['FiscalYearID' => $fiscalYearId]);
            self::createNotification("Fiscal Year Updated", "Fiscal year $startDate to $endDate updated.", $branchId);
            $orm->commit();
         } catch (Exception $e) {
            $orm->rollBack();
            throw $e;
         }
      }

      return ['status' => 'success', 'fiscal_year_id' => $fiscalYearId];
   }

   /**
    * Delete a fiscal year (only if no associated records)
    *
    * @param int $fiscalYearId Fiscal Year ID
    * @return array ['status' => 'success']
    */
   public static function delete(int $fiscalYearId): array
   {
      $orm = new ORM();

      $fy = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
      if (empty($fy)) {
            Helpers::sendFeedback('Fiscal year not found', 404);
      }

      $used = $orm->runQuery(
         "SELECT
                (SELECT COUNT(*) FROM budget WHERE FiscalYearID = :id) +
                (SELECT COUNT(*) FROM contribution WHERE FiscalYearID = :id) +
                (SELECT COUNT(*) FROM expense WHERE FiscalYearID = :id) AS total",
            [':id' => $fiscalYearId]
      )[0]['total'];

      if ($used > 0) {
         Helpers::sendFeedback('Cannot delete fiscal year with associated records', 400);
      }

      $orm->delete('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
      return ['status' => 'success'];
   }

   /**
    * Retrieve a single fiscal year with branch name
    *
    * @param int $fiscalYearId Fiscal Year ID
    * @return array Fiscal year data
    */
   public static function get(int $fiscalYearId): array
   {
      $orm = new ORM();

      $result = $orm->selectWithJoin(
            baseTable: 'fiscalyear fy',
         joins: [['table' => 'branch b', 'on' => 'fy.BranchID = b.BranchID', 'type' => 'LEFT']],
         fields: ['fy.*', 'b.BranchName'],
            conditions: ['fy.FiscalYearID' => ':id'],
            params: [':id' => $fiscalYearId]
      );

      if (empty($result)) {
            Helpers::sendFeedback('Fiscal year not found', 404);
      }

      return $result[0];
   }

   /**
    * Retrieve paginated fiscal years with filters
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

      if (!empty($filters['branch_id'])) {
         $conditions['fy.BranchID'] = ':branch_id';
         $params[':branch_id'] = (int)$filters['branch_id'];
      }
      if (!empty($filters['status'])) {
         $conditions['fy.Status'] = ':status';
            $params[':status'] = $filters['status'];
      }
      if (!empty($filters['date_from'])) {
            $conditions['fy.FiscalYearEndDate >='] = ':date_from';
            $params[':date_from'] = $filters['date_from'];
      }
      if (!empty($filters['date_to'])) {
            $conditions['fy.FiscalYearStartDate <='] = ':date_to';
            $params[':date_to'] = $filters['date_to'];
      }

      $years = $orm->selectWithJoin(
            baseTable: 'fiscalyear fy',
         joins: [['table' => 'branch b', 'on' => 'fy.BranchID = b.BranchID', 'type' => 'LEFT']],
         fields: ['fy.*', 'b.BranchName'],
            conditions: $conditions,
            params: $params,
         orderBy: ['fy.FiscalYearStartDate' => 'DESC'],
            limit: $limit,
            offset: $offset
      );

      $total = $orm->runQuery(
         "SELECT COUNT(*) AS total FROM fiscalyear fy" .
            (!empty($conditions) ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
            $params
      )[0]['total'];

      return [
         'data' => $years,
            'pagination' => [
            'page'   => $page,
            'limit'  => $limit,
            'total'  => (int)$total,
            'pages'  => (int)ceil($total / $limit)
            ]
      ];
   }

   /**
    * Close a fiscal year
    *
    * @param int $fiscalYearId Fiscal Year ID
    * @return array ['status' => 'success', 'fiscal_year_id' => int]
    */
   public static function close(int $fiscalYearId): array
   {
      $orm = new ORM();

      $fy = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
      if (empty($fy)) {
            Helpers::sendFeedback('Fiscal year not found', 404);
      }

      if ($fy[0]['Status'] === 'Closed') {
            Helpers::sendFeedback('Fiscal year is already closed', 400);
      }

      $branch = $orm->getWhere('branch', ['BranchID' => $fy[0]['BranchID']])[0] ?? null;

      $orm->update('fiscalyear', ['Status' => 'Closed'], ['FiscalYearID' => $fiscalYearId]);
      self::createNotification(
         "Fiscal Year Closed",
         "Fiscal year {$fy[0]['FiscalYearStartDate']} to {$fy[0]['FiscalYearEndDate']} closed for branch {$branch['BranchName']}.",
         $fy[0]['BranchID']
      );

      return ['status' => 'success', 'fiscal_year_id' => $fiscalYearId];
   }

   /** Private Helpers */

   private static function validateBranch(int $branchId): void
   {
      $orm = new ORM();
      if (empty($orm->getWhere('branch', ['BranchID' => $branchId]))) {
         Helpers::sendFeedback('Invalid branch ID', 400);
      }
   }

   private static function preventOverlap(int $branchId, string $startDate, string $endDate, ?int $excludeId = null): void
   {
      $orm = new ORM();

      $sql = "SELECT FiscalYearID FROM fiscalyear
                WHERE BranchID = :branch_id
                  AND Status = 'Active'
                  AND FiscalYearID != :exclude_id
                  AND (
                      (:start BETWEEN FiscalYearStartDate AND FiscalYearEndDate)
                      OR (:end BETWEEN FiscalYearStartDate AND FiscalYearEndDate)
                      OR (FiscalYearStartDate BETWEEN :start AND :end)
                  )";

      $params = [
         ':branch_id'  => $branchId,
         ':start'      => $startDate,
         ':end'        => $endDate,
         ':exclude_id' => $excludeId ?? 0
      ];

      if (!empty($orm->runQuery($sql, $params))) {
         Helpers::sendFeedback('Fiscal year overlaps with an existing active fiscal year', 400);
      }
   }

   private static function createNotification(string $title, string $message, int $branchId): void
   {
      $orm = new ORM();
      $branch = $orm->getWhere('branch', ['BranchID' => $branchId])[0] ?? null;

      $orm->insert('communication', [
         'Title'         => $title,
         'Message'       => $message . ($branch ? " ({$branch['BranchName']})" : ''),
         'SentBy'        => 1, // System/admin
         'TargetGroupID' => null,
         'CreatedAt'     => date('Y-m-d H:i:s')
      ]);
   }
}