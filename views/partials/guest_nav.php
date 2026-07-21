<?php
declare(strict_types=1);

use App\Services\AuthService;

$guestShowActions = $guestShowActions ?? true;
$isLoggedIn = AuthService::check();
$dashboardRoute = $isLoggedIn ? AuthService::loginHomeRoute() : 'admin_login';
?>
<nav class="navbar navbar-expand-lg navbar-dark guest-navbar sticky-top">
  <div class="container guest-container">
    <a class="navbar-brand guest-brand" href="?r=register">
      <span>GovNet-Launching</span>
    </a>
    <?php if ($guestShowActions): ?>
    <div class="ms-auto d-flex gap-2 guest-nav-actions">
      <a class="btn btn-outline-light guest-nav-btn" href="?r=scan" title="Scan & Sign">
        <span class="guest-nav-icon d-md-none" aria-hidden="true">&#128247;</span>
        <span class="d-none d-md-inline">Scan &amp; Sign</span>
      </a>
      <?php if ($isLoggedIn): ?>
      <a class="btn btn-outline-light guest-nav-btn" href="?r=<?= htmlspecialchars($dashboardRoute, ENT_QUOTES) ?>" title="Dashboard">
        <span class="guest-nav-icon d-md-none" aria-hidden="true">&#9881;</span>
        <span class="d-none d-md-inline">Dashboard</span>
      </a>
      <a class="btn btn-outline-light guest-nav-btn" href="?r=admin_logout" title="Logout">
        <span class="d-none d-md-inline">Logout</span>
        <span class="guest-nav-icon d-md-none" aria-hidden="true">&#10140;</span>
      </a>
      <?php else: ?>
      <a class="btn btn-outline-light guest-nav-btn" href="?r=admin_login" title="Admin Login">
        <span class="guest-nav-icon d-md-none" aria-hidden="true">&#9881;</span>
        <span class="d-none d-md-inline">Admin</span>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</nav>
