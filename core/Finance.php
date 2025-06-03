<?php

/**
 * Finance Management Class
 * Handles financial operations such as generating income statements and budget vs actual reports.
 * Validates fiscal year existence and ensures data integrity.
 * Implements error handling and logging.
 * @package Finance
 */
class Finance
{
   /**
    * Get income statement for a specific fiscal year
    * @param int $fiscalYearId ID of the fiscal year
    * @return array Income statement data including contributions, expenses, and net income
    * @throws Exception if fiscal year is not found or database operations fail
    */
   public static function getIncomeStatement($fiscalYearId)
   {
      $orm = new ORM();
      try {
         // Validate fiscal year
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
         if (empty($fiscalYear)) {
            throw new Exception('Fiscal year not found');
         }

         // Total contributions
         $contributions = $orm->runQuery(
            "SELECT ct.ContributionTypeName, SUM(c.ContributionAmount) as total, COUNT(c.ContributionID) as count 
                 FROM contribution c 
                 JOIN contributiontype ct ON c.ContributionTypeID = ct.ContributionTypeID 
                 WHERE c.FiscalYearID = :fiscal_year_id 
                 GROUP BY c.ContributionTypeID",
            [':fiscal_year_id' => $fiscalYearId]
         );

         // Total expenses (approved only)
         $expenses = $orm->runQuery(
            "SELECT ec.ExpCategoryName, SUM(e.ExpAmount) as total, COUNT(e.ExpID) as count 
                 FROM expense e 
                 JOIN expensecategory ec ON e.ExpCategoryID = ec.ExpCategoryID 
                 WHERE e.FiscalYearID = :fiscal_year_id AND e.ExpStatus = 'Approved'
                 GROUP BY e.ExpCategoryID",
            [':fiscal_year_id' => $fiscalYearId]
         );

         $totalIncome = array_sum(array_column($contributions, 'total'));
         $totalExpenses = array_sum(array_column($expenses, 'total'));

         return [
            'fiscal_year_id' => $fiscalYearId,
            'income' => $contributions,
            'total_income' => $totalIncome,
            'expenses' => $expenses,
            'total_expenses' => $totalExpenses,
            'net_income' => $totalIncome - $totalExpenses
         ];
      } catch (Exception $e) {
         Helpers::logError('Income statement error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Get budget vs actual report for a specific fiscal year
    * @param int $fiscalYearId ID of the fiscal year
    * @return array Budget vs actual data including budgeted amounts, actual expenses, and counts
    * @throws Exception if fiscal year is not found or database operations fail
    */
   public static function getBudgetVsActual($fiscalYearId)
   {
      $orm = new ORM();
      try {
         // Validate fiscal year
         $fiscalYear = $orm->getWhere('fiscalyear', ['FiscalYearID' => $fiscalYearId]);
         if (empty($fiscalYear)) {
            throw new Exception('Fiscal year not found');
         }

         $results = $orm->runQuery(
            "SELECT b.ExpCategoryID, ec.ExpCategoryName, b.BudgetAmount, 
                        COALESCE(SUM(e.ExpAmount), 0) as actual_amount, 
                        COUNT(e.ExpID) as expense_count 
                 FROM budget b 
                 JOIN expensecategory ec ON b.ExpCategoryID = ec.ExpCategoryID 
                 LEFT JOIN expense e ON b.ExpCategoryID = e.ExpCategoryID 
                     AND e.FiscalYearID = b.FiscalYearID 
                     AND e.ExpStatus = 'Approved'
                 WHERE b.FiscalYearID = :fiscal_year_id 
                 GROUP BY b.ExpCategoryID",
            [':fiscal_year_id' => $fiscalYearId]
         );

         return ['data' => $results];
      } catch (Exception $e) {
         Helpers::logError('Budget vs actual error: ' . $e->getMessage());
         throw $e;
      }
   }
}
