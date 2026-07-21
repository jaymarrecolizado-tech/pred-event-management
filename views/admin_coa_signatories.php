<?php
declare(strict_types=1);

$token = function_exists('csrf_token') ? csrf_token() : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CoA Signatories</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<?php $activeNav = 'admin_coa'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h5 mb-0">Certificate Signatories</h1>
    <div class="btn-group">
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_coa">Send CoA</a>
      <a class="btn btn-outline-primary btn-sm" href="?r=admin_coa_monitor">Send monitor</a>
    </div>
  </div>

  <?php if (!empty($flash)): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info', ENT_QUOTES) ?>">
      <?= htmlspecialchars($flash['message'] ?? '', ENT_QUOTES) ?>
    </div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-body">
      <h2 class="h6">Add signatory</h2>
      <form method="post" action="?r=admin_coa_signatory_save" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <div class="col-md-4">
          <label class="form-label">Full name</label>
          <input name="full_name" class="form-control" required placeholder="Ms. Mina Flor T. Villafuerte">
        </div>
        <div class="col-md-4">
          <label class="form-label">Title</label>
          <input name="title" class="form-control" required placeholder="Chief, Administrative and Finance Division">
        </div>
        <div class="col-md-4">
          <label class="form-label">E-signature (PNG/JPG)</label>
          <input type="file" name="signature" class="form-control" accept="image/png,image/jpeg" required>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="active" id="activeNew" checked>
            <label class="form-check-label" for="activeNew">Active</label>
          </div>
        </div>
        <div class="col-12">
          <button class="btn btn-primary">Add signatory</button>
        </div>
      </form>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Name</th>
          <th>Title</th>
          <th>Signature</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="5" class="text-muted">No signatories yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td colspan="5" class="border-0 pb-0">
            <form method="post" action="?r=admin_coa_signatory_save" enctype="multipart/form-data" class="row g-2 align-items-end">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <div class="col-md-3">
                <label class="form-label small mb-0">Full name</label>
                <input name="full_name" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$row['full_name'], ENT_QUOTES) ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-0">Title</label>
                <input name="title" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$row['title'], ENT_QUOTES) ?>" required>
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-0">Replace signature</label>
                <input type="file" name="signature" class="form-control form-control-sm" accept="image/png,image/jpeg">
              </div>
              <div class="col-md-2">
                <?php if (!empty($row['signature_path']) && is_file($row['signature_path'])): ?>
                  <img src="?r=admin_coa_signatory_image&amp;id=<?= (int)$row['id'] ?>" alt="signature" style="max-height:48px;max-width:120px;background:#fff;border:1px solid #dee2e6;padding:2px">
                <?php else: ?>
                  <span class="text-danger small">Missing file</span>
                <?php endif; ?>
              </div>
              <div class="col-md-1">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="active" id="active<?= (int)$row['id'] ?>" <?= !empty($row['active']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="active<?= (int)$row['id'] ?>">Active</label>
                </div>
              </div>
              <div class="col-md-1">
                <button class="btn btn-sm btn-outline-primary w-100">Save</button>
              </div>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
