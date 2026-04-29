<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System biomedyczny</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header>
    <nav>
        <a href="index.php">Home</a>
        <?php if (isLoggedIn()): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="measurements.php">Pomiary</a>
            <a href="catalog.php">Katalog</a>
            <a href="units.php">Jednostki</a>
            <a href="norms.php">Normy</a>
            <a href="statistics.php">Statystyki</a>
            <a href="profile.php">Profil</a>
            <a href="logout.php">Wyloguj</a>
        <?php else: ?>
            <a href="register.php">Rejestracja</a>
        <?php endif; ?>
    </nav>
</header>
<main>
    <?php displayFlash('info'); ?>
    <?php displayFlash('error'); ?>
