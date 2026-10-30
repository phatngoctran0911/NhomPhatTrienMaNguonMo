<?php

/**
 * Admin: trang tổng quan (thống kê nhanh).
 */
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

/** Chạy câu SQL trả về 1 con số (COUNT...) */
$count = fn(string $sql): int => (int) db()->query($sql)->fetchColumn();

// Số người dùng theo vai trò: FETCH_KEY_PAIR trả về ['student' => '12', ...]; thiếu vai trò nào thì mặc định 0
$roleCounts = array_map('intval', db()->query('SELECT role, COUNT(*) FROM users GROUP BY role')->fetchAll(PDO::FETCH_KEY_PAIR));
$roleCounts = array_merge(['admin' => 0, 'teacher' => 0, 'student' => 0], $roleCounts);

$courseCounts = array_map('intval', db()->query('SELECT status, COUNT(*) FROM courses GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR));
$courseCounts = array_merge(['published' => 0, 'draft' => 0], $courseCounts);

$totalUsers  = array_sum($roleCounts);
$lockedUsers = $count('SELECT COUNT(*) FROM users WHERE is_active = 0');
$enrollments = $count('SELECT COUNT(*) FROM enrollments');
$assignments = $count('SELECT COUNT(*) FROM assignments');
$submissions = $count('SELECT COUNT(*) FROM submissions');
$ungraded    = $count('SELECT COUNT(*) FROM submissions WHERE score IS NULL');

$newest = db()->query('SELECT id, full_name, email, role, created_at FROM users ORDER BY created_at DESC, id DESC LIMIT 5')->fetchAll();
$roleNames = ['admin' => 'Quản trị', 'teacher' => 'Giảng viên', 'student' => 'Sinh viên'];

$pageTitle = 'Trang quản trị viên';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-1">Trang quản trị viên</h3>
<p class="text-muted">Xin chào <strong><?= e(currentUser()['full_name']) ?></strong>. Dưới đây là tổng quan hệ thống.</p>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Người dùng</div>
                <div class="fs-2 fw-bold"><?= $totalUsers ?></div>
                <div class="small text-muted">
                    <?= $roleCounts['admin'] ?> quản trị · <?= $roleCounts['teacher'] ?> giảng viên · <?= $roleCounts['student'] ?> sinh viên
                    <?php if ($lockedUsers > 0): ?><br><span class="text-danger"><?= $lockedUsers ?> tài khoản đang bị khóa</span><?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-white border-0">
                <a href="<?= url('admin/users.php') ?>" class="btn btn-sm btn-outline-primary">Quản lý người dùng</a>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Khóa học</div>
                <div class="fs-2 fw-bold"><?= array_sum($courseCounts) ?></div>
                <div class="small text-muted"><?= $courseCounts['published'] ?> công khai · <?= $courseCounts['draft'] ?> bản nháp</div>
            </div>
            <div class="card-footer bg-white border-0">
                <a href="<?= url('admin/courses.php') ?>" class="btn btn-sm btn-outline-primary">Quản lý khóa học</a>
                <a href="<?= url('admin/categories.php') ?>" class="btn btn-sm btn-outline-secondary">Danh mục</a>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Lượt ghi danh</div>
                <div class="fs-2 fw-bold"><?= $enrollments ?></div>
                <div class="small text-muted"><?= $assignments ?> bài tập đã giao</div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Bài nộp</div>
                <div class="fs-2 fw-bold"><?= $submissions ?></div>
                <div class="small text-muted"><?= $ungraded ?> bài chưa chấm</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-bold">Tài khoản mới nhất</div>
    <?php if (!$newest): ?>
        <div class="card-body text-muted">Chưa có người dùng nào.</div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($newest as $u): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>
                        <?= e($u['full_name']) ?>
                        <small class="text-muted">· <?= e($u['email']) ?></small>
                    </span>
                    <span>
                        <span class="badge text-bg-light border"><?= e($roleNames[$u['role']] ?? $u['role']) ?></span>
                        <small class="text-muted ms-2"><?= e(date('d/m/Y H:i', strtotime($u['created_at']))) ?></small>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>