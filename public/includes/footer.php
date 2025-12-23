</main>

<!-- jQuery (for DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/2.0.0/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.0/js/dataTables.bootstrap5.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- Flatpickr -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.4/dist/sweetalert2.all.min.js"></script>

<!-- Core JS -->
<script src="../assets/js/core/config.js"></script>
<script src="../assets/js/core/utils.js"></script>
<script src="../assets/js/core/api.js"></script>
<script src="../assets/js/core/auth.js"></script>
<script src="../assets/js/core/alerts.js"></script>

<!-- Layout Script -->
<script>
   // Initialize layout
   document.addEventListener('DOMContentLoaded', () => {
      // Check authentication
      if (!Auth.requireAuth()) {
         return;
      }

      // Load user info
      const user = Auth.getUser();
      if (user) {
         document.getElementById('userName').textContent = Auth.getUserName();
         document.getElementById('userRole').textContent = Auth.getUserRole();
         document.getElementById('userAvatar').textContent = Auth.getUserInitials();
      }

      // Set active nav item
      const currentPage = window.location.pathname.split('/').pop().replace('.php', '') || 'index';
      document.querySelectorAll('.nav-link').forEach(link => {
         const page = link.getAttribute('data-page');
         if (page === currentPage || (currentPage === 'index' && page === 'dashboard')) {
            link.classList.add('active');
         }
      });

      // Hide menu items based on permissions
      document.querySelectorAll('[data-permission]').forEach(item => {
         const permission = item.getAttribute('data-permission');
         if (!Auth.hasPermission(Config.PERMISSIONS[permission.toUpperCase()])) {
            item.style.display = 'none';
         }
      });

      // Mobile sidebar toggle
      const sidebarToggle = document.getElementById('sidebarToggle');
      const sidebar = document.getElementById('sidebar');
      const sidebarOverlay = document.getElementById('sidebarOverlay');

      if (sidebarToggle) {
         sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
         });

         sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
         });
      }

      // Logout
      document.getElementById('logoutBtn').addEventListener('click', async (e) => {
         e.preventDefault();

         const confirmed = await Alerts.confirm({
            title: 'Logout',
            text: 'Are you sure you want to logout?',
            icon: 'question',
            confirmButtonText: 'Yes, logout'
         });

         if (confirmed) {
            Alerts.loading('Logging out...', 'Please wait');
            await Auth.logout();
         }
      });

      // Global search (optional)
      const globalSearch = document.getElementById('globalSearch');
      if (globalSearch) {
         globalSearch.addEventListener('input', Utils.debounce((e) => {
            const query = e.target.value;
            if (query.length >= 3) {
               // Implement global search
               console.log('Search:', query);
            }
         }, 500));
      }
   });
</script>
</body>

</html>