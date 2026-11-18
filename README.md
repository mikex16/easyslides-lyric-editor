# Easyslides Song & Lyric Studio (`easyslides-lyric-editor`)

A PHP web editor and concurrent song/lyric scraper built for **Easyslides** SQLite databases (`.db`).

## Features

- **Easyslides SQLite Database Compatible**:
  - Creates and edits SQLite `.db` files using the exact Easyslides `items` table schema and automatically registers new databases in `DatabasesAdmin.db`.
  - Formats lyrics into Easyslides section tags (`[1]`, `[2]`, `[chorus]`, `[prechorus]`, `[bridge]`, `[ending]`, `[tag]`) with 2-line slide blocks and UPPERCASE lyrics.
  - Names songs in `<song title> - <artist>` format.
- **Multi-Site Christian Song Scrapers (Tanpa Chord)**:
  - **JRChord.com Scraper (`scraper_cli.php`)**: Scrapes all songs from `https://www.jrchord.com/` sitemaps, strips all chord lines and inline chord-alignment hyphens, and formats lyrics for Easyslides (`JRChord.db`).
  - **LaguRohani.id Scraper (`scraper_lagurohani_cli.php`)**: Scrapes songs from `https://www.lagurohani.id/sitemap.xml`, automatically skips songs already in `JRChord.db`, and builds the combined `MasterLyricDB.db`.
- **Full Web Editor (`index.php` & `api.php`)**:
  - Switch between any `.db` in your Easyslides `Databases` folder (`MasterLyricDB.db`, `JRChord.db`, etc.).
  - Search by title, artist, lyrics, or musical key, and filter by alphabet (`#`, `A`–`Z`) or artist.
  - Live **Easyslides Screen Preview** as you type.
  - One-click **Strip Chords & Format (Tanpa Chord)** tool for pasted chord sheets.
  - Copy single or bulk-selected songs between databases (with optional song number prefixes).

## Usage

1. Place this folder in your PHP web root (e.g., `htdocs/lyrc`) and open `http://localhost/lyrc/`.
2. Optional local configuration can be placed in `config.local.php` (ignored by Git):
   ```php
   <?php
   define('EASYSLIDES_DATA_DIR', 'C:/Path/To/Documents/EasyslidesData/Admin');
   ```
3. Run CLI scrapers anytime:
   ```bash
   php scraper_cli.php "JRChord.db" 1
   php scraper_lagurohani_cli.php "MasterLyricDB.db" 1
   ```
