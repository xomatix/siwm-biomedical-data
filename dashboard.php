<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

requireLogin();

$userEmail = 'Użytkownik';
$userId = getCurrentUserId();

if ($userId !== null) {
    $stmt = getDB()->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    if ($user) {
        $userEmail = $user['email'];
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="dashboard">
    <h1>Witaj, <?php echo sanitize($userEmail); ?></h1>
    <p>Główny panel użytkownika.</p>
    <ul>
        <li><a href="measurements.php">Pomiary</a></li>
        <li><a href="catalog.php">Katalog badań</a></li>
        <li><a href="units.php">Jednostki</a></li>
        <li><a href="norms.php">Normy</a></li>
        <li><a href="statistics.php">Statystyki</a></li>
        <li><a href="profile.php">Profil</a></li>
    </ul>
</section>
<?php require_once __DIR__ . '/includes/footer.php';
