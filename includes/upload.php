<?php
/**
 * Upload file an toàn + tiện ích video.
 * File được lưu trong uploads/<thư mục con>/ với tên ngẫu nhiên.
 * CSDL chỉ lưu TÊN FILE (không lưu đường dẫn).
 */

function uploadErrorMessage(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Tệp vượt quá dung lượng cho phép.';
        case UPLOAD_ERR_PARTIAL:
            return 'Tệp chỉ được tải lên một phần, vui lòng thử lại.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            return 'Máy chủ không ghi được tệp, vui lòng báo quản trị viên.';
        default:
            return 'Tải tệp lên thất bại.';
    }
}

/** Danh sách MIME hợp lệ cho từng đuôi file (kiểm tra nội dung thật, không tin tên file). */
function uploadMimeMap(): array
{
    $office = ['application/octet-stream', 'application/zip'];
    $legacy = ['application/octet-stream', 'application/msword', 'application/vnd.ms-powerpoint',
               'application/vnd.ms-office', 'application/CDFV2', 'application/x-cfb'];
    return [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'zip'  => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        'docx' => array_merge(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], $office),
        'pptx' => array_merge(['application/vnd.openxmlformats-officedocument.presentationml.presentation'], $office),
        'doc'  => $legacy,
        'ppt'  => $legacy,
    ];
}

/**
 * Xử lý 1 file upload.
 * @return array ['ok' => bool, 'filename' => ?string, 'error' => ?string]
 *   - Không chọn file  -> ok = true, filename = null
 *   - Thành công       -> ok = true, filename = tên file đã lưu
 *   - Lỗi              -> ok = false, error = thông báo
 */
function handleUpload(?array $file, string $subdir, array $allowedExt, int $maxSize = MAX_UPLOAD_SIZE): array
{
    $fail = fn(string $msg) => ['ok' => false, 'filename' => null, 'error' => $msg];

    if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'filename' => null, 'error' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return $fail(uploadErrorMessage((int) $file['error']));
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return $fail('Tệp tải lên không hợp lệ.');
    }
    if ($file['size'] > $maxSize) {
        return $fail('Tệp vượt quá ' . round($maxSize / 1048576) . ' MB.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return $fail('Định dạng không được phép. Chỉ nhận: ' . implode(', ', $allowedExt) . '.');
    }

    // Kiểm tra nội dung thật của file
    if (class_exists('finfo')) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, uploadMimeMap()[$ext] ?? [], true)) {
            return $fail('Nội dung tệp không khớp với định dạng .' . $ext . '.');
        }
    }
    if (in_array($ext, ['jpg', 'jpeg', 'png'], true) && @getimagesize($file['tmp_name']) === false) {
        return $fail('Ảnh không hợp lệ.');
    }

    $dir = UPLOAD_PATH . $subdir . '/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return $fail('Không tạo được thư mục lưu tệp.');
    }

    $name = bin2hex(random_bytes(16)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return $fail('Không lưu được tệp, vui lòng thử lại.');
    }
    return ['ok' => true, 'filename' => $name, 'error' => null];
}

/** Xóa file đã upload (an toàn: chỉ lấy tên file, không cho đi ra ngoài thư mục). */
function deleteUploadedFile(string $subdir, ?string $filename): void
{
    if (!$filename) {
        return;
    }
    $path = UPLOAD_PATH . $subdir . '/' . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}

function uploadedFileUrl(string $subdir, ?string $filename): string
{
    return UPLOAD_URL . $subdir . '/' . rawurlencode(basename((string) $filename));
}

/** Đổi link YouTube bất kỳ thành link nhúng (embed). Trả về null nếu không hợp lệ. */
function youtubeEmbedUrl(?string $url): ?string
{
    if (!$url) {
        return null;
    }
    $pattern = '~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~';
    if (preg_match($pattern, trim($url), $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    return null;
}
