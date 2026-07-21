<?php
declare(strict_types=1);

$guestIncludeRegistrationJs = $guestIncludeRegistrationJs ?? false;
$supportEmail = function_exists('env') ? env('SUPPORT_EMAIL', 'support@example.com') : 'support@example.com';
?>
<footer class="guest-footer">
  <div class="container guest-container text-center">
    <p class="guest-footer-text mb-0">
      Need help? <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES) ?>">Contact support</a>
    </p>
  </div>
</footer>
<?php if ($guestIncludeRegistrationJs): ?>
<script src="assets/guest-registration.js" defer></script>
<?php endif; ?>
</body>
</html>
