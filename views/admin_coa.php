<?php
declare(strict_types=1);

use App\Services\CertificateOfAppearanceService as Coa;

$token = function_exists('csrf_token') ? csrf_token() : '';
$guestCount = $load ? count($recipients) : 0;
$hasSignatories = !empty($signatories);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Certificate of Appearance</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
  <style>
    .coa-step {
      border: 1px solid rgba(29, 27, 58, 0.08);
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 8px 24px rgba(29, 27, 58, 0.06);
      margin-bottom: 1rem;
      overflow: hidden;
    }
    .coa-step-head {
      display: flex;
      gap: 0.85rem;
      align-items: flex-start;
      padding: 1rem 1.15rem 0.85rem;
      border-bottom: 1px solid rgba(29, 27, 58, 0.06);
      background: #fafbff;
    }
    .coa-step-num {
      flex: 0 0 auto;
      width: 2rem;
      height: 2rem;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.95rem;
      color: #fff;
      background: var(--brand-primary, #5c6cf2);
    }
    .coa-step-body { padding: 1rem 1.15rem 1.15rem; }
    .coa-hint { color: #5b617a; font-size: 0.875rem; margin: 0.15rem 0 0; }
    .coa-field-help { color: #6c738a; font-size: 0.8rem; margin-top: 0.35rem; }
    .coa-group-title {
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #6c738a;
      margin-bottom: 0.65rem;
    }
    .coa-panel {
      border: 1px solid rgba(29, 27, 58, 0.08);
      border-radius: 12px;
      padding: 0.9rem;
      height: 100%;
      background: #fcfcff;
    }
    .coa-meal-types {
      border-top: 1px dashed rgba(29, 27, 58, 0.12);
      margin-top: 0.75rem;
      padding-top: 0.75rem;
    }
    .coa-checklist li { margin-bottom: 0.25rem; }
    .coa-send-bar {
      position: sticky;
      bottom: 0;
      z-index: 20;
      background: rgba(255,255,255,0.96);
      border-top: 1px solid rgba(29, 27, 58, 0.1);
      box-shadow: 0 -8px 24px rgba(29, 27, 58, 0.08);
      padding: 0.85rem 0;
      margin-top: 0.5rem;
    }
    .coa-muted-card { opacity: 0.72; }
  </style>
</head>
<body>
<?php $activeNav = 'admin_coa'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-1">Certificate of Appearance</h1>
      <p class="text-muted mb-0">Follow the 4 steps below. Each guest gets a PDF emailed to them (2 copies on one landscape page).</p>
    </div>
    <div class="btn-group">
      <a class="btn btn-outline-primary btn-sm" href="?r=admin_coa_monitor">Send history</a>
      <a class="btn btn-outline-secondary btn-sm" href="?r=admin_coa_signatories">Signatories</a>
    </div>
  </div>

  <?php if (!empty($flash)): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info', ENT_QUOTES) ?>">
      <?= htmlspecialchars($flash['message'] ?? '', ENT_QUOTES) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($monitorSummary) && ((int)$monitorSummary['sent'] + (int)$monitorSummary['failed'] + (int)$monitorSummary['pending']) > 0): ?>
  <div class="row g-2 mb-3">
    <div class="col-4">
      <a href="?r=admin_coa_monitor" class="card text-decoration-none border-0 shadow-sm h-100">
        <div class="card-body py-2">
          <div class="small text-muted">Sent</div>
          <div class="h5 mb-0 text-success"><?= (int)$monitorSummary['sent'] ?></div>
        </div>
      </a>
    </div>
    <div class="col-4">
      <a href="?r=admin_coa_monitor&amp;status=failed" class="card text-decoration-none border-0 shadow-sm h-100">
        <div class="card-body py-2">
          <div class="small text-muted">Failed</div>
          <div class="h5 mb-0 text-danger"><?= (int)$monitorSummary['failed'] ?></div>
        </div>
      </a>
    </div>
    <div class="col-4">
      <a href="?r=admin_coa_monitor&amp;status=pending" class="card text-decoration-none border-0 shadow-sm h-100">
        <div class="card-body py-2">
          <div class="small text-muted">Queued to resend</div>
          <div class="h5 mb-0 text-warning"><?= (int)$monitorSummary['pending'] ?></div>
        </div>
      </a>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($lastResult)): ?>
    <div class="alert alert-secondary">
      <div><strong>Last batch #<?= (int)$lastResult['batch_id'] ?></strong>:
        <?= (int)$lastResult['sent'] ?> sent,
        <?= (int)$lastResult['failed'] ?> failed,
        <?= (int)$lastResult['skipped'] ?> skipped
      </div>
      <?php if (!empty($lastResult['failures'])): ?>
        <ul class="mb-0 mt-2 small">
          <?php foreach (array_slice($lastResult['failures'], 0, 30) as $f): ?>
            <li><?= htmlspecialchars((string)$f, ENT_QUOTES) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!$hasSignatories): ?>
    <div class="alert alert-warning">
      <strong>Action needed:</strong> Add at least one signatory with an e-signature image before you can send certificates.
      <a href="?r=admin_coa_signatories" class="alert-link">Manage signatories</a>
    </div>
  <?php endif; ?>

  <!-- STEP 1 -->
  <section class="coa-step" id="step1">
    <div class="coa-step-head">
      <span class="coa-step-num">1</span>
      <div>
        <h2 class="h6 mb-1">Choose who gets a certificate</h2>
        <p class="coa-hint">Only guests who were marked <strong>present</strong> and have a <strong>signature</strong> can receive a CoA.</p>
      </div>
    </div>
    <div class="coa-step-body">
      <form method="get" class="row g-3 align-items-end">
        <input type="hidden" name="r" value="admin_coa">
        <input type="hidden" name="load" value="1">
        <div class="col-md-4">
          <label class="form-label" for="event_id">Event</label>
          <select name="event_id" id="event_id" class="form-select">
            <option value="">All events</option>
            <?php foreach ($events as $ev): ?>
              <option value="<?= (int)$ev['id'] ?>" <?= ($eventId !== null && (int)$eventId === (int)$ev['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars((string)$ev['name'], ENT_QUOTES) ?><?= !empty($ev['active']) ? ' (active)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="coa-field-help">Usually keep the active event selected.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="date_from">Attendance from</label>
          <input type="date" name="date_from" id="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label" for="date_to">Attendance to</label>
          <input type="date" name="date_to" id="date_to" class="form-control" value="<?= htmlspecialchars($dateTo, ENT_QUOTES) ?>">
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100">Find guests</button>
        </div>
      </form>
      <?php if ($load): ?>
        <div class="alert <?= $guestCount > 0 ? 'alert-success' : 'alert-info' ?> mt-3 mb-0 py-2">
          <?php if ($guestCount > 0): ?>
            Found <strong><?= $guestCount ?></strong> eligible guest<?= $guestCount === 1 ? '' : 's' ?>. Continue to steps 2–4 below.
          <?php else: ?>
            No eligible guests for this filter. Check attendance dates, or confirm guests have signed in as present.
          <?php endif; ?>
        </div>
      <?php else: ?>
        <p class="coa-field-help mt-3 mb-0">Click <strong>Find guests</strong> first. Steps 2–4 unlock after guests are loaded.</p>
      <?php endif; ?>
    </div>
  </section>

  <form method="post" id="coaSendForm" class="<?= $load && $guestCount > 0 ? '' : 'coa-muted-card' ?>">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
    <input type="hidden" name="event_id" value="<?= $eventId !== null ? (int)$eventId : '' ?>">
    <input type="hidden" name="date_from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES) ?>">
    <input type="hidden" name="date_to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES) ?>">

    <!-- STEP 2 -->
    <section class="coa-step" id="step2">
      <div class="coa-step-head">
        <span class="coa-step-num">2</span>
        <div>
          <h2 class="h6 mb-1">Fill in the certificate details</h2>
          <p class="coa-hint">These words appear on every certificate. Write them exactly as they should print.</p>
        </div>
      </div>
      <div class="coa-step-body">
        <div class="row g-3">
          <div class="col-12">
            <div class="coa-group-title">Where &amp; when they appeared</div>
          </div>
          <div class="col-md-7">
            <label class="form-label" for="venue">Venue <span class="text-danger">*</span></label>
            <input name="venue" id="venue" class="form-control" required
                   value="<?= htmlspecialchars($venue, ENT_QUOTES) ?>"
                   placeholder="e.g. DICT Regional Office 2"
                   <?= $load && $guestCount > 0 ? '' : 'readonly' ?>>
            <div class="coa-field-help">Printed as: appeared at <em>…</em></div>
          </div>
          <div class="col-md-5">
            <label class="form-label" for="inclusive_dates">Inclusive dates <span class="text-danger">*</span></label>
            <input name="inclusive_dates" id="inclusive_dates" class="form-control" required
                   value="<?= htmlspecialchars($inclusiveDates, ENT_QUOTES) ?>"
                   placeholder="July 21-22, 2026"
                   <?= $load && $guestCount > 0 ? '' : 'readonly' ?>>
            <div class="coa-field-help">Use the readable format shown on the certificate (not 2026-07-21).</div>
          </div>
          <div class="col-12">
            <label class="form-label" for="purpose">Purpose of appearance <span class="text-danger">*</span></label>
            <textarea name="purpose" id="purpose" class="form-control" rows="2" required
                      placeholder="e.g. attending the DICT AI ROADSHOW 2026"
                      <?= $load && $guestCount > 0 ? '' : 'readonly' ?>><?= htmlspecialchars($purpose, ENT_QUOTES) ?></textarea>
            <div class="coa-field-help">Printed as: for the purpose of <em>…</em></div>
          </div>

          <div class="col-12 pt-1">
            <div class="coa-group-title">Who signs &amp; issue date</div>
          </div>
          <div class="col-md-7">
            <label class="form-label" for="signatory_id">Signatory <span class="text-danger">*</span></label>
            <select name="signatory_id" id="signatory_id" class="form-select" required <?= $hasSignatories && $load && $guestCount > 0 ? '' : 'disabled' ?>>
              <option value="">Select the person who will sign…</option>
              <?php foreach ($signatories as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['full_name'] . ' — ' . $s['title'], ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="coa-field-help">Their name, title, and e-signature appear at the bottom of the CoA.</div>
          </div>
          <div class="col-md-5">
            <label class="form-label" for="issue_date">Issue date <span class="text-danger">*</span></label>
            <input type="date" name="issue_date" id="issue_date" class="form-control" required
                   value="<?= htmlspecialchars($issueDate, ENT_QUOTES) ?>"
                   <?= $load && $guestCount > 0 ? '' : 'readonly' ?>>
            <div class="coa-field-help">Printed as: Issued this <em>21st day of July 2026</em>.</div>
          </div>
        </div>
      </div>
    </section>

    <!-- STEP 3 -->
    <section class="coa-step" id="step3">
      <div class="coa-step-head">
        <span class="coa-step-num">3</span>
        <div>
          <h2 class="h6 mb-1">What did this office provide?</h2>
          <p class="coa-hint">These become the three boxes under <strong>Particulars</strong> on the certificate. Defaults apply to everyone unless you customize one guest in Step 4.</p>
        </div>
      </div>
      <div class="coa-step-body">
        <fieldset <?= $load && $guestCount > 0 ? '' : 'disabled' ?>>
          <div class="row g-3">
            <div class="col-md-4">
              <div class="coa-panel">
                <label class="form-label" for="default_lodging">Hotel / lodging</label>
                <select name="default_lodging" class="form-select" id="default_lodging">
                  <option value="not_provided" selected>Did not provide</option>
                  <option value="provided">Provided</option>
                </select>
                <div class="coa-field-help">Own box on the CoA for lodging only.</div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="coa-panel">
                <label class="form-label" for="default_meals">Food and meals</label>
                <select name="default_meals" class="form-select" id="default_meals">
                  <option value="not_provided" selected>Did not provide</option>
                  <option value="provided">Provided</option>
                </select>
                <div class="coa-field-help">Own box on the CoA, separate from lodging.</div>
                <div id="defaultMealTypes" class="coa-meal-types d-none">
                  <div class="small fw-semibold mb-1">Which meals? <span class="text-muted fw-normal">(check all that apply)</span></div>
                  <div class="form-check"><input class="form-check-input meal-type" type="checkbox" name="default_meal_breakfast" id="dmb"><label class="form-check-label" for="dmb">Breakfast</label></div>
                  <div class="form-check"><input class="form-check-input meal-type" type="checkbox" name="default_meal_lunch" id="dml"><label class="form-check-label" for="dml">Lunch</label></div>
                  <div class="form-check"><input class="form-check-input meal-type" type="checkbox" name="default_meal_dinner" id="dmd"><label class="form-check-label" for="dmd">Dinner</label></div>
                  <div id="mealTypeHint" class="coa-field-help text-danger d-none">Select at least one meal, or switch back to “Did not provide”.</div>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="coa-panel">
                <label class="form-label" for="default_vehicle">Vehicle</label>
                <select name="default_vehicle" id="default_vehicle" class="form-select">
                  <option value="not_provided" selected>Did not provide</option>
                  <option value="provided">Provided</option>
                </select>
                <div class="coa-field-help">Own box on the CoA for vehicle support.</div>
              </div>
            </div>
          </div>
        </fieldset>
      </div>
    </section>

    <!-- STEP 4 -->
    <section class="coa-step" id="step4">
      <div class="coa-step-head">
        <span class="coa-step-num">4</span>
        <div>
          <h2 class="h6 mb-1">Review guests, preview, then send</h2>
          <p class="coa-hint">Uncheck anyone who should not receive email. Use <strong>Preview</strong> to open the PDF first. Use <strong>Different for this guest</strong> only when one person needs different particulars.</p>
        </div>
      </div>
      <div class="coa-step-body">
        <?php if ($load && $recipients): ?>
          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div class="small text-muted"><?= $guestCount ?> guest<?= $guestCount === 1 ? '' : 's' ?> loaded</div>
            <div class="btn-group btn-group-sm">
              <button type="button" class="btn btn-outline-secondary" id="selectAll">Select all</button>
              <button type="button" class="btn btn-outline-secondary" id="selectNone">Select none</button>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th scope="col" title="Include in this send">Send?</th>
                  <th scope="col">Guest</th>
                  <th scope="col">Agency</th>
                  <th scope="col">Email</th>
                  <th scope="col">Attendance dates</th>
                  <th scope="col">Particulars</th>
                  <th scope="col" title="Skip email even if selected">Skip</th>
                  <th scope="col"></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($recipients as $r): ?>
                <?php
                  $pid = (int)$r['id'];
                  $email = Coa::resolveEmail($r);
                  $name = Coa::fullName($r);
                ?>
                <tr class="<?= $email ? '' : 'table-warning' ?>">
                  <td>
                    <input type="checkbox" class="form-check-input recip" name="recipient_ids[]" value="<?= $pid ?>" <?= $email ? 'checked' : '' ?>
                           aria-label="Send to <?= htmlspecialchars($name, ENT_QUOTES) ?>">
                  </td>
                  <td><?= htmlspecialchars($name, ENT_QUOTES) ?></td>
                  <td><?= htmlspecialchars((string)($r['agency'] ?? ''), ENT_QUOTES) ?></td>
                  <td class="<?= $email ? '' : 'text-danger' ?>">
                    <?= htmlspecialchars($email ?? 'No email on record', ENT_QUOTES) ?>
                    <?php if (!$email): ?><div class="small">Cannot send — add email first.</div><?php endif; ?>
                  </td>
                  <td class="small"><?= htmlspecialchars((string)($r['attendance_dates'] ?? ''), ENT_QUOTES) ?></td>
                  <td>
                    <div class="form-check">
                      <input class="form-check-input ov-toggle" type="checkbox" name="override_<?= $pid ?>_enabled" id="ov<?= $pid ?>" data-target="ovpanel<?= $pid ?>">
                      <label class="form-check-label small" for="ov<?= $pid ?>">Different for this guest</label>
                    </div>
                    <div id="ovpanel<?= $pid ?>" class="border rounded p-2 mt-1 small d-none" style="min-width:230px">
                      <label class="form-label mb-0">Lodging</label>
                      <select name="override_<?= $pid ?>_lodging" class="form-select form-select-sm mb-1">
                        <option value="not_provided">Did not provide</option>
                        <option value="provided">Provided</option>
                      </select>
                      <label class="form-label mb-0">Meals</label>
                      <select name="override_<?= $pid ?>_meals" class="form-select form-select-sm mb-1 ov-meals" data-types="ovmeals<?= $pid ?>">
                        <option value="not_provided">Did not provide</option>
                        <option value="provided">Provided</option>
                      </select>
                      <div id="ovmeals<?= $pid ?>" class="d-none mb-1">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="override_<?= $pid ?>_meal_breakfast" id="ob<?= $pid ?>"><label class="form-check-label" for="ob<?= $pid ?>">Breakfast</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="override_<?= $pid ?>_meal_lunch" id="ol<?= $pid ?>"><label class="form-check-label" for="ol<?= $pid ?>">Lunch</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="override_<?= $pid ?>_meal_dinner" id="od<?= $pid ?>"><label class="form-check-label" for="od<?= $pid ?>">Dinner</label></div>
                      </div>
                      <label class="form-label mb-0 mt-1">Vehicle</label>
                      <select name="override_<?= $pid ?>_vehicle" class="form-select form-select-sm">
                        <option value="not_provided">Did not provide</option>
                        <option value="provided">Provided</option>
                      </select>
                    </div>
                  </td>
                  <td>
                    <input type="checkbox" class="form-check-input" name="skip_ids[]" value="<?= $pid ?>"
                           aria-label="Skip <?= htmlspecialchars($name, ENT_QUOTES) ?>">
                  </td>
                  <td>
                    <button type="submit" class="btn btn-sm btn-outline-primary preview-btn"
                            formaction="?r=admin_coa_preview" formtarget="_blank"
                            name="participant_id" value="<?= $pid ?>">Preview PDF</button>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="row g-3 mt-1">
            <div class="col-lg-7">
              <div class="coa-panel">
                <div class="coa-group-title mb-2">Quick check before sending</div>
                <ul class="coa-checklist small mb-0 ps-3">
                  <li>Venue, dates, purpose, and signatory are filled in</li>
                  <li>Lodging / meals / vehicle match what was actually provided</li>
                  <li>If meals = Provided, at least one of Breakfast / Lunch / Dinner is checked</li>
                  <li>Preview at least one PDF to confirm layout</li>
                  <li>Guests without email will not receive a certificate</li>
                </ul>
              </div>
            </div>
            <div class="col-lg-5">
              <div class="alert alert-light border mb-0">
                <div class="fw-semibold mb-1">Ready to email?</div>
                <p class="small mb-2">This sends the landscape CoA (2 copies on 1 page) to each selected guest’s email.</p>
                <button type="submit" class="btn btn-success w-100" id="sendBtn"
                        formaction="?r=admin_coa_send"
                        <?= !$hasSignatories ? 'disabled' : '' ?>>
                  Send certificates to selected
                </button>
              </div>
            </div>
          </div>
        <?php elseif ($load): ?>
          <div class="alert alert-info mb-0">No present + signed guests match this filter. Go back to Step 1 and adjust the dates or event.</div>
        <?php else: ?>
          <div class="alert alert-secondary mb-0">Complete Step 1 first — find eligible guests, then you can preview and send here.</div>
        <?php endif; ?>
      </div>
    </section>
  </form>
</div>
<script>
(() => {
  const mealsSelect = document.getElementById('default_meals');
  const mealTypes = document.getElementById('defaultMealTypes');
  const mealHint = document.getElementById('mealTypeHint');

  const syncDefaultMeals = () => {
    if (!mealsSelect || !mealTypes) return;
    const provided = mealsSelect.value === 'provided';
    mealTypes.classList.toggle('d-none', !provided);
    mealTypes.querySelectorAll('input').forEach(el => {
      el.disabled = !provided;
      if (!provided) el.checked = false;
    });
    if (mealHint) mealHint.classList.add('d-none');
  };

  const mealsOk = () => {
    if (!mealsSelect || mealsSelect.value !== 'provided') return true;
    const any = [...document.querySelectorAll('#defaultMealTypes .meal-type')].some(el => el.checked);
    if (mealHint) mealHint.classList.toggle('d-none', any);
    return any;
  };

  mealsSelect?.addEventListener('change', syncDefaultMeals);
  syncDefaultMeals();

  document.querySelectorAll('.ov-toggle').forEach(el => {
    el.addEventListener('change', () => {
      const panel = document.getElementById(el.dataset.target);
      if (panel) panel.classList.toggle('d-none', !el.checked);
    });
  });

  document.querySelectorAll('.ov-meals').forEach(sel => {
    const sync = () => {
      const box = document.getElementById(sel.dataset.types);
      if (!box) return;
      const provided = sel.value === 'provided';
      box.classList.toggle('d-none', !provided);
      box.querySelectorAll('input').forEach(el => {
        el.disabled = !provided;
        if (!provided) el.checked = false;
      });
    };
    sel.addEventListener('change', sync);
    sync();
  });

  document.getElementById('selectAll')?.addEventListener('click', () => {
    document.querySelectorAll('.recip').forEach(el => { el.checked = true; });
  });
  document.getElementById('selectNone')?.addEventListener('click', () => {
    document.querySelectorAll('.recip').forEach(el => { el.checked = false; });
  });

  const form = document.getElementById('coaSendForm');
  form?.addEventListener('submit', (e) => {
    const submitter = e.submitter;
    const isSend = submitter && submitter.id === 'sendBtn';
    const isPreview = submitter && submitter.classList.contains('preview-btn');
    if (!isSend && !isPreview) return;

    if (!document.getElementById('signatory_id')?.value) {
      e.preventDefault();
      alert('Select a signatory in Step 2.');
      document.getElementById('signatory_id')?.focus();
      return;
    }
    if (!mealsOk()) {
      e.preventDefault();
      alert('Meals is set to Provided — check Breakfast, Lunch, and/or Dinner in Step 3.');
      mealsSelect?.focus();
      return;
    }
    if (isSend) {
      const selected = [...document.querySelectorAll('.recip:checked')].length;
      if (selected === 0) {
        e.preventDefault();
        alert('Select at least one guest to send.');
        return;
      }
      if (!confirm('Email certificates to ' + selected + ' selected guest(s)?')) {
        e.preventDefault();
      }
    }
  });
})();
</script>
</body>
</html>
