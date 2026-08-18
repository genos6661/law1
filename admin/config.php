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
                'date'    => ['label' => 'Date', 'type' => 'date', 'maxlength' => 10],
                'title'   => ['label' => 'Title', 'type' => 'text', 'maxlength' => 200],
                'excerpt' => ['label' => 'Excerpt (shown on the card)', 'type' => 'textarea', 'maxlength' => 500],
                'content' => ['label' => 'Full article (shown when a reader clicks the card — separate paragraphs with a blank line)', 'type' => 'textarea', 'maxlength' => 20000],
            ],
        ],
    ];
}

define('CONTENT_JSON_PATH', __DIR__ . '/../assets/data/content.json');
define('CONTENT_IMG_DIR', __DIR__ . '/../assets/img');
define('MAX_ITEMS_PER_SECTION', 30);
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB
define('MAX_BLOG_EXTRA_PHOTOS', 3); // additional photos a blog post can have besides its main photo

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

/**
 * Writes content.json atomically (temp file + rename) so a save can never
 * leave a half-written file. Returns true on success, or a human-readable
 * error string on failure (distinguishing a bad-data problem from a
 * filesystem problem, since they need very different fixes).
 */
function bh_write_content(array $data)
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log('bh_write_content: json_encode failed: ' . json_last_error_msg());
        return 'Could not encode the content as JSON (' . json_last_error_msg() . '). This is usually one field with an invalid character — please check any fields you just edited.';
    }
    $tmpPath = CONTENT_JSON_PATH . '.tmp-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmpPath, $json) === false) {
        $err = error_get_last();
        error_log('bh_write_content: could not write ' . $tmpPath . ': ' . ($err['message'] ?? 'unknown error'));
        return 'Could not write content.json — check that assets/data/ is writable by the web server user (see the PHP-FPM error log for the exact reason).';
    }
    if (!@rename($tmpPath, CONTENT_JSON_PATH)) {
        $err = error_get_last();
        error_log('bh_write_content: rename failed ' . $tmpPath . ' -> ' . CONTENT_JSON_PATH . ': ' . ($err['message'] ?? 'unknown error'));
        @unlink($tmpPath);
        return 'Could not finalize content.json on the server (see the PHP-FPM error log for the exact reason).';
    }
    return true;
}

function bh_sanitize_text(string $value, int $maxLen): string
{
    $value = str_replace(["\0"], '', $value);
    // A field with an invalid UTF-8 byte sequence (a mis-pasted character,
    // a browser/OS encoding quirk, etc.) makes json_encode() fail for the
    // WHOLE content.json write later, silently blocking every save/delete
    // until the offending field is found and fixed by hand. Clean it here
    // instead, once, so a single bad character can never take the whole
    // save down.
    if (!mb_check_encoding($value, 'UTF-8')) {
        $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        $value = $converted !== false ? $converted : preg_replace('/[^\x00-\x7F]/', '', $value);
    }
    $value = trim($value);
    if (function_exists('mb_substr')) {
        $value = mb_substr($value, 0, $maxLen);
    } else {
        $value = substr($value, 0, $maxLen);
    }
    return $value;
}

/**
 * Reads and validates an uploaded file WITHOUT touching the filesystem
 * destination — call this during validation, before anything is committed.
 * Returns null if no file was uploaded (not an error), a human-readable
 * error string if invalid, or a GD image resource/GdImage on success (the
 * caller owns it and must eventually pass it to bh_save_validated_image()
 * or call imagedestroy() on it).
 */
