<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

bh_require_login();

$schema = bh_section_schema();
$content = bh_load_content();
$csrfToken = bh_csrf_token();
$flash = bh_get_flash();
$maxUploadMb = (int) (MAX_UPLOAD_BYTES / 1024 / 1024);

// Data handed to the JS below so the Edit modal can be filled in without a
// round trip to the server. For blogs, also tell it which extra-photo slots
// (2, 3, 4) currently have a file, since that isn't stored in content.json.
$jsData = [];
foreach ($schema as $sectionKey => $sectionDef) {
    $jsData[$sectionKey] = [];
    foreach (($content[$sectionKey] ?? []) as $item) {
        $id = (int) $item['id'];
        $entry = $item;
        $entry['_mainPhoto'] = bh_image_url($sectionDef['image'], $id, 1);
        if ($sectionKey === 'blogs') {
            $entry['_extraPhotos'] = [
                2 => bh_image_url($sectionDef['image'], $id, 2),
                3 => bh_image_url($sectionDef['image'], $id, 3),
                4 => bh_image_url($sectionDef['image'], $id, 4),
            ];
        }
        $jsData[$sectionKey][$id] = $entry;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin | The Brotherhood</title>
  <meta name="robots" content="noindex, nofollow">
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: "IBM Plex Sans", sans-serif;
      background: #F6F6F6;
      margin: 0;
      color: #090D14;
    }
    header.topbar {
      background: #fff;
      padding: 16px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
      position: sticky;
      top: 0;
      z-index: 10;
    }
    header.topbar h1 {
      font-family: "Crimson Text", serif;
      font-size: 20px;
      margin: 0;
    }
    header.topbar a {
      color: #A87C2D;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      margin-left: 16px;
    }
    main {
      max-width: 1100px;
      margin: 24px auto;
      padding: 0 20px 60px;
    }
    .flash {
      padding: 12px 16px;
      border-radius: 6px;
      margin-bottom: 20px;
      font-size: 14px;
    }
    .flash.success { background: #E7F5E9; color: #1E7B34; }
    .flash.error { background: #FCEBEA; color: #C0392B; }
    section.editor-section {
      background: #fff;
      border-radius: 8px;
      padding: 24px;
      margin-bottom: 24px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    section.editor-section > h2 {
      font-family: "Crimson Text", serif;
      font-size: 22px;
      margin: 0 0 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .add-btn {
      background: #E7EFF0;
      color: #A87C2D;
      border: none;
      padding: 8px 14px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
    }
    .add-btn:hover { background: #A87C2D; color: #fff; }

    .table-scroll { overflow-x: auto; }
    table.items-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }
    table.items-table th {
      text-align: left;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      color: #999;
      padding: 8px 10px;
      border-bottom: 2px solid #EEE;
      white-space: nowrap;
    }
    table.items-table td {
      padding: 8px 10px;
      border-bottom: 1px solid #F0F0F0;
      vertical-align: middle;
    }
    table.items-table tr:last-child td { border-bottom: none; }
    table.items-table .thumb-cell img {
      width: 56px;
      height: 42px;
      object-fit: cover;
      border-radius: 4px;
      background: #F3EDE6;
      display: block;
    }
    table.items-table .truncate {
      max-width: 260px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    table.items-table .actions-cell {
      white-space: nowrap;
      text-align: right;
    }
    .btn-link {
      border: none;
      background: none;
      color: #A87C2D;
      font-weight: 600;
      font-size: 13px;
      cursor: pointer;
      padding: 4px 8px;
    }
    .btn-link.danger { color: #C0392B; }
    .empty-row td {
      color: #999;
      font-style: italic;
      text-align: center;
      padding: 20px;
    }

    /* Modal */
    .bh-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(9, 13, 20, 0.55);
      display: none;
      align-items: flex-start;
      justify-content: center;
      overflow-y: auto;
      padding: 40px 16px;
      z-index: 100;
    }
    .bh-modal-overlay.open { display: flex; }
    .bh-modal {
      background: #fff;
      border-radius: 8px;
      width: 100%;
      max-width: 640px;
      padding: 24px;
    }
    .bh-modal h3 {
      font-family: "Crimson Text", serif;
      font-size: 20px;
      margin: 0 0 20px;
    }
    .bh-modal label {
      font-size: 12px;
      color: #666;
      display: block;
      margin: 14px 0 4px;
    }
    .bh-modal input[type="text"],
    .bh-modal input[type="number"],
    .bh-modal input[type="date"],
    .bh-modal textarea {
      width: 100%;
      padding: 8px 10px;
      border: 1px solid #CED4DA;
      border-radius: 5px;
      font-size: 14px;
      font-family: inherit;
    }
    .bh-modal textarea { resize: vertical; min-height: 60px; }
    .bh-modal .field-row { display: flex; gap: 12px; flex-wrap: wrap; }
    .bh-modal .field-row > div { flex: 1; min-width: 140px; }
    .bh-modal .current-photo {
      width: 100%;
      max-height: 160px;
      object-fit: cover;
      border-radius: 6px;
      margin-top: 8px;
      display: none;
      background: #F3EDE6;
    }
    .bh-modal .current-photo.visible { display: block; }
    .bh-modal .extra-photo-slot {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-top: 8px;
      padding: 8px;
      border: 1px solid #EEE;
      border-radius: 6px;
    }
    .bh-modal .extra-photo-slot img {
      width: 56px;
      height: 42px;
      object-fit: cover;
      border-radius: 4px;
      display: none;
    }
    .bh-modal .extra-photo-slot img.visible { display: block; }
    .bh-modal .extra-photo-slot .slot-fields { flex: 1; }
    .bh-modal .extra-photo-slot input[type="file"] { font-size: 12px; margin-bottom: 4px; }
    .bh-modal .extra-photo-slot label.remove-label {
      font-size: 12px;
      display: none;
      align-items: center;
      gap: 4px;
      margin: 0;
      color: #C0392B;
    }
    .bh-modal .extra-photo-slot label.remove-label.visible { display: flex; }
    .bh-modal .hint { font-size: 12px; color: #999; margin-top: 6px; }
    .bh-modal .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 22px;
    }
    .bh-modal .btn-cancel {
      background: none;
      border: 1px solid #CED4DA;
      color: #495057;
      padding: 10px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
    }
    .bh-modal .btn-save {
      background: #A87C2D;
      color: #fff;
      border: none;
      padding: 10px 24px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
    }
    .bh-modal .btn-save:hover { background: #8A682B; }
  </style>
</head>
<body>
  <header class="topbar">
    <h1>The Brotherhood — Admin</h1>
    <div>
      <a href="/" target="_blank">View site</a>
      <a href="/admin/login.php?action=logout">Log out</a>
    </div>
  </header>

  <main>
    <?php if ($flash): ?>
      <div class="flash <?= htmlspecialchars($flash['type'], ENT_QUOTES) ?>">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php foreach ($schema as $sectionKey => $sectionDef): ?>
      <section class="editor-section" data-section="<?= htmlspecialchars($sectionKey, ENT_QUOTES) ?>">
        <h2>
          <?= htmlspecialchars($sectionDef['label'], ENT_QUOTES) ?>
          <button type="button" class="add-btn" onclick="bhOpenModal('<?= $sectionKey ?>', null)">+ Add</button>
        </h2>
        <div class="table-scroll">
          <table class="items-table">
            <thead>
              <tr>
                <th>Photo</th>
                <?php foreach ($sectionDef['fields'] as $fieldKey => $fieldDef): ?>
                  <?php if ($fieldKey === 'content') continue; // full article body is edit-only, not worth a column ?>
                  <th><?= htmlspecialchars($fieldDef['label'], ENT_QUOTES) ?></th>
                <?php endforeach; ?>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($content[$sectionKey])): ?>
                <tr class="empty-row"><td colspan="10">No items yet — click "+ Add" to create one.</td></tr>
              <?php endif; ?>
              <?php foreach (($content[$sectionKey] ?? []) as $item): ?>
                <?php $id = (int) $item['id']; ?>
                <tr>
                  <td class="thumb-cell"><img src="<?= htmlspecialchars(bh_image_url($sectionDef['image'], $id) ?? '', ENT_QUOTES) ?>" alt=""></td>
                  <?php foreach ($sectionDef['fields'] as $fieldKey => $fieldDef): ?>
                    <?php if ($fieldKey === 'content') continue; ?>
                    <td class="truncate"><?= htmlspecialchars((string) ($item[$fieldKey] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <?php endforeach; ?>
                  <td class="actions-cell">
                    <button type="button" class="btn-link" onclick="bhOpenModal('<?= $sectionKey ?>', <?= $id ?>)">Edit</button>
                    <button type="button" class="btn-link danger" onclick="bhDeleteItem('<?= $sectionKey ?>', <?= $id ?>)">Delete</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endforeach; ?>
  </main>

  <!-- One shared modal per section, reused for both Add and Edit -->
  <?php foreach ($schema as $sectionKey => $sectionDef): ?>
    <div class="bh-modal-overlay" id="bh-modal-<?= $sectionKey ?>">
      <div class="bh-modal">
        <h3 id="bh-modal-<?= $sectionKey ?>-title">Add <?= htmlspecialchars(rtrim($sectionDef['label'], 's'), ENT_QUOTES) ?></h3>
        <form method="post" action="/admin/save.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
          <input type="hidden" name="action" value="save_item">
          <input type="hidden" name="section" value="<?= $sectionKey ?>">
          <input type="hidden" name="id" id="bh-modal-<?= $sectionKey ?>-id" value="">

          <?php foreach ($sectionDef['fields'] as $fieldKey => $fieldDef): ?>
            <label><?= htmlspecialchars($fieldDef['label'], ENT_QUOTES) ?></label>
            <?php if ($fieldDef['type'] === 'textarea'): ?>
              <textarea name="<?= $fieldKey ?>" id="bh-modal-<?= $sectionKey ?>-<?= $fieldKey ?>" maxlength="<?= $fieldDef['maxlength'] ?>" rows="<?= $fieldKey === 'content' ? 8 : 3 ?>"></textarea>
            <?php elseif ($fieldDef['type'] === 'number'): ?>
              <input type="number" min="1" max="5" name="<?= $fieldKey ?>" id="bh-modal-<?= $sectionKey ?>-<?= $fieldKey ?>" value="5">
            <?php elseif ($fieldDef['type'] === 'date'): ?>
              <input type="date" name="<?= $fieldKey ?>" id="bh-modal-<?= $sectionKey ?>-<?= $fieldKey ?>">
            <?php else: ?>
              <input type="text" maxlength="<?= $fieldDef['maxlength'] ?>" name="<?= $fieldKey ?>" id="bh-modal-<?= $sectionKey ?>-<?= $fieldKey ?>">
            <?php endif; ?>
          <?php endforeach; ?>

          <label>Main photo</label>
          <img class="current-photo" id="bh-modal-<?= $sectionKey ?>-current-photo" src="" alt="">
          <input type="file" name="main_image" accept="image/jpeg,image/png,image/gif,image/webp">
          <div class="hint" id="bh-modal-<?= $sectionKey ?>-photo-hint">An image is required. Max <?= $maxUploadMb ?> MB (JPG/PNG/GIF/WEBP).</div>

          <?php if ($sectionKey === 'blogs'): ?>
            <label>Extra photos (up to <?= MAX_BLOG_EXTRA_PHOTOS ?>, shown in the article)</label>
            <?php foreach ([2, 3, 4] as $slot): ?>
              <div class="extra-photo-slot">
                <img id="bh-modal-blogs-extra-<?= $slot ?>-img" src="" alt="">
                <div class="slot-fields">
                  <input type="file" name="extra_image_<?= $slot ?>" accept="image/jpeg,image/png,image/gif,image/webp">
                  <label class="remove-label" id="bh-modal-blogs-extra-<?= $slot ?>-remove-label">
                    <input type="checkbox" name="remove_extra_<?= $slot ?>" value="1"> Remove this photo
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="bhCloseModal('<?= $sectionKey ?>')">Cancel</button>
            <button type="submit" class="btn-save">Save</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Hidden reusable form for Delete (no modal needed for a single confirm) -->
  <form method="post" action="/admin/save.php" id="bh-delete-form" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
    <input type="hidden" name="action" value="delete_item">
    <input type="hidden" name="section" id="bh-delete-section" value="">
    <input type="hidden" name="id" id="bh-delete-id" value="">
  </form>

  <script>
    const BH_DATA = <?= json_encode($jsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const BH_LABEL_SINGULAR = <?= json_encode(array_combine(array_keys($schema), array_map(static fn($s) => rtrim($s['label'], 's'), $schema)), JSON_UNESCAPED_UNICODE) ?>;

    function bhCloseModal(section) {
      document.getElementById('bh-modal-' + section).classList.remove('open');
    }

    function bhOpenModal(section, id) {
      const overlay = document.getElementById('bh-modal-' + section);
      const form = overlay.querySelector('form');
      form.reset();

      const isEdit = id !== null;
      document.getElementById('bh-modal-' + section + '-title').textContent =
        (isEdit ? 'Edit ' : 'Add ') + BH_LABEL_SINGULAR[section];
      document.getElementById('bh-modal-' + section + '-id').value = isEdit ? id : '';

      const item = isEdit ? BH_DATA[section][id] : null;

      // Text/number/date fields
      overlay.querySelectorAll('[id^="bh-modal-' + section + '-"]').forEach(function (el) {
        const field = el.id.replace('bh-modal-' + section + '-', '');
        if (item && field in item && el.tagName !== 'IMG') {
          el.value = item[field];
        }
      });

      // Main photo preview
      const photoEl = document.getElementById('bh-modal-' + section + '-current-photo');
      const hintEl = document.getElementById('bh-modal-' + section + '-photo-hint');
      if (item && item._mainPhoto) {
        photoEl.src = item._mainPhoto;
        photoEl.classList.add('visible');
        hintEl.textContent = 'Leave empty to keep the current photo.';
      } else {
        photoEl.src = '';
        photoEl.classList.remove('visible');
        hintEl.textContent = 'An image is required.';
      }

      // Blog extra-photo slots
      if (section === 'blogs') {
        [2, 3, 4].forEach(function (slot) {
          const img = document.getElementById('bh-modal-blogs-extra-' + slot + '-img');
          const removeLabel = document.getElementById('bh-modal-blogs-extra-' + slot + '-remove-label');
          const url = item && item._extraPhotos ? item._extraPhotos[slot] : null;
          if (url) {
            img.src = url;
            img.classList.add('visible');
            removeLabel.classList.add('visible');
          } else {
            img.src = '';
            img.classList.remove('visible');
            removeLabel.classList.remove('visible');
          }
        });
      }

      overlay.classList.add('open');
    }

    function bhDeleteItem(section, id) {
      if (!confirm('Delete this item? This cannot be undone.')) return;
      document.getElementById('bh-delete-section').value = section;
      document.getElementById('bh-delete-id').value = id;
      document.getElementById('bh-delete-form').submit();
    }

    // Click outside the modal card (on the dark overlay) closes it.
    document.querySelectorAll('.bh-modal-overlay').forEach(function (overlay) {
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
          overlay.classList.remove('open');
        }
      });
    });
  </script>
</body>
</html>
