<?php

/**
 * Xác thực & phân quyền.
 * Mọi trang chỉ cần: require_once __DIR__ . '/includes/auth.php';
 * (file này tự nạp config, database, functions)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function isLoggedIn(): bool
{
    if (empty($_SESSION['user'])) {
        return false;                       // khách: không tốn query
    }

    static $valid = null;                   // nhớ kết quả trong cùng request
    if ($valid === null) {
        $stmt = db()->prepare('SELECT role, is_active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user']['id']]);
        $row = $stmt->fetch();

        if (!$row || !$row['is_active']) {
            unset($_SESSION['user']);
            flash('danger', 'Tài khoản của bạn đã bị khóa hoặc không còn tồn tại.');
            $valid = false;
        } else {
            $_SESSION['user']['role'] = $row['role'];   // role luôn là bản mới nhất
            $valid = true;
        }
    }
    return $valid;
}

function currentUser(): ?array
{
    return isLoggedIn() ? $_SESSION['user'] : null;
}

/** Kiểm tra người dùng hiện tại có một trong các vai trò: hasRole('admin', 'teacher') */
function hasRole(string ...$roles): bool
{
    return isLoggedIn() && in_array($_SESSION['user']['role'], $roles, true);
}

/** Trang chủ tương ứng với từng vai trò */
function roleHome(string $role): string
{
    $map = [
        'admin'   => 'admin/index.php',
        'teacher' => 'teacher/index.php',
        'student' => 'student/index.php',
    ];
    return url($map[$role] ?? 'index.php');
}

/** Lưu thông tin vào session sau khi đăng nhập thành công */
function loginUser(array $user): void
{
    session_regenerate_id(true);     // chống session fixation
    $_SESSION['user'] = [
        'id'        => (int) $user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
    ];
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Bắt buộc đăng nhập, nếu chưa thì chuyển về trang login */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        flash('warning', 'Vui lòng đăng nhập để tiếp tục.');
        redirect(url('login.php'));
    }
}

/** Bắt buộc đúng vai trò: requireRole('admin') hoặc requireRole('teacher', 'admin') */
function requireRole(string ...$roles): void
{
    requireLogin();
    if (!hasRole(...$roles)) {
        flash('danger', 'Bạn không có quyền truy cập trang này.');
        redirect(roleHome(currentUser()['role']));
    }
}

/** Chỉ cho khách (chưa đăng nhập) vào, dùng cho login/register */
function requireGuest(): void
{
    if (isLoggedIn()) {
        redirect(roleHome(currentUser()['role']));
    }
}
