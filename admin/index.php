<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

bh_require_login();

$schema = bh_section_schema();
$content = bh_load_content();
$csrfToken = bh_csrf_token();
$flash = bh_get_flash();
$maxUploadMb = (int) (MAX_UPLOAD_BYTES / 1024 / 1024);

function bh_image_url(string $imgSection, int $id): string
{
    $path = CONTENT_IMG_DIR . '/' . $imgSection . '/' . $imgSection . '-' . $id . '.jpg';
    $v = is_file($path) ? filemtime($path) : 0;
    return '/assets/img/' . $imgSection . '/' . $imgSection . '-' . $id . '.jpg?v=' . $v;
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
      max-width: 960px;
      margin: 24px auto;
      padding: 0 20px 80px;
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
    .item-row {
      display: grid;
      grid-template-columns: 96px 1fr;
      gap: 16px;
      padding: 16px 0;
      border-top: 1px solid #EEE;
    }
    .item-row.marked-delete { opacity: 0.4; }
    .item-row .thumb {
      width: 96px;
      height: 72px;
      object-fit: cover;
      border-radius: 4px;
      background: #F3EDE6;
    }
    .item-row .fields { display: flex; flex-direction: column; gap: 8px; }
    .item-row label {
      font-size: 12px;
      color: #666;
      display: block;
      margin-bottom: 2px;
    }
    .item-row input[type="text"],
    .item-row input[type="number"],
    .item-row textarea {
      width: 100%;
      padding: 7px 10px;
      border: 1px solid #CED4DA;
      border-radius: 5px;
      font-size: 14px;
      font-family: inherit;
    }
    .item-row textarea { resize: vertical; min-height: 50px; }
    .field-row { display: flex; gap: 12px; flex-wrap: wrap; }
    .field-row > div { flex: 1; min-width: 140px; }
    .item-row .row-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 12px;
      margin-top: 4px;
    }
    .item-row .row-actions input[type="file"] { font-size: 12px; }
    .delete-toggle { color: #C0392B; cursor: pointer; user-select: none; }
    .save-bar {
      position: sticky;
      bottom: 0;
      background: #fff;
      padding: 16px 24px;
      box-shadow: 0 -2px 10px rgba(0,0,0,0.08);
      display: flex;
      justify-content: flex-end;
      border-radius: 8px;
    }
    .save-bar button {
      background: #A87C2D;
      color: #fff;
      border: none;
      padding: 12px 28px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 15px;
      cursor: pointer;
    }
    .save-bar button:hover { background: #8A682B; }
    .hint { font-size: 12px; color: #999; margin-top: 4px; }
    template { display: none; }
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

    <form method="post" action="/admin/save.php" enctype="multipart/form-data" id="content-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

      <?php foreach ($schema as $sectionKey => $sectionDef): ?>
        <section class="editor-section" data-section="<?= htmlspecialchars($sectionKey, ENT_QUOTES) ?>">
          <h2>
            <?= htmlspecialchars($sectionDef['label'], ENT_QUOTES) ?>
            <button type="button" class="add-btn" data-add="<?= htmlspecialchars($sectionKey, ENT_QUOTES) ?>">+ Add item</button>
          </h2>
          <div class="items">
            <?php foreach (($content[$sectionKey] ?? []) as $item): ?>
              <div class="item-row" data-key="<?= (int) $item['id'] ?>">
                <img class="thumb" src="<?= htmlspecialchars(bh_image_url($sectionDef['image'], (int) $item['id']), ENT_QUOTES) ?>" alt="">
                <div class="fields">
                  <input type="hidden" name="<?= $sectionKey ?>[<?= (int) $item['id'] ?>][id]" value="<?= (int) $item['id'] ?>">
                  <div class="field-row">
                    <?php foreach ($sectionDef['fields'] as $fieldKey => $fieldDef): ?>
                      <?php if ($fieldDef['type'] === 'textarea') continue; ?>
                      <div>
                        <label><?= htmlspecialchars($fieldDef['label'], ENT_QUOTES) ?></label>
                        <?php if ($fieldDef['type'] === 'number'): ?>
                          <input type="number" min="1" max="5" name="<?= $sectionKey ?>[<?= (int) $item['id'] ?>][<?= $fieldKey ?>]" value="<?= htmlspecialchars((string) ($item[$fieldKey] ?? ''), ENT_QUOTES) ?>">
                        <?php else: ?>
                          <input type="text" maxlength="<?= $fieldDef['maxlength'] ?>" name="<?= $sectionKey ?>[<?= (int) $item['id'] ?>][<?= $fieldKey ?>]" value="<?= htmlspecialchars((string) ($item[$fieldKey] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <?php foreach ($sectionDef['fields'] as $fieldKey => $fieldDef): ?>
                    <?php if ($fieldDef['type'] !== 'textarea') continue; ?>
                    <label><?= htmlspecialchars($fieldDef['label'], ENT_QUOTES) ?></label>
                    <textarea name="<?= $sectionKey ?>[<?= (int) $item['id'] ?>][<?= $fieldKey ?>]" maxlength="<?= $fieldDef['maxlength'] ?>" rows="<?= $fieldKey === 'content' ? 8 : 3 ?>"><?= htmlspecialchars((string) ($item[$fieldKey] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                  <?php endforeach; ?>
                  <div class="row-actions">
                    <input type="file" name="<?= $sectionKey ?>_image[<?= (int) $item['id'] ?>]" accept="image/jpeg,image/png,image/gif,image/webp">
                    <input type="hidden" class="delete-flag" name="<?= $sectionKey ?>[<?= (int) $item['id'] ?>][delete]" value="">
                    <span class="delete-toggle">Delete</span>
                  </div>
                  <div class="hint">Leave empty to keep the current photo. Max <?= $maxUploadMb ?> MB (JPG/PNG/GIF/WEBP).</div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>

      <div class="save-bar">
        <button type="submit">Save All Changes</button>
      </div>
    </form>
  </main>

  <template id="template-gallery">
    <div class="item-row" data-key="">
      <img class="thumb" src="" alt="">
      <div class="fields">
        <input type="hidden" name="" value="">
        <div class="field-row">
          <div><label>Title</label><input type="text" maxlength="200" name=""></div>
          <div><label>Caption</label><input type="text" maxlength="300" name=""></div>
        </div>
        <div class="row-actions">
          <input type="file" name="" accept="image/jpeg,image/png,image/gif,image/webp" required>
          <input type="hidden" class="delete-flag" name="" value="">
          <span class="delete-toggle">Delete</span>
        </div>
        <div class="hint">New item — an image is required. Max <?= $maxUploadMb ?> MB (JPG/PNG/GIF/WEBP).</div>
      </div>
    </div>
  </template>

  <template id="template-testimonials">
    <div class="item-row" data-key="">
      <img class="thumb" src="" alt="">
      <div class="fields">
        <input type="hidden" name="" value="">
        <div class="field-row">
          <div><label>Name</label><input type="text" maxlength="100" name=""></div>
          <div><label>Role / Company</label><input type="text" maxlength="150" name=""></div>
          <div><label>Rating (1-5)</label><input type="number" min="1" max="5" value="5" name=""></div>
        </div>
        <label>Testimonial text</label>
        <textarea maxlength="3000" name=""></textarea>
        <div class="row-actions">
          <input type="file" name="" accept="image/jpeg,image/png,image/gif,image/webp" required>
          <input type="hidden" class="delete-flag" name="" value="">
          <span class="delete-toggle">Delete</span>
        </div>
        <div class="hint">New item — an image is required. Max <?= $maxUploadMb ?> MB (JPG/PNG/GIF/WEBP).</div>
      </div>
    </div>
  </template>

  <template id="template-blogs">
    <div class="item-row" data-key="">
      <img class="thumb" src="" alt="">
      <div class="fields">
        <input type="hidden" name="" value="">
        <div class="field-row">
          <div><label>Date</label><input type="text" maxlength="50" name="" placeholder="August 15, 2026"></div>
          <div><label>Title</label><input type="text" maxlength="200" name=""></div>
        </div>
        <label>Excerpt (shown on the card)</label>
        <textarea maxlength="500" name=""></textarea>
        <label>Full article (shown when a reader clicks the card)</label>
        <textarea maxlength="20000" name="" rows="8"></textarea>
        <div class="row-actions">
          <input type="file" name="" accept="image/jpeg,image/png,image/gif,image/webp" required>
          <input type="hidden" class="delete-flag" name="" value="">
          <span class="delete-toggle">Delete</span>
        </div>
        <div class="hint">New item — an image is required. Max <?= $maxUploadMb ?> MB (JPG/PNG/GIF/WEBP).</div>
      </div>
    </div>
  </template>

  <script>
    (function () {
      var fieldOrder = {
        gallery: ['title', 'caption'],
        testimonials: ['name', 'role', 'rating', 'text'],
        blogs: ['date', 'title', 'excerpt', 'content'],
      };
      var newCounter = 0;

      function wireDeleteToggle(row) {
        var toggle = row.querySelector('.delete-toggle');
        var flag = row.querySelector('.delete-flag');
        toggle.addEventListener('click', function () {
          var marked = row.classList.toggle('marked-delete');
          flag.value = marked ? '1' : '';
          toggle.textContent = marked ? 'Undo' : 'Delete';
        });
      }

      document.querySelectorAll('.item-row').forEach(wireDeleteToggle);

      document.querySelectorAll('[data-add]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var section = btn.getAttribute('data-add');
          var template = document.getElementById('template-' + section);
          var clone = template.content.firstElementChild.cloneNode(true);
          var key = 'new' + (++newCounter);

          clone.querySelectorAll('input[type="hidden"]:not(.delete-flag)').forEach(function (input) {
            input.name = section + '[' + key + '][id]';
            input.value = '';
          });
          clone.querySelector('.delete-flag').name = section + '[' + key + '][delete]';

          var fields = fieldOrder[section];
          var fieldInputs = clone.querySelectorAll('.fields input[type="text"], .fields input[type="number"], .fields textarea');
          fieldInputs.forEach(function (input, i) {
            if (fields[i]) {
              input.name = section + '[' + key + '][' + fields[i] + ']';
            }
          });

          clone.querySelector('input[type="file"]').name = section + '_image[' + key + ']';

          wireDeleteToggle(clone);
          btn.closest('section').querySelector('.items').appendChild(clone);
        });
      });
    })();
  </script>
</body>
</html>
