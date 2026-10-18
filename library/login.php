<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

if (is_logged()) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = (string)($_POST['password'] ?? '');

    $st = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $st->execute([$email]);
    $u = $st->fetch();

    if ($u && password_verify($pass, $u['password_hash'])) {
        $_SESSION['user'] = [
            'id'        => (int)$u['id'],
            'email'     => $u['email'],
            'full_name' => $u['full_name'],
            'role'      => $u['role'],
        ];
        header('Location: index.php');
        exit;
    }
    $error = 'Неверный email или пароль.';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Вход — Библиотека</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-body">
<div class="auth-card">
  <div class="auth-logo">📚</div>
  <h1>Вход в систему</h1>
  <p class="muted">Введите email и пароль</p>

  <?php if ($error): ?><div class="alert alert--err"><?= e($error) ?></div><?php endif; ?>

  <form method="post">
    <label class="field"><span>Email</span>
      <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
    </label>
    <label class="field"><span>Пароль</span>
      <input type="password" name="password" required>
    </label>
    <button class="btn btn--primary btn--block" type="submit">Войти</button>
  </form>

  <p class="auth-foot">Нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
</div>
</body>
</html>