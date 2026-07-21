<?php
declare(strict_types=1);

$token = function_exists('csrf_token') ? csrf_token() : '';
$rows = $rows ?? [];
$errors = $errors ?? [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Import CSV</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<?php $activeNav = 'admin_import'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5">Import CSV</h1>
    <div class="btn-group">
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_registrants">Registrants</a>
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_attendance">Attendance</a>
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_import_history">Import History</a>
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_report">Report</a>
    </div>
  </div>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="?r=register">Home</a></li>
      <li class="breadcrumb-item"><a href="?r=admin_registrants">Admin</a></li>
      <li class="breadcrumb-item active" aria-current="page">Import</li>
    </ol>
  </nav>
  <form method="post" action="?r=admin_import_preview" enctype="multipart/form-data" class="mb-3">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
    <div class="row g-2">
      <div class="col-12 col-md-6"><input type="file" name="csv" accept=".csv" class="form-control" required></div>
      <div class="col-12 col-md-3"><button class="btn btn-primary w-100">Preview</button></div>
    </div>
  </form>

  <?php if ($errors): ?>
  <div class="alert alert-danger"><?= htmlspecialchars(implode('; ', $errors), ENT_QUOTES) ?></div>
  <?php endif; ?>

  <?php if ($rows): ?>
  <div class="mb-3">
    <form method="post" action="?r=admin_import_execute">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
      <div class="row g-2 align-items-center">
        <div class="col-12 col-md-4">
          <select name="strategy" class="form-select">
            <option value="skip">Skip duplicates (Default)</option>
            <option value="override_duplicates">Override duplicates only</option>
            <option value="override_all">Override all matches</option>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <button class="btn btn-success w-100">Confirm & Execute</button>
        </div>
      </div>
    </form>
  </div>
  <div class="table-responsive table-modern">
    <table class="table table-sm align-middle">
      <thead>
        <tr><th>Row</th><th>Name</th><th>Email</th><th>Agency</th><th>Sector</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): $p=$r['row']; ?>
        <tr>
          <td><?= (int)$r['rownum'] ?></td>
          <td><?= htmlspecialchars($p['first_name'].' '.$p['last_name'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($p['email'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($p['agency'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($p['sector'], ENT_QUOTES) ?></td>
          <td>
            <?php $s=$r['status']; $cls = $s==='New'?'success':($s==='Error'?'danger':($s==='Duplicate (email)'||$s==='Duplicate (name+agency)'?'warning':'secondary')); ?>
            <span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($s, ENT_QUOTES) ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
</body>
</html>