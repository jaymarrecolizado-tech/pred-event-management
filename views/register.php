<?php
declare(strict_types=1);

$token = function_exists('csrf_token') ? csrf_token() : '';
$guestTitle = 'Event Registration — DICT AI ROADSHOW 2026';
$guestIncludeRegistrationAssets = true;
$guestIncludeRegistrationJs = true;
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'guest_head.php';
?>

<main class="guest-main">
  <div class="container guest-container">
    <div class="guest-layout">
      <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'guest_hero.php'; ?>

      <div class="guest-form-wrap">
        <div class="guest-form-card">
          <h2 class="guest-form-title">DICT AI ROADSHOW 2026</h2>
          <p class="guest-form-subtitle">Participant registration — fill each step completely. You can only submit after all required fields are done.</p>

          <div class="guest-stepper" aria-label="Registration progress">
            <div class="guest-stepper-progress" aria-hidden="true">
              <div class="guest-stepper-progress-bar" id="stepperProgress" style="width:33%"></div>
            </div>
            <p class="guest-stepper-label guest-stepper-label-mobile" id="stepperLabel">Step 1 of 3 — Personal details</p>
            <div class="guest-stepper-tabs" role="tablist">
              <button type="button" class="guest-stepper-tab is-active" data-step="1" role="tab" aria-current="step">1 · Personal</button>
              <button type="button" class="guest-stepper-tab" data-step="2" role="tab">2 · Work</button>
              <button type="button" class="guest-stepper-tab" data-step="3" role="tab">3 · Review</button>
            </div>
          </div>

          <div id="stepError" class="guest-step-error" role="alert"></div>

          <form method="post" action="?r=register_submit" id="registrationForm" class="needs-validation" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">

            <!-- Step 1: Personal -->
            <fieldset class="reg-step is-active" data-step="1">
              <legend>Personal details</legend>
              <div class="row g-3 g-md-4">
                <div class="col-12 col-md-6">
                  <label class="form-label" for="first_name">First Name <span class="req" aria-hidden="true">*</span></label>
                  <input name="first_name" id="first_name" class="form-control" required autocomplete="given-name">
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="middle_name">Middle Name</label>
                  <input name="middle_name" id="middle_name" class="form-control" autocomplete="additional-name">
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="last_name">Last Name <span class="req" aria-hidden="true">*</span></label>
                  <input name="last_name" id="last_name" class="form-control" required autocomplete="family-name">
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="nickname">Nickname</label>
                  <input name="nickname" id="nickname" class="form-control" autocomplete="nickname">
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="sex">Sex</label>
                  <select name="sex" id="sex" class="form-select">
                    <option value="">Select</option>
                    <option value="Female">Female</option>
                    <option value="Male">Male</option>
                    <option value="Other">Prefer not to say</option>
                  </select>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="email">Email Address <span class="req" aria-hidden="true">*</span></label>
                  <input name="email" id="email" type="email" class="form-control" required autocomplete="email" inputmode="email">
                  <p class="field-hint">Required — we send your QR code here.</p>
                </div>
              </div>
            </fieldset>

            <!-- Step 2: Work -->
            <fieldset class="reg-step" data-step="2">
              <legend>Work information</legend>
              <div class="row g-3 g-md-4">
                <div class="col-12 col-md-6">
                  <label class="form-label" for="sector">Sector <span class="req" aria-hidden="true">*</span></label>
                  <select name="sector" id="sector" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach (($sectors ?? []) as $s): ?>
                      <option value="<?= htmlspecialchars($s, ENT_QUOTES) ?>"><?= htmlspecialchars($s, ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                    <option value="Other">Other</option>
                  </select>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="agencyPickerBtn">Agency <span class="req" aria-hidden="true">*</span></label>
                  <div class="picker-field">
                    <input name="agency_select" id="agencyInput" class="form-control picker-value-input" list="agencyList" placeholder="Type or tap to search" autocomplete="organization" required>
                    <button type="button" id="agencyPickerBtn" class="picker-trigger is-placeholder" data-placeholder="Tap to select agency" aria-haspopup="listbox" aria-expanded="false" aria-required="true">
                      <span class="picker-trigger-text">Tap to select agency</span>
                      <span class="picker-trigger-chevron" aria-hidden="true">&#9662;</span>
                    </button>
                    <datalist id="agencyList">
                      <?php foreach (($agencies ?? []) as $a): ?>
                        <option value="<?= htmlspecialchars($a['agency'], ENT_QUOTES) ?>"></option>
                      <?php endforeach; ?>
                    </datalist>
                    <input name="agency_other" id="agencyOther" class="form-control mt-2" placeholder="Enter agency name" style="display:none" autocomplete="organization">
                  </div>
                  <p class="field-hint">Search your organization or choose Other if not listed.</p>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="designationPickerBtn">Designation</label>
                  <div class="picker-field">
                    <input name="designation_select" id="designationInput" class="form-control picker-value-input" list="designationList" placeholder="Type or tap to search" autocomplete="organization-title">
                    <button type="button" id="designationPickerBtn" class="picker-trigger is-placeholder" data-placeholder="Tap to select designation" aria-haspopup="listbox">
                      <span class="picker-trigger-text">Tap to select designation</span>
                      <span class="picker-trigger-chevron" aria-hidden="true">&#9662;</span>
                    </button>
                    <datalist id="designationList">
                      <?php foreach (($designations ?? []) as $d): ?>
                        <option value="<?= htmlspecialchars($d['designation'], ENT_QUOTES) ?>"></option>
                      <?php endforeach; ?>
                    </datalist>
                    <input name="designation_other" id="designationOther" class="form-control mt-2" placeholder="Enter designation" style="display:none" autocomplete="organization-title">
                  </div>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="contact_no">Mobile Number <span class="req" aria-hidden="true">*</span></label>
                  <input
                    name="contact_no"
                    id="contact_no"
                    class="form-control"
                    type="tel"
                    inputmode="tel"
                    autocomplete="tel"
                    required
                    placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                    pattern="^(09\d{9}|\+639\d{9}|639\d{9})$"
                    maxlength="13"
                    aria-describedby="contactHint"
                  >
                  <p class="field-hint" id="contactHint">Philippine mobile only — use 09XXXXXXXXX or +639XXXXXXXXX.</p>
                </div>
              </div>
            </fieldset>

            <!-- Step 3: Review & submit -->
            <fieldset class="reg-step" data-step="3">
              <legend>Review and submit</legend>
              <div class="guest-review" aria-label="Registration summary">
                <span class="guest-review-chip"><strong>Name:</strong> <span id="reviewName">—</span></span>
                <span class="guest-review-chip"><strong>Agency:</strong> <span id="reviewAgency">—</span></span>
                <span class="guest-review-chip"><strong>Email:</strong> <span id="reviewEmail">—</span></span>
                <span class="guest-review-chip"><strong>Mobile:</strong> <span id="reviewMobile">—</span></span>
              </div>
              <ul class="guest-checklist" id="submitChecklist" aria-label="Required information checklist">
                <li data-check="name"><span class="check-icon" aria-hidden="true"></span> Full name</li>
                <li data-check="email"><span class="check-icon" aria-hidden="true"></span> Email for QR code</li>
                <li data-check="sector"><span class="check-icon" aria-hidden="true"></span> Sector</li>
                <li data-check="agency"><span class="check-icon" aria-hidden="true"></span> Agency</li>
                <li data-check="mobile"><span class="check-icon" aria-hidden="true"></span> Mobile number</li>
              </ul>
              <p class="guest-submit-hint" id="submitHint">Complete the checklist above before submitting.</p>
            </fieldset>

            <!-- Inline actions (tablet/desktop) -->
            <div class="guest-actions-inline d-none d-md-flex">
              <button type="button" class="btn btn-outline-secondary guest-btn guest-btn-lg guest-btn-back" id="btnBack" style="display:none">Back</button>
              <button type="button" class="btn btn-primary guest-btn guest-btn-lg guest-btn-next" id="btnContinue">
                <span class="btn-continue-label">Next: Work info</span>
              </button>
              <button type="submit" class="btn btn-primary guest-btn guest-btn-lg" id="btnRegister" style="display:none" disabled aria-disabled="true">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                <span class="btn-label">Submit registration</span>
              </button>
            </div>

            <!-- No-JS fallback submit -->
            <div class="guest-submit-fallback mt-4">
              <button type="submit" class="btn btn-primary btn-lg guest-btn w-100">Submit registration</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Sticky mobile actions -->
<div class="guest-actions-sticky" aria-label="Form navigation">
  <button type="button" class="btn btn-outline-secondary guest-btn guest-btn-back" id="btnBackSticky" style="display:none">Back</button>
  <button type="button" class="btn btn-primary guest-btn flex-grow-1" id="btnContinueSticky">
    <span class="btn-continue-label">Next: Work info</span>
  </button>
  <button type="submit" form="registrationForm" class="btn btn-primary guest-btn flex-grow-1" id="btnRegisterSticky" style="display:none" disabled aria-disabled="true">
    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
    <span class="btn-label">Submit registration</span>
  </button>
</div>

<!-- Agency picker sheet -->
<div class="picker-sheet-wrap" id="agencyPickerWrap">
  <div class="picker-sheet-backdrop" aria-hidden="true"></div>
  <div class="picker-sheet" id="agencyPickerSheet" role="dialog" aria-modal="true" aria-labelledby="agencyPickerTitle">
    <button type="button" class="picker-sheet-close" aria-label="Close">&times;</button>
    <div class="picker-sheet-header">
      <h3 class="picker-sheet-title" id="agencyPickerTitle">Select Agency</h3>
      <input type="search" class="picker-sheet-search" placeholder="Search agencies..." aria-label="Search agencies">
    </div>
    <div class="picker-sheet-list" role="listbox"></div>
  </div>
</div>

<!-- Designation picker sheet -->
<div class="picker-sheet-wrap" id="designationPickerWrap">
  <div class="picker-sheet-backdrop" aria-hidden="true"></div>
  <div class="picker-sheet" id="designationPickerSheet" role="dialog" aria-modal="true" aria-labelledby="designationPickerTitle">
    <button type="button" class="picker-sheet-close" aria-label="Close">&times;</button>
    <div class="picker-sheet-header">
      <h3 class="picker-sheet-title" id="designationPickerTitle">Select Designation</h3>
      <input type="search" class="picker-sheet-search" placeholder="Search designations..." aria-label="Search designations">
    </div>
    <div class="picker-sheet-list" role="listbox"></div>
  </div>
</div>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'guest_footer.php'; ?>
