<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <meta name="description" content="AliveChMS - Modern Church Management System">
   <title><?= $pageTitle ?? 'Dashboard' ?> - AliveChMS</title>

   <!-- Bootstrap CSS -->
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

   <!-- Bootstrap Icons -->
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

   <!-- DataTables -->
   <link rel="stylesheet" href="https://cdn.datatables.net/2.0.0/css/dataTables.bootstrap5.min.css">

   <!-- Flatpickr -->
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">

   <!-- SweetAlert2 -->
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.4/dist/sweetalert2.min.css">

   <!-- Custom CSS -->
   <link rel="stylesheet" href="../assets/css/app.css">
</head>

<body>
   <!-- Header -->
   <header class="main-header">
      <nav class="navbar">
         <div class="d-flex align-items-center">
            <!-- Mobile menu toggle -->
            <button class="btn btn-link d-lg-none me-3" id="sidebarToggle">
               <i class="bi bi-list fs-4"></i>
            </button>

            <!-- Brand -->
            <a class="navbar-brand" href="./">
               <i class="bi bi-church me-2"></i>
               AliveChMS
            </a>
         </div>

         <div class="d-flex align-items-center gap-3">
            <!-- Search (Optional) -->
            <div class="d-none d-md-block">
               <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white">
                     <i class="bi bi-search"></i>
                  </span>
                  <input type="text" class="form-control" placeholder="Search..." id="globalSearch">
               </div>
            </div>

            <!-- Notifications -->
            <div class="dropdown">
               <button class="btn btn-light position-relative" type="button" data-bs-toggle="dropdown">
                  <i class="bi bi-bell fs-5"></i>
                  <span class="notification-badge d-none" id="notificationBadge">0</span>
               </button>
               <div class="dropdown-menu dropdown-menu-end" style="width: 320px;">
                  <div class="dropdown-header">
                     <strong>Notifications</strong>
                  </div>
                  <div id="notificationList" class="px-3 py-2 text-muted text-center">
                     No new notifications
                  </div>
               </div>
            </div>

            <!-- User Menu -->
            <div class="dropdown">
               <button class="btn d-flex align-items-center gap-3" type="button" data-bs-toggle="dropdown">
                  <div class="user-avatar" id="userAvatar">?</div>
                  <div class="d-none d-md-block text-start">
                     <div class="fw-semibold" id="userName">Loading...</div>
                     <small class="text-muted" id="userRole">User</small>
                  </div>
                  <i class="bi bi-chevron-down"></i>
               </button>
               <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                     <a class="dropdown-item" href="profile.php">
                        <i class="bi bi-person me-2"></i>My Profile
                     </a>
                  </li>
                  <li>
                     <a class="dropdown-item" href="settings.php">
                        <i class="bi bi-gear me-2"></i>Settings
                     </a>
                  </li>
                  <li>
                     <hr class="dropdown-divider">
                  </li>
                  <li>
                     <a class="dropdown-item text-danger" href="#" id="logoutBtn">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                     </a>
                  </li>
               </ul>
            </div>
         </div>
      </nav>
   </header>

   <!-- Sidebar Overlay (Mobile) -->
   <div class="sidebar-overlay" id="sidebarOverlay"></div>