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

   <style>
      :root {
         --sidebar-width: 260px;
         --header-height: 70px;
         --sidebar-bg: #1a202c;
         --sidebar-hover: #2d3748;
         --sidebar-active: #4a5568;
         --sidebar-text: #e2e8f0;
      }

      body {
         font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
         background-color: #f8f9fa;
      }

      /* Header */
      .main-header {
         position: fixed;
         top: 0;
         left: 0;
         right: 0;
         height: var(--header-height);
         background: #ffffff;
         border-bottom: 1px solid #dee2e6;
         z-index: 1000;
         box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
      }

      .main-header .navbar {
         height: 100%;
         padding: 0 1.5rem;
      }

      .main-header .navbar-brand {
         font-weight: 700;
         font-size: 1.5rem;
         color: #2c3e50;
      }

      /* Sidebar */
      .sidebar {
         position: fixed;
         top: var(--header-height);
         left: 0;
         bottom: 0;
         width: var(--sidebar-width);
         background: var(--sidebar-bg);
         overflow-y: auto;
         z-index: 999;
         transition: transform 0.3s ease;
      }

      .sidebar::-webkit-scrollbar {
         width: 6px;
      }

      .sidebar::-webkit-scrollbar-thumb {
         background: rgba(255, 255, 255, 0.2);
         border-radius: 3px;
      }

      .sidebar-nav {
         padding: 1rem 0;
      }

      .nav-item {
         margin: 0.25rem 0.75rem;
      }

      .nav-link {
         color: var(--sidebar-text);
         padding: 0.75rem 1rem;
         border-radius: 0.5rem;
         transition: all 0.2s;
         display: flex;
         align-items: center;
      }

      .nav-link i {
         width: 24px;
         font-size: 1.1rem;
      }

      .nav-link:hover {
         background: var(--sidebar-hover);
         color: #ffffff;
      }

      .nav-link.active {
         background: var(--sidebar-active);
         color: #ffffff;
         font-weight: 600;
      }

      /* Main Content */
      .main-content {
         margin-left: var(--sidebar-width);
         margin-top: var(--header-height);
         padding: 2rem;
         min-height: calc(100vh - var(--header-height));
      }

      /* Page Header */
      .page-header {
         margin-bottom: 2rem;
      }

      .page-header h1 {
         font-size: 2rem;
         font-weight: 700;
         color: #2c3e50;
         margin-bottom: 0.5rem;
      }

      .breadcrumb {
         background: transparent;
         padding: 0;
         margin: 0;
      }

      /* Cards */
      .card {
         border: none;
         border-radius: 0.75rem;
         box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
         margin-bottom: 1.5rem;
      }

      .card-header {
         background: #ffffff;
         border-bottom: 1px solid #e9ecef;
         padding: 1rem 1.5rem;
         font-weight: 600;
      }

      .card-body {
         padding: 1.5rem;
      }

      /* Stats Cards */
      .stat-card {
         transition: transform 0.2s;
      }

      .stat-card:hover {
         transform: translateY(-5px);
      }

      .stat-card .card-body {
         padding: 1.5rem;
      }

      .stat-icon {
         width: 50px;
         height: 50px;
         border-radius: 0.75rem;
         display: flex;
         align-items: center;
         justify-content: center;
         font-size: 1.5rem;
      }

      /* Buttons */
      .btn {
         border-radius: 0.5rem;
         padding: 0.5rem 1.25rem;
         font-weight: 500;
      }

      /* Mobile Responsive */
      @media (max-width: 991.98px) {
         .sidebar {
            transform: translateX(-100%);
         }

         .sidebar.show {
            transform: translateX(0);
         }

         .main-content {
            margin-left: 0;
         }

         .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            display: none;
         }

         .sidebar-overlay.show {
            display: block;
         }
      }

      /* User Dropdown */
      .user-avatar {
         width: 40px;
         height: 40px;
         border-radius: 50%;
         background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
         display: flex;
         align-items: center;
         justify-content: center;
         color: white;
         font-weight: 600;
         font-size: 0.9rem;
      }

      /* Notifications Badge */
      .notification-badge {
         position: absolute;
         top: -5px;
         right: -5px;
         background: #dc3545;
         color: white;
         border-radius: 50%;
         width: 18px;
         height: 18px;
         font-size: 0.7rem;
         display: flex;
         align-items: center;
         justify-content: center;
      }
   </style>
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
               <button class="btn btn-link position-relative" type="button" data-bs-toggle="dropdown">
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
               <button class="btn btn-link d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
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