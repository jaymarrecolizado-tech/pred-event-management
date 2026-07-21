(function () {
  'use strict';

  var root = document.getElementById('seoDashRoot');
  var dateInput = document.getElementById('seoDate');
  var goBtn = document.getElementById('seoDateGo');
  var refreshBtn = document.getElementById('seoRefreshNow');
  var filter = 'all';
  var guestFilter = 'all';

  var statusLabel = {
    present: 'Present',
    absent: 'Absent',
    in_vicinity: 'In Vicinity'
  };

  var statusClass = {
    present: 'present',
    absent: 'absent',
    in_vicinity: 'vicinity'
  };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function initials(name) {
    var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    return parts.slice(0, 2).map(function (p) { return p.charAt(0).toUpperCase(); }).join('');
  }

  function setText(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  function applyTableFilter(tbodyId, activeFilter, emptyLabel) {
    var body = document.getElementById(tbodyId);
    if (!body) return;
    var rows = body.querySelectorAll('tr[data-status]');
    var visible = 0;
    rows.forEach(function (row) {
      var show = activeFilter === 'all' || row.getAttribute('data-status') === activeFilter;
      row.style.display = show ? '' : 'none';
      if (show) visible += 1;
    });
    var empty = body.querySelector('.seo-filter-empty');
    if (empty) empty.remove();
    if (rows.length && visible === 0) {
      var tr = document.createElement('tr');
      tr.className = 'seo-filter-empty';
      tr.innerHTML = '<td colspan="4"><div class="seo-empty">No ' + esc(emptyLabel) + ' match this filter.</div></td>';
      body.appendChild(tr);
    }
  }

  function applyFilter() {
    applyTableFilter('vipTableBody', filter, 'VIP guests');
  }

  function applyGuestFilter() {
    applyTableFilter('guestTableBody', guestFilter, 'attendees');
  }

  function compositionWidths(counts) {
    var total = Math.max(0, Number(counts.total) || 0);
    if (!total) return { present: 0, vicinity: 0, absent: 0 };
    var present = Math.round((counts.present / total) * 100);
    var vicinity = Math.round((counts.in_vicinity / total) * 100);
    var absent = Math.max(0, 100 - present - vicinity);
    return { present: present, vicinity: vicinity, absent: absent };
  }

  function guestRowHtml(v) {
    var status = v.guest_status || 'in_vicinity';
    var designation = v.designation
      ? '<span class="seo-sub">' + esc(v.designation) + '</span>'
      : '';
    return (
      '<tr data-status="' + esc(status) + '">' +
        '<td><span class="seo-name">' + esc(v.name) + '</span>' + designation + '</td>' +
        '<td>' + esc(v.agency) + '</td>' +
        '<td><span class="seo-status seo-status-' + (statusClass[status] || 'vicinity') + '">' +
          esc(statusLabel[status] || status) + '</span></td>' +
        '<td>' + esc(v.time_in || '—') + '</td>' +
      '</tr>'
    );
  }

  function renderVipRows(vips) {
    var body = document.getElementById('vipTableBody');
    if (!body) return;
    if (!vips.length) {
      body.innerHTML = '<tr><td colspan="4"><div class="seo-empty">No VIP guests marked yet. Admins can flag VIPs on Registrants.</div></td></tr>';
      return;
    }
    body.innerHTML = vips.map(guestRowHtml).join('');
    applyFilter();
  }

  function renderGuestRows(guests) {
    var body = document.getElementById('guestTableBody');
    if (!body) return;
    if (!guests.length) {
      body.innerHTML = '<tr><td colspan="4"><div class="seo-empty">No non-VIP attendees registered yet.</div></td></tr>';
      return;
    }
    body.innerHTML = guests.map(guestRowHtml).join('');
    applyGuestFilter();
  }

  function renderFeed(listId, items, emptyText, alertMode) {
    var list = document.getElementById(listId);
    if (!items.length) {
      list.innerHTML = '<li class="seo-empty">' + esc(emptyText) + '</li>';
      return;
    }
    list.innerHTML = items.map(function (item) {
      return (
        '<li class="seo-feed-item' + (alertMode ? ' is-alert' : '') + '">' +
          '<div class="seo-avatar">' + esc(initials(item.name)) + '</div>' +
          '<div>' +
            '<div class="seo-feed-title">' + esc(item.name) + '</div>' +
            '<div class="seo-feed-meta">' + esc(item.agency) + '</div>' +
          '</div>' +
          '<div class="seo-feed-time">' + esc(alertMode ? 'Follow up' : (item.time_in || '')) + '</div>' +
        '</li>'
      );
    }).join('');
  }

  function renderAgencies(rows) {
    var host = document.getElementById('agencyRollupBody');
    if (!rows.length) {
      host.innerHTML = '<div class="seo-empty">No VIP agency data yet.</div>';
      return;
    }
    host.innerHTML = rows.map(function (row) {
      var total = Math.max(1, Number(row.total) || 1);
      var p = Number(row.present) || 0;
      var v = Number(row.in_vicinity) || 0;
      var a = Number(row.absent) || 0;
      return (
        '<div class="seo-agency-row">' +
          '<div class="seo-agency-top">' +
            '<strong>' + esc(row.agency) + '</strong>' +
            '<span>' + p + 'P · ' + v + 'V · ' + a + 'A</span>' +
          '</div>' +
          '<div class="seo-agency-bar">' +
            '<i class="seo-bar-present" style="width:' + Math.round((p / total) * 100) + '%"></i>' +
            '<i class="seo-bar-vicinity" style="width:' + Math.round((v / total) * 100) + '%"></i>' +
            '<i class="seo-bar-absent" style="width:' + Math.round((a / total) * 100) + '%"></i>' +
          '</div>' +
        '</div>'
      );
    }).join('');
  }

  function render(data) {
    setText('kpiPresent', data.kpi.dateCount);
    setText('kpiVicinity', data.kpi.vicinityCount);
    setText('kpiAbsent', data.kpi.absentCount);
    setText('kpiRate', data.kpi.attendanceRate + '%');
    setText('kpiRecent', data.kpi.recentCount);
    setText('kpiPeak', data.kpi.peakHourText);
    setText('seoRefreshedAt', new Date(data.refreshedAt).toLocaleTimeString());
    setText('seoDateLabel', data.selectedDate || (dateInput && dateInput.value) || '');
    if (data.event && data.event.name) {
      setText('seoEventName', data.event.name);
    }

    var counts = data.vipCounts || {};
    var widths = compositionWidths(counts);
    document.getElementById('vipCountSummary').innerHTML =
      '<span><i class="seo-swatch present"></i> ' + (counts.present || 0) + ' present</span>' +
      '<span><i class="seo-swatch vicinity"></i> ' + (counts.in_vicinity || 0) + ' vicinity</span>' +
      '<span><i class="seo-swatch absent"></i> ' + (counts.absent || 0) + ' absent</span>' +
      '<span>' + (counts.total || 0) + ' total</span>';

    var bar = document.getElementById('vipCompositionBar');
    if (bar) {
      bar.innerHTML =
        '<i class="seo-bar-present" style="width:' + widths.present + '%"></i>' +
        '<i class="seo-bar-vicinity" style="width:' + widths.vicinity + '%"></i>' +
        '<i class="seo-bar-absent" style="width:' + widths.absent + '%"></i>';
    }

    renderVipRows(data.vips || []);
    renderGuestRows(data.guests || []);

    var gCounts = data.guestCounts || {};
    var guestSummary = document.getElementById('guestCountSummary');
    if (guestSummary) {
      guestSummary.textContent =
        '· ' + (gCounts.total || 0) + ' total' +
        ' · ' + (gCounts.present || 0) + ' present' +
        ' · ' + (gCounts.in_vicinity || 0) + ' vicinity' +
        ' · ' + (gCounts.absent || 0) + ' absent';
    }
    var truncNote = document.getElementById('guestTruncNote');
    if (truncNote) {
      if (gCounts.truncated) {
        truncNote.hidden = false;
        truncNote.innerHTML =
          'Showing first ' + (gCounts.listed || (data.guests || []).length) +
          ' of ' + (gCounts.total || 0) +
          '. Use <strong>Find a guest</strong> above to look up anyone not listed.';
      } else {
        truncNote.hidden = true;
      }
    }

    renderFeed('attentionList', data.attention || [], 'All clear — no VIP follow-ups right now.', true);
    renderFeed('recentVipList', data.recentVip || [], 'No VIP sign-ins yet for this date.', false);
    renderAgencies(data.agencyRollup || []);
  }

  function load() {
    if (!dateInput) return;
    if (root) root.classList.add('seo-refreshing');
    var date = dateInput.value || '';
    fetch('?r=admin_seo_summary&date=' + encodeURIComponent(date), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(render)
      .catch(function () {})
      .finally(function () {
        if (root) root.classList.remove('seo-refreshing');
      });
  }

  document.querySelectorAll('#vipFilters .seo-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      document.querySelectorAll('#vipFilters .seo-chip').forEach(function (c) {
        c.classList.remove('is-active');
      });
      chip.classList.add('is-active');
      filter = chip.getAttribute('data-filter') || 'all';
      applyFilter();
    });
  });

  document.querySelectorAll('#guestFilters .seo-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      document.querySelectorAll('#guestFilters .seo-chip').forEach(function (c) {
        c.classList.remove('is-active');
      });
      chip.classList.add('is-active');
      guestFilter = chip.getAttribute('data-filter') || 'all';
      applyGuestFilter();
    });
  });

  if (goBtn) {
    goBtn.addEventListener('click', function () {
      var date = dateInput.value || '';
      window.location = '?r=admin_seo_dashboard&date=' + encodeURIComponent(date);
    });
  }

  if (refreshBtn) {
    refreshBtn.addEventListener('click', load);
  }

  // Guest search (VIPs + all attendees)
  var searchInput = document.getElementById('seoSearchInput');
  var searchClear = document.getElementById('seoSearchClear');
  var searchResults = document.getElementById('seoSearchResults');
  var searchWrap = document.getElementById('seoSearchResultsWrap');
  var searchEmpty = document.getElementById('seoSearchEmpty');
  var searchMeta = document.getElementById('seoSearchMeta');
  var searchScope = 'all';
  var searchTimer = null;

  function renderSearchResults(payload) {
    var rows = payload.results || [];
    if (searchClear) {
      searchClear.style.display = (payload.q && payload.q.length) ? '' : 'none';
    }
    if (!payload.q || payload.q.length < 2) {
      if (searchWrap) searchWrap.hidden = true;
      if (searchEmpty) searchEmpty.hidden = true;
      if (searchMeta) searchMeta.hidden = true;
      return;
    }
    if (searchMeta) {
      searchMeta.hidden = false;
      searchMeta.textContent = rows.length
        ? (rows.length + ' result' + (rows.length === 1 ? '' : 's') +
          ' · ' + (payload.scope === 'vip' ? 'VIP only' : 'all attendees') +
          ' · ' + (payload.selectedDate || ''))
        : ('No matches · ' + (payload.scope === 'vip' ? 'VIP only' : 'all attendees'));
    }
    if (!rows.length) {
      if (searchWrap) searchWrap.hidden = true;
      if (searchEmpty) searchEmpty.hidden = false;
      return;
    }
    if (searchEmpty) searchEmpty.hidden = true;
    if (searchWrap) searchWrap.hidden = false;
    searchResults.innerHTML = rows.map(function (r) {
      var status = r.guest_status || 'in_vicinity';
      var designation = r.designation
        ? '<span class="seo-sub">' + esc(r.designation) + '</span>'
        : '';
      var vip = r.is_vip
        ? '<span class="seo-vip-pill">VIP</span>'
        : '<span class="seo-vip-pill is-muted">Guest</span>';
      return (
        '<tr>' +
          '<td><span class="seo-name">' + esc(r.name) + '</span>' + designation + '</td>' +
          '<td>' + esc(r.agency) + '</td>' +
          '<td>' + vip + '</td>' +
          '<td><span class="seo-status seo-status-' + (statusClass[status] || 'vicinity') + '">' +
            esc(statusLabel[status] || status) + '</span></td>' +
          '<td>' + esc(r.time_in || '—') + '</td>' +
        '</tr>'
      );
    }).join('');
  }

  function runSearch() {
    if (!searchInput) return;
    var q = String(searchInput.value || '').trim();
    if (q.length < 2) {
      renderSearchResults({ results: [], q: q, scope: searchScope });
      return;
    }
    var date = (dateInput && dateInput.value) || '';
    var url = '?r=admin_seo_search&q=' + encodeURIComponent(q) +
      '&scope=' + encodeURIComponent(searchScope) +
      '&date=' + encodeURIComponent(date);
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(renderSearchResults)
      .catch(function () {
        renderSearchResults({ results: [], q: q, scope: searchScope });
      });
  }

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(runSearch, 280);
    });
    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(searchTimer);
        runSearch();
      }
    });
  }

  if (searchClear) {
    searchClear.addEventListener('click', function () {
      if (searchInput) searchInput.value = '';
      renderSearchResults({ results: [], q: '', scope: searchScope });
      searchInput && searchInput.focus();
    });
  }

  document.querySelectorAll('#seoSearchScope .seo-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      document.querySelectorAll('#seoSearchScope .seo-chip').forEach(function (c) {
        c.classList.remove('is-active');
      });
      chip.classList.add('is-active');
      searchScope = chip.getAttribute('data-scope') || 'all';
      runSearch();
    });
  });

  setInterval(load, 20000);
})();
