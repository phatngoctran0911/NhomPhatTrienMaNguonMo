<?php

/**
 * Admin: quản lý danh mục khóa học (thêm / đổi tên / xóa), phân trang 20 dòng.
 * Xóa danh mục KHÔNG xóa khóa học: cột courses.category_id tự chuyển về NULL (ON DELETE SET NULL).
 */
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$perPage   = 20;
$pageParam = max(1, (int) ($_GET['page'] ?? 1));
$listUrl   = url('admin/categories.php') . ($pageParam > 1 ? '?page=' . $pageParam : '');

/** Trả về thông báo lỗi nếu tên danh mục không hợp lệ, null nếu hợp lệ. */
function categoryNameError(string $name): ?string
{
    if ($name === '') {
        return 'Tên danh mục không được để trống.';
    }
    if (mb_strlen($name) > 100) {
        return 'Tên danh mục tối đa 100 ký tự.';
    }
    return null;
}

// ================= XỬ LÝ POST =================
if (isPost()) {
    if (!csrfVerify()) {
        flash('danger', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
        redirect($listUrl);
    }

    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['category_id'] ?? 0);
    $name   = $_POST['name'] ?? '';
    $name   = is_string($name) ? trim($name) : '';

    try {
        if ($action === 'add') {
            $error = categoryNameError($name);
            if ($error) {
                flash('danger', $error);
            } else {
                db()->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
                flash('success', 'Đã thêm danh mục: ' . $name);
            }
        } elseif ($action === 'rename') {
            $error = categoryNameError($name);
            if ($error) {
                flash('danger', $error);
            } else {
                $stmt = db()->prepare('UPDATE categories SET name = ? WHERE id = ?');
                $stmt->execute([$name, $id]);
                // MySQL trả 0 dòng nếu tên không đổi, nên không dùng rowCount() để báo "không tìm thấy"
                flash(
                    $stmt->rowCount() > 0 ? 'success' : 'info',
                    $stmt->rowCount() > 0 ? 'Đã đổi tên danh mục.' : 'Không có thay đổi nào.'
                );
            }
        } elseif ($action === 'delete') {
            // Đếm trước để báo cho admin biết bao nhiêu khóa học bị ảnh hưởng
            $stmt = db()->prepare('SELECT COUNT(*) FROM courses WHERE category_id = ?');
            $stmt->execute([$id]);
            $courseCount = (int) $stmt->fetchColumn();

            $stmt = db()->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                flash('success', sprintf('Đã xóa danh mục. %d khóa học chuyển về "Chưa phân loại".', $courseCount));
            } else {
                flash('danger', 'Không tìm thấy danh mục.');
            }
        } else {
            flash('danger', 'Thao tác không hợp lệ.');
        }
    } catch (PDOException $ex) {
        // 23000 = vi phạm UNIQUE: tên danh mục đã tồn tại
        if ($ex->getCode() === '23000') {
            flash('danger', 'Tên danh mục này đã tồn tại.');
        } else {
            throw $ex;
        }
    }

    redirect($listUrl);
}

// ================= TRUY VẤN DANH SÁCH =================
$total = (int) db()->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$pg    = paginate($total, $perPage);

$sql = "SELECT cat.id, cat.name,
               (SELECT COUNT(*) FROM courses c WHERE c.category_id = cat.id) AS course_count
        FROM categories cat
        ORDER BY cat.name
        LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
$categories = db()->query($sql)->fetchAll();

$uncategorized = (int) db()->query('SELECT COUNT(*) FROM courses WHERE category_id IS NULL')->fetchColumn();

$pageTitle = 'Quản lý danh mục';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-3">Quản lý danh mục khóa học</h3>

<form method="post" action="<?= e($listUrl) ?>" class="row g-2 mb-4">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="add">
    <div class="col-md-6 col-lg-4">
        <input type="text" name="name" class="form-control" placeholder="Tên danh mục mới..." maxlength="100" required>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Thêm danh mục</button>
    </div>
</form>

<?php if ($uncategorized > 0): ?>
    <p class="text-muted small"><i class="bi bi-info-circle"></i> Có <strong><?= $uncategorized ?></strong> khóa học chưa phân loại.</p>
<?php endif; ?>

<?php if (!$categories): ?>
    <div class="alert alert-info">Chưa có danh mục nào.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 70px">ID</th>
                    <th>Tên danh mục</th>
                    <th style="width: 120px">Số khóa học</th>
                    <th style="width: 100px">Xóa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?= (int) $cat['id'] ?></td>
                        <td>
                            <!-- Đổi tên -->
                            <form method="post" action="<?= e($listUrl) ?>" class="d-flex gap-2">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="rename">
                                <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
                                <input type="text" name="name" class="form-control form-control-sm"
                                    value="<?= e($cat['name']) ?>" maxlength="100" required>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Lưu</button>
                            </form>
                        </td>
                        <td><?= (int) $cat['course_count'] ?></td>
                        <td>
                            <!-- Xóa (khóa học của danh mục này vẫn còn, chỉ thành "chưa phân loại") -->
                            <form method="post" action="<?= e($listUrl) ?>"
                                onsubmit="return confirm('Xóa danh mục này? Các khóa học thuộc danh mục sẽ chuyển về Chưa phân loại (không bị xóa).')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= renderPagination($pg) ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>