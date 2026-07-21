<?php
declare(strict_types=1);

use App\Services\AuthService;

$selectedDate = $selectedDate ?? date('Y-m-d');
$kpi = $kpi ?? [];
$vipCounts = $vipCounts ?? ['total' => 0, 'present' => 0, 'in_vicinity' => 0, 'absent' => 0];
$vips = $vips ?? [];
$guestCounts = $guestCounts ?? ['total' => 0, 'present' => 0, 'in_vicinity' => 0, 'absent' => 0, 'listed' => 0, 'truncated' => false];
$guests = $guests ?? [];
$agencyRollup = $agencyRollup ?? [];
$recentVip = $recentVip ?? [];
$attention = $attention ?? [];
$event = $event ?? null;

$vipTotal = max(0, (int)($vipCounts['total'] ?? 0));
$vipPresent = (int)($vipCounts['present'] ?? 0);
$vipVicinity = (int)($vipCounts['in_vicinity'] ?? 0);
$vipAbsent = (int)($vipCounts['absent'] ?? 0);
$vipPresentPct = $vipTotal > 0 ? round(($vipPresent / $vipTotal) * 100) : 0;
$vipVicinityPct = $vipTotal > 0 ? round(($vipVicinity / $vipTotal) * 100) : 0;
$vipAbsentPct = $vipTotal > 0 ? max(0, 100 - $vipPresentPct - $vipVicinityPct) : 0;

$statusLabel = static function (string $status): string {
    return match ($status) {
        'present' => 'Present',
        'absent' => 'Absent',
        default => 'In Vicinity',
    };
};

$statusClass = static function (string $status): string {
    return match ($status) {
        'present' => 'present',
        'absent' => 'absent',
        default => 'vicinity',
    };
};

$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= strtoupper(substr($part, 0, 1));
    }
    return $letters !== '' ? $letters : '?';
};

$eventName = (string)($event['name'] ?? 'Active event');
$registered = (int)($kpi['totalRegistered'] ?? 0);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SEO Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
  <link href="assets/seo-dashboard.css" rel="stylesheet">
</head>
<body>
<?php $activeNav = 'admin_seo_dashboard'; require __DIR__ . '/partials/admin_nav.php'; ?>

