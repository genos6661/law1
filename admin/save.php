<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

bh_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/');
    exit;
}

if (!bh_verify_csrf($_POST['csrf_token'] ?? null)) {
    bh_set_flash('error', 'Your session expired. Please try again.');
    header('Location: /admin/');
    exit;
}

$schema = bh_section_schema();
$existing = bh_load_content();
$errors = [];
$result = ['gallery' => [], 'testimonials' => [], 'blogs' => []];

foreach ($schema as $section => $sectionDef) {
    $fields = $sectionDef['fields'];
    $posted = $_POST[$section] ?? [];
    $files  = $_FILES[$section . '_image'] ?? [];
    $imgSection = $sectionDef['image'];

    if (!is_array($posted)) {
        continue;
    }
    if (count($posted) > MAX_ITEMS_PER_SECTION) {
        $errors[] = ucfirst($section) . ': too many items (max ' . MAX_ITEMS_PER_SECTION . ').';
        continue;
    }

    $existingIds = array_map(static fn($item) => (int) $item['id'], $existing[$section] ?? []);
    $nextId = $existingIds === [] ? 1 : (max($existingIds) + 1);
    $usedIds = [];

    foreach ($posted as $key => $item) {
        if (!is_array($item)) {
            continue;
        }

        $isDelete = !empty($item['delete']);
        $postedId = isset($item['id']) ? (int) $item['id'] : 0;
        $isExisting = $postedId > 0 && in_array($postedId, $existingIds, true);

        // Build the file entry for this row (PHP nests $_FILES the same way as $_POST).
        $file = null;
        if (isset($files['error'][$key])) {
            $file = [
                'name'     => $files['name'][$key],
                'type'     => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error'    => $files['error'][$key],
                'size'     => $files['size'][$key],
            ];
        }

        if ($isDelete) {
            if ($isExisting) {
                bh_delete_image($imgSection, $postedId);
            }
            continue; // dropped from $result entirely
        }

        // Assign an id: keep the existing one, or mint a fresh one for new rows.
        if ($isExisting) {
            $id = $postedId;
        } else {
            $id = $nextId++;
        }
        if (in_array($id, $usedIds, true)) {
            $errors[] = ucfirst($section) . ": duplicate item id $id.";
            continue;
        }
        $usedIds[] = $id;

        $uploadResult = $file !== null ? bh_process_image_upload($file, $imgSection, $id) : null;
        if (is_string($uploadResult)) {
            $errors[] = ucfirst($section) . " item #$id: $uploadResult";
            continue;
        }
        $hasImage = $isExisting || $uploadResult === true;
        if (!$hasImage) {
            $errors[] = ucfirst($section) . ': a new item needs an image.';
            continue;
        }

        $row = ['id' => $id];
        foreach ($fields as $field => $fieldDef) {
            $value = (string) ($item[$field] ?? '');
            if ($fieldDef['type'] === 'number') {
                $rating = (int) $value;
                $row[$field] = max(1, min(5, $rating ?: 5));
            } else {
                $row[$field] = bh_sanitize_text($value, $fieldDef['maxlength']);
            }
        }
        $result[$section][] = $row;
    }

    usort($result[$section], static fn($a, $b) => $a['id'] <=> $b['id']);
}

if ($errors) {
    bh_set_flash('error', implode(' ', $errors));
    header('Location: /admin/');
    exit;
}

if (!bh_write_content($result)) {
    bh_set_flash('error', 'Could not write content.json — check the assets/data folder is writable by the web server.');
    header('Location: /admin/');
    exit;
}

bh_set_flash('success', 'Changes saved.');
header('Location: /admin/');
exit;
