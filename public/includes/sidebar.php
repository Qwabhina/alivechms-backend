<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
   <nav class="sidebar-nav">
      <ul class="nav flex-column">
         <!-- Dashboard -->
         <li class="nav-item">
            <a class="nav-link" href="./" data-page="dashboard">
               <i class="bi bi-speedometer2 me-2"></i>
               <span>Dashboard</span>
            </a>
         </li>

         <!-- Members -->
         <li class="nav-item" data-permission="view_members">
            <a class="nav-link" href="members.php" data-page="members">
               <i class="bi bi-people me-2"></i>
               <span>Members</span>
            </a>
         </li>

         <!-- Contributions -->
         <li class="nav-item" data-permission="view_contribution">
            <a class="nav-link" href="contributions.php" data-page="contributions">
               <i class="bi bi-currency-dollar me-2"></i>
               <span>Contributions</span>
            </a>
         </li>

         <!-- Expenses -->
         <li class="nav-item" data-permission="view_expenses">
            <a class="nav-link" href="expenses.php" data-page="expenses">
               <i class="bi bi-receipt me-2"></i>
               <span>Expenses</span>
            </a>
         </li>

         <!-- Events -->
         <li class="nav-item" data-permission="view_events">
            <a class="nav-link" href="events.php" data-page="events">
               <i class="bi bi-calendar-event me-2"></i>
               <span>Events</span>
            </a>
         </li>

         <!-- Groups -->
         <li class="nav-item" data-permission="view_groups">
            <a class="nav-link" href="groups.php" data-page="groups">
               <i class="bi bi-diagram-3 me-2"></i>
               <span>Groups</span>
            </a>
         </li>

         <!-- Reports -->
         <li class="nav-item" data-permission="view_financial_reports">
            <a class="nav-link" href="reports.php" data-page="reports">
               <i class="bi bi-graph-up me-2"></i>
               <span>Reports</span>
            </a>
         </li>

         <!-- Divider -->
         <li class="nav-item">
            <hr class="my-3" style="border-color: rgba(255,255,255,0.1);">
         </li>

         <!-- Settings -->
         <li class="nav-item" data-permission="manage_roles">
            <a class="nav-link" href="settings.php" data-page="settings">
               <i class="bi bi-gear me-2"></i>
               <span>Settings</span>
            </a>
         </li>
      </ul>
   </nav>
</aside>

<!-- Main Content -->
<main class="main-content">