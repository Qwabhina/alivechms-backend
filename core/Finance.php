<?php

/**
 * Finance Reporting Class
 *
 * Generates key financial reports:
 * - Income Statement
 * - Budget vs Actual
 * - Expense Summary
 * - Contribution Summary
 * - Balance Sheet
 *
 * All reports are fiscal-year based with optional date filtering.
 *
 * @package AliveChMS\Core
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

declare(strict_types=1);

class Finance
{
   /**
    * Generate Income Statement
    *
    * @param int         $fiscalYearId Fiscal Year ID
    * @param string|null $dateFrom     Optional start date (YYYY-MM-DD)
    * @param string|null $dateTo       Optional end date (YYYY-MM-DD)
    * @return array Report data
    */
   public static function getIncomeStatement(int $fiscalYearId, ?string $dateFrom = null, ?string $dateTo = null): array
   {
      $orm = new ORM();

      // Validate fiscal year
      $fy = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId, 'Status' => 'Active']);
      if (empty($fy)) {
         Helpers::sendFeedback('Invalid or inactive fiscal year', 400);
      }

      $params = [':fy' => $fiscalYearId];
      $where  = "c.FiscalYearID = :fy AND c.Deleted = 0";

      if ($dateFrom) {
         $where .= " AND c.ContributionDate >= :from";
         $params[':from'] = $dateFrom;
      }
      if ($dateTo) {
         $where .= " AND c.ContributionDate <= :to";
         $params[':to'] = $dateTo;
      }

      $contributions = $orm->runQuery(
         "SELECT ct.ContributionTypeName, SUM(c.ContributionAmount) AS total
             FROM contribution c
             JOIN contributiontype ct ON c.ContributionTypeID = ct.ContributionTypeID
             WHERE $where
             GROUP BY ct.ContributionTypeID",
            $params
      );

      $expenses = $orm->runQuery(
         "SELECT ec.CategoryName, SUM(e.ExpenseAmount) AS total
             FROM expense e
             JOIN expense_category ec ON e.ExpenseCategoryID = ec.ExpenseCategoryID
             WHERE e.FiscalYearID = :fy AND e.ExpenseStatus = 'Approved'
             GROUP BY ec.ExpenseCategoryID",
         [':fy' => $fiscalYearId]
      );

      $totalIncome = array_sum(array_column($contributions, 'total'));
      $totalExpense = array_sum(array_column($expenses, 'total'));

      return [
         'fiscal_year'     => $fy[0]['YearName'],
         'income'          => $contributions,
         'total_income'    => number_format($totalIncome, 2),
         'expenses'        => $expenses,
         'total_expenses'  => number_format($totalExpense, 2),
         'net_surplus'     => number_format($totalIncome - $totalExpense, 2)
      ];
   }

   /**
    * Generate Budget vs Actual Report
    *
    * @param int         $fiscalYearId Fiscal Year ID
    * @param string|null $dateFrom     Optional start date
    * @param string|null $dateTo       Optional end date
    * @return array Report data
    */
   public static function getBudgetVsActual(int $fiscalYearId, ?string $dateFrom = null, ?string $dateTo = null): array
   {
      $orm = new ORM();

      $budgets = $orm->runQuery(
         "SELECT bi.Category, SUM(bi.Amount) AS budgeted
             FROM budget_items bi
             JOIN budget b ON bi.BudgetID = b.BudgetID
             WHERE b.FiscalYearID = :fy AND b.BudgetStatus = 'Approved'
             GROUP BY bi.Category",
         [':fy' => $fiscalYearId]
      );

      $params = [':fy' => $fiscalYearId];
      $where  = "e.FiscalYearID = :fy AND e.ExpenseStatus = 'Approved'";

      if ($dateFrom) {
         $where .= " AND e.ExpenseDate >= :from";
         $params[':from'] = $dateFrom;
      }
      if ($dateTo) {
         $where .= " AND e.ExpenseDate <= :to";
         $params[':to'] = $dateTo;
      }

      $actual = $orm->runQuery(
         "SELECT ec.CategoryName AS Category, SUM(e.ExpenseAmount) AS actual
             FROM expense e
             JOIN expense_category ec ON e.ExpenseCategoryID = ec.ExpenseCategoryID
             WHERE $where
             GROUP BY ec.CategoryName",
            $params
      );

      // Merge budgeted and actual
      $report = [];
      foreach ($budgets as $b) {
         $report[$b['Category']] = [
            'category'   => $b['Category'],
            'budgeted'   => (float)$b['budgeted'],
            'actual'     => 0.0,
            'variance'   => 0.0
         ];
      }
      foreach ($actual as $a) {
         if (!isset($report[$a['Category']])) {
            $report[$a['Category']] = ['category' => $a['Category'], 'budgeted' => 0.0];
         }
         $report[$a['Category']]['actual'] = (float)$a['actual'];
         $report[$a['Category']]['variance'] = $report[$a['Category']]['budgeted'] - (float)$a['actual'];
      }

      return ['data' => array_values($report)];
   }

   /**
    * Generate Expense Summary by Category
    *
    * @param int         $fiscalYearId Fiscal Year ID
    * @param string|null $dateFrom     Optional start date
    * @param string|null $dateTo       Optional end date
    * @return array Report data
    */
   public static function getExpenseSummary(int $fiscalYearId, ?string $dateFrom = null, ?string $dateTo = null): array
   {
      $orm = new ORM();

      $params = [':fy' => $fiscalYearId];
      $where  = "e.FiscalYearID = :fy AND e.ExpenseStatus = 'Approved'";

      if ($dateFrom) {
         $where .= " AND e.ExpenseDate >= :from";
         $params[':from'] = $dateFrom;
      }
      if ($dateTo) {
         $where .= " AND e.ExpenseDate <= :to";
         $params[':to'] = $dateTo;
      }

      $summary = $orm->runQuery(
         "SELECT ec.CategoryName, SUM(e.ExpenseAmount) AS total, COUNT(e.ExpenseID) AS count
             FROM expense e
             JOIN expense_category ec ON e.ExpenseCategoryID = ec.ExpenseCategoryID
             WHERE $where
             GROUP BY ec.CategoryName
             ORDER BY total DESC",
            $params
      );

      $grandTotal = array_sum(array_column($summary, 'total'));

      return [
         'summary' => $summary,
         'grand_total' => number_format($grandTotal, 2)
      ];
   }

   /**
    * Generate Contribution Summary by Type
    *
    * @param int         $fiscalYearId Fiscal Year ID
    * @param string|null $dateFrom     Optional start date
    * @param string|null $dateTo       Optional end date
    * @return array Report data
    */
   public static function getContributionSummary(int $fiscalYearId, ?string $dateFrom = null, ?string $dateTo = null): array
   {
      $orm = new ORM();

      $params = [':fy' => $fiscalYearId];
      $where  = "c.FiscalYearID = :fy AND c.Deleted = 0";

      if ($dateFrom) {
         $where .= " AND c.ContributionDate >= :from";
         $params[':from'] = $dateFrom;
      }
      if ($dateTo) {
         $where .= " AND c.ContributionDate <= :to";
         $params[':to'] = $dateTo;
      }

      $summary = $orm->runQuery(
         "SELECT ct.ContributionTypeName, SUM(c.ContributionAmount) AS total, COUNT(c.ContributionID) AS count
             FROM contribution c
             JOIN contributiontype ct ON c.ContributionTypeID = ct.ContributionTypeID
             WHERE $where
             GROUP BY ct.ContributionTypeID
             ORDER BY total DESC",
            $params
      );

      $grandTotal = array_sum(array_column($summary, 'total'));

      return [
         'summary' => $summary,
         'grand_total' => number_format($grandTotal, 2)
      ];
   }

   /**
    * Generate Simple Balance Sheet
    *
    * @param int         $fiscalYearId Fiscal Year ID
    * @param string|null $dateFrom     Optional start date
    * @param string|null $dateTo       Optional end date
    * @return array Balance sheet data
    */
   public static function getBalanceSheet(int $fiscalYearId, ?string $dateFrom = null, ?string $dateTo = null): array
   {
      $income = self::getContributionSummary($fiscalYearId, $dateFrom, $dateTo)['grand_total'];
      $expense = self::getExpenseSummary($fiscalYearId, $dateFrom, $dateTo)['grand_total'];

      $net = (float)$income - (float)$expense;

      return [
            'assets' => [
            'cash_in_hand' => $income
            ],
            'liabilities' => [
            'approved_expenses' => $expense
            ],
         'net_assets' => number_format($net, 2)
      ];
   }
}