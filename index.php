<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Wypełnij wszystkie pola.';
    } elseif (loginUser($email, $password)) {
        redirect('dashboard.php');
    } else {
        $error = 'Błędny email lub hasło.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="auth-form">
    <h1>Logowanie</h1>
    <?php if ($error !== null): ?>
        <div class="error-message"><?php echo sanitize($error); ?></div>
    <?php endif; ?>
    <form method="post" action="index.php">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?php echo sanitize($_POST['email'] ?? ''); ?>" required>

        <label for="password">Hasło</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Zaloguj</button>
    </form>
    <p><a href="reset_password.php">Zapomniałeś hasła?</a></p>
    <p>Brak konta? <a href="register.php">Zarejestruj się</a></p>
</section>
<?php require_once __DIR__ . '/includes/footer.php';
