<?php
declare(strict_types=1);

$guestTitle = $guestTitle ?? 'GovNet-Launching';
$guestBodyClass = $guestBodyClass ?? 'guest-page';
$guestIncludeRegistrationAssets = $guestIncludeRegistrationAssets ?? false;
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#5c6cf2">
  <title><?= htmlspecialchars($guestTitle, ENT_QUOTES) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
  <?php if ($guestIncludeRegistrationAssets): ?>
  <link href="assets/guest-registration.css" rel="stylesheet">
  <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($guestBodyClass, ENT_QUOTES) ?>">
<?php require __DIR__ . DIRECTORY_SEPARATOR . 'guest_nav.php'; ?>
