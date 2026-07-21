<?php
declare(strict_types=1);
$token = function_exists('csrf_token') ? csrf_token() : '';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$defaultMessage = 'Thank you for joining and registering for the event. Please keep this QR code for fast onsite check-in.';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registrants</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<?php $activeNav = 'admin_registrants'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <div>
      <p class="text-uppercase text-muted mb-1" style="letter-spacing:.2em;font-size:.75rem;">Operations</p>
      <h1 class="page-heading h4 mb-0">Registrants</h1>
      <div class="text-muted small">Search, export, and generate QR codes on demand.</div>
    </div>
    <div class="d-flex flex-column flex-sm-row gap-2">
      <form method="post" action="?r=admin_generate_qr" class="d-flex align-items-center gap-2">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <input type="number" name="batch" min="1" max="200" value="50" class="form-control form-control-sm" style="width:90px" title="Batch size">
        <button class="btn btn-outline-primary btn-sm px-3" type="submit">Generate Missing QRs</button>
      </form>
      <a class="btn btn-outline-secondary btn-sm px-3" href="?r=admin_logout">Logout</a>
    </div>
  </div>
  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info', ENT_QUOTES) ?>"><?= htmlspecialchars($flash['message'] ?? '', ENT_QUOTES) ?></div>
  <?php endif; ?>
  <?php if (!empty($canViewVip)): ?>
    <div class="alert alert-info border-0 shadow-sm">
      <?php if (!empty($canManageVip)): ?>
        <strong>VIP guests:</strong> Use <em>Mark VIP</em> on a registrant to flag priority guests for checkers and the SEO dashboard.
      <?php else: ?>
        <strong>VIP guests:</strong> Yellow <em>VIP</em> badges mark priority guests — treat them with protocol care at check-in.
      <?php endif; ?>
      <?php if (($vipCount ?? 0) > 0): ?>
        <span class="badge text-bg-warning ms-1"><?= (int)$vipCount ?> VIP<?= (int)$vipCount === 1 ? '' : 's' ?> flagged</span>
      <?php else: ?>
        <span class="text-muted ms-1">No VIPs flagged yet.</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="?r=register">Home</a></li>
      <li class="breadcrumb-item"><a href="?r=admin_registrants">Admin</a></li>
      <li class="breadcrumb-item active" aria-current="page">Registrants</li>
    </ol>
  </nav>
  <form method="get" action=".">
    <input type="hidden" name="r" value="admin_registrants">
    <div class="row g-2">
      <div class="col-12 col-md-3"><input name="q" value="<?= htmlspecialchars($q??'', ENT_QUOTES) ?>" placeholder="Name" class="form-control"></div>
      <div class="col-12 col-md-3">
        <div class="input-group">
          <input name="agency" list="agencyList" value="<?= htmlspecialchars($agency??'', ENT_QUOTES) ?>" placeholder="Agency" class="form-control">
          <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#agencyExpand">Expand</button>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <div class="input-group">
          <input name="sector" list="sectorList" value="<?= htmlspecialchars($sector??'', ENT_QUOTES) ?>" placeholder="Sector" class="form-control">
          <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#sectorExpand">Expand</button>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <button id="searchBtn" class="btn btn-primary w-100" type="submit">
          <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" style="display:none"></span>
          Search
        </button>
      </div>
      <?php if (!empty($canViewVip)): ?>
      <div class="col-12 col-md-3">
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="vip" value="1" id="vipOnly" <?= !empty($vipOnly) ? 'checked' : '' ?>>
          <label class="form-check-label" for="vipOnly">Show VIP only</label>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </form>
  <datalist id="agencyList">
    <?php foreach (($agenciesList??[]) as $a): ?><option value="<?= htmlspecialchars($a['agency'], ENT_QUOTES) ?>"></option><?php endforeach; ?>
  </datalist>
  <datalist id="sectorList">
    <?php foreach (($sectorsList??[]) as $s): ?><option value="<?= htmlspecialchars($s['sector'], ENT_QUOTES) ?>"></option><?php endforeach; ?>
  </datalist>
  <div class="collapse mt-2" id="agencyExpand">
    <select class="form-select scroll-select" size="6">
      <?php foreach (($agenciesList??[]) as $a): ?><option><?= htmlspecialchars($a['agency'], ENT_QUOTES) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="collapse mt-2" id="sectorExpand">
    <select class="form-select scroll-select" size="6">
      <?php foreach (($sectorsList??[]) as $s): ?><option><?= htmlspecialchars($s['sector'], ENT_QUOTES) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="mt-3">
    <div class="table-responsive table-modern">
      <table class="table table-sm align-middle">
        <thead>
          <tr><th>#</th><th>Name</th><th>Agency</th><th>Sector</th><th>Email</th><th>VIP</th><th>UUID</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (!($rows??[])) : ?>
          <tr><td colspan="8" class="text-center text-muted">No registrants found</td></tr>
        <?php endif; ?>
        <?php foreach (($rows??[]) as $i=>$r):
              $displayEmail = $r['email'] ?: ($r['office_email'] ?? '');
              $isVip = (int)($r['is_vip'] ?? 0) === 1;
        ?>
          <tr>
            <td><?= ($i+1) + (($page-1)*20) ?></td>
            <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($r['agency']??'', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($r['sector']??'', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($displayEmail, ENT_QUOTES) ?></td>
            <td>
              <?php if (!empty($canManageVip)): ?>
              <form method="post" action="?r=admin_registrant_vip" class="d-inline">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                <input type="hidden" name="participant_id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="is_vip" value="<?= $isVip ? '0' : '1' ?>">
                <input type="hidden" name="q" value="<?= htmlspecialchars($q ?? '', ENT_QUOTES) ?>">
                <input type="hidden" name="agency" value="<?= htmlspecialchars($agency ?? '', ENT_QUOTES) ?>">
                <input type="hidden" name="sector" value="<?= htmlspecialchars($sector ?? '', ENT_QUOTES) ?>">
                <input type="hidden" name="page" value="<?= (int)($page ?? 1) ?>">
                <button class="btn btn-sm <?= $isVip ? 'btn-warning' : 'btn-outline-secondary' ?>" type="submit"><?= $isVip ? 'VIP' : 'Mark VIP' ?></button>
              </form>
              <?php else: ?>
                <?= $isVip ? '<span class="badge text-bg-warning">VIP</span>' : '<span class="text-muted">—</span>' ?>
              <?php endif; ?>
            </td>
            <td><code><?= htmlspecialchars($r['uuid'], ENT_QUOTES) ?></code></td>
            <td>
              <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm btn-view-qr" data-uuid="<?= htmlspecialchars($r['uuid'], ENT_QUOTES) ?>" data-name="<?= htmlspecialchars($r['first_name'].' '.$r['last_name'], ENT_QUOTES) ?>">View QR</button>
                <button type="button" class="btn btn-outline-success btn-sm btn-send-email" data-id="<?= (int)$r['id'] ?>" data-name="<?= htmlspecialchars($r['first_name'].' '.$r['last_name'], ENT_QUOTES) ?>" data-email="<?= htmlspecialchars($displayEmail, ENT_QUOTES) ?>" <?= $displayEmail === '' ? 'disabled' : '' ?>>Send Email</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-2">
    <a class="btn btn-outline-secondary btn-sm" href="?r=export_registrants_csv&q=<?= urlencode($q??'') ?>&agency=<?= urlencode($agency??'') ?>&sector=<?= urlencode($sector??'') ?>">Quick Export Current View</a>
  </div>
  <nav>
    <ul class="pagination">
      <?php for ($p=1; $p<=($pages??1); $p++): ?>
        <li class="page-item <?= $p==($page??1)?'active':'' ?>"><a class="page-link" href="?r=admin_registrants&page=<?= $p ?>&q=<?= urlencode($q??'') ?>&agency=<?= urlencode($agency??'') ?>&sector=<?= urlencode($sector??'') ?>"><?= $p ?></a></li>
      <?php endfor; ?>
    </ul>
  </nav>
</div>

<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="qrModalTitle">QR Code</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img id="qrPreviewImage" src="" alt="QR code" class="img-fluid" style="max-width:320px">
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="emailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="?r=admin_registrant_email">
      <div class="modal-header">
        <h5 class="modal-title">Send QR via Email</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <input type="hidden" name="participant_id" id="emailParticipantId">
        <div class="mb-3">
          <label class="form-label">Recipient</label>
          <input type="text" class="form-control" id="emailParticipantAddress" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Message</label>
          <textarea name="message" id="emailMessage" class="form-control" rows="4"><?= htmlspecialchars($defaultMessage, ENT_QUOTES) ?></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Send Email</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const form = document.querySelector('form[action="."]');
  const btn = document.getElementById('searchBtn');
  const spin = btn?.querySelector('.spinner-border');
  if (form && btn && spin) {
    form.addEventListener('submit', ()=>{ spin.style.display='inline-block'; btn.setAttribute('disabled','disabled'); });
  }
})();

