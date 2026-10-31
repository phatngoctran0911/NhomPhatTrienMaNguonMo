<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/courses.php';
requireRole('teacher');

$teacherId = (int) currentUser()['id'];

$sql = "SELECT c.*, cat.name AS category_name,
               (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id)     AS lesson_count,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count
        FROM courses c
        LEFT JOIN categories cat ON cat.id = c.category_id
        WHERE c.teacher_id = ?
        ORDER BY c.created_at DESC, c.id DESC";
$stmt = db()->prepare($sql);
$stmt->execute([$teacherId]);
$courses = $stmt->fetchAll();

$totalCourses   = count($courses);
$totalPublished = count(array_filter($courses, fn($c) => $c['status'] === 'published'));
$totalStudents  = array_sum(array_column($courses, 'student_count'));
$totalLessons   = array_sum(array_column($courses, 'lesson_count'));

$pageTitle = 'Quản lý khóa học';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Khóa học của tôi</h3>
    <a href="<?= url('teacher/course_form.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tạo khóa học</a>
</div>

<div class="row g-3 mb-4">
    <?php
    $stats = [
        ['Khóa học', $totalCourses, 'bi-journal-text', 'primary'],
        ['Đang công khai', $totalPublished, 'bi-globe', 'success'],
        ['Bài học', $totalLessons, 'bi-play-circle', 'info'],
        ['Lượt ghi danh', $totalStudents, 'bi-people', 'warning'],
    ];
    foreach ($stats as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-lg-3">
            <div class="card">
                <div class="card-body d-flex align-items-center">
                    <i class="bi <?= $icon ?> fs-2 text-<?= $color ?> me-3"></i>
                    <div>
                        <div class="fs-4 fw-bold lh-1"><?= (int) $value ?></div>
                        <div class="text-muted small"><?= e($label) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!$courses): ?>
    <div class="alert alert-info">Bạn chưa có khóa học nào. Bấm <strong>Tạo khóa học</strong> để bắt đầu.</div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Khóa học</th>
                        <th>Danh mục</th>
                        <th class="text-center">Trạng thái</th>
                        <th class="text-center">Bài học</th>
                        <th class="text-center">Học viên</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($courses as $c): ?>
                    <tr>
                        <td>
                            <a href="<?= url('course.php?id=' . (int) $c['id']) ?>" class="fw-semibold text-decoration-none">
                                <?= e($c['title']) ?>
                            </a>
                            <div class="text-muted small"><?= e(date('d/m/Y', strtotime($c['created_at']))) ?></div>
                        </td>
                        <td><?= e($c['category_name'] ?? '—') ?></td>
                        <td class="text-center">
                            <?php if ($c['status'] === 'published'): ?>
                                <span class="badge text-bg-success">Công khai</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Nháp</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= (int) $c['lesson_count'] ?></td>
                        <td class="text-center"><?= (int) $c['student_count'] ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= url('teacher/lessons.php?course_id=' . (int) $c['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-list-ul"></i> Bài học
                            </a>
                            <a href="<?= url('teacher/course_form.php?id=' . (int) $c['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Sửa
                            </a>
                            <form method="post" action="<?= url('teacher/course_delete.php') ?>" class="d-inline"
                                  onsubmit="return confirm('Xóa khóa học này? Toàn bộ bài học, ghi danh, bài tập liên quan sẽ bị xóa vĩnh viễn.');">
                                <?= csrfField() ?>
                                <input type="hidden" name="course_id" value="<?= (int) $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
