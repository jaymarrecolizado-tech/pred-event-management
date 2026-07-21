<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Logs</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<?php $activeNav = 'admin_logs'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5">Admin Logs</h1>
    <div class="btn-group">
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_registrants">Registrants</a>
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_attendance">Attendance</a>
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_export">Export</a>
    </div>
  </div>
  <div class="table-responsive table-modern">
    <table class="table table-sm align-middle">
      <thead><tr><th>#</th><th>Admin</th><th>Action</th><th>Detail</th><th>Time</th></tr></thead>
      <tbody>
        <?php foreach (($rows??[]) as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars((string)$r['admin_id'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($r['action']??'', ENT_QUOTES) ?></td>
            <td><code><?= htmlspecialchars($r['detail']??'', ENT_QUOTES) ?></code></td>
            <td><?= htmlspecialchars($r['created_at']??'', ENT_QUOTES) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>