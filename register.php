<?php
// register.php - User Registration Page

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/security.php';

if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Create Account — ' . APP_NAME;
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

<div class="container" style="max-width: 480px; padding: 20px;">
    <div class="text-center mb-4">
        <a href="index.php" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-2">
            <div style="width: 42px; height: 42px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: #fff;">
                <i class="fas fa-wave-square"></i>
            </div>
            <h2 class="text-white fw-bold mb-0">StemFlow <span style="color: var(--secondary);">AI</span></h2>
        </a>
        <p class="text-muted small">Register your private AI audio separation workspace</p>
    </div>

    <div class="daw-card">
        <div id="registerAlert" class="alert alert-danger" style="display: none; font-size: 0.88rem;"></div>

        <form id="registerForm">
            <div class="mb-3">
                <label for="name" class="form-label text-secondary small fw-bold">Full Name</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">
                        <i class="fas fa-user"></i>
                    </span>
                    <input type="text" class="form-control" id="name" name="name" required placeholder="Alex Producer" autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label text-secondary small fw-bold">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">
                        <i class="fas fa-envelope"></i>
                    </span>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="alex@studio.com">
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label text-secondary small fw-bold">Password (min 6 chars)</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" class="form-control" id="password" name="password" minlength="6" required placeholder="••••••••">
                </div>
            </div>

            <div class="mb-4">
                <label for="confirm_password" class="form-label text-secondary small fw-bold">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">
                        <i class="fas fa-check-double"></i>
                    </span>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required placeholder="••••••••">
                </div>
            </div>

            <button type="submit" id="btnSubmit" class="btn btn-primary w-100 py-2">
                <i class="fas fa-user-plus me-2"></i> Create Studio Account
            </button>
        </form>

        <div class="border-top mt-4 pt-3 text-center" style="border-color: var(--border-color) !important;">
            <p class="text-muted small mb-0">
                Already registered? <a href="login.php" style="color: var(--secondary); text-decoration: none; font-weight: 600;">Sign In</a>
            </p>
        </div>
    </div>
</div>

<script>
document.getElementById('registerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const alertBox = document.getElementById('registerAlert');
    const btnSubmit = document.getElementById('btnSubmit');
    alertBox.style.display = 'none';

    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (password !== confirmPassword) {
        alertBox.textContent = 'Passwords do not match.';
        alertBox.style.display = 'block';
        return;
    }

    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Registering...';

    try {
        const res = await fetch('api/auth/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, email, password, confirm_password: confirmPassword })
        });

        const data = await res.json();

        if (data.success) {
            window.location.href = 'dashboard.php';
        } else {
            alertBox.textContent = data.error || 'Registration failed.';
            alertBox.style.display = 'block';
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fas fa-user-plus me-2"></i> Create Studio Account';
        }
    } catch (err) {
        alertBox.textContent = 'Network or server error occurred. Please try again.';
        alertBox.style.display = 'block';
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<i class="fas fa-user-plus me-2"></i> Create Studio Account';
    }
});
</script>
</body>
</html>
