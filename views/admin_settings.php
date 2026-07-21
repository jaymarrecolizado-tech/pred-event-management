<?php
declare(strict_types=1);

$token = function_exists('csrf_token') ? csrf_token() : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<?php $activeNav = 'admin_settings'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-3" style="max-width:720px">
  <h1 class="h5 mb-3">SMTP Settings</h1>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="?r=register">Home</a></li>
      <li class="breadcrumb-item"><a href="?r=admin_registrants">Admin</a></li>
      <li class="breadcrumb-item active" aria-current="page">Settings</li>
    </ol>
  </nav>
  <form method="post" action="?r=admin_settings_save" class="row g-3">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
    <div class="col-12 col-md-6">
      <label class="form-label">SMTP Host</label>
      <input name="SMTP_HOST" class="form-control" value="<?= htmlspecialchars($env['SMTP_HOST']??'', ENT_QUOTES) ?>" required>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label">SMTP Port</label>
      <input name="SMTP_PORT" class="form-control" value="<?= htmlspecialchars($env['SMTP_PORT']??'', ENT_QUOTES) ?>" required>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label">SMTP User</label>
      <input name="SMTP_USER" class="form-control" value="<?= htmlspecialchars($env['SMTP_USER']??'', ENT_QUOTES) ?>" required>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label">SMTP Password</label>
      <input name="SMTP_PASS" type="password" class="form-control" placeholder="(unchanged if empty)">
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label">Security</label>
      <select name="SMTP_SECURE" class="form-select">
        <option value="ssl" <?= (($env['SMTP_SECURE']??'')==='ssl')?'selected':'' ?>>SSL</option>
        <option value="tls" <?= (($env['SMTP_SECURE']??'')==='tls')?'selected':'' ?>>TLS</option>
      </select>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label">From Email</label>
      <input name="SMTP_FROM" class="form-control" value="<?= htmlspecialchars($env['SMTP_FROM']??'', ENT_QUOTES) ?>">
    </div>
    <div class="col-12">
      <button class="btn btn-primary">Save</button>
      <a class="btn btn-outline-secondary ms-2" href="?r=admin_events">Manage Events</a>
    </div>
  </form>
</div>
</body>
</html>