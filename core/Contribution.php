<?php

/** Contribution Management Class
 * Handles contribution creation, updating, deletion, retrieval, and listing
 * Validates inputs and ensures data integrity
 * Implements error handling and transaction management
 * @package Contribution
 */
class Contribution
{
   /**
    * Create a new contribution entry
    * @param array $data Array containing required fields: amount, date, contribution_type_id, member_id, payment_option_id, fiscal_year_id
    * @return array Success status and contribution ID
    * @throws Exception if validation fails or database operations fail
    */
   public static function create($data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'contribution_type_id' => 'required|numeric',
            'member_id' => 'required|numeric',
            'payment_option_id' => 'required|numeric',
            'fiscal_year_id' => 'required|numeric'
         ]);

         // Validate amount is positive
         if ($data['amount'] <= 0) {
            Helpers::sendError('Contribution amount must be positive.', 400);
         }

         // Validate date format and ensure it's not in the future
         if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
            Helpers::sendError('Invalid date format (YYYY-MM-DD required).', 400);
         }
         $dateObj = DateTime::createFromFormat('Y-m-d', $data['date']);
         if (!$dateObj || $dateObj->format('Y-m-d') !== $data['date']) {
            Helpers::sendError('Invalid date value.', 400);
         }
         $currentDate = (new DateTime())->format('Y-m-d');
         if ($data['date'] > $currentDate) {
            Helpers::sendError('Contribution date cannot be in the future.', 400);
         }

         // Validate foreign keys in a single query
         $validationQuery = $orm->runQuery(
            'SELECT 
                (SELECT COUNT(*) FROM churchmember WHERE MbrID = :member_id AND Deleted = "0") as member_exists,
                (SELECT COUNT(*) FROM contributiontype WHERE ContributionTypeID = :contribution_type_id) as type_exists,
                (SELECT COUNT(*) FROM paymentoption WHERE PaymentOptionID = :payment_option_id) as payment_exists,
                (SELECT COUNT(*) FROM fiscalyear WHERE FiscalYearID = :fiscal_year_id) as fiscal_year_exists',
            [
               ':member_id' => $data['member_id'],
               ':contribution_type_id' => $data['contribution_type_id'],
               ':payment_option_id' => $data['payment_option_id'],
               ':fiscal_year_id' => $data['fiscal_year_id']
            ]
         );

         if ($validationQuery[0]['member_exists'] == 0) {
            Helpers::sendError('Invalid Member ID: Member does not exist or is deleted.', 400);
         }
         if ($validationQuery[0]['type_exists'] == 0) {
            Helpers::sendError('Invalid Contribution Type ID.', 400);
         }
         if ($validationQuery[0]['payment_exists'] == 0) {
            Helpers::sendError('Invalid Payment Option ID.', 400);
         }
         if ($validationQuery[0]['fiscal_year_exists'] == 0) {
            Helpers::sendError('Invalid Fiscal Year ID.', 400);
         }

         $orm->beginTransaction();

         // Insert contribution
         $contributionId = $orm->insert('contribution', [
            'ContributionAmount' => $data['amount'],
            'ContributionDate' => $data['date'],
            'ContributionTypeID' => $data['contribution_type_id'],
            'PaymentOptionID' => $data['payment_option_id'],
            'MbrID' => $data['member_id'],
            'FiscalYearID' => $data['fiscal_year_id'],
            'Deleted' => '0'
         ])['id'];

         // Create notifications for admins with view_contributions permission
         $adminUsers = $orm->runQuery(
            'SELECT DISTINCT u.MbrID
             FROM userauthentication u
             INNER JOIN memberrole mr ON u.MbrID = mr.MbrID
             INNER JOIN churchrole cr ON mr.ChurchRoleID = cr.RoleID
             INNER JOIN rolepermission rp ON cr.RoleID = rp.ChurchRoleID
             INNER JOIN permission p ON rp.PermissionID = p.PermissionID
             WHERE p.PermissionName = :permission',
            [':permission' => 'view_contributions']
         );

         foreach ($adminUsers as $admin) {
            $orm->insert('communication', [
               'Title' => 'New Contribution Submitted',
               'Message' => "Contribution of {$data['amount']} submitted by member ID {$data['member_id']} on {$data['date']}.",
               'SentBy' => $data['member_id'],
               'TargetGroupID' => null
            ]);
         }

         $orm->commit();
         return ['status' => 'success', 'contribution_id' => $contributionId];
      } catch (Exception $e) {
         if ($orm->inTransaction()) {
            $orm->rollBack();
         }
         $errorMessage = 'Failed to create contribution: ' . $e->getMessage();
         if (strpos($e->getMessage(), 'SQLSTATE[23000]') !== false) {
            $errorMessage = 'Database constraint violation (e.g., invalid foreign key).';
         }
         Helpers::logError('Contribution create error: ' . $e->getMessage());
         Helpers::sendError($errorMessage, 400);
      }
   }

   /**
    * Update an existing contribution entry
    * @param int $contributionId The ID of the contribution to update
    * @param array $data Array containing fields to update: amount, date, contribution_type_id, payment_option_id, fiscal_year_id
    * @return array Success status and contribution ID
    * @throws Exception if validation fails or database operations fail
    */
   public static function update($contributionId, $data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'contribution_type_id' => 'required|numeric',
            'payment_option_id' => 'required|numeric',
            'fiscal_year_id' => 'required|numeric'
         ]);

         // Validate amount is positive
         if ($data['amount'] <= 0) {
            Helpers::sendError('Contribution amount must be positive.', 400);
         }

         // Validate date format and ensure it's not in the future
         if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
            Helpers::sendError('Invalid date format (YYYY-MM-DD required).', 400);
         }
         $dateObj = DateTime::createFromFormat('Y-m-d', $data['date']);
         if (!$dateObj || $dateObj->format('Y-m-d') !== $data['date']) {
            Helpers::sendError('Invalid date value.', 400);
         }
         $currentDate = (new DateTime())->format('Y-m-d');
         if ($data['date'] > $currentDate) {
            Helpers::sendError('Contribution date cannot be in the future.', 400);
         }

         // Validate contribution exists and is not soft-deleted
         $contribution = $orm->getWhere('contribution', [
            'ContributionID' => $contributionId,
            'Deleted' => '0'
         ]);
         if (empty($contribution)) {
            Helpers::sendError('Contribution not found or has been deleted.', 400);
         }

         // Validate foreign keys in a single query
         $validationQuery = $orm->runQuery(
            'SELECT 
                (SELECT COUNT(*) FROM contributiontype WHERE ContributionTypeID = :contribution_type_id) as type_exists,
                (SELECT COUNT(*) FROM churchmember WHERE MbrID = :member_id) as member_exists,
                (SELECT COUNT(*) FROM paymentoption WHERE PaymentOptionID = :payment_option_id) as payment_exists,
                (SELECT COUNT(*) FROM fiscalyear WHERE FiscalYearID = :fiscal_year_id) as fiscal_year_exists',
            [
               ':contribution_type_id' => $data['contribution_type_id'],
               ':payment_option_id' => $data['payment_option_id'],
               ':member_id' => $data['member_id'],
               ':fiscal_year_id' => $data['fiscal_year_id']
            ]
         );

         if ($validationQuery[0]['type_exists'] == 0) Helpers::sendError('Invalid Contribution Type ID.', 400);
         if ($validationQuery[0]['payment_exists'] == 0) Helpers::sendError('Invalid Payment Option ID.', 400);
         if ($validationQuery[0]['member_exists'] == 0) Helpers::sendError('Member Not Found.', 400);
         if ($validationQuery[0]['fiscal_year_exists'] == 0) Helpers::sendError('Invalid Fiscal Year ID.', 400);

         $orm->beginTransaction();

         // Update contribution
         $orm->update('contribution', [
            'ContributionAmount' => $data['amount'],
            'ContributionDate' => $data['date'],
            'MbrID' => $data['member_id'],
            'ContributionTypeID' => $data['contribution_type_id'],
            'PaymentOptionID' => $data['payment_option_id'],
            'FiscalYearID' => $data['fiscal_year_id']
         ], ['ContributionID' => $contributionId]);

         $orm->commit();
         return ['status' => 'success', 'contribution_id' => $contributionId];
      } catch (Exception $e) {
         if ($orm->inTransaction()) {
            $orm->rollBack();
         }
         $errorMessage = 'Failed to update contribution: ' . $e->getMessage();
         if (strpos($e->getMessage(), 'SQLSTATE[23000]') !== false) {
            $errorMessage = 'Database constraint violation (e.g., invalid foreign key).';
         }
         Helpers::logError('Contribution update error: ' . $e->getMessage());
         Helpers::sendError($errorMessage, 400);
      }
   }

   /**
    * Delete a contribution entry
    * @param int $contributionId The ID of the contribution to delete
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function delete($contributionId)
   {
      $orm = new ORM();
      try {
         // Validate contribution exists and is not already soft-deleted
         $contribution = $orm->getWhere('contribution', [
            'ContributionID' => $contributionId,
            'Deleted' => 0
         ]);
         if (empty($contribution)) {
            Helpers::sendError('Contribution not found or already deleted.');
         }

         $orm->beginTransaction();
         $orm->update('contribution', [
            'Deleted' => 1
         ], [
            'ContributionID' => $contributionId
         ]);
         $orm->commit();

         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($orm->inTransaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Contribution soft delete error: ' . $e->getMessage());
         Helpers::sendError('Contribution soft delete failed: ' . $e->getMessage());
      }
   }

   /**
    * Restore a soft-deleted contribution entry
    * @param int $contributionId The ID of the contribution to restore
    * @return array Result of the operation
    * @throws Exception if validation fails or database operations fail
    */
   public static function restore($contributionId)
   {
      $orm = new ORM();
      try {
         $contribution = $orm->getWhere('contribution', [
            'ContributionID' => $contributionId,
            'Deleted' => 1
         ]);
         if (empty($contribution)) {
            Helpers::sendError('Contribution not found or not deleted.');
         }

         $orm->beginTransaction();
         $orm->update('contribution', [
            'Deleted' => 0
         ], [
            'ContributionID' => $contributionId
         ]);
         $orm->commit();

         return ['status' => 'success'];
      } catch (Exception $e) {
         if ($orm->inTransaction()) {
            $orm->rollBack();
         }
         Helpers::logError('Contribution restore error: ' . $e->getMessage());
         Helpers::sendError('Contribution restore failed: ' . $e->getMessage());
      }
   }

   /**
    * Get a single contribution entry by ID
    * @param int $contributionId The ID of the contribution to retrieve
    * @return array|null The contribution data or null if not found
    * @throws Exception if database operations fail
    */
   public static function get($contributionId)
   {
      $orm = new ORM();
      try {
         $contribution = $orm->selectWithJoin(
            baseTable: 'contribution c',
            joins: [
               ['table' => 'churchmember m', 'on' => 'c.MbrID = m.MbrID', 'type' => 'LEFT'],
               ['table' => 'contributiontype ct', 'on' => 'c.ContributionTypeID = ct.ContributionTypeID', 'type' => 'LEFT'],
               ['table' => 'paymentoption p', 'on' => 'c.PaymentOptionID = p.PaymentOptionID', 'type' => 'LEFT']
            ],
            fields: [
               'c.ContributionID',
               'c.ContributionAmount',
               'c.ContributionDate',
               'CONCAT(m.MbrFirstName," ", m.MbrFamilyName) AS ContributorName',
               'ct.*',
               'p.*',
            ],
            conditions: ['c.ContributionID' => ':id', 'c.Deleted' => ':deleted'],
            params: [':id' => $contributionId, ':deleted' => 0]
         )[0] ?? null;

         if (!$contribution) Helpers::sendError('Contribution not found');

         return $contribution;
      } catch (Exception $e) {
         Helpers::logError('Contribution get error: ' . $e->getMessage());
         Helpers::sendError('Contribution get error: ' . $e->getMessage());
         // Helpers::sendError('Contribution retrieval error.');
      }
   }

   /**
    * Get all contributions with pagination and optional filters
    * @param int $page Page number for pagination
    * @param int $limit Number of records per page
    * @param array $filters Optional filters for contribution type or date range
    * @return array List of contributions with pagination info
    * @throws Exception if database operations fail
    */
   public static function getAll($page = 1, $limit = 10, $filters = [])
   {
      $orm = new ORM();
      try {
         $offset = ($page - 1) * $limit;
         $conditions = [];
         $params = [];

         // Apply filters
         if (!empty($filters['contribution_type']) && is_numeric($filters['contribution_type'])) {
            $conditions[] = ['column' => 'c.ContributionTypeID', 'operator' => '=', 'placeholder' => ':contribution_type'];
            $params[':contribution_type'] = $filters['contribution_type'];
         }
         if (!empty($filters['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['start_date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $filters['start_date']);
            if ($date && $date->format('Y-m-d') === $filters['start_date']) {
               $conditions[] = ['column' => 'c.ContributionDate', 'operator' => '>=', 'placeholder' => ':start_date'];
               $params[':start_date'] = $filters['start_date'];
            } else {
               Helpers::sendError('Invalid start_date format.');
            }
         }
         if (!empty($filters['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['end_date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $filters['end_date']);
            if ($date && $date->format('Y-m-d') === $filters['end_date']) {
               $conditions[] = ['column' => 'c.ContributionDate', 'operator' => '<=', 'placeholder' => ':end_date'];
               $params[':end_date'] = $filters['end_date'];
            } else {
               Helpers::sendError('Invalid end_date format.');
            }
         }
         if (!empty($filters['fiscal_year']) && is_numeric($filters['fiscal_year'])) {
            $conditions[] = ['column' => 'c.FiscalYearID', 'operator' => '=', 'placeholder' => ':fiscal_year'];
            $params[':fiscal_year'] = $filters['fiscal_year'];
         }
         if (!empty($filters['payment_option']) && is_numeric($filters['payment_option'])) {
            $conditions[] = ['column' => 'c.PaymentOptionID', 'operator' => '=', 'placeholder' => ':payment_option'];
            $params[':payment_option'] = $filters['payment_option'];
         }

         // Validate date range against fiscal year if both are provided
         if (!empty($filters['fiscal_year']) && !empty($filters['start_date']) && !empty($filters['end_date'])) {
            $fiscalYearData = $orm->getWhere('fiscalyear', ['FiscalYearID' => $filters['fiscal_year']]);

            if (empty($fiscalYearData)) Helpers::sendError('Invalid FiscalYearID: No fiscal year found.');

            $fiscalStart = $fiscalYearData[0]['FiscalYearStartDate'];
            $fiscalEnd = $fiscalYearData[0]['FiscalYearEndDate'];

            if ($filters['start_date'] < $fiscalStart || $filters['end_date'] > $fiscalEnd) Helpers::sendError("Date range must be within fiscal year boundaries ($fiscalStart to $fiscalEnd).");
         }

         // Add deleted condition
         $conditions[] = ['column' => 'c.Deleted', 'operator' => '=', 'placeholder' => ':deleted'];
         $params[':deleted'] = 0;

         // Construct WHERE clause
         $whereClauses = [];
         foreach ($conditions as $condition) $whereClauses[] = "{$condition['column']} {$condition['operator']} {$condition['placeholder']}";

         $whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

         // Main query
         $sql = "SELECT c.ContributionID, c.ContributionAmount, c.ContributionDate, 
                CONCAT(m.MbrFirstName, ' ', m.MbrFamilyName) AS ContributorName, 
                ct.*, p.*
                FROM contribution c
                LEFT JOIN churchmember m ON c.MbrID = m.MbrID
                LEFT JOIN contributiontype ct ON c.ContributionTypeID = ct.ContributionTypeID
                LEFT JOIN paymentoption p ON c.PaymentOptionID = p.PaymentOptionID"
            . $whereSql;

         if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
               $sql .= " OFFSET {$offset}";
            }
         }

         $contributions = $orm->runQuery($sql, $params);

         // Total count query
         $total = $orm->runQuery(
            "SELECT COUNT(*) as total FROM contribution c" . $whereSql,
            $params
         )[0]['total'];

         return [
            'data' => $contributions,
            'pagination' => [
               'page' => $page,
               'limit' => $limit,
               'total' => $total,
               'pages' => ceil($total / $limit)
            ]
         ];
      } catch (Exception $e) {
         Helpers::logError('Contribution getAll error: ' . $e->getMessage());
         Helpers::sendError('Failed to retrieve contributions: ' . $e->getMessage());
      }
   }

   /**
    * Get the average contribution amount based on filters
    * @param array $filters Filters for contribution type, payment option, fiscal year, and date range
    * @return array Average contribution amount
    * @throws Exception if validation fails or database operations fail
    */
   public static function getAverage(array $filters = [])
   {
      $orm = new ORM();
      try {
         // Ensure at least one of start_date, end_date, or fiscal_year is provided
         if (empty($filters['start_date']) && empty($filters['end_date']) && empty($filters['fiscal_year'])) {
            Helpers::sendError('At least one of start_date, end_date, or fiscal_year is required.', 400);
         }

         // Validate date formats and values
         if (!empty($filters['start_date'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['start_date'])) {
               Helpers::sendError('Invalid start_date format (YYYY-MM-DD required).', 400);
            }
            $startDateObj = DateTime::createFromFormat('Y-m-d', $filters['start_date']);
            if (!$startDateObj || $startDateObj->format('Y-m-d') !== $filters['start_date']) {
               Helpers::sendError('Invalid start_date value.', 400);
            }
         }
         if (!empty($filters['end_date'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['end_date'])) {
               Helpers::sendError('Invalid end_date format (YYYY-MM-DD required).', 400);
            }
            $endDateObj = DateTime::createFromFormat('Y-m-d', $filters['end_date']);
            if (!$endDateObj || $endDateObj->format('Y-m-d') !== $filters['end_date']) {
               Helpers::sendError('Invalid end_date value.', 400);
            }
         }
         if (!empty($filters['start_date']) && !empty($filters['end_date']) && $filters['start_date'] > $filters['end_date']) {
            Helpers::sendError('start_date cannot be after end_date.', 400);
         }

         // Validate fiscal year and date range compatibility
         if (!empty($filters['fiscal_year'])) {
            if (!is_numeric($filters['fiscal_year'])) {
               Helpers::sendError('Invalid fiscal_year: Must be numeric.', 400);
            }
            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
               $fiscalYearData = $orm->runQuery(
                  'SELECT StartDate, EndDate FROM fiscalyear WHERE FiscalYearID = :fiscal_year',
                  [':fiscal_year' => $filters['fiscal_year']]
               );
               if (empty($fiscalYearData)) {
                  Helpers::sendError('Invalid fiscal_year: No fiscal year found.', 400);
               }
               $fiscalStart = $fiscalYearData[0]['StartDate'];
               $fiscalEnd = $fiscalYearData[0]['EndDate'];
               if ($filters['start_date'] < $fiscalStart || $filters['end_date'] > $fiscalEnd) {
                  Helpers::sendError(
                     "Date range must be within fiscal year boundaries ($fiscalStart to $fiscalEnd).",
                     400
                  );
               }
            }
         }

         // Validate payment_option and contribution_type
         if (!empty($filters['payment_option']) && !is_numeric($filters['payment_option'])) {
            Helpers::sendError('Invalid payment_option: Must be numeric.', 400);
         }
         if (!empty($filters['contribution_type']) && !is_numeric($filters['contribution_type'])) {
            Helpers::sendError('Invalid contribution_type: Must be numeric.', 400);
         }

         // Build WHERE clause
         $conditions = [];
         $params = [];
         if (!empty($filters['start_date'])) {
            $conditions[] = 'ContributionDate >= :start_date';
            $params[':start_date'] = $filters['start_date'];
         }
         if (!empty($filters['end_date'])) {
            $conditions[] = 'ContributionDate <= :end_date';
            $params[':end_date'] = $filters['end_date'];
         }
         if (!empty($filters['fiscal_year'])) {
            $conditions[] = 'FiscalYearID = :fiscal_year';
            $params[':fiscal_year'] = $filters['fiscal_year'];
         }
         if (!empty($filters['payment_option'])) {
            $conditions[] = 'PaymentOptionID = :payment_option';
            $params[':payment_option'] = $filters['payment_option'];
         }
         if (!empty($filters['contribution_type'])) {
            $conditions[] = 'ContributionTypeID = :contribution_type';
            $params[':contribution_type'] = $filters['contribution_type'];
         }
         if (!empty($filters['contributor_id'])) {
            $conditions[] = 'MbrID = :contributor_id';
            $params[':contributor_id'] = $filters['contributor_id'];
         }
         $conditions[] = 'Deleted = :deleted';
         $params[':deleted'] = 0;

         $whereSql = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';

         // Query to get single average
         $results = $orm->runQuery(
            'SELECT AVG(ContributionAmount) as average_contribution
             FROM contribution'
               . $whereSql,
            $params
         );

         // Format the result
         $average = !empty($results[0]['average_contribution']) ? (float)$results[0]['average_contribution'] : 0;
         return ['average_contribution' => number_format($average, 2)];
      } catch (Exception $e) {
         Helpers::logError('Contribution average error: ' . $e->getMessage());
         Helpers::sendError('Failed to retrieve average contribution: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Get the total contribution amount based on filters
    * @param array $filters Filters for contribution type, payment option, fiscal year, and date range
    * @return array Total contribution amount
    * @throws Exception if validation fails or database operations fail
    */
   public static function getTotal(array $filters = [])
   {
      $orm = new ORM();
      try {
         // Ensure at least one of start_date, end_date, or fiscal_year is provided
         if (empty($filters['start_date']) && empty($filters['end_date']) && empty($filters['fiscal_year'])) {
            Helpers::sendError('At least one of start_date, end_date, or fiscal_year is required.', 400);
         }

         // Validate date formats and values
         if (!empty($filters['start_date'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['start_date'])) {
               Helpers::sendError('Invalid start_date format (YYYY-MM-DD required).', 400);
            }
            $startDateObj = DateTime::createFromFormat('Y-m-d', $filters['start_date']);
            if (!$startDateObj || $startDateObj->format('Y-m-d') !== $filters['start_date']) {
               Helpers::sendError('Invalid start_date value.', 400);
            }
         }
         if (!empty($filters['end_date'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['end_date'])) {
               Helpers::sendError('Invalid end_date format (YYYY-MM-DD required).', 400);
            }
            $endDateObj = DateTime::createFromFormat('Y-m-d', $filters['end_date']);
            if (!$endDateObj || $endDateObj->format('Y-m-d') !== $filters['end_date']) {
               Helpers::sendError('Invalid end_date value.', 400);
            }
         }
         if (!empty($filters['start_date']) && !empty($filters['end_date']) && $filters['start_date'] > $filters['end_date']) {
            Helpers::sendError('start_date cannot be after end_date.', 400);
         }

         // Validate fiscal year and date range compatibility
         if (!empty($filters['fiscal_year'])) {
            if (!is_numeric($filters['fiscal_year'])) {
               Helpers::sendError('Invalid fiscal_year: Must be numeric.', 400);
            }
            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
               $fiscalYearData = $orm->runQuery(
                  'SELECT StartDate, EndDate FROM fiscalyear WHERE FiscalYearID = :fiscal_year',
                  [':fiscal_year' => $filters['fiscal_year']]
               );
               if (empty($fiscalYearData)) {
                  Helpers::sendError('Invalid fiscal_year: No fiscal year found.', 400);
               }
               $fiscalStart = $fiscalYearData[0]['StartDate'];
               $fiscalEnd = $fiscalYearData[0]['EndDate'];
               if ($filters['start_date'] < $fiscalStart || $filters['end_date'] > $fiscalEnd) {
                  Helpers::sendError(
                     "Date range must be within fiscal year boundaries ($fiscalStart to $fiscalEnd).",
                     400
                  );
               }
            }
         }

         // Validate payment_option, contribution_type, and contributor_id
         if (!empty($filters['payment_option']) && !is_numeric($filters['payment_option'])) {
            Helpers::sendError('Invalid payment_option: Must be numeric.', 400);
         }
         if (!empty($filters['contribution_type']) && !is_numeric($filters['contribution_type'])) {
            Helpers::sendError('Invalid contribution_type: Must be numeric.', 400);
         }
         if (!empty($filters['contributor_id']) && !is_numeric($filters['contributor_id'])) {
            Helpers::sendError('Invalid contributor_id: Must be numeric.', 400);
         }

         // Build WHERE clause
         $conditions = [];
         $params = [];
         if (!empty($filters['start_date'])) {
            $conditions[] = 'ContributionDate >= :start_date';
            $params[':start_date'] = $filters['start_date'];
         }
         if (!empty($filters['end_date'])) {
            $conditions[] = 'ContributionDate <= :end_date';
            $params[':end_date'] = $filters['end_date'];
         }
         if (!empty($filters['fiscal_year'])) {
            $conditions[] = 'FiscalYearID = :fiscal_year';
            $params[':fiscal_year'] = $filters['fiscal_year'];
         }
         if (!empty($filters['payment_option'])) {
            $conditions[] = 'PaymentOptionID = :payment_option';
            $params[':payment_option'] = $filters['payment_option'];
         }
         if (!empty($filters['contribution_type'])) {
            $conditions[] = 'ContributionTypeID = :contribution_type';
            $params[':contribution_type'] = $filters['contribution_type'];
         }
         if (!empty($filters['contributor_id'])) {
            $conditions[] = 'MbrID = :contributor_id';
            $params[':contributor_id'] = $filters['contributor_id'];
         }
         $conditions[] = 'Deleted = :deleted';
         $params[':deleted'] = 0;

         $whereSql = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';

         // Query to get total contribution
         $results = $orm->runQuery(
            'SELECT SUM(ContributionAmount) as total_contribution
             FROM contribution'
               . $whereSql,
            $params
         );

         // Format the result
         $total = !empty($results[0]['total_contribution']) ? (float)$results[0]['total_contribution'] : 0;
         return ['total_contribution' => number_format($total, 2)];
      } catch (Exception $e) {
         Helpers::logError('Contribution total error: ' . $e->getMessage());
         Helpers::sendError('Failed to retrieve total contribution: ' . $e->getMessage(), 400);
      }
   }
}
