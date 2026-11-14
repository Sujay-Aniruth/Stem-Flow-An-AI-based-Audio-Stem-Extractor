<?php
// includes/header.php - Global Header for StemFlow AI

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config/app.php';
}
require_once __DIR__ . '/../config/security.php';

$currentUser = getAuthUser();
$pageTitle = $pageTitle ?? APP_NAME . ' — AI Audio Stem Workspace';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom Theme Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (!empty($includeWorkspaceCss)): ?>
    <link rel="stylesheet" href="assets/css/workspace.css?v=<?= (int)filemtime(__DIR__ . '/../assets/css/workspace.css') ?>">
    <?php endif; ?>
    
    <!-- WaveSurfer.js v7 -->
    <script src="https://unpkg.com/wavesurfer.js@7/dist/wavesurfer.min.js"></script>
</head>
<body>
<div class="app-wrapper">
