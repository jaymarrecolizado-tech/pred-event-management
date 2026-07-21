<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Attendance Gallery</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
  <style>.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}</style>
</head>
<body>
<?php $activeNav = 'admin_attendance_gallery'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-3">
  <h1 class="h5">Attendance Gallery</h1>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="?r=register">Home</a></li>
      <li class="breadcrumb-item"><a href="?r=admin_registrants">Admin</a></li>
      <li class="breadcrumb-item"><a href="?r=admin_attendance">Attendance</a></li>
      <li class="breadcrumb-item active" aria-current="page">Gallery</li>
    </ol>
  </nav>
  <form method="get" action="." class="mb-3">
    <input type="hidden" name="r" value="admin_attendance_gallery">
    <div class="row g-2">
      <div class="col-6 col-md-3"><input name="date" value="<?= htmlspecialchars(($date??''), ENT_QUOTES) ?>" placeholder="YYYY-MM-DD" class="form-control"></div>
      <div class="col-6 col-md-3"><input name="agency" value="<?= htmlspecialchars(($agency??''), ENT_QUOTES) ?>" placeholder="Agency" class="form-control"></div>
      <div class="col-12 col-md-3"><button class="btn btn-primary w-100">Filter</button></div>
    </div>
  </form>
  <?php if (!($items??[])): ?>
    <div class="alert alert-secondary">No attendance signatures found</div>
  <?php endif; ?>
  <div class="grid">
    <?php foreach (($items??[]) as $it): ?>
      <div class="card">
        <img src="signature.php?aid=<?= (int)$it['id'] ?>" class="card-img-top" alt="sig">
        <div class="card-body">
          <div class="small mb-1"><?= htmlspecialchars($it['first_name'].' '.$it['last_name'], ENT_QUOTES) ?></div>
          <div class="small text-muted"><?= htmlspecialchars($it['attendance_date'].' '.$it['time_in'], ENT_QUOTES) ?></div>
          <div class="d-flex gap-2 mt-2">
            <a class="btn btn-sm btn-outline-primary" href="signature.php?aid=<?= (int)$it['id'] ?>" download>Download</a>
            <a class="btn btn-sm btn-outline-secondary" href="?r=admin_attendance&date=<?= htmlspecialchars($it['attendance_date'], ENT_QUOTES) ?>&agency=<?= urlencode($it['agency']??'') ?>">View in Table</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <nav class="mt-3">
    <ul class="pagination">
      <?php for ($p=1; $p<=($pages??1); $p++): ?>
        <li class="page-item <?= $p==($page??1)?'active':'' ?>"><a class="page-link" href="?r=admin_attendance_gallery&page=<?= $p ?>&date=<?= urlencode($date??'') ?>&agency=<?= urlencode($agency??'') ?>"><?= $p ?></a></li>
      <?php endfor; ?>
    </ul>
  </nav>
</div>
</body>
</html>