<?php
require_once __DIR__ . '/config.php';
$obsUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/obs.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OBS Overlay & Easyslides Multicast Studio</title>
  <style>
    :root {
      --bg-body: #0f172a;
      --bg-panel: #1e293b;
      --bg-panel-alt: #263349;
      --bg-input: #0f172a;
      --border: #334155;
      --text-main: #f8fafc;
      --text-muted: #94a3b8;
      --accent: #f97316;
      --primary: #3b82f6;
      --success: #10b981;
      --danger: #ef4444;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Inter, sans-serif;
      background: var(--bg-body);
      color: var(--text-main);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .topbar {
      height: 60px;
      background: var(--bg-panel);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 22px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 700;
      font-size: 16px;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      border: 1px solid var(--border);
      cursor: pointer;
      color: var(--text-main);
      background: var(--bg-panel-alt);
      text-decoration: none;
      transition: all 0.15s;
    }
    .btn:hover { background: #334155; }
    .btn-primary { background: var(--primary); border-color: var(--primary); color: #fff; }
    .btn-accent { background: var(--accent); border-color: var(--accent); color: #fff; }
    .btn-success { background: var(--success); border-color: var(--success); color: #fff; }
    .btn-danger { background: rgba(239, 68, 68, 0.18); border-color: rgba(239, 68, 68, 0.45); color: #fca5a5; }
    .btn-sm { padding: 5px 10px; font-size: 12px; }

    .container {
      max-width: 1440px;
      width: 100%;
      margin: 0 auto;
      padding: 20px;
      display: grid;
      grid-template-columns: 420px 1fr;
      gap: 20px;
      flex: 1;
    }

    .card {
      background: var(--bg-panel);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 18px;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .card-title {
      font-size: 14px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: #e2e8f0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid var(--border);
      padding-bottom: 10px;
    }

    .field {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .field label {
      font-size: 12px;
      font-weight: 600;
      color: var(--text-muted);
    }

    input[type="text"], input[type="number"], input[type="password"], select {
      background: var(--bg-input);
      color: var(--text-main);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 8px 10px;
      font-size: 13px;
      outline: none;
    }

    .grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }

    .status-pill {
      font-size: 11px;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: 999px;
      text-transform: uppercase;
    }
    .status-connected { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); }
    .status-connecting { background: rgba(249, 115, 22, 0.2); color: #fdba74; border: 1px solid rgba(249, 115, 22, 0.4); }
    .status-idle { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }

    /* Simulated 16:9 OBS Preview Monitor */
    .preview-monitor-wrap {
      width: 100%;
      aspect-ratio: 16 / 9;
      background:
        linear-gradient(135deg, rgba(30, 58, 138, 0.45), rgba(15, 23, 42, 0.75)),
        repeating-conic-gradient(#1e293b 0% 25%, #0f172a 0% 50%) 50% / 32px 32px;
      border: 2px solid var(--border);
      border-radius: 10px;
      overflow: hidden;
      position: relative;
      box-shadow: 0 12px 35px rgba(0, 0, 0, 0.5);
    }

    .preview-iframe {
      width: 1920px;
      height: 1080px;
      border: none;
      transform-origin: top left;
      pointer-events: none;
    }

    .slide-btn-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 10px;
      max-height: 290px;
      overflow-y: auto;
      padding-right: 4px;
    }

    .slide-btn {
      background: var(--bg-input);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 10px 12px;
      text-align: left;
      cursor: pointer;
      color: var(--text-main);
      transition: all 0.12s;
    }
    .slide-btn:hover {
      border-color: var(--primary);
      background: rgba(59, 130, 246, 0.1);
    }
    .slide-btn.active {
      border-color: var(--accent);
      background: rgba(249, 115, 22, 0.18);
    }

    .url-copy-box {
      display: flex;
      gap: 8px;
      align-items: center;
      background: var(--bg-input);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 8px 12px;
      font-family: monospace;
      font-size: 13px;
      color: #38bdf8;
    }

    #toast {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: var(--success);
      color: #fff;
      padding: 10px 18px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      opacity: 0;
      transform: translateY(40px);
      transition: all 0.25s;
      z-index: 100;
    }
    #toast.show { opacity: 1; transform: translateY(0); }
  </style>
</head>
<body>

  <header class="topbar">
    <div class="brand">
      <span>📺 Easyslides Multicast → OBS Overlay Studio</span>
    </div>
    <div style="display:flex; gap:10px;">
      <a href="index.php" class="btn">⬅ Back to Song & Lyric Editor</a>
      <a href="obs.php" target="_blank" class="btn btn-accent">↗ Open OBS Overlay Tab</a>
    </div>
  </header>

  <div class="container">
    <!-- Left Column: Multicast Connection & Visual Theme Settings -->
    <div style="display:flex; flex-direction:column; gap:18px;">

      <!-- Card 1: OBS Browser Source URL -->
      <div class="card">
        <div class="card-title">
          <span>1. OBS Studio Browser Source URL</span>
        </div>
        <p style="font-size:12.5px; color:var(--text-muted); line-height:1.45;">
          In OBS Studio, add a <strong>Browser Source</strong> (Width: <code>1920</code>, Height: <code>1080</code>) and paste this URL:
        </p>
        <div class="url-copy-box">
          <span id="obsUrlText" style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($obsUrl) ?></span>
          <button class="btn btn-sm btn-primary" onclick="copyObsUrl()">Copy URL</button>
        </div>
      </div>

      <!-- Card 2: Easyslides Multicast TCP Connection -->
      <div class="card">
        <div class="card-title">
          <span>2. Easyslides Multicast Connection</span>
          <span class="status-pill status-idle" id="connBadge">IDLE</span>
        </div>

        <div id="connDetailText" style="font-size:12px; color:var(--text-muted);">
          In Easyslides, go to the <strong>Multicast</strong> tab → click <strong>Start Multicast</strong> (default Port <code>9701</code>).
        </div>

        <div class="grid-2">
          <div class="field">
            <label>Easyslides Server IP</label>
            <input type="text" id="cfgHost" value="127.0.0.1" placeholder="127.0.0.1 or LAN IP">
          </div>
          <div class="field">
            <label>Multicast Port</label>
            <input type="number" id="cfgPort" value="9701">
          </div>
        </div>

        <div class="grid-2">
          <div class="field">
            <label>Service Mode</label>
            <select id="cfgServiceType">
              <option value="1">Live Screen (Recommended)</option>
              <option value="0">Stage Display</option>
            </select>
          </div>
          <div class="field">
            <label>Multicast Password (if set)</label>
            <input type="password" id="cfgPassword" placeholder="Leave blank if none">
          </div>
        </div>

        <div style="display:flex; gap:8px;">
          <button class="btn btn-success" style="flex:1;" onclick="saveAndStartDaemon()">🔌 Connect & Start Listener</button>
          <button class="btn btn-danger" onclick="stopDaemon()">⏹ Stop</button>
        </div>
      </div>

      <!-- Card 3: Overlay Theme & Typography -->
      <div class="card">
        <div class="card-title">
          <span>3. OBS Overlay Theme & Styling</span>
        </div>

        <div class="grid-2">
          <div class="field">
            <label>Overlay Theme</label>
            <select id="cfgTheme" onchange="saveVisualConfig()">
              <option value="glass">Frosted Glass Lower-Third</option>
              <option value="cinema">Cinema Outline (No Box)</option>
              <option value="gradient">Worship Full-Width Gradient</option>
              <option value="stage">Stage Monitor (Full Screen)</option>
            </select>
          </div>
          <div class="field">
            <label>Vertical Position</label>
            <select id="cfgPosition" onchange="saveVisualConfig()">
              <option value="bottom">Bottom (Lower-Third)</option>
              <option value="center">Center Screen</option>
              <option value="top">Top Banner</option>
            </select>
          </div>
        </div>

        <div class="grid-2">
          <div class="field">
            <label>Font Size (px)</label>
            <input type="number" id="cfgFontSize" value="42" min="20" max="90" onchange="saveVisualConfig()">
          </div>
          <div class="field">
            <label>Box Opacity (0.0 – 1.0)</label>
            <input type="number" id="cfgBgOpacity" value="0.78" step="0.05" min="0" max="1" onchange="saveVisualConfig()">
          </div>
        </div>

        <div class="grid-2">
          <div class="field">
            <label>Text Color</label>
            <input type="text" id="cfgTextColor" value="#ffffff" onchange="saveVisualConfig()">
          </div>
          <div class="field">
            <label>Accent Bar / Badge Color</label>
            <input type="text" id="cfgAccentColor" value="#f97316" onchange="saveVisualConfig()">
          </div>
        </div>

        <div style="display:flex; flex-wrap:wrap; gap:14px; padding-top:4px;">
          <label style="font-size:12.5px; display:flex; align-items:center; gap:6px; cursor:pointer;">
            <input type="checkbox" id="cfgShowSection" checked onchange="saveVisualConfig()"> Show Section Pill (Verse / Chorus)
          </label>
          <label style="font-size:12.5px; display:flex; align-items:center; gap:6px; cursor:pointer;">
            <input type="checkbox" id="cfgShowTitle" checked onchange="saveVisualConfig()"> Show Song Title
          </label>
        </div>
      </div>

    </div>

    <!-- Right Column: Live 16:9 OBS Preview + Direct Web Slide Controller -->
    <div style="display:flex; flex-direction:column; gap:18px;">

      <div class="card">
        <div class="card-title">
          <span>🖥 Live 1920×1080 OBS Overlay Preview</span>
          <div style="display:flex; gap:8px;">
            <button class="btn btn-sm" id="btnBlack" onclick="toggleBlack()">⬛ Black Screen</button>
            <button class="btn btn-sm" id="btnClear" onclick="toggleClear()">🧹 Clear Lyrics</button>
          </div>
        </div>

        <div class="preview-monitor-wrap" id="previewWrap">
          <iframe src="obs.php" class="preview-iframe" id="previewIframe"></iframe>
        </div>
      </div>

      <!-- Direct Web Slide Controller (Test or run live from MasterLyricDB.db!) -->
      <div class="card" style="flex:1;">
        <div class="card-title">
          <span>🎛 Live Slide Controller (Multicast + Direct DB Projection)</span>
          <span id="activeSongLabel" style="font-size:12px; color:var(--accent); font-weight:600;"></span>
        </div>

        <div style="display:flex; gap:8px;">
          <input type="text" id="quickSongSearch" placeholder="Search any song in MasterLyricDB.db to project directly to OBS..." style="flex:1;" oninput="searchQuickSong()">
          <select id="quickSongResults" style="flex:1.2;" onchange="loadQuickSong()">
            <option value="">— Select a song to test/project —</option>
          </select>
        </div>

        <div class="slide-btn-grid" id="slideButtonsGrid">
          <div style="color:var(--text-muted); font-size:13px; padding:12px;">
            When Easyslides Multicast is connected, slides appear here automatically—or search a song above to project slides directly from the browser!
          </div>
        </div>
      </div>

    </div>
  </div>

  <div id="toast"></div>

  <script>
    let currentState = {};
    let currentConfig = {};
    let loadedSlides = [];
    let loadedSongTitle = '';
    let loadedSongAuthor = '';
    let searchTimer = null;

    function scalePreviewIframe() {
      const wrap = document.getElementById('previewWrap');
      const iframe = document.getElementById('previewIframe');
      const scale = wrap.clientWidth / 1920;
      iframe.style.transform = `scale(${scale})`;
    }
    window.addEventListener('resize', scalePreviewIframe);
    setTimeout(scalePreviewIframe, 50);

    function showToast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      setTimeout(() => t.classList.remove('show'), 2600);
    }

    function copyObsUrl() {
      const url = document.getElementById('obsUrlText').textContent;
      navigator.clipboard.writeText(url);
      showToast('OBS Browser Source URL copied!');
    }

    async function obsApi(action, data = {}) {
      const res = await fetch('obs_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...data })
      });
      return await res.json();
    }

    async function loadInitialConfig() {
      const res = await obsApi('state');
      if (!res.ok) return;
      currentConfig = res.config;
      document.getElementById('cfgHost').value = currentConfig.host || '127.0.0.1';
      document.getElementById('cfgPort').value = currentConfig.port || 9701;
      document.getElementById('cfgServiceType').value = currentConfig.service_type ?? 1;
      document.getElementById('cfgPassword').value = currentConfig.password || '';
      document.getElementById('cfgTheme').value = currentConfig.theme || 'glass';
      document.getElementById('cfgPosition').value = currentConfig.position || 'bottom';
      document.getElementById('cfgFontSize').value = currentConfig.font_size || 42;
      document.getElementById('cfgBgOpacity').value = currentConfig.bg_opacity ?? 0.78;
      document.getElementById('cfgTextColor').value = currentConfig.text_color || '#ffffff';
      document.getElementById('cfgAccentColor').value = currentConfig.accent_color || '#f97316';
      document.getElementById('cfgShowSection').checked = !!currentConfig.show_section;
      document.getElementById('cfgShowTitle').checked = !!currentConfig.show_title;
      updateUiFromState(res.state);
    }

    async function saveVisualConfig() {
      const payload = {
        host: document.getElementById('cfgHost').value,
        port: Number(document.getElementById('cfgPort').value),
        service_type: Number(document.getElementById('cfgServiceType').value),
        password: document.getElementById('cfgPassword').value,
        theme: document.getElementById('cfgTheme').value,
        position: document.getElementById('cfgPosition').value,
        font_size: Number(document.getElementById('cfgFontSize').value),
        bg_opacity: Number(document.getElementById('cfgBgOpacity').value),
        text_color: document.getElementById('cfgTextColor').value,
        accent_color: document.getElementById('cfgAccentColor').value,
        show_section: document.getElementById('cfgShowSection').checked,
        show_title: document.getElementById('cfgShowTitle').checked,
      };
      const res = await obsApi('save_config', payload);
      if (res.ok) showToast('Overlay style updated!');
    }

    async function saveAndStartDaemon() {
      await saveVisualConfig();
      const res = await obsApi('start_daemon');
      if (res.ok) {
        showToast(res.message);
        updateUiFromState(res.state);
      }
    }

    async function stopDaemon() {
      const res = await obsApi('stop_daemon');
      if (res.ok) {
        showToast(res.message);
        updateUiFromState(res.state);
      }
    }

    async function toggleBlack() {
      const next = !currentState.is_black;
      const res = await obsApi('toggle_state', { is_black: next });
      if (res.ok) updateUiFromState(res.state);
    }

    async function toggleClear() {
      const next = !currentState.is_clear;
      const res = await obsApi('toggle_state', { is_clear: next });
      if (res.ok) updateUiFromState(res.state);
    }

    function updateUiFromState(st) {
      if (!st) return;
      currentState = st;

      const badge = document.getElementById('connBadge');
      const status = (st.connection_status || 'idle').toLowerCase();
      badge.textContent = status.toUpperCase();
      badge.className = 'status-pill ' + (
        status === 'connected' ? 'status-connected' :
        (status === 'connecting' || status === 'starting') ? 'status-connecting' : 'status-idle'
      );

      if (st.connection_message) {
        document.getElementById('connDetailText').textContent = st.connection_message;
      }

      document.getElementById('btnBlack').style.background = st.is_black ? '#ef4444' : '';
      document.getElementById('btnClear').style.background = st.is_clear ? '#f97316' : '';

      if (st.item && st.item.slides && st.item.slides.length > 0 && st.mode === 'multicast') {
        loadedSlides = st.item.slides;
        loadedSongTitle = st.item.title1 || '';
        loadedSongAuthor = st.item.author || '';
        renderSlideButtons(st.cur_slide_no);
      }
    }

    // Search songs in MasterLyricDB.db for direct projection/testing
    function searchQuickSong() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(async () => {
        const q = document.getElementById('quickSongSearch').value.trim();
        const res = await fetch('api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'list_songs', db: 'MasterLyricDB.db', q, per_page: 40 })
        }).then(r => r.json());

        if (!res.ok) return;
        const sel = document.getElementById('quickSongResults');
        sel.innerHTML = `<option value="">— Select song (${res.total} matches) —</option>` +
          res.items.map(it => `<option value="${it.rowid}">${escapeHtml(it.title1)}</option>`).join('');
      }, 200);
    }

    async function loadQuickSong() {
      const rowid = Number(document.getElementById('quickSongResults').value);
      if (!rowid) return;
      const res = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_song', db: 'MasterLyricDB.db', rowid })
      }).then(r => r.json());

      if (!res.ok || !res.song) return;
      const s = res.song;
      loadedSongTitle = s.title1;
      loadedSongAuthor = s.author;
      loadedSlides = parseContentsIntoSlides(s.contents || '');
      if (loadedSlides.length > 0) {
        pushSlideToObs(1);
      }
    }

    function parseContentsIntoSlides(raw) {
      const lines = raw.replace(/\r\n/g, '\n').split('\n');
      const slides = [];
      let curSection = '';
      let curLines = [];
      let slideNo = 1;

      const flush = () => {
        const text = curLines.join('\n').trim();
        if (text !== '') {
          const blocks = text.split(/\n\s*\n/);
          blocks.forEach(b => {
            if (b.trim() !== '') {
              slides.push({
                slide_no: slideNo++,
                section: curSection,
                region1: b.trim(),
                region2: ''
              });
            }
          });
        }
        curLines = [];
      };

      for (const line of lines) {
        const m = line.trim().match(/^\[([^\]]+)\]$/);
        if (m) {
          flush();
          const tag = m[1].toLowerCase();
          curSection = /^\d+$/.test(tag) ? `Verse ${tag}` : tag.charAt(0).toUpperCase() + tag.slice(1);
        } else {
          curLines.push(line);
        }
      }
      flush();
      return slides;
    }

    async function pushSlideToObs(slideNo) {
      const res = await obsApi('push_slide', {
        title1: loadedSongTitle,
        author: loadedSongAuthor,
        slides: loadedSlides,
        slide_no: slideNo
      });
      if (res.ok) {
        updateUiFromState(res.state);
        renderSlideButtons(slideNo);
      }
    }

    function renderSlideButtons(activeSlideNo) {
      document.getElementById('activeSongLabel').textContent = loadedSongTitle;
      const grid = document.getElementById('slideButtonsGrid');
      grid.innerHTML = loadedSlides.map(sl => `
        <button class="slide-btn ${Number(sl.slide_no) === Number(activeSlideNo) ? 'active' : ''}" onclick="pushSlideToObs(${sl.slide_no})">
          <div style="display:flex; justify-content:space-between; font-size:11px; color:var(--accent); font-weight:700; margin-bottom:4px;">
            <span>${escapeHtml(sl.section || 'Slide')}</span>
            <span>#${sl.slide_no}</span>
          </div>
          <div style="font-size:12.5px; line-height:1.35; white-space:pre-line;">${escapeHtml(sl.region1)}</div>
        </button>
      `).join('');
    }

    function escapeHtml(str) {
      return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    setInterval(async () => {
      const res = await obsApi('state');
      if (res.ok) updateUiFromState(res.state);
    }, 1000);

    loadInitialConfig();
    searchQuickSong();
  </script>
</body>
</html>
