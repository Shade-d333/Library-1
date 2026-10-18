<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $d): void
{
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_logged()) {
    respond(['ok' => false, 'error' => 'Требуется авторизация']);
}

$action    = $_POST['action']     ?? '';
$bookId    = (int)($_POST['book_id']    ?? 0);
$userId    = (int)($_POST['user_id']    ?? 0);
$loanId    = (int)($_POST['loan_id']    ?? 0);
$requestId = (int)($_POST['request_id'] ?? 0);

try {
    switch ($action) {

        /* ---------- Читатель: подать заявку ---------- */
        case 'request': {
            $st = $pdo->prepare("SELECT available_copies FROM books WHERE id = ?");
            $st->execute([$bookId]);
            $avail = $st->fetchColumn();
            if ($avail === false) respond(['ok' => false, 'error' => 'Книга не найдена']);
            if ((int)$avail < 1)  respond(['ok' => false, 'error' => 'Книга выдана, заявку подать нельзя']);

            $uid = (int)$_SESSION['user']['id'];
            $st  = $pdo->prepare("SELECT COUNT(*) FROM requests
                                  WHERE user_id = ? AND book_id = ? AND status = 'pending'");
            $st->execute([$uid, $bookId]);
            if ((int)$st->fetchColumn() > 0) {
                respond(['ok' => false, 'error' => 'Заявка уже отправлена']);
            }

            $st = $pdo->prepare("SELECT COUNT(*) FROM loans
                                  WHERE user_id = ? AND book_id = ?
                                    AND status IN ('active','return_pending')");
            $st->execute([$uid, $bookId]);
            if ((int)$st->fetchColumn() > 0) {
                respond(['ok' => false, 'error' => 'Книга уже у вас на руках']);
            }

            $pdo->prepare("INSERT INTO requests (user_id, book_id) VALUES (?, ?)")
                ->execute([$uid, $bookId]);
            respond(['ok' => true, 'message' => 'Заявка отправлена администратору']);
        }

        /* ---------- Читатель: отменить заявку ---------- */
        case 'cancel_request': {
            if (!$requestId) respond(['ok' => false, 'error' => 'Некорректные данные']);

            $st = $pdo->prepare("DELETE FROM requests
                                 WHERE id = ? AND user_id = ? AND status = 'pending'");
            $st->execute([$requestId, (int)$_SESSION['user']['id']]);
            if ($st->rowCount() === 0) {
                respond(['ok' => false, 'error' => 'Заявка не найдена или уже обработана']);
            }
            respond(['ok' => true, 'message' => 'Заявка отменена']);
        }

        /* ---------- Читатель: вернуть книгу ---------- */
        case 'request_return': {
            if (!$loanId) respond(['ok' => false, 'error' => 'Некорректные данные']);

            $st = $pdo->prepare("UPDATE loans
                                 SET status = 'return_pending'
                                 WHERE id = ? AND user_id = ? AND status = 'active'");
            $st->execute([$loanId, (int)$_SESSION['user']['id']]);
            if ($st->rowCount() === 0) {
                respond(['ok' => false, 'error' => 'Выдача не найдена или уже в процессе возврата']);
            }
            respond(['ok' => true, 'message' => 'Заявка на возврат отправлена. Ожидайте подтверждения.']);
        }

        /* ---------- Админ: выдать книгу ---------- */
        case 'issue': {
            if (!is_admin())          respond(['ok' => false, 'error' => 'Недостаточно прав']);
            if (!$userId || !$bookId) respond(['ok' => false, 'error' => 'Некорректные данные']);

            $pdo->beginTransaction();

            $st = $pdo->prepare("SELECT available_copies FROM books WHERE id = ? FOR UPDATE");
            $st->execute([$bookId]);
            $avail = $st->fetchColumn();
            if ($avail === false || (int)$avail < 1) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'Нет доступных экземпляров']);
            }

            $days = (int)LOAN_DAYS;
            $pdo->prepare(
                "INSERT INTO loans (user_id, book_id, request_id, issued_at, due_at)
                 VALUES (?,?,?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL $days DAY))"
            )->execute([$userId, $bookId, $requestId ?: null]);

            $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?")
                ->execute([$bookId]);

            if ($requestId) {
                $pdo->prepare("UPDATE requests SET status='issued', processed_at=NOW() WHERE id = ?")
                    ->execute([$requestId]);
            } else {
                $pdo->prepare("UPDATE requests SET status='issued', processed_at=NOW()
                               WHERE user_id = ? AND book_id = ? AND status = 'pending'")
                    ->execute([$userId, $bookId]);
            }

            $pdo->commit();
            respond(['ok' => true, 'message' => 'Книга выдана читателю']);
        }

        /* ---------- Админ: отклонить заявку ---------- */
        case 'reject_request': {
            if (!is_admin()) respond(['ok' => false, 'error' => 'Недостаточно прав']);
            if (!$requestId) respond(['ok' => false, 'error' => 'Некорректные данные']);

            $st = $pdo->prepare("UPDATE requests
                                 SET status='rejected', processed_at=NOW()
                                 WHERE id = ? AND status = 'pending'");
            $st->execute([$requestId]);
            if ($st->rowCount() === 0) {
                respond(['ok' => false, 'error' => 'Заявка уже обработана']);
            }
            respond(['ok' => true, 'message' => 'Заявка отклонена']);
        }

        /* ---------- Админ: продлить срок ---------- */
        case 'extend': {
            if (!is_admin()) respond(['ok' => false, 'error' => 'Недостаточно прав']);
            $days = (int)($_POST['days'] ?? 14);
            if (!in_array($days, [7, 14, 30], true)) {
                respond(['ok' => false, 'error' => 'Недопустимый срок']);
            }
            $st = $pdo->prepare(
                "UPDATE loans
                 SET due_at = DATE_ADD(due_at, INTERVAL $days DAY),
                     extension_days = extension_days + $days
                 WHERE id = ? AND status = 'active'"
            );
            $st->execute([$loanId]);
            if ($st->rowCount() === 0) respond(['ok' => false, 'error' => 'Выдача не найдена']);
            respond(['ok' => true, 'message' => "Срок продлён на $days дн."]);
        }

        /* ---------- Админ: принять книгу (возврат) ---------- */
        case 'accept': {
            if (!is_admin()) respond(['ok' => false, 'error' => 'Недостаточно прав']);

            $pdo->beginTransaction();
            $st = $pdo->prepare(
                "SELECT book_id FROM loans
                 WHERE id = ? AND status IN ('active','return_pending')
                 FOR UPDATE"
            );
            $st->execute([$loanId]);
            $bId = $st->fetchColumn();
            if (!$bId) {
                $pdo->rollBack();
                respond(['ok' => false, 'error' => 'Выдача не найдена']);
            }

            $pdo->prepare("UPDATE loans SET status='returned', returned_at = CURDATE() WHERE id = ?")
                ->execute([$loanId]);
            $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?")
                ->execute([(int)$bId]);

            $pdo->commit();
            respond(['ok' => true, 'message' => 'Книга принята в библиотеку']);
        }

        default:
            respond(['ok' => false, 'error' => 'Неизвестное действие']);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond(['ok' => false, 'error' => 'Ошибка сервера']);
}