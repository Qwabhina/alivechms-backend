<?php $pageTitle = 'Dashboard'; ?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="page-header">
   <div class="row align-items-center">
      <div class="col">
         <h1>Dashboard</h1>
         <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
               <li class="breadcrumb-item active">Dashboard</li>
            </ol>
         </nav>
      </div>
      <div class="col-auto">
         <button class="btn btn-primary" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise me-2"></i>Refresh
         </button>
      </div>
   </div>
</div>

<!-- Stats Cards -->
<div class="row" id="statsCards">
   <!-- Will be populated dynamically -->
   <div class="col-12 text-center py-5">
      <div class="spinner-border text-primary" role="status">
         <span class="visually-hidden">Loading...</span>
      </div>
      <p class="text-muted mt-2">Loading dashboard data...</p>
   </div>
</div>

<!-- Charts Row -->
<div class="row mt-4">
   <!-- Attendance Chart -->
   <div class="col-lg-8 col-md-12">
      <div class="card">
         <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Attendance Trend (Last 4 Sundays)</h5>
            <i class="bi bi-graph-up-arrow text-primary"></i>
         </div>
         <div class="card-body">
            <canvas id="attendanceChart" height="180"></canvas>
         </div>
      </div>
   </div>

   <!-- Financial Overview -->
   <div class="col-lg-4 col-md-12">
      <div class="card">
         <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Financial Overview</h5>
            <i class="bi bi-currency-dollar text-success"></i>
         </div>
         <div class="card-body">
            <canvas id="financeChart" height="180"></canvas>
            <div class="mt-3">
               <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Income:</span>
                  <strong class="text-success" id="totalIncome">₵0.00</strong>
               </div>
               <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Expenses:</span>
                  <strong class="text-danger" id="totalExpenses">₵0.00</strong>
               </div>
               <hr>
               <div class="d-flex justify-content-between">
                  <span class="fw-bold">Net:</span>
                  <strong id="netBalance">₵0.00</strong>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>

<!-- Upcoming Events & Recent Activity -->
<div class="row mt-4">
   <!-- Upcoming Events -->
   <div class="col-lg-6 col-md-12">
      <div class="card">
         <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Upcoming Events</h5>
            <a href="events.php" class="btn btn-sm btn-outline-primary">View All</a>
         </div>
         <div class="card-body">
            <div id="upcomingEvents">
               <div class="text-center py-3">
                  <div class="spinner-border spinner-border-sm text-primary"></div>
               </div>
            </div>
         </div>
      </div>
   </div>

   <!-- Recent Activity -->
   <div class="col-lg-6 col-md-12">
      <div class="card">
         <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Recent Activity</h5>
            <span class="badge bg-primary" id="activityCount">0</span>
         </div>
         <div class="card-body">
            <div id="recentActivity" style="max-height: 400px; overflow-y: auto;">
               <div class="text-center py-3">
                  <div class="spinner-border spinner-border-sm text-primary"></div>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>

<!-- Pending Approvals (if applicable) -->
<div class="row mt-4" id="pendingApprovalsSection" style="display: none;">
   <div class="col-12">
      <div class="card">
         <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Pending Approvals</h5>
            <span class="badge bg-warning text-dark" id="pendingCount">0</span>
         </div>
         <div class="card-body">
            <div class="row" id="pendingApprovals">
               <!-- Populated dynamically -->
            </div>
         </div>
      </div>
   </div>
</div>

