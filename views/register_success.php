<?php
declare(strict_types=1);

$guestTitle = 'Registration Complete — DICT AI ROADSHOW 2026';
$guestShowActions = false;
$guestIncludeRegistrationAssets = true;
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'guest_head.php';

$firstName = htmlspecialchars($participant['first_name'] ?? '', ENT_QUOTES);
$qrUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/qrcode.php?uuid=' . urlencode($participant['uuid']);
$emailSent = !empty($_SESSION['registration_email_sent']);
unset($_SESSION['registration_email_sent']);
?>

<main class="guest-main">
  <div class="container guest-container">
    <div class="guest-success-wrap">
      <div class="glass-panel guest-form-card text-center">
        <div class="guest-success-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="11" stroke="#00c9a7" stroke-width="2"/>
            <path d="M7 12.5l3 3 7-7" stroke="#00c9a7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h1 class="guest-form-title mb-2">Welcome<?= $firstName !== '' ? ', ' . $firstName : '' ?>!</h1>
        <p class="guest-form-subtitle mb-4">You are all set. Present this QR code at the welcome desk for fast check-in.</p>

        <?php if ($emailSent): ?>
          <div class="alert alert-success text-start">A copy of your QR code was sent to your registration email. Keep it for every event day.</div>
        <?php else: ?>
          <div class="alert alert-warning text-start">We could not confirm email delivery. Please download your QR code below and save it on your phone for every event day.</div>
        <?php endif; ?>

        <div class="guest-qr-card">
          <img src="<?= htmlspecialchars($qrUrl, ENT_QUOTES) ?>" alt="Your registration QR code" class="img-fluid">
        </div>

        <p class="guest-scan-hint">
          <strong>Multi-day tip:</strong> Use this same QR code on Day 1, Day 2, and any remaining days. You only need to register once.
        </p>

        <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
          <a class="btn btn-primary guest-btn guest-btn-lg" href="<?= htmlspecialchars($qrUrl, ENT_QUOTES) ?>" download="DICT-AI-ROADSHOW-QR.png">Download QR</a>
          <a class="btn btn-outline-secondary guest-btn guest-btn-lg" href="?r=register">Register another</a>
        </div>
      </div>
    </div>
  </div>
</main>

<?php
$guestIncludeRegistrationJs = false;
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'guest_footer.php';
