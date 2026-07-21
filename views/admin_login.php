<?php
declare(strict_types=1);

$token = function_exists('csrf_token') ? csrf_token() : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark py-3">
  <div class="container"><a class="navbar-brand" href="?r=register">GovNet-Launching</a></div>
</nav>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-5">
      <div class="card p-4 p-md-5">
        <p class="text-uppercase text-muted mb-2" style="letter-spacing:.3em;font-size:.75rem;">Staff &amp; Admin Portal</p>
        <h1 class="page-heading h3 mb-4">Sign in to manage the event</h1>
        <form method="post" action="?r=admin_login_post" class="needs-validation" novalidate>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input name="username" class="form-control" required>
          </div>
          <div class="mb-4">
            <label class="form-label">Password</label>
            <input name="password" type="password" class="form-control" required>
          </div>
          <button class="btn btn-primary w-100">Login</button>
        </form>
      </div>
    </div>
  </div>
</div>
</body>
</html>