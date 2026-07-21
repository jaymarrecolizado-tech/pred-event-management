(() => {
  'use strict';

  document.documentElement.classList.remove('no-js');

  const form = document.getElementById('registrationForm');
  if (!form) return;

  const steps = Array.from(form.querySelectorAll('.reg-step'));
  const totalSteps = steps.length;
  let currentStep = 1;

  const stepperProgress = document.getElementById('stepperProgress');
  const stepperLabel = document.getElementById('stepperLabel');
  const stepperTabs = document.querySelectorAll('.guest-stepper-tab');
  const btnBack = document.getElementById('btnBack');
  const btnContinue = document.getElementById('btnContinue');
  const btnRegister = document.getElementById('btnRegister');
  const btnBackSticky = document.getElementById('btnBackSticky');
  const btnContinueSticky = document.getElementById('btnContinueSticky');
  const btnRegisterSticky = document.getElementById('btnRegisterSticky');
  const stepError = document.getElementById('stepError');
  const submitHint = document.getElementById('submitHint');
  const submitChecklist = document.getElementById('submitChecklist');

  const agencyInput = document.getElementById('agencyInput');
  const agencyOther = document.getElementById('agencyOther');
  const agencyPickerBtn = document.getElementById('agencyPickerBtn');
  const designationInput = document.getElementById('designationInput');
  const designationOther = document.getElementById('designationOther');

  const reviewName = document.getElementById('reviewName');
  const reviewAgency = document.getElementById('reviewAgency');
  const reviewEmail = document.getElementById('reviewEmail');
  const reviewMobile = document.getElementById('reviewMobile');

  const CONTINUE_LABELS = {
    1: 'Next: Work info',
    2: 'Next: Review & submit',
  };
  const STEP_NAMES = ['Personal details', 'Work information', 'Review & submit'];
  const PH_MOBILE_RE = /^(09\d{9}|\+639\d{9}|639\d{9})$/;

  function showStepError(message) {
    if (!stepError) return;
    stepError.textContent = message;
    stepError.classList.add('is-visible', 'alert', 'alert-warning');
  }

  function hideStepError() {
    if (!stepError) return;
    stepError.textContent = '';
    stepError.classList.remove('is-visible', 'alert', 'alert-warning');
  }

  function normalizePhMobileInput(raw) {
    return String(raw || '').replace(/[\s\-().]/g, '').trim();
  }

  function isValidPhMobile(raw) {
    return PH_MOBILE_RE.test(normalizePhMobileInput(raw));
  }

  function validatePickerField(input, otherInput) {
    const val = (input?.value || '').trim();
    if (!val) return false;
    if (val === 'other') return !!(otherInput?.value || '').trim();
    return true;
  }

  function getAgencyValue() {
    const val = (agencyInput?.value || '').trim();
    if (val === 'other') return (agencyOther?.value || '').trim();
    return val;
  }

  function getRequiredStatus() {
    const first = (form.querySelector('[name="first_name"]')?.value || '').trim();
    const last = (form.querySelector('[name="last_name"]')?.value || '').trim();
    const emailEl = form.querySelector('[name="email"]');
    const email = (emailEl?.value || '').trim();
    const sector = (form.querySelector('[name="sector"]')?.value || '').trim();
    const agency = getAgencyValue();
    const mobile = form.querySelector('[name="contact_no"]')?.value || '';
    const emailOk = !!email && (!emailEl || emailEl.checkValidity());

    return {
      name: first !== '' && last !== '',
      email: emailOk,
      sector: sector !== '',
      agency: agency !== '',
      mobile: isValidPhMobile(mobile),
    };
  }

  function isFormReadyToSubmit() {
    const s = getRequiredStatus();
    return s.name && s.email && s.sector && s.agency && s.mobile;
  }

  function updateChecklist() {
    if (!submitChecklist) return;
    const status = getRequiredStatus();
    submitChecklist.querySelectorAll('[data-check]').forEach((li) => {
      const key = li.getAttribute('data-check');
      const ok = !!status[key];
      li.classList.toggle('is-done', ok);
      li.classList.toggle('is-missing', !ok);
    });
    const ready = isFormReadyToSubmit();
    if (submitHint) {
      submitHint.textContent = ready
        ? 'All required information looks good. Tap Submit registration.'
        : 'Complete the checklist above before submitting.';
      submitHint.classList.toggle('is-ready', ready);
    }
  }

  function setRegisterEnabled(enabled) {
    [btnRegister, btnRegisterSticky].forEach((btn) => {
      if (!btn) return;
      btn.disabled = !enabled;
      btn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
      btn.classList.toggle('is-blocked', !enabled);
    });
  }

  function updateContinueLabels() {
    const label = CONTINUE_LABELS[currentStep] || 'Next';
    document.querySelectorAll('.btn-continue-label').forEach((el) => {
      el.textContent = label;
    });
  }

  function updateAgencyPickerValidity(showError) {
    if (!agencyPickerBtn) return;
    const ok = validatePickerField(agencyInput, agencyOther);
    agencyPickerBtn.classList.toggle('is-invalid', showError && !ok);
    agencyPickerBtn.classList.toggle('is-valid', ok);
  }

  function updateStepper() {
    const pct = (currentStep / totalSteps) * 100;
    if (stepperProgress) stepperProgress.style.width = pct + '%';
    if (stepperLabel) {
      stepperLabel.textContent = 'Step ' + currentStep + ' of ' + totalSteps + ' — ' + (STEP_NAMES[currentStep - 1] || '');
    }
    stepperTabs.forEach((tab) => {
      const step = parseInt(tab.dataset.step, 10);
      tab.classList.toggle('is-active', step === currentStep);
      tab.classList.toggle('is-done', step < currentStep);
      tab.setAttribute('aria-current', step === currentStep ? 'step' : 'false');
    });
    steps.forEach((fieldset) => {
      const step = parseInt(fieldset.dataset.step, 10);
      fieldset.classList.toggle('is-active', step === currentStep);
    });

    const isFirst = currentStep === 1;
    const isLast = currentStep === totalSteps;

    [btnBack, btnBackSticky].forEach((btn) => {
      if (btn) btn.style.display = isFirst ? 'none' : '';
    });
    [btnContinue, btnContinueSticky].forEach((btn) => {
      if (btn) btn.style.display = isLast ? 'none' : '';
    });
    [btnRegister, btnRegisterSticky].forEach((btn) => {
      if (btn) btn.style.display = isLast ? '' : 'none';
    });

    updateContinueLabels();
    if (isLast) {
      updateReview();
      updateChecklist();
      setRegisterEnabled(isFormReadyToSubmit());
    }
  }

  function getFieldsInStep(step) {
    const fieldset = steps.find((fs) => parseInt(fs.dataset.step, 10) === step);
    if (!fieldset) return [];
    return Array.from(fieldset.querySelectorAll('input, select, textarea')).filter(
      (el) => !el.disabled && el.type !== 'hidden' && el.offsetParent !== null
    );
  }

  function validateStep(step) {
    hideStepError();
    const fields = getFieldsInStep(step);
    let valid = true;
    let message = 'Please complete the required fields in this step.';

    fields.forEach((field) => {
      if (field.hasAttribute('required') && !field.checkValidity()) valid = false;
      else if (field.type === 'email' && field.value && !field.checkValidity()) valid = false;
    });

    if (step === 1) {
      const email = form.querySelector('#email');
      if (email && !email.checkValidity()) {
        valid = false;
        message = 'Please enter a valid email — we send your QR code there.';
      }
    }

    if (step === 2) {
      if (!(form.querySelector('#sector')?.value || '').trim()) {
        valid = false;
        message = 'Please select your sector.';
      }
      if (!validatePickerField(agencyInput, agencyOther)) {
        valid = false;
        message = 'Please select your agency. Choose Other if not listed.';
        updateAgencyPickerValidity(true);
      } else {
        updateAgencyPickerValidity(false);
      }
      const mobile = form.querySelector('#contact_no');
      if (mobile) {
        const cleaned = normalizePhMobileInput(mobile.value);
        if (cleaned !== mobile.value) mobile.value = cleaned;
        if (!isValidPhMobile(mobile.value)) {
          valid = false;
          message = 'Enter a Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).';
          mobile.setCustomValidity(message);
        } else {
          mobile.setCustomValidity('');
        }
      }
    }

    if (!valid) {
      form.classList.add('was-validated');
      showStepError(message);
      const firstInvalid = fields.find((f) => f.hasAttribute('required') && !f.checkValidity());
      if (firstInvalid) {
        firstInvalid.classList.add('shake');
        setTimeout(() => firstInvalid.classList.remove('shake'), 300);
        firstInvalid.focus();
      } else if (step === 2 && agencyPickerBtn && !validatePickerField(agencyInput, agencyOther)) {
        agencyPickerBtn.focus();
      }
      return false;
    }

    return true;
  }

  function goToStep(step) {
    currentStep = Math.max(1, Math.min(totalSteps, step));
    updateStepper();
    hideStepError();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function updateReview() {
    const first = form.querySelector('[name="first_name"]')?.value?.trim() || '';
    const last = form.querySelector('[name="last_name"]')?.value?.trim() || '';
    const email = form.querySelector('[name="email"]')?.value?.trim() || '—';
    const mobile = form.querySelector('[name="contact_no"]')?.value?.trim() || '—';
    let agency = agencyInput?.value?.trim() || '';
    if (agency === 'other') agency = agencyOther?.value?.trim() || 'Other';

    if (reviewName) reviewName.textContent = (first + ' ' + last).trim() || '—';
    if (reviewAgency) reviewAgency.textContent = agency || '—';
    if (reviewEmail) reviewEmail.textContent = email;
    if (reviewMobile) reviewMobile.textContent = mobile;
  }

  function handleContinue() {
    if (validateStep(currentStep)) goToStep(currentStep + 1);
  }

  function handleBack() {
    goToStep(currentStep - 1);
  }

  [btnContinue, btnContinueSticky].forEach((btn) => {
    btn?.addEventListener('click', handleContinue);
  });
  [btnBack, btnBackSticky].forEach((btn) => {
    btn?.addEventListener('click', handleBack);
  });

  stepperTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const target = parseInt(tab.dataset.step, 10);
      if (target < currentStep) {
        goToStep(target);
        return;
      }
      if (target > currentStep) {
        for (let s = currentStep; s < target; s++) {
          if (!validateStep(s)) return;
        }
        goToStep(target);
      }
    });
  });

  function refreshSubmitGate() {
    updateChecklist();
    updateReview();
    if (currentStep === totalSteps) {
      setRegisterEnabled(isFormReadyToSubmit());
    }
    updateAgencyPickerValidity(form.classList.contains('was-validated'));
  }

  form.addEventListener('input', refreshSubmitGate);
  form.addEventListener('change', refreshSubmitGate);

  const contactInput = form.querySelector('#contact_no');
  contactInput?.addEventListener('blur', () => {
    const cleaned = normalizePhMobileInput(contactInput.value);
    if (cleaned !== contactInput.value) contactInput.value = cleaned;
    if (cleaned && !isValidPhMobile(cleaned)) {
      contactInput.setCustomValidity('Use 09XXXXXXXXX or +639XXXXXXXXX');
    } else {
      contactInput.setCustomValidity('');
    }
  });
  contactInput?.addEventListener('input', () => {
    contactInput.setCustomValidity('');
  });

  function setSubmitLoading(loading) {
    [btnRegister, btnRegisterSticky].forEach((btn) => {
      if (!btn) return;
      if (loading) {
        btn.disabled = true;
        const label = btn.querySelector('.btn-label');
        const spinner = btn.querySelector('.spinner-border');
        if (label) label.textContent = 'Submitting...';
        spinner?.classList.remove('d-none');
      } else {
        const label = btn.querySelector('.btn-label');
        const spinner = btn.querySelector('.spinner-border');
        if (label) label.textContent = 'Submit registration';
        spinner?.classList.add('d-none');
        setRegisterEnabled(isFormReadyToSubmit());
      }
    });
  }

  form.addEventListener('submit', (event) => {
    for (let s = 1; s <= totalSteps; s++) {
      if (!validateStep(s)) {
        event.preventDefault();
        event.stopPropagation();
        goToStep(s);
        return;
      }
    }
    if (!isFormReadyToSubmit()) {
      event.preventDefault();
      event.stopPropagation();
      goToStep(totalSteps);
      updateChecklist();
      setRegisterEnabled(false);
      showStepError('Please complete all required information before submitting.');
      return;
    }
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
      form.classList.add('was-validated');
      return;
    }
    setSubmitLoading(true);
  });

  // Block accidental submit taps on disabled sticky button (some browsers still fire)
  [btnRegister, btnRegisterSticky].forEach((btn) => {
    btn?.addEventListener('click', (event) => {
      if (btn.disabled || !isFormReadyToSubmit()) {
        event.preventDefault();
        event.stopPropagation();
        showStepError('Please complete all required information before submitting.');
        updateChecklist();
      }
    });
  });

  form.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || currentStep >= totalSteps) return;
    const target = event.target;
    if (target && (target.tagName === 'TEXTAREA' || target.type === 'submit')) return;
    event.preventDefault();
    handleContinue();
  });

  /* Picker component */
  function buildPicker(config) {
    const {
      triggerId,
      hiddenInputId,
      otherInputId,
      datalistId,
      sheetId,
    } = config;

    const trigger = document.getElementById(triggerId);
    const hiddenInput = document.getElementById(hiddenInputId);
    const otherInput = document.getElementById(otherInputId);
    const datalist = document.getElementById(datalistId);
    const sheet = document.getElementById(sheetId);
    if (!trigger || !hiddenInput || !sheet) return;

    const backdrop = sheet.closest('.picker-sheet-wrap')?.querySelector('.picker-sheet-backdrop');
    const searchInput = sheet.querySelector('.picker-sheet-search');
    const listEl = sheet.querySelector('.picker-sheet-list');
    const closeBtn = sheet.querySelector('.picker-sheet-close');

    const options = datalist
      ? Array.from(datalist.querySelectorAll('option')).map((o) => o.value).filter(Boolean)
      : [];

    function updateTriggerLabel(value) {
      const text = trigger.querySelector('.picker-trigger-text');
      if (!text) return;
      if (!value || value === 'other') {
        text.textContent = value === 'other' ? 'Other (custom)' : (trigger.dataset.placeholder || 'Tap to select');
        trigger.classList.toggle('is-placeholder', value !== 'other' && !value);
      } else {
        text.textContent = value;
        trigger.classList.remove('is-placeholder');
      }
    }

    function toggleOther(value) {
      if (!otherInput) return;
      const show = value === 'other';
      otherInput.style.display = show ? '' : 'none';
      otherInput.toggleAttribute('required', show);
      if (show) otherInput.focus();
    }

    function selectValue(value) {
      hiddenInput.value = value;
      updateTriggerLabel(value);
      toggleOther(value);
      closeSheet();
      hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
      refreshSubmitGate();
    }

    function renderList(filter) {
      if (!listEl) return;
      const q = (filter || '').toLowerCase();
      const filtered = options.filter((o) => o.toLowerCase().includes(q));
      listEl.innerHTML = '';

      if (filtered.length === 0 && q) {
        listEl.innerHTML = '<div class="picker-sheet-empty">No matches found</div>';
      } else {
        filtered.forEach((opt) => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'picker-sheet-option';
          btn.textContent = opt;
          btn.addEventListener('click', () => selectValue(opt));
          listEl.appendChild(btn);
        });
      }

      const otherBtn = document.createElement('button');
      otherBtn.type = 'button';
      otherBtn.className = 'picker-sheet-option is-other';
      otherBtn.textContent = 'Other (not listed)';
      otherBtn.addEventListener('click', () => selectValue('other'));
      listEl.appendChild(otherBtn);
    }

    function openSheet() {
      renderList('');
      if (searchInput) {
        searchInput.value = '';
        setTimeout(() => searchInput.focus(), 100);
      }
      sheet.classList.add('is-open');
      backdrop?.classList.add('is-open');
      document.body.style.overflow = 'hidden';
    }

    function closeSheet() {
      sheet.classList.remove('is-open');
      backdrop?.classList.remove('is-open');
      document.body.style.overflow = '';
    }

    trigger.addEventListener('click', openSheet);
    closeBtn?.addEventListener('click', closeSheet);
    backdrop?.addEventListener('click', closeSheet);

    searchInput?.addEventListener('input', () => renderList(searchInput.value));

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && sheet.classList.contains('is-open')) closeSheet();
    });

    if (hiddenInput.value) updateTriggerLabel(hiddenInput.value);
  }

  buildPicker({
    triggerId: 'agencyPickerBtn',
    hiddenInputId: 'agencyInput',
    otherInputId: 'agencyOther',
    datalistId: 'agencyList',
    sheetId: 'agencyPickerSheet',
  });

  buildPicker({
    triggerId: 'designationPickerBtn',
    hiddenInputId: 'designationInput',
    otherInputId: 'designationOther',
    datalistId: 'designationList',
    sheetId: 'designationPickerSheet',
  });

  updateStepper();
  setRegisterEnabled(false);
})();
