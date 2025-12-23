<?php

// Start session
if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

// Check if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
   header('Location: /public/dashboard');
} else {
   header('Location: /public/login');
}
exit();
