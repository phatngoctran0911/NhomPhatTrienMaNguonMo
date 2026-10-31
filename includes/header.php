<?php

/**
 * Header chung. Trước khi include, trang có thể đặt: $pageTitle = 'Tên trang';
 * LƯU Ý: mọi lệnh redirect() phải nằm TRƯỚC dòng include file này.
 */
require_once __DIR__ . '/auth.php';
$pageTitle = $pageTitle ?? 'Trang chủ';
$user = currentUser();
$roleLabels = ['admin' => 'Quản trị', 'teacher' => 'Giảng viên', 'student' => 'Khóa học của tôi'];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>

<body class="d-flex flex-column min-vh-100">

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= url('index.php') ?>">
                <i class="bi bi-mortarboard-fill"></i> <?= e(SITE_NAME) ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="<?= url('index.php') ?>">Khóa học</a></li>
                    <?php if ($user): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= roleHome($user['role']) ?>"><?= e($roleLabels[$user['role']] ?? '') ?></a>
                        </li>
                        <?php if ($user['role'] === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= url('admin/users.php') ?>">Người dùng</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= url('admin/courses.php') ?>">QL khóa học</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= url('admin/categories.php') ?>">Danh mục</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>

                <ul class="navbar-nav">
                    <?php if ($user): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?= e($user['full_name']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><span class="dropdown-item-text text-muted small"><?= e($user['email']) ?> (<?= e($user['role']) ?>)</span></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="<?= url('logout.php') ?>">Đăng xuất</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?= url('login.php') ?>">Đăng nhập</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= url('register.php') ?>">Đăng ký</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-4 flex-grow-1">
        <?php foreach (getFlashes() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($f['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>