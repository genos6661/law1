<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

bh_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/');
    exit;
}

// If the request body was bigger than PHP's post_max_size, PHP silently
// empties $_POST and $_FILES instead of raising a normal error.
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if (empty($_POST) && empty($_FILES) && $contentLength > 0) {
    error_log('save.php: request body (' . $contentLength . ' bytes) exceeded post_max_size.');
    bh_set_flash('error', 'The upload was too large for the server to accept. Try a smaller photo, or ask your host to raise post_max_size / upload_max_filesize in php.ini.');
    header('Location: /admin/');
    exit;
}

if (!bh_verify_csrf($_POST['csrf_token'] ?? null)) {
    bh_set_flash('error', 'Your session expired. Please try again.');
    header('Location: /admin/');
    exit;
}

$schema = bh_section_schema();
$action = (string) ($_POST['action'] ?? '');
$section = (string) ($_POST['section'] ?? '');

if (!isset($schema[$section])) {
    bh_set_flash('error', 'Unknown section.');
    header('Location: /admin/');
    exit;
}

$sectionDef = $schema[$section];
$imgSection = $sectionDef['image'];
$content = bh_load_content();
$items = $content[$section];

/** Removes an item's main + (for blogs) extra photos from disk. */
function bh_delete_all_images_for(string $imgSection, string $section, int $id): void
{
    bh_delete_image($imgSection, $id);
    if ($section === 'blogs') {
        foreach ([2, 3, 4] as $slot) {
            bh_delete_image($imgSection, $id, $slot);
        }
    }
}

// ---------------------------------------------------------------- delete
if ($action === 'delete_item') {
    $id = (int) ($_POST['id'] ?? 0);
    $found = false;
    foreach ($items as $i => $it) {
        if ((int) $it['id'] === $id) {
            unset($items[$i]);
            $found = true;
            break;
        }
    }
    if (!$found) {
        bh_set_flash('error', 'Item not found — it may already have been deleted.');
        header('Location: /admin/');
        exit;
    }

    $content[$section] = array_values($items);
    $writeResult = bh_write_content($content);
    if ($writeResult !== true) {
        bh_set_flash('error', is_string($writeResult) ? $writeResult : 'Could not save.');
        header('Location: /admin/');
        exit;
    }

    // Only remove the files once content.json no longer references them.
    bh_delete_all_images_for($imgSection, $section, $id);

    bh_set_flash('success', 'Item deleted.');
    header('Location: /admin/');
    exit;
}

// ---------------------------------------------------------- add / edit
if ($action !== 'save_item') {
    bh_set_flash('error', 'Unknown action.');
    header('Location: /admin/');
    exit;
}

$postedId = (int) ($_POST['id'] ?? 0);
$existingIds = array_map(static fn($it) => (int) $it['id'], $items);
$isExisting = $postedId > 0 && in_array($postedId, $existingIds, true);

if (!$isExisting && count($items) >= MAX_ITEMS_PER_SECTION) {
    bh_set_flash('error', 'Too many items in this section (max ' . MAX_ITEMS_PER_SECTION . ').');
    header('Location: /admin/');
    exit;
}

$id = $isExisting ? $postedId : ($existingIds === [] ? 1 : max($existingIds) + 1);

$errors = [];
$pendingSaves = [];   // [image, imgSection, id, slot]
$pendingDeletes = []; // [imgSection, id, slot]

// Nothing above this point touched the filesystem. Validate the main photo
// and (for blogs) the extra photos first — actual writes only happen after
// every check below has passed.
$mainFile = $_FILES['main_image'] ?? null;
$mainHasUpload = $mainFile && ($mainFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$mainValidated = $mainHasUpload ? bh_validate_image_upload($mainFile) : null;
if (is_string($mainValidated)) {
    $errors[] = "Main photo: $mainValidated";
} elseif ($mainValidated !== null) {
    $pendingSaves[] = [$mainValidated, $imgSection, $id, 1];
}
if (!$isExisting && $mainValidated === null && !$errors) {
    $errors[] = 'A new item needs a main photo.';
}

if ($section === 'blogs') {
    foreach ([2, 3, 4] as $slot) {
        if (!empty($_POST['remove_extra_' . $slot])) {
            $pendingDeletes[] = [$imgSection, $id, $slot];
            continue;
        }
        $file = $_FILES['extra_image_' . $slot] ?? null;
        $hasUpload = $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$hasUpload) {
            continue;
        }
        $validated = bh_validate_image_upload($file);
        if (is_string($validated)) {
            $errors[] = "Extra photo $slot: $validated";
        } elseif ($validated !== null) {
            $pendingSaves[] = [$validated, $imgSection, $id, $slot];
        }
    }
}

if ($errors) {
    foreach ($pendingSaves as [$image]) {
        imagedestroy($image);
    }
    bh_set_flash('error', implode(' ', $errors));
    header('Location: /admin/');
    exit;
}

$row = ['id' => $id];
foreach ($sectionDef['fields'] as $field => $fieldDef) {
    $value = (string) ($_POST[$field] ?? '');
    if ($fieldDef['type'] === 'number') {
        $rating = (int) $value;
        $row[$field] = max(1, min(5, $rating ?: 5));
    } elseif ($fieldDef['type'] === 'date') {
        // <input type="date"> always sends YYYY-MM-DD (or empty if left blank).
        $row[$field] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : date('Y-m-d');
    } else {
        $row[$field] = bh_sanitize_text($value, $fieldDef['maxlength']);
    }
}

if ($isExisting) {
    foreach ($items as $i => $it) {
        if ((int) $it['id'] === $id) {
            $items[$i] = $row;
            break;
        }
    }
} else {
    $items[] = $row;
}
$content[$section] = array_values($items);

// Commit order matters: photos, then content.json, then deletes — so a
// failure partway through never leaves content.json referencing a photo
// that was never saved, or deletes a photo an item still needs.
foreach ($pendingSaves as [$image, $imgSec, $itemId, $slot]) {
    $saveResult = bh_save_validated_image($image, $imgSec, $itemId, $slot);
    if ($saveResult !== true) {
        bh_set_flash('error', $saveResult);
        header('Location: /admin/');
        exit;
    }
}

$writeResult = bh_write_content($content);
if ($writeResult !== true) {
    bh_set_flash('error', is_string($writeResult) ? $writeResult : 'Could not write content.json.');
    header('Location: /admin/');
    exit;
}

foreach ($pendingDeletes as [$imgSec, $itemId, $slot]) {
    bh_delete_image($imgSec, $itemId, $slot);
}

bh_set_flash('success', $isExisting ? 'Item updated.' : 'Item added.');
header('Location: /admin/');
exit;