<section class="seo-dash" id="seoDashRoot">
  <div class="seo-shell">
    <header class="seo-hero">
      <div>
        <div class="seo-kicker"><span class="seo-live-dot" aria-hidden="true"></span> Live executive view</div>
        <h1 class="seo-title">Event pulse &amp; VIP watch</h1>
        <p class="seo-subtitle">
          Real-time attendance posture for protocol and stakeholders — present, in vicinity, and follow-ups at a glance.
        </p>
        <div class="seo-meta">
          <span><strong id="seoEventName"><?= htmlspecialchars($eventName, ENT_QUOTES) ?></strong></span>
          <span>Date <strong id="seoDateLabel"><?= htmlspecialchars($selectedDate, ENT_QUOTES) ?></strong></span>
          <span class="seo-refresh-flag">Updated <strong id="seoRefreshedAt"><?= htmlspecialchars(date('g:i:s A'), ENT_QUOTES) ?></strong></span>
          <span><?= number_format($registered) ?> registered</span>
        </div>
      </div>
      <div class="seo-controls">
        <input type="date" id="seoDate" value="<?= htmlspecialchars($selectedDate, ENT_QUOTES) ?>" aria-label="Select date">
        <button type="button" class="seo-btn seo-btn-primary" id="seoDateGo">Apply date</button>
        <button type="button" class="seo-btn seo-btn-ghost" id="seoRefreshNow">Refresh</button>
      </div>
    </header>

    <section class="seo-search-panel" aria-label="Guest search">
      <div class="seo-search-head">
        <div>
          <h2>Find a guest</h2>
          <p>Search VIPs or any registered attendee by name, agency, or designation</p>
        </div>
        <div class="seo-filters" id="seoSearchScope" role="tablist" aria-label="Search scope">
          <button type="button" class="seo-chip is-active" data-scope="all">All attendees</button>
          <button type="button" class="seo-chip" data-scope="vip">VIP only</button>
        </div>
      </div>
      <div class="seo-search-row">
        <input
          type="search"
          id="seoSearchInput"
          class="seo-search-input"
          placeholder="Type at least 2 characters — e.g. surname, agency, or title"
          autocomplete="off"
          aria-label="Search guests"
        >
        <button type="button" class="seo-btn seo-btn-ghost" id="seoSearchClear" style="display:none">Clear</button>
      </div>
      <div id="seoSearchMeta" class="seo-search-meta" hidden></div>
      <div class="seo-table-wrap seo-search-results" id="seoSearchResultsWrap" hidden>
        <table class="seo-table">
          <thead>
            <tr>
              <th>Guest</th>
              <th>Agency</th>
              <th>VIP</th>
              <th>Status</th>
              <th>Signed in at</th>
            </tr>
          </thead>
          <tbody id="seoSearchResults"></tbody>
        </table>
      </div>
      <div id="seoSearchEmpty" class="seo-empty" hidden>No matching guests for this search.</div>
    </section>

    <div class="seo-kpi-grid" id="seoKpiStrip">
      <article class="seo-kpi" data-tone="present">
        <div class="seo-kpi-label">Signed in</div>
        <div class="seo-kpi-value" id="kpiPresent"><?= (int)($kpi['dateCount'] ?? 0) ?></div>
        <div class="seo-kpi-hint">Confirmed present</div>
      </article>
      <article class="seo-kpi" data-tone="vicinity">
        <div class="seo-kpi-label">In vicinity</div>
        <div class="seo-kpi-value" id="kpiVicinity"><?= (int)($kpi['vicinityCount'] ?? 0) ?></div>
        <div class="seo-kpi-hint">On site, not signed</div>
      </article>
      <article class="seo-kpi" data-tone="absent">
        <div class="seo-kpi-label">Absent</div>
        <div class="seo-kpi-value" id="kpiAbsent"><?= (int)($kpi['absentCount'] ?? 0) ?></div>
        <div class="seo-kpi-hint">Marked unavailable</div>
      </article>
      <article class="seo-kpi" data-tone="rate">
        <div class="seo-kpi-label">Accounted rate</div>
        <div class="seo-kpi-value" id="kpiRate"><?= htmlspecialchars((string)($kpi['attendanceRate'] ?? 0), ENT_QUOTES) ?>%</div>
        <div class="seo-kpi-hint">Not explicitly absent</div>
      </article>
      <article class="seo-kpi" data-tone="hour">
        <div class="seo-kpi-label">Last hour</div>
        <div class="seo-kpi-value" id="kpiRecent"><?= (int)($kpi['recentCount'] ?? 0) ?></div>
        <div class="seo-kpi-hint">Sign-ins in the past 60 minutes</div>
      </article>
      <article class="seo-kpi" data-tone="peak">
        <div class="seo-kpi-label">Busiest hour</div>
        <div class="seo-kpi-value" id="kpiPeak" style="font-size:1.05rem;line-height:1.25"><?= htmlspecialchars((string)($kpi['peakHourText'] ?? 'N/A'), ENT_QUOTES) ?></div>
        <div class="seo-kpi-hint">Peak check-in window today</div>
      </article>
    </div>

    <section class="seo-composition" aria-label="VIP composition">
      <div class="seo-composition-head">
        <h2>VIP completion</h2>
        <div class="seo-legend" id="vipCountSummary">
          <span><i class="seo-swatch present"></i> <?= $vipPresent ?> present</span>
          <span><i class="seo-swatch vicinity"></i> <?= $vipVicinity ?> vicinity</span>
          <span><i class="seo-swatch absent"></i> <?= $vipAbsent ?> absent</span>
          <span><?= $vipTotal ?> total</span>
        </div>
      </div>
      <?php if ($vipTotal === 0): ?>
        <div class="seo-empty" style="padding:1rem 0 0.25rem;text-align:left">
          <strong>No VIP guests yet.</strong>
          <?php if (AuthService::isAdmin()): ?>
            Flag priority guests on <a href="?r=admin_registrants">Registrants</a> using <em>Mark VIP</em>.
          <?php else: ?>
            Ask an admin to flag priority guests on Registrants using <em>Mark VIP</em>.
          <?php endif; ?>
          Overall attendance KPIs above still update; only the VIP watchlist waits for flagged guests.
        </div>
      <?php else: ?>
      <div class="seo-bar" id="vipCompositionBar" role="img" aria-label="VIP status composition">
        <i class="seo-bar-present" style="width:<?= $vipPresentPct ?>%"></i>
        <i class="seo-bar-vicinity" style="width:<?= $vipVicinityPct ?>%"></i>
        <i class="seo-bar-absent" style="width:<?= $vipAbsentPct ?>%"></i>
      </div>
      <?php endif; ?>
    </section>

    <div class="seo-layout">
      <div class="seo-main-col">
      <section class="seo-panel">
        <div class="seo-panel-head">
          <div>
            <h2>VIP watchlist</h2>
            <p>Priority guests and their current floor status</p>
          </div>
          <div class="seo-filters" id="vipFilters" role="tablist" aria-label="Filter VIP status">
            <button type="button" class="seo-chip is-active" data-filter="all">All</button>
            <button type="button" class="seo-chip" data-filter="present">Present</button>
            <button type="button" class="seo-chip" data-filter="in_vicinity">In vicinity</button>
            <button type="button" class="seo-chip" data-filter="absent">Absent</button>
          </div>
        </div>
        <div class="seo-table-wrap">
          <table class="seo-table">
            <thead>
              <tr>
                <th>Guest</th>
                <th>Agency</th>
                <th>Status</th>
                <th>Signed in at</th>
              </tr>
            </thead>
            <tbody id="vipTableBody">
            <?php if (!$vips): ?>
              <tr><td colspan="4"><div class="seo-empty">No VIP guests flagged yet. Ask an admin to mark priority guests on Registrants.</div></td></tr>
            <?php else: ?>
              <?php foreach ($vips as $vip):
                $status = (string)$vip['guest_status'];
              ?>
              <tr data-status="<?= htmlspecialchars($status, ENT_QUOTES) ?>">
                <td>
                  <span class="seo-name"><?= htmlspecialchars((string)$vip['name'], ENT_QUOTES) ?></span>
                  <?php if (trim((string)($vip['designation'] ?? '')) !== ''): ?>
                    <span class="seo-sub"><?= htmlspecialchars((string)$vip['designation'], ENT_QUOTES) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string)$vip['agency'], ENT_QUOTES) ?></td>
                <td><span class="seo-status seo-status-<?= $statusClass($status) ?>"><?= htmlspecialchars($statusLabel($status), ENT_QUOTES) ?></span></td>
                <td><?= htmlspecialchars((string)($vip['time_in'] ?? '—'), ENT_QUOTES) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="seo-panel" style="margin-top:1rem">
        <div class="seo-panel-head">
          <div>
            <h2>General attendees</h2>
            <p>
              Non-VIP registered guests
              <span id="guestCountSummary" class="seo-inline-counts">
                · <?= (int)($guestCounts['total'] ?? 0) ?> total
                · <?= (int)($guestCounts['present'] ?? 0) ?> present
                · <?= (int)($guestCounts['in_vicinity'] ?? 0) ?> vicinity
                · <?= (int)($guestCounts['absent'] ?? 0) ?> absent
              </span>
            </p>
          </div>
          <div class="seo-filters" id="guestFilters" role="tablist" aria-label="Filter attendee status">
            <button type="button" class="seo-chip is-active" data-filter="all">All</button>
            <button type="button" class="seo-chip" data-filter="present">Present</button>
            <button type="button" class="seo-chip" data-filter="in_vicinity">In vicinity</button>
            <button type="button" class="seo-chip" data-filter="absent">Absent</button>
          </div>
        </div>
        <?php if (!empty($guestCounts['truncated'])): ?>
          <div class="seo-search-meta mb-2" id="guestTruncNote">
            Showing first <?= (int)($guestCounts['listed'] ?? count($guests)) ?> of <?= (int)($guestCounts['total'] ?? 0) ?>.
            Use <strong>Find a guest</strong> above to look up anyone not listed.
          </div>
        <?php else: ?>
          <div class="seo-search-meta mb-2" id="guestTruncNote" hidden></div>
        <?php endif; ?>
        <div class="seo-table-wrap">
          <table class="seo-table">
            <thead>
              <tr>
                <th>Guest</th>
                <th>Agency</th>
                <th>Status</th>
                <th>Signed in at</th>
              </tr>
            </thead>
            <tbody id="guestTableBody">
            <?php if (!$guests): ?>
              <tr><td colspan="4"><div class="seo-empty">No non-VIP attendees registered yet.</div></td></tr>
            <?php else: ?>
              <?php foreach ($guests as $guest):
                $status = (string)$guest['guest_status'];
              ?>
              <tr data-status="<?= htmlspecialchars($status, ENT_QUOTES) ?>">
                <td>
                  <span class="seo-name"><?= htmlspecialchars((string)$guest['name'], ENT_QUOTES) ?></span>
                  <?php if (trim((string)($guest['designation'] ?? '')) !== ''): ?>
                    <span class="seo-sub"><?= htmlspecialchars((string)$guest['designation'], ENT_QUOTES) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string)$guest['agency'], ENT_QUOTES) ?></td>
                <td><span class="seo-status seo-status-<?= $statusClass($status) ?>"><?= htmlspecialchars($statusLabel($status), ENT_QUOTES) ?></span></td>
                <td><?= htmlspecialchars((string)($guest['time_in'] ?? '—'), ENT_QUOTES) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
      </div>

      <aside>
        <section class="seo-panel">
          <div class="seo-panel-head">
            <div>
              <h2>Needs attention</h2>
              <p>VIPs still in vicinity</p>
            </div>
          </div>
          <ul class="seo-feed" id="attentionList">
            <?php if (!$attention): ?>
              <li class="seo-empty">All clear — no VIP follow-ups right now.</li>
            <?php else: ?>
              <?php foreach ($attention as $item): ?>
              <li class="seo-feed-item is-alert">
                <div class="seo-avatar"><?= htmlspecialchars($initials((string)$item['name']), ENT_QUOTES) ?></div>
                <div>
                  <div class="seo-feed-title"><?= htmlspecialchars((string)$item['name'], ENT_QUOTES) ?></div>
                  <div class="seo-feed-meta"><?= htmlspecialchars((string)$item['agency'], ENT_QUOTES) ?></div>
                </div>
                <div class="seo-feed-time">Follow up</div>
              </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </section>

        <section class="seo-panel">
          <div class="seo-panel-head">
            <div>
              <h2>Recent VIP check-ins</h2>
              <p>Latest confirmed arrivals</p>
            </div>
          </div>
          <ul class="seo-feed" id="recentVipList">
            <?php if (!$recentVip): ?>
              <li class="seo-empty">No VIP sign-ins yet for this date.</li>
            <?php else: ?>
              <?php foreach ($recentVip as $item): ?>
              <li class="seo-feed-item">
                <div class="seo-avatar"><?= htmlspecialchars($initials((string)$item['name']), ENT_QUOTES) ?></div>
                <div>
                  <div class="seo-feed-title"><?= htmlspecialchars((string)$item['name'], ENT_QUOTES) ?></div>
                  <div class="seo-feed-meta"><?= htmlspecialchars((string)$item['agency'], ENT_QUOTES) ?></div>
                </div>
                <div class="seo-feed-time"><?= htmlspecialchars((string)($item['time_in'] ?? ''), ENT_QUOTES) ?></div>
              </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </section>

        <section class="seo-panel">
          <div class="seo-panel-head">
            <div>
              <h2>Agency roll-up</h2>
              <p>VIP mix by organization</p>
            </div>
          </div>
          <div id="agencyRollupBody">
            <?php if (!$agencyRollup): ?>
              <div class="seo-empty">No VIP agency data yet.</div>
            <?php else: ?>
              <?php foreach ($agencyRollup as $row):
                $total = max(1, (int)$row['total']);
                $p = (int)$row['present'];
                $v = (int)$row['in_vicinity'];
                $a = (int)$row['absent'];
              ?>
              <div class="seo-agency-row">
                <div class="seo-agency-top">
                  <strong><?= htmlspecialchars((string)$row['agency'], ENT_QUOTES) ?></strong>
                  <span><?= $p ?>P · <?= $v ?>V · <?= $a ?>A</span>
                </div>
                <div class="seo-agency-bar">
                  <i class="seo-bar-present" style="width:<?= round(($p / $total) * 100) ?>%"></i>
                  <i class="seo-bar-vicinity" style="width:<?= round(($v / $total) * 100) ?>%"></i>
                  <i class="seo-bar-absent" style="width:<?= round(($a / $total) * 100) ?>%"></i>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>
      </aside>
    </div>
  </div>
</section>

<script src="assets/seo-dashboard.js"></script>
</body>
</html>
