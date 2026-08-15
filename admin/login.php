<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

bh_start_session();

if (($_GET['action'] ?? '') === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: /admin/login.php');
    exit;
}

if (bh_is_logged_in() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/');
    exit;
}

$error = '';

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['login_last_attempt'])) {
    $_SESSION['login_last_attempt'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $waitSeconds = min(30, $_SESSION['login_attempts'] * 2);
    $canTryAt = $_SESSION['login_last_attempt'] + $waitSeconds;

    if ($_SESSION['login_attempts'] >= 5 && time() < $canTryAt) {
        $error = 'Too many attempts. Please wait a few seconds and try again.';
    } else {
        $username = (string) ($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if (hash_equals(ADMIN_USERNAME, $username) && password_verify($password, ADMIN_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['login_attempts'] = 0;
            header('Location: /admin/');
            exit;
        }

        $_SESSION['login_attempts']++;
        $_SESSION['login_last_attempt'] = time();
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | The Brotherhood</title>
  <meta name="robots" content="noindex, nofollow">
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: "IBM Plex Sans", sans-serif;
      background: #F6F6F6;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
    }
    form {
      background: #fff;
      padding: 32px;
      border-radius: 8px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      width: 320px;
      max-width: 90vw;
    }
    h1 {
      font-family: "Crimson Text", serif;
      font-size: 22px;
      margin: 0 0 24px;
      color: #090D14;
    }
    label {
      display: block;
      font-size: 14px;
      margin-bottom: 6px;
      color: #090D14;
    }
    input {
      width: 100%;
      padding: 10px 12px;
      margin-bottom: 16px;
      border: 1px solid #CED4DA;
      border-radius: 6px;
      font-size: 14px;
    }
    button {
      width: 100%;
      padding: 11px;
      background: #A87C2D;
      color: #fff;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      font-size: 15px;
    }
    button:hover { background: #8A682B; }
    .error {
      color: #C0392B;
      background: #FCEBEA;
      padding: 10px 12px;
      border-radius: 6px;
      font-size: 13px;
      margin-bottom: 16px;
    }
  </style>
</head>
<body>
  <form method="post" autocomplete="off">
    <h1>The Brotherhood — Admin</h1>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <input type="hidden" name="action" value="login">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" required autofocus>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>
    <button type="submit">Log In</button>
  </form>
</body>
</html>
