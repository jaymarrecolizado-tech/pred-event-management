<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Export</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<?php $activeNav = 'admin_export'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5">Export</h1>
    <div class="btn-group">
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_registrants">Registrants</a>
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_attendance">Attendance</a>
    </div>
  </div>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="?r=register">Home</a></li>
      <li class="breadcrumb-item"><a href="?r=admin_registrants">Admin</a></li>
      <li class="breadcrumb-item active" aria-current="page">Export</li>
    </ol>
  </nav>
  <div class="row g-3">
    <div class="col-12 col-md-4">
      <div class="card"><div class="card-body">
        <h2 class="h6">Registrants CSV</h2>
        <p class="text-muted">Google Sheets-compatible headers.</p>
        <a class="btn btn-primary" href="?r=export_registrants_csv">Download</a>
      </div></div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card"><div class="card-body">
        <h2 class="h6">Attendance CSV</h2>
        <p class="text-muted">Includes signature paths.</p>
        <a class="btn btn-primary" href="?r=export_attendance_csv">Download</a>
      </div></div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card"><div class="card-body">
        <h2 class="h6">Sample CSV Template</h2>
        <p class="text-muted">Headers for registrants import.</p>
        <a class="btn btn-outline-secondary" href="?r=sample_csv">Download Template</a>
      </div></div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card"><div class="card-body">
        <h2 class="h6">Signed attendees CSV</h2>
        <p class="text-muted mb-2">Registered guests who already signed (present + signature).</p>
        <form class="vstack gap-2" method="get" action="">
          <input type="hidden" name="r" value="export_signed_csv">
          <label class="form-label small mb-0">Attendance date (optional)</label>
          <input type="date" name="date" class="form-control" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES) ?>">
          <button class="btn btn-primary" type="submit">Download signed</button>
          <a class="btn btn-outline-secondary btn-sm" href="?r=export_signed_csv">Download all signed (any day)</a>
        </form>
      </div></div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card"><div class="card-body">
        <h2 class="h6">Not yet signed CSV</h2>
        <p class="text-muted mb-2">Registered guests who have not signed yet.</p>
        <form class="vstack gap-2" method="get" action="">
          <input type="hidden" name="r" value="export_unsigned_csv">
          <label class="form-label small mb-0">For this date (optional)</label>
          <input type="date" name="date" class="form-control" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES) ?>">
          <button class="btn btn-warning" type="submit">Download not signed on date</button>
          <a class="btn btn-outline-secondary btn-sm" href="?r=export_unsigned_csv">Download never signed</a>
        </form>
      </div></div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card"><div class="card-body">
        <h2 class="h6">Certificate of Appearance</h2>
        <p class="text-muted">Email CoA PDFs to present + signed guests.</p>
        <a class="btn btn-primary" href="?r=admin_coa">Open Certificates</a>
      </div></div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card"><div class="card-body">
        <h2 class="h6">Attendance Report (PDF/HTML)</h2>
        <p class="text-muted">Design header and choose fields.</p>
        <a class="btn btn-primary" href="?r=admin_report">Open Report Builder</a>
      </div></div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card"><div class="card-body">
        <h2 class="h6">Quick Attendance PDF</h2>
        <p class="text-muted">Simple layout with signature thumbnails.</p>
        <div class="d-flex gap-2">
          <a class="btn btn-outline-primary" href="?r=export_attendance_pdf">Export PDF (Inline)</a>
          <a class="btn btn-success" href="?r=export_attendance_pdf&download=1">Download PDF</a>
        </div>
      </div></div>
    </div>
  </div>
</div>
</body>
</html>