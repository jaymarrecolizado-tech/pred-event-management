<?php
declare(strict_types=1);

$eventBrand = 'DICT AI ROADSHOW 2026';
$bannerSrc = 'assets/dict-ai-roadshow-2026-banner.png';
?>
<aside class="guest-hero" aria-label="Registration guide">
  <div class="guest-hero-mobile d-lg-none">
    <img
      class="guest-hero-banner"
      src="<?= htmlspecialchars($bannerSrc, ENT_QUOTES) ?>"
      alt="<?= htmlspecialchars($eventBrand, ENT_QUOTES) ?>"
      width="1200"
      height="400"
      decoding="async"
    >
    <div class="guest-hero-mobile-copy">
      <p class="guest-hero-mobile-title mb-0"><?= htmlspecialchars($eventBrand, ENT_QUOTES) ?></p>
      <p class="guest-hero-mobile-sub mb-0">One QR for every event day — register once, check in daily</p>
    </div>
  </div>

  <div class="guest-hero-panel d-none d-lg-flex">
    <div class="guest-hero-banner-wrap">
      <img
        class="guest-hero-banner"
        src="<?= htmlspecialchars($bannerSrc, ENT_QUOTES) ?>"
        alt="<?= htmlspecialchars($eventBrand, ENT_QUOTES) ?>"
        width="1200"
        height="400"
        decoding="async"
      >
    </div>
    <div class="guest-hero-content">
      <span class="badge-soft guest-hero-badge">Event Registration</span>
      <h1 class="guest-hero-title"><?= htmlspecialchars($eventBrand, ENT_QUOTES) ?></h1>
      <p class="guest-hero-subtitle">Multi-day event — register once, then use the same QR code each day at the welcome desk.</p>
      <ol class="guest-hero-steps">
        <li>
          <span class="guest-hero-step-num">1</span>
          <span>Fill in your details</span>
        </li>
        <li>
          <span class="guest-hero-step-num">2</span>
          <span>Save your QR (email + download)</span>
        </li>
        <li>
          <span class="guest-hero-step-num">3</span>
          <span>Scan the same QR every day</span>
        </li>
      </ol>
      <p class="guest-hero-tip">
        <strong>Tip:</strong> Use <em>Next</em> to move through steps. <em>Submit registration</em> stays locked until required fields are complete.
      </p>
    </div>
  </div>
</aside>
