<?php
declare(strict_types=1);

use App\Services\CertificateOfAppearanceService as Coa;

$token = function_exists('csrf_token') ? csrf_token() : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Certificate of Appearance</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<?php $activeNav = 'admin_coa'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h5 mb-0">Send Certificate of Appearance</h1>
    <div class="btn-group">
      <a class="btn btn-outline-primary btn-sm" href="?r=admin_coa_monitor">Send monitor</a>
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_coa_signatories">Manage signatories</a>
    </div>
  </div>

  <?php if (!empty($flash)): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info', ENT_QUOTES) ?>">
      <?= htmlspecialchars($flash['message'] ?? '', ENT_QUOTES) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($monitorSummary) && ((int)$monitorSummary['sent'] + (int)$monitorSummary['failed'] + (int)$monitorSummary['pending']) > 0): ?>
  <div class="row g-2 mb-3">
    <div class="col-4">
      <a href="?r=admin_coa_monitor" class="card text-decoration-none border-0 shadow-sm h-100">
        <div class="card-body py-2">
          <div class="small text-muted">Sent</div>
          <div class="h5 mb-0 text-success"><?= (int)$monitorSummary['sent'] ?></div>
        </div>
      </a>
    </div>
    <div class="col-4">
      <a href="?r=admin_coa_monitor&amp;status=failed" class="card text-decoration-none border-0 shadow-sm h-100">
        <div class="card-body py-2">
          <div class="small text-muted">Failed</div>
          <div class="h5 mb-0 text-danger"><?= (int)$monitorSummary['failed'] ?></div>
        </div>
      </a>
    </div>
    <div class="col-4">
      <a href="?r=admin_coa_monitor&amp;status=pending" class="card text-decoration-none border-0 shadow-sm h-100">
        <div class="card-body py-2">
          <div class="small text-muted">Queued to resend</div>
          <div class="h5 mb-0 text-warning"><?= (int)$monitorSummary['pending'] ?></div>
        </div>
      </a>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($lastResult)): ?>
    <div class="alert alert-secondary">
      <div><strong>Batch #<?= (int)$lastResult['batch_id'] ?></strong>:
        <?= (int)$lastResult['sent'] ?> sent,
        <?= (int)$lastResult['failed'] ?> failed,
        <?= (int)$lastResult['skipped'] ?> skipped
      </div>
      <?php if (!empty($lastResult['failures'])): ?>
        <ul class="mb-0 mt-2 small">
          <?php foreach (array_slice($lastResult['failures'], 0, 30) as $f): ?>
            <li><?= htmlspecialchars((string)$f, ENT_QUOTES) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="get" class="card mb-3">
    <div class="card-body row g-3 align-items-end">
      <input type="hidden" name="r" value="admin_coa">
      <input type="hidden" name="load" value="1">
      <div class="col-md-3">
        <label class="form-label">Event (optional)</label>
        <select name="event_id" class="form-select">
          <option value="">All events</option>
          <?php foreach ($events as $ev): ?>
            <option value="<?= (int)$ev['id'] ?>" <?= ($eventId !== null && (int)$eventId === (int)$ev['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)$ev['name'], ENT_QUOTES) ?><?= !empty($ev['active']) ? ' (active)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Date from</label>
        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Date to</label>
        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo, ENT_QUOTES) ?>">
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary w-100">Load present + signed</button>
      </div>
      <div class="col-12 text-muted small">
        Only guests with attendance signature and status present are eligible.
        <?php if ($load): ?> Found <strong><?= count($recipients) ?></strong> eligible guest(s).<?php endif; ?>
      </div>
    </div>
  </form>

  <?php if (!$signatories): ?>
    <div class="alert alert-warning">Add at least one active signatory with an e-signature before sending. <a href="?r=admin_coa_signatories">Manage signatories</a></div>
  <?php endif; ?>

  <form method="post" id="coaSendForm">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
    <input type="hidden" name="event_id" value="<?= $eventId !== null ? (int)$eventId : '' ?>">
    <input type="hidden" name="date_from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES) ?>">
    <input type="hidden" name="date_to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES) ?>">

    <div class="card mb-3">
      <div class="card-body row g-3">
        <div class="col-12"><h2 class="h6 mb-0">Activity details (fills underlines)</h2></div>
        <div class="col-md-6">
          <label class="form-label">Appeared at (venue)</label>
          <input name="venue" class="form-control" required value="<?= htmlspecialchars($venue, ENT_QUOTES) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Inclusive dates (as printed)</label>
          <input name="inclusive_dates" class="form-control" required value="<?= htmlspecialchars($inclusiveDates, ENT_QUOTES) ?>" placeholder="June 17-18, 2026">
        </div>
        <div class="col-md-8">
          <label class="form-label">Purpose</label>
          <textarea name="purpose" class="form-control" rows="2" required><?= htmlspecialchars($purpose, ENT_QUOTES) ?></textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label">Issue date</label>
          <input type="date" name="issue_date" class="form-control" required value="<?= htmlspecialchars($issueDate, ENT_QUOTES) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Signatory</label>
          <select name="signatory_id" class="form-select" required>
            <option value="">Select…</option>
            <?php foreach ($signatories as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['full_name'] . ' — ' . $s['title'], ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body row g-3">
        <div class="col-12"><h2 class="h6 mb-0">Particulars defaults (apply to all)</h2></div>
        <div class="col-md-4">
          <label class="form-label">Hotel / lodging &amp; meals</label>
          <select name="default_lodging" class="form-select" id="default_lodging">
            <option value="not_provided" selected>DID NOT PROVIDE</option>
            <option value="provided">PROVIDED</option>
          </select>
        </div>
        <div class="col-md-4" id="defaultMeals">
          <label class="form-label">Meals (when provided)</label>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="default_meal_breakfast" id="dmb"><label class="form-check-label" for="dmb">Breakfast</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="default_meal_lunch" id="dml"><label class="form-check-label" for="dml">Lunch</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="default_meal_dinner" id="dmd"><label class="form-check-label" for="dmd">Dinner</label></div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Vehicle</label>
          <select name="default_vehicle" class="form-select">
            <option value="not_provided" selected>DID NOT PROVIDE VEHICLE</option>
            <option value="provided">PROVIDED VEHICLE</option>
          </select>
        </div>
      </div>
    </div>

    <?php if ($load && $recipients): ?>
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
          <h2 class="h6 mb-0">Recipients</h2>
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" id="selectAll">Select all</button>
            <button type="button" class="btn btn-outline-secondary" id="selectNone">Select none</button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th></th>
                <th>Name</th>
                <th>Agency</th>
                <th>Email</th>
                <th>Attendance</th>
                <th>Override</th>
                <th>Skip</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($recipients as $r): ?>
              <?php
                $pid = (int)$r['id'];
                $email = Coa::resolveEmail($r);
                $name = Coa::fullName($r);
              ?>
              <tr>
                <td><input type="checkbox" class="form-check-input recip" name="recipient_ids[]" value="<?= $pid ?>" checked></td>
                <td><?= htmlspecialchars($name, ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars((string)($r['agency'] ?? ''), ENT_QUOTES) ?></td>
                <td class="<?= $email ? '' : 'text-danger' ?>"><?= htmlspecialchars($email ?? 'none', ENT_QUOTES) ?></td>
                <td class="small"><?= htmlspecialchars((string)($r['attendance_dates'] ?? ''), ENT_QUOTES) ?></td>
                <td>
                  <div class="form-check">
                    <input class="form-check-input ov-toggle" type="checkbox" name="override_<?= $pid ?>_enabled" id="ov<?= $pid ?>" data-target="ovpanel<?= $pid ?>">
                    <label class="form-check-label small" for="ov<?= $pid ?>">Custom</label>
                  </div>
                  <div id="ovpanel<?= $pid ?>" class="border rounded p-2 mt-1 small d-none" style="min-width:220px">
                    <label class="form-label mb-0">Lodging</label>
                    <select name="override_<?= $pid ?>_lodging" class="form-select form-select-sm mb-1">
                      <option value="not_provided">DID NOT PROVIDE</option>
                      <option value="provided">PROVIDED</option>
                    </select>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="override_<?= $pid ?>_meal_breakfast" id="ob<?= $pid ?>"><label class="form-check-label" for="ob<?= $pid ?>">Breakfast</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="override_<?= $pid ?>_meal_lunch" id="ol<?= $pid ?>"><label class="form-check-label" for="ol<?= $pid ?>">Lunch</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="override_<?= $pid ?>_meal_dinner" id="od<?= $pid ?>"><label class="form-check-label" for="od<?= $pid ?>">Dinner</label></div>
                    <label class="form-label mb-0 mt-1">Vehicle</label>
                    <select name="override_<?= $pid ?>_vehicle" class="form-select form-select-sm">
                      <option value="not_provided">DID NOT PROVIDE</option>
                      <option value="provided">PROVIDED</option>
                    </select>
                  </div>
                </td>
                <td><input type="checkbox" class="form-check-input" name="skip_ids[]" value="<?= $pid ?>"></td>
                <td>
                  <button type="submit" class="btn btn-sm btn-outline-primary" formaction="?r=admin_coa_preview" formtarget="_blank" name="participant_id" value="<?= $pid ?>">Preview</button>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button type="submit" class="btn btn-success" formaction="?r=admin_coa_send" <?= !$signatories ? 'disabled' : '' ?> onclick="return confirm('Email certificates to all selected recipients?');">
          Send certificates to selected
        </button>
      </div>
    </div>
    <?php elseif ($load): ?>
      <div class="alert alert-info">No present + signed guests match this filter.</div>
    <?php endif; ?>
  </form>
</div>
<script>
document.getElementById('selectAll')?.addEventListener('click', () => {
  document.querySelectorAll('.recip').forEach(el => { el.checked = true; });
});
document.getElementById('selectNone')?.addEventListener('click', () => {
  document.querySelectorAll('.recip').forEach(el => { el.checked = false; });
});
document.querySelectorAll('.ov-toggle').forEach(el => {
  el.addEventListener('change', () => {
    const panel = document.getElementById(el.dataset.target);
    if (panel) panel.classList.toggle('d-none', !el.checked);
  });
});
</script>
</body>
</html>
