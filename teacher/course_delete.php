<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/teacher.php';
require_once __DIR__ . '/../includes/upload.php';
requireRole('teacher');

if (!isPost() || !csrfVerify()) {
    flash('danger', 'Yêu cầu không hợp lệ.');
    redirect(url('teacher/index.php'));
}

$courseId = (int) ($_POST['course_id'] ?? 0);
$course   = requireOwnCourse($courseId);          // chỉ xóa được khóa của chính mình

// Gom danh sách file cần xóa trước khi xóa dòng trong CSDL
$stmt = db()->prepare('SELECT file_path FROM lessons WHERE course_id = ? AND file_path IS NOT NULL');
$stmt->execute([$courseId]);
$lessonFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ON DELETE CASCADE sẽ xóa kèm lessons, enrollments, assignments, submissions
$stmt = db()->prepare('DELETE FROM courses WHERE id = ? AND teacher_id = ?');
$stmt->execute([$courseId, (int) currentUser()['id']]);

deleteUploadedFile('thumbnails', $course['thumbnail']);
foreach ($lessonFiles as $f) {
    deleteUploadedFile('lessons', $f);
}

flash('success', 'Đã xóa khóa học "' . $course['title'] . '".');
redirect(url('teacher/index.php'));
