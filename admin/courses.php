<?php

/**
 * Admin: quản lý khóa học.
 * "Gỡ khóa học" = chuyển status từ published sang draft (KHÔNG xóa dòng nào, nên bài học,
 * ghi danh, bài nộp, điểm vẫn nguyên). Muốn hoàn tác thì bấm "Công khai lại".
 */
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$statusLabels = ['published' => 'Công khai', 'draft' => 'Bản nháp'];
$statusBadges = ['published' => 'success',   'draft' => 'warning'];
$perPage      = 20;

// ---- Đọc tham số lọc (chỉ nhận chuỗi) ----
$q = $_GET['q'] ?? '';
$q = is_string($q) ? trim($q) : '';

$statusFilter = $_GET['status'] ?? '';
if (!is_string($statusFilter) || !isset($statusLabels[$statusFilter])) {
    $statusFilter = '';
}
$pageParam = max(1, (int) ($_GET['page'] ?? 1));

$qs = http_build_query(array_filter([
    'q'      => $q,
    'status' => $statusFilter,
    'page'   => $pageParam > 1 ? $pageParam : null,
]));
$listUrl = url('admin/courses.php') . ($qs !== '' ? '?' . $qs : '');

// ================= XỬ LÝ POST =================
if (isPost()) {
    if (!csrfVerify()) {
        flash('danger', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
        redirect($listUrl);
    }

    $action   = $_POST['action'] ?? '';
    $courseId = (int) ($_POST['course_id'] ?? 0);

    $stmt = db()->prepare('SELECT id, title, status FROM courses WHERE id = ? LIMIT 1');
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();

    if (!$course) {
        flash('danger', 'Không tìm thấy khóa học.');
    } elseif ($action === 'unpublish') {
        if ($course['status'] === 'published') {
            db()->prepare("UPDATE courses SET status = 'draft' WHERE id = ?")->execute([$courseId]);
            flash('success', 'Đã gỡ khóa học khỏi trang công khai: ' . $course['title']);
        } else {
            flash('info', 'Khóa học này đang là bản nháp.');
        }
    } elseif ($action === 'publish') {
        if ($course['status'] === 'draft') {
            db()->prepare("UPDATE courses SET status = 'published' WHERE id = ?")->execute([$courseId]);
            flash('success', 'Đã công khai lại khóa học: ' . $course['title']);
        } else {
            flash('info', 'Khóa học này đang công khai.');
        }
    } else {
        flash('danger', 'Thao tác không hợp lệ.');
    }

    redirect($listUrl);
}

// ================= TRUY VẤN DANH SÁCH =================
$where  = [];
$params = [];

if ($q !== '') {
    $where[]  = 'c.title LIKE ?';
    $params[] = '%' . addcslashes($q, '%_\\') . '%';
}
if ($statusFilter !== '') {
    $where[]  = 'c.status = ?';
    $params[] = $statusFilter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare("SELECT COUNT(*) FROM courses c $whereSql");
$stmt->execute($params);
$pg = paginate((int) $stmt->fetchColumn(), $perPage);

$sql = "SELECT c.id, c.title, c.status, c.created_at,
               u.full_name AS teacher_name, cat.name AS category_name,
               (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id)     AS lesson_count,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count
        FROM courses c
        JOIN users u ON u.id = c.teacher_id
        LEFT JOIN categories cat ON cat.id = c.category_id
        $whereSql
        ORDER BY c.created_at DESC, c.id DESC
        LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

$pageTitle = 'Quản lý khóa học';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-3">Quản lý khóa học</h3>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-5">
        <input type="search" name="q" class="form-control" placeholder="Tìm theo tên khóa học..."
            value="<?= e($q) ?>">
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">Tất cả trạng thái</option>
            <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
        <button class="btn btn-outline-primary flex-grow-1" type="submit"><i class="bi bi-search"></i> Lọc</button>
        <?php if ($q !== '' || $statusFilter !== ''): ?>
            <a href="<?= url('admin/courses.php') ?>" class="btn btn-outline-secondary" title="Xóa bộ lọc"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
    </div>
</form>

<?php if (!$courses): ?>
    <div class="alert alert-info">Không có khóa học nào phù hợp.</div>
<?php else: ?>
    <p class="text-muted small">Tổng cộng <strong><?= (int) $pg['total'] ?></strong> khóa học</p>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Khóa học</th>
                    <th>Giảng viên</th>
                    <th>Danh mục</th>
                    <th class="text-center">Bài học</th>
                    <th class="text-center">Học viên</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $c): ?>
                    <tr>
                        <td><?= (int) $c['id'] ?></td>
                        <td>
                            <a href="<?= url('course.php?id=' . (int) $c['id']) ?>" class="text-decoration-none">
                                <?= e($c['title']) ?>
                            </a>
                        </td>
                        <td><?= e($c['teacher_name']) ?></td>
                        <td><?= e($c['category_name'] ?? 'Chưa phân loại') ?></td>
                        <td class="text-center"><?= (int) $c['lesson_count'] ?></td>
                        <td class="text-center"><?= (int) $c['student_count'] ?></td>
                        <td>
                            <span class="badge text-bg-<?= e($statusBadges[$c['status']] ?? 'secondary') ?>">
                                <?= e($statusLabels[$c['status']] ?? $c['status']) ?>
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($c['status'] === 'published'): ?>
                                <form method="post" action="<?= e($listUrl) ?>" class="d-inline"
                                    onsubmit="return confirm('Gỡ khóa học này khỏi trang công khai? Sinh viên đã ghi danh sẽ tạm thời không vào học được. Bạn có thể công khai lại sau.')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="unpublish">
                                    <input type="hidden" name="course_id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Gỡ</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e($listUrl) ?>" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="publish">
                                    <input type="hidden" name="course_id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success">Công khai lại</button>
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