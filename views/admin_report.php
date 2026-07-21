<?php
declare(strict_types=1);

$token = function_exists('csrf_token') ? csrf_token() : '';
$tpl = $tpl ?? [];
$pdfAvailable = $pdfAvailable ?? false;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Attendance Report Builder</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<?php $activeNav = 'admin_report'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-3" style="max-width:800px">
  <h1 class="h5 mb-3">Attendance Report Builder</h1>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="?r=register">Home</a></li>
      <li class="breadcrumb-item"><a href="?r=admin_registrants">Admin</a></li>
      <li class="breadcrumb-item active" aria-current="page">Report</li>
    </ol>
  </nav>
  <?php if (!empty($reportNotice)): ?>
    <div class="alert alert-warning"><?= htmlspecialchars((string)$reportNotice, ENT_QUOTES) ?></div>
  <?php endif; ?>
  <form method="post" action="?r=admin_report_generate" class="row g-3" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
    <input type="hidden" name="download" value="0">
    <div class="col-12">
      <label class="form-label">Report Title</label>
      <textarea name="title" class="form-control" rows="2" style="resize: vertical;">Attendance Report</textarea>
      <div class="form-text">Enter a descriptive title for your report. Supports multiple lines.</div>
    </div>
    <div class="col-12">
      <label class="form-label">Subtitle</label>
      <textarea name="subtitle" class="form-control" rows="3" placeholder="Event / Venue / Date / Additional details..." style="resize: vertical;"></textarea>
      <div class="form-text">Add subtitle information such as event name, venue, date range, or other details. Supports multiple lines.</div>
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label">Date</label>
      <input name="date" type="date" class="form-control">
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label">Start Date</label>
      <input name="start_date" type="date" class="form-control">
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label">End Date</label>
      <input name="end_date" type="date" class="form-control">
    </div>
    <div class="col-12">
      <label class="form-label">Fields</label>
      <div class="row g-1">
        <?php $fields = ['id'=>'No.','name'=>'Name','agency'=>'Agency/Org.','sector'=>'Sector','designation'=>'Designation','email'=>'Email','sex'=>'Gender','registered_at'=>'Registered At']; foreach ($fields as $k=>$v): ?>
        <div class="col-6 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="fields[]" value="<?= $k ?>" id="f<?= $k ?>" checked><label class="form-check-label" for="f<?= $k ?>"><?= $v ?></label></div></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label">Output Format</label>
      <select name="format" class="form-select">
        <option value="auto" selected>Auto (PDF if available, else HTML)</option>
        <option value="pdf">PDF</option>
        <option value="html">HTML</option>
      </select>
      <div class="form-text">
        <?php if ($pdfAvailable): ?>
          <span class="badge bg-success">PDF engine available</span>
        <?php else: ?>
          <span class="badge bg-warning text-dark">PDF engine not installed — output will be HTML</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label">Header Logos</label>
      <div class="row g-2">
        <div class="col-6"><input type="file" name="left_logo" accept="image/*" class="form-control"></div>
        <div class="col-6"><input type="file" name="right_logo" accept="image/*" class="form-control"></div>
      </div>
    </div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-primary" type="submit" onclick="document.querySelector('select[name=format]').value='html';document.querySelector('input[name=download]').value='0'">Generate HTML</button>
      <button class="btn btn-outline-primary" type="submit" onclick="document.querySelector('select[name=format]').value='pdf';document.querySelector('input[name=download]').value='0'">Export PDF (Inline)</button>
      <button class="btn btn-success" type="submit" onclick="document.querySelector('select[name=format]').value='pdf';document.querySelector('input[name=download]').value='1'">Download PDF</button>
    </div>
  </form>
  <hr class="my-4">
  <h2 class="h6">Templates</h2>
  <form method="post" action="?r=admin_report_save" class="row g-2">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
    <div class="col-12 col-md-6"><input name="tpl_name" class="form-control" placeholder="Template name" required></div>
    <div class="col-12 col-md-3"><button class="btn btn-outline-primary w-100">Save Current Settings</button></div>
  </form>
  <div class="mt-2">
    <label class="form-label">Load Template</label>
    <div class="input-group">
      <select id="tplSelect" class="form-select">
        <?php if (!$tpl): ?>
          <option value="">No saved templates yet</option>
        <?php endif; ?>
        <?php foreach ($tpl as $t): ?>
          <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars((string)$t['name'], ENT_QUOTES) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-outline-secondary" type="button" id="tplLoadBtn" <?= !$tpl ? 'disabled' : '' ?>>Load</button>
    </div>
  </div>
</div>
<script>
document.getElementById('tplLoadBtn')?.addEventListener('click', () => {
  const id = document.getElementById('tplSelect').value;
  if (!id) return;
  fetch('?r=admin_report_load&tpl_id=' + encodeURIComponent(id))
    .then(r => r.json()).then(cfg => {
      if (cfg && !cfg.error) {
        const titleEl = document.querySelector('textarea[name=title]') || document.querySelector('input[name=title]');
        const subtitleEl = document.querySelector('textarea[name=subtitle]') || document.querySelector('input[name=subtitle]');
        if (titleEl) titleEl.value = cfg.title||'';
        if (subtitleEl) subtitleEl.value = cfg.subtitle||'';
        document.querySelector('input[name=date]').value = cfg.date||'';
        document.querySelector('input[name=start_date]').value = cfg.start_date||'';
        document.querySelector('input[name=end_date]').value = cfg.end_date||'';
        document.querySelector('select[name=format]').value = cfg.format||'auto';
        const f = cfg.fields||[];
        document.querySelectorAll('input[name="fields[]"]').forEach(cb => { cb.checked = f.includes(cb.value); });
      }
    });
});
</script>
</body>
</html>
