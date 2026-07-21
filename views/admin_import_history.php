<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Import History</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<?php $activeNav = 'admin_import'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5">Import History</h1>
    <div class="btn-group">
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_import">Import</a>
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_registrants">Registrants</a>
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_report">Report</a>
    </div>
  </div>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="?r=register">Home</a></li>
      <li class="breadcrumb-item"><a href="?r=admin_registrants">Admin</a></li>
      <li class="breadcrumb-item"><a href="?r=admin_import">Import</a></li>
      <li class="breadcrumb-item active" aria-current="page">History</li>
    </ol>
  </nav>
  <div class="table-responsive table-modern">
    <table class="table table-sm align-middle">
      <thead>
        <tr><th>#</th><th>File</th><th>Action</th><th>Strategy</th><th>Counts</th><th>Stored CSV</th><th>Admin</th><th>Timestamp</th></tr>
      </thead>
      <tbody>
        <?php foreach (($rows??[]) as $i=>$r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars($r['file_name']??'', ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($r['action']??'', ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($r['duplicate_strategy']??'', ENT_QUOTES) ?></td>
          <?php $sum=null; if (!empty($r['summary'])) { $sum=json_decode($r['summary'], true); } ?>
          <td>
            <?php if ($sum): ?>
              <span class="badge bg-success">Inserted <?= (int)($sum['inserted']??0) ?></span>
              <span class="badge bg-primary">Updated <?= (int)($sum['updated']??0) ?></span>
              <span class="badge bg-secondary">Skipped <?= (int)($sum['skipped']??0) ?></span>
              <span class="badge bg-danger">Errors <?= (int)($sum['errored']??0) ?></span>
            <?php else: ?>
              <span class="text-muted">n/a</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($sum && !empty($sum['stored_csv'])): ?>
              <code><?= htmlspecialchars(basename($sum['stored_csv']), ENT_QUOTES) ?></code>
            <?php else: ?>
              <span class="text-muted">n/a</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars((string)$r['admin_id'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($r['created_at']??'', ENT_QUOTES) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>