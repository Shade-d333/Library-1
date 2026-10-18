<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged(): bool
{
    return !empty($_SESSION['user']);
}

function is_admin(): bool
{
    return is_logged() && $_SESSION['user']['role'] === 'admin';
}

function require_login(): void
{
    if (!is_logged()) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        header('Location: index.php');
        exit;
    }
}

/** Количество просроченных книг для счётчика в меню */
function overdue_count(PDO $pdo): int
{
    if (!is_logged()) {
        return 0;
    }
    if (is_admin()) {
        return (int)$pdo->query(
            "SELECT COUNT(*) FROM loans WHERE status='active' AND due_at < CURDATE()"
        )->fetchColumn();
    }
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM loans WHERE status='active' AND due_at < CURDATE() AND user_id = ?"
    );
    $st->execute([$_SESSION['user']['id']]);
    return (int)$st->fetchColumn();
}

/** Просрочена ли выдача и на сколько дней */
function overdue_days(string $dueAt): int
{
    $diff = (strtotime(date('Y-m-d')) - strtotime($dueAt)) / 86400;
    return $diff > 0 ? (int)$diff : 0;
}

function ru_date(?string $d): string
{
    return $d ? date('d.m.Y', strtotime($d)) : '—';
}

/** Градиент обложки по id книги */
function cover_gradient(int $id): string
{
    $palette = [
        'linear-gradient(135deg,#8B5CF6,#C4B5FD)',
        'linear-gradient(135deg,#22C55E,#86EFAC)',
        'linear-gradient(135deg,#EF4444,#FCA5A5)',
        'linear-gradient(135deg,#3B82F6,#93C5FD)',
        'linear-gradient(135deg,#F59E0B,#FCD34D)',
        'linear-gradient(135deg,#EC4899,#F9A8D4)',
    ];
    return $palette[$id % count($palette)];
}

/** SVG-иконка книги */
function book_icon(): string
{
    return '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
            </svg>';
}
/** Количество активных заявок */
function pending_requests_count(PDO $pdo): int
{
    if (!is_logged()) {
        return 0;
    }
    if (is_admin()) {
        return (int)$pdo->query(
            "SELECT COUNT(*) FROM requests WHERE status = 'pending'"
        )->fetchColumn();
    }
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM requests WHERE status = 'pending' AND user_id = ?"
    );
    $st->execute([$_SESSION['user']['id']]);
    return (int)$st->fetchColumn();
}
/** Количество книг, ожидающих приёма администратором */
function pending_returns_count(PDO $pdo): int
{
    if (!is_logged()) {
        return 0;
    }
    if (is_admin()) {
        return (int)$pdo->query(
            "SELECT COUNT(*) FROM loans WHERE status = 'return_pending'"
        )->fetchColumn();
    }
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM loans WHERE status = 'return_pending' AND user_id = ?"
    );
    $st->execute([$_SESSION['user']['id']]);
    return (int)$st->fetchColumn();
}