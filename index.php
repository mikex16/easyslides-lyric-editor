<?php
require_once __DIR__ . '/config.php';
$dbs = getAvailableDatabases();
$defaultDb = DEFAULT_DB_FILE;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Easyslides Song & Lyric Studio</title>
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
      --accent-hover: #ea580c;
      --primary: #3b82f6;
      --primary-hover: #2563eb;
      --success: #10b981;
      --danger: #ef4444;
      --slide-bg: #000088;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Inter, sans-serif;
      background: var(--bg-body);
      color: var(--text-main);
      height: 100vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    /* Top Header */
    .topbar {
      height: 60px;
      background: var(--bg-panel);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 18px;
      gap: 12px;
      flex-shrink: 0;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 700;
      font-size: 16px;
      letter-spacing: -0.01em;
    }

    .brand-badge {
      background: var(--accent);
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 999px;
      text-transform: uppercase;
    }

    .db-selector-group {
      display: flex;
      align-items: center;
      gap: 8px;
      background: var(--bg-input);
      padding: 5px 10px;
      border-radius: 8px;
      border: 1px solid var(--border);
    }

    .db-selector-group label {
      font-size: 12px;
      color: var(--text-muted);
      font-weight: 600;
      white-space: nowrap;
    }

    select, input[type="text"], input[type="number"], input[type="search"], textarea {
      background: var(--bg-input);
      color: var(--text-main);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 7px 10px;
      font-size: 13px;
      outline: none;
      transition: border-color 0.15s;
    }

    select:focus, input:focus, textarea:focus {
      border-color: var(--primary);
    }

    .db-selector-group select {
      border: none;
      background: transparent;
      font-weight: 600;
      color: #fff;
      padding: 2px 6px;
      cursor: pointer;
    }

    .db-selector-group select option {
      background: var(--bg-panel);
      color: #fff;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 13px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      border: 1px solid transparent;
      cursor: pointer;
      transition: all 0.15s;
      color: var(--text-main);
      background: var(--bg-panel-alt);
      border-color: var(--border);
      white-space: nowrap;
      text-decoration: none;
    }

    .btn:hover {
      background: #334155;
    }

    .btn-primary {
      background: var(--primary);
      border-color: var(--primary);
      color: #fff;
    }
    .btn-primary:hover {
      background: var(--primary-hover);
    }

    .btn-accent {
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
    }
    .btn-accent:hover {
      background: var(--accent-hover);
    }

    .btn-success {
      background: var(--success);
      border-color: var(--success);
      color: #fff;
    }
    .btn-success:hover {
      background: #059669;
    }

    .btn-danger {
      background: rgba(239, 68, 68, 0.15);
      border-color: rgba(239, 68, 68, 0.4);
      color: #fca5a5;
    }
    .btn-danger:hover {
      background: var(--danger);
      color: #fff;
    }

    .btn-sm {
      padding: 4px 9px;
      font-size: 12px;
    }

    /* Main Workspace Layout */
    .workspace {
      display: flex;
      flex: 1;
      overflow: hidden;
    }

    /* Left Sidebar: Song List */
    .sidebar {
      width: 390px;
      background: var(--bg-panel);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
    }

    .sidebar-controls {
      padding: 12px;
      border-bottom: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .search-box {
      position: relative;
      display: flex;
    }

    .search-box input {
      width: 100%;
      padding-left: 32px;
    }

    .search-icon {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      font-size: 13px;
      pointer-events: none;
    }

    .filter-row {
      display: flex;
      gap: 6px;
    }

    .filter-row select {
      flex: 1;
      min-width: 0;
      font-size: 12px;
      padding: 5px 8px;
    }

    .alpha-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 3px;
      padding: 8px 12px;
      border-bottom: 1px solid var(--border);
      background: rgba(15, 23, 42, 0.4);
    }

    .alpha-btn {
      font-size: 11px;
      font-weight: 600;
      padding: 3px 6px;
      border-radius: 4px;
      border: 1px solid transparent;
      background: transparent;
      color: var(--text-muted);
      cursor: pointer;
    }

    .alpha-btn:hover {
      color: #fff;
      background: var(--bg-panel-alt);
    }

    .alpha-btn.active {
      background: var(--accent);
      color: #fff;
    }

    /* Bulk selection bar */
    .bulk-bar {
      background: rgba(59, 130, 246, 0.15);
      border-bottom: 1px solid rgba(59, 130, 246, 0.35);
      padding: 7px 12px;
      display: none;
      align-items: center;
      justify-content: space-between;
      font-size: 12px;
      gap: 8px;
    }

    .bulk-bar.visible {
      display: flex;
    }

    .song-list {
      flex: 1;
      overflow-y: auto;
      divide-y: 1px solid var(--border);
    }

    .song-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 10px 12px;
      border-bottom: 1px solid rgba(51, 65, 85, 0.5);
      cursor: pointer;
      transition: background 0.12s;
    }

    .song-item:hover {
      background: rgba(255, 255, 255, 0.04);
    }

    .song-item.active {
      background: rgba(59, 130, 246, 0.18);
      border-left: 3px solid var(--primary);
    }

    .song-item input[type="checkbox"] {
      margin-top: 3px;
      cursor: pointer;
    }

    .song-item-body {
      flex: 1;
      min-width: 0;
    }

    .song-item-title {
      font-size: 13.5px;
      font-weight: 600;
      color: var(--text-main);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-bottom: 3px;
    }

    .song-item-meta {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 11.5px;
      color: var(--text-muted);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .key-pill {
      background: rgba(249, 115, 22, 0.18);
      color: #fdba74;
      border: 1px solid rgba(249, 115, 22, 0.35);
      font-size: 10.5px;
      font-weight: 700;
      padding: 1px 5px;
      border-radius: 4px;
      flex-shrink: 0;
    }

    .sidebar-footer {
      padding: 9px 12px;
      border-top: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 12px;
      color: var(--text-muted);
      background: var(--bg-panel);
    }

    /* Main Editor Area */
    .editor-pane {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      background: var(--bg-body);
    }

    .editor-header {
      padding: 14px 20px;
      background: var(--bg-panel);
      border-bottom: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .editor-top-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 2fr 1.3fr 90px 140px auto;
      gap: 10px;
      align-items: end;
    }

    .form-grid-more {
      display: none;
      grid-template-columns: repeat(6, 1fr);
      gap: 10px;
      padding-top: 8px;
      border-top: 1px dashed var(--border);
    }

    .form-grid-more.open {
      display: grid;
    }

    .field {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .field label {
      font-size: 11px;
      font-weight: 600;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    .field input {
      width: 100%;
    }

    /* Toolbar for Lyric Tags & Formatting */
    .lyric-toolbar {
      padding: 8px 20px;
      background: var(--bg-panel-alt);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 8px;
    }

    .tag-buttons {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 5px;
    }

    .tag-btn {
      background: var(--bg-input);
      color: #cbd5e1;
      border: 1px solid var(--border);
      border-radius: 5px;
      padding: 4px 8px;
      font-size: 12px;
      font-family: 'Consolas', 'Monaco', monospace;
      cursor: pointer;
      transition: all 0.12s;
    }

    .tag-btn:hover {
      background: var(--primary);
      color: #fff;
      border-color: var(--primary);
    }

    /* Split Editor + Slide Preview */
    .editor-split {
      flex: 1;
      display: grid;
      grid-template-columns: 1.15fr 0.85fr;
      overflow: hidden;
    }

    .textarea-wrap {
      display: flex;
      flex-direction: column;
      border-right: 1px solid var(--border);
      height: 100%;
      overflow: hidden;
    }

    #songContents {
      flex: 1;
      width: 100%;
      border: none;
      border-radius: 0;
      padding: 16px 20px;
      font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
      font-size: 14px;
      line-height: 1.6;
      resize: none;
      background: var(--bg-body);
      color: #f1f5f9;
    }

    .preview-wrap {
      display: flex;
      flex-direction: column;
      height: 100%;
      overflow: hidden;
      background: #0b1120;
    }

    .preview-header {
      padding: 10px 16px;
      border-bottom: 1px solid var(--border);
      font-size: 12px;
      font-weight: 600;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: rgba(30, 41, 59, 0.5);
    }

    .slides-container {
      flex: 1;
      overflow-y: auto;
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .slide-card {
      background: var(--slide-bg);
      border: 2px solid #1e3a8a;
      border-radius: 8px;
      padding: 18px 16px;
      min-height: 125px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      position: relative;
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.45);
    }

    .slide-badge {
      position: absolute;
      top: 7px;
      left: 9px;
      background: rgba(0, 0, 0, 0.45);
      color: #93c5fd;
      font-size: 10px;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 4px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .slide-num {
      position: absolute;
      top: 7px;
      right: 9px;
      color: rgba(255, 255, 255, 0.45);
      font-size: 10px;
      font-weight: 600;
    }

    .slide-heading {
      font-family: 'Microsoft Sans Serif', sans-serif;
      font-size: 12px;
      color: #bfdbfe;
      text-decoration: underline;
      margin-bottom: 6px;
    }

    .slide-lyrics {
      font-family: 'Microsoft Sans Serif', sans-serif;
      font-size: 16px;
      font-weight: 500;
      color: #ffffff;
      line-height: 1.45;
      text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.85);
      white-space: pre-wrap;
    }

    /* Modals */
    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.7);
      backdrop-filter: blur(3px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 100;
      padding: 20px;
    }

    .modal-backdrop.open {
      display: flex;
    }

    .modal {
      background: var(--bg-panel);
      border: 1px solid var(--border);
      border-radius: 12px;
      width: 100%;
      max-width: 600px;
      overflow: hidden;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
    }

    .modal-header {
      padding: 15px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-weight: 700;
      font-size: 15px;
    }

    .modal-body {
      padding: 18px 20px;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .modal-footer {
      padding: 12px 20px;
      border-top: 1px solid var(--border);
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      background: rgba(15, 23, 42, 0.4);
    }

    /* Toast Notification */
    #toast {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: #10b981;
      color: #fff;
      padding: 10px 18px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      box-shadow: 0 10px 25px rgba(0,0,0,0.4);
      transform: translateY(80px);
      opacity: 0;
      transition: all 0.25s;
      z-index: 200;
    }

    #toast.show {
      transform: translateY(0);
      opacity: 1;
    }

    .progress-box {
      background: var(--bg-input);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 12px;
      font-size: 12.5px;
    }

    .progress-bar-outer {
      height: 8px;
      background: #1e293b;
      border-radius: 999px;
      overflow: hidden;
      margin-top: 8px;
    }

    .progress-bar-inner {
      height: 100%;
      background: var(--accent);
      width: 0%;
      transition: width 0.3s;
    }
  </style>
