<?php
/**
 * Hàm dùng chung cho khu vực giảng viên.
 * Cần require auth.php trước.
 */

function getCategories(): array
{
    return db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
}

/**
 * Lấy khóa học thuộc về giảng viên đang đăng nhập.
 * Nếu không tồn tại HOẶC không phải của mình thì báo lỗi và chuyển về dashboard.
 * Đây là chốt chặn chống sửa khóa học của người khác bằng cách đổi ID trên URL.
 */
function requireOwnCourse(int $courseId): array
{
    $stmt = db()->prepare('SELECT * FROM courses WHERE id = ? AND teacher_id = ?');
    $stmt->execute([$courseId, (int) currentUser()['id']]);
    $course = $stmt->fetch();

    if (!$course) {
        flash('danger', 'Không tìm thấy khóa học hoặc bạn không có quyền truy cập.');
        redirect(url('teacher/index.php'));
    }
    return $course;
}

/** Ghi lại thứ tự bài học theo mảng id đã sắp xếp (1, 2, 3...). */
function saveLessonOrder(array $orderedIds): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE lessons SET sort_order = ? WHERE id = ?');
        foreach (array_values($orderedIds) as $i => $id) {
            $stmt->execute([$i + 1, (int) $id]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

/** Đánh số lại 1..n cho bài học của một khóa (sau khi xóa). */
function renumberLessons(int $courseId): void
{
    $stmt = db()->prepare('SELECT id FROM lessons WHERE course_id = ? ORDER BY sort_order, id');
    $stmt->execute([$courseId]);
    saveLessonOrder($stmt->fetchAll(PDO::FETCH_COLUMN));
}
