<?php
declare(strict_types=1);

use App\Services\AuthService;

$role = AuthService::role() ?? 'admin';

$adminNavLinks = [
    ['label' => 'SEO Dashboard', 'route' => 'admin_seo_dashboard', 'roles' => ['admin', 'seo_viewer']],
    ['label' => 'Register', 'route' => 'register', 'roles' => ['admin', 'checker']],
    ['label' => 'Registrants', 'route' => 'admin_registrants', 'roles' => ['admin', 'checker']],
    ['label' => 'Attendance', 'route' => 'admin_attendance', 'roles' => ['admin', 'checker']],
    ['label' => 'Scan', 'route' => 'scan', 'roles' => ['admin', 'checker']],
    ['label' => 'Gallery', 'route' => 'admin_attendance_gallery', 'roles' => ['admin']],
    ['label' => 'Events', 'route' => 'admin_events', 'roles' => ['admin']],
    ['label' => 'Import', 'route' => 'admin_import', 'roles' => ['admin']],
    ['label' => 'Export', 'route' => 'admin_export', 'roles' => ['admin']],
    ['label' => 'Certificates', 'route' => 'admin_coa', 'roles' => ['admin']],
    ['label' => 'Report', 'route' => 'admin_report', 'roles' => ['admin']],
    ['label' => 'Users', 'route' => 'admin_users', 'roles' => ['admin']],
    ['label' => 'Logs', 'route' => 'admin_logs', 'roles' => ['admin']],
    ['label' => 'Settings', 'route' => 'admin_settings', 'roles' => ['admin']],
];

$adminNavLinks = array_values(array_filter(
    $adminNavLinks,
    static fn(array $link): bool => in_array($role, $link['roles'], true)
));

$current = $activeNav ?? '';
$homeRoute = AuthService::loginHomeRoute($role);
$roleLabel = AuthService::roleLabel($role);
$displayName = AuthService::displayName() ?? 'Staff';
?>
<nav class="navbar navbar-expand-lg navbar-dark py-3">
  <div class="container">
    <a class="navbar-brand" href="?r=<?= htmlspecialchars($homeRoute, ENT_QUOTES) ?>">DICT AI ROADSHOW 2026</a>
    <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
      <span class="badge text-bg-light text-dark"><?= htmlspecialchars($roleLabel, ENT_QUOTES) ?></span>
      <span class="text-white-50 small d-none d-md-inline"><?= htmlspecialchars($displayName, ENT_QUOTES) ?></span>
      <?php foreach ($adminNavLinks as $link): ?>
        <?php
          $isActive = $current === $link['route'];
          $classes = $isActive ? 'btn btn-light btn-sm px-3 text-dark shadow-sm' : 'btn btn-outline-light btn-sm px-3';
        ?>
        <a class="<?= $classes ?>" href="?r=<?= htmlspecialchars($link['route'], ENT_QUOTES) ?>"><?= htmlspecialchars($link['label'], ENT_QUOTES) ?></a>
      <?php endforeach; ?>
      <a class="btn btn-outline-light btn-sm px-3" href="?r=admin_logout">Logout</a>
    </div>
  </div>
</nav>
