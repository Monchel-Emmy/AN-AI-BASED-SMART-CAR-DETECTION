<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> — Accident Detection</title>
    <link rel="stylesheet" href="/accident/web/dashboard/assets/css/style.css">
</head>
<body>

<div class="layout">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">🚨</span>
            <span class="brand-name">AccidentWatch</span>
        </div>
        <nav class="sidebar-nav">
            <?php
            $current = basename($_SERVER['PHP_SELF']);
            function navItem($href, $icon, $label, $current) {
                $active = (basename($href) === $current) ? 'active' : '';
                echo "<a href='/accident/web/dashboard/{$href}' class='nav-item {$active}'>"
                   . "<span class='nav-icon'>{$icon}</span>"
                   . "<span class='nav-label'>{$label}</span>"
                   . "</a>";
            }
            navItem('index.php',    '📊', 'Dashboard', $current);
            navItem('drivers.php',  '👤', 'Drivers',   $current);
            navItem('vehicles.php', '🚗', 'Vehicles',  $current);
            navItem('devices.php',  '📡', 'Devices',   $current);
            navItem('accidents.php','⚠️', 'Accidents', $current);
            ?>
        </nav>
        <div class="sidebar-footer">
            <span>Accident Detection System</span>
        </div>
    </aside>

    <!-- Main content -->
    <main class="main">
        <div class="topbar">
            <h1 class="page-title"><?= $pageTitle ?? 'Dashboard' ?></h1>
            <div class="topbar-right">
                <span class="time" id="clock"></span>
                <span class="topbar-user">👤 <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></span>
                <a href="/accident/web/dashboard/auth/logout.php" class="btn btn-ghost btn-sm">Sign Out</a>
            </div>
        </div>
        <div class="content">
