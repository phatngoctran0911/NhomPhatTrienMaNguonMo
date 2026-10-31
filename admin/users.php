<?php

/**
 * Admin: quản lý người dùng.
 * - Danh sách + tìm kiếm + lọc vai trò + phân trang 20 dòng
 * - Khóa / mở khóa tài khoản, đổi vai trò (thao tác thay đổi dữ liệu đều dùng POST + CSRF)
 */
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$roleNames = ['admin' => 'Quản trị', 'teacher' => 'Giảng viên', 'student' => 'Sinh viên'];
$roleBadges = ['admin' => 'danger',   'teacher' => 'info',       'student' => 'secondary'];
$perPage    = 20;                                   // đề: phân trang khi dữ liệu > 20 dòng
$me         = (int) currentUser()['id'];

// ---- Đọc tham số lọc từ URL (chỉ nhận chuỗi, bỏ qua dạng mảng ?q[]=...) ----
$q = $_GET['q'] ?? '';
$q = is_string($q) ? trim($q) : '';

$roleFilter = $_GET['role'] ?? '';
if (!is_string($roleFilter) || !isset($roleNames[$roleFilter])) {
    $roleFilter = '';
}
$pageParam = max(1, (int) ($_GET['page'] ?? 1));

// URL danh sách hiện tại (giữ bộ lọc + số trang) để quay lại sau khi xử lý POST
$qs = http_build_query(array_filter([
    'q'    => $q,
    'role' => $roleFilter,
    'page' => $pageParam > 1 ? $pageParam : null,
]));
$listUrl = url('admin/users.php') . ($qs !== '' ? '?' . $qs : '');

// ================= XỬ LÝ POST =================
if (isPost()) {
    if (!csrfVerify()) {
        flash('danger', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
        redirect($listUrl);
    }

    $action   = $_POST['action'] ?? '';
    $targetId = (int) ($_POST['user_id'] ?? 0);

    $stmt = db()->prepare('SELECT id, full_name, role, is_active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();

    if (!$target) {
        flash('danger', 'Không tìm thấy người dùng.');
    } elseif ((int) $target['id'] === $me) {
        // Chặn tự khóa / tự hạ quyền => hệ thống luôn còn ít nhất 1 admin đang hoạt động (chính bạn)
        flash('danger', 'Bạn không thể khóa hoặc đổi vai trò của chính mình.');
    } elseif ($action === 'toggle_active') {
        $newActive = $target['is_active'] ? 0 : 1;
        db()->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$newActive, $targetId]);
        flash('success', ($newActive ? 'Đã mở khóa tài khoản: ' : 'Đã khóa tài khoản: ') . $target['full_name']);
    } elseif ($action === 'change_role') {
        $newRole = $_POST['role'] ?? '';

        if (!is_string($newRole) || !isset($roleNames[$newRole])) {
            flash('danger', 'Vai trò không hợp lệ.');
        } elseif ($newRole === $target['role']) {
            flash('info', 'Vai trò không thay đổi.');
        } else {
            // Giảng viên đang phụ trách khóa học thì không cho đổi, tránh để lại khóa học "mồ côi"
            $courseCount = 0;
            if ($target['role'] === 'teacher') {
                $stmt = db()->prepare('SELECT COUNT(*) FROM courses WHERE teacher_id = ?');
                $stmt->execute([$targetId]);
                $courseCount = (int) $stmt->fetchColumn();
            }

            if ($courseCount > 0) {
                flash('danger', sprintf(
                    '%s đang phụ trách %d khóa học nên chưa thể đổi vai trò, vì các khóa học đó sẽ không còn người quản lý.',
                    $target['full_name'],
                    $courseCount
                ));
            } else {
                db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $targetId]);
                flash('success', sprintf('Đã đổi vai trò của %s thành %s.', $target['full_name'], $roleNames[$newRole]));
            }
        }
    } else {
        flash('danger', 'Thao tác không hợp lệ.');
    }

    redirect($listUrl);   // Post/Redirect/Get: F5 không gửi lại form
}

// ================= TRUY VẤN DANH SÁCH =================
$where  = [];
$params = [];

