<?php

/**
 * Dashboard Analytics Class
 *
 * Provides comprehensive real-time statistics and insights for church leadership.
 * All data is branch-aware and respects user permissions.
 *
 * @package AliveChMS\Core
 * @version 1.0.0
 * @author  Benjamin Ebo Yankson
 * @since   2025-11-21
 */

declare(strict_types=1);

class Dashboard
{
   /**
    * Get complete dashboard overview for the authenticated user
    *
    * Automatically respects branch access based on user's branch
    *
    * @return array Complete dashboard data
    */
   public static function getOverview(): array
   {
      $orm = new ORM();
      $currentUserId = Auth::getCurrentUserId($token ?? '');
      $userBranchId = Auth::getUserBranchId($currentUserId); // Helper from Auth class

      $today = date('Y-m-d');
      $thisMonthStart = date('Y-m-01');
      $thisYearStart = date('Y-01-01');

      // === MEMBERSHIP STATISTICS ===
      $members = $orm->runQuery(
         "SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN MbrRegistrationDate >= :today THEN 1 ELSE 0 END) AS today,
                SUM(CASE WHEN MbrRegistrationDate >= :month THEN 1 ELSE 0 END) AS this_month,
                SUM(CASE WHEN MbrRegistrationDate >= :year THEN 1 ELSE 0 END) AS this_year
             FROM churchmember 
             WHERE Deleted = 0 AND MbrMembershipStatus = 'Active'
               AND BranchID = :branch",
         [':today' => $today, ':month' => $thisMonthStart, ':year' => $thisYearStart, ':branch' => $userBranchId]
      )[0];

      // === FINANCIAL SUMMARY (Current Fiscal Year) ===
      $fiscalYear = $orm->runQuery(
         "SELECT FiscalYearID FROM fiscalyear WHERE :today BETWEEN FiscalYearStartDate AND FiscalYearEndDate AND Status = 'Active' LIMIT 1",
         [':today' => $today]
      );

      $fyId = !empty($fiscalYear) ? $fiscalYear[0]['FiscalYearID'] : null;

      $finance = ['income' => 0, 'expenses' => 0, 'net' => 0];
      if ($fyId) {
         $income = $orm->runQuery(
            "SELECT COALESCE(SUM(ContributionAmount), 0) AS total 
                 FROM contribution 
                 WHERE FiscalYearID = :fy AND Deleted = 0 AND BranchID = :branch",
            [':fy' => $fyId, ':branch' => $userBranchId]
         )[0]['total'];

         $expenses = $orm->runQuery(
            "SELECT COALESCE(SUM(ExpenseAmount), 0) AS total 
                 FROM expense 
                 WHERE FiscalYearID = :fy AND ExpenseStatus = 'Approved' AND BranchID = :branch",
            [':fy' => $fyId, ':branch' => $userBranchId]
         )[0]['total'];

         $finance = [
            'income'   => number_format((float)$income, 2),
            'expenses' => number_format((float)$expenses, 2),
            'net'      => number_format((float)$income - (float)$expenses, 2)
         ];
      }

      // === ATTENDANCE (Last 4 Sundays) ===
      $attendance = $orm->runQuery(
         "SELECT 
                DATE(EventDate) AS date,
                COALESCE(SUM(CASE WHEN AttendanceStatus = 'Present' THEN 1 ELSE 0 END), 0) AS present
             FROM event e
             LEFT JOIN event_attendance ea ON e.EventID = ea.EventID
             WHERE e.BranchID = :branch
               AND e.EventDate <= :today
               AND DAYOFWEEK(e.EventDate) = 1  -- Sunday
             GROUP BY e.EventDate
             ORDER BY e.EventDate DESC
             LIMIT 4",
         [':branch' => $userBranchId, ':today' => $today]
      );

      // === UPCOMING EVENTS (Next 7 days) ===
      $upcomingEvents = $orm->runQuery(
         "SELECT EventID, EventTitle, EventDate, StartTime, Location
             FROM event
             WHERE BranchID = :branch
               AND EventDate BETWEEN :today AND DATE_ADD(:today, INTERVAL 7 DAY)
             ORDER BY EventDate, StartTime
             LIMIT 5",
         [':branch' => $userBranchId, ':today' => $today]
      );

      // === PENDING APPROVALS ===
      $pending = [
         'budgets'   => (int)$orm->runQuery("SELECT COUNT(*) AS cnt FROM budget WHERE BudgetStatus = 'Submitted' AND BranchID = :br", [':br' => $userBranchId])[0]['cnt'],
         'expenses'  => (int)$orm->runQuery("SELECT COUNT(*) AS cnt FROM expense WHERE ExpenseStatus = 'Pending Approval' AND BranchID = :br", [':br' => $userBranchId])[0]['cnt'],
         'pledges'   => (int)$orm->runQuery("SELECT COUNT(*) AS cnt FROM pledge WHERE PledgeStatus = 'Active'", [])[0]['cnt']
      ];

      // === RECENT ACTIVITY (Last 10 actions) ===
      $activity = $orm->runQuery(
         "SELECT 'Member Registered' AS type, CONCAT(m.MbrFirstName, ' ', m.MbrFamilyName) AS description, m.MbrRegistrationDate AS timestamp
             FROM churchmember m WHERE m.BranchID = :br AND m.MbrRegistrationDate >= DATE_SUB(:today, INTERVAL 7 DAY)
             UNION ALL
             SELECT 'Contribution' AS type, CONCAT('GHS ', c.ContributionAmount) AS description, c.ContributionDate AS timestamp
             FROM contribution c WHERE c.BranchID = :br AND c.ContributionDate >= DATE_SUB(:today, INTERVAL 7 DAY)
             UNION ALL
             SELECT 'Event Created' AS type, e.EventTitle AS description, e.CreatedAt AS timestamp
             FROM event e WHERE e.BranchID = :br AND e.CreatedAt >= DATE_SUB(:today, INTERVAL 7 DAY)
             ORDER BY timestamp DESC
             LIMIT 10",
         [':br' => $userBranchId, ':today' => $today]
      );

      return [
         'membership' => [
            'total'       => (int)$members['total'],
            'new_today'   => (int)$members['today'],
            'new_this_month' => (int)$members['this_month'],
            'new_this_year'  => (int)$members['this_year']
         ],
         'finance' => $finance,
         'attendance_last_4_sundays' => array_reverse($attendance),
         'upcoming_events' => $upcomingEvents,
         'pending_approvals' => $pending,
         'recent_activity' => $activity,
         'generated_at' => date('c')
      ];
   }
}