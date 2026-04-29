<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

requireLogin();
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'Wypełnij wszystkie pola.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Nowe hasła nie są zgodne.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Hasło musi mieć co najmniej 8 znaków.';
    } else {
        $stmt = getDB()->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => getCurrentUserId()]);
        $user = $stmt->fetch();

        if (!$user || !verifyPassword($oldPassword, $user['password_hash'])) {
            $error = 'Stare hasło jest nieprawidłowe.';
        } else {
            $newHash = hashPassword($newPassword);
            $update = getDB()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $update->execute([
                'password_hash' => $newHash,
                'id' => getCurrentUserId(),
            ]);
            $success = 'Hasło zostało zmienione.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="profile-page">
    <h1>Zmiana hasła</h1>

    <?php if ($error !== null): ?>
        <div class="error-message"><?php echo sanitize($error); ?></div>
    <?php elseif ($success !== null): ?>
        <div class="success-message"><?php echo sanitize($success); ?></div>
    <?php endif; ?>

    <form method="post" action="profile.php">
        <label for="old_password">Stare hasło</label>
        <input type="password" id="old_password" name="old_password" required>

        <label for="new_password">Nowe hasło</label>
        <input type="password" id="new_password" name="new_password" required>

        <label for="confirm_password">Powtórz nowe hasło</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <button type="submit">Zmień hasło</button>
    </form>
</section>
<?php require_once __DIR__ . '/includes/footer.php';
