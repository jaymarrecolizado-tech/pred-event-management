<?php
declare(strict_types=1);

$token = function_exists('csrf_token') ? csrf_token() : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Scan & Sign</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
  <meta name="csrf" content="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
  <style>
    #reader { width: 100%; max-width: 520px; margin: 0 auto; border-radius: 20px; overflow: hidden; }
    #sigCanvas { border: 1px dashed rgba(92,108,242,0.3); border-radius: 16px; width: 100%; height: 260px; touch-action: none; background: rgba(255,255,255,0.9); }
  </style>
  </head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark py-3">
  <div class="container"><a class="navbar-brand" href="?r=register">GovNet-Launching</a>
    <div class="ms-auto d-flex gap-2">
      <a class="btn btn-outline-light btn-sm px-3" href="?r=scan">Scan</a>
      <?php if (!empty($_SESSION['admin_id'])): ?>
      <a class="btn btn-outline-light btn-sm px-3" href="?r=admin_registrants">Dashboard</a>
      <a class="btn btn-outline-light btn-sm px-3" href="?r=admin_logout">Logout</a>
      <?php else: ?>
      <a class="btn btn-outline-light btn-sm px-3" href="?r=admin_login">Admin</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<div class="container py-4">
  <div class="row g-4">
    <div class="col-12 col-lg-6">
      <div class="glass-panel h-100">
        <p class="text-uppercase text-muted mb-2" style="letter-spacing:.2em;font-size:.75rem;">Step 2</p>
        <h1 class="page-heading h3 mb-3">Scan QR and capture signature</h1>
        <div id="reader" class="mb-4 bg-white position-relative">
          <div id="scanLoading" class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading camera...</span>
            </div>
            <p class="mt-2 text-muted small">Initializing camera...</p>
          </div>
        </div>
        <div class="mb-3 text-center">
          <button id="switchCameraBtn" class="btn btn-outline-primary btn-sm" style="display:none;">
            <span id="cameraLabel">Switch Camera</span>
          </button>
        </div>
        <p class="subtext mb-0">Use a tablet or desktop camera for best results. Once scanned, collect the participant's signature to complete attendance.</p>
      </div>
    </div>
    <div class="col-12 col-lg-6">
      <div class="card mb-3" id="participantCard" style="display:none">
        <div class="card-body">
          <div id="pinfo" class="mb-3 fw-semibold"></div>
          <div id="pvip" class="mb-2" style="display:none">
            <span class="badge text-bg-warning">VIP</span>
            <span class="small text-muted ms-1">Priority guest — protocol care</span>
          </div>
          <canvas id="sigCanvas"></canvas>
          <div class="mt-3 d-flex flex-wrap gap-2">
            <button id="calibrateBtn" class="btn btn-outline-secondary flex-fill">Calibrate</button>
            <button id="clearBtn" class="btn btn-outline-secondary flex-fill">Clear</button>
            <button id="saveBtn" class="btn btn-primary flex-fill">Save Attendance</button>
          </div>
          <div id="status" class="mt-3 text-muted small"></div>
        </div>
      </div>
      <a class="btn btn-outline-secondary" href="?r=register">Back to registration</a>
    </div>
  </div>
</div>
<div class="position-fixed top-0 end-0 p-3" style="z-index:1055">
  <div id="saveToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">Attendance saved</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const csrf = document.querySelector('meta[name="csrf"]').getAttribute('content');
let currentUuid = null;
let sigPad;
let calibrated = false;
let qrScanner;
let isScanning = false;
let availableCameras = [];
let currentCameraIndex = 0;
let currentCameraId = null;

function showError(msg) {
  const el = document.getElementById('saveToast');
  el.className = 'toast align-items-center text-bg-danger border-0';
  el.querySelector('.toast-body').innerText = msg;
  const t = new bootstrap.Toast(el);
  t.show();
}

function findBackCamera(devices) {
  // Try to find back camera first (usually better for scanning)
  // Back cameras often have labels like "back", "rear", or "environment"
  for (let i = 0; i < devices.length; i++) {
    const label = devices[i].label.toLowerCase();
    if (label.includes('back') || label.includes('rear') || label.includes('environment') || 
        label.includes('facing back') || label.includes('facing rear')) {
      return i;
    }
  }
  // If no back camera found, prefer the last camera (often the back camera on mobile devices)
  return devices.length > 1 ? devices.length - 1 : 0;
}

function findFrontCamera(devices) {
  // Try to find front camera
  for (let i = 0; i < devices.length; i++) {
    const label = devices[i].label.toLowerCase();
    if (label.includes('front') || label.includes('user') || label.includes('facing front')) {
      return i;
    }
  }
  // If no front camera found, use the first camera
  return 0;
}

