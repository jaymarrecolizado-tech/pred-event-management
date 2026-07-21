<?php
declare(strict_types=1);

$guestTitle = 'Registration Error — GovNet-Launching';
$guestShowActions = false;
$guestIncludeRegistrationAssets = true;
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'guest_head.php';
?>

<main class="guest-main">
  <div class="container guest-container">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-7">
        <div class="glass-panel guest-form-card">
          <div class="guest-error-icon" aria-hidden="true">&#9888;</div>
          <h1 class="guest-form-title h4 mb-3">We couldn&rsquo;t complete your registration</h1>
          <p class="guest-form-subtitle mb-4"><?= htmlspecialchars($error ?? 'An unexpected error occurred.', ENT_QUOTES) ?></p>
          <?php if (!empty($errorsList) && is_array($errorsList)): ?>
            <div class="alert alert-warning" role="alert">
              <ul class="mb-0 ps-3">
                <?php foreach ($errorsList as $message): ?>
                  <li><?= htmlspecialchars($message, ENT_QUOTES) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
            <a class="btn btn-primary guest-btn guest-btn-lg" href="?r=register">Try again</a>
            <a class="btn btn-outline-secondary guest-btn guest-btn-lg" href="mailto:<?= htmlspecialchars(function_exists('env') ? env('SUPPORT_EMAIL', 'support@example.com') : 'support@example.com', ENT_QUOTES) ?>">Contact support</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php
$guestIncludeRegistrationJs = false;
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'guest_footer.php';
