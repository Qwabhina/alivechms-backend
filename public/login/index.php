<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Login - AliveChMS</title>

   <!-- Bootstrap CSS -->
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
   <!-- <link rel="stylesheet" href="../assets/css/bootstrap.min.css"> -->

   <!-- Bootstrap Icons -->
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
   <!-- <link rel="stylesheet" href="../assets/bootstrap-icons.min.css"> -->

   <!-- SweetAlert2 -->
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.4/dist/sweetalert2.min.css">
   <!-- <link rel="stylesheet" href="../assets/css/sweetalert2.min.css"> -->

   <!-- Custom CSS -->
   <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body>
   <div class="container">
      <div class="row justify-content-center">
         <div class="col-12 col-sm-10 col-md-8 col-lg-6">
            <div class="login-container mx-auto">
               <div class="card login-card">
                  <div class="card-body p-4 p-sm-5">
                     <div class="logo-container">
                        <img src="../assets/img/logo.png" alt="Church Logo" onerror="this.style.display='none'">
                        <h3>AliveChMS</h3>
                        <p class="mb-0">Church Management System</p>
                     </div>

                     <form id="loginForm" novalidate>
                        <div class="mb-3">
                           <label for="username" class="form-label fw-semibold">Username</label>
                           <div class="input-group">
                              <span class="input-group-text">
                                 <i class="bi bi-person"></i>
                              </span>
                              <input
                                 type="text"
                                 class="form-control"
                                 id="username"
                                 name="username"
                                 placeholder="Enter your username"
                                 required
                                 autofocus>
                           </div>
                           <div class="invalid-feedback">
                              Please enter your username.
                           </div>
                        </div>

                        <div class="mb-3">
                           <label for="password" class="form-label fw-semibold">Password</label>
                           <div class="input-group">
                              <span class="input-group-text">
                                 <i class="bi bi-lock"></i>
                              </span>
                              <input
                                 type="password"
                                 class="form-control"
                                 id="password"
                                 name="password"
                                 placeholder="Enter your password"
                                 required>
                              <button
                                 class="btn btn-outline-secondary"
                                 type="button"
                                 id="togglePassword"
                                 title="Toggle password visibility">
                                 <i class="bi bi-eye" id="togglePasswordIcon"></i>
                              </button>
                           </div>
                           <div class="invalid-feedback">
                              Please enter your password.
                           </div>
                        </div>

                        <div class="mb-3 form-check">
                           <input
                              type="checkbox"
                              class="form-check-input"
                              id="remember">
                           <label class="form-check-label" for="remember">
                              Remember me
                           </label>
                        </div>

                        <button type="submit" class="btn btn-primary btn-login w-100">
                           <i class="bi bi-box-arrow-in-right me-2"></i>
                           <span id="loginButtonText">Login</span>
                        </button>
                     </form>

                     <div class="footer-text">
                        <small>
                           <i class="bi bi-shield-check me-1"></i>
                           Secure Login &middot; &copy; <?= date('Y') ?> AliveChMS
                        </small>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>

   <!-- Bootstrap JS -->
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
   <!-- <script src="../assets/js/bootstrap.bundle.min.js"></script> -->

   <!-- SweetAlert2 -->
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.4/dist/sweetalert2.all.min.js"></script>
   <!-- <script src="../assets/js/sweetalert2.all.min.js"></script> -->

   <!-- Core JS -->
   <script src="../assets/js/core/config.js"></script>
   <script src="../assets/js/core/utils.js"></script>
   <script src="../assets/js/core/api.js"></script>
   <script src="../assets/js/core/auth.js"></script>
   <script src="../assets/js/core/alerts.js"></script>

   <script>
      // Check if already logged in
      if (Auth.isAuthenticated()) {
         window.location.href = '../dashboard/';
      }

      // Toggle password visibility
      document.getElementById('togglePassword').addEventListener('click', function() {
         const passwordInput = document.getElementById('password');
         const icon = document.getElementById('togglePasswordIcon');

         if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
         } else {
            passwordInput.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
         }
      });

      // Login form handling
      document.getElementById('loginForm').addEventListener('submit', async (e) => {
         e.preventDefault();

         const form = e.target;
         const username = document.getElementById('username').value.trim();
         const password = document.getElementById('password').value;
         const remember = document.getElementById('remember').checked;

         // Validate
         if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
         }

         // Disable form
         const submitBtn = form.querySelector('button[type="submit"]');
         const buttonText = document.getElementById('loginButtonText');
         submitBtn.disabled = true;
         buttonText.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...';

         try {
            // Attempt login
            await Auth.login(username, password, remember);

            // Show success message
            Alerts.success('Login successful! Redirecting...');

            // Small delay for better UX
            setTimeout(() => {
               Auth.handleRedirectAfterLogin();
            }, 1000);

         } catch (error) {
            // Shake the card for visual feedback
            const card = document.querySelector('.login-card');
            card.classList.add('shake');
            setTimeout(() => card.classList.remove('shake'), 500);

            // Handle error
            Alerts.handleApiError(error, 'Login failed. Please check your credentials and try again.');

            // Re-enable form
            submitBtn.disabled = false;
            buttonText.innerHTML = 'Login';

            // Clear password for security
            document.getElementById('password').value = '';
            document.getElementById('password').focus();
         }
      });

      // Handle Enter key on inputs
      document.querySelectorAll('#loginForm input').forEach(input => {
         input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
               document.getElementById('loginForm').dispatchEvent(new Event('submit'));
            }
         });
      });
   </script>
</body>

</html>