function bh_validate_image_upload(array $file)
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // no file provided — not an error, caller decides if that's OK
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return 'Image is too large. Max ' . (int) (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB per photo.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log('bh_validate_image_upload: PHP upload error code ' . $file['error']);
        return 'Upload failed (error code ' . $file['error'] . '). Check the PHP-FPM error log for details.';
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return 'Invalid upload.';
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return 'Image is larger than ' . (int) (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB.';
    }
    if (!extension_loaded('gd')) {
        error_log('bh_validate_image_upload: the PHP GD extension is not loaded.');
        return 'The server is missing the PHP GD extension needed to process images. Install it (e.g. "sudo apt install php-gd" then restart PHP-FPM) and try again.';
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
    if (!function_exists($create)) {
        return 'The server\'s PHP GD build does not support this image format.';
    }

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

    return $flat;
}

/**
 * Path convention for an item's image. $slot is 1 for the main photo, or
 * 2..(1+MAX_BLOG_EXTRA_PHOTOS) for a blog's extra photos — main is
 * assets/img/{section}/{section}-{id}.jpg, extras are ...-{id}-{slot}.jpg.
 */
function bh_image_filename(string $section, int $id, int $slot = 1): string
{
    return $slot <= 1 ? "{$section}-{$id}.jpg" : "{$section}-{$id}-{$slot}.jpg";
}

function bh_image_disk_path(string $section, int $id, int $slot = 1): string
{
    return CONTENT_IMG_DIR . '/' . $section . '/' . bh_image_filename($section, $id, $slot);
}

/**
 * Saves an already-validated GD image (from bh_validate_image_upload) to
 * its slot on disk. Only call this once every item in the whole submission
 * has validated successfully, so a failure on item #5 can never leave item
 * #1's photo half-applied.
 */
function bh_save_validated_image($image, string $section, int $id, int $slot = 1)
{
    $sectionDir = CONTENT_IMG_DIR . '/' . $section;
    if (!is_dir($sectionDir) && !@mkdir($sectionDir, 0755, true) && !is_dir($sectionDir)) {
        $err = error_get_last();
        error_log('bh_save_validated_image: mkdir failed for ' . $sectionDir . ': ' . ($err['message'] ?? 'unknown error'));
        imagedestroy($image);
        return 'Could not create ' . $section . ' image folder on the server — check that the web server user can write to assets/img/.';
    }
    if (!is_writable($sectionDir)) {
        error_log('bh_save_validated_image: ' . $sectionDir . ' is not writable by the PHP process (owner/permissions issue).');
        imagedestroy($image);
        return 'The server cannot write to assets/img/' . $section . '/ — check its file permissions/ownership (must be writable by the web server user, e.g. www-data).';
    }

    $destPath = $sectionDir . '/' . bh_image_filename($section, $id, $slot);
    $tmpDest = $destPath . '.tmp-' . bin2hex(random_bytes(4));

    $ok = @imagejpeg($image, $tmpDest, 88);
    imagedestroy($image);

    if (!$ok) {
        $err = error_get_last();
        error_log('bh_save_validated_image: imagejpeg failed writing ' . $tmpDest . ': ' . ($err['message'] ?? 'unknown error'));
        return 'Could not write the image file on the server (see PHP-FPM error log).';
    }
    if (!@rename($tmpDest, $destPath)) {
        $err = error_get_last();
        error_log('bh_save_validated_image: rename failed ' . $tmpDest . ' -> ' . $destPath . ': ' . ($err['message'] ?? 'unknown error'));
        @unlink($tmpDest);
        return 'Could not finalize the saved image on the server (see PHP-FPM error log).';
    }

    return true;
}

function bh_delete_image(string $section, int $id, int $slot = 1): void
{
    $path = bh_image_disk_path($section, $id, $slot);
    if (is_file($path)) {
        @unlink($path);
    }
}

/** URL (with a cache-busting ?v=) for an item's image, or null if that slot has no file. */
function bh_image_url(string $section, int $id, int $slot = 1): ?string
{
    $path = bh_image_disk_path($section, $id, $slot);
    if (!is_file($path)) {
        return null;
    }
    return '/assets/img/' . $section . '/' . bh_image_filename($section, $id, $slot) . '?v=' . filemtime($path);
}
