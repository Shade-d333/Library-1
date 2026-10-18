<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

if (is_logged()) { header('Location: index.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $p1    = (string)($_POST['password'] ?? '');
    $p2    = (string)($_POST['password2'] ?? '');

    if (mb_strlen($name) < 3)                        $errors[] = 'Укажите ФИО.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Некорректный email.';
    if (strlen($p1) < 5)                             $errors[] = 'Пароль минимум 5 символов.';
    if ($p1 !== $p2)                                 $errors[] = 'Пароли не совпадают.';

    if (!$errors) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $st->execute([$email]);
        if ((int)$st->fetchColumn() > 0) {
            $errors[] = 'Пользователь с таким email уже существует.';
        }
    }

    if (!$errors) {
        $card = (string)random_int(100000, 999999);
        $st = $pdo->prepare(
            "INSERT INTO users (email, password_hash, full_name, role, card_number)
             VALUES (?,?,?, 'reader', ?)"
        );
        $st->execute([$email, password_hash($p1, PASSWORD_DEFAULT), $name, $card]);

        $_SESSION['user'] = [
            'id'        => (int)$pdo->lastInsertId(),
            'email'     => $email,
            'full_name' => $name,
            'role'      => 'reader',
        ];
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Регистрация — Библиотека</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-body">
<div class="auth-card">
  <div class="auth-logo">📚</div>
  <h1>Регистрация читателя</h1>
  <p class="muted">Создайте личный кабинет</p>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert--err"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="post">
    <label class="field"><span>ФИО</span>
      <input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required>
    </label>
    <label class="field"><span>Email</span>
      <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
    </label>
    <label class="field"><span>Пароль</span>
      <input type="password" name="password" required>
    </label>
    <label class="field"><span>Повторите пароль</span>
      <input type="password" name="password2" required>
    </label>
    <button class="btn btn--primary btn--block" type="submit">Зарегистрироваться</button>
  </form>

  <p class="auth-foot">Уже есть аккаунт? <a href="login.php">Войти</a></p>
</div>
</body>
</html>