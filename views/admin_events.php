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
  <link href="assets/app.css?v=20260722b" rel="stylesheet">
</head>
<body>
<?php $activeNav = 'admin_events'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h5 mb-1">Events</h1>
      <p class="text-muted small mb-0">Only one event should be <strong>Active</strong> at a time. Delete unused/test events that have no attendance.</p>
    </div>
    <div class="btn-group">
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_registrants">Registrants</a>
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_attendance">Attendance</a>
    </div>
  </div>

  <?php if (!empty($flash)): ?>
    <div class="alert alert-<?= htmlspecialchars((string)($flash['type'] ?? 'info'), ENT_QUOTES) ?>">
      <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES) ?>
    </div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-body">
      <h2 class="h6 mb-3">Add event</h2>
      <form method="post" action="?r=admin_events_create" class="row g-3 align-items-end">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <div class="col-md-5">
          <label class="form-label" for="event_name">Event name</label>
          <input name="name" id="event_name" class="form-control" placeholder="e.g. DICT AI ROADSHOW 2026" required>
        </div>
        <div class="col-md-5">
          <div class="form-check mt-md-4">
            <input class="form-check-input" type="checkbox" name="enforce" id="enf" checked>
            <label class="form-check-label" for="enf">Enforce one time-in per day (same QR still works on other days)</label>
          </div>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100">Add event</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h2 class="h6 mb-3">All events</h2>
      <div class="table-responsive table-modern">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Enforce daily time-in</th>
              <th>Status</th>
              <th>Linked records</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="6" class="text-muted">No events yet. Add one above.</td></tr>
            <?php endif; ?>
            <?php foreach (($rows ?? []) as $r): ?>
              <?php
                $id = (int)$r['id'];
                $isActive = (int)($r['active'] ?? 0) === 1;
                $attCount = (int)($r['attendance_count'] ?? 0);
                $coaCount = (int)($r['coa_batch_count'] ?? 0);
                $canDelete = !$isActive && $attCount === 0 && $coaCount === 0;
              ?>
              <tr class="<?= $isActive ? 'table-success' : '' ?>">
                <td><?= $id ?></td>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars((string)$r['name'], ENT_QUOTES) ?></div>
                  <?php if ($isActive): ?>
                    <span class="badge text-bg-success">Active</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">Inactive</span>
                  <?php endif; ?>
                </td>
                <td><?= ((int)$r['enforce_single_time_in']) ? 'Yes' : 'No' ?></td>
                <td><?= $isActive ? 'Active' : 'Inactive' ?></td>
                <td class="small text-muted">
                  <?= $attCount ?> attendance
                  · <?= $coaCount ?> CoA batch<?= $coaCount === 1 ? '' : 'es' ?>
                </td>
                <td class="text-end">
                  <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                    <?php if (!$isActive): ?>
                      <form method="post" action="?r=admin_events_set_active" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button class="btn btn-sm btn-primary" type="submit">Set Active</button>
                      </form>
                    <?php else: ?>
                      <form method="post" action="?r=admin_events_deactivate" class="d-inline"
                            onsubmit="return confirm('Deactivate this event? Attendance will have no active event until you set another.');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button class="btn btn-sm btn-outline-warning" type="submit">Deactivate</button>
                      </form>
                    <?php endif; ?>

                    <?php if ($canDelete): ?>
                      <form method="post" action="?r=admin_events_delete" class="d-inline"
                            onsubmit="return confirm('Permanently delete “<?= htmlspecialchars((string)$r['name'], ENT_QUOTES) ?>”? This cannot be undone.');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                      </form>
                    <?php else: ?>
                      <button class="btn btn-sm btn-outline-secondary" type="button" disabled
                              title="<?= $isActive ? 'Deactivate first, then delete if unused' : 'Has linked attendance or CoA records — deactivate instead of deleting' ?>">
                        Delete
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="small text-muted mt-3 mb-0">
        <strong>Set Active</strong> makes this the live event (others become inactive).
        <strong>Deactivate</strong> turns it off without deleting.
        <strong>Delete</strong> is only for inactive events with no attendance or CoA history (safe for leftover test events).
      </p>
    </div>
  </div>
</div>
</body>
</html>
