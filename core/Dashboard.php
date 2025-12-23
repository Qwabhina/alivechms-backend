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
                 WHERE FiscalYearID = :fy AND BranchID = :branch",
            [':fy' => $fyId, ':branch' => $branchId]
         )[0]['total'];

         $expenses = $orm->runQuery(
            "SELECT COALESCE(SUM(ExpAmount), 0) AS total
                 FROM expense
                 WHERE FiscalYearID = :fy AND ExpStatus = 'Approved' AND BranchID = :branch",
            [':fy' => $fyId, ':branch' => $branchId]
         )[0]['total'];

         $finance = [
            'income'   => (float)$income,
            'expenses' => (float)$expenses,
            'net'      => (float)$income - (float)$expenses
         ];
      }

      // Last 4 Sundays Attendance
      $attendance = $orm->runQuery(
         "SELECT
                DATE(e.EventDateTime) AS date,
                COALESCE(SUM(CASE WHEN ea.AttendanceStatus = 'Present' THEN 1 ELSE 0 END), 0) AS present
             FROM churchevent e
             LEFT JOIN event_attendance ea ON e.EventID = ea.EventID
             WHERE e.BranchID = :branch
               AND e.EventDateTime <= :today
               AND DAYOFWEEK(e.EventDateTime) = 1
             GROUP BY e.EventDateTime
             ORDER BY e.EventDateTime DESC
             LIMIT 4",
         [':branch' => $branchId, ':today' => $today]
      );

      // Upcoming Events (Next 7 days)
      $upcomingEvents = $orm->runQuery(
         "SELECT EventID, EventName, EventDateTime, Location
             FROM churchevent
             WHERE BranchID = :branch
               AND EventDateTime BETWEEN :today AND DATE_ADD(:nextweek, INTERVAL 7 DAY)
             ORDER BY EventDateTime DESC
             LIMIT 5",
         [':branch' => $branchId, ':today' => $today, ':nextweek' => date('Y-m-d', strtotime('+7 days'))]
      );

      // Pending Approvals
      $pending = [
         'budgets'  => (int)$orm->runQuery(
            "SELECT COUNT(*) AS cnt FROM churchbudget WHERE BudgetStatus = 'Submitted' AND BranchID = :br",
            [':br' => $branchId]
         )[0]['cnt'],
         'expenses' => (int)$orm->runQuery(
            "SELECT COUNT(*) AS cnt FROM expense WHERE ExpStatus = 'Pending Approval' AND BranchID = :br",
            [':br' => $branchId]
         )[0]['cnt']
      ];

      // Recent Activity (Last 7 days)
      $cutoff = date('Y-m-d', strtotime('-7 days'));

      $activity = $orm->runQuery(
         "SELECT 'Member Registered' AS type,
       CONCAT(m.MbrFirstName, ' ', m.MbrFamilyName) AS description,
       m.MbrRegistrationDate AS timestamp
FROM churchmember m
WHERE m.BranchID = :br1
  AND m.MbrRegistrationDate >= :cutoff1

UNION ALL

SELECT 'Contribution' AS type,
       CONCAT('GHS ', c.ContributionAmount) AS description,
       c.ContributionDate AS timestamp
FROM contribution c
WHERE c.BranchID = :br2
  AND c.ContributionDate >= :cutoff2

UNION ALL

SELECT 'Event Created' AS type,
       e.EventName AS description,
       e.CreatedAt AS timestamp
FROM churchevent e
WHERE e.BranchID = :br3
  AND e.CreatedAt >= :cutoff3

ORDER BY timestamp DESC
LIMIT 10
",
         [
            ':br1' => 1,
            ':br2' => 1,
            ':br3' => 1,

            ':cutoff1' => $cutoff,
            ':cutoff2' => $cutoff,
            ':cutoff3' => $cutoff,
         ]

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