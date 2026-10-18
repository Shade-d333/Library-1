<?php
// ВНИМАНИЕ: запустить один раз, затем удалить файл с хостинга!
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  role ENUM('reader','admin') NOT NULL DEFAULT 'reader',
  card_number VARCHAR(20) UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS authors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS genres (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS books (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  author_id INT NOT NULL,
  genre_id INT NOT NULL,
  isbn VARCHAR(20),
  description TEXT,
  total_copies INT NOT NULL DEFAULT 1,
  available_copies INT NOT NULL DEFAULT 1,
  FOREIGN KEY (author_id) REFERENCES authors(id),
  FOREIGN KEY (genre_id) REFERENCES genres(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  book_id INT NOT NULL,
  status ENUM('pending','issued','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS loans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  book_id INT NOT NULL,
  request_id INT NULL,
  issued_at DATE NOT NULL,
  due_at DATE NOT NULL,
  returned_at DATE NULL,
  extension_days INT NOT NULL DEFAULT 0,
  status ENUM('active','return_pending','returned') NOT NULL DEFAULT 'active',
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  INDEX idx_loans_active (status, due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ---------- пользователи ---------- */
$users = [
    ['admin@library.ru', 'admin123', 'Администратор', 'admin', null],
    ['petrov@mail.ru',   '12345',    'Петров Иван',    'reader', '123456'],
    ['sidorova@mail.ru', '12345',    'Сидорова Мария', 'reader', '123457'],
    ['kozlov@mail.ru',   '12345',    'Козлов Алексей', 'reader', '123458'],
];
$st = $pdo->prepare("INSERT IGNORE INTO users (email, password_hash, full_name, role, card_number)
                     VALUES (?,?,?,?,?)");
foreach ($users as [$mail, $pass, $name, $role, $card]) {
    $st->execute([$mail, password_hash($pass, PASSWORD_DEFAULT), $name, $role, $card]);
}

/* ---------- авторы и жанры ---------- */
$aSt = $pdo->prepare("INSERT IGNORE INTO authors (name) VALUES (?)");
$gSt = $pdo->prepare("INSERT IGNORE INTO genres  (name) VALUES (?)");
$authors = ['Джордж Оруэлл','Михаил Булгаков','Дж. К. Роулинг','Лев Толстой',
            'Эрих Мария Ремарк','Пауло Коэльо','Харпер Ли','Фрэнк Герберт'];
$genres  = ['Антиутопия','Классика','Фэнтези','Проза','Философия','Фантастика'];
foreach ($authors as $a) $aSt->execute([$a]);
foreach ($genres  as $g) $gSt->execute([$g]);

$authorId = fn(string $n) => (int)$pdo->query("SELECT id FROM authors WHERE name=" . $pdo->quote($n))->fetchColumn();
$genreId  = fn(string $n) => (int)$pdo->query("SELECT id FROM genres  WHERE name=" . $pdo->quote($n))->fetchColumn();

/* ---------- книги ---------- */
$books = [
    ['1984',                 'Джордж Оруэлл',      'Антиутопия', '978-5-17-098825-6', 'Классическая антиутопия о тотальном контроле над личностью.', 1],
    ['Мастер и Маргарита',   'Михаил Булгаков',    'Классика',   '978-5-17-090625-1', 'Роман о добре и зле, любви и трусости.',                     1],
    ['Гарри Поттер',         'Дж. К. Роулинг',     'Фэнтези',    '978-5-389-07435-4', 'Первая книга о юном волшебнике.',                            1],
    ['Война и мир',          'Лев Толстой',        'Классика',   '978-5-17-982345-1', 'Роман-эпопея о России эпохи войн с Наполеоном.',             1],
    ['Три товарища',         'Эрих Мария Ремарк',  'Проза',      '978-5-17-118144-2', 'История дружбы и любви на фоне послевоенной Европы.',        1],
    ['Алхимик',              'Пауло Коэльо',       'Философия',  '978-5-17-088325-0', 'Притча о поиске своей Судьбы.',                              1],
    ['Убить пересмешника',   'Харпер Ли',          'Классика',   '978-5-17-102235-6', 'Роман о взрослении на фоне расовой несправедливости.',       1],
    ['Дюна',                 'Фрэнк Герберт',      'Фантастика', '978-5-17-093223-8', 'Фантастическая сага о планете Арракис.',                     1],
];
$bSt = $pdo->prepare("INSERT INTO books (title, author_id, genre_id, isbn, description, total_copies, available_copies)
                      VALUES (?,?,?,?,?,?,?)");
if ((int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn() === 0) {
    foreach ($books as [$t, $a, $g, $isbn, $d, $c]) {
        $bSt->execute([$t, $authorId($a), $genreId($g), $isbn, $d, $c, $c]);
    }
}

/* ---------- выданные книги (3 выдано, 1 просрочено) ---------- */
$uId = fn(string $n) => (int)$pdo->query("SELECT id FROM users WHERE full_name=" . $pdo->quote($n))->fetchColumn();
$bkId = fn(string $t) => (int)$pdo->query("SELECT id FROM books WHERE title=" . $pdo->quote($t))->fetchColumn();

if ((int)$pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn() === 0) {
    $loanSt = $pdo->prepare("INSERT INTO loans (user_id, book_id, issued_at, due_at, status)
                             VALUES (?,?,CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'active')");
    // просрочено на 5 дней
    $loanSt->execute([$uId('Петров Иван'), $bkId('Мастер и Маргарита'), -5]);
    // в срок
    $loanSt->execute([$uId('Сидорова Мария'), $bkId('Три товарища'), 5]);
    $loanSt->execute([$uId('Козлов Алексей'), $bkId('Дюна'), 10]);

    $pdo->exec("UPDATE books SET available_copies = 0
                WHERE title IN ('Мастер и Маргарита','Три товарища','Дюна')");
}

echo '<h2>Установка завершена ✅</h2>
      <p>Администратор: <b>admin@library.ru / admin123</b><br>
         Читатель: <b>petrov@mail.ru / 12345</b></p>
      <p><a href="index.php">Перейти к каталогу</a> &nbsp;|&nbsp;
         <b style="color:#c00">Удалите install.php с сервера!</b></p>';