<?php

/**
 * Dashboard Analytics
 *
 * Provides comprehensive real-time overview for church leadership:
 * membership stats, finance summary, attendance trends,
 * upcoming events, pending approvals, and recent activity.
 *
 * All data is branch-aware and respects current user context.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class Dashboard
{
   /**
    * Generate complete dashboard overview for the authenticated user
    *
    * @return array Dashboard data
    */
   public static function getOverview(): array
   {
      $orm          = new ORM();
      $currentUserId = Auth::getCurrentUserId();
      $branchId     = Auth::getUserBranchId($currentUserId);

      $today        = date('Y-m-d');
      $monthStart   = date('Y-m-01');
      $yearStart    = date('Y-01-01');

      // Membership Statistics
      $membership = $orm->runQuery(
         "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN MbrRegistrationDate >= :today THEN 1 ELSE 0 END) AS new_today,
                SUM(CASE WHEN MbrRegistrationDate >= :month THEN 1 ELSE 0 END) AS new_this_month,
                SUM(CASE WHEN MbrRegistrationDate >= :year THEN 1 ELSE 0 END) AS new_this_year
             FROM churchmember
             WHERE Deleted = 0
               AND MbrMembershipStatus = 'Active'
               AND BranchID = :branch",
         [':today' => $today, ':month' => $monthStart, ':year' => $yearStart, ':branch' => $branchId]
      )[0];

      // Financial Summary (Current Active Fiscal Year)
      $fiscalYear = $orm->runQuery(
         "SELECT FiscalYearID FROM fiscalyear
             WHERE :today BETWEEN FiscalYearStartDate AND FiscalYearEndDate
               AND Status = 'Active' AND BranchID = :branch
             LIMIT 1",
         [':today' => $today, ':branch' => $branchId]
      );

      $finance = ['income' => '0.00', 'expenses' => '0.00', 'net' => '0.00'];
      if (!empty($fiscalYear)) {
         $fyId = $fiscalYear[0]['FiscalYearID'];

         $income = $orm->runQuery(
            "SELECT COALESCE(SUM(ContributionAmount), 0) AS total
                 FROM contribution
                 WHERE FiscalYearID = :fy AND Deleted = 0 AND BranchID = :branch",
            [':fy' => $fyId, ':branch' => $branchId]
         )[0]['total'];

         $expenses = $orm->runQuery(
            "SELECT COALESCE(SUM(ExpenseAmount), 0) AS total
                 FROM expense
                 WHERE FiscalYearID = :fy AND ExpenseStatus = 'Approved' AND BranchID = :branch",
            [':fy' => $fyId, ':branch' => $branchId]
         )[0]['total'];

         $finance = [
            'income'   => number_format((float)$income, 2),
            'expenses' => number_format((float)$expenses, 2),
            'net'      => number_format((float)$income - (float)$expenses, 2)
         ];
      }

      // Last 4 Sundays Attendance
      $attendance = $orm->runQuery(
         "SELECT
                DATE(e.EventDate) AS date,
                COALESCE(SUM(CASE WHEN ea.AttendanceStatus = 'Present' THEN 1 ELSE 0 END), 0) AS present
             FROM event e
             LEFT JOIN event_attendance ea ON e.EventID = ea.EventID
             WHERE e.BranchID = :branch
               AND e.EventDate <= :today
               AND DAYOFWEEK(e.EventDate) = 1
             GROUP BY e.EventDate
             ORDER BY e.EventDate DESC
             LIMIT 4",
         [':branch' => $branchId, ':today' => $today]
      );

      // Upcoming Events (Next 7 days)
      $upcomingEvents = $orm->runQuery(
         "SELECT EventID, EventTitle, EventDate, StartTime, Location
             FROM event
             WHERE BranchID = :branch
               AND EventDate BETWEEN :today AND DATE_ADD(:today, INTERVAL 7 DAY)
             ORDER BY EventDate, StartTime
             LIMIT 5",
         [':branch' => $branchId, ':today' => $today]
      );

      // Pending Approvals
      $pending = [
         'budgets'  => (int)$orm->runQuery(
            "SELECT COUNT(*) AS cnt FROM budget WHERE BudgetStatus = 'Submitted' AND BranchID = :br",
            [':br' => $branchId]
         )[0]['cnt'],
         'expenses' => (int)$orm->runQuery(
            "SELECT COUNT(*) AS cnt FROM expense WHERE ExpenseStatus = 'Pending Approval' AND BranchID = :br",
            [':br' => $branchId]
         )[0]['cnt']
      ];

      // Recent Activity (Last 7 days)
      $activity = $orm->runQuery(
         "SELECT 'Member Registered' AS type,
                    CONCAT(m.MbrFirstName, ' ', m.MbrFamilyName) AS description,
                    m.MbrRegistrationDate AS timestamp
             FROM churchmember m
             WHERE m.BranchID = :br AND m.MbrRegistrationDate >= DATE_SUB(:today, INTERVAL 7 DAY)

             UNION ALL

             SELECT 'Contribution' AS type,
                    CONCAT('GHS ', c.ContributionAmount) AS description,
                    c.ContributionDate AS timestamp
             FROM contribution c
             WHERE c.BranchID = :br AND c.ContributionDate >= DATE_SUB(:today, INTERVAL 7 DAY)

             UNION ALL

             SELECT 'Event Created' AS type,
                    e.EventTitle AS description,
                    e.CreatedAt AS timestamp
             FROM event e
             WHERE e.BranchID = :br AND e.CreatedAt >= DATE_SUB(:today, INTERVAL 7 DAY)

             ORDER BY timestamp DESC
             LIMIT 10",
         [':br' => $branchId, ':today' => $today]
      );

      return [
         'membership' => [
            'total'           => (int)$membership['total'],
            'new_today'       => (int)$membership['new_today'],
            'new_this_month'  => (int)$membership['new_this_month'],
            'new_this_year'   => (int)$membership['new_this_year']
         ],
         'finance'              => $finance,
         'attendance_last_4_sundays' => array_reverse($attendance),
         'upcoming_events'      => $upcomingEvents,
         'pending_approvals'    => $pending,
         'recent_activity'      => $activity,
         'generated_at'         => date('c')
      ];
   }
}