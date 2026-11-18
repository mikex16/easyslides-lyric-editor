# Easyslides Song & Lyric Studio + OBS Multicast Overlay (`easyslides-lyric-editor`)

A full-featured PHP web editor, multi-site Indonesian Christian lyric scraper (**tanpa chord**), and real-time **Easyslides 5.2 Multicast $\rightarrow$ OBS Browser Source Overlay Studio**.

---

## ✨ Features

### 1. Real-Time OBS Browser Source Overlay & Easyslides Multicast Bridge
- **Direct Easyslides 5.2 Multicast TCP Integration (`multicast_daemon.php`)**:
  - Connects directly to Easyslides' built-in Multicast TCP Server (default port `9701`).
  - Implements the complete Easyslides 5.2 XML & RSA-OAEP handshake (`Command 11` $\rightarrow$ `12` $\rightarrow$ `13` $\rightarrow$ `14` $\rightarrow$ `16` $\rightarrow$ `35`).
  - Listens in real time for song changes (`Command 31`), slide transitions (`Command 34`), Black/Clear screen toggles (`Command 92`), alerts (`Command 51`–`54`), and Live Show stop (`Command 106` / `17`).
  - Parses Easyslides' `<SlidesArray>` line-index tuples and `<LyricsAndNotationsArray>` (supporting both Region 1 & bilingual Region 2 lyrics).
- **OBS Browser Source Overlay (`obs.php`)**:
  - Designed for `1920x1080` OBS Browser Sources with a **100% transparent background** and smooth cross-fade slide animations.
  - Automatically fades out when Easyslides **Black Screen**, **Clear Screen**, or **End Live Show** is active.
  - **4 Built-in Broadcast Themes**:
    1. **Frosted Glass Lower-Third** (`?theme=glass`) — Blurred dark glass card with accent underline, optional Section pill (`Verse 1`, `Chorus`), and Song Title badge.
    2. **Cinema Outline / Transparent** (`?theme=cinema`) — Clean broadcast subtitles with dark stroke outline and drop shadow (no box).
    3. **Worship Full-Width Gradient Bar** (`?theme=gradient`) — Full-width lower-third gradient bar across the bottom of the screen.
    4. **Stage Monitor View** (`?theme=stage`) — Full-screen confidence monitor showing the Current Slide, Next Slide preview, Song Title, Slide Counter, and a live 24-hour Digital Clock.
- **OBS Overlay & Multicast Control Studio (`obs_control.php`)**:
  - Configure Easyslides Server IP, Port (`9701`), Service Mode (`LiveScreen` or `StageDisplay`), and optional Multicast Password.
  - Customize overlay theme, vertical position (`bottom`, `center`, `top`), font size, box opacity, text color, and accent color with a live 16:9 preview monitor.
  - **Standalone Web-to-OBS Slide Controller**: Search any song in `MasterLyricDB.db` and project slides directly to OBS from your browser—even without Easyslides running!

---

### 2. Easyslides SQLite Database Studio (`index.php` & `api.php`)
- **Native Easyslides Schema Compatibility**:
  - Reads, creates, and edits `.db` files using the exact Easyslides `items` SQLite schema and automatically registers new databases in `DatabasesAdmin.db`.
  - Formats lyrics with Easyslides section tags (`[1]`, `[2]`, `[chorus]`, `[prechorus]`, `[bridge]`, `[ending]`, `[tag]`), 2-line slide blocks, and **UPPERCASE** lyrics.
  - Formats song titles as `<song title> - <artist>`.
- **Rich Web Editor**:
  - Switch between any `.db` in your Easyslides `Databases` folder (`MasterLyricDB.db`, `JRChord.db`, etc.).
  - Search by title, artist, lyrics, or musical key, and filter by alphabet (`#`, `A`–`Z`) or artist.
  - One-click **✨ Strip Chords & Format (Tanpa Chord)** button to strip chords from pasted chord sheets.
  - Live **Easyslides Screen Preview** — click any preview slide card to send it straight to the OBS Overlay.
  - Copy single or bulk-selected songs between databases (with optional song number prefixes).

---

### 3. Multi-Site Christian Song Scrapers (Tanpa Chord)
- **JRChord.com Scraper (`scraper_cli.php`)**:
  - Scrapes all **1,194** songs from `https://www.jrchord.com/`, strips all chord lines and inline chord-alignment hyphens (`Sa - lib - Mu` $\rightarrow$ `SALIB-MU`), and saves to `JRChord.db`.
- **LaguRohani.id Scraper (`scraper_lagurohani_cli.php`)**:
  - Scrapes songs from `https://www.lagurohani.id/sitemap.xml`, filters out non-song articles/sermons, skips songs already present in `JRChord.db`, and builds the combined **`MasterLyricDB.db` (3,254 pure songs)**.

---

## 📺 How to Set Up the OBS Overlay with Easyslides

### Step 1: Start Multicast in Easyslides
1. Open **Easyslides 5.2**.
2. In the middle pane, click the **Multicast** tab.
3. Note your **Server IP** (e.g., `192.168.x.x`) and **Port** (default `9701`), then click **Start Multicast**.

### Step 2: Connect the Multicast Listener
1. Open **`http://localhost/lyrc/obs_control.php`** in your browser (or click **📺 OBS Overlay Studio** from `http://localhost/lyrc/`).
2. Enter your Easyslides Server IP and Port (`9701`), choose **Live Screen (Recommended)**, and click **🔌 Connect & Start Listener**.
3. The status badge will turn green (**`CONNECTED`**) and display your currently active Easyslides item and slides.

### Step 3: Add the Browser Source in OBS Studio
1. In **OBS Studio**, click **+** under **Sources** $\rightarrow$ select **Browser**.
2. Set the properties:
   - **URL**: `http://localhost/lyrc/obs.php`
   - **Width**: `1920`
   - **Height**: `1080`
3. Optional URL query parameters (if you want multiple Browser Sources in OBS with different layouts, e.g. one for livestream lower-third and one for stage display):
   - Livestream Frosted Glass: `http://localhost/lyrc/obs.php?theme=glass`
   - Livestream Cinema Outline: `http://localhost/lyrc/obs.php?theme=cinema`
   - Livestream Gradient Bar: `http://localhost/lyrc/obs.php?theme=gradient`
   - Full-Screen Stage Monitor: `http://localhost/lyrc/obs.php?theme=stage`

---

## 🛠 Installation & CLI Usage

1. Place this repository inside your PHP/XAMPP web root (e.g., `c:\xampp\htdocs\lyrc`) and ensure PHP `pdo_sqlite`, `curl`, `mbstring`, `openssl`, and `simplexml` extensions are enabled.
2. Open **`http://localhost/lyrc/`** for the Song & Lyric Editor or **`http://localhost/lyrc/obs_control.php`** for the OBS Overlay Studio.
3. Optional local path overrides can be placed in `config.local.php` (ignored by Git):
   ```php
   <?php
   define('EASYSLIDES_DATA_DIR', 'C:/Path/To/Documents/EasyslidesData/Admin');
   ```
4. Run the CLI scrapers or multicast daemon manually anytime:
   ```bash
   php scraper_cli.php "JRChord.db" 1
   php scraper_lagurohani_cli.php "MasterLyricDB.db" 1
   php multicast_daemon.php
   ```
