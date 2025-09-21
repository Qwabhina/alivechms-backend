<?php

/**
 * Finance Management Class
 * Handles generation of financial reports including income statement, budget vs actual, expense summary,
 * contribution summary, and balance sheet. Supports date filtering for actual transactions.
 * Validates fiscal year existence and ensures data integrity. Implements error handling and logging
 * consistent with AliveChMS standards.
 */
class Finance
{
   /**
    * Generates an income statement for a specific fiscal year, with optional date filtering.
    * @param int $fiscalYearId The ID of the fiscal year.
    * @param string|null $dateFrom Optional start date for filtering (YYYY-MM-DD).
    * @param string|null $dateTo Optional end date for filtering (YYYY-MM-DD).
    * @return array Income statement data including contributions, expenses, and net income.
    */
   public static function getIncomeStatement($fiscalYearId, $dateFrom = null, $dateTo = null)
   {
      $orm = new ORM();
      try {
         // Validate fiscal year
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
         if (empty($fiscalYear)) {
            Helpers::logError('Finance getIncomeStatement error: Fiscal year not found');
            Helpers::sendFeedback('Fiscal year not found', 404);
         }

         // Validate dates if provided
         if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            Helpers::logError('Finance getIncomeStatement error: Invalid date_from format. Use YYYY-MM-DD');
            Helpers::sendFeedback('Invalid date_from format. Use YYYY-MM-DD', 400);
         }
         if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            Helpers::logError('Finance getIncomeStatement error: Invalid date_to format. Use YYYY-MM-DD');
            Helpers::sendFeedback('Invalid date_to format. Use YYYY-MM-DD', 400);
         }

         // Total contributions (excluding deleted), with date filter
         $params = [':fiscal_year_id' => $fiscalYearId];
         $dateConditions = '';
         if ($dateFrom || $dateTo) {
            $dateConditions = ' AND c.ContributionDate ';
            if ($dateFrom && $dateTo) {
               $dateConditions .= 'BETWEEN :date_from AND :date_to';
               $params[':date_from'] = $dateFrom;
               $params[':date_to'] = $dateTo;
            } elseif ($dateFrom) {
               $dateConditions .= '>= :date_from';
               $params[':date_from'] = $dateFrom;
            } elseif ($dateTo) {
               $dateConditions .= '<= :date_to';
               $params[':date_to'] = $dateTo;
            }
         }

         $contributions = $orm->runQuery(
            "SELECT ct.ContributionTypeName, SUM(c.ContributionAmount) as total, COUNT(c.ContributionID) as count 
             FROM contribution c 
             JOIN contributiontype ct ON c.ContributionTypeID = ct.ContributionTypeID 
             WHERE c.FiscalYearID = :fiscal_year_id AND c.Deleted = '0' $dateConditions
             GROUP BY c.ContributionTypeID",
            $params
         );

         // Total expenses (approved only), with date filter
         $params = [':fiscal_year_id' => $fiscalYearId];
         if ($dateFrom || $dateTo) {
            $params[':date_from'] = $dateFrom ?? '';
            $params[':date_to'] = $dateTo ?? '';
         }

         $expenses = $orm->runQuery(
            "SELECT ec.ExpCategoryName, SUM(e.ExpAmount) as total, COUNT(e.ExpID) as count 
             FROM expense e 
             JOIN expensecategory ec ON e.ExpCategoryID = ec.ExpCategoryID 
             WHERE e.FiscalYearID = :fiscal_year_id AND e.ExpStatus = 'Approved' $dateConditions
             GROUP BY e.ExpCategoryID",
            $params
         );

         $totalIncome = array_sum(array_column($contributions, 'total')) ?? 0;
         $totalExpenses = array_sum(array_column($expenses, 'total')) ?? 0;

