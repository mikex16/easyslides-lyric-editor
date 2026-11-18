<?php
/**
 * OBS Studio Browser Source Overlay (http://localhost/lyrc/obs.php)
 * Add as a Browser Source in OBS Studio (1920x1080, default transparent background).
 * Optional query overrides: ?theme=glass|cinema|gradient|stage&fontsize=42&pos=bottom|center|top
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Easyslides OBS Lyric Overlay</title>
  <style>
    :root {
      --font-size: 42px;
      --text-color: #ffffff;
      --accent-color: #f97316;
      --bg-opacity: 0.78;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html, body {
      width: 100vw;
      height: 100vh;
      overflow: hidden;
      background: transparent !important;
      font-family: 'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
      -webkit-font-smoothing: antialiased;
    }

    .overlay-viewport {
      width: 100vw;
      height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      align-items: center;
      padding: 48px 80px;
      position: relative;
      transition: all 0.25s ease;
    }

    .overlay-viewport.pos-top {
      justify-content: flex-start;
    }

    .overlay-viewport.pos-center {
      justify-content: center;
    }

    .overlay-viewport.pos-bottom {
      justify-content: flex-end;
    }

    /* Main Lower-Third Container */
    .lyric-box {
      max-width: 1520px;
      width: auto;
      min-width: 640px;
      text-align: center;
      opacity: 1;
      transform: translateY(0);
      transition: opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1), transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .lyric-box.hidden {
      opacity: 0;
      transform: translateY(18px);
      pointer-events: none;
    }

    /* Theme 1: Frosted Glass Card (glass) */
    .theme-glass .lyric-box {
      background: rgba(15, 23, 42, var(--bg-opacity));
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border: 1px solid rgba(255, 255, 255, 0.14);
      border-bottom: 4px solid var(--accent-color);
      border-radius: 16px;
      padding: 22px 44px 26px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.55);
    }

    /* Theme 2: Cinema Outline / Transparent (cinema) */
    .theme-cinema .lyric-box {
      background: transparent;
      padding: 12px 24px;
    }

    .theme-cinema .lyric-region1,
    .theme-cinema .lyric-region2 {
      text-shadow:
        -2px -2px 0 #000,
         2px -2px 0 #000,
        -2px  2px 0 #000,
         2px  2px 0 #000,
         0px  4px 14px rgba(0, 0, 0, 0.95),
         0px  0px 24px rgba(0, 0, 0, 0.85);
    }

    /* Theme 3: Full-Width Worship Gradient Bar (gradient) */
    .theme-gradient.overlay-viewport {
      padding: 0;
    }

    .theme-gradient .lyric-box {
      width: 100vw;
      max-width: 100vw;
      background: linear-gradient(180deg, rgba(15, 23, 42, 0.0) 0%, rgba(15, 23, 42, var(--bg-opacity)) 28%, rgba(9, 14, 28, 0.95) 100%);
      border-top: 2px solid rgba(255, 255, 255, 0.12);
      padding: 38px 80px 42px;
    }

    /* Theme 4: Full-Screen Stage Monitor (stage) */
    .theme-stage.overlay-viewport {
      background: #090d16 !important;
      justify-content: space-between;
      align-items: stretch;
      padding: 36px 56px;
    }

    .theme-stage .lyric-box {
      max-width: 100%;
      width: 100%;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    /* Meta Header (Song Title + Section Pill) */
    .meta-bar {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 12px;
    }

    .meta-bar:empty {
      display: none;
    }

    .section-pill {
      background: var(--accent-color);
      color: #ffffff;
      font-size: 14px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 3px 11px;
      border-radius: 999px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
    }

    .song-title-pill {
      background: rgba(255, 255, 255, 0.12);
      color: rgba(255, 255, 255, 0.92);
      font-size: 14px;
      font-weight: 600;
      padding: 3px 12px;
      border-radius: 999px;
      backdrop-filter: blur(6px);
    }

    .theme-cinema .song-title-pill {
      background: rgba(0, 0, 0, 0.65);
    }

    /* Lyric Lines */
    .lyric-region1 {
      font-size: var(--font-size);
      font-weight: 700;
      color: var(--text-color);
      line-height: 1.32;
      letter-spacing: 0.01em;
      white-space: pre-line;
      text-shadow: 0 2px 10px rgba(0, 0, 0, 0.65);
      transition: opacity 0.16s ease, transform 0.16s ease;
    }

    .lyric-region2 {
      margin-top: 10px;
      font-size: calc(var(--font-size) * 0.78);
      font-weight: 600;
      color: #fde047;
      line-height: 1.32;
      white-space: pre-line;
      text-shadow: 0 2px 8px rgba(0, 0, 0, 0.65);
    }

    .lyric-fade-step {
      opacity: 0.15;
      transform: translateY(4px);
    }

    /* Stage Monitor Extra Elements */
    .stage-top-bar {
      display: none;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #1e293b;
      padding-bottom: 16px;
    }

    .theme-stage .stage-top-bar {
      display: flex;
    }

    .stage-clock {
      font-size: 36px;
      font-weight: 800;
      color: #38bdf8;
      font-variant-numeric: tabular-nums;
    }

    .stage-next-box {
      display: none;
      border-top: 2px solid #1e293b;
      padding-top: 20px;
      text-align: left;
    }

    .theme-stage .stage-next-box {
      display: block;
    }

    .stage-next-label {
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
      color: #94a3b8;
      letter-spacing: 0.08em;
      margin-bottom: 6px;
    }

    .stage-next-lyrics {
      font-size: calc(var(--font-size) * 0.62);
      color: #94a3b8;
      white-space: pre-line;
      line-height: 1.35;
    }

    /* Alert Banner */
    .alert-banner {
      position: fixed;
      top: 36px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(220, 38, 38, 0.92);
      color: #fff;
      font-size: 24px;
      font-weight: 700;
      padding: 10px 28px;
      border-radius: 999px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
      display: none;
      z-index: 50;
    }

    .alert-banner.visible {
      display: block;
    }
  </style>
</head>
<body>

  <div class="alert-banner" id="alertBanner"></div>

  <div class="overlay-viewport theme-glass pos-bottom" id="viewport">
    <!-- Stage Monitor Header (only visible in theme=stage) -->
    <div class="stage-top-bar">
      <div>
        <div id="stageSongTitle" style="font-size:24px; font-weight:700; color:#f8fafc;"></div>
        <div id="stageSlideCounter" style="font-size:15px; color:#94a3b8; margin-top:2px;"></div>
      </div>
      <div class="stage-clock" id="stageClock">00:00:00</div>
    </div>

    <!-- Main Lower-Third Lyric Box -->
    <div class="lyric-box hidden" id="lyricBox">
      <div class="meta-bar" id="metaBar"></div>
      <div class="lyric-region1" id="lyricRegion1"></div>
      <div class="lyric-region2" id="lyricRegion2" style="display:none;"></div>
    </div>

    <!-- Stage Monitor Next Slide Footer (only visible in theme=stage) -->
    <div class="stage-next-box">
      <div class="stage-next-label">Next Slide</div>
      <div class="stage-next-lyrics" id="stageNextLyrics">—</div>
    </div>
  </div>

  <script>
    const urlParams = new URLSearchParams(window.location.search);
    let lastUpdatedMs = -1;
    let lastSlideSignature = '';

    function updateClock() {
      const now = new Date();
      document.getElementById('stageClock').textContent = now.toLocaleTimeString('en-GB', { hour12: false });
    }
    setInterval(updateClock, 1000);
    updateClock();

    function applyVisualConfig(cfg) {
      const theme = urlParams.get('theme') || cfg.theme || 'glass';
      const pos = urlParams.get('pos') || cfg.position || 'bottom';
      const fontSize = urlParams.get('fontsize') || cfg.font_size || 42;
      const textColor = cfg.text_color || '#ffffff';
      const accentColor = cfg.accent_color || '#f97316';
      const bgOpacity = cfg.bg_opacity ?? 0.78;

      const vp = document.getElementById('viewport');
      vp.className = `overlay-viewport theme-${theme} pos-${pos}`;

      document.documentElement.style.setProperty('--font-size', `${fontSize}px`);
      document.documentElement.style.setProperty('--text-color', textColor);
      document.documentElement.style.setProperty('--accent-color', accentColor);
      document.documentElement.style.setProperty('--bg-opacity', bgOpacity);
    }

    function renderOverlay(cfg, st) {
      applyVisualConfig(cfg);

      const alertEl = document.getElementById('alertBanner');
      if (st.alert_text && st.alert_text.trim() !== '') {
        alertEl.textContent = st.alert_text;
        alertEl.classList.add('visible');
      } else {
        alertEl.classList.remove('visible');
      }

      const box = document.getElementById('lyricBox');
      const reg1El = document.getElementById('lyricRegion1');
      const reg2El = document.getElementById('lyricRegion2');
      const metaBar = document.getElementById('metaBar');

      const slide = st.active_slide || {};
      const r1 = (slide.region1 || '').trim();
      const r2 = (slide.region2 || '').trim();
      const section = (slide.section || '').trim();
      const title1 = ((st.item && st.item.title1) || '').trim();

      // Hide overlay if show is not running, or Black/Clear screen is active, or slide has no text
      const shouldHide = !st.show_running || st.is_black || st.is_clear || (r1 === '' && r2 === '');

      if (shouldHide) {
        box.classList.add('hidden');
        document.getElementById('stageNextLyrics').textContent = '—';
        return;
      }

      box.classList.remove('hidden');

      // Meta pills (Section & Title)
      const pills = [];
      if (cfg.show_section && section !== '') {
        pills.push(`<span class="section-pill">${escapeHtml(section)}</span>`);
      }
      if (cfg.show_title && title1 !== '') {
        pills.push(`<span class="song-title-pill">${escapeHtml(title1)}</span>`);
      }
      metaBar.innerHTML = pills.join('');

      // Stage monitor top/bottom info
      document.getElementById('stageSongTitle').textContent = title1 || 'Live Output';
      document.getElementById('stageSlideCounter').textContent =
        st.total_slides > 0 ? `Slide ${st.cur_slide_no} of ${st.total_slides}${section ? ' • ' + section : ''}` : '';
      document.getElementById('stageNextLyrics').textContent =
        (st.next_slide && st.next_slide.region1) ? st.next_slide.region1 : 'End of Item';

      // Smooth cross-fade when lyric text changes
      const sig = `${r1}||${r2}`;
      if (sig !== lastSlideSignature) {
        lastSlideSignature = sig;
        reg1El.classList.add('lyric-fade-step');
        setTimeout(() => {
          reg1El.textContent = r1;
          if (cfg.show_region2 && r2 !== '') {
            reg2El.textContent = r2;
            reg2El.style.display = 'block';
          } else {
            reg2El.style.display = 'none';
          }
          reg1El.classList.remove('lyric-fade-step');
        }, 90);
      }
    }

    function escapeHtml(str) {
      return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    async function pollState() {
      try {
        const res = await fetch('obs_api.php?action=state&_=' + Date.now(), { cache: 'no-store' });
        const data = await res.json();
        if (data && data.ok) {
          if (data.state.updated_at_ms !== lastUpdatedMs) {
            lastUpdatedMs = data.state.updated_at_ms;
            renderOverlay(data.config, data.state);
          }
        }
      } catch (e) {
        // Ignore transient network errors
      }
    }

    setInterval(pollState, 180);
    pollState();
  </script>
</body>
</html>