function getCameraLabel(device) {
  if (!device || !device.label) return 'Camera';
  const label = device.label.toLowerCase();
  if (label.includes('back') || label.includes('rear') || label.includes('environment')) {
    return 'Back Camera';
  } else if (label.includes('front') || label.includes('user')) {
    return 'Front Camera';
  }
  return device.label || 'Camera';
}

function startScanWithCamera(cameraIndex = null) {
  if (isScanning && qrScanner) {
    // Stop current scan before switching
    qrScanner.stop().then(() => {
      isScanning = false;
      startScanWithCamera(cameraIndex);
    }).catch(() => {
      isScanning = false;
      startScanWithCamera(cameraIndex);
    });
    return;
  }
  
  const qrRegion = document.getElementById('reader');
  if (!qrRegion) {
    showError('QR scanner container not found');
    return;
  }
  if (typeof Html5Qrcode === 'undefined') {
    showError('QR scanner library not loaded. Please refresh the page.');
    return;
  }
  
  // If we already have cameras, use them
  if (availableCameras.length > 0) {
    if (cameraIndex === null) {
      cameraIndex = currentCameraIndex;
    }
    if (cameraIndex >= availableCameras.length) {
      cameraIndex = 0;
    }
    currentCameraIndex = cameraIndex;
    currentCameraId = availableCameras[cameraIndex].id;
    
    const loadingEl = document.getElementById('scanLoading');
    if (loadingEl) loadingEl.style.display = 'none';
    
    if (!qrScanner) {
      qrScanner = new Html5Qrcode(qrRegion.id);
    }
    
    isScanning = true;
    const config = {
      fps: 10,
      qrbox: 250,
      aspectRatio: 1.0
    };
    
    qrScanner.start(
      currentCameraId,
      config,
      onScanSuccess,
      onScanError
    ).then(() => {
      if (loadingEl) loadingEl.style.display = 'none';
      // Update camera switch button
      const switchBtn = document.getElementById('switchCameraBtn');
      const cameraLabel = document.getElementById('cameraLabel');
      if (switchBtn && availableCameras.length > 1) {
        switchBtn.style.display = 'inline-block';
        cameraLabel.textContent = getCameraLabel(availableCameras[cameraIndex]) + ' (Switch)';
      }
    }).catch(err => {
      console.error('QR scanner start error:', err);
      if (loadingEl) loadingEl.style.display = 'block';
      showError('Failed to start camera: ' + (err.message || 'Unknown error'));
      isScanning = false;
      // Try next camera if available
      if (availableCameras.length > 1) {
        setTimeout(() => {
          const nextIndex = (cameraIndex + 1) % availableCameras.length;
          startScanWithCamera(nextIndex);
        }, 1000);
      }
    });
    return;
  }
  
  // First time: get cameras
  qrScanner = new Html5Qrcode(qrRegion.id);
  isScanning = true;
  Html5Qrcode.getCameras().then(devices => {
    if (!devices || devices.length === 0) {
      showError('No camera found. Please connect a camera and refresh.');
      isScanning = false;
      return;
    }
    
    availableCameras = devices;
    
    // Prefer back camera for scanning (usually better quality)
    if (cameraIndex === null) {
      currentCameraIndex = findBackCamera(devices);
    } else {
      currentCameraIndex = cameraIndex >= devices.length ? 0 : cameraIndex;
    }
    
    currentCameraId = devices[currentCameraIndex].id;
    const loadingEl = document.getElementById('scanLoading');
    if (loadingEl) loadingEl.style.display = 'none';
    
    const config = {
      fps: 10,
      qrbox: 250,
      aspectRatio: 1.0
    };
    
    qrScanner.start(
      currentCameraId,
      config,
      onScanSuccess,
      onScanError
    ).then(() => {
      if (loadingEl) loadingEl.style.display = 'none';
      // Show camera switch button if multiple cameras available
      const switchBtn = document.getElementById('switchCameraBtn');
      const cameraLabel = document.getElementById('cameraLabel');
      if (switchBtn && devices.length > 1) {
        switchBtn.style.display = 'inline-block';
        cameraLabel.textContent = getCameraLabel(devices[currentCameraIndex]) + ' (Switch)';
      }
    }).catch(err => {
      console.error('QR scanner start error:', err);
      if (loadingEl) loadingEl.style.display = 'block';
      showError('Failed to start camera: ' + (err.message || 'Unknown error'));
      isScanning = false;
      // Try next camera if available
      if (devices.length > 1) {
        setTimeout(() => {
          const nextIndex = (currentCameraIndex + 1) % devices.length;
          startScanWithCamera(nextIndex);
        }, 1000);
      }
    });
  }).catch(err => {
    console.error('Camera access error:', err);
    const loadingEl = document.getElementById('scanLoading');
    if (loadingEl) {
      loadingEl.innerHTML = '<p class="text-danger">Camera access denied. Please allow camera permissions and refresh the page.</p>';
    }
    showError('Camera access denied. Please allow camera permissions and refresh.');
    isScanning = false;
  });
}

