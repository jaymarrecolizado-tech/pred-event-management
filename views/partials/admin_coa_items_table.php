<?php
declare(strict_types=1);

use App\Services\CertificateOfAppearanceService as Coa;

$token = $token ?? (function_exists('csrf_token') ? csrf_token() : '');
$items = $items ?? [];
?>
<div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead>
      <tr>
        <th>Name</th>
        <th>Agency</th>
        <th>Email</th>
        <th>Status</th>
        <th>Error</th>
        <th>Sent at</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$items): ?>
      <tr><td colspan="7" class="text-muted">No items for this filter.</td></tr>
    <?php endif; ?>
    <?php foreach ($items as $item): ?>
      <?php
        $name = Coa::fullName($item);
        $st = (string)$item['status'];
        $badge = match ($st) {
            'sent' => 'success',
            'failed' => 'danger',
            'pending' => 'warning',
            default => 'secondary',
        };
      ?>
      <tr>
        <td><?= htmlspecialchars($name, ENT_QUOTES) ?></td>
        <td class="small"><?= htmlspecialchars((string)($item['agency'] ?? ''), ENT_QUOTES) ?></td>
        <td class="small"><?= htmlspecialchars((string)($item['email_to'] ?? ''), ENT_QUOTES) ?></td>
        <td><span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars($st === 'pending' ? 'queued' : $st, ENT_QUOTES) ?></span></td>
        <td class="small text-muted"><?= htmlspecialchars((string)($item['error'] ?? ''), ENT_QUOTES) ?></td>
        <td class="small"><?= htmlspecialchars((string)($item['sent_at'] ?? '—'), ENT_QUOTES) ?></td>
        <td>
          <?php if ($st === 'failed'): ?>
            <form method="post" action="?r=admin_coa_queue_failed" class="d-inline">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
              <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
              <button class="btn btn-sm btn-outline-warning" type="submit">Queue</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
