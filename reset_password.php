<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$error = null;
success:
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['request_reset'])) {
        $email = trim($_POST['email'] ?? '');

        if ($email === '') {
            $error = 'Podaj adres email.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Nieprawidłowy adres email.';
        } else {
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600);
                $update = $pdo->prepare('UPDATE users SET reset_token = :token, reset_token_expires = :expires WHERE id = :id');
                $update->execute([
                    'token' => $token,
                    'expires' => $expires,
                    'id' => $user['id'],
                ]);
                $resetLink = sprintf('%sreset_password.php?token=%s', BASE_URL, $token);
                $success = 'Link do resetu hasła wygenerowany. Skopiuj link i otwórz go w przeglądarce.';
            } else {
                $success = 'Jeśli konto istnieje, link do resetu został wysłany.';
            }
        }
    }

    if (isset($_POST['reset_password'])) {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($token === '' || $password === '' || $confirm === '') {
            $error = 'Wypełnij wszystkie pola.';
        } elseif ($password !== $confirm) {
            $error = 'Hasła nie są zgodne.';
        } elseif (strlen($password) < 8) {
            $error = 'Hasło musi mieć co najmniej 8 znaków.';
        } else {
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT id, reset_token_expires FROM users WHERE reset_token = :token LIMIT 1');
            $stmt->execute(['token' => $token]);
            $user = $stmt->fetch();

            if (!$user || strtotime($user['reset_token_expires']) < time()) {
                $error = 'Token jest nieprawidłowy lub wygasł.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $update = $pdo->prepare('UPDATE users SET password_hash = :hash, reset_token = NULL, reset_token_expires = NULL WHERE id = :id');
                $update->execute(['hash' => $hash, 'id' => $user['id']]);
                $success = 'Hasło zostało zresetowane. Możesz się teraz zalogować.';
            }
        }
    }
}

$token = $_GET['token'] ?? null;
require_once __DIR__ . '/includes/header.php';
?>
<section class="reset-password-page">
    <?php if ($token): ?>
        <h1>Resetuj hasło</h1>
        <?php if ($error !== null): ?>
            <div class="error-message"><?php echo sanitize($error); ?></div>
        <?php elseif ($success !== null): ?>
            <div class="success-message"><?php echo sanitize($success); ?></div>
        <?php endif; ?>
        <form method="post" action="reset_password.php">
            <input type="hidden" name="token" value="<?php echo sanitize($token); ?>">
            <label for="password">Nowe hasło</label>
            <input type="password" id="password" name="password" required>
            <label for="confirm_password">Powtórz hasło</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
            <button type="submit" name="reset_password">Resetuj hasło</button>
        </form>
    <?php else: ?>
        <h1>Poproś o reset hasła</h1>
        <?php if ($error !== null): ?>
            <div class="error-message"><?php echo sanitize($error); ?></div>
        <?php elseif ($success !== null): ?>
            <div class="success-message"><?php echo sanitize($success); ?></div>
            <?php if (!empty($resetLink)): ?>
                <p>Link resetujący:</p>
                <a href="<?php echo sanitize($resetLink); ?>"><?php echo sanitize($resetLink); ?></a>
            <?php endif; ?>
        <?php endif; ?>
        <form method="post" action="reset_password.php">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
            <button type="submit" name="request_reset">Wyślij link resetu</button>
        </form>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php';
