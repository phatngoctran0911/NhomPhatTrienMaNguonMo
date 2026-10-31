<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/teacher.php';
require_once __DIR__ . '/../includes/upload.php';
requireRole('teacher');

$teacherId = (int) currentUser()['id'];
$lessonId  = (int) ($_GET['id'] ?? 0);
$lesson    = null;

if ($lessonId > 0) {
    // Sửa: lấy bài học và kiểm tra khóa học chứa nó có phải của mình không
    $stmt = db()->prepare(
        'SELECT l.* FROM lessons l JOIN courses c ON c.id = l.course_id WHERE l.id = ? AND c.teacher_id = ?'
    );
    $stmt->execute([$lessonId, $teacherId]);
    $lesson = $stmt->fetch();
    if (!$lesson) {
        flash('danger', 'Không tìm thấy bài học hoặc bạn không có quyền truy cập.');
        redirect(url('teacher/index.php'));
    }
    $courseId = (int) $lesson['course_id'];
} else {
    $courseId = (int) ($_GET['course_id'] ?? 0);
}

$course  = requireOwnCourse($courseId);
$isEdit  = $lesson !== null;
$backUrl = url('teacher/lessons.php?course_id=' . $courseId);
$errors  = [];

$title    = $lesson['title'] ?? '';
$content  = $lesson['content'] ?? '';
$videoUrl = $lesson['video_url'] ?? '';

if (isPost()) {
    if (!csrfVerify()) {
        $errors[] = 'Phiên làm việc không hợp lệ (hoặc tệp quá lớn). Vui lòng thử lại.';
    } else {
        $title      = trim($_POST['title'] ?? '');
        $content    = trim($_POST['content'] ?? '');
        $videoUrl   = trim($_POST['video_url'] ?? '');
        $removeFile = isset($_POST['remove_file']);

        if ($title === '' || mb_strlen($title) > 200) {
            $errors[] = 'Tiêu đề không được để trống và tối đa 200 ký tự.';
        }
        if ($videoUrl !== '' && youtubeEmbedUrl($videoUrl) === null) {
            $errors[] = 'Liên kết video chưa đúng. Hiện chỉ hỗ trợ link YouTube (youtube.com hoặc youtu.be).';
        }

        if (!$errors) {
            $up = handleUpload($_FILES['attachment'] ?? null, 'lessons', ALLOWED_UPLOAD_EXT);
            if (!$up['ok']) {
                $errors[] = $up['error'];
            }
        }

        if (!$errors) {
            $videoValue = $videoUrl !== '' ? $videoUrl : null;

            if ($isEdit) {
                $file = $lesson['file_path'];
                if ($up['filename']) {
                    deleteUploadedFile('lessons', $file);
                    $file = $up['filename'];
                } elseif ($removeFile) {
                    deleteUploadedFile('lessons', $file);
                    $file = null;
                }
                $stmt = db()->prepare(
                    'UPDATE lessons SET title = ?, content = ?, video_url = ?, file_path = ? WHERE id = ? AND course_id = ?'
                );
                $stmt->execute([$title, $content, $videoValue, $file, $lessonId, $courseId]);
                flash('success', 'Đã cập nhật bài học.');
            } else {
                $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lessons WHERE course_id = ?');
                $stmt->execute([$courseId]);
                $nextOrder = (int) $stmt->fetchColumn();

                $stmt = db()->prepare(
                    'INSERT INTO lessons (course_id, title, content, video_url, file_path, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$courseId, $title, $content, $videoValue, $up['filename'], $nextOrder]);
                flash('success', 'Đã thêm bài học.');
            }
            redirect($backUrl);
        }
    }
}

$pageTitle = $isEdit ? 'Sửa bài học' : 'Thêm bài học';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('teacher/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item"><a href="<?= e($backUrl) ?>"><?= e($course['title']) ?></a></li>
        <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <h4 class="mb-4"><?= e($pageTitle) ?></h4>

                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>

                <form method="post" enctype="multipart/form-data" novalidate>
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label" for="title">Tiêu đề bài học <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" maxlength="200"
                               value="<?= e($title) ?>" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="content">Nội dung bài giảng</label>
                        <textarea class="form-control" id="content" name="content" rows="10"><?= e($content) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="video_url">Link video YouTube (không bắt buộc)</label>
                        <input type="url" class="form-control" id="video_url" name="video_url"
                               placeholder="https://www.youtube.com/watch?v=..." value="<?= e($videoUrl) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="attachment">
                            Tài liệu đính kèm (<?= e(implode(', ', ALLOWED_UPLOAD_EXT)) ?>; tối đa 5 MB)
                        </label>
                        <?php if ($isEdit && $lesson['file_path']): ?>
                            <div class="mb-2 small">
                                <i class="bi bi-paperclip"></i>
                                <a href="<?= e(uploadedFileUrl('lessons', $lesson['file_path'])) ?>" target="_blank">Tài liệu hiện tại</a>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="remove_file" name="remove_file">
                                <label class="form-check-label small" for="remove_file">Xóa tài liệu hiện tại</label>
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="attachment" name="attachment">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Lưu thay đổi' : 'Thêm bài học' ?></button>
                        <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary">Hủy</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