if ($q !== '') {
    $like     = '%' . addcslashes($q, '%_\\') . '%';   // escape ký tự đặc biệt của LIKE
    $where[]  = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $params[] = $like;
    $params[] = $like;
}
if ($roleFilter !== '') {
    $where[]  = 'u.role = ?';
    $params[] = $roleFilter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare("SELECT COUNT(*) FROM users u $whereSql");
$stmt->execute($params);
$pg = paginate((int) $stmt->fetchColumn(), $perPage);

// LIMIT/OFFSET lấy từ paginate() (đã là số nguyên) nên nối chuỗi an toàn
$sql = "SELECT u.id, u.full_name, u.email, u.role, u.is_active, u.created_at,
               (SELECT COUNT(*) FROM courses c WHERE c.teacher_id = u.id) AS course_count
        FROM users u
        $whereSql
        ORDER BY u.created_at DESC, u.id DESC
        LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Quản lý người dùng';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Quản lý người dùng</h3>
    <a href="<?= url('admin/user_form.php') ?>" class="btn btn-primary">
        <i class="bi bi-person-plus"></i> Tạo giảng viên
    </a>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-5">
        <input type="search" name="q" class="form-control" placeholder="Tìm theo tên hoặc email..."
            value="<?= e($q) ?>">
    </div>
    <div class="col-md-3">
        <select name="role" class="form-select">
            <option value="">Tất cả vai trò</option>
            <?php foreach ($roleNames as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $roleFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
        <button class="btn btn-outline-primary flex-grow-1" type="submit"><i class="bi bi-search"></i> Lọc</button>
        <?php if ($q !== '' || $roleFilter !== ''): ?>
            <a href="<?= url('admin/users.php') ?>" class="btn btn-outline-secondary" title="Xóa bộ lọc"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
    </div>
</form>

<?php if (!$users): ?>
    <div class="alert alert-info">Không có người dùng nào phù hợp.</div>
<?php else: ?>
    <p class="text-muted small">Tổng cộng <strong><?= (int) $pg['total'] ?></strong> người dùng</p>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Họ tên</th>
                    <th>Email</th>
                    <th>Vai trò</th>
                    <th>Trạng thái</th>
                    <th>Ngày tạo</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php $isMe = (int) $u['id'] === $me; ?>
                    <tr class="<?= $u['is_active'] ? '' : 'table-secondary' ?>">
                        <td><?= (int) $u['id'] ?></td>
                        <td>
                            <?= e($u['full_name']) ?>
                            <?php if ($isMe): ?><span class="badge text-bg-warning">Bạn</span><?php endif; ?>
                        </td>
                        <td><?= e($u['email']) ?></td>
                        <td>
                            <span class="badge text-bg-<?= e($roleBadges[$u['role']] ?? 'secondary') ?>">
                                <?= e($roleNames[$u['role']] ?? $u['role']) ?>
                            </span>
                            <?php if ($u['role'] === 'teacher'): ?>
                                <small class="text-muted"><?= (int) $u['course_count'] ?> khóa</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['is_active']): ?>
                                <span class="badge text-bg-success">Hoạt động</span>
                            <?php else: ?>
                                <span class="badge text-bg-dark">Đã khóa</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= e(date('d/m/Y H:i', strtotime($u['created_at']))) ?></td>
                        <td class="text-nowrap">
                            <?php if ($isMe): ?>
                                <span class="text-muted small">Không thể tự thao tác</span>
                            <?php else: ?>
                                <!-- Khóa / mở khóa -->
                                <form method="post" action="<?= e($listUrl) ?>" class="d-inline"
                                    onsubmit="return confirm('<?= $u['is_active'] ? 'Khóa tài khoản này?' : 'Mở khóa tài khoản này?' ?>')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <button type="submit"
                                        class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                        <?= $u['is_active'] ? 'Khóa' : 'Mở khóa' ?>
                                    </button>
                                </form>

                                <!-- Đổi vai trò -->
                                <form method="post" action="<?= e($listUrl) ?>" class="d-inline-flex gap-1 ms-2">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <select name="role" class="form-select form-select-sm w-auto">
                                        <?php foreach ($roleNames as $key => $label): ?>
                                            <option value="<?= e($key) ?>" <?= $u['role'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Đổi</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= renderPagination($pg) ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>