<?php
declare(strict_types=1);

$token = function_exists('csrf_token') ? csrf_token() : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Events</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<?php $activeNav = 'admin_events'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5">Events</h1>
    <div class="btn-group">
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_registrants">Registrants</a>
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_attendance">Attendance</a>
    </div>
  </div>
  <form method="post" action="?r=admin_events_create" class="row g-2 mb-3">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
    <div class="col-12 col-md-6"><input name="name" class="form-control" placeholder="Event name" required></div>
    <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" name="enforce" id="enf" checked><label class="form-check-label" for="enf">Enforce single time-in per day</label></div></div>
    <div class="col"><button class="btn btn-primary">Add Event</button></div>
  </form>
  <div class="table-responsive table-modern">
    <table class="table table-sm align-middle">
      <thead><tr><th>#</th><th>Name</th><th>Enforce</th><th>Active</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach (($rows??[]) as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars($r['name'], ENT_QUOTES) ?></td>
          <td><?= ((int)$r['enforce_single_time_in']) ? 'Yes' : 'No' ?></td>
          <td><?= ((int)$r['active']) ? 'Yes' : 'No' ?></td>
          <td>
            <form method="post" action="?r=admin_events_set_active" class="d-inline">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-outline-primary">Set Active</button>
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