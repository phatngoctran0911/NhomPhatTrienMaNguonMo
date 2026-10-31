<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/courses.php';
require_once __DIR__ . '/../includes/teacher.php';
require_once __DIR__ . '/../includes/upload.php';
requireRole('teacher');

$teacherId = (int) currentUser()['id'];
$courseId  = (int) ($_GET['id'] ?? 0);
$course    = $courseId > 0 ? requireOwnCourse($courseId) : null;   // null = đang tạo mới
$isEdit    = $course !== null;
$categories = getCategories();
$errors    = [];

// Giá trị ban đầu của form
$title       = $course['title'] ?? '';
$description = $course['description'] ?? '';
$categoryId  = (int) ($course['category_id'] ?? 0);
$status      = $course['status'] ?? 'draft';

if (isPost()) {
    if (!csrfVerify()) {
        $errors[] = 'Phiên làm việc không hợp lệ (hoặc tệp quá lớn). Vui lòng thử lại.';
    } else {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId  = (int) ($_POST['category_id'] ?? 0);
        $status      = $_POST['status'] ?? 'draft';
        $removeThumb = isset($_POST['remove_thumbnail']);

        if ($title === '' || mb_strlen($title) > 200) {
            $errors[] = 'Tên khóa học không được để trống và tối đa 200 ký tự.';
        }
        if (!in_array($status, ['draft', 'published'], true)) {
            $errors[] = 'Trạng thái không hợp lệ.';
        }
        if ($categoryId > 0 && !in_array($categoryId, array_map('intval', array_column($categories, 'id')), true)) {
            $errors[] = 'Danh mục không hợp lệ.';
        }

        if (!$errors) {
            $up = handleUpload($_FILES['thumbnail'] ?? null, 'thumbnails', ['jpg', 'jpeg', 'png']);
            if (!$up['ok']) {
                $errors[] = $up['error'];
            }
        }

        if (!$errors) {
            $catValue = $categoryId > 0 ? $categoryId : null;

            if ($isEdit) {
                $thumb = $course['thumbnail'];
                if ($up['filename']) {                       // có ảnh mới -> thay ảnh cũ
                    deleteUploadedFile('thumbnails', $thumb);
                    $thumb = $up['filename'];
                } elseif ($removeThumb) {                    // tick "xóa ảnh bìa"
                    deleteUploadedFile('thumbnails', $thumb);
                    $thumb = null;
                }
                $stmt = db()->prepare(
                    'UPDATE courses SET title = ?, description = ?, category_id = ?, status = ?, thumbnail = ?
                     WHERE id = ? AND teacher_id = ?'
                );
                $stmt->execute([$title, $description, $catValue, $status, $thumb, $courseId, $teacherId]);
                flash('success', 'Đã cập nhật khóa học.');
                redirect(url('teacher/index.php'));
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO courses (teacher_id, category_id, title, description, status, thumbnail)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$teacherId, $catValue, $title, $description, $status, $up['filename']]);
                $newId = (int) db()->lastInsertId();
                flash('success', 'Đã tạo khóa học. Hãy thêm bài học đầu tiên.');
                redirect(url('teacher/lessons.php?course_id=' . $newId));
            }
        }
    }
}

$pageTitle = $isEdit ? 'Sửa khóa học' : 'Tạo khóa học';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('teacher/index.php') ?>">Khóa học của tôi</a></li>
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
                        <label class="form-label" for="title">Tên khóa học <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" maxlength="200"
                               value="<?= e($title) ?>" required autofocus>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="category_id">Danh mục</label>
                            <select class="form-select" id="category_id" name="category_id">
                                <option value="0">-- Chưa phân loại --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="status">Trạng thái</label>
                            <select class="form-select" id="status" name="status">
                                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Nháp (chỉ mình tôi thấy)</option>
                                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Công khai (hiện ở trang chủ)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Mô tả</label>
                        <textarea class="form-control" id="description" name="description" rows="6"><?= e($description) ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="thumbnail">Ảnh bìa (JPG hoặc PNG, tối đa 5 MB)</label>
                        <?php if ($isEdit && $course['thumbnail']): ?>
                            <div class="mb-2" style="max-width: 260px">
                                <div class="rounded overflow-hidden border"><?= courseThumbHtml($course, 120) ?></div>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="remove_thumbnail" name="remove_thumbnail">
                                <label class="form-check-label small" for="remove_thumbnail">Xóa ảnh bìa hiện tại</label>
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="thumbnail" name="thumbnail" accept="image/png,image/jpeg">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Lưu thay đổi' : 'Tạo khóa học' ?></button>
                        <a href="<?= url('teacher/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
