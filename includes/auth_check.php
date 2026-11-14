<?php
// includes/auth_check.php - Enforce authenticated session for protected pages

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';

requireAuth();