function startScan() {
  startScanWithCamera();
}

function onScanSuccess(decodedText, decodedResult) {
  if (!decodedText) return;
  if (decodedText.startsWith('PART|')) {
    const uuid = decodedText.split('|')[1];
    if (qrScanner) {
      qrScanner.stop().then(() => {
        isScanning = false;
      }).catch(() => {
        isScanning = false;
      });
    }
    fetch('?r=api_participant&uuid=' + encodeURIComponent(uuid))
      .then(r => {
        if (!r.ok) {
          throw new Error('Participant not found');
        }
        return r.json();
      })
      .then(j => {
        if (j.participant) {
          currentUuid = j.participant.uuid;
          document.getElementById('participantCard').style.display = 'block';
          const p = j.participant;
          document.getElementById('pinfo').innerText = `${p.first_name} ${p.last_name}${p.agency ? ' (' + p.agency + ')' : ''}`;
          const vipEl = document.getElementById('pvip');
          if (vipEl) {
            vipEl.style.display = Number(p.is_vip) === 1 ? 'block' : 'none';
          }
          const canvas = document.getElementById('sigCanvas');
          if (sigPad) {
            sigPad.clear();
            sigPad = null;
          }
          sigPad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255,255,255,1)',
            minWidth: Math.max((window.devicePixelRatio||1),1),
            maxWidth: Math.max((window.devicePixelRatio||1)*2,2)
          });
          calibrateCanvas(canvas);
        } else {
          showError('Participant data not found');
          setTimeout(() => { startScan(); }, 1000);
        }
      })
      .catch(err => {
        console.error('Fetch error:', err);
        showError('Failed to load participant: ' + (err.message || 'Unknown error'));
        setTimeout(() => { startScan(); }, 1000);
      });
  }
}

function onScanError(errorMessage) {
  // Ignore scanning errors - they're normal during scanning
}

// Camera switch button
document.getElementById('switchCameraBtn').addEventListener('click', () => {
  if (availableCameras.length > 1) {
    const nextIndex = (currentCameraIndex + 1) % availableCameras.length;
    startScanWithCamera(nextIndex);
  }
});

document.getElementById('calibrateBtn').addEventListener('click', () => {
  const canvas = document.getElementById('sigCanvas');
  calibrateCanvas(canvas);
});

document.getElementById('clearBtn').addEventListener('click', () => {
  if (sigPad) sigPad.clear();
});

document.getElementById('saveBtn').addEventListener('click', () => {
  if (!sigPad || sigPad.isEmpty() || !currentUuid) {
    showError('Please capture a signature first');
    return;
  }
  const data = sigPad.toDataURL('image/png');
  const btn = document.getElementById('saveBtn');
  btn.disabled = true;
  btn.textContent = 'Saving...';
  fetch('?r=attendance_submit', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    body: JSON.stringify({ uuid: currentUuid, signature: data })
  }).then(r => r.json()).then(j => {
    const el = document.getElementById('saveToast');
    el.className = 'toast align-items-center ' + (j.ok ? 'text-bg-success' : 'text-bg-danger') + ' border-0';
    el.querySelector('.toast-body').innerText = j.ok ? 'Attendance saved successfully' : (j.error || 'Error saving attendance');
    const t = new bootstrap.Toast(el);
    t.show();
    if (j.ok) {
      sigPad.clear();
      document.getElementById('participantCard').style.display = 'none';
      currentUuid = null;
      setTimeout(() => { startScan(); }, 500);
    }
    btn.disabled = false;
    btn.textContent = 'Save Attendance';
  }).catch(err => {
    console.error('Save error:', err);
    showError('Failed to save attendance: ' + (err.message || 'Network error'));
    btn.disabled = false;
    btn.textContent = 'Save Attendance';
  });
});

// Start scanning when page loads
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', startScan);
} else {
  startScan();
}

function calibrateCanvas(canvas){
  const ratio = Math.max(window.devicePixelRatio || 1, 1);
  const data = sigPad ? sigPad.toData() : null;
  const rect = canvas.getBoundingClientRect();
  canvas.width = Math.floor(rect.width * ratio);
  canvas.height = Math.floor(rect.height * ratio);
  const ctx = canvas.getContext('2d');
  ctx.scale(ratio, ratio);
  if (sigPad) {
    sigPad.clear();
    if (data && data.length) sigPad.fromData(data);
  }
  calibrated = true;
}

window.addEventListener('resize', () => {
  const canvas = document.getElementById('sigCanvas');
  if (canvas && sigPad) calibrateCanvas(canvas);
});
</script>
</body>
</html>