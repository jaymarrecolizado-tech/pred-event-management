<?php
declare(strict_types=1);

use App\Services\CertificateOfAppearanceService as Coa;

$token = function_exists('csrf_token') ? csrf_token() : '';
$summary = $summary ?? ['sent' => 0, 'failed' => 0, 'pending' => 0, 'skipped' => 0, 'batches' => 0];
$batches = $batches ?? [];
$items = $items ?? [];
$statusFilter = $statusFilter ?? '';
$batchId = $batchId ?? null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CoA Send Monitor</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<?php $activeNav = 'admin_coa'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h5 mb-0">Certificate send monitor</h1>
    <div class="btn-group">
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_coa">Send new</a>
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_coa_signatories">Signatories</a>
    </div>
  </div>

  <?php if (!empty($flash)): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info', ENT_QUOTES) ?>">
      <?= htmlspecialchars($flash['message'] ?? '', ENT_QUOTES) ?>
    </div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Sent</div>
          <div class="h3 mb-0 text-success"><?= (int)$summary['sent'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Failed</div>
          <div class="h3 mb-0 text-danger"><?= (int)$summary['failed'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Queued to resend</div>
          <div class="h3 mb-0 text-warning"><?= (int)$summary['pending'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Batches</div>
          <div class="h3 mb-0"><?= (int)$summary['batches'] ?></div>
          <div class="small text-muted"><?= (int)$summary['skipped'] ?> skipped</div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <form method="post" action="?r=admin_coa_queue_failed" class="d-inline">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
      <?php if ($batchId): ?><input type="hidden" name="batch_id" value="<?= (int)$batchId ?>"><?php endif; ?>
      <button class="btn btn-outline-danger btn-sm" type="submit" <?= (int)$summary['failed'] < 1 && !$batchId ? 'disabled' : '' ?>
              onclick="return confirm('Queue failed certificates for resend?');">
        Queue failed for resend<?= $batchId ? ' (this batch)' : ' (all)' ?>
      </button>
    </form>
    <form method="post" action="?r=admin_coa_resend_queued" class="d-inline">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
      <?php if ($batchId): ?><input type="hidden" name="batch_id" value="<?= (int)$batchId ?>"><?php endif; ?>
      <input type="hidden" name="limit" value="50">
      <button class="btn btn-warning btn-sm" type="submit" <?= (int)$summary['pending'] < 1 && !$batchId ? 'disabled' : '' ?>
              onclick="return confirm('Send up to 50 queued certificates now?');">
        Resend queued (max 50)
      </button>
    </form>
    <a class="btn btn-outline-secondary btn-sm" href="?r=admin_coa_monitor">Refresh</a>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <h2 class="h6">Recent batches</h2>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>When</th>
              <th>Inclusive dates</th>
              <th>Signatory</th>
              <th class="text-success">Sent</th>
              <th class="text-danger">Failed</th>
              <th class="text-warning">Queued</th>
              <th>Skipped</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$batches): ?>
            <tr><td colspan="9" class="text-muted">No send batches yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($batches as $b): ?>
            <tr class="<?= $batchId && (int)$batchId === (int)$b['id'] ? 'table-active' : '' ?>">
              <td><?= (int)$b['id'] ?></td>
              <td class="small"><?= htmlspecialchars((string)$b['created_at'], ENT_QUOTES) ?></td>
              <td><?= htmlspecialchars((string)$b['inclusive_dates'], ENT_QUOTES) ?></td>
              <td class="small"><?= htmlspecialchars((string)($b['signatory_name'] ?? ''), ENT_QUOTES) ?></td>
              <td class="text-success fw-semibold"><?= (int)$b['sent_count'] ?></td>
              <td class="text-danger fw-semibold"><?= (int)$b['failed_count'] ?></td>
              <td class="text-warning fw-semibold"><?= (int)($b['queued_count'] ?? 0) ?></td>
              <td><?= (int)$b['skipped_count'] ?></td>
              <td><a class="btn btn-sm btn-outline-primary" href="?r=admin_coa_monitor&amp;batch_id=<?= (int)$b['id'] ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($batchId && $selectedBatch): ?>
  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <h2 class="h6 mb-1">Batch #<?= (int)$selectedBatch['id'] ?></h2>
          <div class="small text-muted">
            <?= htmlspecialchars((string)$selectedBatch['venue'], ENT_QUOTES) ?>
            · <?= htmlspecialchars((string)$selectedBatch['inclusive_dates'], ENT_QUOTES) ?>
            · issued <?= htmlspecialchars((string)$selectedBatch['issue_date'], ENT_QUOTES) ?>
          </div>
        </div>
        <div class="btn-group btn-group-sm">
          <a class="btn btn-outline-secondary <?= $statusFilter === '' ? 'active' : '' ?>" href="?r=admin_coa_monitor&amp;batch_id=<?= (int)$batchId ?>">All</a>
          <a class="btn btn-outline-success <?= $statusFilter === 'sent' ? 'active' : '' ?>" href="?r=admin_coa_monitor&amp;batch_id=<?= (int)$batchId ?>&amp;status=sent">Sent</a>
          <a class="btn btn-outline-danger <?= $statusFilter === 'failed' ? 'active' : '' ?>" href="?r=admin_coa_monitor&amp;batch_id=<?= (int)$batchId ?>&amp;status=failed">Failed</a>
          <a class="btn btn-outline-warning <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="?r=admin_coa_monitor&amp;batch_id=<?= (int)$batchId ?>&amp;status=pending">Queued</a>
          <a class="btn btn-outline-secondary <?= $statusFilter === 'skipped' ? 'active' : '' ?>" href="?r=admin_coa_monitor&amp;batch_id=<?= (int)$batchId ?>&amp;status=skipped">Skipped</a>
        </div>
      </div>

      <?php require __DIR__ . '/partials/admin_coa_items_table.php'; ?>
    </div>
  </div>
  <?php elseif ($batchId): ?>
    <div class="alert alert-warning">Batch not found.</div>
  <?php elseif ($statusFilter !== ''): ?>
  <div class="card">
    <div class="card-body">
      <h2 class="h6 mb-3">All items · <?= htmlspecialchars($statusFilter === 'pending' ? 'queued' : $statusFilter, ENT_QUOTES) ?> (latest 200)</h2>
      <?php require __DIR__ . '/partials/admin_coa_items_table.php'; ?>
    </div>
  </div>
  <?php else: ?>
    <p class="text-muted small mb-0">Open a batch to inspect recipients, queue failures, and resend.</p>
  <?php endif; ?>
</div>
</body>
</html>