</head>
<body>

  <!-- Top Header -->
  <header class="topbar">
    <div class="brand">
      <span>🎵 Easyslides Song & Lyric Studio</span>
    </div>

    <div style="display:flex; align-items:center; gap:8px;">
      <div class="db-selector-group">
        <label for="dbSelect">Active Database:</label>
        <select id="dbSelect" onchange="onDatabaseChange()"></select>
      </div>
      <button class="btn btn-sm" onclick="openNewDbModal()" title="Create a new Easyslides .db file">+ New DB</button>
      <button class="btn btn-sm" onclick="downloadCurrentDb()" title="Download current SQLite .db file">⬇ Download .db</button>
    </div>

    <div style="display:flex; align-items:center; gap:8px;">
      <a href="obs_control.php" class="btn" title="Open OBS Browser Source & Easyslides Multicast Studio">📺 OBS Overlay Studio</a>
      <button class="btn btn-accent" onclick="openScraperModal()">🌐 Scrape Songs</button>
      <button class="btn btn-primary" onclick="newSongForm()">+ New Song</button>
    </div>
  </header>

  <!-- Main Workspace -->
  <div class="workspace">
    <!-- Left Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-controls">
        <div class="search-box">
          <span class="search-icon">🔍</span>
          <input type="search" id="searchInput" placeholder="Search title, artist, lyrics, or key..." oninput="debounceSearch()">
        </div>
        <div class="filter-row">
          <select id="artistFilter" onchange="loadSongs(1)">
            <option value="">All Artists / Authors</option>
          </select>
          <select id="sortSelect" onchange="loadSongs(1)">
            <option value="title_asc">Title (A → Z)</option>
            <option value="title_desc">Title (Z → A)</option>
            <option value="author_asc">Artist (A → Z)</option>
            <option value="recent">Recently Modified</option>
          </select>
        </div>
      </div>

      <!-- Alphabet filter -->
      <div class="alpha-bar" id="alphaBar"></div>

      <!-- Bulk Actions Bar -->
      <div class="bulk-bar" id="bulkBar">
        <span id="bulkCountLabel">0 selected</span>
        <div style="display:flex; gap:5px;">
          <button class="btn btn-sm btn-primary" onclick="openCopyModal(true)">Copy to DB...</button>
          <button class="btn btn-sm btn-danger" onclick="deleteSelectedSongs()">Delete</button>
          <button class="btn btn-sm" onclick="clearSelection()">Clear</button>
        </div>
      </div>

      <!-- Song List -->
      <div class="song-list" id="songList"></div>

      <!-- Pagination Footer -->
      <div class="sidebar-footer">
        <span id="songCountInfo">0 songs</span>
        <div style="display:flex; align-items:center; gap:6px;">
          <button class="btn btn-sm" id="prevPageBtn" onclick="changePage(-1)">◀ Prev</button>
          <span id="pageInfo">1 / 1</span>
          <button class="btn btn-sm" id="nextPageBtn" onclick="changePage(1)">Next ▶</button>
        </div>
      </div>
    </aside>

    <!-- Main Song Editor -->
    <main class="editor-pane">
      <div class="editor-header">
        <div class="editor-top-row">
          <div style="display:flex; align-items:center; gap:10px;">
            <h2 id="editorHeading" style="font-size:16px; font-weight:700;">Edit Song</h2>
            <span id="lastModifiedBadge" style="font-size:12px; color:var(--text-muted);"></span>
            <a id="sourceUrlLink" href="#" target="_blank" style="font-size:12px; color:#60a5fa; display:none; text-decoration:none;">🔗 View Source</a>
          </div>
          <div style="display:flex; align-items:center; gap:8px;">
            <button class="btn btn-sm" onclick="toggleMoreFields()" id="moreFieldsBtn">⚙️ More Easyslides Fields</button>
            <button class="btn btn-sm" onclick="openCopyModal(false)" id="copySingleBtn" title="Copy this song to Hari Malaysia 2025.db or another DB">📋 Copy to Another DB...</button>
            <button class="btn btn-sm btn-danger" onclick="deleteCurrentSong()" id="deleteSingleBtn">🗑 Delete</button>
            <button class="btn btn-success" onclick="saveCurrentSong()">💾 Save Song (Ctrl+S)</button>
          </div>
        </div>

        <input type="hidden" id="songRowId" value="">

        <div class="form-grid">
          <div class="field">
            <label>Song Title (title1) *</label>
            <input type="text" id="songTitle1" placeholder="e.g. 1. Tuhan Penerobosan">
          </div>
          <div class="field">
            <label>Artist / Author</label>
            <input type="text" id="songAuthor" placeholder="e.g. JPCC Worship">
          </div>
          <div class="field">
            <label>Key (songkey)</label>
            <input type="text" id="songKey" placeholder="e.g. Bb, C, G">
          </div>
          <div class="field">
            <label>Sequence</label>
            <input type="text" id="songSequence" placeholder="e.g. 1,c,2,c,b,c">
          </div>
          <div class="field">
            <label>Folder #</label>
            <input type="number" id="songFolderNo" value="1" style="width:70px;">
          </div>
        </div>

        <div class="form-grid-more" id="moreFieldsGrid">
          <div class="field">
            <label>Title 2 (title2)</label>
            <input type="text" id="songTitle2" placeholder="Alt / Original Title">
          </div>
          <div class="field">
            <label>Song Number</label>
            <input type="number" id="songNumber" value="0">
          </div>
          <div class="field">
            <label>Capo</label>
            <input type="number" id="songCapo" value="-1">
          </div>
          <div class="field">
            <label>Timing</label>
            <input type="text" id="songTiming" placeholder="e.g. 4/4">
          </div>
          <div class="field">
            <label>Copyright</label>
            <input type="text" id="songCopyright" placeholder="Copyright info">
          </div>
          <div class="field">
            <label>Source URL (userreference)</label>
            <input type="text" id="songUserRef" placeholder="https://www.jrchord.com/...">
          </div>
        </div>
      </div>

      <!-- Lyric Formatting & Section Tag Toolbar -->
      <div class="lyric-toolbar">
        <div class="tag-buttons">
          <span style="font-size:11px; color:var(--text-muted); font-weight:600; margin-right:4px;">Insert Tag:</span>
          <button class="tag-btn" onclick="insertTag('[1]')">[1]</button>
          <button class="tag-btn" onclick="insertTag('[2]')">[2]</button>
          <button class="tag-btn" onclick="insertTag('[3]')">[3]</button>
          <button class="tag-btn" onclick="insertTag('[4]')">[4]</button>
          <button class="tag-btn" onclick="insertTag('[chorus]')">[chorus]</button>
          <button class="tag-btn" onclick="insertTag('[chorus 2]')">[chorus 2]</button>
          <button class="tag-btn" onclick="insertTag('[prechorus]')">[prechorus]</button>
          <button class="tag-btn" onclick="insertTag('[bridge]')">[bridge]</button>
          <button class="tag-btn" onclick="insertTag('[ending]')">[ending]</button>
          <button class="tag-btn" onclick="insertTag('[tag]')">[tag]</button>
        </div>

        <div style="display:flex; align-items:center; gap:6px;">
          <button class="btn btn-sm btn-accent" onclick="stripChordsInEditor()" title="Remove chords, convert Bait/Reff/Bridge to [1]/[chorus]/[bridge], and format 2-line slides">✨ Strip Chords & Format (Tanpa Chord)</button>
          <button class="btn btn-sm" onclick="transformLyricsCase(true)" title="Convert lyrics to UPPERCASE (keep section tags lowercase)">UPPERCASE</button>
          <button class="btn btn-sm" onclick="transformLyricsCase(false)" title="Convert lyrics to Title Case">Title Case</button>
        </div>
      </div>

      <!-- Split Code Editor + Live Easyslides Slide Preview -->
      <div class="editor-split">
        <div class="textarea-wrap">
          <textarea id="songContents" placeholder="Paste lyrics or JRChord chords+lyrics here...&#10;[1]&#10;LINE 1&#10;LINE 2&#10;&#10;[chorus]&#10;CHORUS LINE 1&#10;CHORUS LINE 2" oninput="renderSlidePreview()"></textarea>
        </div>

        <div class="preview-wrap">
          <div class="preview-header">
            <span>🖥 Live Easyslides Screen Preview</span>
            <span id="slideCountBadge">0 slides</span>
          </div>
          <div class="slides-container" id="slidesPreview"></div>
        </div>
      </div>
    </main>
  </div>

  <!-- Modal: Scrape from JRChord.com, LaguRohani.id & Alkitab.app -->
  <div class="modal-backdrop" id="scraperModal">
    <div class="modal">
      <div class="modal-header">
        <span>🌐 Scrape Song & Lyric (JRChord.com, LaguRohani.id & Alkitab.app)</span>
        <button class="btn btn-sm" onclick="closeModal('scraperModal')">✕</button>
      </div>
      <div class="modal-body">
        <div class="field">
          <label>Scrape Single Song URL (jrchord.com, lagurohani.id, or alkitab.app/{BOOK}/{NO})</label>
          <div style="display:flex; gap:8px;">
            <input type="text" id="scrapeUrlInput" placeholder="https://www.jrchord.com/... or https://alkitab.app/KJ/1" style="flex:1;">
            <button class="btn btn-accent" onclick="scrapeSingleUrl(true)">Scrape & Save</button>
            <button class="btn" onclick="scrapeSingleUrl(false)">Load into Editor</button>
          </div>
        </div>

        <div style="display:flex; align-items:center; gap:8px;">
          <input type="checkbox" id="scrapeUppercaseCheck" checked>
          <label for="scrapeUppercaseCheck" style="font-size:13px;">Convert scraped lyrics to UPPERCASE (matching Hari Malaysia 2025.db)</label>
        </div>

        <hr style="border:none; border-top:1px solid var(--border);">

        <div class="field">
          <label>Full Website / Songbooks Sync</label>
          <p style="font-size:12.5px; color:var(--text-muted); line-height:1.45;">
            Sync all songs from <code>jrchord.com</code> (~1,196 songs), <code>lagurohani.id</code> (~3,143 songs → <code>MasterLyricDB.db</code>), or <code>alkitab.app/songs</code> (23 Indonesian Christian Songbooks → separate <code>KJ.db</code>, <code>PKJ.db</code>, <code>NKB.db</code>, <code>NKI.db</code>, etc.).
          </p>
        </div>

        <div class="progress-box" id="scrapeStatusBox">
          <div style="display:flex; justify-content:space-between; font-weight:600;">
            <span id="scrapeStateText">Checking status...</span>
            <span id="scrapePercentText"></span>
          </div>
          <div class="progress-bar-outer">
            <div class="progress-bar-inner" id="scrapeProgressBar"></div>
          </div>
          <div id="scrapeDetailText" style="margin-top:6px; color:var(--text-muted); font-size:12px;"></div>
        </div>
      </div>
      <div class="modal-footer" style="flex-wrap:wrap;">
        <button class="btn" onclick="closeModal('scraperModal')">Close</button>
        <button class="btn btn-primary" onclick="triggerFullScrape('jrchord')">🚀 Sync JRChord.com</button>
        <button class="btn btn-accent" onclick="triggerFullScrape('lagurohani')">🚀 Sync LaguRohani.id</button>
        <button class="btn btn-success" onclick="triggerFullScrape('alkitab')">📚 Sync Alkitab.app (23 Books)</button>
      </div>
    </div>
  </div>

  <!-- Modal: Copy Song(s) to Another Database -->
  <div class="modal-backdrop" id="copyModal">
    <div class="modal" style="max-width:460px;">
      <div class="modal-header">
        <span>📋 Copy Song(s) to Another Easyslides DB</span>
        <button class="btn btn-sm" onclick="closeModal('copyModal')">✕</button>
      </div>
      <div class="modal-body">
        <p id="copyModalDesc" style="font-size:13px; color:var(--text-muted);"></p>
        <div class="field">
          <label>Target Database</label>
          <select id="copyTargetDbSelect"></select>
        </div>
        <div class="field" id="copyPrefixField">
          <label>Optional Number Prefix (e.g. "6" → "6. SONG TITLE" like Hari Malaysia 2025.db)</label>
          <input type="text" id="copyPrefixInput" placeholder="Leave blank to keep original title">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn" onclick="closeModal('copyModal')">Cancel</button>
        <button class="btn btn-success" onclick="confirmCopyToDb()">Copy Now</button>
      </div>
    </div>
  </div>

  <!-- Modal: Create New Database -->
  <div class="modal-backdrop" id="newDbModal">
    <div class="modal" style="max-width:440px;">
      <div class="modal-header">
        <span>➕ Create New Easyslides Database</span>
        <button class="btn btn-sm" onclick="closeModal('newDbModal')">✕</button>
      </div>
      <div class="modal-body">
        <p style="font-size:12.5px; color:var(--text-muted);">
          Creates a new SQLite <code>.db</code> file in <code><?= htmlspecialchars(EASYSLIDES_DB_DIR) ?></code> and registers it in Easyslides <code>DatabasesAdmin.db</code>.
        </p>
        <div class="field">
          <label>Database Name</label>
          <input type="text" id="newDbNameInput" placeholder="e.g. Ibadah Minggu 2026.db">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn" onclick="closeModal('newDbModal')">Cancel</button>
        <button class="btn btn-primary" onclick="confirmCreateDb()">Create Database</button>
      </div>
    </div>
  </div>

  <div id="toast"></div>

  <script>
    let currentDb = <?= json_encode($defaultDb) ?>;
    let currentPage = 1;
    let totalPages = 1;
    let currentLetter = 'ALL';
    let selectedRowIds = new Set();
    let copyBulkMode = false;
    let searchTimer = null;
    let scrapePollTimer = null;
    let availableDbs = [];

    // Initialize alphabet filter bar
    function initAlphaBar() {
      const bar = document.getElementById('alphaBar');
      const letters = ['ALL', '#', ...'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')];
      bar.innerHTML = letters.map(l =>
        `<button class="alpha-btn ${l === currentLetter ? 'active' : ''}" onclick="setLetter('${l}')">${l}</button>`
      ).join('');
    }

    function setLetter(letter) {
      currentLetter = letter;
      initAlphaBar();
      loadSongs(1);
    }

    function showToast(msg, isError = false) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.style.background = isError ? '#ef4444' : '#10b981';
      t.classList.add('show');
      setTimeout(() => t.classList.remove('show'), 3200);
    }

    async function apiCall(action, data = {}) {
      const payload = { action, db: currentDb, ...data };
      const res = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      return await res.json();
    }

    async function loadDatabases(selectDb = null) {
      const res = await apiCall('list_dbs');
      if (!res.ok) return;
      availableDbs = res.databases;
      if (selectDb) currentDb = selectDb;

      const sel = document.getElementById('dbSelect');
      const copySel = document.getElementById('copyTargetDbSelect');

      sel.innerHTML = availableDbs.map(d =>
        `<option value="${escapeHtml(d.filename)}" ${d.filename.toLowerCase() === currentDb.toLowerCase() ? 'selected' : ''}>
          ${escapeHtml(d.filename)} (${d.song_count} songs)
        </option>`
      ).join('');

      if (sel.value) {
        currentDb = sel.value;
      }

      copySel.innerHTML = availableDbs.map(d =>
        `<option value="${escapeHtml(d.filename)}" ${d.filename === 'Hari Malaysia 2025.db' ? 'selected' : ''}>
          ${escapeHtml(d.filename)} (${d.song_count} songs)
        </option>`
      ).join('');
    }

    function onDatabaseChange() {
      currentDb = document.getElementById('dbSelect').value;
      selectedRowIds.clear();
      updateBulkBar();
      document.getElementById('artistFilter').value = '';
      loadSongs(1, true);
    }

    function debounceSearch() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => loadSongs(1), 220);
    }

    async function loadSongs(page = 1, autoSelectFirst = false) {
      currentPage = page;
      const q = document.getElementById('searchInput').value;
      const artist = document.getElementById('artistFilter').value;
      const sort = document.getElementById('sortSelect').value;

      const res = await apiCall('list_songs', {
        q,
        letter: currentLetter,
        artist,
        sort,
        page: currentPage,
        per_page: 100
      });

      if (!res.ok) {
        showToast(res.error || 'Failed to load songs', true);
        return;
      }

      totalPages = res.total_pages;
      document.getElementById('songCountInfo').textContent = `${res.total} song${res.total === 1 ? '' : 's'}`;
      document.getElementById('pageInfo').textContent = `${res.page} / ${res.total_pages}`;
      document.getElementById('prevPageBtn').disabled = res.page <= 1;
      document.getElementById('nextPageBtn').disabled = res.page >= res.total_pages;

      // Populate artist filter while keeping current value
      const artistSel = document.getElementById('artistFilter');
      const curArtist = artistSel.value;
      artistSel.innerHTML = `<option value="">All Artists / Authors</option>` +
        res.artists.map(a =>
          `<option value="${escapeHtml(a.author)}" ${a.author === curArtist ? 'selected' : ''}>
            ${escapeHtml(a.author)} (${a.cnt})
          </option>`
        ).join('');

      const listEl = document.getElementById('songList');
      const activeRowId = Number(document.getElementById('songRowId').value || 0);

      if (res.items.length === 0) {
        listEl.innerHTML = `<div style="padding:30px 16px; text-align:center; color:var(--text-muted); font-size:13px;">No songs found matching filter.</div>`;
        return;
      }

      listEl.innerHTML = res.items.map(item => {
        const isChecked = selectedRowIds.has(Number(item.rowid));
        const isActive = Number(item.rowid) === activeRowId;
        return `
          <div class="song-item ${isActive ? 'active' : ''}" id="song-item-${item.rowid}" onclick="selectSong(${item.rowid})">
            <input type="checkbox" ${isChecked ? 'checked' : ''} onclick="toggleCheck(event, ${item.rowid})">
            <div class="song-item-body">
              <div class="song-item-title">${escapeHtml(item.title1)}</div>
              <div class="song-item-meta">
                ${item.songkey ? `<span class="key-pill">${escapeHtml(item.songkey)}</span>` : ''}
                <span>${escapeHtml(item.author || 'No Artist')}</span>
              </div>
            </div>
          </div>
        `;
      }).join('');

      if (autoSelectFirst && res.items.length > 0) {
        selectSong(res.items[0].rowid);
      }
    }

    function changePage(delta) {
      const next = currentPage + delta;
      if (next >= 1 && next <= totalPages) {
        loadSongs(next);
      }
    }

    function toggleCheck(e, rowid) {
      e.stopPropagation();
      rowid = Number(rowid);
      if (e.target.checked) {
        selectedRowIds.add(rowid);
      } else {
        selectedRowIds.delete(rowid);
      }
      updateBulkBar();
    }

    function clearSelection() {
      selectedRowIds.clear();
      updateBulkBar();
      loadSongs(currentPage);
    }

    function updateBulkBar() {
      const bar = document.getElementById('bulkBar');
      const count = selectedRowIds.size;
      if (count > 0) {
        bar.classList.add('visible');
        document.getElementById('bulkCountLabel').textContent = `${count} selected`;
      } else {
        bar.classList.remove('visible');
      }
    }

    async function selectSong(rowid) {
      const res = await apiCall('get_song', { rowid });
      if (!res.ok) {
        showToast(res.error || 'Could not load song', true);
        return;
      }
      populateEditor(res.song);

      document.querySelectorAll('.song-item.active').forEach(el => el.classList.remove('active'));
      const itemEl = document.getElementById(`song-item-${rowid}`);
      if (itemEl) itemEl.classList.add('active');
    }

    function populateEditor(s) {
      document.getElementById('songRowId').value = s.rowid || '';
      document.getElementById('songTitle1').value = s.title1 || '';
      document.getElementById('songTitle2').value = s.title2 || '';
      document.getElementById('songAuthor').value = s.author || '';
      document.getElementById('songKey').value = s.songkey || '';
      document.getElementById('songSequence').value = s.sequence || '';
      document.getElementById('songFolderNo').value = s.folderno ?? 1;
      document.getElementById('songNumber').value = s.songnumber ?? 0;
      document.getElementById('songCapo').value = s.capo ?? -1;
      document.getElementById('songTiming').value = s.timing || '';
      document.getElementById('songCopyright').value = s.copyright || '';
      document.getElementById('songUserRef').value = s.userreference || '';
      document.getElementById('songContents').value = s.contents || '';

      document.getElementById('editorHeading').textContent = s.rowid ? `Edit Song` : `New Song`;
      document.getElementById('lastModifiedBadge').textContent = s.lastmodified ? `Modified: ${s.lastmodified}` : '';

      const link = document.getElementById('sourceUrlLink');
      if (s.userreference && s.userreference.startsWith('http')) {
        link.href = s.userreference;
        link.style.display = 'inline';
      } else {
        link.style.display = 'none';
      }

      renderSlidePreview();
    }

    function newSongForm() {
      populateEditor({
        rowid: '',
        title1: '',
        author: '',
        songkey: '',
        sequence: '',
        folderno: 1,
        songnumber: 0,
        capo: -1,
        contents: "[1]\n\n[chorus]\n"
      });
      document.getElementById('songTitle1').focus();
    }

    function toggleMoreFields() {
      document.getElementById('moreFieldsGrid').classList.toggle('open');
    }

    async function saveCurrentSong() {
      const title1 = document.getElementById('songTitle1').value.trim();
      if (!title1) {
        showToast('Song Title (title1) is required!', true);
        document.getElementById('songTitle1').focus();
        return;
      }

      const payload = {
        rowid: document.getElementById('songRowId').value,
        title1,
        title2: document.getElementById('songTitle2').value,
        author: document.getElementById('songAuthor').value,
        songkey: document.getElementById('songKey').value,
        sequence: document.getElementById('songSequence').value,
        folderno: document.getElementById('songFolderNo').value,
        songnumber: document.getElementById('songNumber').value,
        capo: document.getElementById('songCapo').value,
        timing: document.getElementById('songTiming').value,
        copyright: document.getElementById('songCopyright').value,
        userreference: document.getElementById('songUserRef').value,
        contents: document.getElementById('songContents').value,
      };

      const res = await apiCall('save_song', payload);
      if (!res.ok) {
        showToast(res.error || 'Save failed', true);
        return;
      }

      showToast(res.message);
      if (res.song) {
        populateEditor(res.song);
      }
      await loadDatabases();
      await loadSongs(currentPage);
    }

    async function deleteCurrentSong() {
      const rowid = document.getElementById('songRowId').value;
      const title = document.getElementById('songTitle1').value;
      if (!rowid) return;
      if (!confirm(`Delete "${title}" from ${currentDb}?`)) return;

      const res = await apiCall('delete_song', { rowid });
      if (res.ok) {
        showToast(res.message);
        await loadDatabases();
        await loadSongs(currentPage, true);
      } else {
        showToast(res.error || 'Delete failed', true);
      }
    }

    async function deleteSelectedSongs() {
      if (selectedRowIds.size === 0) return;
      if (!confirm(`Delete ${selectedRowIds.size} selected song(s) from ${currentDb}?`)) return;

      const res = await apiCall('delete_song', { rowids: Array.from(selectedRowIds) });
      if (res.ok) {
        showToast(res.message);
        selectedRowIds.clear();
        updateBulkBar();
        await loadDatabases();
        await loadSongs(currentPage, true);
      } else {
        showToast(res.error || 'Bulk delete failed', true);
      }
    }

    // Insert section tag at cursor in textarea
    function insertTag(tag) {
      const ta = document.getElementById('songContents');
      const start = ta.selectionStart;
      const end = ta.selectionEnd;
      const val = ta.value;
      const prefix = (start > 0 && val[start - 1] !== '\n') ? '\n' : '';
      const insertText = `${prefix}${tag}\n`;
      ta.value = val.substring(0, start) + insertText + val.substring(end);
      ta.focus();
      ta.selectionStart = ta.selectionEnd = start + insertText.length;
      renderSlidePreview();
    }

    async function stripChordsInEditor() {
      const raw = document.getElementById('songContents').value;
      if (!raw.trim()) return;
      const res = await apiCall('format_lyrics', {
        raw_text: raw,
        uppercase: true,
        deduplicate: true,
        two_line_slides: true
      });
      if (res.ok) {
        document.getElementById('songContents').value = res.contents;
        renderSlidePreview();
        showToast('Chords stripped & formatted for Easyslides!');
      }
    }

    function transformLyricsCase(toUpper) {
      const ta = document.getElementById('songContents');
      const lines = ta.value.split('\n');
      ta.value = lines.map(line => {
        if (/^\s*\[[^\]]+\]\s*$/.test(line)) {
          return line.trim().toLowerCase();
        }
        if (toUpper) {
          return line.toUpperCase();
        } else {
          return line.toLowerCase().replace(/(^|\s)\S/g, t => t.toUpperCase());
        }
      }).join('\n');
      renderSlidePreview();
    }

    // Live Easyslides Slide Preview Renderer
    function renderSlidePreview() {
      const raw = document.getElementById('songContents').value || '';
      const container = document.getElementById('slidesPreview');
      const lines = raw.replace(/\r\n/g, '\n').split('\n');

      const sections = [];
      let curTag = 'Slide';
      let curLines = [];

      const flush = () => {
        const text = curLines.join('\n').trim();
        if (text !== '') {
          // Split by blank lines into individual slides
          const blocks = text.split(/\n\s*\n/);
          blocks.forEach((b, idx) => {
            if (b.trim() !== '') {
              sections.push({
                tag: curTag,
                isFirstOfSection: idx === 0,
                text: b.trim()
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
          const rawTag = m[1].toLowerCase();
          if (/^\d+$/.test(rawTag)) {
            curTag = `Verse ${rawTag}`;
          } else if (rawTag === 'chorus') {
            curTag = 'Chorus';
          } else if (rawTag === 'prechorus') {
            curTag = 'PreChorus';
          } else if (rawTag === 'bridge') {
            curTag = 'Bridge';
          } else if (rawTag === 'ending') {
            curTag = 'End';
          } else {
            curTag = rawTag.toUpperCase();
          }
        } else {
          curLines.push(line);
        }
      }
      flush();

      document.getElementById('slideCountBadge').textContent = `${sections.length} slide${sections.length === 1 ? '' : 's'}`;

      if (sections.length === 0) {
        container.innerHTML = `<div style="color:var(--text-muted); text-align:center; padding:30px; font-size:13px;">Type lyrics on the left to preview Easyslides slides.</div>`;
        return;
      }

      window._currentPreviewSlides = sections.map((s, idx) => ({
        slide_no: idx + 1,
        section: s.tag,
        region1: s.text,
        region2: ''
      }));

      container.innerHTML = sections.map((s, idx) => `
        <div class="slide-card" style="cursor:pointer;" onclick="projectSlideFromEditor(${idx + 1})" title="Click to send Slide #${idx + 1} to OBS Overlay">
          <span class="slide-badge">${escapeHtml(s.tag)}</span>
          <span class="slide-num">#${idx + 1} • Click → OBS</span>
          ${s.isFirstOfSection ? `<div class="slide-heading">${escapeHtml(s.tag)}</div>` : ''}
          <div class="slide-lyrics">${escapeHtml(s.text)}</div>
        </div>
      `).join('');
    }

    async function projectSlideFromEditor(slideNo) {
      const title1 = document.getElementById('songTitle1').value || '';
      const author = document.getElementById('songAuthor').value || '';
      const slides = window._currentPreviewSlides || [];
      try {
        await fetch('obs_api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'push_slide',
            title1,
            author,
            slides,
            slide_no: slideNo
          })
        });
        showToast(`Sent Slide #${slideNo} to OBS Overlay!`);
      } catch (e) {}
    }

    // Copy Modal Logic
    function openCopyModal(isBulk) {
      copyBulkMode = isBulk;
      const count = isBulk ? selectedRowIds.size : 1;
      const songTitle = document.getElementById('songTitle1').value;
      document.getElementById('copyModalDesc').textContent = isBulk
        ? `Copy ${count} selected song(s) from "${currentDb}" into:`
        : `Copy "${songTitle}" from "${currentDb}" into:`;
      document.getElementById('copyPrefixField').style.display = isBulk ? 'none' : 'flex';
      document.getElementById('copyPrefixInput').value = '';
      document.getElementById('copyModal').classList.add('open');
    }

    async function confirmCopyToDb() {
      const targetDb = document.getElementById('copyTargetDbSelect').value;
      const prefix = document.getElementById('copyPrefixInput').value.trim();
      const rowids = copyBulkMode
        ? Array.from(selectedRowIds)
        : [Number(document.getElementById('songRowId').value)];

      const res = await apiCall('copy_to_db', {
        source_db: currentDb,
        target_db: targetDb,
        rowids,
        prefix_number: prefix
      });

      if (res.ok) {
        showToast(res.message);
        closeModal('copyModal');
        await loadDatabases();
      } else {
        showToast(res.error || 'Copy failed', true);
      }
    }

    // New DB Modal
    function openNewDbModal() {
      document.getElementById('newDbNameInput').value = '';
      document.getElementById('newDbModal').classList.add('open');
      document.getElementById('newDbNameInput').focus();
    }

    async function confirmCreateDb() {
      const name = document.getElementById('newDbNameInput').value.trim();
      if (!name) return;
      const res = await apiCall('create_db', { name });
      if (res.ok) {
        showToast(res.message);
        closeModal('newDbModal');
        await loadDatabases(res.filename);
        await loadSongs(1, true);
      } else {
        showToast(res.error || 'Failed to create DB', true);
      }
    }

    function downloadCurrentDb() {
      window.location.href = `api.php?action=download_db&db=${encodeURIComponent(currentDb)}`;
    }

    // Scraper Modal
    function openScraperModal() {
      document.getElementById('scraperModal').classList.add('open');
      pollScrapeStatus();
      scrapePollTimer = setInterval(pollScrapeStatus, 2000);
    }

    function closeModal(id) {
      document.getElementById(id).classList.remove('open');
      if (id === 'scraperModal' && scrapePollTimer) {
        clearInterval(scrapePollTimer);
        scrapePollTimer = null;
      }
    }

    async function scrapeSingleUrl(save) {
      const url = document.getElementById('scrapeUrlInput').value.trim();
      const uppercase = document.getElementById('scrapeUppercaseCheck').checked;
      if (!url) {
        showToast('Enter a JRChord song URL first', true);
        return;
      }
      const res = await apiCall('scrape_url', { url, save, uppercase });
      if (!res.ok) {
        showToast(res.error || 'Scrape failed', true);
        return;
      }
      showToast(res.message);
      populateEditor(res.song);
      if (save) {
        await loadDatabases();
        await loadSongs(currentPage);
      }
      closeModal('scraperModal');
    }

    async function triggerFullScrape(source = 'jrchord') {
      const uppercase = document.getElementById('scrapeUppercaseCheck').checked;
      const targetDb = source === 'lagurohani' ? 'MasterLyricDB.db' : currentDb;
      const res = await apiCall('start_full_scrape', { target_db: targetDb, source, uppercase });
      if (res.ok) {
        showToast(res.message);
        pollScrapeStatus();
      }
    }

    async function pollScrapeStatus() {
      const res = await apiCall('scrape_status');
      if (!res.ok || !res.status) return;
      const st = res.status;
      const stateEl = document.getElementById('scrapeStateText');
      const pctEl = document.getElementById('scrapePercentText');
      const barEl = document.getElementById('scrapeProgressBar');
      const detailEl = document.getElementById('scrapeDetailText');

      const total = Number(st.total || 0);
      const proc = Number(st.processed || 0);
      const pct = total > 0 ? Math.min(100, Math.round((proc / total) * 100)) : 0;

      stateEl.textContent = `Status: ${(st.state || 'idle').toUpperCase()}`;
      pctEl.textContent = total > 0 ? `${pct}% (${proc}/${total})` : '';
      barEl.style.width = `${pct}%`;
      detailEl.textContent = st.message || '';

      if (st.state === 'completed') {
        loadDatabases();
      }
    }

    function escapeHtml(str) {
      return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    // Ctrl+S shortcut to save song
    window.addEventListener('keydown', e => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        saveCurrentSong();
      }
    });

    // Boot
    (async function init() {
      initAlphaBar();
      await loadDatabases();
      await loadSongs(1, true);
    })();
  </script>
</body>
</html>
