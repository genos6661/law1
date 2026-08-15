<?php
declare(strict_types=1);

/**
 * Admin credentials + session bootstrap.
 *
 * Never store a plain-text password here — only a bcrypt hash.
 * Generate a hash for your real password on the server with:
 *
 *   php -r "echo password_hash('your-real-password', PASSWORD_BCRYPT), PHP_EOL;"
 *
 * then paste the result (starts with $2y$) into ADMIN_PASSWORD_HASH below.
 * The placeholder hash below is for the placeholder password "changeme" —
 * replace both before this ever goes near a live server.
 */

define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$TWjJyPN97ISEoAq/A8sUXOdUvRlKJdkw80RG/Eg7OgXmPIiQ2hL/S');

define('SESSION_NAME', 'bh_admin_session');
define('SESSION_LIFETIME', 60 * 60 * 4); // 4 hours

function bh_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => $isHttps, // becomes true automatically once served over HTTPS
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function bh_is_logged_in(): bool
{
    bh_start_session();
    return !empty($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

/** Call this at the top of every admin page that requires a login. */
function bh_require_login(): void
{
    if (!bh_is_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/** Returns the CSRF token for this session, creating one if needed. */
function bh_csrf_token(): string
{
    bh_start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function bh_verify_csrf(?string $token): bool
{
    bh_start_session();
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/** One-time success/error message that survives exactly one redirect. */
function bh_set_flash(string $type, string $message): void
{
    bh_start_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function bh_get_flash(): ?array
{
    bh_start_session();
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Single source of truth for what each section's items look like, shared by
 * index.php (renders the form) and save.php (validates the submission).
 * 'image' maps the JSON section key to its image folder/filename prefix
 * (content-loader.js expects assets/img/{image}/{image}-{id}.jpg).
 */
function bh_section_schema(): array
{
    return [
        'gallery' => [
            'label' => 'Gallery',
            'image' => 'gallery',
            'fields' => [
                'title'   => ['label' => 'Title', 'type' => 'text', 'maxlength' => 200],
                'caption' => ['label' => 'Caption', 'type' => 'text', 'maxlength' => 300],
            ],
        ],
        'testimonials' => [
            'label' => 'Testimonials',
            'image' => 'testimonial',
            'fields' => [
                'name'   => ['label' => 'Name', 'type' => 'text', 'maxlength' => 100],
                'role'   => ['label' => 'Role / Company', 'type' => 'text', 'maxlength' => 150],
                'rating' => ['label' => 'Rating (1-5)', 'type' => 'number', 'maxlength' => 1],
                'text'   => ['label' => 'Testimonial text', 'type' => 'textarea', 'maxlength' => 3000],
            ],
        ],
        'blogs' => [
            'label' => 'Blog posts',
            'image' => 'blog',
            'fields' => [
                'date'    => ['label' => 'Date', 'type' => 'text', 'maxlength' => 50],
                'title'   => ['label' => 'Title', 'type' => 'text', 'maxlength' => 200],
                'excerpt' => ['label' => 'Excerpt', 'type' => 'textarea', 'maxlength' => 500],
            ],
        ],
    ];
}

define('CONTENT_JSON_PATH', __DIR__ . '/../assets/data/content.json');
define('CONTENT_IMG_DIR', __DIR__ . '/../assets/img');
define('MAX_ITEMS_PER_SECTION', 30);
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB

/** Reads content.json into an array, or a safe empty default if missing/corrupt. */
function bh_load_content(): array
{
    $default = ['gallery' => [], 'testimonials' => [], 'blogs' => []];
    if (!is_file(CONTENT_JSON_PATH)) {
        return $default;
    }
    $raw = file_get_contents(CONTENT_JSON_PATH);
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        return $default;
    }
    return array_merge($default, $data);
}

/** Writes content.json atomically (temp file + rename) so a save can never leave a half-written file. */
function bh_write_content(array $data): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $tmpPath = CONTENT_JSON_PATH . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($tmpPath, $json) === false) {
        return false;
    }
    return rename($tmpPath, CONTENT_JSON_PATH);
}

function bh_sanitize_text(string $value, int $maxLen): string
{
    $value = str_replace(["\0"], '', $value);
    $value = trim($value);
    if (function_exists('mb_substr')) {
        $value = mb_substr($value, 0, $maxLen);
    } else {
        $value = substr($value, 0, $maxLen);
    }
    return $value;
}

/**
 * Validates and saves an uploaded image as assets/img/{section}/{section}-{id}.jpg,
 * always re-encoding through GD so the output is guaranteed to be a real JPEG
 * (strips anything a malicious file might smuggle past the extension/mime check).
 * Returns true on success, or a human-readable error string on failure.
 */
function bh_process_image_upload(array $file, string $section, int $id)
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // no file provided — not an error, caller decides if that's OK
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Upload failed (error code ' . $file['error'] . ').';
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return 'Invalid upload.';
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return 'Image is larger than 5 MB.';
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return 'File is not a valid image.';
    }

    $allowed = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG  => 'imagecreatefrompng',
        IMAGETYPE_GIF  => 'imagecreatefromgif',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    ];
    $type = $info[2];
    if (!isset($allowed[$type])) {
        return 'Only JPG, PNG, GIF or WEBP images are allowed.';
    }

    $create = $allowed[$type];
    $srcImage = @$create($file['tmp_name']);
    if ($srcImage === false) {
        return 'Could not read the uploaded image.';
    }

    // Flatten transparency onto white (PNG/GIF/WEBP can have alpha; JPEG cannot).
    $width = imagesx($srcImage);
    $height = imagesy($srcImage);
    $flat = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($flat, 255, 255, 255);
    imagefill($flat, 0, 0, $white);
    imagecopy($flat, $srcImage, 0, 0, 0, 0, $width, $height);
    imagedestroy($srcImage);

    $sectionDir = CONTENT_IMG_DIR . '/' . $section;
    if (!is_dir($sectionDir)) {
        @mkdir($sectionDir, 0755, true);
    }
    $destPath = $sectionDir . '/' . $section . '-' . $id . '.jpg';
    $tmpDest = $destPath . '.tmp-' . bin2hex(random_bytes(4));

    $ok = imagejpeg($flat, $tmpDest, 88);
    imagedestroy($flat);

    if (!$ok || !rename($tmpDest, $destPath)) {
        @unlink($tmpDest);
        return 'Could not save the image on the server.';
    }

    return true;
}

function bh_delete_image(string $section, int $id): void
{
    $path = CONTENT_IMG_DIR . '/' . $section . '/' . $section . '-' . $id . '.jpg';
    if (is_file($path)) {
        @unlink($path);
    }
}