         return [
            'status' => 'success',
            'fiscal_year_id' => $fiscalYearId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'income' => $contributions,
            'total_income' => $totalIncome,
            'expenses' => $expenses,
            'total_expenses' => $totalExpenses,
            'net_income' => $totalIncome - $totalExpenses
         ];
      } catch (Exception $e) {
         Helpers::logError('Finance getIncomeStatement error: ' . $e->getMessage());
         Helpers::sendFeedback('Income statement generation failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Generates a budget vs actual report for a specific fiscal year, including budget items and date-filtered actuals.
    * @param int $fiscalYearId The ID of the fiscal year.
    * @param string|null $dateFrom Optional start date for filtering actuals (YYYY-MM-DD).
    * @param string|null $dateTo Optional end date for filtering actuals (YYYY-MM-DD).
    * @return array Budget vs actual data including budgeted items, actual expenses, and variances.
    */
   public static function getBudgetVsActual($fiscalYearId, $dateFrom = null, $dateTo = null)
   {
      $orm = new ORM();
      try {
         // Validate fiscal year
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
         if (empty($fiscalYear)) {
            Helpers::logError('Finance getBudgetVsActual error: Fiscal year not found');
            Helpers::sendFeedback('Fiscal year not found', 404);
         }

         // Validate dates if provided
         if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            Helpers::logError('Finance getBudgetVsActual error: Invalid date_from format. Use YYYY-MM-DD');
            Helpers::sendFeedback('Invalid date_from format. Use YYYY-MM-DD', 400);
         }
         if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            Helpers::logError('Finance getBudgetVsActual error: Invalid date_to format. Use YYYY-MM-DD');
            Helpers::sendFeedback('Invalid date_to format. Use YYYY-MM-DD', 400);
         }

         // Budget items (expenses only, approved budgets)
         $budgetItems = $orm->runQuery(
            "SELECT bi.ItemID, bi.ItemName, bi.Amount as budgeted_amount, bic.SubcategoryName, bic.Category,
                    cb.BudgetTitle 
             FROM budget_items bi 
             JOIN budget_item_category bic ON bi.SubcategoryID = bic.SubcategoryID 
             JOIN churchbudget cb ON bi.BudgetID = cb.BudgetID 
             WHERE cb.FiscalYearID = :fiscal_year_id AND cb.BudgetStatus = 'Approved' AND bic.Category = 'Expense'
             ORDER BY bic.SubcategoryName, bi.ItemName",
            [':fiscal_year_id' => $fiscalYearId]
         );

         // Actual expenses with date filter
         $params = [':fiscal_year_id' => $fiscalYearId];
         $dateConditions = '';
         if ($dateFrom || $dateTo) {
            $dateConditions = ' AND e.ExpDate ';
            if ($dateFrom && $dateTo) {
               $dateConditions .= 'BETWEEN :date_from AND :date_to';
               $params[':date_from'] = $dateFrom;
               $params[':date_to'] = $dateTo;
            } elseif ($dateFrom) {
               $dateConditions .= '>= :date_from';
               $params[':date_from'] = $dateFrom;
            } elseif ($dateTo) {
               $dateConditions .= '<= :date_to';
               $params[':date_to'] = $dateTo;
            }
         }

         $actualExpenses = $orm->runQuery(
            "SELECT e.ExpID, e.ExpTitle, e.ExpAmount as actual_amount, e.ExpDate, e.ExpStatus,
                    ec.ExpCategoryName 
             FROM expense e 
             JOIN expensecategory ec ON e.ExpCategoryID = ec.ExpCategoryID 
             WHERE e.FiscalYearID = :fiscal_year_id AND e.ExpStatus = 'Approved' $dateConditions
             ORDER BY ec.ExpCategoryName, e.ExpTitle",
            $params
         );

         // For variance, aggregate budgets and actuals by subcategory (assuming ExpCategoryID aligns with SubcategoryID)
         $budgetSummary = [];
         foreach ($budgetItems as $item) {
            $key = $item['SubcategoryName'];
            if (!isset($budgetSummary[$key])) {
               $budgetSummary[$key] = ['budgeted' => 0, 'items' => []];
            }
            $budgetSummary[$key]['budgeted'] += $item['budgeted_amount'];
            $budgetSummary[$key]['items'][] = $item;
         }

         $actualSummary = [];
         foreach ($actualExpenses as $exp) {
            $key = $exp['ExpCategoryName'];
            if (!isset($actualSummary[$key])) {
               $actualSummary[$key] = ['actual' => 0, 'items' => []];
            }
            $actualSummary[$key]['actual'] += $exp['actual_amount'];
            $actualSummary[$key]['items'][] = $exp;
         }

         // Combine for variance
         $combined = [];
         $allKeys = array_unique(array_merge(array_keys($budgetSummary), array_keys($actualSummary)));
         foreach ($allKeys as $key) {
            $budgeted = $budgetSummary[$key]['budgeted'] ?? 0;
            $actual = $actualSummary[$key]['actual'] ?? 0;
            $combined[$key] = [
               'budgeted_amount' => $budgeted,
               'actual_amount' => $actual,
               'variance' => $budgeted - $actual,
               'budget_items' => $budgetSummary[$key]['items'] ?? [],
               'actual_items' => $actualSummary[$key]['items'] ?? []
            ];
         }

         return [
            'status' => 'success',
            'fiscal_year_id' => $fiscalYearId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'summary' => array_values($combined),
            'budget_items' => $budgetItems,
            'actual_expenses' => $actualExpenses
         ];
      } catch (Exception $e) {
         Helpers::logError('Finance getBudgetVsActual error: ' . $e->getMessage());
         Helpers::sendFeedback('Budget vs actual report generation failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Generates an expense summary for a specific fiscal year, with optional date filtering.
    * @param int $fiscalYearId The ID of the fiscal year.
    * @param string|null $dateFrom Optional start date for filtering (YYYY-MM-DD).
    * @param string|null $dateTo Optional end date for filtering (YYYY-MM-DD).
    * @return array Expense summary data grouped by category and status.
    */
   public static function getExpenseSummary($fiscalYearId, $dateFrom = null, $dateTo = null)
   {
      $orm = new ORM();
      try {
         // Validate fiscal year
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
         if (empty($fiscalYear)) {
            Helpers::logError('Finance getExpenseSummary error: Fiscal year not found');
            Helpers::sendFeedback('Fiscal year not found', 404);
         }

         // Validate dates if provided
         if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            Helpers::logError('Finance getExpenseSummary error: Invalid date_from format. Use YYYY-MM-DD');
            Helpers::sendFeedback('Invalid date_from format. Use YYYY-MM-DD', 400);
         }
         if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            Helpers::logError('Finance getExpenseSummary error: Invalid date_to format. Use YYYY-MM-DD');
            Helpers::sendFeedback('Invalid date_to format. Use YYYY-MM-DD', 400);
         }

         $params = [':fiscal_year_id' => $fiscalYearId];
         $dateConditions = '';
         if ($dateFrom || $dateTo) {
            $dateConditions = ' AND e.ExpDate ';
            if ($dateFrom && $dateTo) {
               $dateConditions .= 'BETWEEN :date_from AND :date_to';
               $params[':date_from'] = $dateFrom;
               $params[':date_to'] = $dateTo;
            } elseif ($dateFrom) {
               $dateConditions .= '>= :date_from';
               $params[':date_from'] = $dateFrom;
            } elseif ($dateTo) {
               $dateConditions .= '<= :date_to';
               $params[':date_to'] = $dateTo;
            }
         }

         $results = $orm->runQuery(
            "SELECT ec.ExpCategoryName, e.ExpStatus, 
                    SUM(e.ExpAmount) as total_amount, 
                    COUNT(e.ExpID) as expense_count 
             FROM expense e 
             JOIN expensecategory ec ON e.ExpCategoryID = ec.ExpCategoryID 
             WHERE e.FiscalYearID = :fiscal_year_id $dateConditions
             GROUP BY ec.ExpCategoryName, e.ExpStatus
             ORDER BY ec.ExpCategoryName, e.ExpStatus",
            $params
         );

         $total = array_sum(array_column($results, 'total_amount')) ?? 0;

         return [
            'status' => 'success',
            'fiscal_year_id' => $fiscalYearId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'data' => $results,
            'total_expenses' => $total
         ];
      } catch (Exception $e) {
         Helpers::logError('Finance getExpenseSummary error: ' . $e->getMessage());
         Helpers::sendFeedback('Expense summary generation failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Generates a contribution summary for a specific fiscal year, with optional date filtering.
    * @param int $fiscalYearId The ID of the fiscal year.
    * @param string|null $dateFrom Optional start date for filtering (YYYY-MM-DD).
    * @param string|null $dateTo Optional end date for filtering (YYYY-MM-DD).
    * @return array Contribution summary data grouped by contribution type.
    */
   public static function getContributionSummary($fiscalYearId, $dateFrom = null, $dateTo = null)
   {
      $orm = new ORM();
      try {
         // Validate fiscal year
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
         if (empty($fiscalYear)) {
            Helpers::logError('Finance getContributionSummary error: Fiscal year not found');
            Helpers::sendFeedback('Fiscal year not found', 404);
         }

         // Validate dates if provided
         if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            Helpers::logError('Finance getContributionSummary error: Invalid date_from format. Use YYYY-MM-DD');
            Helpers::sendFeedback('Invalid date_from format. Use YYYY-MM-DD', 400);
         }
         if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            Helpers::logError('Finance getContributionSummary error: Invalid date_to format. Use YYYY-MM-DD');
            Helpers::sendFeedback('Invalid date_to format. Use YYYY-MM-DD', 400);
         }

         $params = [':fiscal_year_id' => $fiscalYearId];
         $dateConditions = '';
         if ($dateFrom || $dateTo) {
            $dateConditions = ' AND c.ContributionDate ';
            if ($dateFrom && $dateTo) {
               $dateConditions .= 'BETWEEN :date_from AND :date_to';
               $params[':date_from'] = $dateFrom;
               $params[':date_to'] = $dateTo;
            } elseif ($dateFrom) {
               $dateConditions .= '>= :date_from';
               $params[':date_from'] = $dateFrom;
            } elseif ($dateTo) {
               $dateConditions .= '<= :date_to';
               $params[':date_to'] = $dateTo;
            }
         }

         $results = $orm->runQuery(
            "SELECT ct.ContributionTypeName, 
                    SUM(c.ContributionAmount) as total_amount, 
                    COUNT(c.ContributionID) as contribution_count 
             FROM contribution c 
             JOIN contributiontype ct ON c.ContributionTypeID = ct.ContributionTypeID 
             WHERE c.FiscalYearID = :fiscal_year_id AND c.Deleted = '0' $dateConditions
             GROUP BY ct.ContributionTypeName
             ORDER BY ct.ContributionTypeName",
            $params
         );

         $total = array_sum(array_column($results, 'total_amount')) ?? 0;

         return [
            'status' => 'success',
            'fiscal_year_id' => $fiscalYearId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'data' => $results,
            'total_contributions' => $total
         ];
      } catch (Exception $e) {
         Helpers::logError('Finance getContributionSummary error: ' . $e->getMessage());
         Helpers::sendFeedback('Contribution summary generation failed: ' . $e->getMessage(), 400);
      }
   }

   /**
    * Generates a balance sheet for a specific fiscal year, with optional date filtering for transactions.
    * @param int $fiscalYearId The ID of the fiscal year.
    * @param string|null $dateFrom Optional start date for filtering (YYYY-MM-DD).
    * @param string|null $dateTo Optional end date for filtering (YYYY-MM-DD).
    * @return array Balance sheet data including assets, liabilities, and net assets.
    */
   public static function getBalanceSheet($fiscalYearId, $dateFrom = null, $dateTo = null)
   {
      $orm = new ORM();
      try {
         // Validate fiscal year
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
         if (empty($fiscalYear)) {
            Helpers::logError('Finance getBalanceSheet error: Fiscal year not found');
            Helpers::sendFeedback('Fiscal year not found', 404);
         }

         // Validate dates if provided
         if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            Helpers::logError('Finance getBalanceSheet error: Invalid date_from format. Use YYYY-MM-DD');
            Helpers::sendFeedback('Invalid date_from format. Use YYYY-MM-DD', 400);
         }
         if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            Helpers::logError('Finance getBalanceSheet error: Invalid date_to format. Use YYYY-MM-DD');
            Helpers::sendFeedback('Invalid date_to format. Use YYYY-MM-DD', 400);
         }

         $params = [':fiscal_year_id' => $fiscalYearId];
         $dateConditions = '';
         if ($dateFrom || $dateTo) {
            $dateConditions = ' AND c.ContributionDate ';
            if ($dateFrom && $dateTo) {
               $dateConditions .= 'BETWEEN :date_from AND :date_to';
               $params[':date_from'] = $dateFrom;
               $params[':date_to'] = $dateTo;
            } elseif ($dateFrom) {
               $dateConditions .= '>= :date_from';
               $params[':date_from'] = $dateFrom;
            } elseif ($dateTo) {
               $dateConditions .= '<= :date_to';
               $params[':date_to'] = $dateTo;
            }
         }

         // Assets (total contributions, assuming contributions represent cash inflows)
         $contributions = $orm->runQuery(
            "SELECT SUM(c.ContributionAmount) as total 
             FROM contribution c 
             WHERE c.FiscalYearID = :fiscal_year_id AND c.Deleted = '0' $dateConditions",
            $params
         )[0]['total'] ?? 0;

         // Liabilities (pending expenses), with date filter
         $liabilitiesParams = [':fiscal_year_id' => $fiscalYearId];
         $liabilitiesDateConditions = str_replace('c.ContributionDate', 'e.ExpDate', $dateConditions);
         if ($dateFrom || $dateTo) {
            $liabilitiesParams[':date_from'] = $dateFrom ?? '';
            $liabilitiesParams[':date_to'] = $dateTo ?? '';
         }

         $liabilities = $orm->runQuery(
            "SELECT SUM(e.ExpAmount) as total 
             FROM expense e 
             WHERE e.FiscalYearID = :fiscal_year_id AND e.ExpStatus = 'Pending Approval' $liabilitiesDateConditions",
            $liabilitiesParams
         )[0]['total'] ?? 0;

         // Net assets (total contributions minus approved expenses), with date filter for approved
         $approvedExpensesParams = [':fiscal_year_id' => $fiscalYearId];
         $approvedExpensesDateConditions = str_replace('c.ContributionDate', 'e.ExpDate', $dateConditions);
         if ($dateFrom || $dateTo) {
            $approvedExpensesParams[':date_from'] = $dateFrom ?? '';
            $approvedExpensesParams[':date_to'] = $dateTo ?? '';
         }

         $approvedExpenses = $orm->runQuery(
            "SELECT SUM(e.ExpAmount) as total 
             FROM expense e 
             WHERE e.FiscalYearID = :fiscal_year_id AND e.ExpStatus = 'Approved' $approvedExpensesDateConditions",
            $approvedExpensesParams
         )[0]['total'] ?? 0;

         $netAssets = $contributions - $approvedExpenses;

         return [
            'status' => 'success',
            'fiscal_year_id' => $fiscalYearId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'assets' => [
               'cash' => $contributions,
               'total_assets' => $contributions
            ],
            'liabilities' => [
               'pending_expenses' => $liabilities,
               'total_liabilities' => $liabilities
            ],
            'net_assets' => $netAssets
         ];
      } catch (Exception $e) {
         Helpers::logError('Finance getBalanceSheet error: ' . $e->getMessage());
         Helpers::sendFeedback('Balance sheet generation failed: ' . $e->getMessage(), 400);
      }
   }
}