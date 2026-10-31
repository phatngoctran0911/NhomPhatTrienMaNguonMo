<?php

/**
 * Admin: tạo tài khoản giảng viên.
 * (Đăng ký công khai ở register.php chỉ tạo được sinh viên, nên giảng viên phải do admin tạo.)
 */
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

/** Kiểm tra dữ liệu form, trả về mảng thông báo lỗi (mảng rỗng = hợp lệ). */
function validateTeacherForm(string $fullName, string $email, string $password, string $confirm): array
{
    $errors = [];

    if ($fullName === '' || mb_strlen($fullName) > 100) {
        $errors[] = 'Họ tên không được để trống và tối đa 100 ký tự.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors[] = 'Email không hợp lệ.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Mật khẩu nhập lại không khớp.';
    }
    return $errors;
}

$errors = [];

if (isPost()) {
    if (!csrfVerify()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    } else {
        // Chỉ nhận chuỗi (tránh lỗi khi ai đó gửi name[]=...). Mật khẩu không trim.
        $field    = fn(string $key): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
        $fullName = trim($field('full_name'));
        $email    = trim($field('email'));
        $password = $field('password');
        $confirm  = $field('confirm_password');

        $errors = validateTeacherForm($fullName, $email, $password, $confirm);

        if (!$errors) {
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email này đã được sử dụng.';
            }
        }

        if (!$errors) {
            try {
                db()->prepare('INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)')
                    ->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), 'teacher']);

                flash('success', 'Đã tạo tài khoản giảng viên: ' . $fullName);
                redirect(url('admin/users.php'));
                // exit;
            } catch (PDOException $ex) {
                // 23000 = vi phạm ràng buộc UNIQUE (2 admin tạo cùng email gần như cùng lúc)
                if ($ex->getCode() === '23000') {
                    $errors[] = 'Email này đã được sử dụng.';
                } else {
                    throw $ex;
                }
            }
        }
    }
}

$pageTitle = 'Tạo giảng viên';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('admin/users.php') ?>">Quản lý người dùng</a></li>
        <li class="breadcrumb-item active">Tạo giảng viên</li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card">
            <div class="card-body p-4">
                <h4 class="mb-4 text-center">Tạo tài khoản giảng viên</h4>

                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>

                <form method="post" novalidate>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label" for="full_name">Họ và tên</label>
                        <input type="text" class="form-control" id="full_name" name="full_name"
                            value="<?= old('full_name') ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                            value="<?= old('email') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mật khẩu ban đầu (tối thiểu 6 ký tự)</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Nhập lại mật khẩu</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">Tạo tài khoản</button>
                        <a href="<?= url('admin/users.php') ?>" class="btn btn-outline-secondary">Hủy</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>