<?php
declare(strict_types=1);

use App\Services\AuthService;

$token = function_exists('csrf_token') ? csrf_token() : '';
$rows = $rows ?? [];
$roles = $roles ?? [];
$flash = $flash ?? null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Users</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<?php $activeNav = 'admin_users'; require __DIR__ . '/partials/admin_nav.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <p class="text-uppercase text-muted mb-1" style="letter-spacing:.2em;font-size:.75rem;">Administration</p>
      <h1 class="page-heading h4 mb-0">Manage accounts</h1>
      <div class="text-muted small">Create staff accounts, edit profiles, change roles, and reset passwords.</div>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info', ENT_QUOTES) ?>">
      <?= htmlspecialchars($flash['message'] ?? '', ENT_QUOTES) ?>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-12 col-lg-4">
      <div class="border rounded-3 p-3 bg-white">
        <h2 class="h6 mb-3">Add account</h2>
        <form method="post" action="?r=admin_users_create" class="vstack gap-2">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
          <div>
            <label class="form-label small mb-1">Username</label>
            <input name="username" class="form-control" required autocomplete="off">
          </div>
          <div>
            <label class="form-label small mb-1">Display name</label>
            <input name="display_name" class="form-control" placeholder="Optional">
          </div>
          <div>
            <label class="form-label small mb-1">Email</label>
            <input name="email" type="email" class="form-control" placeholder="Optional">
          </div>
          <div>
            <label class="form-label small mb-1">Role</label>
            <select name="role" class="form-select" required>
              <?php foreach ($roles as $key => $label): ?>
                <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>" <?= $key === AuthService::ROLE_CHECKER ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label small mb-1">Password</label>
            <input name="password" type="password" class="form-control" required minlength="10" placeholder="Min. 10 characters">
          </div>
          <button class="btn btn-primary" type="submit">Create account</button>
        </form>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="border rounded-3 p-3 bg-white">
        <h2 class="h6 mb-3">Accounts</h2>
        <div class="table-responsive table-modern">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>User</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last login</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">No accounts yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $u):
              $roleLabel = $roles[$u['role']] ?? (string)$u['role'];
              $isActive = (int)$u['is_active'] === 1;
            ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars((string)($u['display_name'] ?: $u['username']), ENT_QUOTES) ?></div>
                  <div class="small text-muted">@<?= htmlspecialchars((string)$u['username'], ENT_QUOTES) ?></div>
                  <?php if (!empty($u['email'])): ?>
                    <div class="small text-muted"><?= htmlspecialchars((string)$u['email'], ENT_QUOTES) ?></div>
                  <?php endif; ?>
                </td>
                <td><span class="badge text-bg-light text-dark border"><?= htmlspecialchars($roleLabel, ENT_QUOTES) ?></span></td>
                <td>
                  <?php if ($isActive): ?>
                    <span class="badge text-bg-success">Active</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">Inactive</span>
                  <?php endif; ?>
                </td>
                <td class="small"><?= htmlspecialchars((string)($u['last_login_at'] ?? '—'), ENT_QUOTES) ?></td>
                <td class="text-end">
                  <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                    <button
                      type="button"
                      class="btn btn-outline-primary btn-sm btn-edit-user"
                      data-bs-toggle="modal"
                      data-bs-target="#editUserModal"
                      data-id="<?= (int)$u['id'] ?>"
                      data-username="<?= htmlspecialchars((string)$u['username'], ENT_QUOTES) ?>"
                      data-display-name="<?= htmlspecialchars((string)($u['display_name'] ?? ''), ENT_QUOTES) ?>"
                      data-email="<?= htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES) ?>"
                      data-role="<?= htmlspecialchars((string)$u['role'], ENT_QUOTES) ?>"
                    >Edit</button>
                    <button
                      type="button"
                      class="btn btn-outline-secondary btn-sm btn-reset-user"
                      data-bs-toggle="modal"
                      data-bs-target="#resetPasswordModal"
                      data-id="<?= (int)$u['id'] ?>"
                      data-username="<?= htmlspecialchars((string)$u['username'], ENT_QUOTES) ?>"
                    >Password</button>
                    <form method="post" action="?r=admin_users_update" class="d-inline">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="action" value="<?= $isActive ? 'deactivate' : 'activate' ?>">
                      <button class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit">
                        <?= $isActive ? 'Deactivate' : 'Activate' ?>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="?r=admin_users_update" class="modal-content">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editUserId" value="">
      <div class="modal-header">
        <h2 class="modal-title h5" id="editUserModalLabel">Edit account</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body vstack gap-3">
        <div>
          <label class="form-label">Username</label>
          <input type="text" class="form-control" id="editUsername" readonly>
          <div class="form-text">Username cannot be changed.</div>
        </div>
        <div>
          <label class="form-label" for="editDisplayName">Display name</label>
          <input type="text" class="form-control" name="display_name" id="editDisplayName">
        </div>
        <div>
          <label class="form-label" for="editEmail">Email</label>
          <input type="email" class="form-control" name="email" id="editEmail">
        </div>
        <div>
          <label class="form-label" for="editRole">Role</label>
          <select class="form-select" name="role" id="editRole" required>
            <?php foreach ($roles as $key => $label): ?>
              <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save changes</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="?r=admin_users_update" class="modal-content">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="id" id="resetUserId" value="">
      <div class="modal-header">
        <h2 class="modal-title h5" id="resetPasswordModalLabel">Reset password</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">Set a new password for <strong id="resetUsernameLabel"></strong>.</p>
        <label class="form-label" for="resetPassword">New password</label>
        <input type="password" class="form-control" name="password" id="resetPassword" required minlength="10" placeholder="Min. 10 characters">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Update password</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  document.querySelectorAll('.btn-edit-user').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('editUserId').value = btn.getAttribute('data-id') || '';
      document.getElementById('editUsername').value = btn.getAttribute('data-username') || '';
      document.getElementById('editDisplayName').value = btn.getAttribute('data-display-name') || '';
      document.getElementById('editEmail').value = btn.getAttribute('data-email') || '';
      document.getElementById('editRole').value = btn.getAttribute('data-role') || 'checker';
    });
  });
  document.querySelectorAll('.btn-reset-user').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('resetUserId').value = btn.getAttribute('data-id') || '';
      document.getElementById('resetUsernameLabel').textContent = btn.getAttribute('data-username') || '';
      document.getElementById('resetPassword').value = '';
    });
  });
})();
</script>
</body>
</html>
