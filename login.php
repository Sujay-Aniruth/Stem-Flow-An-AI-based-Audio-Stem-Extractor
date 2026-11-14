<?php
// login.php - User Login Page

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/security.php';

if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Sign In — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="background-color: var(--bg-main); display: flex; align-items: center; justify-content: center; min-height: 100vh;">

<div class="container" style="max-width: 440px; padding: 20px;">
    <div class="text-center mb-4">
        <a href="index.php" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-2">
            <div style="width: 42px; height: 42px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: #fff;">
                <i class="fas fa-wave-square"></i>
            </div>
            <h2 class="text-white fw-bold mb-0">StemFlow <span style="color: var(--secondary);">AI</span></h2>
        </a>
        <p class="text-muted small">Sign in to your audio production workspace</p>
    </div>

    <div class="daw-card">
        <div id="loginAlert" class="alert alert-danger" style="display: none; font-size: 0.88rem;"></div>

        <form id="loginForm">
            <div class="mb-3">
                <label for="email" class="form-label text-secondary small fw-bold">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">
                        <i class="fas fa-envelope"></i>
                    </span>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="producer@stemflow.ai" autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label text-secondary small fw-bold">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                </div>
            </div>

            <button type="submit" id="btnSubmit" class="btn btn-primary w-100 py-2">
                <i class="fas fa-sign-in-alt me-2"></i> Sign In to Studio
            </button>
        </form>

        <div class="border-top mt-4 pt-3 text-center" style="border-color: var(--border-color) !important;">
            <p class="text-muted small mb-0">
                Don't have an account? <a href="register.php" style="color: var(--secondary); text-decoration: none; font-weight: 600;">Create Account</a>
            </p>
        </div>
    </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const alertBox = document.getElementById('loginAlert');
    const btnSubmit = document.getElementById('btnSubmit');
    alertBox.style.display = 'none';

    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Authenticating...';

    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    try {
        const res = await fetch('api/auth/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });

        const data = await res.json();

        if (data.success) {
            window.location.href = 'dashboard.php';
        } else {
            alertBox.textContent = data.error || 'Invalid email or password.';
            alertBox.style.display = 'block';
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i> Sign In to Studio';
        }
    } catch (err) {
        alertBox.textContent = 'Network or server error occurred. Please try again.';
        alertBox.style.display = 'block';
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i> Sign In to Studio';
    }
});
</script>
</body>
</html>
