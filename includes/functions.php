<?php

/**
 * Các hàm tiện ích dùng chung.
 */

/** Escape HTML để chống XSS. LUÔN dùng khi in dữ liệu ra trang: <?= e($text) ?> */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Tạo URL tuyệt đối từ đường dẫn tương đối: url('login.php') */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Chuyển hướng rồi dừng script. Phải gọi TRƯỚC khi in bất kỳ HTML nào. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// ---------- Flash message (thông báo hiện 1 lần) ----------
/** $type: success | danger | warning | info (theo Bootstrap) */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// ---------- CSRF ----------
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** In input ẩn chứa token. Đặt trong mọi <form method="post">. */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function csrfVerify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// ---------- Form ----------
/** Giữ lại giá trị người dùng đã nhập khi form có lỗi. */
function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}


// ---------- Phân trang (dùng chung cho các trang danh sách) ----------
/**
 * Tính thông số phân trang. Trang hiện tại lấy từ ?page= trên URL.
 * Dùng: $pg = paginate($tongSoDong, 20);
 *       ... LIMIT {$pg['perPage']} OFFSET {$pg['offset']}
 * Trả về: page, perPage, total, totalPages, offset
 */
function paginate(int $total, int $perPage = 20): array
{
    $perPage    = max(1, $perPage);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page       = min(max(1, (int) ($_GET['page'] ?? 1)), $totalPages);   // kẹp trong [1, totalPages]

    return [
        'page'       => $page,
        'perPage'    => $perPage,
        'total'      => $total,
        'totalPages' => $totalPages,
        'offset'     => ($page - 1) * $perPage,
    ];
}

/** URL của trang $page, giữ nguyên các tham số lọc đang có (q, role, status...). */
function pageLink(int $page): string
{
    $params = $_GET;
    unset($params['page']);
    if ($page > 1) {
        $params['page'] = $page;
    }
    return $_SERVER['SCRIPT_NAME'] . ($params ? '?' . http_build_query($params) : '');
}

/** HTML thanh phân trang Bootstrap. Chỉ có 1 trang thì không in gì. */
function renderPagination(array $pg): string
{
    if ($pg['totalPages'] <= 1) {
        return '';
    }

    $html = '<nav class="mt-4" aria-label="Phân trang"><ul class="pagination justify-content-center">';

    $html .= '<li class="page-item' . ($pg['page'] <= 1 ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . e(pageLink($pg['page'] - 1)) . '">&laquo;</a></li>';

    for ($i = 1; $i <= $pg['totalPages']; $i++) {
        $html .= '<li class="page-item' . ($i === $pg['page'] ? ' active' : '') . '">'
            . '<a class="page-link" href="' . e(pageLink($i)) . '">' . $i . '</a></li>';
    }

    $html .= '<li class="page-item' . ($pg['page'] >= $pg['totalPages'] ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . e(pageLink($pg['page'] + 1)) . '">&raquo;</a></li>';

    return $html . '</ul></nav>';
}
