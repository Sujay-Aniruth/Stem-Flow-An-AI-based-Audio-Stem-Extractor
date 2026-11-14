<?php
// index.php - StemFlow AI Landing Page

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/security.php';

if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'StemFlow AI — AI-Powered Audio Stem Extraction & Production Workspace';
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
    
    <style>
        .hero-section {
            min-height: 85vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: radial-gradient(circle at 50% 20%, rgba(139, 92, 246, 0.18) 0%, rgba(13, 13, 18, 0) 70%);
            padding: 80px 20px;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 30px;
            background: rgba(139, 92, 246, 0.15);
            border: 1px solid rgba(139, 92, 246, 0.35);
            color: #C084FC;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 24px;
        }
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -1.5px;
            margin-bottom: 24px;
        }
        .hero-title span {
            background: linear-gradient(135deg, #A855F7, #22D3EE);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-desc {
            font-size: 1.25rem;
            color: var(--text-secondary);
            max-width: 680px;
            margin: 0 auto 36px auto;
            line-height: 1.6;
        }
        .feature-box {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px 24px;
            text-align: left;
            height: 100%;
            transition: transform 0.2s, border-color 0.2s;
        }
        .feature-box:hover {
            transform: translateY(-4px);
            border-color: var(--border-light);
        }
        .feature-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 18px;
        }
    </style>
</head>
<body style="background-color: var(--bg-main);">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg border-bottom" style="background-color: rgba(20,20,28,0.85); backdrop-filter: blur(10px); border-color: var(--border-color) !important;">
    <div class="container py-2">
        <a class="navbar-brand d-flex align-items-center gap-2 text-white fw-bold" href="index.php">
            <div style="width: 34px; height: 34px; border-radius: 8px; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-wave-square text-white"></i>
            </div>
            StemFlow <span style="color: var(--secondary);">AI</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <a href="login.php" class="btn btn-outline-light btn-sm px-3">Sign In</a>
            <a href="register.php" class="btn btn-primary btn-sm px-3">Get Started</a>
        </div>
    </div>
</nav>

<!-- Hero -->
<section class="hero-section">
    <div class="container">
        <div class="hero-badge">
            <i class="fas fa-bolt"></i> Pretrained Demucs AI Audio Separation
        </div>
        <h1 class="hero-title">
            Extract, Isolate & Remix<br><span>Audio Stems with AI</span>
        </h1>
        <p class="hero-desc">
            Separate any song into Vocals, Drums, Bass, and Other instruments using real Demucs AI models.
            Fine-tune volume and pitch independently in a synchronized multitrack studio workspace.
        </p>
        <div class="d-flex justify-content-center gap-3">
            <a href="register.php" class="btn btn-primary btn-lg px-4 py-3">
                <i class="fas fa-play me-2"></i> Launch Studio Workspace
            </a>
            <a href="login.php" class="btn btn-secondary btn-lg px-4 py-3">
                Sign In to Account
            </a>
        </div>
    </div>
</section>

<!-- Features Grid -->
<section class="container pb-5">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="feature-box">
                <div class="feature-icon" style="background: rgba(168, 85, 247, 0.2); color: #C084FC;">
                    <i class="fas fa-brain"></i>
                </div>
                <h5 class="fw-bold mb-2">Demucs AI Engine</h5>
                <p class="text-secondary small mb-0">
                    High-fidelity neural network audio stem separation. Extracts Vocals, Drums, Bass, and Instruments with authentic deep-learning inference.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-box">
                <div class="feature-icon" style="background: rgba(34, 211, 238, 0.2); color: #22D3EE;">
                    <i class="fas fa-sliders-h"></i>
                </div>
                <h5 class="fw-bold mb-2">Synchronized Multitrack DAW</h5>
                <p class="text-secondary small mb-0">
                    Shared Web Audio API clock ensures zero-drift synchronization across all stems with interactive WaveSurfer waveforms, solo, and mute controls.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-box">
                <div class="feature-icon" style="background: rgba(16, 185, 129, 0.2); color: #34D399;">
                    <i class="fas fa-music"></i>
                </div>
                <h5 class="fw-bold mb-2">Duration-Preserving Pitch & Export</h5>
                <p class="text-secondary small mb-0">
                    Independently shift pitch by -12 to +12 semitones without changing audio duration. Export your customized stems or download full ZIP archives.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="border-top py-4 text-center text-muted" style="border-color: var(--border-color) !important; font-size: 0.85rem;">
    <div class="container">
        StemFlow AI • Local AI Audio Stem Extraction Workspace
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