document.addEventListener('DOMContentLoaded', () => {
  const qrModalEl = document.getElementById('qrModal');
  const qrModal = new bootstrap.Modal(qrModalEl);
  const qrImage = document.getElementById('qrPreviewImage');
  document.querySelectorAll('.btn-view-qr').forEach(btn => {
    btn.addEventListener('click', () => {
      const uuid = btn.getAttribute('data-uuid');
      const name = btn.getAttribute('data-name');
      document.getElementById('qrModalTitle').textContent = `QR for ${name}`;
      qrImage.src = '?r=admin_qr&uuid=' + encodeURIComponent(uuid) + '&t=' + Date.now();
      qrModal.show();
    });
  });

  const emailModalEl = document.getElementById('emailModal');
  const emailModal = new bootstrap.Modal(emailModalEl);
  const emailId = document.getElementById('emailParticipantId');
  const emailAddress = document.getElementById('emailParticipantAddress');
  const emailMessage = document.getElementById('emailMessage');
  document.querySelectorAll('.btn-send-email').forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.hasAttribute('disabled')) return;
      emailId.value = btn.getAttribute('data-id');
      emailAddress.value = btn.getAttribute('data-email');
      emailMessage.value = <?= json_encode($defaultMessage) ?>;
      emailModal.show();
    });
  });
});
</script>
</body>
</html>