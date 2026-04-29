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
</section>
<?php require_once __DIR__ . '/includes/footer.php';