<script>
   let attendanceChart, financeChart;

   // Load dashboard data
   async function loadDashboard() {
      try {
         const data = await api.get('dashboard/overview');

         // Populate stats cards
         renderStatsCards(data);

         // Render charts
         renderAttendanceChart(data.attendance_last_4_sundays || []);
         renderFinanceChart(data.finance || {});

         // Populate upcoming events
         renderUpcomingEvents(data.upcoming_events || []);

         // Populate recent activity
         renderRecentActivity(data.recent_activity || []);

         // Show pending approvals if user has permission
         if (Auth.hasPermission(Config.PERMISSIONS.APPROVE_EXPENSES) ||
            Auth.hasPermission(Config.PERMISSIONS.APPROVE_BUDGETS)) {
            renderPendingApprovals(data.pending_approvals || {});
         }

      } catch (error) {
         Alerts.handleApiError(error, 'Failed to load dashboard data');
         document.getElementById('statsCards').innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Failed to load dashboard. Please refresh the page.
                    </div>
                </div>
            `;
      }
   }

   // Render stats cards
   function renderStatsCards(data) {
      const membership = data.membership || {};
      const finance = data.finance || {};
      const pending = data.pending_approvals || {};

      const cards = [{
            title: 'Total Active Members',
            value: membership.total || 0,
            change: `+${membership.new_this_month || 0} this month`,
            icon: 'people',
            color: 'primary',
            link: 'members.php'
         },
         {
            title: 'Total Income',
            value: Utils.formatCurrency(parseFloat(finance.income || 0)),
            change: 'This fiscal year',
            icon: 'currency-dollar',
            color: 'success',
            link: 'contributions.php'
         },
         {
            title: 'Total Expenses',
            value: Utils.formatCurrency(parseFloat(finance.expenses || 0)),
            change: 'This fiscal year',
            icon: 'receipt',
            color: 'danger',
            link: 'expenses.php'
         },
         {
            title: 'Pending Approvals',
            value: (pending.budgets || 0) + (pending.expenses || 0),
            change: `${pending.budgets || 0} budgets, ${pending.expenses || 0} expenses`,
            icon: 'clock-history',
            color: 'warning',
            link: '#pendingApprovalsSection'
         }
      ];

      const html = cards.map(card => `
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card bg-${card.color} bg-opacity-25 mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <p class="text-muted mb-1">${card.title}</p>
                                <h3 class="mb-0">${card.value}</h3>
                                <small class="text-muted">${card.change}</small>
                            </div>
                            <div class="stat-icon bg-${card.color} text-white text-opacity-50 rounded-circle p-3">
                                <i class="bi bi-${card.icon}"></i>
                            </div>
                        </div>
                        <a href="${card.link}" class="btn btn-sm btn-${card.color}">
                            View Details <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        `).join('');

      document.getElementById('statsCards').innerHTML = html;
   }

   // Render attendance chart
   function renderAttendanceChart(data) {
      const ctx = document.getElementById('attendanceChart');

      if (attendanceChart) {
         attendanceChart.destroy();
      }

      const dates = data.map(d => Utils.formatDate(d.date, 'M d'));
      const attendance = data.map(d => parseInt(d.present) || 0);

      attendanceChart = new Chart(ctx, {
         type: 'line',
         data: {
            labels: dates,
            datasets: [{
               label: 'Attendance',
               data: attendance,
               borderColor: Config.CHART_COLORS.primary,
               backgroundColor: Config.CHART_COLORS.primary + '20',
               tension: 0.4,
               fill: true
            }]
         },
         options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
               legend: {
                  display: false
               }
            },
            scales: {
               y: {
                  beginAtZero: true,
                  ticks: {
                     stepSize: 10
                  }
               }
            }
         }
      });
   }

   // Render finance chart
   function renderFinanceChart(data) {
      const ctx = document.getElementById('financeChart');

      if (financeChart) {
         financeChart.destroy();
      }

      const income = parseFloat(data.income || 0);
      const expenses = parseFloat(data.expenses || 0);
      const net = income - expenses;

      // Update text values
      document.getElementById('totalIncome').textContent = Utils.formatCurrency(income);
      document.getElementById('totalExpenses').textContent = Utils.formatCurrency(expenses);
      document.getElementById('netBalance').textContent = Utils.formatCurrency(net);
      document.getElementById('netBalance').className = net >= 0 ? 'text-success' : 'text-danger';

      financeChart = new Chart(ctx, {
         type: 'doughnut',
         data: {
            labels: ['Income', 'Expenses'],
            datasets: [{
               data: [income, expenses],
               backgroundColor: [
                  Config.CHART_COLORS.success,
                  Config.CHART_COLORS.danger
               ]
            }]
         },
         options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
               legend: {
                  position: 'top'
               }
            }
         }
      });
   }

   // Render upcoming events
   function renderUpcomingEvents(events) {
      const container = document.getElementById('upcomingEvents');

      if (events.length === 0) {
         container.innerHTML = `
                <div class="text-center text-muted py-3">
                    <i class="bi bi-calendar-x fs-1"></i>
                    <p class="mb-0 mt-2">No upcoming events</p>
                </div>
            `;
         return;
      }

      const html = events.map(event => `
            <div class="d-flex align-items-start mb-3 pb-3 border-bottom">
                <div class="me-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded p-2">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1">${Utils.escapeHtml(event.EventTitle)}</h6>
                    <small class="text-muted">
                        <i class="bi bi-clock me-1"></i>${Utils.formatDate(event.EventDate)}
                        ${event.StartTime ? ` at ${event.StartTime}` : ''}
                    </small>
                    ${event.Location ? `<br><small class="text-muted"><i class="bi bi-geo-alt me-1"></i>${Utils.escapeHtml(event.Location)}</small>` : ''}
                </div>
            </div>
        `).join('');

      container.innerHTML = html;
   }

   // Render recent activity
   function renderRecentActivity(activities) {
      const container = document.getElementById('recentActivity');
      document.getElementById('activityCount').textContent = activities.length;

      if (activities.length === 0) {
         container.innerHTML = `
                <div class="text-center text-muted py-3">
                    <i class="bi bi-activity fs-1"></i>
                    <p class="mb-0 mt-2">No recent activity</p>
                </div>
            `;
         return;
      }

      const iconMap = {
         'Member Registered': {
            icon: 'person-plus',
            color: 'success'
         },
         'Contribution': {
            icon: 'currency-dollar',
            color: 'primary'
         },
         'Event Created': {
            icon: 'calendar-plus',
            color: 'info'
         },
         'Expense': {
            icon: 'receipt',
            color: 'danger'
         }
      };

      const html = activities.map(activity => {
         const info = iconMap[activity.type] || {
            icon: 'circle',
            color: 'secondary'
         };
         return `
                <div class="d-flex align-items-start mb-3">
                    <div class="me-3">
                        <div class="bg-${info.color} bg-opacity-10 text-${info.color} rounded-circle p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-${info.icon}"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">${activity.type}</div>
                        <div class="text-muted small">${Utils.escapeHtml(activity.description)}</div>
                        <div class="text-muted small">${Utils.timeAgo(activity.timestamp)}</div>
                    </div>
                </div>
            `;
      }).join('');

      container.innerHTML = html;
   }

   // Render pending approvals
   function renderPendingApprovals(pending) {
      const total = (pending.budgets || 0) + (pending.expenses || 0);

      if (total === 0) return;

      document.getElementById('pendingApprovalsSection').style.display = 'block';
      document.getElementById('pendingCount').textContent = total;

      const html = `
            <div class="col-md-6">
                <div class="alert alert-warning mb-0">
                    <h6 class="alert-heading">
                        <i class="bi bi-folder-check me-2"></i>Budget Approvals
                    </h6>
                    <p class="mb-2">${pending.budgets || 0} budget(s) pending approval</p>
                    <a href="budgets.php?filter=pending" class="btn btn-sm btn-warning">Review Now</a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert alert-info mb-0">
                    <h6 class="alert-heading">
                        <i class="bi bi-receipt-cutoff me-2"></i>Expense Approvals
                    </h6>
                    <p class="mb-2">${pending.expenses || 0} expense(s) pending approval</p>
                    <a href="expenses.php?filter=pending" class="btn btn-sm btn-info">Review Now</a>
                </div>
            </div>
        `;

      document.getElementById('pendingApprovals').innerHTML = html;
   }

   // Initialize on page load
   document.addEventListener('DOMContentLoaded', () => {
      // Check permission
      if (!Auth.requirePermission(Config.PERMISSIONS.VIEW_DASHBOARD)) {
         window.location.href = '../login/';
         return;
      }

      // Load dashboard
      loadDashboard();

      // Auto-refresh every 5 minutes
      setInterval(loadDashboard, 5 * 60 * 1000);
   });
</script>

<?php include '../includes/footer.php'; ?>