<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/teacher.php';
require_once __DIR__ . '/../includes/upload.php';
requireRole('teacher');

$courseId = (int) ($_GET['course_id'] ?? 0);
$course   = requireOwnCourse($courseId);
$selfUrl  = url('teacher/lessons.php?course_id=' . $courseId);

// ---------- Xử lý xóa / đổi thứ tự (POST) ----------
if (isPost()) {
    if (!csrfVerify()) {
        flash('danger', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
        redirect($selfUrl);
    }

    $action   = $_POST['action'] ?? '';
    $lessonId = (int) ($_POST['lesson_id'] ?? 0);

    if ($action === 'delete') {
        // Điều kiện course_id đảm bảo chỉ xóa bài học thuộc khóa của mình
        $stmt = db()->prepare('SELECT file_path FROM lessons WHERE id = ? AND course_id = ?');
        $stmt->execute([$lessonId, $courseId]);
        $row = $stmt->fetch();

        if ($row) {
            db()->prepare('DELETE FROM lessons WHERE id = ? AND course_id = ?')->execute([$lessonId, $courseId]);
            deleteUploadedFile('lessons', $row['file_path']);
            renumberLessons($courseId);
            flash('success', 'Đã xóa bài học.');
        }
    } elseif ($action === 'move') {
        $dir  = ($_POST['dir'] ?? '') === 'up' ? -1 : 1;
        $stmt = db()->prepare('SELECT id FROM lessons WHERE course_id = ? ORDER BY sort_order, id');
        $stmt->execute([$courseId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $i = array_search($lessonId, $ids, true);
        $j = ($i === false) ? false : $i + $dir;
        if ($i !== false && $j >= 0 && $j < count($ids)) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            saveLessonOrder($ids);
        }
    }
    redirect($selfUrl);
}

$stmt = db()->prepare('SELECT * FROM lessons WHERE course_id = ? ORDER BY sort_order, id');
$stmt->execute([$courseId]);
$lessons = $stmt->fetchAll();
$last    = count($lessons) - 1;

$pageTitle = 'Bài học: ' . $course['title'];
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('teacher/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item active"><?= e($course['title']) ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h3 class="mb-1"><?= e($course['title']) ?></h3>
        <?php if ($course['status'] === 'published'): ?>
            <span class="badge text-bg-success">Công khai</span>
        <?php else: ?>
            <span class="badge text-bg-secondary">Nháp</span>
        <?php endif; ?>
        <span class="text-muted small ms-1"><?= count($lessons) ?> bài học</span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('teacher/course_form.php?id=' . $courseId) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-pencil"></i> Sửa khóa học
        </a>
        <a href="<?= url('teacher/lesson_form.php?course_id=' . $courseId) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Thêm bài học
        </a>
    </div>
</div>

<?php if (!$lessons): ?>
    <div class="alert alert-info">Khóa học chưa có bài học nào. Bấm <strong>Thêm bài học</strong> để bắt đầu.</div>
<?php else: ?>
    <div class="card">
        <ul class="list-group list-group-flush">
            <?php foreach ($lessons as $i => $l): ?>
                <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
                    <span class="badge text-bg-light border"><?= $i + 1 ?></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= e($l['title']) ?></div>
                        <div class="small text-muted">
                            <?php if ($l['video_url']): ?><i class="bi bi-camera-video"></i> Có video &nbsp;<?php endif; ?>
                            <?php if ($l['file_path']): ?><i class="bi bi-paperclip"></i> Có tài liệu<?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-1">
                        <?php foreach ([['up', 'bi-arrow-up', $i === 0], ['down', 'bi-arrow-down', $i === $last]] as [$dir, $icon, $disabled]): ?>
                            <form method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="move">
                                <input type="hidden" name="dir" value="<?= $dir ?>">
                                <input type="hidden" name="lesson_id" value="<?= (int) $l['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" <?= $disabled ? 'disabled' : '' ?>
                                        title="<?= $dir === 'up' ? 'Lên' : 'Xuống' ?>"><i class="bi <?= $icon ?>"></i></button>
                            </form>
                        <?php endforeach; ?>

                        <a href="<?= url('teacher/lesson_form.php?id=' . (int) $l['id']) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i> Sửa
                        </a>

                        <form method="post" class="d-inline" onsubmit="return confirm('Xóa bài học này?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="lesson_id" value="<?= (int) $l['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
