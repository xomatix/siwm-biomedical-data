<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($email === '' || $password === '' || $confirm === '') {
        $error = 'Wypełnij wszystkie pola.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Nieprawidłowy adres email.';
    } elseif (strlen($password) < 8) {
        $error = 'Hasło musi mieć co najmniej 8 znaków.';
    } elseif ($password !== $confirm) {
        $error = 'Hasła nie są zgodne.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        if ($stmt->fetch()) {
            $error = 'Adres email jest już zajęty.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $insert = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)');
            $insert->execute(['email' => $email, 'password_hash' => $hash]);
            flashMessage('info', 'Rejestracja zakończona. Zaloguj się.');
            redirect('index.php');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="auth-form">
    <h1>Rejestracja</h1>
    <?php if ($error !== null): ?>
        <div class="error-message"><?php echo sanitize($error); ?></div>
    <?php endif; ?>
    <form method="post" action="register.php">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?php echo sanitize($_POST['email'] ?? ''); ?>" required>

        <label for="password">Hasło</label>
        <input type="password" id="password" name="password" required>

        <label for="confirm_password">Powtórz hasło</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <button type="submit">Zarejestruj</button>
    </form>
    <p>Masz konto? <a href="index.php">Zaloguj się</a></p>
</section>
<?php require_once __DIR__ . '/includes/footer.php';
