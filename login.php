<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === AUTH_EMAIL && password_verify($password, AUTH_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['email'] = $email;
        header('Location: index.php');
        exit;
    }

    $error = 'Invalid email or password';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Log In - Jerry's Clock Repair</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page login-page">
    <h1>Jerry's Clock Repair</h1>
    <div class="subhead">Log in to continue</div>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="actions">
            <button type="submit">Log In</button>
        </div>
    </form>
</div>
</body>
</html>
