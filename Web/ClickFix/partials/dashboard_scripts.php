<script>
    const appHeader = document.getElementById('app-header');

    function syncStickyHeaderOffset() {
      if (!appHeader) {
        return;
      }
      const rect = appHeader.getBoundingClientRect();
      const computed = window.getComputedStyle(appHeader);
      const marginBottom = parseFloat(computed.marginBottom || '0') || 0;
      const offset = Math.ceil(rect.height + marginBottom + 20);
      document.documentElement.style.setProperty('--sticky-header-offset', `${offset}px`);
    }

    syncStickyHeaderOffset();
    window.addEventListener('resize', syncStickyHeaderOffset);
    window.addEventListener('load', syncStickyHeaderOffset);

    window.addEventListener('resize', syncStickyHeaderOffset);

    const selectedInvestigation = <?= $selectedInvestigationJson; ?>;
    const sharedInvestigation = <?= $sharedGraphJson; ?>;
    const eventWorkbenchData = <?= $eventWorkbenchJson; ?>;
    const intelApiLookupMapRows = <?= $intelApiLookupMapRowsJson; ?>;
    const intelApiCommonKeywords = <?= $intelApiCommonKeywordsJson; ?>;
    const eventFeed = document.getElementById('event-feed');
    const eventItems = eventFeed ? Array.from(eventFeed.querySelectorAll('.event-feed-item')) : [];
    const eventEmpty = document.getElementById('event-empty');
    const eventDetail = document.getElementById('event-detail');
    const eventTitle = document.getElementById('event-title');
    const eventBadges = document.getElementById('event-badges');
    const eventTime = document.getElementById('event-time');
    const eventCountry = document.getElementById('event-country');
    const eventUrl = document.getElementById('event-url');
    const eventPrevUrl = document.getElementById('event-prev-url');
    const eventIp = document.getElementById('event-ip');
    const eventExtension = document.getElementById('event-extension');
    const eventDomainHistory = document.getElementById('event-domain-history');
    const eventIpHistory = document.getElementById('event-ip-history');
    const eventIoc = document.getElementById('event-ioc');
    const eventIocHash = document.getElementById('event-ioc-hash');
    const eventIocName = document.getElementById('event-ioc-name');
    const eventIocPath = document.getElementById('event-ioc-path');
    const eventIocSite = document.getElementById('event-ioc-site');
    const eventIocDate = document.getElementById('event-ioc-date');
    const eventReasons = document.getElementById('event-reasons');
    const eventSnippets = document.getElementById('event-snippets');
    const eventContextTitle = document.getElementById('event-context-title');
    const eventContext = document.getElementById('event-context');
    const eventRaw = document.getElementById('event-raw');
    const eventSignals = document.getElementById('event-signals');
    const eventScoreDetails = document.getElementById('event-score-details');
    const eventRelatedLoad = document.getElementById('event-related-load');
    const eventRelatedStatus = document.getElementById('event-related-status');
    const eventRelatedWrap = document.getElementById('event-related-wrap');
    const eventRelatedBody = document.getElementById('event-related-body');
    const eventMitreGrid = document.getElementById('event-mitre-grid');
    const eventMitreEmpty = document.getElementById('event-mitre-empty');
    const canViewExactEventContext = <?= $canViewExactEventContext ? 'true' : 'false'; ?>;
    const eventReviewForm = document.getElementById('event-review-form');
    const eventReviewId = document.getElementById('event-review-id');
    const eventReviewStatus = document.getElementById('event-review-status');
    const eventQuickForms = Array.from(document.querySelectorAll('.event-quick-form'));
    const focusReportId = <?= $focusReportId; ?>;
    const msgScope = document.getElementById('msg-scope');
    const msgClientIds = document.getElementById('msg-client-ids');
    const msgUserIds = document.getElementById('msg-user-ids');
    const sessionExpiresAt = <?= (int) $sessionExpiresAt; ?>;
    const sessionWarningMinutes = <?= (int) $sessionWarningMinutes; ?>;
    const sessionExtendMinutes = <?= (int) $sessionExtendMinutes; ?>;
    const csrfToken = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const homeLeafletCssUrl = <?= json_encode($leafletCssUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const homeLeafletJsUrl = <?= json_encode($leafletJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const homeLeafletWorldGeoJsonUrl = <?= json_encode($leafletWorldGeoJsonUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    let leafletEnsurePromise = null;
    let localWorldGeoPromise = null;

    const displayPanel = document.getElementById('display-settings-panel');
    const displayToggleBtn = document.getElementById('display-settings-toggle');
    const displayCloseBtn = document.getElementById('display-settings-close');
    const displayStorageKey = 'cf_display_settings_v1';
    const displayDefaults = {
      dark: true,
      contrast: false,
      compact: false,
      reducedMotion: false,
      decorations: true,
      accent: 'blue',
      font: 'jakarta',
      template: true,
      layout: 'integrated'
    };
    function applyDisplaySettings(settings) {
      const body = document.body;
      if (!body) return;
      body.classList.toggle('ui-light', !settings.dark);
      body.classList.toggle('ui-contrast', !!settings.contrast);
      body.classList.toggle('ui-compact', !!settings.compact);
      body.classList.toggle('ui-reduced-motion', !!settings.reducedMotion);
      body.classList.toggle('ui-no-decor', !settings.decorations);
      body.classList.toggle('template-corona', !!settings.template);
      ['blue','green','purple','amber','red','cyan'].forEach((key) => body.classList.remove(`ui-accent-${key}`));
      if (settings.accent) body.classList.add(`ui-accent-${settings.accent}`);
      ['jakarta','public','dm','nunito','sora','arial','helvetica','ubuntu','roboto'].forEach((key) => body.classList.remove(`ui-font-${key}`));
      if (settings.font) body.classList.add(`ui-font-${settings.font}`);
      ['integrated','wide','split','focused','compact','minimal'].forEach((key) => body.classList.remove(`ui-layout-${key}`));
      if (settings.layout) body.classList.add(`ui-layout-${settings.layout}`);
      requestNodeBackgroundRedraw();
    }
    function loadDisplaySettings() {
      try {
        const raw = localStorage.getItem(displayStorageKey);
        if (!raw) return { ...displayDefaults };
        const parsed = JSON.parse(raw);
        return { ...displayDefaults, ...(parsed || {}) };
      } catch (error) {
        return { ...displayDefaults };
      }
    }
    function saveDisplaySettings(settings) {
      try {
        localStorage.setItem(displayStorageKey, JSON.stringify(settings));
      } catch (error) {
        // ignore
      }
    }
    function syncDisplayInputs(settings) {
      document.querySelectorAll('[data-setting]').forEach((input) => {
        const key = String(input.getAttribute('data-setting') || '');
        if (!key) return;
        input.checked = !!settings[key];
      });
    }
    window.addEventListener('DOMContentLoaded', () => {
      let current = loadDisplaySettings();
      applyDisplaySettings(current);
      syncDisplayInputs(current);
      if (displayToggleBtn && displayPanel) {
        displayToggleBtn.addEventListener('click', () => {
          displayPanel.classList.toggle('open');
          displayPanel.setAttribute('aria-hidden', displayPanel.classList.contains('open') ? 'false' : 'true');
        });
      }
      if (displayCloseBtn && displayPanel) {
        displayCloseBtn.addEventListener('click', () => {
          displayPanel.classList.remove('open');
          displayPanel.setAttribute('aria-hidden', 'true');
        });
      }
      document.addEventListener('click', (ev) => {
        if (!displayPanel || !displayPanel.classList.contains('open')) return;
        const target = ev.target;
        if (!(target instanceof HTMLElement)) return;
        if (displayPanel.contains(target) || (displayToggleBtn && displayToggleBtn.contains(target))) return;
        displayPanel.classList.remove('open');
        displayPanel.setAttribute('aria-hidden', 'true');
      });
      document.querySelectorAll('[data-setting]').forEach((input) => {
        input.addEventListener('change', () => {
          const key = String(input.getAttribute('data-setting') || '');
          if (!key) return;
          current = { ...current, [key]: !!input.checked };
          applyDisplaySettings(current);
          saveDisplaySettings(current);
        });
      });
      document.querySelectorAll('[data-accent]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const accent = String(btn.getAttribute('data-accent') || '');
          if (!accent) return;
          current = { ...current, accent };
          applyDisplaySettings(current);
          saveDisplaySettings(current);
        });
      });
      document.querySelectorAll('[data-font]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const font = String(btn.getAttribute('data-font') || '');
          if (!font) return;
          current = { ...current, font };
          applyDisplaySettings(current);
          saveDisplaySettings(current);
        });
      });
      document.querySelectorAll('[data-preset]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const preset = String(btn.getAttribute('data-preset') || '');
          const accent = String(btn.getAttribute('data-accent') || '');
          if (!preset) return;
          current = { ...current, layout: preset };
          if (accent) current.accent = accent;
          applyDisplaySettings(current);
          saveDisplaySettings(current);
        });
      });

      const avatarInput = document.getElementById('self-avatar-url');
      const avatarHelp = document.getElementById('avatar-source-help');
      document.querySelectorAll('[data-avatar-source]').forEach((btn) => {
        btn.addEventListener('click', () => {
          if (!avatarInput) return;
          const source = String(btn.getAttribute('data-avatar-source') || '');
          const handle = String(btn.getAttribute('data-avatar-handle') || '').trim();
          let url = '';
          if (source === 'github' && handle) {
            url = `https://github.com/${handle}.png`;
          } else if (handle && /^https?:\\/\\//i.test(handle)) {
            url = handle;
          }
          if (url) {
            avatarInput.value = url;
            if (avatarHelp) avatarHelp.textContent = 'URL aplicada. Guarda ajustes para actualizar tu avatar.';
          } else {
            if (avatarHelp) avatarHelp.textContent = 'Añade un handle válido o pega la URL pública del avatar y vuelve a intentar.';
          }
        });
      });
    });

    const MITRE_TACTIC_ORDER = [
      'Initial Access',
      'Execution',
      'Persistence',
      'Privilege Escalation',
      'Defense Evasion',
      'Credential Access',
      'Discovery',
      'Lateral Movement',
      'Collection',
      'Command and Control',
      'Exfiltration',
      'Impact',
    ];

    const MITRE_LIBRARY = [
      { id: 'T1059.003', name: 'Windows Command Shell', tactic: 'Execution', patterns: [/\bcmd(?:\.exe)?\b/i, /\/c\s+[^\n]+/i, /\bcommand prompt\b/i] },
      { id: 'T1059.001', name: 'PowerShell', tactic: 'Execution', patterns: [/\bpowershell(?:\.exe)?\b/i, /\bpwsh\b/i, /\bIEX\b/i, /\binvoke-expression\b/i] },
      { id: 'T1059.005', name: 'Visual Basic', tactic: 'Execution', patterns: [/\bwscript\b/i, /\bcscript\b/i, /\.vbs\b/i, /\bvbscript\b/i] },
      { id: 'T1218.005', name: 'Mshta', tactic: 'Defense Evasion', patterns: [/\bmshta\b/i, /\.hta\b/i] },
      { id: 'T1218.011', name: 'Rundll32', tactic: 'Defense Evasion', patterns: [/\brundll32(?:\.exe)?\b/i] },
      { id: 'T1218.010', name: 'Regsvr32', tactic: 'Defense Evasion', patterns: [/\bregsvr32(?:\.exe)?\b/i] },
      { id: 'T1047', name: 'Windows Management Instrumentation', tactic: 'Execution', patterns: [/\bwmic\b/i, /\bwmiprvse\b/i, /win32_process/i] },
      { id: 'T1053.005', name: 'Scheduled Task', tactic: 'Persistence', patterns: [/\bschtasks\b/i, /\/create\s+\/tn/i] },
      { id: 'T1105', name: 'Ingress Tool Transfer', tactic: 'Command and Control', patterns: [/\bcurl\b/i, /\bwget\b/i, /\bInvoke-WebRequest\b/i, /\bcertutil\b/i, /\bbitsadmin\b/i] },
      { id: 'T1071.001', name: 'Web Protocols', tactic: 'Command and Control', patterns: [/https?:\/\//i, /\bwinhttp\b/i] },
      { id: 'T1027', name: 'Obfuscated/Encoded File or Information', tactic: 'Defense Evasion', patterns: [/\bbase64\b/i, /\bencoded\b/i, /\b-enc\b/i, /frombase64string/i] },
      { id: 'T1021.002', name: 'SMB/Windows Admin Shares', tactic: 'Lateral Movement', patterns: [/\bnet\s+use\b/i, /\\\\[a-z0-9\.\-]+\\[a-z0-9$]+/i] },
      { id: 'T1218.007', name: 'Msiexec', tactic: 'Defense Evasion', patterns: [/\bmsiexec\b/i, /\/i\s+https?:\/\//i] },
      { id: 'T1204.002', name: 'User Execution: Malicious File', tactic: 'Execution', patterns: [/\.exe\b/i, /\.scr\b/i, /\.msi\b/i, /\.bat\b/i] },
    ];

    function extractMitreMatches(sourceText) {
      const text = String(sourceText || '');
      if (!text) return [];
      const matches = new Map();
      for (const entry of MITRE_LIBRARY) {
        if (!entry || !Array.isArray(entry.patterns)) continue;
        if (entry.patterns.some((pattern) => pattern.test(text))) {
          matches.set(entry.id, entry);
        }
      }
      return Array.from(matches.values());
    }

    function groupMitreByTactic(matches) {
      const grouped = {};
      (matches || []).forEach((entry) => {
        const tactic = String(entry.tactic || 'Other');
        if (!grouped[tactic]) grouped[tactic] = [];
        grouped[tactic].push(entry);
      });
      return grouped;
    }

    function renderMitreBlueprint(container, emptyNode, matches) {
      if (!container) return;
      const list = Array.isArray(matches) ? matches : [];
      container.innerHTML = '';
      if (!list.length) {
        if (emptyNode) emptyNode.hidden = false;
        return;
      }
      if (emptyNode) emptyNode.hidden = true;
      const grouped = groupMitreByTactic(list);
      const tactics = [...MITRE_TACTIC_ORDER, ...Object.keys(grouped).filter((t) => !MITRE_TACTIC_ORDER.includes(t))];
      tactics.forEach((tactic) => {
        const entries = grouped[tactic];
        if (!entries || !entries.length) return;
        const card = document.createElement('div');
        card.className = 'mitre-tactic';
        const title = document.createElement('div');
        title.className = 'mitre-tactic-title';
        title.textContent = tactic;
        const listWrap = document.createElement('div');
        listWrap.className = 'mitre-tech-list';
        entries.forEach((entry) => {
          const chip = document.createElement('div');
          chip.className = 'mitre-tech';
          const id = document.createElement('b');
          id.textContent = entry.id;
          const name = document.createElement('span');
          name.textContent = entry.name;
          chip.appendChild(id);
          chip.appendChild(name);
          listWrap.appendChild(chip);
        });
        card.appendChild(title);
        card.appendChild(listWrap);
        container.appendChild(card);
      });
    }

    function decodeBase64Candidate(value) {
      const raw = String(value || '').trim();
      if (!raw || raw.length < 24) return '';
      if (!/^[A-Za-z0-9+/=]+$/.test(raw)) return '';
      try {
        const decoded = atob(raw.replace(/\s+/g, ''));
        const printable = decoded.replace(/[^\x09\x0A\x0D\x20-\x7E]/g, '');
        if (printable.length < Math.floor(decoded.length * 0.6)) {
          return '';
        }
        return decoded;
      } catch (error) {
        return '';
      }
    }

    function expandMitreSource(source) {
      const base = String(source || '');
      if (!base) return '';
      const extras = [];
      if (/%[0-9A-Fa-f]{2}/.test(base)) {
        try {
          const decoded = decodeURIComponent(base);
          if (decoded && decoded !== base) extras.push(decoded);
        } catch (error) {
          // ignore decode errors
        }
      }
      const base64Candidates = base.match(/[A-Za-z0-9+/=]{24,}/g) || [];
      base64Candidates.slice(0, 6).forEach((candidate) => {
        const decoded = decodeBase64Candidate(candidate);
        if (decoded) extras.push(decoded);
      });
      const cleaned = base.replace(/[`^]/g, '');
      if (cleaned !== base) extras.push(cleaned);
      return [base, ...extras].filter(Boolean).join('\n');
    }

    function buildMitreSourceFromEvent(event) {
      const parts = [
        event?.message,
        event?.detected_content,
        event?.full_context,
        event?.url,
        event?.previous_url,
        Array.isArray(event?.snippets) ? event.snippets.join('\n') : '',
        Array.isArray(event?.signals) ? event.signals.join('\n') : '',
      ];
      const raw = parts.filter(Boolean).join('\n');
      return expandMitreSource(raw);
    }

    function updateEventMitre(event) {
      if (!eventMitreGrid) return;
      const source = buildMitreSourceFromEvent(event);
      const matches = extractMitreMatches(source);
      renderMitreBlueprint(eventMitreGrid, eventMitreEmpty, matches);
    }

    function updateInvestigationMitre() {
      const container = document.getElementById('investigation-mitre-grid');
      const emptyNode = document.getElementById('investigation-mitre-empty');
      const wrapper = document.getElementById('investigation-mitre');
      if (!wrapper || !container) return;
      const source = expandMitreSource(wrapper.getAttribute('data-mitre-source') || '');
      const matches = extractMitreMatches(source);
      renderMitreBlueprint(container, emptyNode, matches);
    }

    function updateSharedInvestigationMitre() {
      const container = document.getElementById('shared-mitre-grid');
      const emptyNode = document.getElementById('shared-mitre-empty');
      const wrapper = document.getElementById('shared-mitre');
      if (!wrapper || !container) return;
      const source = expandMitreSource(wrapper.getAttribute('data-mitre-source') || '');
      const matches = extractMitreMatches(source);
      renderMitreBlueprint(container, emptyNode, matches);
    }

    function updateSourceAlertMitre() {
      const container = document.getElementById('source-alert-mitre-grid');
      const emptyNode = document.getElementById('source-alert-mitre-empty');
      const wrapper = document.getElementById('source-alert-mitre');
      if (!wrapper || !container) return;
      const source = expandMitreSource(wrapper.getAttribute('data-mitre-source') || '');
      const matches = extractMitreMatches(source);
      renderMitreBlueprint(container, emptyNode, matches);
    }

    const syncMessagingScope = () => {
      if (!msgScope) {
        return;
      }
      const scope = String(msgScope.value || 'all');
      if (msgClientIds) {
        const enabled = scope === 'client';
        msgClientIds.disabled = !enabled;
        msgClientIds.required = enabled;
        if (!enabled) {
          msgClientIds.value = '';
        }
      }
      if (msgUserIds) {
        const enabled = scope === 'user';
        msgUserIds.disabled = !enabled;
        msgUserIds.required = enabled;
        if (!enabled) {
          Array.from(msgUserIds.options).forEach((option) => {
            option.selected = false;
          });
        }
      }
    };
    if (msgScope) {
      msgScope.addEventListener('change', syncMessagingScope);
      syncMessagingScope();
    }

    (function initSessionTimeoutModal() {
      if (!sessionExpiresAt || sessionExpiresAt <= 0) {
        return;
      }
      const modal = document.getElementById('session-timeout-modal');
      const countdown = document.getElementById('session-timeout-countdown');
      const extendBtn = document.getElementById('session-extend-btn');
      const logoutBtn = document.getElementById('session-logout-btn');
      if (!modal || !countdown || !extendBtn || !logoutBtn) {
        return;
      }
      let expiresAt = Number(sessionExpiresAt || 0) * 1000;
      let warningTimer = null;
      let tickTimer = null;

      const renderCountdown = () => {
        const remainingMs = Math.max(0, expiresAt - Date.now());
        const totalSeconds = Math.floor(remainingMs / 1000);
        const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
        const seconds = String(totalSeconds % 60).padStart(2, '0');
        countdown.textContent = `${minutes}:${seconds}`;
        if (remainingMs <= 0) {
          window.location.href = 'dashboard.php?page=access&public=1';
        }
      };

      const showModal = () => {
        modal.hidden = false;
        renderCountdown();
        if (tickTimer) window.clearInterval(tickTimer);
        tickTimer = window.setInterval(renderCountdown, 1000);
      };

      const scheduleWarning = () => {
        if (warningTimer) window.clearTimeout(warningTimer);
        const warningAt = expiresAt - (sessionWarningMinutes * 60 * 1000);
        const delay = Math.max(0, warningAt - Date.now());
        warningTimer = window.setTimeout(showModal, delay);
      };

      extendBtn.addEventListener('click', async () => {
        const formData = new FormData();
        formData.set('action', 'session_extend');
        formData.set('csrf_token', String(csrfToken || ''));
        try {
          const response = await fetch('dashboard.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
          });
          const payload = await response.json();
          if (payload && payload.status === 'ok' && payload.expires_at) {
            expiresAt = Number(payload.expires_at) * 1000;
            modal.hidden = true;
            if (tickTimer) window.clearInterval(tickTimer);
            scheduleWarning();
          }
        } catch (error) {
          // ignore
        }
      });

      logoutBtn.addEventListener('click', () => {
        window.location.href = 'dashboard.php?page=access&public=1';
      });

      scheduleWarning();
    })();

    (function initIntelAutosave() {
      const intelSaveForm = document.getElementById('intel-save-form');
      if (!intelSaveForm) {
        return;
      }
      const status = document.getElementById('intel-autosave-status');
      let dirty = false;
      let saveInFlight = false;

      const markDirty = () => {
        dirty = true;
        if (status) {
          status.textContent = 'Autosave: cambios pendientes...';
        }
      };

      intelSaveForm.addEventListener('input', markDirty);
      intelSaveForm.addEventListener('change', markDirty);

      const runAutosave = async () => {
        if (!dirty || saveInFlight) {
          return;
        }
        saveInFlight = true;
        const formData = new FormData(intelSaveForm);
        formData.set('auto_save', '1');
        try {
          const response = await fetch('dashboard.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
          });
          const payload = await response.json();
          if (payload && payload.status === 'ok') {
            dirty = false;
            const graphIdInput = document.getElementById('intel-graph-id');
            if (graphIdInput && payload.graph_id) {
              graphIdInput.value = String(payload.graph_id);
            }
            if (status) {
              status.textContent = `Autosave: ${String(payload.saved_at || '')}`;
            }
          }
        } catch (error) {
          if (status) {
            status.textContent = 'Autosave: error';
          }
        } finally {
          saveInFlight = false;
        }
      };

      setInterval(runAutosave, 45000);
    })();

    const escapeHtml = (value) =>
      String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const escapeRegex = (value) =>
      String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    function boldMatchedSnippets(source, snippets) {
      const text = String(source || '');
      const list = Array.isArray(snippets) ? snippets.filter(Boolean) : [];
      if (!text || !list.length) {
        return escapeHtml(text);
      }
      const ranges = [];
      const lowerSource = text.toLowerCase();
      list.forEach((snippet) => {
        const raw = String(snippet || '').trim();
        if (!raw) return;
        const needle = raw.toLowerCase();
        let cursor = 0;
        while (cursor < lowerSource.length) {
          const idx = lowerSource.indexOf(needle, cursor);
          if (idx === -1) break;
          ranges.push({ start: idx, end: idx + needle.length });
          cursor = idx + needle.length;
        }
      });
      if (!ranges.length) {
        return escapeHtml(text);
      }
      ranges.sort((a, b) => a.start - b.start || b.end - a.end);
      const merged = [];
      ranges.forEach((range) => {
        const last = merged[merged.length - 1];
        if (last && range.start <= last.end) {
          last.end = Math.max(last.end, range.end);
        } else {
          merged.push({ ...range });
        }
      });
      let output = '';
      let cursor = 0;
      merged.forEach((range) => {
        output += escapeHtml(text.slice(cursor, range.start));
        output += '<strong>' + escapeHtml(text.slice(range.start, range.end)) + '</strong>';
        cursor = range.end;
      });
      output += escapeHtml(text.slice(cursor));
      return output;
    }

    function parseChartData(canvas) {
      if (!canvas) {
        return { labels: [], alerts: [], blocks: [] };
      }
      const parseArray = (raw) => {
        try {
          const value = JSON.parse(String(raw || '[]'));
          return Array.isArray(value) ? value : [];
        } catch (error) {
          return [];
        }
      };
      return {
        labels: parseArray(canvas.dataset.labels),
        alerts: parseArray(canvas.dataset.alerts).map((v) => Number(v || 0)),
        blocks: parseArray(canvas.dataset.blocks).map((v) => Number(v || 0))
      };
    }

    function setupCanvasSize(canvas) {
      if (!canvas) return null;
      const width = Math.max(240, Math.floor(canvas.clientWidth || 240));
      const height = Math.max(120, Math.floor(canvas.clientHeight || 180));
      const ratio = Math.max(1, Math.floor(window.devicePixelRatio || 1));
      canvas.width = width * ratio;
      canvas.height = height * ratio;
      const ctx = canvas.getContext('2d');
      if (!ctx) return null;
      ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
      return { ctx, width, height };
    }

    function drawTrendChart(canvas) {
      const parsed = parseChartData(canvas);
      const setup = setupCanvasSize(canvas);
      if (!setup) return;
      const { ctx, width, height } = setup;
      ctx.clearRect(0, 0, width, height);
      const labels = parsed.labels;
      const alerts = parsed.alerts;
      const blocks = parsed.blocks;
      const count = Math.min(labels.length, alerts.length, blocks.length);
      if (count <= 0) return;
      const maxValue = Math.max(1, ...alerts, ...blocks);
      const padLeft = 28;
      const padRight = 8;
      const padTop = 10;
      const padBottom = 18;
      const plotW = Math.max(40, width - padLeft - padRight);
      const plotH = Math.max(40, height - padTop - padBottom);

      ctx.strokeStyle = '#1d3a55';
      ctx.lineWidth = 1;
      for (let i = 0; i <= 4; i += 1) {
        const y = padTop + (plotH * i) / 4;
        ctx.beginPath();
        ctx.moveTo(padLeft, y);
        ctx.lineTo(width - padRight, y);
        ctx.stroke();
      }

      const drawSeries = (series, color) => {
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.beginPath();
        for (let i = 0; i < count; i += 1) {
          const x = padLeft + (plotW * (count === 1 ? 0.5 : i / (count - 1)));
          const y = padTop + plotH - (plotH * Number(series[i] || 0)) / maxValue;
          if (i === 0) ctx.moveTo(x, y);
          else ctx.lineTo(x, y);
        }
        ctx.stroke();
      };

      drawSeries(alerts, '#14b8ff');
      drawSeries(blocks, '#38d17a');
    }

    function drawRatioChart(canvas) {
      const parsed = parseChartData(canvas);
      const setup = setupCanvasSize(canvas);
      if (!setup) return;
      const { ctx, width, height } = setup;
      ctx.clearRect(0, 0, width, height);
      const alerts = parsed.alerts;
      const blocks = parsed.blocks;
      const count = Math.min(alerts.length, blocks.length);
      if (count <= 0) return;
      const padLeft = 16;
      const padRight = 8;
      const padTop = 10;
      const padBottom = 18;
      const plotW = Math.max(40, width - padLeft - padRight);
      const plotH = Math.max(40, height - padTop - padBottom);
      const barGap = 4;
      const barWidth = Math.max(2, (plotW - barGap * (count - 1)) / count);

      ctx.strokeStyle = '#1d3a55';
      ctx.lineWidth = 1;
      for (let i = 0; i <= 4; i += 1) {
        const y = padTop + (plotH * i) / 4;
        ctx.beginPath();
        ctx.moveTo(padLeft, y);
        ctx.lineTo(width - padRight, y);
        ctx.stroke();
      }

      for (let i = 0; i < count; i += 1) {
        const alertValue = Number(alerts[i] || 0);
        const blockValue = Number(blocks[i] || 0);
        const ratio = alertValue > 0 ? Math.min(100, (blockValue / alertValue) * 100) : 0;
        const barH = (plotH * ratio) / 100;
        const x = padLeft + i * (barWidth + barGap);
        const y = padTop + plotH - barH;
        ctx.fillStyle = '#ffd166';
        ctx.fillRect(x, y, barWidth, barH);
      }
    }

    initNodeBackground();

    let nodeBgCanvas = null;
    let nodeBgCtx = null;
    let nodeBgNodes = [];
    let nodeBgRaf = null;
    let nodeBgDpr = Math.max(1, window.devicePixelRatio || 1);
    function buildNodeBackground() {
      if (!nodeBgCanvas) return;
      const width = Math.max(320, window.innerWidth || 0);
      const height = Math.max(320, window.innerHeight || 0);
      nodeBgDpr = Math.max(1, window.devicePixelRatio || 1);
      nodeBgCanvas.width = Math.floor(width * nodeBgDpr);
      nodeBgCanvas.height = Math.floor(height * nodeBgDpr);
      nodeBgCanvas.style.width = `${width}px`;
      nodeBgCanvas.style.height = `${height}px`;
      nodeBgCtx.setTransform(nodeBgDpr, 0, 0, nodeBgDpr, 0, 0);
      const area = width * height;
      const count = Math.min(70, Math.max(24, Math.floor(area / 45000)));
      nodeBgNodes = Array.from({ length: count }, () => ({
        x: Math.random() * width,
        y: Math.random() * height,
        vx: (Math.random() - 0.5) * 0.5,
        vy: (Math.random() - 0.5) * 0.5,
        r: 1.5 + Math.random() * 1.4
      }));
    }
    function nodeColorToRgba(color, alpha) {
      if (!color) return `rgba(91,139,255,${alpha})`;
      const raw = color.trim();
      if (raw.startsWith('rgb')) {
        return raw.replace('rgb', 'rgba').replace(')', `, ${alpha})`);
      }
      if (raw.startsWith('#')) {
        const hex = raw.replace('#', '');
        const full = hex.length === 3 ? hex.split('').map((c) => c + c).join('') : hex;
        const int = parseInt(full, 16);
        if (!Number.isNaN(int)) {
          const r = (int >> 16) & 255;
          const g = (int >> 8) & 255;
          const b = int & 255;
          return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }
      }
      return `rgba(91,139,255,${alpha})`;
    }
    function drawNodeBackground() {
      if (!nodeBgCanvas || !nodeBgCtx) return;
      const body = document.body;
      if (!body || body.classList.contains('ui-no-decor')) {
        nodeBgCtx.clearRect(0, 0, nodeBgCanvas.width, nodeBgCanvas.height);
        return;
      }
      const width = nodeBgCanvas.width / nodeBgDpr;
      const height = nodeBgCanvas.height / nodeBgDpr;
      const reduced = body.classList.contains('ui-reduced-motion');
      nodeBgCtx.clearRect(0, 0, width, height);
      const accent = getComputedStyle(body).getPropertyValue('--cf-accent').trim()
        || getComputedStyle(body).getPropertyValue('--accent').trim()
        || '#5b8bff';
      const lineAlpha = body.classList.contains('ui-light') ? 0.14 : 0.28;
      const dotAlpha = body.classList.contains('ui-light') ? 0.35 : 0.6;
      const maxDist = Math.min(180, Math.max(120, Math.min(width, height) * 0.18));

      if (!reduced) {
        nodeBgNodes.forEach((node) => {
          node.x += node.vx;
          node.y += node.vy;
          if (node.x < 0 || node.x > width) node.vx *= -1;
          if (node.y < 0 || node.y > height) node.vy *= -1;
        });
      }

      for (let i = 0; i < nodeBgNodes.length; i++) {
        const a = nodeBgNodes[i];
        for (let j = i + 1; j < nodeBgNodes.length; j++) {
          const b = nodeBgNodes[j];
          const dx = a.x - b.x;
          const dy = a.y - b.y;
          const dist = Math.hypot(dx, dy);
          if (dist > maxDist) continue;
          const alpha = (1 - dist / maxDist) * lineAlpha;
          nodeBgCtx.strokeStyle = nodeColorToRgba(accent, alpha);
          nodeBgCtx.lineWidth = 1;
          nodeBgCtx.beginPath();
          nodeBgCtx.moveTo(a.x, a.y);
          nodeBgCtx.lineTo(b.x, b.y);
          nodeBgCtx.stroke();
        }
      }
      nodeBgNodes.forEach((node) => {
        nodeBgCtx.fillStyle = nodeColorToRgba(accent, dotAlpha);
        nodeBgCtx.beginPath();
        nodeBgCtx.arc(node.x, node.y, node.r, 0, Math.PI * 2);
        nodeBgCtx.fill();
      });
    }
    function animateNodeBackground() {
      if (!nodeBgCanvas || !nodeBgCtx) return;
      const body = document.body;
      const reduced = body && body.classList.contains('ui-reduced-motion');
      drawNodeBackground();
      if (reduced) {
        nodeBgRaf = null;
        return;
      }
      nodeBgRaf = requestAnimationFrame(animateNodeBackground);
    }
    function requestNodeBackgroundRedraw() {
      if (!nodeBgCanvas || !nodeBgCtx) return;
      if (nodeBgRaf) {
        cancelAnimationFrame(nodeBgRaf);
        nodeBgRaf = null;
      }
      animateNodeBackground();
    }
    function initNodeBackground() {
      nodeBgCanvas = document.getElementById('fx-node-bg');
      if (!nodeBgCanvas) return;
      nodeBgCtx = nodeBgCanvas.getContext('2d');
      if (!nodeBgCtx) return;
      buildNodeBackground();
      animateNodeBackground();
      window.addEventListener('resize', () => {
        buildNodeBackground();
        requestNodeBackgroundRedraw();
      });
    }

    function drawSingleLineChart(canvas, color = '#a78bfa') {
      if (!canvas) return;
      let series = [];
      try {
        series = JSON.parse(String(canvas.dataset.series || '[]'));
        if (!Array.isArray(series)) series = [];
      } catch (error) {
        series = [];
      }
      const setup = setupCanvasSize(canvas);
      if (!setup) return;
      const { ctx, width, height } = setup;
      ctx.clearRect(0, 0, width, height);
      if (!series.length) return;

      const maxValue = Math.max(1, ...series.map((v) => Number(v || 0)));
      const padLeft = 16;
      const padRight = 8;
      const padTop = 10;
      const padBottom = 18;
      const plotW = Math.max(40, width - padLeft - padRight);
      const plotH = Math.max(40, height - padTop - padBottom);

      ctx.strokeStyle = '#1d3a55';
      ctx.lineWidth = 1;
      for (let i = 0; i <= 4; i += 1) {
        const y = padTop + (plotH * i) / 4;
        ctx.beginPath();
        ctx.moveTo(padLeft, y);
        ctx.lineTo(width - padRight, y);
        ctx.stroke();
      }

      ctx.beginPath();
      series.forEach((value, idx) => {
        const x = padLeft + (plotW * (series.length === 1 ? 0.5 : idx / (series.length - 1)));
        const y = padTop + plotH - (plotH * Number(value || 0)) / maxValue;
        if (idx === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      });
      ctx.strokeStyle = color;
      ctx.lineWidth = 2;
      ctx.stroke();
    }

    function drawCategoryBarChart(canvas) {
      if (!canvas) return;
      const parseArray = (raw) => {
        try {
          const value = JSON.parse(String(raw || '[]'));
          return Array.isArray(value) ? value : [];
        } catch (error) {
          return [];
        }
      };
      const labels = parseArray(canvas.dataset.labels).map((v) => String(v || '').slice(0, 24));
      const counts = parseArray(canvas.dataset.counts).map((v) => Number(v || 0));
      const count = Math.min(labels.length, counts.length);
      const setup = setupCanvasSize(canvas);
      if (!setup) return;
      const { ctx, width, height } = setup;
      ctx.clearRect(0, 0, width, height);
      if (count <= 0) return;

      const maxValue = Math.max(1, ...counts);
      const padLeft = 14;
      const padRight = 12;
      const padTop = 12;
      const padBottom = 46;
      const plotW = Math.max(40, width - padLeft - padRight);
      const plotH = Math.max(40, height - padTop - padBottom);
      const gap = 8;
      const barW = Math.max(8, Math.floor((plotW - gap * (count - 1)) / count));

      ctx.strokeStyle = '#1d3a55';
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(padLeft, padTop + plotH);
      ctx.lineTo(width - padRight, padTop + plotH);
      ctx.stroke();

      for (let i = 0; i < count; i += 1) {
        const value = Number(counts[i] || 0);
        const barH = Math.max(1, (plotH * value) / maxValue);
        const x = padLeft + i * (barW + gap);
        const y = padTop + plotH - barH;
        ctx.fillStyle = '#63d9ff';
        ctx.fillRect(x, y, barW, barH);
        ctx.fillStyle = '#bcd8f2';
        ctx.font = '10px monospace';
        ctx.textAlign = 'center';
        ctx.fillText(String(value), x + Math.floor(barW / 2), y - 4);
        const label = labels[i] || '-';
        const trimmedLabel = label.length > 12 ? `${label.slice(0, 11)}…` : label;
        ctx.fillStyle = '#8fb7d6';
        ctx.fillText(trimmedLabel, x + Math.floor(barW / 2), padTop + plotH + 14);
      }
    }

    function renderDashboardCharts() {
      drawTrendChart(document.getElementById('home-trend-chart'));
      drawRatioChart(document.getElementById('home-ratio-chart'));
      drawTrendChart(document.getElementById('analytics-trend-chart'));
      drawRatioChart(document.getElementById('analytics-ratio-chart'));
      drawTrendChart(document.getElementById('analytics-review-chart'));
      drawTrendChart(document.getElementById('analytics-risk-chart'));
      drawCategoryBarChart(document.getElementById('analytics-type-chart'));
      drawSingleLineChart(document.getElementById('analytics-hosts-chart'), '#a78bfa');
      drawSingleLineChart(document.getElementById('analytics-score-chart'), '#f97316');
      drawCategoryBarChart(document.getElementById('analytics-pending-chart'));
      drawCategoryBarChart(document.getElementById('analytics-manual-chart'));
      drawCategoryBarChart(document.getElementById('vt-reported-class-chart'));
      drawCategoryBarChart(document.getElementById('vt-reported-engine-chart'));
    }

    let chartResizeTimer = null;
    window.addEventListener('resize', () => {
      if (chartResizeTimer) {
        clearTimeout(chartResizeTimer);
      }
      chartResizeTimer = setTimeout(() => {
        renderDashboardCharts();
      }, 120);
    });

    function wireTableSearch(inputId, bodyId, rowSelector, emptyId) {
      const searchInput = document.getElementById(inputId);
      const tableBody = document.getElementById(bodyId);
      const emptyRow = document.getElementById(emptyId);
      const rows = tableBody ? Array.from(tableBody.querySelectorAll(rowSelector)) : [];
      if (!searchInput || !rows.length) {
        return;
      }
      const runFilter = () => {
        const term = String(searchInput.value || '').trim().toLowerCase();
        let visible = 0;
        rows.forEach((row) => {
          const haystack = String(row.textContent || '').toLowerCase();
          const show = term === '' || haystack.includes(term);
          row.hidden = !show;
          if (show) {
            visible += 1;
          }
        });
        if (emptyRow) {
          emptyRow.hidden = visible > 0;
        }
      };
      searchInput.addEventListener('input', runFilter);
      runFilter();
    }

    wireTableSearch('analytics-daily-search', 'analytics-daily-body', 'tr[data-day-row="1"]', 'analytics-daily-empty');
    wireTableSearch('analytics-pending-search', 'analytics-pending-body', 'tr[data-analytics-pending-row="1"]', 'analytics-pending-empty');
    wireTableSearch('pending-outside-search', 'pending-outside-body', 'tr[data-pending-outside-row="1"]', 'pending-outside-empty');
    wireTableSearch('admin-users-search', 'admin-users-body', 'tr[data-user-row="1"]', 'admin-users-empty');

    function initVtReportedCharts() {
      const button = document.getElementById('vt-reported-generate');
      const panel = document.getElementById('vt-reported-panel');
      const classCanvas = document.getElementById('vt-reported-class-chart');
      const engineCanvas = document.getElementById('vt-reported-engine-chart');
      if (!button || !panel || !classCanvas || !engineCanvas) {
        return;
      }

      const render = () => {
        drawCategoryBarChart(classCanvas);
        drawCategoryBarChart(engineCanvas);
      };

      const updateButtonLabel = () => {
        button.textContent = panel.hidden ? 'Generar gráficos VT' : 'Ocultar gráficos VT';
      };

      button.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        updateButtonLabel();
        if (!panel.hidden) {
          requestAnimationFrame(() => {
            render();
          });
        }
      });

      updateButtonLabel();
    }

    initVtReportedCharts();

    async function copyTextToClipboard(value) {
      const text = String(value || '');
      if (!text) return false;
      try {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          await navigator.clipboard.writeText(text);
          return true;
        }
      } catch (error) {
        console.debug(error);
      }
      const helper = document.createElement('textarea');
      helper.value = text;
      helper.setAttribute('readonly', 'readonly');
      helper.style.position = 'fixed';
      helper.style.opacity = '0';
      helper.style.pointerEvents = 'none';
      document.body.appendChild(helper);
      helper.select();
      helper.setSelectionRange(0, text.length);
      let copied = false;
      try {
        copied = document.execCommand('copy');
      } catch (error) {
        console.debug(error);
      }
      document.body.removeChild(helper);
      return copied;
    }

    document.querySelectorAll('[data-copy-text]').forEach((button) => {
      button.addEventListener('click', async () => {
        const payload = button.getAttribute('data-copy-text') || '';
        const ok = await copyTextToClipboard(payload);
        const original = button.textContent || 'Copiar';
        button.textContent = ok ? 'Copiado' : 'Error copia';
        setTimeout(() => {
          button.textContent = original;
        }, 1400);
      });
    });

    const intelNavButtons = Array.from(document.querySelectorAll('[data-scroll-target]'));
    const intelSections = Array.from(document.querySelectorAll('[data-intel-section]'));

    function setActiveIntelSection(sectionId) {
      intelNavButtons.forEach((button) => {
        const target = String(button.getAttribute('data-scroll-target') || '');
        button.classList.toggle('active', target !== '' && target === sectionId);
      });
    }

    intelNavButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const targetId = String(button.getAttribute('data-scroll-target') || '');
        if (!targetId) {
          return;
        }
        const target = document.getElementById(targetId);
        if (!target) {
          return;
        }
        setActiveIntelSection(targetId);
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    if (intelSections.length) {
      const observer = new IntersectionObserver((entries) => {
        const visible = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
        if (!visible) {
          return;
        }
        const sectionId = String(visible.target.getAttribute('data-intel-section') || visible.target.id || '');
        if (sectionId) {
          setActiveIntelSection(sectionId);
        }
      }, {
        root: null,
        threshold: [0.25, 0.45, 0.7],
        rootMargin: '-110px 0px -40% 0px'
      });
      intelSections.forEach((section) => observer.observe(section));
      const initialSection = String(intelSections[0].getAttribute('data-intel-section') || intelSections[0].id || '');
      if (initialSection) {
        setActiveIntelSection(initialSection);
      }
    }

    document.querySelectorAll('[data-scan-inline-src][data-scan-inline-target]').forEach((button) => {
      button.addEventListener('click', () => {
        const src = String(button.getAttribute('data-scan-inline-src') || '');
        const targetId = String(button.getAttribute('data-scan-inline-target') || '');
        if (!src || !targetId) {
          return;
        }
        const target = document.getElementById(targetId);
        if (!target) {
          return;
        }
        if (target.getAttribute('data-scan-loaded') === '1') {
          return;
        }
        target.textContent = 'Cargando captura...';
        const img = document.createElement('img');
        img.src = src;
        img.loading = 'lazy';
        img.alt = 'scan preview';
        img.style.maxWidth = '100%';
        img.style.borderRadius = '10px';
        img.style.border = '1px solid #5dc8ff33';
        img.addEventListener('load', () => {
          target.innerHTML = '';
          target.appendChild(img);
          target.setAttribute('data-scan-loaded', '1');
        });
        img.addEventListener('error', () => {
          target.textContent = 'No se pudo cargar la captura.';
        });
      });
    });

    function normalizeGraphPayload(raw) {
      const base = raw && typeof raw === 'object' ? raw : {};
      const nodes = Array.isArray(base.nodes) ? base.nodes : [];
      const edges = Array.isArray(base.edges) ? base.edges : [];
      const cleanedNodes = [];
      const seenNodes = new Set();
      nodes.forEach((node) => {
        if (!node || typeof node !== 'object') return;
        const id = String(node.id || '').replace(/[^a-zA-Z0-9._-]/g, '') || `n_${Math.random().toString(16).slice(2, 10)}`;
        if (seenNodes.has(id)) return;
        seenNodes.add(id);
        cleanedNodes.push({
          id,
          label: String(node.label || 'node').slice(0, 120),
          color: /^#[0-9a-fA-F]{6}$/.test(String(node.color || '')) ? String(node.color) : '#5dc8ff',
          x: Number.isFinite(Number(node.x)) ? Number(node.x) : 120,
          y: Number.isFinite(Number(node.y)) ? Number(node.y) : 120,
          tags: Array.isArray(node.tags) ? node.tags.map((t) => String(t).slice(0, 40)).filter(Boolean) : [],
          notes: String(node.notes || '').slice(0, 400)
        });
      });
      const cleanedEdges = [];
      const seenEdges = new Set();
      edges.forEach((edge) => {
        if (!edge || typeof edge !== 'object') return;
        const from = String(edge.from || '').replace(/[^a-zA-Z0-9._-]/g, '');
        const to = String(edge.to || '').replace(/[^a-zA-Z0-9._-]/g, '');
        if (!from || !to || !seenNodes.has(from) || !seenNodes.has(to)) return;
        const id = String(edge.id || '').replace(/[^a-zA-Z0-9._-]/g, '') || `e_${Math.random().toString(16).slice(2, 10)}`;
        if (seenEdges.has(id)) return;
        seenEdges.add(id);
        cleanedEdges.push({
          id,
          from,
          to,
          label: String(edge.label || '').slice(0, 120),
          color: /^#[0-9a-fA-F]{6}$/.test(String(edge.color || '')) ? String(edge.color) : '#94a3b8'
        });
      });
      return { nodes: cleanedNodes, edges: cleanedEdges };
    }

    function makeGraphRenderer({ wrap, svg, nodeLayer, graph, readOnly, onSelectNode, edgeListSelect, edgeFromSelect, edgeToSelect, nodeListSelect, controls = {}, vtLookupIndex = null }) {
      if (!wrap || !svg || !nodeLayer) {
        return null;
      }
      const state = {
        graph: normalizeGraphPayload(graph),
        selectedNodeId: null,
        drag: null,
        lastInteractionMoved: false,
        camera: {
          x: 40,
          y: 40,
          scale: 1,
          minScale: 0.35,
          maxScale: 2.6
        }
      };

      const controlRefs = {
        layoutSelect: controls.layoutSelect || null,
        layoutApplyButton: controls.layoutApplyButton || null,
        fitButton: controls.fitButton || null,
        zoomInButton: controls.zoomInButton || null,
        zoomOutButton: controls.zoomOutButton || null,
        zoomResetButton: controls.zoomResetButton || null,
        fullscreenButton: controls.fullscreenButton || null,
        fullscreenButtonAlt: controls.fullscreenButtonAlt || null,
        zoomStatus: controls.zoomStatus || null,
        zoomStatusAlt: controls.zoomStatusAlt || null
      };

      function nodeById(id) {
        return state.graph.nodes.find((n) => n.id === id) || null;
      }

      function edgeById(id) {
        return state.graph.edges.find((e) => e.id === id) || null;
      }

      function fillNodeSelect(selectEl, fallback = '') {
        if (!selectEl) return;
        const prev = fallback || selectEl.value || '';
        selectEl.innerHTML = '';
        state.graph.nodes.forEach((node) => {
          const opt = document.createElement('option');
          opt.value = node.id;
          opt.textContent = `${node.label} (${node.id})`;
          selectEl.appendChild(opt);
        });
        if (prev && state.graph.nodes.some((n) => n.id === prev)) {
          selectEl.value = prev;
        } else if (state.graph.nodes[0]) {
          selectEl.value = state.graph.nodes[0].id;
        }
      }

      function fillNodeList() {
        if (!nodeListSelect) return;
        const prev = state.selectedNodeId || nodeListSelect.value || '';
        nodeListSelect.innerHTML = '';
        state.graph.nodes.forEach((node) => {
          const opt = document.createElement('option');
          const tagsCount = Array.isArray(node.tags) ? node.tags.length : 0;
          const hasNotes = String(node.notes || '').trim() !== '';
          opt.value = node.id;
          opt.textContent = `${node.label}${tagsCount ? ` | tags:${tagsCount}` : ''}${hasNotes ? ' | notes' : ''}`;
          nodeListSelect.appendChild(opt);
        });
        if (prev && state.graph.nodes.some((n) => n.id === prev)) {
          nodeListSelect.value = prev;
        } else if (state.graph.nodes[0]) {
          nodeListSelect.value = state.graph.nodes[0].id;
        }
      }

      function fillEdgeList() {
        if (!edgeListSelect) return;
        const prev = edgeListSelect.value || '';
        edgeListSelect.innerHTML = '';
        state.graph.edges.forEach((edge) => {
          const from = nodeById(edge.from);
          const to = nodeById(edge.to);
          const opt = document.createElement('option');
          opt.value = edge.id;
          opt.textContent = `${from ? from.label : edge.from} -> ${to ? to.label : edge.to}${edge.label ? ` | ${edge.label}` : ''}`;
          edgeListSelect.appendChild(opt);
        });
        if (prev && state.graph.edges.some((e) => e.id === prev)) {
          edgeListSelect.value = prev;
        }
      }

      function nodeBounds() {
        if (!state.graph.nodes.length) {
          return { minX: 0, maxX: 0, minY: 0, maxY: 0, width: 0, height: 0, centerX: 0, centerY: 0 };
        }
        const xs = state.graph.nodes.map((node) => Number(node.x || 0));
        const ys = state.graph.nodes.map((node) => Number(node.y || 0));
        const minX = Math.min(...xs);
        const maxX = Math.max(...xs);
        const minY = Math.min(...ys);
        const maxY = Math.max(...ys);
        return {
          minX,
          maxX,
          minY,
          maxY,
          width: Math.max(1, maxX - minX),
          height: Math.max(1, maxY - minY),
          centerX: minX + (maxX - minX) / 2,
          centerY: minY + (maxY - minY) / 2
        };
      }

      function syncZoomStatus() {
        const label = `zoom ${Math.round(state.camera.scale * 100)}%`;
        [controlRefs.zoomStatus, controlRefs.zoomStatusAlt].forEach((node) => {
          if (node) {
            node.textContent = label;
          }
        });
      }

      function centerWorldPoint(worldX, worldY) {
        const rect = wrap.getBoundingClientRect();
        state.camera.x = rect.width / 2 - worldX * state.camera.scale;
        state.camera.y = rect.height / 2 - worldY * state.camera.scale;
      }

      function fitGraph(padding = 90) {
        const rect = wrap.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        const bounds = nodeBounds();
        if (!state.graph.nodes.length) {
          state.camera.scale = 1;
          state.camera.x = rect.width / 2;
          state.camera.y = rect.height / 2;
          render();
          return;
        }
        const availableWidth = Math.max(120, rect.width - padding * 2);
        const availableHeight = Math.max(120, rect.height - padding * 2);
        const scaleX = availableWidth / Math.max(bounds.width, 120);
        const scaleY = availableHeight / Math.max(bounds.height, 120);
        state.camera.scale = Math.max(state.camera.minScale, Math.min(state.camera.maxScale, Math.min(scaleX, scaleY, 1.65)));
        centerWorldPoint(bounds.centerX, bounds.centerY);
        render();
      }

      function resetZoom() {
        const bounds = nodeBounds();
        state.camera.scale = 1;
        centerWorldPoint(bounds.centerX, bounds.centerY);
        render();
      }

      function clientToWorld(clientX, clientY) {
        const rect = wrap.getBoundingClientRect();
        return {
          x: (clientX - rect.left - state.camera.x) / state.camera.scale,
          y: (clientY - rect.top - state.camera.y) / state.camera.scale
        };
      }

      function setZoom(nextScale, anchorClientX = null, anchorClientY = null) {
        const rect = wrap.getBoundingClientRect();
        const clamped = Math.max(state.camera.minScale, Math.min(state.camera.maxScale, nextScale));
        if (Math.abs(clamped - state.camera.scale) < 0.0001) {
          syncZoomStatus();
          return;
        }
        const anchorX = anchorClientX ?? (rect.left + rect.width / 2);
        const anchorY = anchorClientY ?? (rect.top + rect.height / 2);
        const world = clientToWorld(anchorX, anchorY);
        state.camera.scale = clamped;
        state.camera.x = anchorX - rect.left - world.x * state.camera.scale;
        state.camera.y = anchorY - rect.top - world.y * state.camera.scale;
        render();
      }

      function zoomBy(factor) {
        const rect = wrap.getBoundingClientRect();
        setZoom(state.camera.scale * factor, rect.left + rect.width / 2, rect.top + rect.height / 2);
      }

      function orderedNodeIds(rootId) {
        const adjacency = new Map();
        const incoming = new Map();
        state.graph.nodes.forEach((node) => {
          adjacency.set(node.id, []);
          incoming.set(node.id, 0);
        });
        state.graph.edges.forEach((edge) => {
          if (!adjacency.has(edge.from) || !adjacency.has(edge.to)) return;
          adjacency.get(edge.from).push(edge.to);
          incoming.set(edge.to, Number(incoming.get(edge.to) || 0) + 1);
        });
        adjacency.forEach((list) => list.sort());
        const fallbackRoot = rootId && adjacency.has(rootId)
          ? rootId
          : [...incoming.entries()].sort((a, b) => a[1] - b[1] || String(a[0]).localeCompare(String(b[0])))[0]?.[0];
        const visited = new Set();
        const queue = fallbackRoot ? [fallbackRoot] : [];
        const ordered = [];
        while (queue.length) {
          const current = queue.shift();
          if (!current || visited.has(current)) continue;
          visited.add(current);
          ordered.push(current);
          (adjacency.get(current) || []).forEach((nextId) => {
            if (!visited.has(nextId)) queue.push(nextId);
          });
        }
        state.graph.nodes
          .map((node) => node.id)
          .filter((id) => !visited.has(id))
          .sort()
          .forEach((id) => ordered.push(id));
        return { ordered, adjacency, incoming, rootId: fallbackRoot || '' };
      }

      function shouldAutoLayout() {
        const total = state.graph.nodes.length;
        if (!total) return false;
        let valid = 0;
        let maxCount = 0;
        const counts = new Map();
        state.graph.nodes.forEach((node) => {
          const x = Number(node.x);
          const y = Number(node.y);
          if (!Number.isFinite(x) || !Number.isFinite(y)) {
            return;
          }
          valid += 1;
          const key = `${Math.round(x)}:${Math.round(y)}`;
          const next = (counts.get(key) || 0) + 1;
          counts.set(key, next);
          if (next > maxCount) {
            maxCount = next;
          }
        });
        if (valid === 0) return true;
        return (maxCount / total) >= 0.6;
      }

      function estimateNodeRadius(node) {
        const label = String(node?.label || '');
        const base = 38;
        const extra = Math.min(80, label.length * 2.4);
        return base + extra;
      }

      function runForceLayout({ iterations = 200, stepsPerFrame = 6 } = {}) {
        if (!state.graph.nodes.length) {
          render();
          return;
        }
        const nodes = state.graph.nodes;
        const edges = state.graph.edges;
        const velocity = new Map();
        nodes.forEach((node) => {
          velocity.set(node.id, { x: 0, y: 0 });
          if (!Number.isFinite(node.x) || !Number.isFinite(node.y)) {
            node.x = 200 + Math.random() * 240;
            node.y = 160 + Math.random() * 180;
          }
        });
        const rect = wrap.getBoundingClientRect();
        const center = {
          x: rect.width > 0 ? rect.width / 2 : 420,
          y: rect.height > 0 ? rect.height / 2 : 260
        };
        const repulsion = 5200;
        const spring = 0.012;
        const desired = 170;
        const collisionStrength = 0.28;
        const centerStrength = 0.004;
        const damping = 0.86;
        let iter = 0;

        function stepSimulation() {
          const forces = new Map();
          nodes.forEach((node) => forces.set(node.id, { x: 0, y: 0 }));

          for (let i = 0; i < nodes.length; i += 1) {
            const a = nodes[i];
            for (let j = i + 1; j < nodes.length; j += 1) {
              const b = nodes[j];
              let dx = a.x - b.x;
              let dy = a.y - b.y;
              let dist2 = dx * dx + dy * dy;
              if (dist2 < 12) {
                dx += (Math.random() - 0.5) * 4;
                dy += (Math.random() - 0.5) * 4;
                dist2 = dx * dx + dy * dy;
              }
              const dist = Math.sqrt(dist2);
              const rep = repulsion / Math.max(40, dist2);
              const fx = (dx / dist) * rep;
              const fy = (dy / dist) * rep;
              forces.get(a.id).x += fx;
              forces.get(a.id).y += fy;
              forces.get(b.id).x -= fx;
              forces.get(b.id).y -= fy;

              const minDist = estimateNodeRadius(a) + estimateNodeRadius(b) + 24;
              if (dist < minDist) {
                const push = (minDist - dist) * collisionStrength;
                const cx = (dx / dist) * push;
                const cy = (dy / dist) * push;
                forces.get(a.id).x += cx;
                forces.get(a.id).y += cy;
                forces.get(b.id).x -= cx;
                forces.get(b.id).y -= cy;
              }
            }
          }

          edges.forEach((edge) => {
            const from = nodeById(edge.from);
            const to = nodeById(edge.to);
            if (!from || !to) return;
            const dx = to.x - from.x;
            const dy = to.y - from.y;
            const dist = Math.max(20, Math.sqrt(dx * dx + dy * dy));
            const force = (dist - desired) * spring;
            const fx = (dx / dist) * force;
            const fy = (dy / dist) * force;
            forces.get(from.id).x += fx;
            forces.get(from.id).y += fy;
            forces.get(to.id).x -= fx;
            forces.get(to.id).y -= fy;
          });

          nodes.forEach((node) => {
            const f = forces.get(node.id);
            const vx = velocity.get(node.id);
            vx.x = (vx.x + f.x + (center.x - node.x) * centerStrength) * damping;
            vx.y = (vx.y + f.y + (center.y - node.y) * centerStrength) * damping;
            node.x += vx.x;
            node.y += vx.y;
          });
        }

        function tick() {
          for (let s = 0; s < stepsPerFrame && iter < iterations; s += 1) {
            stepSimulation();
            iter += 1;
          }
          render();
          if (iter < iterations && !state.drag) {
            requestAnimationFrame(tick);
          } else {
            fitGraph(110);
          }
        }
        tick();
      }

      function applyLayout(mode = 'tree-vertical') {
        if (!state.graph.nodes.length) {
          render();
          return;
        }
        if (mode === 'force') {
          runForceLayout({ iterations: 240, stepsPerFrame: 8 });
          return;
        }
        const { ordered, adjacency, incoming, rootId } = orderedNodeIds(state.selectedNodeId || state.graph.nodes[0]?.id || '');
        const root = rootId || ordered[0] || '';
        const levels = new Map();
        const queue = root ? [{ id: root, depth: 0 }] : [];
        while (queue.length) {
          const current = queue.shift();
          if (!current || levels.has(current.id)) continue;
          levels.set(current.id, current.depth);
          (adjacency.get(current.id) || []).forEach((nextId) => {
            if (!levels.has(nextId)) {
              queue.push({ id: nextId, depth: current.depth + 1 });
            }
          });
        }
        ordered.forEach((id, index) => {
          if (!levels.has(id)) {
            levels.set(id, Math.max(1, Math.floor(index / 4) + 1));
          }
        });

        const layers = new Map();
        ordered.forEach((id) => {
          const depth = Number(levels.get(id) || 0);
          if (!layers.has(depth)) layers.set(depth, []);
          layers.get(depth).push(id);
        });
        layers.forEach((list) => {
          list.sort((a, b) => {
            const inDiff = Number(incoming.get(a) || 0) - Number(incoming.get(b) || 0);
            if (inDiff !== 0) return inDiff;
            return ordered.indexOf(a) - ordered.indexOf(b);
          });
        });

        const startX = 160;
        const startY = 110;
        const gapX = 190;
        const gapY = 110;

        if (mode === 'tree-vertical' || mode === 'tree-horizontal') {
          [...layers.entries()].sort((a, b) => a[0] - b[0]).forEach(([depth, ids]) => {
            const count = ids.length;
            ids.forEach((id, index) => {
              const node = nodeById(id);
              if (!node) return;
              const spread = (index - (count - 1) / 2);
              if (mode === 'tree-vertical') {
                node.x = startX + depth * gapX;
                node.y = startY + spread * gapY + 180;
              } else {
                node.x = startX + spread * gapX + 280;
                node.y = startY + depth * gapY;
              }
            });
          });
        } else if (mode === 'cascade') {
          ordered.forEach((id, index) => {
            const node = nodeById(id);
            if (!node) return;
            const row = Math.floor(index / 5);
            const col = index % 5;
            node.x = 150 + row * 130 + col * 85;
            node.y = 100 + row * 74 + col * 56;
          });
        } else if (mode === 'radial') {
          const bounds = nodeBounds();
          const centerX = bounds.centerX || 420;
          const centerY = bounds.centerY || 250;
          const byDepth = [...layers.entries()].sort((a, b) => a[0] - b[0]);
          byDepth.forEach(([depth, ids]) => {
            const radius = depth === 0 ? 0 : 120 + (depth - 1) * 105;
            ids.forEach((id, index) => {
              const node = nodeById(id);
              if (!node) return;
              if (depth === 0) {
                node.x = centerX;
                node.y = centerY;
                return;
              }
              const angle = (index / Math.max(1, ids.length)) * Math.PI * 2;
              node.x = Math.round(centerX + Math.cos(angle) * radius);
              node.y = Math.round(centerY + Math.sin(angle) * Math.max(70, radius * 0.66));
            });
          });
        } else if (mode === 'grid') {
          const columns = Math.max(2, Math.ceil(Math.sqrt(state.graph.nodes.length)));
          ordered.forEach((id, index) => {
            const node = nodeById(id);
            if (!node) return;
            const col = index % columns;
            const row = Math.floor(index / columns);
            node.x = 150 + col * 180;
            node.y = 110 + row * 115;
          });
        }

        render();
        fitGraph(100);
      }

      function toggleFullscreen() {
        if (!document.fullscreenEnabled) return;
        if (document.fullscreenElement === wrap) {
          document.exitFullscreen().catch(() => {});
          return;
        }
        wrap.requestFullscreen?.().catch(() => {});
      }

      function render() {
        const bounds = wrap.getBoundingClientRect();
        const width = Math.max(200, bounds.width);
        const height = Math.max(200, bounds.height);
        svg.setAttribute('viewBox', `0 0 ${Math.round(width)} ${Math.round(height)}`);
        svg.innerHTML = '';
        nodeLayer.innerHTML = '';
        nodeLayer.style.transform = `translate(${state.camera.x}px, ${state.camera.y}px) scale(${state.camera.scale})`;
        nodeLayer.style.transformOrigin = '0 0';
        syncZoomStatus();

        const scene = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        scene.setAttribute('transform', `translate(${state.camera.x} ${state.camera.y}) scale(${state.camera.scale})`);
        svg.appendChild(scene);

        state.graph.edges.forEach((edge) => {
          const from = nodeById(edge.from);
          const to = nodeById(edge.to);
          if (!from || !to) return;
          const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
          line.setAttribute('x1', String(from.x));
          line.setAttribute('y1', String(from.y));
          line.setAttribute('x2', String(to.x));
          line.setAttribute('y2', String(to.y));
          line.setAttribute('stroke', edge.color || '#94a3b8');
          line.setAttribute('stroke-width', '2');
          line.setAttribute('stroke-linecap', 'round');
          scene.appendChild(line);
          if (edge.label) {
            const tx = (from.x + to.x) / 2;
            const ty = (from.y + to.y) / 2;
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', String(tx));
            text.setAttribute('y', String(ty));
            text.setAttribute('fill', '#d6ebf8');
            text.setAttribute('font-size', '10');
            text.setAttribute('text-anchor', 'middle');
            text.textContent = edge.label;
            scene.appendChild(text);
          }
        });

        state.graph.nodes.forEach((node) => {
          const el = document.createElement('div');
          const kind = detectNodeKind(node);
          const kindClass = kind.key ? ` type-${kind.key}` : '';
          el.className = 'intel-node' + kindClass + (state.selectedNodeId === node.id ? ' active' : '');
          el.dataset.nodeId = node.id;
          el.style.left = `${node.x}px`;
          el.style.top = `${node.y}px`;
          el.style.background = node.color || '#5dc8ff';
          el.style.cursor = readOnly ? 'pointer' : 'move';
          const hasNotes = String(node.notes || '').trim() !== '';
          const tags = Array.isArray(node.tags) ? node.tags.filter(Boolean) : [];
          const labelWrap = document.createElement('div');
          labelWrap.className = 'intel-node-label';
          const flag = flagForNode(node);
          labelWrap.textContent = `${flag ? `${flag} ` : ''}${node.label}${hasNotes ? ' *' : ''}`;
          el.appendChild(labelWrap);
          const metaWrap = document.createElement('div');
          metaWrap.className = 'intel-node-meta';
          if (kind.label) {
            const kindChip = document.createElement('span');
            kindChip.className = `node-chip ${kind.key}`;
            kindChip.textContent = kind.label;
            metaWrap.appendChild(kindChip);
          }
          const vtLookup = lookupVtForNode(node, vtLookupIndex);
          if (vtLookup && vtLookup.summary && typeof vtLookup.summary === 'object') {
            const mal = Number(vtLookup.summary.malicious || 0);
            const sus = Number(vtLookup.summary.suspicious || 0);
            const har = Number(vtLookup.summary.harmless || 0);
            const und = Number(vtLookup.summary.undetected || 0);
            const vtChip = document.createElement('span');
            vtChip.className = 'node-chip vt';
            vtChip.textContent = `VT ${mal}/${sus}/${har}/${und}`;
            metaWrap.appendChild(vtChip);
          }
          if (metaWrap.childNodes.length) {
            el.appendChild(metaWrap);
          }
          el.title = `${node.label}${tags.length ? `\nTags: ${tags.join(', ')}` : ''}${hasNotes ? `\nNotas: ${String(node.notes).slice(0, 300)}` : ''}`;
          el.addEventListener('click', (ev) => {
            if (state.drag && state.drag.moved) {
              ev.preventDefault();
              return;
            }
            ev.stopPropagation();
            state.selectedNodeId = node.id;
            render();
            if (typeof onSelectNode === 'function') {
              onSelectNode(node);
            }
          });
          if (!readOnly) {
            el.addEventListener('pointerdown', (ev) => {
              ev.preventDefault();
              ev.stopPropagation();
              state.selectedNodeId = node.id;
              const world = clientToWorld(ev.clientX, ev.clientY);
              state.drag = {
                type: 'node',
                id: node.id,
                offsetX: world.x - node.x,
                offsetY: world.y - node.y,
                moved: false
              };
              render();
            });
          }
          nodeLayer.appendChild(el);
        });

        fillNodeSelect(edgeFromSelect);
        fillNodeSelect(edgeToSelect);
        fillEdgeList();
        fillNodeList();
      }

      wrap.addEventListener('pointerdown', (ev) => {
        const target = ev.target;
        const isNode = target instanceof HTMLElement && target.classList.contains('intel-node');
        if (isNode) {
          return;
        }
        state.drag = {
          type: 'pan',
          startClientX: ev.clientX,
          startClientY: ev.clientY,
          startX: state.camera.x,
          startY: state.camera.y,
          moved: false
        };
        wrap.classList.add('is-panning');
      });

      window.addEventListener('pointermove', (ev) => {
        if (!state.drag) return;
        if (state.drag.type === 'node' && !readOnly) {
          const node = nodeById(state.drag.id);
          if (!node) return;
          const world = clientToWorld(ev.clientX, ev.clientY);
          node.x = world.x - state.drag.offsetX;
          node.y = world.y - state.drag.offsetY;
          state.drag.moved = true;
          render();
          return;
        }
        if (state.drag.type === 'pan') {
          state.camera.x = state.drag.startX + (ev.clientX - state.drag.startClientX);
          state.camera.y = state.drag.startY + (ev.clientY - state.drag.startClientY);
          state.drag.moved = true;
          render();
        }
      });

      window.addEventListener('pointerup', () => {
        state.lastInteractionMoved = Boolean(state.drag && state.drag.moved);
        wrap.classList.remove('is-panning');
        state.drag = null;
      });

      wrap.addEventListener('click', (ev) => {
        if (state.lastInteractionMoved) {
          state.lastInteractionMoved = false;
          return;
        }
        const target = ev.target;
        const isNode = target instanceof HTMLElement && target.classList.contains('intel-node');
        if (isNode) return;
        state.selectedNodeId = null;
        render();
        if (typeof onSelectNode === 'function') {
          onSelectNode(null);
        }
      });

      wrap.addEventListener('wheel', (ev) => {
        ev.preventDefault();
        const factor = ev.deltaY > 0 ? 0.9 : 1.1;
        setZoom(state.camera.scale * factor, ev.clientX, ev.clientY);
      }, { passive: false });

      controlRefs.zoomInButton?.addEventListener('click', () => zoomBy(1.18));
      controlRefs.zoomOutButton?.addEventListener('click', () => zoomBy(0.84));
      controlRefs.zoomResetButton?.addEventListener('click', () => resetZoom());
      controlRefs.fitButton?.addEventListener('click', () => fitGraph(90));
      controlRefs.layoutApplyButton?.addEventListener('click', () => applyLayout(String(controlRefs.layoutSelect?.value || 'tree-vertical')));
      [controlRefs.fullscreenButton, controlRefs.fullscreenButtonAlt].forEach((buttonRef) => {
        buttonRef?.addEventListener('click', () => toggleFullscreen());
      });

      document.addEventListener('fullscreenchange', () => {
        const isActive = document.fullscreenElement === wrap;
        wrap.classList.toggle('is-fullscreen', isActive);
        [controlRefs.fullscreenButton, controlRefs.fullscreenButtonAlt].forEach((buttonRef) => {
          if (buttonRef) {
            buttonRef.textContent = isActive ? 'Salir pantalla completa' : 'Pantalla completa';
          }
        });
        render();
      });

      window.addEventListener('resize', () => render());
      const initialLayoutMode = String(controlRefs.layoutSelect?.value || 'tree-vertical');
      if (shouldAutoLayout()) {
        applyLayout('force');
      } else {
        render();
        fitGraph(90);
      }

      return {
        getGraph() {
          return normalizeGraphPayload(state.graph);
        },
        getSelectedNode() {
          return nodeById(state.selectedNodeId);
        },
        addNode(payload) {
          state.graph.nodes.push({
            id: payload.id,
            label: payload.label,
            color: payload.color,
            x: payload.x,
            y: payload.y,
            tags: payload.tags || [],
            notes: payload.notes || ''
          });
          state.selectedNodeId = payload.id;
          render();
          if (typeof onSelectNode === 'function') {
            onSelectNode(nodeById(payload.id));
          }
        },
        updateSelectedNode(payload) {
          const node = nodeById(state.selectedNodeId);
          if (!node) return false;
          node.label = payload.label;
          node.color = payload.color;
          node.tags = payload.tags || [];
          node.notes = payload.notes || '';
          render();
          if (typeof onSelectNode === 'function') {
            onSelectNode(node);
          }
          return true;
        },
        removeSelectedNode() {
          if (!state.selectedNodeId) return false;
          const nodeId = state.selectedNodeId;
          state.graph.nodes = state.graph.nodes.filter((n) => n.id !== nodeId);
          state.graph.edges = state.graph.edges.filter((e) => e.from !== nodeId && e.to !== nodeId);
          state.selectedNodeId = null;
          render();
          if (typeof onSelectNode === 'function') {
            onSelectNode(null);
          }
          return true;
        },
        addEdge(payload) {
          state.graph.edges.push(payload);
          render();
        },
        removeEdge(edgeId) {
          state.graph.edges = state.graph.edges.filter((e) => e.id !== edgeId);
          render();
        },
        edgeById,
        selectNode(nodeId) {
          if (!nodeId || !nodeById(nodeId)) {
            state.selectedNodeId = null;
            render();
            if (typeof onSelectNode === 'function') {
              onSelectNode(null);
            }
            return false;
          }
          state.selectedNodeId = nodeId;
          render();
          if (typeof onSelectNode === 'function') {
            onSelectNode(nodeById(nodeId));
          }
          return true;
        },
        fitGraph() {
          fitGraph(90);
        },
        resetZoom() {
          resetZoom();
        },
        zoomIn() {
          zoomBy(1.18);
        },
        zoomOut() {
          zoomBy(0.84);
        },
        applyLayout(mode) {
          applyLayout(mode);
        },
        toggleFullscreen() {
          toggleFullscreen();
        }
      };
    }

    function buildHighlightedContext(text, snippets) {
      if (!text) return '';
      const source = String(text);
      const lowerSource = source.toLowerCase();
      const list = Array.isArray(snippets) ? snippets : [];
      const uniqueSnippets = [...new Set(list.map((entry) => String(entry || '').trim()).filter(Boolean))];
      if (!uniqueSnippets.length) {
        return escapeHtml(source);
      }
      const ranges = [];
      uniqueSnippets.forEach((snippet) => {
        const lowerSnippet = snippet.toLowerCase();
        let cursor = 0;
        while (cursor < lowerSource.length) {
          const index = lowerSource.indexOf(lowerSnippet, cursor);
          if (index === -1) break;
          ranges.push({ start: index, end: index + lowerSnippet.length });
          cursor = index + lowerSnippet.length;
        }
      });
      if (!ranges.length) {
        return escapeHtml(source);
      }
      ranges.sort((a, b) => a.start - b.start || b.end - a.end);
      const merged = [];
      ranges.forEach((range) => {
        const last = merged[merged.length - 1];
        if (last && range.start <= last.end) {
          last.end = Math.max(last.end, range.end);
        } else {
          merged.push({ ...range });
        }
      });
      let output = '';
      let cursor = 0;
      merged.forEach((range) => {
        output += escapeHtml(source.slice(cursor, range.start));
        output += '<mark>' + escapeHtml(source.slice(range.start, range.end)) + '</mark>';
        cursor = range.end;
      });
      output += escapeHtml(source.slice(cursor));
      return output;
    }

    function eventSeverity(score) {
      const numeric = Number(score || 0);
      if (numeric > 40) return 'critical';
      if (numeric >= 30) return 'high';
      if (numeric > 15) return 'medium';
      return 'low';
    }

    const relatedEventsCache = new Map();

    function buildEventFocusLink(reportId) {
      const url = new URL(window.location.href);
      url.searchParams.set('report_id', String(reportId || ''));
      url.searchParams.delete('format');
      return `${url.pathname}?${url.searchParams.toString()}`;
    }

    function renderRelatedRows(rows) {
      if (!eventRelatedBody || !eventRelatedWrap) {
        return;
      }
      eventRelatedBody.innerHTML = '';
      if (!Array.isArray(rows) || !rows.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 9;
        td.className = 'mut';
        td.textContent = 'No se encontraron alertas relacionadas.';
        tr.appendChild(td);
        eventRelatedBody.appendChild(tr);
        eventRelatedWrap.hidden = false;
        return;
      }
      rows.forEach((row) => {
        const tr = document.createElement('tr');
        const relation = [];
        if (row.related_by_domain) relation.push('dominio');
        if (row.related_by_ip) relation.push('ip');
        if (row.related_by_ttp) relation.push('ttp');
        if (row.related_by_snippet) relation.push('snippet');
        const relationText = relation.length ? relation.join(' + ') : '-';
        const sharedReasons = Array.isArray(row.shared_reasons) ? row.shared_reasons : [];
        const sharedSignals = Array.isArray(row.shared_signals) ? row.shared_signals : [];
        const sharedSnippets = Array.isArray(row.shared_snippets) ? row.shared_snippets : [];
        const evidenceParts = [];
        if (sharedSnippets.length) {
          evidenceParts.push(`snippet: ${sharedSnippets[0]}`);
        }
        if (sharedReasons.length) {
          evidenceParts.push(`reason: ${sharedReasons[0]}`);
        }
        if (sharedSignals.length) {
          evidenceParts.push(`signal: ${sharedSignals[0]}`);
        }
        const evidenceText = evidenceParts.join(' | ') || '-';
        const cells = [
          String(row.id || ''),
          String(row.activity_at || row.received_at || '-'),
          String(row.hostname || '-'),
          String(row.ip || '-'),
          `${Number(row.score_total || 0)}/100`,
          String(row.review_status || 'pending'),
          relationText,
          evidenceText,
        ];
        cells.forEach((value) => {
          const td = document.createElement('td');
          td.textContent = value;
          if (/^\d+$/.test(value) || value.includes('/100')) {
            td.className = 'mono';
          }
          tr.appendChild(td);
        });
        const actionTd = document.createElement('td');
        const link = document.createElement('a');
        link.className = 'event-related-link';
        link.href = buildEventFocusLink(row.id || 0);
        link.textContent = 'Abrir';
        actionTd.appendChild(link);
        if (row.hostname) {
          const sep = document.createTextNode(' | ');
          actionTd.appendChild(sep);
          const searchLink = document.createElement('a');
          searchLink.className = 'event-related-link';
          searchLink.href = `dashboard.php?page=search&domain=${encodeURIComponent(String(row.hostname || ''))}`;
          searchLink.textContent = 'Buscar';
          actionTd.appendChild(searchLink);
        }
        tr.appendChild(actionTd);
        eventRelatedBody.appendChild(tr);
      });
      eventRelatedWrap.hidden = false;
    }

    async function loadRelatedReports(reportId) {
      const url = `dashboard.php?format=related_reports&report_id=${encodeURIComponent(String(reportId || 0))}`;
      const response = await fetch(url, { cache: 'no-store' });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const payload = await response.json();
      if (!payload || payload.status !== 'ok') {
        throw new Error(String(payload?.message || 'invalid_response'));
      }
      return Array.isArray(payload.related) ? payload.related : [];
    }

    const setText = (node, value, fallback = '-') => {
      if (!node) return;
      const next = String(value ?? '').trim();
      node.textContent = next !== '' ? next : fallback;
    };

    function renderEventDetail(index) {
      const safeIndex = Number(index);
      if (!eventDetail || !eventEmpty || !Number.isInteger(safeIndex) || !eventWorkbenchData[safeIndex]) {
        return;
      }
      const event = eventWorkbenchData[safeIndex];
      const liveItems = eventFeed ? Array.from(eventFeed.querySelectorAll('.event-feed-item')) : [];
      liveItems.forEach((item) => {
        item.classList.toggle('is-active', Number(item.dataset.eventIndex) === safeIndex);
      });
      try {
        eventEmpty.hidden = true;
        eventDetail.hidden = false;
        setText(eventTitle, event.hostname, '(sin dominio)');
        setText(eventTime, event.activity_at || event.received_at, '-');
        setText(eventCountry, event.country, '-');
        setText(eventUrl, event.url, '-');
        setText(eventPrevUrl, event.previous_url, '-');
        const isManualReport = String(event.event_type || '') === 'manual_report';
        setText(eventIp, isManualReport ? event.ip : '-', '-');
        setText(eventExtension, isManualReport ? event.extension_version : '-', '-');
        if (eventDomainHistory) {
          const domainBlockedCount = Number(event.host_blocked_count || 0);
          const domainTotalCount = Number(event.host_total_count || 0);
          const domainBlocked = Boolean(event.host_blocked_before);
          const domainLastBlockedAt = String(event.host_last_blocked_at || '');
          eventDomainHistory.textContent = domainBlocked
            ? `Sí (${domainBlockedCount} bloqueos / ${domainTotalCount} reportes${domainLastBlockedAt ? `, último ${domainLastBlockedAt}` : ''})`
            : `No (${domainTotalCount} reportes)`;
        }
        if (eventIpHistory) {
          const ipBlockedCount = Number(event.ip_blocked_count || 0);
          const ipTotalCount = Number(event.ip_total_count || 0);
          const ipBlocked = Boolean(event.ip_blocked_before);
          const ipLastBlockedAt = String(event.ip_last_blocked_at || '');
          eventIpHistory.textContent = ipBlocked
            ? `Sí (${ipBlockedCount} bloqueos / ${ipTotalCount} reportes${ipLastBlockedAt ? `, último ${ipLastBlockedAt}` : ''})`
            : `No (${ipTotalCount} reportes)`;
        }
        if (eventIoc) {
          const isUnsafeDownload = String(event.event_type || '') === 'unsafe_download';
          if (isUnsafeDownload) {
            const ioc = event.download_ioc || {};
            setText(eventIocHash, ioc.hash, 'No disponible');
            setText(eventIocName, ioc.filename || event.detected_content, '-');
            setText(eventIocPath, ioc.path, '-');
            setText(eventIocSite, ioc.url || ioc.site || event.url || event.hostname, '-');
            setText(eventIocDate, event.activity_at || event.received_at, '-');
            eventIoc.hidden = false;
          } else {
            eventIoc.hidden = true;
          }
        }
        if (eventReviewId) {
          eventReviewId.value = event.id || '';
        }
        if (eventReviewStatus) {
          const nextStatus = String(event.review_status || 'pending');
          eventReviewStatus.value = ['pending', 'accepted', 'rejected', 'allowlisted'].includes(nextStatus) ? nextStatus : 'pending';
        }
        document.querySelectorAll('[data-event-report-id]').forEach((input) => {
          input.value = event.id || '';
        });

        if (eventBadges) {
          eventBadges.innerHTML = '';
          const badges = [
            `score ${Number(event.score_total || 0)}/100`,
            event.blocked ? 'blocked' : 'alert-only',
            String(event.review_status || 'pending'),
            `x${Math.max(1, Number(event.duplicate_count || 1))}`,
            String(event.event_type || 'clickfix_alert')
          ];
          if (Boolean(event.host_blocked_before)) {
            badges.push(`domain_blocked x${Number(event.host_blocked_count || 0)}`);
          }
          if (Boolean(event.ip_blocked_before)) {
            badges.push(`ip_blocked x${Number(event.ip_blocked_count || 0)}`);
          }
          badges.forEach((label) => {
            const chip = document.createElement('span');
            chip.className = 'event-chip';
            chip.textContent = label;
            eventBadges.appendChild(chip);
          });
        }

        if (eventReasons) {
          eventReasons.innerHTML = '';
          const reasonList = Array.isArray(event.reason_list) && event.reason_list.length
            ? event.reason_list
            : [event.message || 'Sin motivo clasificado'];
          reasonList.forEach((reason) => {
            const li = document.createElement('li');
            li.textContent = String(reason);
            eventReasons.appendChild(li);
          });
        }

        const snippets = Array.isArray(event.snippets) ? event.snippets.filter(Boolean) : [];
        if (eventSnippets) {
          eventSnippets.innerHTML = '';
          if (snippets.length) {
            snippets.forEach((snippet) => {
              const div = document.createElement('div');
              div.className = 'event-snippet';
              div.innerHTML = boldMatchedSnippets(String(snippet), [snippet]);
              eventSnippets.appendChild(div);
            });
          } else {
            const div = document.createElement('div');
            div.className = 'event-empty';
            div.textContent = 'Sin snippets almacenados.';
            eventSnippets.appendChild(div);
          }
        }

        if (eventSignals) {
          eventSignals.innerHTML = '';
          const signals = Array.isArray(event.signals) ? event.signals.filter(Boolean) : [];
          if (signals.length) {
            signals.forEach((signal) => {
              const li = document.createElement('li');
              li.textContent = String(signal);
              eventSignals.appendChild(li);
            });
          } else {
            const li = document.createElement('li');
            li.className = 'event-empty';
            li.textContent = 'Sin signals capturados.';
            eventSignals.appendChild(li);
          }
        }

        if (eventScoreDetails) {
          eventScoreDetails.innerHTML = '';
          const details = event.score_details;
          if (details && typeof details === 'object') {
            const pre = document.createElement('pre');
            pre.className = 'event-snippet';
            pre.textContent = JSON.stringify(details, null, 2);
            eventScoreDetails.appendChild(pre);
          } else if (typeof details === 'string' && details.trim() !== '') {
            const pre = document.createElement('pre');
            pre.className = 'event-snippet';
            pre.textContent = details;
            eventScoreDetails.appendChild(pre);
          } else {
            const div = document.createElement('div');
            div.className = 'event-empty';
            div.textContent = 'Sin detalle de score.';
            eventScoreDetails.appendChild(div);
          }
        }

        const fullContextText = String(event.full_context || '');
        const detectedContextText = String(event.detected_content || '');
        if (canViewExactEventContext) {
          setText(eventContextTitle, 'Contexto completo de pagina', 'Contexto completo de pagina');
          if (eventContext) {
            eventContext.textContent = fullContextText || detectedContextText || 'Sin contexto capturado.';
          }
        } else {
          setText(eventContextTitle, 'Contexto resaltado', 'Contexto resaltado');
          const contextText = detectedContextText || fullContextText;
          if (eventContext) {
            if (contextText) {
              eventContext.innerHTML = buildHighlightedContext(contextText, snippets);
            } else {
              eventContext.textContent = 'Sin contexto capturado.';
            }
          }
        }

        const rawPayload = {
          id: event.id,
          received_at: event.received_at,
          last_seen: event.last_seen,
          activity_at: event.activity_at,
          hostname: event.hostname,
          url: event.url,
          previous_url: event.previous_url,
          message: event.message,
          detected_content: event.detected_content,
          full_context: canViewExactEventContext ? event.full_context : undefined,
          score_total: event.score_total,
          event_type: event.event_type,
          ip: event.ip,
          extension_version: event.extension_version,
          host_blocked_before: event.host_blocked_before,
          host_blocked_count: event.host_blocked_count,
          host_total_count: event.host_total_count,
          host_last_blocked_at: event.host_last_blocked_at,
          ip_blocked_before: event.ip_blocked_before,
          ip_blocked_count: event.ip_blocked_count,
          ip_total_count: event.ip_total_count,
          ip_last_blocked_at: event.ip_last_blocked_at,
          review_status: event.review_status,
          blocked: event.blocked,
          duplicate_count: event.duplicate_count,
          reasons: event.reason_list,
          snippets: event.snippets,
          signals: event.signals,
          score_details: event.score_details,
          download_ioc: event.download_ioc
        };
        setText(eventRaw, JSON.stringify(rawPayload, null, 2), '{}');

        const severity = eventSeverity(event.score_total);
        if (eventTitle) {
          eventTitle.dataset.severity = severity;
        }

        if (eventRelatedLoad) {
          eventRelatedLoad.disabled = false;
          eventRelatedLoad.dataset.reportId = String(event.id || '');
        }
        setText(eventRelatedStatus, 'No se cargan automáticamente. Pulsa "Ver relacionadas" para consultar el historial relacionado.', '');
        if (eventRelatedBody) {
          eventRelatedBody.innerHTML = '';
        }
        if (eventRelatedWrap) {
          eventRelatedWrap.hidden = true;
        }
        updateEventMitre(event);
      } catch (error) {
        console.error('clickfix:event-detail-render-failed', error, event);
        eventEmpty.hidden = false;
        eventDetail.hidden = true;
      }
    }

    if (eventReviewForm) {
      eventReviewForm.addEventListener('submit', (ev) => {
        const reportId = Number(eventReviewId?.value || 0);
        if (!Number.isFinite(reportId) || reportId <= 0) {
          ev.preventDefault();
          alert('Selecciona un evento válido antes de actualizar la revisión.');
        }
      });
    }
    eventQuickForms.forEach((form) => {
      form.addEventListener('submit', (ev) => {
        const idInput = form.querySelector('[data-event-report-id]');
        const reportId = Number(idInput?.value || 0);
        if (!Number.isFinite(reportId) || reportId <= 0) {
          ev.preventDefault();
          alert('Selecciona un evento válido antes de ejecutar la acción.');
        }
      });
    });

    if (eventRelatedLoad) {
      eventRelatedLoad.addEventListener('click', async () => {
        const reportId = Number(eventRelatedLoad.dataset.reportId || 0);
        if (!Number.isFinite(reportId) || reportId <= 0) {
          if (eventRelatedStatus) {
            eventRelatedStatus.textContent = 'Selecciona primero un evento válido.';
          }
          return;
        }
        if (eventRelatedStatus) {
          eventRelatedStatus.textContent = 'Cargando alertas relacionadas...';
        }
        eventRelatedLoad.disabled = true;
        try {
          if (!relatedEventsCache.has(reportId)) {
            const rows = await loadRelatedReports(reportId);
            relatedEventsCache.set(reportId, rows);
          }
          const rows = relatedEventsCache.get(reportId) || [];
          renderRelatedRows(rows);
          if (eventRelatedStatus) {
            eventRelatedStatus.textContent = `Relacionadas encontradas: ${Array.isArray(rows) ? rows.length : 0}.`;
          }
        } catch (error) {
          if (eventRelatedStatus) {
            eventRelatedStatus.textContent = `No se pudieron cargar las relacionadas (${String(error?.message || 'error')}).`;
          }
          if (eventRelatedBody) {
            eventRelatedBody.innerHTML = '';
          }
          if (eventRelatedWrap) {
            eventRelatedWrap.hidden = true;
          }
        } finally {
          eventRelatedLoad.disabled = false;
        }
      });
    }

    updateInvestigationMitre();
    updateSharedInvestigationMitre();
    updateSourceAlertMitre();
    if (eventFeed && eventWorkbenchData.length) {
      eventItems.forEach((item) => {
        item.addEventListener('click', () => {
          renderEventDetail(Number(item.dataset.eventIndex));
        });
      });
      eventFeed.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
          return;
        }
        const toggleBtn = target.closest('.event-group-toggle');
        if (toggleBtn && eventFeed.contains(toggleBtn)) {
          event.preventDefault();
          event.stopPropagation();
          const group = toggleBtn.closest('.event-group');
          const items = group ? group.querySelector('.event-group-items') : null;
          if (items) {
            const willOpen = items.hasAttribute('hidden');
            if (willOpen) {
              items.removeAttribute('hidden');
              toggleBtn.setAttribute('aria-expanded', 'true');
              toggleBtn.textContent = 'Ocultar';
            } else {
              items.setAttribute('hidden', '');
              toggleBtn.setAttribute('aria-expanded', 'false');
              const count = Math.max(0, items.querySelectorAll('.event-feed-item').length);
              toggleBtn.textContent = `Ver ${count} más`;
            }
          }
          return;
        }
        const btn = target.closest('.event-feed-item');
        if (!btn || !eventFeed.contains(btn)) {
          return;
        }
        renderEventDetail(Number(btn.dataset.eventIndex));
      });
      const preferredIndex = focusReportId > 0
        ? eventWorkbenchData.findIndex((entry) => Number(entry?.id || 0) === Number(focusReportId))
        : -1;
      renderEventDetail(preferredIndex >= 0 ? preferredIndex : 0);
    }

    const focusShell = document.querySelector('.intel-selector-shell[data-intel-focus="1"]');
    if (focusShell) {
      const tabButtons = Array.from(focusShell.querySelectorAll('[data-intel-tab]'));
      const panels = Array.from(focusShell.querySelectorAll('[data-intel-panel]'));
      const searchInput = focusShell.querySelector('#intel-focus-search');
      const cards = Array.from(focusShell.querySelectorAll('.intel-focus-card'));

      const activateTab = (tabId) => {
        tabButtons.forEach((btn) => {
          const isActive = btn.dataset.intelTab === tabId;
          btn.classList.toggle('is-active', isActive);
          btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        panels.forEach((panel) => {
          panel.hidden = panel.dataset.intelPanel !== tabId;
        });
      };

      tabButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
          activateTab(btn.dataset.intelTab || 'investigations');
        });
      });

      if (searchInput) {
        const runFilter = () => {
          const query = String(searchInput.value || '').trim().toLowerCase();
          cards.forEach((card) => {
            const haystack = String(card.dataset.search || '');
            const match = !query || haystack.includes(query);
            card.classList.toggle('is-hidden', !match);
          });
        };
        searchInput.addEventListener('input', runFilter);
      }
    }

    const intelWrap = document.getElementById('intel-canvas-wrap');
    const intelSvg = document.getElementById('intel-svg');
    const intelNodeLayer = document.getElementById('intel-node-layer');
    const intelLayoutMode = document.getElementById('intel-layout-mode');
    const intelLayoutApply = document.getElementById('intel-layout-apply');
    const intelFitGraph = document.getElementById('intel-fit-graph');
    const intelZoomIn = document.getElementById('intel-zoom-in');
    const intelZoomOut = document.getElementById('intel-zoom-out');
    const intelZoomReset = document.getElementById('intel-zoom-reset');
    const intelZoomStatus = document.getElementById('intel-zoom-status');
    const intelFullscreen = document.getElementById('intel-fullscreen');
    const intelWorkspaceFullscreen = document.getElementById('intel-workspace-fullscreen');
    const intelLayoutCycle = document.getElementById('intel-layout-cycle');
    const intelDockFit = document.getElementById('intel-dock-fit');
    const intelDockZoomIn = document.getElementById('intel-dock-zoom-in');
    const intelDockZoomOut = document.getElementById('intel-dock-zoom-out');
    const intelDockZoomReset = document.getElementById('intel-dock-zoom-reset');
    const intelDockZoomStatus = document.getElementById('intel-dock-zoom-status');
    const intelDockFullscreen = document.getElementById('intel-dock-fullscreen');
    const intelGraphJsonInput = document.getElementById('intel-graph-json');
    const intelSaveForm = document.getElementById('intel-save-form');
    const nodeLabelInput = document.getElementById('node-label');
    const nodeColorInput = document.getElementById('node-color');
    const nodeTagsInput = document.getElementById('node-tags');
    const nodeNotesInput = document.getElementById('node-notes');
    const nodeAddButton = document.getElementById('node-add');
    const nodeUpdateButton = document.getElementById('node-update');
    const nodeDeleteButton = document.getElementById('node-delete');
    const nodeListSelect = document.getElementById('node-list');
    const nodePreviewLabel = document.getElementById('node-preview-label');
    const nodePreviewTags = document.getElementById('node-preview-tags');
    const nodePreviewNotes = document.getElementById('node-preview-notes');
    const edgeFromSelect = document.getElementById('edge-from');
    const edgeToSelect = document.getElementById('edge-to');
    const edgeLabelInput = document.getElementById('edge-label');
    const edgeColorInput = document.getElementById('edge-color');
    const edgeAddButton = document.getElementById('edge-add');
    const edgeListSelect = document.getElementById('edge-list');
    const edgeDeleteButton = document.getElementById('edge-delete');
    const intelApiMapMeta = document.getElementById('intel-api-map-meta');
    const intelApiKeywordsWrap = document.getElementById('intel-api-keywords');

    function tagsFromInput(value) {
      return String(value || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
        .slice(0, 20);
    }

    function makeNodeId() {
      return `n_${Date.now().toString(36)}_${Math.random().toString(16).slice(2, 7)}`;
    }

    function makeEdgeId() {
      return `e_${Date.now().toString(36)}_${Math.random().toString(16).slice(2, 7)}`;
    }

    function tinyHash(value) {
      const raw = String(value || '');
      if (!raw) return '0';
      let hash = 0;
      for (let i = 0; i < raw.length; i += 1) {
        hash = ((hash << 5) - hash) + raw.charCodeAt(i);
        hash |= 0;
      }
      return Math.abs(hash).toString(16);
    }

    function makeStableGraphId(prefix, rawValue) {
      const clean = String(rawValue || '')
        .toLowerCase()
        .replace(/[^a-z0-9._-]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 34) || 'item';
      return `${prefix}_${clean}_${tinyHash(rawValue).slice(0, 6)}`;
    }

    function normalizeDomainValue(value) {
      const raw = String(value || '').trim();
      if (!raw) return '';
      try {
        const maybeUrl = /^https?:\/\//i.test(raw) ? new URL(raw) : null;
        if (maybeUrl) {
          return String(maybeUrl.hostname || '').toLowerCase().replace(/^www\./, '');
        }
      } catch (error) {
        // Ignore URL parse errors.
      }
      if (/^\d{1,3}(?:\.\d{1,3}){3}$/.test(raw)) {
        return '';
      }
      return raw.toLowerCase().replace(/^www\./, '').replace(/:\d+$/, '');
    }

    function extractCountryCode(label) {
      const raw = String(label || '').trim();
      if (!raw) return '';
      const match = raw.match(/\(([A-Z]{2})\)/);
      if (match) return match[1];
      if (/^[A-Z]{2}$/.test(raw)) return raw;
      return '';
    }

    function countryCodeToFlag(code) {
      const safe = String(code || '').trim().toUpperCase();
      if (!/^[A-Z]{2}$/.test(safe)) return '';
      const base = 0x1f1e6;
      const first = safe.charCodeAt(0) - 65;
      const second = safe.charCodeAt(1) - 65;
      if (first < 0 || first > 25 || second < 0 || second > 25) return '';
      return String.fromCodePoint(base + first, base + second);
    }

    function flagForNode(node) {
      const label = String(node?.label || '');
      const tags = Array.isArray(node?.tags) ? node.tags.map((t) => String(t).toLowerCase()) : [];
      const code = extractCountryCode(label);
      if (!code) return '';
      if (!tags.includes('geo') && !/\([A-Z]{2}\)/.test(label) && !/^[A-Z]{2}$/.test(label.trim())) {
        return '';
      }
      return countryCodeToFlag(code);
    }

    function detectNodeKind(node) {
      const label = String(node?.label || '');
      const tags = Array.isArray(node?.tags) ? node.tags.map((t) => String(t).toLowerCase()) : [];
      if (tags.includes('api-provider') || tags.includes('source') || tags.includes('origin') || tags.includes('alert')) {
        return { key: 'source', label: 'fuente' };
      }
      if (label.trim().startsWith('#') || tags.includes('keyword')) {
        return { key: 'hash', label: '#' };
      }
      const isSha256 = /^[a-f0-9]{64}$/i.test(label.trim());
      const isUrl = /^https?:\/\//i.test(label.trim());
      const isIp = /^\d{1,3}(?:\.\d{1,3}){3}$/.test(label.trim());
      const isDomain = !!normalizeDomainValue(label);
      if (isSha256 || isUrl || isIp || isDomain || tags.includes('ioc') || tags.includes('domain') || tags.includes('ip') || tags.includes('url')) {
        return { key: 'ioc', label: 'ioc' };
      }
      if (tags.includes('artifact') || tags.includes('evidence') || tags.includes('snippet') || tags.includes('file') || tags.includes('download')) {
        return { key: 'artifact', label: 'artefacto' };
      }
      return { key: '', label: '' };
    }

    function normalizeLookupKey(type, value) {
      const raw = String(value || '').trim();
      if (!raw) return '';
      if (type === 'domain') {
        const domain = normalizeDomainValue(raw);
        return domain ? `domain:${domain}` : '';
      }
      if (type === 'ip') {
        return /^\d{1,3}(?:\.\d{1,3}){3}$/.test(raw) ? `ip:${raw}` : '';
      }
      if (type === 'url') {
        try {
          const parsed = new URL(raw);
          return `url:${parsed.toString()}`;
        } catch (error) {
          return '';
        }
      }
      if (type === 'hash') {
        return /^[a-f0-9]{64}$/i.test(raw) ? `hash:${raw.toLowerCase()}` : '';
      }
      return '';
    }

    function buildVtLookupIndex(lookupRows) {
      const index = new Map();
      const rows = Array.isArray(lookupRows) ? lookupRows : [];
      rows.forEach((row) => {
        if (!row || String(row.provider || '').toLowerCase() !== 'virustotal') {
          return;
        }
        const target = String(row.target || '');
        const targetType = String(row.target_type || '');
        const meta = parseLookupTargetMeta(target, targetType);
        let key = '';
        if (/^[a-f0-9]{64}$/i.test(target.trim()) || targetType === 'sha256') {
          key = normalizeLookupKey('hash', target.trim());
        } else {
          key = normalizeLookupKey(meta.type, meta.display || target);
        }
        if (!key) return;
        index.set(key, row);
      });
      return index;
    }

    function lookupVtForNode(node, vtIndex) {
      if (!vtIndex || !(vtIndex instanceof Map)) return null;
      const label = String(node?.label || '').trim();
      const tags = Array.isArray(node?.tags) ? node.tags.map((t) => String(t).toLowerCase()) : [];
      let key = '';
      if (/^[a-f0-9]{64}$/i.test(label)) {
        key = normalizeLookupKey('hash', label);
      } else if (tags.includes('url') || /^https?:\/\//i.test(label)) {
        key = normalizeLookupKey('url', label);
      } else if (tags.includes('ip') || /^\d{1,3}(?:\.\d{1,3}){3}$/.test(label)) {
        key = normalizeLookupKey('ip', label);
      } else {
        key = normalizeLookupKey('domain', label);
      }
      if (!key) return null;
      return vtIndex.get(key) || null;
    }

    function parseLookupTargetMeta(target, targetType) {
      const raw = String(target || '').trim();
      const declaredType = String(targetType || '').toLowerCase();
      let type = declaredType || 'unknown';
      let display = raw;
      let domain = '';
      if (/^\d{1,3}(?:\.\d{1,3}){3}$/.test(raw)) {
        type = 'ip';
      } else if (/^https?:\/\//i.test(raw)) {
        type = 'url';
        try {
          const parsed = new URL(raw);
          domain = normalizeDomainValue(parsed.hostname);
          display = parsed.toString();
        } catch (error) {
          // Keep raw value.
        }
      } else {
        const normalizedDomain = normalizeDomainValue(raw);
        if (normalizedDomain) {
          type = 'domain';
          domain = normalizedDomain;
          display = normalizedDomain;
        }
      }
      if (!domain && type === 'domain') {
        domain = normalizeDomainValue(raw);
      }
      return { type, display, domain };
    }

    function providerColor(provider) {
      const key = String(provider || '').toLowerCase();
      if (key === 'virustotal') return '#ff9f43';
      if (key === 'abuseipdb') return '#ff6b6b';
      if (key === 'urlscan') return '#4fd1c5';
      if (key === 'threatrip') return '#a78bfa';
      return '#6cb6ff';
    }

    function ensureGraphNode(graph, payload) {
      const existing = graph.nodes.find((node) => String(node.id) === String(payload.id));
      if (existing) {
        return existing;
      }
      const node = {
        id: String(payload.id),
        label: String(payload.label || 'node').slice(0, 120),
        color: /^#[0-9a-fA-F]{6}$/.test(String(payload.color || '')) ? String(payload.color) : '#5dc8ff',
        x: Number.isFinite(Number(payload.x)) ? Number(payload.x) : 120,
        y: Number.isFinite(Number(payload.y)) ? Number(payload.y) : 120,
        tags: Array.isArray(payload.tags) ? payload.tags.map((tag) => String(tag).slice(0, 40)).filter(Boolean) : [],
        notes: String(payload.notes || '').slice(0, 400)
      };
      graph.nodes.push(node);
      return node;
    }

    function ensureGraphEdge(graph, payload) {
      const from = String(payload.from || '');
      const to = String(payload.to || '');
      const label = String(payload.label || '').slice(0, 120);
      if (!from || !to || from === to) return;
      const dup = graph.edges.some((edge) =>
        String(edge.from) === from && String(edge.to) === to && String(edge.label || '') === label
      );
      if (dup) return;
      graph.edges.push({
        id: String(payload.id || makeEdgeId()),
        from,
        to,
        label,
        color: /^#[0-9a-fA-F]{6}$/.test(String(payload.color || '')) ? String(payload.color) : '#94a3b8'
      });
    }

    function summarizeLookup(lookup, meta) {
      const provider = String(lookup?.provider || 'unknown');
      const status = Number(lookup?.status || 0);
      const createdAt = String(lookup?.created_at || '');
      const summary = (lookup && typeof lookup.summary === 'object' && lookup.summary) ? lookup.summary : {};
      const details = (lookup && typeof lookup.details === 'object' && lookup.details) ? lookup.details : {};
      const summaryParts = Object.entries(summary)
        .slice(0, 6)
        .map(([key, value]) => `${key}: ${String(value)}`);
      const lines = [
        `provider=${provider}`,
        `target=${String(meta.display || lookup?.target || '')}`,
        `type=${String(meta.type || lookup?.target_type || 'unknown')}`,
        `status=${status}`,
      ];
      if (createdAt) {
        lines.push(`at=${createdAt}`);
      }
      if (summaryParts.length) {
        lines.push(summaryParts.join(' | '));
      }
      const detailParts = [];
      if (details.related_ip) detailParts.push(`ip=${String(details.related_ip)}`);
      if (details.related_domain) detailParts.push(`domain=${String(details.related_domain)}`);
      if (details.country_code || details.country_name) {
        detailParts.push(`country=${String(details.country_name || details.country_code)}`);
      }
      if (details.abuse_score) detailParts.push(`abuse_score=${Number(details.abuse_score)}`);
      if (details.total_reports) detailParts.push(`reports=${Number(details.total_reports)}`);
      if (details.vt_reputation) detailParts.push(`vt_reputation=${Number(details.vt_reputation)}`);
      if (details.vt_registrar) detailParts.push(`registrar=${String(details.vt_registrar)}`);
      if (details.vt_cert_issuer) detailParts.push(`issuer=${String(details.vt_cert_issuer)}`);
      if (Array.isArray(details.vt_malicious_labels) && details.vt_malicious_labels.length) {
        detailParts.push(`labels=${details.vt_malicious_labels.slice(0, 4).join(',')}`);
      }
      if (Array.isArray(details.vt_malicious_engines) && details.vt_malicious_engines.length) {
        detailParts.push(`engines=${details.vt_malicious_engines.slice(0, 4).join(',')}`);
      }
      if (detailParts.length) {
        lines.push(detailParts.join(' | '));
      }
      return lines.join('\n').slice(0, 390);
    }

    function renderIntelApiInsights(lookupRows, keywordRows) {
      const lookups = Array.isArray(lookupRows) ? lookupRows : [];
      const keywords = Array.isArray(keywordRows) ? keywordRows : [];
      if (intelApiMapMeta) {
        if (!lookups.length) {
          intelApiMapMeta.textContent = 'Sin consultas recientes de proveedores para esta investigación.';
        } else {
          const providers = {};
          let highRisk = 0;
          lookups.forEach((row) => {
            const provider = String(row?.provider || 'unknown').toLowerCase();
            providers[provider] = (providers[provider] || 0) + 1;
            const mal = Number(row?.summary?.malicious || 0);
            const sus = Number(row?.summary?.suspicious || 0);
            const abuse = Number(row?.details?.abuse_score || 0);
            if (mal > 0 || sus > 0 || abuse >= 40) {
              highRisk += 1;
            }
          });
          const providerLabel = Object.entries(providers)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 3)
            .map(([provider, hits]) => `${provider}:${hits}`)
            .join(' | ');
          intelApiMapMeta.textContent = `${lookups.length} consultas | high-risk:${highRisk} | ${keywords.length} keywords | ${providerLabel || 'sin proveedor'}`;
        }
      }
      if (!intelApiKeywordsWrap) return;
      intelApiKeywordsWrap.innerHTML = '';
      if (!keywords.length) {
        const empty = document.createElement('span');
        empty.className = 'mut';
        empty.textContent = lookups.length ? 'No hay keywords frecuentes en los resultados de proveedores.' : 'Sin keywords comunes detectadas.';
        intelApiKeywordsWrap.appendChild(empty);
        return;
      }
      keywords.slice(0, 12).forEach((row) => {
        const chip = document.createElement('span');
        chip.className = 'intel-api-keyword-chip';
        const keywordLabel = String(row?.keyword || '-').replace(/_/g, ' ');
        chip.textContent = keywordLabel;
        const hits = document.createElement('b');
        hits.textContent = `x${Number(row?.hits || 0)}`;
        chip.appendChild(hits);
        intelApiKeywordsWrap.appendChild(chip);
      });
    }

    function enrichGraphWithApiResults(baseGraph, lookupRows, keywordRows) {
      const graph = normalizeGraphPayload(baseGraph || { nodes: [], edges: [] });
      const lookups = Array.isArray(lookupRows) ? lookupRows.filter((row) => row && typeof row === 'object') : [];
      const keywords = Array.isArray(keywordRows) ? keywordRows.filter((row) => row && typeof row === 'object') : [];
      if (!lookups.length) {
        return graph;
      }

      const investigationDomain = normalizeDomainValue(
        selectedInvestigation?.site_domain || selectedInvestigation?.hostname || selectedInvestigation?.title || ''
      );
      let rootNode = graph.nodes.find((node) => {
        const labelDomain = normalizeDomainValue(node?.label || '');
        const tags = Array.isArray(node?.tags) ? node.tags : [];
        return (investigationDomain && labelDomain === investigationDomain)
          || tags.some((tag) => normalizeDomainValue(tag) === investigationDomain);
      }) || null;

      if (!rootNode) {
        const centerX = Number(intelWrap?.clientWidth || 920) * 0.5;
        const centerY = Number(intelWrap?.clientHeight || 470) * 0.46;
        rootNode = ensureGraphNode(graph, {
          id: makeStableGraphId('root', investigationDomain || selectedInvestigation?.title || 'investigation'),
          label: investigationDomain || String(selectedInvestigation?.title || 'Investigación'),
          color: '#5dc8ff',
          x: Math.round(centerX),
          y: Math.round(centerY),
          tags: ['investigation', investigationDomain || 'scope'],
          notes: 'Nodo raíz de investigación (contexto de proveedores)'
        });
      }

      const providerNodeMap = new Map();
      const baseRadius = 165;
      lookups.slice(0, 30).forEach((lookup, index) => {
        const provider = String(lookup?.provider || 'unknown').toLowerCase();
        const target = String(lookup?.target || '').trim();
        if (!target) return;
        const meta = parseLookupTargetMeta(target, lookup?.target_type || '');
        const angle = (index / Math.max(1, Math.min(lookups.length, 30))) * Math.PI * 2;
        const radius = baseRadius + (index % 4) * 20;
        const indicatorX = Math.round(Number(rootNode.x || 450) + Math.cos(angle) * radius);
        const indicatorY = Math.round(Number(rootNode.y || 235) + Math.sin(angle) * (radius * 0.65));

        const summary = (lookup && typeof lookup.summary === 'object' && lookup.summary) ? lookup.summary : {};
        const details = (lookup && typeof lookup.details === 'object' && lookup.details) ? lookup.details : {};
        const tags = ['api', provider, String(meta.type || 'unknown')];
        if (Number(summary.malicious || 0) > 0) tags.push('malicious');
        if (Number(summary.suspicious || 0) > 0) tags.push('suspicious');
        if (Number(summary.abuseConfidenceScore || 0) >= 40) tags.push('abuse-high');
        if (Number(details.abuse_score || 0) >= 40) tags.push('abuse-high');
        if (Number(details.vt_reputation || 0) < 0) tags.push('negative-reputation');
        if (Array.isArray(details.vt_malicious_labels)) {
          details.vt_malicious_labels.slice(0, 2).forEach((label) => {
            const normalized = String(label || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            if (normalized) {
              tags.push(`label:${normalized}`);
            }
          });
        }
        if (!lookup?.ok) tags.push('error');

        const indicatorNode = ensureGraphNode(graph, {
          id: makeStableGraphId('api_i', `${provider}:${meta.display || target}`),
          label: String(meta.display || target).slice(0, 72),
          color: providerColor(provider),
          x: indicatorX,
          y: indicatorY,
          tags,
          notes: summarizeLookup(lookup, meta)
        });

        ensureGraphEdge(graph, {
          id: makeStableGraphId('api_e', `${rootNode.id}>${indicatorNode.id}>lookup`),
          from: rootNode.id,
          to: indicatorNode.id,
          label: `lookup:${provider}`,
          color: '#7eb6de'
        });

        if (!providerNodeMap.has(provider)) {
          const providerIndex = providerNodeMap.size;
          const providerNode = ensureGraphNode(graph, {
            id: makeStableGraphId('api_p', provider),
            label: provider,
            color: providerColor(provider),
            x: Math.round(Number(rootNode.x || 450) - 210 + providerIndex * 95),
            y: Math.round(Number(rootNode.y || 235) - 150),
            tags: ['api-provider', provider],
            notes: `Proveedor: ${provider}`
          });
          providerNodeMap.set(provider, providerNode);
        }
        const providerNode = providerNodeMap.get(provider);
        if (providerNode) {
          ensureGraphEdge(graph, {
            id: makeStableGraphId('api_ep', `${providerNode.id}>${indicatorNode.id}`),
            from: providerNode.id,
            to: indicatorNode.id,
            label: `status:${Number(lookup?.status || 0)}`,
            color: '#8ba9be'
          });
        }

        if (meta.domain && meta.domain !== investigationDomain) {
          const domainNode = ensureGraphNode(graph, {
            id: makeStableGraphId('api_d', meta.domain),
            label: meta.domain,
            color: '#7ab8ff',
            x: Math.round(indicatorX + 54),
            y: Math.round(indicatorY + 42),
            tags: ['domain', 'resolved-domain'],
            notes: `Dominio asociado por consulta de proveedor: ${meta.domain}`
          });
          ensureGraphEdge(graph, {
            id: makeStableGraphId('api_ed', `${indicatorNode.id}>${domainNode.id}`),
            from: indicatorNode.id,
            to: domainNode.id,
            label: 'resolved-domain',
            color: '#7fa4c2'
          });
        }

        const detailDomain = normalizeDomainValue(String(details.related_domain || ''));
        if (detailDomain && detailDomain !== investigationDomain && detailDomain !== meta.domain) {
          const relatedDomainNode = ensureGraphNode(graph, {
            id: makeStableGraphId('api_rd', detailDomain),
            label: detailDomain,
            color: '#82c2ff',
            x: Math.round(indicatorX - 56),
            y: Math.round(indicatorY + 54),
            tags: ['domain', 'api-derived'],
            notes: `Dominio derivado de ${provider}`
          });
          ensureGraphEdge(graph, {
            id: makeStableGraphId('api_erd', `${indicatorNode.id}>${relatedDomainNode.id}`),
            from: indicatorNode.id,
            to: relatedDomainNode.id,
            label: 'related-domain',
            color: '#7fa4c2'
          });
        }

        const detailIp = String(details.related_ip || '').trim();
        if (/^\d{1,3}(?:\.\d{1,3}){3}$/.test(detailIp) && detailIp !== target) {
          const ipNode = ensureGraphNode(graph, {
            id: makeStableGraphId('api_ip', detailIp),
            label: detailIp,
            color: '#9aa8ff',
            x: Math.round(indicatorX + 72),
            y: Math.round(indicatorY - 48),
            tags: ['ip', 'api-derived'],
            notes: `IP relacionada por ${provider}`
          });
          ensureGraphEdge(graph, {
            id: makeStableGraphId('api_eip', `${indicatorNode.id}>${ipNode.id}`),
            from: indicatorNode.id,
            to: ipNode.id,
            label: 'related-ip',
            color: '#8b95cd'
          });
        }

        const countryCode = String(details.country_code || '').trim().toUpperCase();
        const countryName = String(details.country_name || '').trim();
        if (countryCode || countryName) {
          const countryLabel = countryName ? `${countryName}${countryCode ? ` (${countryCode})` : ''}` : countryCode;
          const countryNode = ensureGraphNode(graph, {
            id: makeStableGraphId('api_cc', countryLabel),
            label: countryLabel,
            color: '#7ac6bf',
            x: Math.round(indicatorX - 78),
            y: Math.round(indicatorY - 52),
            tags: ['geo', 'api-derived'],
            notes: `Geolocalizacion detectada por ${provider}`
          });
          ensureGraphEdge(graph, {
            id: makeStableGraphId('api_ec', `${indicatorNode.id}>${countryNode.id}`),
            from: indicatorNode.id,
            to: countryNode.id,
            label: 'country',
            color: '#7ea4b1'
          });
        }

        const hostnames = Array.isArray(details.hostnames) ? details.hostnames : [];
        hostnames.slice(0, 3).forEach((hostname, hostIndex) => {
          const hostLabel = normalizeDomainValue(String(hostname || ''));
          if (!hostLabel) return;
          const hostNode = ensureGraphNode(graph, {
            id: makeStableGraphId('api_hn', hostLabel),
            label: hostLabel,
            color: '#95b7ff',
            x: Math.round(indicatorX + 36 + hostIndex * 24),
            y: Math.round(indicatorY + 78 + hostIndex * 18),
            tags: ['hostname', 'api-derived'],
            notes: `Hostname asociado (${provider})`
          });
          ensureGraphEdge(graph, {
            id: makeStableGraphId('api_eh', `${indicatorNode.id}>${hostNode.id}`),
            from: indicatorNode.id,
            to: hostNode.id,
            label: 'hostname',
            color: '#8ba9be'
          });
        });
      });

      if (keywords.length) {
        const keywordHub = ensureGraphNode(graph, {
          id: makeStableGraphId('api_kw', `${rootNode.id}:hub`),
          label: 'api-keywords',
          color: '#7ac6bf',
          x: Math.round(Number(rootNode.x || 450)),
          y: Math.round(Number(rootNode.y || 235) + 170),
          tags: ['keywords', 'api'],
          notes: 'Keywords comunes detectadas en resultados de proveedores'
        });
        ensureGraphEdge(graph, {
          id: makeStableGraphId('api_kw_e', `${rootNode.id}>${keywordHub.id}`),
          from: rootNode.id,
          to: keywordHub.id,
          label: 'api-keywords',
          color: '#7eb6de'
        });
        keywords.slice(0, 8).forEach((row, index) => {
          const keyword = String(row?.keyword || '').trim();
          if (!keyword) return;
          const hits = Number(row?.hits || 0);
          const angle = (index / Math.max(1, Math.min(keywords.length, 8))) * Math.PI * 2;
          const keywordNode = ensureGraphNode(graph, {
            id: makeStableGraphId('kw', keyword),
            label: `#${keyword}`.slice(0, 58),
            color: '#58bfa7',
            x: Math.round(Number(keywordHub.x || 450) + Math.cos(angle) * 130),
            y: Math.round(Number(keywordHub.y || 360) + Math.sin(angle) * 62),
            tags: ['keyword', 'api'],
            notes: `Apariciones en consultas de proveedores: ${hits}`
          });
          ensureGraphEdge(graph, {
            id: makeStableGraphId('kw_e', `${keywordHub.id}>${keywordNode.id}`),
            from: keywordHub.id,
            to: keywordNode.id,
            label: `hits:${hits}`,
            color: '#7fa4c2'
          });
        });
      }

      return graph;
    }

    if (intelWrap && intelSvg && intelNodeLayer) {
      renderIntelApiInsights(intelApiLookupMapRows, intelApiCommonKeywords);
      const baseGraph = selectedInvestigation?.graph || { nodes: [], edges: [] };
      const initialGraph = enrichGraphWithApiResults(baseGraph, intelApiLookupMapRows, intelApiCommonKeywords);
      const vtLookupIndex = buildVtLookupIndex(intelApiLookupMapRows);
      const editor = makeGraphRenderer({
        wrap: intelWrap,
        svg: intelSvg,
        nodeLayer: intelNodeLayer,
        graph: initialGraph,
        readOnly: false,
        nodeListSelect,
        vtLookupIndex,
        controls: {
          layoutSelect: intelLayoutMode,
          layoutApplyButton: intelLayoutApply,
          fitButton: intelFitGraph,
          zoomInButton: intelZoomIn,
          zoomOutButton: intelZoomOut,
          zoomResetButton: intelZoomReset,
          fullscreenButton: intelFullscreen,
          fullscreenButtonAlt: intelDockFullscreen || intelWorkspaceFullscreen,
          zoomStatus: intelZoomStatus,
          zoomStatusAlt: intelDockZoomStatus
        },
        onSelectNode(node) {
          if (!node) {
            if (nodeLabelInput) nodeLabelInput.value = '';
            if (nodeColorInput) nodeColorInput.value = '#5dc8ff';
            if (nodeTagsInput) nodeTagsInput.value = '';
            if (nodeNotesInput) nodeNotesInput.value = '';
            if (nodePreviewLabel) nodePreviewLabel.textContent = 'Sin nodo seleccionado.';
            if (nodePreviewTags) nodePreviewTags.textContent = '';
            if (nodePreviewNotes) nodePreviewNotes.textContent = '';
            return;
          }
          if (nodeLabelInput) nodeLabelInput.value = node.label || '';
          if (nodeColorInput) nodeColorInput.value = /^#[0-9a-fA-F]{6}$/.test(String(node.color || '')) ? node.color : '#5dc8ff';
          if (nodeTagsInput) nodeTagsInput.value = Array.isArray(node.tags) ? node.tags.join(', ') : '';
          if (nodeNotesInput) nodeNotesInput.value = node.notes || '';
          if (nodePreviewLabel) nodePreviewLabel.textContent = `Nodo: ${String(node.label || '')} (${String(node.id || '')})`;
          if (nodePreviewTags) nodePreviewTags.textContent = `Tags: ${Array.isArray(node.tags) && node.tags.length ? node.tags.join(', ') : '-'}`;
          if (nodePreviewNotes) nodePreviewNotes.textContent = String(node.notes || '').trim() || 'Sin notas en este nodo.';
        },
        edgeListSelect,
        edgeFromSelect,
        edgeToSelect
      });

      nodeListSelect?.addEventListener('change', () => {
        if (!editor) return;
        const nodeId = String(nodeListSelect.value || '');
        editor.selectNode(nodeId);
      });

      nodeAddButton?.addEventListener('click', () => {
        if (!editor) return;
        const label = (nodeLabelInput?.value || '').trim() || 'node';
        const color = /^#[0-9a-fA-F]{6}$/.test(String(nodeColorInput?.value || '')) ? String(nodeColorInput?.value) : '#5dc8ff';
        const tags = tagsFromInput(nodeTagsInput?.value || '');
        const notes = (nodeNotesInput?.value || '').trim().slice(0, 400);
        const bounds = intelWrap.getBoundingClientRect();
        editor.addNode({
          id: makeNodeId(),
          label: label.slice(0, 120),
          color,
          x: Math.max(40, Math.min(bounds.width - 40, 80 + Math.random() * (bounds.width - 160))),
          y: Math.max(40, Math.min(bounds.height - 40, 80 + Math.random() * (bounds.height - 160))),
          tags,
          notes
        });
      });

      nodeUpdateButton?.addEventListener('click', () => {
        if (!editor) return;
        const ok = editor.updateSelectedNode({
          label: ((nodeLabelInput?.value || '').trim() || 'node').slice(0, 120),
          color: /^#[0-9a-fA-F]{6}$/.test(String(nodeColorInput?.value || '')) ? String(nodeColorInput?.value) : '#5dc8ff',
          tags: tagsFromInput(nodeTagsInput?.value || ''),
          notes: (nodeNotesInput?.value || '').trim().slice(0, 400)
        });
        if (!ok) {
          alert('Selecciona un nodo para actualizar.');
        }
      });

      nodeDeleteButton?.addEventListener('click', () => {
        if (!editor) return;
        const ok = editor.removeSelectedNode();
        if (!ok) {
          alert('Selecciona un nodo para eliminar.');
        }
      });

      edgeAddButton?.addEventListener('click', () => {
        if (!editor || !edgeFromSelect || !edgeToSelect) return;
        const from = edgeFromSelect.value;
        const to = edgeToSelect.value;
        if (!from || !to || from === to) {
          alert('Selecciona nodos origen y destino distintos.');
          return;
        }
        editor.addEdge({
          id: makeEdgeId(),
          from,
          to,
          label: (edgeLabelInput?.value || '').trim().slice(0, 120),
          color: /^#[0-9a-fA-F]{6}$/.test(String(edgeColorInput?.value || '')) ? String(edgeColorInput?.value) : '#94a3b8'
        });
      });

      edgeDeleteButton?.addEventListener('click', () => {
        if (!editor || !edgeListSelect) return;
        if (!edgeListSelect.value) {
          alert('Selecciona una conexion para eliminar.');
          return;
        }
        editor.removeEdge(edgeListSelect.value);
      });

      intelSaveForm?.addEventListener('submit', () => {
        if (!editor || !intelGraphJsonInput) return;
        intelGraphJsonInput.value = JSON.stringify(editor.getGraph());
      });

      const proxyClick = (targetButton, triggerButton) => {
        triggerButton?.addEventListener('click', () => {
          targetButton?.click();
        });
      };
      proxyClick(intelFitGraph, intelDockFit);
      proxyClick(intelZoomIn, intelDockZoomIn);
      proxyClick(intelZoomOut, intelDockZoomOut);
      proxyClick(intelZoomReset, intelDockZoomReset);
      proxyClick(intelFullscreen, intelWorkspaceFullscreen);

      intelLayoutCycle?.addEventListener('click', () => {
        if (!(intelLayoutMode instanceof HTMLSelectElement)) {
          return;
        }
        const optionCount = intelLayoutMode.options.length;
        if (!optionCount) {
          return;
        }
        intelLayoutMode.selectedIndex = (intelLayoutMode.selectedIndex + 1) % optionCount;
        intelLayoutApply?.click();
      });

      document.addEventListener('fullscreenchange', () => {
        if (!intelWorkspaceFullscreen || !intelWrap) {
          return;
        }
        const isActive = document.fullscreenElement === intelWrap;
        intelWorkspaceFullscreen.textContent = isActive ? 'Salir pantalla completa' : 'Pantalla completa';
      });
    }

    const sharedWrap = document.getElementById('shared-canvas-wrap');
    const sharedSvg = document.getElementById('shared-svg');
    const sharedNodeLayer = document.getElementById('shared-node-layer');
    const sharedLayoutMode = document.getElementById('shared-layout-mode');
    const sharedLayoutApply = document.getElementById('shared-layout-apply');
    const sharedFitGraph = document.getElementById('shared-fit-graph');
    const sharedZoomIn = document.getElementById('shared-zoom-in');
    const sharedZoomOut = document.getElementById('shared-zoom-out');
    const sharedZoomReset = document.getElementById('shared-zoom-reset');
    const sharedZoomStatus = document.getElementById('shared-zoom-status');
    const sharedFullscreen = document.getElementById('shared-fullscreen');
    const sharedNodeLabel = document.getElementById('shared-node-label');
    const sharedNodeTags = document.getElementById('shared-node-tags');
    const sharedNodeNotes = document.getElementById('shared-node-notes');
    if (sharedWrap && sharedSvg && sharedNodeLayer && sharedInvestigation?.graph) {
      const sharedVtIndex = buildVtLookupIndex(intelApiLookupMapRows);
      makeGraphRenderer({
        wrap: sharedWrap,
        svg: sharedSvg,
        nodeLayer: sharedNodeLayer,
        graph: sharedInvestigation.graph,
        readOnly: true,
        vtLookupIndex: sharedVtIndex,
        controls: {
          layoutSelect: sharedLayoutMode,
          layoutApplyButton: sharedLayoutApply,
          fitButton: sharedFitGraph,
          zoomInButton: sharedZoomIn,
          zoomOutButton: sharedZoomOut,
          zoomResetButton: sharedZoomReset,
          fullscreenButton: sharedFullscreen,
          zoomStatus: sharedZoomStatus
        },
        onSelectNode(node) {
          if (!node) {
            if (sharedNodeLabel) sharedNodeLabel.textContent = 'Sin nodo seleccionado.';
            if (sharedNodeTags) sharedNodeTags.textContent = '';
            if (sharedNodeNotes) sharedNodeNotes.textContent = '';
            return;
          }
          if (sharedNodeLabel) sharedNodeLabel.textContent = `Nodo: ${String(node.label || '')} (${String(node.id || '')})`;
          if (sharedNodeTags) sharedNodeTags.textContent = `Tags: ${Array.isArray(node.tags) && node.tags.length ? node.tags.join(', ') : '-'}`;
          if (sharedNodeNotes) sharedNodeNotes.textContent = String(node.notes || '').trim() || 'Sin notas en este nodo.';
        }
      });
    }

    const apiKeyToggleButtons = Array.from(document.querySelectorAll('[data-toggle-api-key]'));
    apiKeyToggleButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const targetId = String(button.getAttribute('data-toggle-api-key') || '');
        if (!targetId) return;
        const input = document.getElementById(targetId);
        if (!(input instanceof HTMLInputElement)) return;
        const masked = String(input.dataset.apiKeyMasked || '');
        const plain = String(input.dataset.apiKeyPlain || '');
        const revealed = String(input.dataset.apiKeyRevealed || '0') === '1';
        if (revealed) {
          input.value = masked;
          input.dataset.apiKeyRevealed = '0';
          button.textContent = 'ver';
          return;
        }
        if (plain !== '') {
          input.value = plain;
          input.dataset.apiKeyRevealed = '1';
          button.textContent = 'ocultar';
          return;
        }
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        button.textContent = visible ? 'ver' : 'ocultar';
      });
    });

    const apiKeyForms = Array.from(document.querySelectorAll('.api-key-row-form'));
    apiKeyForms.forEach((form) => {
      form.addEventListener('submit', () => {
        const input = form.querySelector('input[name="api_key"]');
        if (!(input instanceof HTMLInputElement)) return;
        const masked = String(input.dataset.apiKeyMasked || '');
        const plain = String(input.dataset.apiKeyPlain || '');
        if (plain && masked && input.value.trim() === masked) {
          // Keep existing key if user submits without revealing/editing.
          input.value = plain;
        }
      });
    });

    const homeExtensionMapEl = document.getElementById('home-extension-map');
    const homeWebMapEl = document.getElementById('home-web-map');
    const homeExtensionBody = document.getElementById('home-extension-country-body');
    const homeWebBody = document.getElementById('home-web-body');
    const homeExtensionTotal = document.getElementById('home-extension-total');
    const homeExtensionCountries = document.getElementById('home-extension-countries');
    const homeWebCount = document.getElementById('home-web-count');
    const homeWebLast = document.getElementById('home-web-last');

    function setTableMessage(tableBodyEl, message, columns) {
      if (!tableBodyEl) return;
      tableBodyEl.innerHTML = '';
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = columns;
      td.className = 'mut';
      td.textContent = message;
      tr.appendChild(td);
      tableBodyEl.appendChild(tr);
    }

    function parseSortableValue(raw) {
      const text = String(raw || '').replace(/\s+/g, ' ').trim();
      if (!text) {
        return { type: 'empty', value: '' };
      }

      const maybeDate = Date.parse(text);
      if (
        Number.isFinite(maybeDate) &&
        /\d/.test(text) &&
        (text.includes('-') || text.includes('/') || text.includes(':') || text.includes('T'))
      ) {
        return { type: 'date', value: maybeDate };
      }

      let numericText = text
        .replace(/[%â‚¬$Â£Â¥]/g, '')
        .replace(/\(([^)]+)\)/g, '-$1')
        .replace(/[^\d,.\-]/g, '');
      if (/,/.test(numericText) && /\./.test(numericText)) {
        if (numericText.lastIndexOf(',') > numericText.lastIndexOf('.')) {
          numericText = numericText.replace(/\./g, '').replace(',', '.');
        } else {
          numericText = numericText.replace(/,/g, '');
        }
      } else if (/,/.test(numericText) && !/\./.test(numericText)) {
        const parts = numericText.split(',');
        if (parts.length === 2 && parts[1].length <= 2) {
          numericText = `${parts[0].replace(/,/g, '')}.${parts[1]}`;
        } else {
          numericText = numericText.replace(/,/g, '');
        }
      }
      const numericValue = Number(numericText);
      if (numericText && Number.isFinite(numericValue)) {
        return { type: 'number', value: numericValue };
      }

      return { type: 'text', value: text.toLocaleLowerCase() };
    }

    function compareSortableValues(a, b) {
      const rank = { number: 0, date: 1, text: 2, empty: 3 };
      const ra = Object.prototype.hasOwnProperty.call(rank, a.type) ? rank[a.type] : 9;
      const rb = Object.prototype.hasOwnProperty.call(rank, b.type) ? rank[b.type] : 9;
      if (ra !== rb) {
        return ra - rb;
      }
      if (a.type === 'number' || a.type === 'date') {
        return a.value - b.value;
      }
      if (a.type === 'empty') {
        return 0;
      }
      return String(a.value).localeCompare(String(b.value), undefined, { numeric: true, sensitivity: 'base' });
    }

    function sortTableByColumn(table, columnIndex, direction) {
      const tbody = table?.tBodies?.[0];
      if (!tbody) return;
      const rows = Array.from(tbody.rows || []);
      if (rows.length < 2) return;

      const prepared = rows.map((row, index) => {
        const cell = row.cells[columnIndex];
        const raw = cell?.dataset?.sortValue ?? cell?.textContent ?? '';
        return {
          row,
          index,
          parsed: parseSortableValue(raw)
        };
      });

      prepared.sort((left, right) => {
        const base = compareSortableValues(left.parsed, right.parsed);
        if (base !== 0) {
          return direction === 'desc' ? -base : base;
        }
        return left.index - right.index;
      });

      prepared.forEach((item) => tbody.appendChild(item.row));
    }

    function initSortableTables(root = document) {
      const tables = Array.from(root.querySelectorAll('table'));
      tables.forEach((table) => {
        const thead = table.tHead;
        const tbody = table.tBodies?.[0];
        if (!thead || !tbody || tbody.rows.length < 2) {
          return;
        }
        if (table.dataset.sortableReady === 'true') {
          return;
        }
        const headerRow = thead.rows[thead.rows.length - 1];
        if (!headerRow) {
          return;
        }
        const headers = Array.from(headerRow.cells || []);
        if (!headers.length) {
          return;
        }

        table.dataset.sortableReady = 'true';
        headers.forEach((th, columnIndex) => {
          if (th.dataset.sortable === 'false') {
            return;
          }
          th.classList.add('sortable');
          th.tabIndex = 0;
          th.setAttribute('role', 'button');
          th.dataset.sortDir = '';

          const triggerSort = () => {
            const nextDir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';
            headers.forEach((other) => {
              if (other === th) return;
              other.dataset.sortDir = '';
              other.classList.remove('sort-asc', 'sort-desc');
            });
            th.dataset.sortDir = nextDir;
            th.classList.toggle('sort-asc', nextDir === 'asc');
            th.classList.toggle('sort-desc', nextDir === 'desc');
            sortTableByColumn(table, columnIndex, nextDir);
          };

          th.addEventListener('click', triggerSort);
          th.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              triggerSort();
            }
          });
        });
      });
    }

    function initBulkReviewActions() {
      const form = document.getElementById('bulk-review-form');
      if (!form) {
        return;
      }
      const checkboxes = Array.from(document.querySelectorAll('.bulk-review-checkbox'));
      const selectAll = document.getElementById('bulk-review-select-all');
      const selectPending = document.getElementById('bulk-review-select-pending');
      const clearButton = document.getElementById('bulk-review-clear');
      const submitButton = document.getElementById('bulk-review-submit');
      const countEl = document.getElementById('bulk-review-count');
      const statusSelect = document.getElementById('bulk-review-status');

      const updateState = () => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
        if (countEl) {
          countEl.textContent = `${selected} seleccionadas`;
        }
        if (submitButton) {
          submitButton.disabled = selected <= 0;
        }
        if (selectAll) {
          const allSelected = checkboxes.length > 0 && selected === checkboxes.length;
          const hasAnySelected = selected > 0 && selected < checkboxes.length;
          selectAll.checked = allSelected;
          selectAll.indeterminate = hasAnySelected;
        }
      };

      checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateState);
      });

      if (selectAll) {
        selectAll.addEventListener('change', () => {
          const checked = Boolean(selectAll.checked);
          checkboxes.forEach((checkbox) => {
            checkbox.checked = checked;
          });
          updateState();
        });
      }

      if (selectPending) {
        selectPending.addEventListener('click', () => {
          checkboxes.forEach((checkbox) => {
            checkbox.checked = String(checkbox.dataset.reviewStatus || 'pending') === 'pending';
          });
          updateState();
        });
      }

      if (clearButton) {
        clearButton.addEventListener('click', () => {
          checkboxes.forEach((checkbox) => {
            checkbox.checked = false;
          });
          updateState();
        });
      }

      form.addEventListener('submit', (event) => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
        if (selected <= 0) {
          event.preventDefault();
          alert('Selecciona al menos una alerta para revisión masiva.');
          return;
        }
        const status = String(statusSelect?.value || 'pending');
        if (!confirm(`¿Aplicar estado "${status}" a ${selected} alertas seleccionadas?`)) {
          event.preventDefault();
        }
      });

      updateState();
    }

    function normalizeUrlCandidate(value) {
      try {
        return new URL(String(value || ''), window.location.href).toString();
      } catch (error) {
        return String(value || '');
      }
    }

    function ensureStylesheetLoaded(href) {
      const normalizedHref = normalizeUrlCandidate(href);
      if (!normalizedHref) return;
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"][href]'));
      if (links.some((link) => normalizeUrlCandidate(link.getAttribute('href')) === normalizedHref)) {
        return;
      }
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = normalizedHref;
      document.head.appendChild(link);
    }

    function loadScriptOnce(src) {
      const normalizedSrc = normalizeUrlCandidate(src);
      if (!normalizedSrc) return Promise.resolve(false);
      const scripts = Array.from(document.querySelectorAll('script[src]'));
      const existing = scripts.find((script) => normalizeUrlCandidate(script.getAttribute('src')) === normalizedSrc);
      if (existing) {
        if (window.L && typeof window.L.map === 'function') {
          return Promise.resolve(true);
        }
        return new Promise((resolve) => {
          existing.addEventListener('load', () => resolve(!!(window.L && typeof window.L.map === 'function')), { once: true });
          existing.addEventListener('error', () => resolve(false), { once: true });
          setTimeout(() => resolve(!!(window.L && typeof window.L.map === 'function')), 2500);
        });
      }
      return new Promise((resolve) => {
        const script = document.createElement('script');
        script.src = normalizedSrc;
        script.async = true;
        script.defer = true;
        script.addEventListener('load', () => resolve(!!(window.L && typeof window.L.map === 'function')), { once: true });
        script.addEventListener('error', () => resolve(false), { once: true });
        document.head.appendChild(script);
      });
    }

    async function ensureLeafletLoaded() {
      if (window.L && typeof window.L.map === 'function') {
        return true;
      }
      if (!leafletEnsurePromise) {
        leafletEnsurePromise = (async () => {
          const cssCandidates = [
            homeLeafletCssUrl,
            'assets/vendor/leaflet/leaflet.css',
            '/assets/vendor/leaflet/leaflet.css'
          ];
          cssCandidates.forEach((href) => ensureStylesheetLoaded(href));

          const jsCandidates = [
            homeLeafletJsUrl,
            'assets/vendor/leaflet/leaflet.js',
            '/assets/vendor/leaflet/leaflet.js'
          ];
          for (const src of jsCandidates) {
            if (await loadScriptOnce(src)) {
              return true;
            }
          }
          return !!(window.L && typeof window.L.map === 'function');
        })();
      }
      return leafletEnsurePromise;
    }

    function createOfflineTileLayer() {
      const fallback = L.gridLayer({ attribution: 'Offline base', noWrap: true, opacity: 0.95 });
      fallback.createTile = function (coords) {
        const size = this.getTileSize();
        const canvas = document.createElement('canvas');
        canvas.width = size.x;
        canvas.height = size.y;
        const ctx = canvas.getContext('2d');
        if (!ctx) return canvas;
        ctx.fillStyle = '#0c2235';
        ctx.fillRect(0, 0, size.x, size.y);
        ctx.strokeStyle = 'rgba(99, 217, 255, 0.18)';
        ctx.strokeRect(0.5, 0.5, size.x - 1, size.y - 1);
        ctx.strokeStyle = 'rgba(87, 240, 190, 0.1)';
        ctx.beginPath();
        ctx.moveTo(0, size.y / 2);
        ctx.lineTo(size.x, size.y / 2);
        ctx.moveTo(size.x / 2, 0);
        ctx.lineTo(size.x / 2, size.y);
        ctx.stroke();
        ctx.fillStyle = 'rgba(220, 238, 255, 0.65)';
        ctx.font = '11px JetBrains Mono, monospace';
        ctx.fillText(`${coords.z}/${coords.x}/${coords.y}`, 8, 16);
        return canvas;
      };
      return fallback;
    }

    async function loadLocalWorldGeoJson() {
      if (localWorldGeoPromise) {
        return localWorldGeoPromise;
      }
      localWorldGeoPromise = (async () => {
        const candidates = [
          homeLeafletWorldGeoJsonUrl,
          'assets/vendor/leaflet/data/world-countries.geo.json',
          '/assets/vendor/leaflet/data/world-countries.geo.json'
        ];
        for (const candidate of candidates) {
          try {
            const response = await fetch(candidate, { cache: 'force-cache' });
            if (!response.ok) {
              continue;
            }
            const json = await response.json();
            if (json && String(json.type || '') === 'FeatureCollection' && Array.isArray(json.features)) {
              return json;
            }
          } catch (error) {
            // Ignore and try next candidate.
          }
        }
        return null;
      })();
      return localWorldGeoPromise;
    }

    function createOfflineCountriesLayer() {
      const group = L.layerGroup();
      loadLocalWorldGeoJson().then((geojson) => {
        if (!geojson) {
          return;
        }
        L.geoJSON(geojson, {
          interactive: false,
          attribution: 'Countries: Natural Earth (local cache)',
          style: () => ({
            color: '#33577a',
            weight: 0.8,
            opacity: 0.95,
            fillColor: '#10263b',
            fillOpacity: 0.9
          })
        }).addTo(group);
      }).catch(() => {
        // Keep map available even if local geojson could not be loaded.
      });
      return group;
    }

    function makeLeafletMap(targetEl, center = [20, 0], zoom = 2) {
      if (!targetEl || !window.L) return null;
      const map = L.map(targetEl, {
        center,
        zoom,
        minZoom: 2,
        maxZoom: 7,
        zoomControl: true,
        worldCopyJump: true
      });
      const baseLayers = {
        'Offline Countries (local)': createOfflineCountriesLayer(),
        'Offline Grid': createOfflineTileLayer()
      };
      baseLayers['Offline Countries (local)'].addTo(map);
      L.control.layers(baseLayers, null, { position: 'topright', collapsed: true }).addTo(map);
      return map;
    }

    async function loadHomeGeoData() {
      if (!homeExtensionMapEl && !homeWebMapEl) {
        return;
      }
      const leafletReady = await ensureLeafletLoaded();
      if (!leafletReady || !window.L) {
        setTableMessage(homeExtensionBody, 'Mapa no disponible (Leaflet no cargado).', 4);
        setTableMessage(homeWebBody, 'Mapa no disponible (Leaflet no cargado).', 7);
        return;
      }
      let payload = null;
      try {
        const response = await fetch('dashboard.php?format=home_geo', { cache: 'no-store' });
        if (!response.ok) {
          throw new Error(`home_geo_failed_${response.status}`);
        }
        payload = await response.json();
      } catch (error) {
        setTableMessage(homeExtensionBody, 'No se pudo cargar geointeligencia de usuarios.', 4);
        setTableMessage(homeWebBody, 'No se pudo cargar geointeligencia de webs.', 7);
        return;
      }
      const data = payload && payload.status === 'ok' ? payload.data : null;
      if (!data || typeof data !== 'object') {
        setTableMessage(homeExtensionBody, 'Sin datos de geointeligencia.', 4);
        setTableMessage(homeWebBody, 'Sin datos de geointeligencia.', 7);
        return;
      }

      const extensionPoints = Array.isArray(data.extension_points) ? data.extension_points : [];
      const extensionCountriesData = Array.isArray(data.extension_country_counts) ? data.extension_country_counts : [];
      const websitePoints = Array.isArray(data.website_points) ? data.website_points : [];
      const websiteRows = Array.isArray(data.website_rows) ? data.website_rows : [];

      const extensionMap = makeLeafletMap(homeExtensionMapEl);
      if (extensionMap) {
        extensionPoints.forEach((point) => {
          const lat = Number(point.lat || 0);
          const lon = Number(point.lon || 0);
          if (!Number.isFinite(lat) || !Number.isFinite(lon) || (lat === 0 && lon === 0)) return;
          const users = Number(point.users || 0);
          L.circleMarker([lat, lon], {
            radius: Math.max(5, Math.min(22, 4 + Math.log2(Math.max(1, users)) * 3)),
            color: '#ff4d4f',
            fillColor: '#ff2d2f',
            fillOpacity: 0.75,
            weight: 1
          })
            .bindPopup(
              `<b>${escapeHtml(String(point.country_name || point.country_code || '-'))}</b><br>` +
              `Usuarios extension: ${escapeHtml(String(users))}<br>` +
              `Codigo: ${escapeHtml(String(point.country_code || '-'))}`
            )
            .addTo(extensionMap);
        });
        setTimeout(() => extensionMap.invalidateSize(), 80);
      }

      if (homeExtensionTotal) {
        homeExtensionTotal.textContent = String(Number(data.extension_users_total || 0));
      }
      if (homeExtensionCountries) {
        homeExtensionCountries.textContent = String(extensionCountriesData.length);
      }
      if (homeExtensionBody) {
        homeExtensionBody.innerHTML = '';
        if (!extensionCountriesData.length) {
          setTableMessage(homeExtensionBody, 'No hay países con actividad reciente.', 4);
        } else {
          extensionCountriesData.forEach((row) => {
            const tr = document.createElement('tr');
            const languages = Array.isArray(row.languages) && row.languages.length ? row.languages.join(', ') : '-';
            tr.innerHTML =
              `<td>${escapeHtml(String(row.country_name || row.country_code || '-'))}</td>` +
              `<td class="mono">${escapeHtml(String(row.country_code || '-'))}</td>` +
              `<td class="mono">${escapeHtml(String(row.users || 0))}</td>` +
              `<td>${escapeHtml(languages)}</td>`;
            homeExtensionBody.appendChild(tr);
          });
        }
      }

      const webMap = makeLeafletMap(homeWebMapEl);
      if (webMap) {
        websitePoints.forEach((point) => {
          const lat = Number(point.lat || 0);
          const lon = Number(point.lon || 0);
          if (!Number.isFinite(lat) || !Number.isFinite(lon) || (lat === 0 && lon === 0)) return;
          const hits = Number(point.hits || 0);
          L.circleMarker([lat, lon], {
            radius: Math.max(4, Math.min(18, 4 + Math.log2(Math.max(1, hits)) * 2)),
            color: '#ffb347',
            fillColor: '#ff8f1f',
            fillOpacity: 0.65,
            weight: 1
          })
            .bindPopup(
              `<b>${escapeHtml(String(point.hostname || '-'))}</b><br>` +
              `IP: ${escapeHtml(String(point.ip || '-'))}<br>` +
              `ISP: ${escapeHtml(String(point.isp || '-'))}<br>` +
              `Servidor HTTP: ${escapeHtml(String(point.http_server || '-'))}<br>` +
              `Pais: ${escapeHtml(String(point.country_name || point.country_code || '-'))}<br>` +
              `Idioma: ${escapeHtml(String(point.language || '-'))}<br>` +
              `Servicios: ${escapeHtml(Array.isArray(point.services) && point.services.length ? point.services.join(', ') : '-') }<br>` +
              `Hits: ${escapeHtml(String(hits))}`
            )
            .addTo(webMap);
        });
        setTimeout(() => webMap.invalidateSize(), 80);
      }

      if (homeWebCount) {
        homeWebCount.textContent = String(websitePoints.length);
      }
      if (homeWebLast) {
        homeWebLast.textContent = String(data.generated_at || '-');
      }
      if (homeWebBody) {
        homeWebBody.innerHTML = '';
        if (!websiteRows.length) {
          setTableMessage(homeWebBody, 'No hay webs suficientes para geolocalizar.', 7);
        } else {
          websiteRows.slice(0, 80).forEach((row) => {
            const tr = document.createElement('tr');
            const services = Array.isArray(row.services) && row.services.length
              ? row.services.slice(0, 5).join(', ')
              : (row.http_server ? String(row.http_server) : '-');
            tr.innerHTML =
              `<td class="mono">${escapeHtml(String(row.hostname || '-'))}</td>` +
              `<td class="mono">${escapeHtml(String(row.ip || '-'))}</td>` +
              `<td>${escapeHtml(String(row.isp || '-'))}</td>` +
              `<td>${escapeHtml(String(row.country_name || row.country_code || '-'))}</td>` +
              `<td class="mono">${escapeHtml(String(row.language || '-'))}</td>` +
              `<td>${escapeHtml(services)}</td>` +
              `<td class="mono">${escapeHtml(String(row.hits || 0))}</td>`;
            homeWebBody.appendChild(tr);
          });
        }
      }
    const opsFeedSearch = document.getElementById('ops-feed-search');
    if (opsFeedSearch && eventFeed) {
      opsFeedSearch.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        const groups = eventFeed.querySelectorAll('.event-group');
        groups.forEach(g => {
          const text = (g.textContent || '').toLowerCase();
          g.style.display = (q === '' || text.includes(q)) ? '' : 'none';
        });
        if (eventEmpty) {
          const visible = eventFeed.querySelectorAll('.event-group:not([style*="display: none"])');
          eventEmpty.hidden = visible.length > 0;
        }
      });
    }

    initSortableTables();
    }
    initSortableTables();
    initBulkReviewActions();
    loadHomeGeoData();
    renderDashboardCharts();

    setInterval(async () => {
      try {
        const r = await fetch('dashboard.php?public=1&format=live', { cache: 'no-store' });
        if (!r.ok) return;
        const p = await r.json();
        if (!p.stats) return;
        document.querySelectorAll('[data-live-metric]').forEach((n) => {
          const k = n.getAttribute('data-live-metric');
          if (!k || !Object.prototype.hasOwnProperty.call(p.stats, k)) return;
          const rawValue = p.stats[k];
          if (k === 'review_coverage_pct' || k === 'block_rate_24h') {
            const numeric = Number(rawValue);
            n.textContent = `${Number.isFinite(numeric) ? numeric.toFixed(2) : '0.00'}%`;
            return;
          }
          n.textContent = String(rawValue);
        });
      } catch (e) { console.debug(e); }
    }, 45000);

    const llmChatPanel = document.getElementById('llm-chat-panel');
    if (llmChatPanel) {
      const llmProfileSelect = document.getElementById('llm-profile-select');
      const llmModelOverride = document.getElementById('llm-model-override');
      const llmBearerOverride = document.getElementById('llm-bearer-override');
      const llmAgentOverride = document.getElementById('llm-agent-override');
      const llmMessagesEl = document.getElementById('llm-chat-messages');
      const llmInput = document.getElementById('llm-chat-input-textarea');
      const llmSendBtn = document.getElementById('llm-chat-send');
      const llmClearBtn = document.getElementById('llm-chat-clear');
      const llmSummarizeBtn = document.getElementById('llm-action-summarize');
      const llmExtractIocBtn = document.getElementById('llm-action-extract-ioc');
      const llmGraphIdEl = document.getElementById('llm-graph-id');
      let llmChatHistory = [];
      let llmIsStreaming = false;

      function addLlamaMessage(role, content) {
        if (!llmMessagesEl) return;
        const div = document.createElement('div');
        div.className = 'llm-msg ' + role;
        div.innerHTML = '<div class="msg-meta">' + (role === 'user' ? 'You' : 'AI Analyst') + '</div><div class="msg-body">' + escapeHtml(content).replace(/\n/g, '<br>') + '</div>';
        llmMessagesEl.appendChild(div);
        llmMessagesEl.scrollTop = llmMessagesEl.scrollHeight;
      }

      function setLlmTyping(active) {
        if (!llmMessagesEl) return;
        const existing = llmMessagesEl.querySelector('.llm-typing');
        if (active && !existing) {
          const dots = document.createElement('div');
          dots.className = 'llm-typing';
          dots.innerHTML = '<span></span><span></span><span></span>';
          llmMessagesEl.appendChild(dots);
          llmMessagesEl.scrollTop = llmMessagesEl.scrollHeight;
        } else if (!active && existing) {
          existing.remove();
        }
      }

      async function sendLlmMessage(message) {
        if (llmIsStreaming) return;
        llmIsStreaming = true;
        if (llmSendBtn) llmSendBtn.disabled = true;
        addLlamaMessage('user', message);
        setLlmTyping(true);
        try {
          const profileId = llmProfileSelect ? parseInt(llmProfileSelect.value || '0') : 0;
          const modelOverride = llmModelOverride ? llmModelOverride.value.trim() : '';
          const bearerOverride = llmBearerOverride ? llmBearerOverride.value.trim() : '';
          const agentOverride = llmAgentOverride ? llmAgentOverride.value.trim() : '';
          const graphId = llmGraphIdEl ? parseInt(llmGraphIdEl.value || '0') : 0;
          const options = {};
          if (modelOverride) options.model = modelOverride;
          if (bearerOverride) options.bearer_token = bearerOverride;
          if (agentOverride) options.user_agent = agentOverride;
          const body = {
            action: 'chat',
            profile_id: profileId,
            graph_id: graphId,
            messages: [...llmChatHistory, { role: 'user', content: message }],
            options: options
          };
          const r = await fetch('api/llm.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-API-Key': csrfToken },
            body: JSON.stringify(body)
          });
          const d = await r.json();
          setLlmTyping(false);
          if (d.status === 'ok' && d.content) {
            llmChatHistory.push({ role: 'user', content: message }, { role: 'assistant', content: d.content });
            addLlamaMessage('assistant', d.content);
          } else {
            addLlamaMessage('assistant', '[Error: ' + (d.error || d.message || 'unknown') + ']');
          }
        } catch (e) {
          setLlmTyping(false);
          addLlamaMessage('assistant', '[Network error: ' + e.message + ']');
        } finally {
          llmIsStreaming = false;
          if (llmSendBtn) llmSendBtn.disabled = false;
        }
      }

      if (llmSendBtn) llmSendBtn.addEventListener('click', () => {
        const msg = llmInput ? llmInput.value.trim() : '';
        if (!msg) return;
        if (llmInput) llmInput.value = '';
        sendLlmMessage(msg);
      });

      if (llmInput) llmInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          llmSendBtn.click();
        }
      });

      if (llmClearBtn) llmClearBtn.addEventListener('click', () => {
        llmChatHistory = [];
        if (llmMessagesEl) llmMessagesEl.innerHTML = '';
      });

      if (llmSummarizeBtn) llmSummarizeBtn.addEventListener('click', async () => {
        const graphId = llmGraphIdEl ? parseInt(llmGraphIdEl.value || '0') : 0;
        if (!graphId) return;
        llmIsStreaming = true;
        setLlmTyping(true);
        try {
          const r = await fetch('api/llm.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-API-Key': csrfToken },
            body: JSON.stringify({ action: 'summarize', graph_id: graphId, profile_id: llmProfileSelect ? parseInt(llmProfileSelect.value || '0') : 0 })
          });
          const d = await r.json();
          setLlmTyping(false);
          if (d.status === 'ok' && d.content) {
            addLlamaMessage('assistant', d.content);
            llmChatHistory.push({ role: 'user', content: 'Summarize this investigation.' }, { role: 'assistant', content: d.content });
          } else {
            addLlamaMessage('assistant', '[Summary failed: ' + (d.error || 'unknown') + ']');
          }
        } catch (e) {
          setLlmTyping(false);
          addLlamaMessage('assistant', '[Error: ' + e.message + ']');
        } finally {
          llmIsStreaming = false;
        }
      });

      if (llmExtractIocBtn) llmExtractIocBtn.addEventListener('click', async () => {
        const graphId = llmGraphIdEl ? parseInt(llmGraphIdEl.value || '0') : 0;
        if (!graphId) return;
        llmIsStreaming = true;
        setLlmTyping(true);
        try {
          const r = await fetch('api/llm.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-API-Key': csrfToken },
            body: JSON.stringify({ action: 'extract_iocs', text: document.getElementById('intel-summary') ? document.getElementById('intel-summary').textContent : '', profile_id: llmProfileSelect ? parseInt(llmProfileSelect.value || '0') : 0 })
          });
          const d = await r.json();
          setLlmTyping(false);
          if (d.status === 'ok') {
            const iocList = (d.iocs || []).map(i => i.type + ': ' + i.value).join('\n');
            addLlamaMessage('assistant', 'Extracted IOCs:\n' + (iocList || 'None found'));
          } else {
            addLlamaMessage('assistant', '[IOC extraction failed: ' + (d.error || 'unknown') + ']');
          }
        } catch (e) {
          setLlmTyping(false);
          addLlamaMessage('assistant', '[Error: ' + e.message + ']');
        } finally {
          llmIsStreaming = false;
        }
      });

      if (llmProfileSelect) {
        llmProfileSelect.addEventListener('change', async () => {
          const pid = parseInt(llmProfileSelect.value || '0');
          if (!pid || !llmModelOverride) return;
          try {
            const r = await fetch('api/llm.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-API-Key': csrfToken }, body: JSON.stringify({ action: 'models', profile_id: pid }) });
            const d = await r.json();
            if (d.status === 'ok' && d.models && d.models.length > 0) {
              const datalist = document.getElementById('llm-models-datalist');
              if (datalist) {
                datalist.innerHTML = d.models.map(m => '<option value="' + escapeHtml(m.id) + '">').join('');
              }
            }
          } catch (e) {}
        });
      }
    }

    const autoInvPanel = document.getElementById('auto-inv-panel');
    if (autoInvPanel) {
      const autoInvToggle = document.getElementById('auto-inv-toggle');
      const autoInvRunBtn = document.getElementById('auto-inv-run');
      const autoInvRefreshBtn = document.getElementById('auto-inv-refresh');
      const autoInvJobsList = document.getElementById('auto-inv-jobs-list');
      const autoInvStatusDot = document.getElementById('auto-inv-status-dot');
      const autoInvJobsCount = document.getElementById('auto-inv-jobs-count');

      async function loadAutoInvStatus() {
        try {
          const r = await fetch('api/auto_investigation.php?action=status', { headers: { 'X-API-Key': csrfToken } });
          const d = await r.json();
          if (d.status === 'ok') {
            if (autoInvStatusDot) {
              autoInvStatusDot.className = 'status-dot ' + (d.enabled ? 'on' : 'off');
            }
            if (autoInvToggle) autoInvToggle.textContent = d.enabled ? 'Disable' : 'Enable';
          }
        } catch (e) {}
      }

      async function loadAutoInvJobs() {
        try {
          const r = await fetch('api/auto_investigation.php?action=jobs&limit=30', { headers: { 'X-API-Key': csrfToken } });
          const d = await r.json();
          if (d.status === 'ok' && autoInvJobsList) {
            if (autoInvJobsCount) autoInvJobsCount.textContent = String(d.count || 0);
            autoInvJobsList.innerHTML = (d.jobs || []).map(j => {
              const stageClass = j.status === 'running' ? 'running' : j.status === 'completed' ? 'completed' : j.status === 'failed' ? 'failed' : '';
              return '<div class="auto-inv-job">' +
                '<span class="job-id">#' + j.id + '</span>' +
                '<span class="job-title">' + escapeHtml(j.graph_title || j.report_hostname || 'Job #' + j.id) + '</span>' +
                '<span class="job-stage ' + stageClass + '">' + escapeHtml(j.status || 'queued') + '</span>' +
                (j.report_score ? '<span class="job-score">' + j.report_score + '/100</span>' : '') +
                '</div>';
            }).join('');
          }
        } catch (e) {}
      }

      if (autoInvToggle) autoInvToggle.addEventListener('click', async () => {
        try {
          const r = await fetch('api/auto_investigation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-API-Key': csrfToken },
            body: JSON.stringify({ action: 'toggle' })
          });
          const d = await r.json();
          if (d.status === 'ok') {
            if (autoInvToggle) autoInvToggle.textContent = d.enabled ? 'Disable' : 'Enable';
            if (autoInvStatusDot) autoInvStatusDot.className = 'status-dot ' + (d.enabled ? 'on' : 'off');
          }
        } catch (e) {}
      });

      if (autoInvRunBtn) autoInvRunBtn.addEventListener('click', async () => {
        autoInvRunBtn.disabled = true;
        autoInvRunBtn.textContent = 'Running...';
        try {
          const r = await fetch('api/auto_investigation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-API-Key': csrfToken },
            body: JSON.stringify({ action: 'run' })
          });
          const d = await r.json();
          if (d.status === 'ok' && d.result) {
            const res = d.result;
            alert('Auto-investigation complete.\nNew alerts: ' + (res.new_alerts || 0) + '\nJobs enqueued: ' + (res.jobs_enqueued || 0) + '\nJobs processed: ' + (res.jobs_processed || 0));
            loadAutoInvJobs();
          }
        } catch (e) { alert('Error: ' + e.message); }
        finally {
          autoInvRunBtn.disabled = false;
          autoInvRunBtn.textContent = 'Run Now';
        }
      });

      if (autoInvRefreshBtn) autoInvRefreshBtn.addEventListener('click', () => { loadAutoInvJobs(); loadAutoInvStatus(); });

      loadAutoInvStatus();
      loadAutoInvJobs();
    }

    const blogFeedPanel = document.getElementById('blog-feed-panel');
    if (blogFeedPanel) {
      async function loadBlogFeed() {
        try {
          const r = await fetch('api/blog_feed.php?action=feed&limit=15');
          const d = await r.json();
          if (d.status === 'ok') {
            const grid = document.getElementById('blog-feed-grid');
            if (grid && d.items) {
              grid.innerHTML = d.items.map(item => {
                const pubDate = item.pub_date ? new Date(item.pub_date).toLocaleDateString() : '';
                return '<div class="blog-feed-card">' +
                  '<div class="source">' + escapeHtml(item.source_label || 'Blog') + '</div>' +
                  '<h4><a href="' + escapeHtml(item.link) + '" target="_blank" rel="noopener">' + escapeHtml(item.title || 'Untitled') + '</a></h4>' +
                  '<p>' + escapeHtml(item.description || '') + '</p>' +
                  '<div class="meta"><span>' + escapeHtml(item.author || '') + '</span><span>' + escapeHtml(pubDate) + '</span></div>' +
                  '</div>';
              }).join('');
            }
            const crossGrid = document.getElementById('blog-crosslinks-grid');
            if (crossGrid) {
              loadBlogCrosslinks();
            }
          }
        } catch (e) { console.debug(e); }
      }
      async function loadBlogCrosslinks() {
        try {
          const r = await fetch('api/blog_feed.php?action=crosslinks');
          const d = await r.json();
          if (d.status === 'ok') {
            const grid = document.getElementById('blog-crosslinks-grid');
            if (grid && d.crosslinks) {
              grid.innerHTML = (d.crosslinks || []).slice(0, 10).map(cl =>
                '<div class="crosslink-item">' +
                '<span class="relevance">' + cl.relevance_score + '</span>' +
                '<a href="' + escapeHtml(cl.blog_link) + '" target="_blank" rel="noopener">' + escapeHtml(cl.blog_title) + ' &rarr; ' + escapeHtml(cl.investigation_title) + '</a>' +
                '</div>'
              ).join('');
            }
          }
        } catch (e) { console.debug(e); }
      }
      loadBlogFeed();
    }
  </script>
