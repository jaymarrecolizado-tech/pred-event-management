<?php
declare(strict_types=1);

$guestIncludeRegistrationJs = $guestIncludeRegistrationJs ?? false;
?>
<footer class="guest-footer">
  <div class="container guest-container text-center">
    <p class="guest-footer-text mb-0">
      Created by: JE Lite of DICT R2
    </p>
  </div>
</footer>
<?php if ($guestIncludeRegistrationJs): ?>
<script src="assets/guest-registration.js" defer></script>
<?php endif; ?>
</body>
</html>
