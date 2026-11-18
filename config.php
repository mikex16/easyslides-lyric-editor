<?php
/**
 * Configuration & Core Helpers for Easyslides DB Editor & JRChord Scraper
 */

// Optional local config override (ignored in git)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

function detectEasyslidesDataDir(): string {
    $candidates = [];
    if ($up = getenv('USERPROFILE')) {
        $candidates[] = str_replace('\\', '/', $up) . '/Documents/EasyslidesData/Admin';
    }
    foreach (glob('C:/Users/*/Documents/EasyslidesData/Admin', GLOB_ONLYDIR) ?: [] as $dir) {
        $candidates[] = str_replace('\\', '/', $dir);
    }
    foreach ($candidates as $cand) {
        if (is_dir($cand . '/Databases')) {
            return $cand;
        }
    }
    return str_replace('\\', '/', __DIR__);
}

$detectedDataDir = detectEasyslidesDataDir();
if (!defined('EASYSLIDES_DATA_DIR')) {
    define('EASYSLIDES_DATA_DIR', $detectedDataDir);
}
if (!defined('EASYSLIDES_DB_DIR')) {
    define('EASYSLIDES_DB_DIR', is_dir(EASYSLIDES_DATA_DIR . '/Databases') ? (EASYSLIDES_DATA_DIR . '/Databases') : EASYSLIDES_DATA_DIR);
}
if (!defined('EASYSLIDES_SETTINGS_DB')) {
    define('EASYSLIDES_SETTINGS_DB', EASYSLIDES_DATA_DIR . '/Settings/DatabasesAdmin.db');
}
if (!defined('DEFAULT_DB_FILE')) {
    define('DEFAULT_DB_FILE', 'MasterLyricDB.db');
}

// Default Easyslides formatdata matching Hari Malaysia 2025.db
define('DEFAULT_FORMATDATA', "<ShowSongHeadings>3</ShowSongHeadings>\r\n<ShowSongHeadingsAlign>0</ShowSongHeadingsAlign>\r\n<UseShadowFont>1</UseShadowFont>\r\n<ShowNotations>0</ShowNotations>\r\n<CapoZero>0</CapoZero>\r\n<UseOutlineFont>0</UseOutlineFont>\r\n<DisplayRegions>2</DisplayRegions>\r\n<DisplayRegionsLayout>0</DisplayRegionsLayout>\r\n<ScreenColour1>-16777056</ScreenColour1>\r\n<ScreenColour2>-16777056</ScreenColour2>\r\n<ScreenPatternStyle>0</ScreenPatternStyle>\r\n<BackgroundPicture />\r\n<BackgroundPictureMode>2</BackgroundPictureMode>\r\n<VerticalAlign>1</VerticalAlign>\r\n<ScreenLeftMargin>2</ScreenLeftMargin>\r\n<ScreenRightMargin>2</ScreenRightMargin>\r\n<ScreenBottomMargin>0</ScreenBottomMargin>\r\n<ShowItemTransition>0</ShowItemTransition>\r\n<ShowSlideTransition>0</ShowSlideTransition>\r\n<FontVPosition1>2</FontVPosition1>\r\n<FontVPosition2>50</FontVPosition2>\r\n<MediaOption>3</MediaOption>\r\n<MediaVolume>50</MediaVolume>\r\n<MediaBalance>0</MediaBalance>\r\n<MediaMute>1</MediaMute>\r\n<MediaRepeat>0</MediaRepeat>\r\n<MediaWidescreen>0</MediaWidescreen>\r\n<MediaCaptureDeviceNumber>3</MediaCaptureDeviceNumber>\r\n<HeadingFontFormat>1</HeadingFontFormat>\r\n<HeadingFontPercentSize>100</HeadingFontPercentSize>\r\n<HeadingFontBold>0</HeadingFontBold>\r\n<HeadingFontItalic>0</HeadingFontItalic>\r\n<HeadingFontUnderline>1</HeadingFontUnderline>\r\n<HeadingFontChorusItalic>0</HeadingFontChorusItalic>\r\n<FontBold1>0</FontBold1>\r\n<FontItalic1>0</FontItalic1>\r\n<FontUnderline1>0</FontUnderline1>\r\n<FontChorusBold1>0</FontChorusBold1>\r\n<FontChorusItalic1>0</FontChorusItalic1>\r\n<FontChorusUnderline1>0</FontChorusUnderline1>\r\n<FontBold2>0</FontBold2>\r\n<FontItalic2>0</FontItalic2>\r\n<FontUnderline2>0</FontUnderline2>\r\n<FontChorusBold2>0</FontChorusBold2>\r\n<FontChorusItalic2>0</FontChorusItalic2>\r\n<FontChorusUnderline2>0</FontChorusUnderline2>\r\n<FontName1>Microsoft Sans Serif</FontName1>\r\n<FontName2>Microsoft Sans Serif</FontName2>\r\n<FontSize1>40</FontSize1>\r\n<FontSize2>40</FontSize2>\r\n<FontColour1>-1</FontColour1>\r\n<FontColour2>-1</FontColour2>\r\n<FontRTL1>0</FontRTL1>\r\n<FontRTL2>0</FontRTL2>\r\n<FontAlign1>2</FontAlign1>\r\n<FontAlign2>2</FontAlign2>\r\n<KeyTransposeOffset>0</KeyTransposeOffset>\r\n<PreviousKeyTransposeOffset>0</PreviousKeyTransposeOffset>\r\n<ShowDataPanel>2</ShowDataPanel>\r\n<AutoTextOverflow>2</AutoTextOverflow>\r\n<UseLargestFontSize>2</UseLargestFontSize>\r\n<LineBetweenRegions>2</LineBetweenRegions>\r\n<WordWrapLeftAlignIndent>2</WordWrapLeftAlignIndent>");

/**
 * List all available .db files in the Easyslides Databases directory
 */
function getAvailableDatabases(): array {
    $dir = EASYSLIDES_DB_DIR;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $files = glob($dir . '/*.db');
    $result = [];
    foreach ($files as $f) {
        $basename = basename($f);
        $result[] = [
            'filename' => $basename,
            'path' => $f,
            'size' => filesize($f),
            'modified' => date('Y-m-d H:i:s', filemtime($f)),
        ];
    }
    return $result;
}

/**
 * Resolve safe database file path inside EASYSLIDES_DB_DIR
 */
function resolveDbPath(string $filename): string {
    $clean = basename(trim($filename));
    if ($clean === '' || !preg_match('/\.db$/i', $clean)) {
        $clean .= '.db';
    }
    return EASYSLIDES_DB_DIR . '/' . $clean;
}

/**
 * Connect to an Easyslides SQLite database and ensure the 'items' table exists
 */
function getDbConnection(string $filename = DEFAULT_DB_FILE): PDO {
    $path = resolveDbPath($filename);
    $isNew = !file_exists($path) || filesize($path) === 0;

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Ensure items table exists with exact Easyslides schema
    $pdo->exec("CREATE TABLE IF NOT EXISTS 'items' (
        itemid INTEGER,
        title1 TEXT NOT NULL COLLATE NOCASE,
        title2 TEXT,
        titlesnormalized TEXT,
        songnumber INTEGER,
        folderno INTEGER,
        oldfolderno INTEGER,
        contents TEXT,
        contentsnormalized TEXT,
        sequence TEXT,
        author TEXT NOT NULL COLLATE NOCASE,
        authornormalized TEXT,
        copyright TEXT,
        copyrightnormalized TEXT,
        capo INTEGER,
        timing TEXT,
        songkey TEXT,
        notations TEXT,
        category TEXT,
        cjkwordcount TEXT,
        cjkstrokecount TEXT,
        licenceadmin1 TEXT,
        licenceadmin2 TEXT,
        bookreference TEXT,
        userreference TEXT,
        cclisongnumber TEXT,
        rotatepattern TEXT,
        formatdata TEXT,
        lastmodified TEXT NOT NULL,
        foldername TEXT,
        t1 TEXT,
        PRIMARY KEY (title1, author)
    )");

    if ($isNew) {
        registerDatabaseInEasyslides(basename($path));
    }

    return $pdo;
}

/**
 * Register the database in Easyslides DatabasesAdmin.db so Easyslides shows it automatically
 */
function registerDatabaseInEasyslides(string $filename): void {
    if (!file_exists(EASYSLIDES_SETTINGS_DB)) {
        return;
    }
    try {
        $adminPdo = new PDO('sqlite:' . EASYSLIDES_SETTINGS_DB, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $name = preg_replace('/\.db$/i', '', $filename);
        $stmt = $adminPdo->prepare('SELECT COUNT(*) FROM settings WHERE LOWER(filename) = LOWER(?) OR LOWER(name) = LOWER(?)');
        $stmt->execute([$filename, $name]);
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }

        // Get max displayorder excluding 100000
        $maxOrder = (int)$adminPdo->query('SELECT COALESCE(MAX(CASE WHEN displayorder < 99999 THEN displayorder ELSE 2 END), 2) FROM settings')->fetchColumn();
        $nextOrder = $maxOrder + 1;

        $insert = $adminPdo->prepare("INSERT INTO settings (
            name, filename, use, displayorder, groupstyle,
            headingprechorus, headingchorus, headingbridge, headingending,
            fontname1, bold1, italic1, underline1, chorusbold1, chorusitalic1, chorusunderline1,
            headingbold1, headingitalic1, headingunderline1, size1, halign1, vpos1, rtl1, linespacing1,
            fontname2, bold2, italic2, underline2, chorusbold2, chorusitalic2, chorusunderline2,
            headingbold2, headingitalic2, headingunderline2, size2, halign2, vpos2, rtl2, linespacing2,
            headingpercentsize, headingoption, lmargin, rmargin, bmargin, headingintro, headingtag
        ) VALUES (
            ?, ?, 1, ?, 0,
            'PreChorus', 'Chorus', 'Bridge', 'End',
            'Microsoft Sans Serif', 0, 0, 0, 0, 0, 0,
            0, 0, 1, 40, 2, 2, 0, 1,
            'Microsoft Sans Serif', 0, 0, 0, 0, 0, 0,
            0, 0, 0, 40, 2, 50, 0, 1,
            100, 1, 2, 2, 0, 'Intro', 'Tag'
        )");
        $insert->execute([$name, $filename, $nextOrder]);
    } catch (Throwable $e) {
        // Ignore if settings DB is locked
    }
}

/**
 * Determine if a single token is a musical chord or chord notation marker
 */
function isChordToken(string $token): bool {
    $token = trim($token);
    if ($token === '') {
        return true;
    }
    // Strip surrounding parentheses/brackets/bars/dots
    $clean = trim($token, "()[]{}|.,-~");
    if ($clean === '') {
        return true;
    }
    // Multiplier like 2x, x2, 3x, 4x
    if (preg_match('/^([0-9]+x|x[0-9]+)$/i', $clean)) {
        return true;
    }
    // Standard chord pattern (matches JRChord transposer + common extensions like maj9, m7b5, sus4, add9, slash chords)
    $chordPattern = '/^[A-G][b#]?(?:6\/9|7\-5|7\-9|7\#5|7\#9|7\+5|7\+9|7b5|7b9|7sus2|7sus4|7sus|9sus4|add2|add4|add9|add11|aug|dim7|dim|m\/maj7|mMaj7|mmaj7|m6|m7b5|m7|m9|m11|m13|maj7|maj9|maj11|maj13|M7|mb5|m|sus2|sus4|sus|2|4|5|6|7|9|11|13|\+|\-)*(?:\/[A-G][b#]?m?)?$/';
    return (bool)preg_match($chordPattern, $clean);
}

/**
 * Determine if an entire line is a chord line (and not a lyric line)
 */
function isChordLine(string $line): bool {
    $trimmed = trim($line);
    if ($trimmed === '') {
        return false;
    }
    // Replace multiple whitespace and non-breaking spaces with single space
    $normalized = preg_replace('/[\s\x{00A0}]+/u', ' ', $trimmed);
    $tokens = explode(' ', $normalized);
    $chordCount = 0;
    foreach ($tokens as $tok) {
        if ($tok === '') continue;
        if (!isChordToken($tok)) {
            return false;
        }
        $chordCount++;
    }
    return $chordCount > 0;
}

/**
 * Clean stretched syllables used for chord alignment in JRChord
 * e.g., "Sa - lib - Mu" -> "Salib-Mu", "Keme - na - ngan - ku" -> "Kemenanganku", "Supa - ya" -> "Supaya"
 */
function cleanStretchedSyllables(string $line): string {
    // collapse multiple spaces inside a line
    $line = preg_replace('/[\t\x{00A0} ]+/u', ' ', trim($line));

    // Repeatedly join stretched syllables like "Keme - na - ngan - ku" or "Sa - lib - Mu"
    for ($i = 0; $i < 5; $i++) {
        // If the second part is Mu, Ku, Nya (capitalized or lowercase at end of word), keep hyphen or join cleanly
        $next = preg_replace_callback('/\b([A-Za-z\']+)\s+-\s+([A-Za-z\']+)\b/u', function ($m) {
            $left = $m[1];
            $right = $m[2];
            if (preg_match('/^(Mu|Nya|Ku)$/i', $right)) {
                return $left . '-' . $right;
            }
            return $left . strtolower($right);
        }, $line);
        if ($next === $line) {
            break;
        }
        $line = $next;
    }

    return $line;
}

/**
 * Convert raw JRChord <pre> text (or any chord+lyric text) into Easyslides lyric format (tanpa chord)
 */
function convertJrchordToEasyslides(
    string $rawText,
    bool $uppercase = true,
    bool $deduplicateSections = true,
    bool $twoLineSlides = true
): array {
    $text = html_entity_decode($rawText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = preg_split('/\r\n|\r|\n/', $text);

    $sections = [];
    $currentTag = '[1]';
    $currentType = 'verse'; // 'verse', 'chorus', 'prechorus', 'bridge', 'ending', 'tag', 'skip'
    $currentLines = [];
    $verseCounter = 0;
    $hasExplicitSection = false;
    $inMetadataTail = false;

    $flushSection = function () use (&$sections, &$currentTag, &$currentType, &$currentLines) {
        if ($currentType === 'skip') {
            $currentLines = [];
            return;
        }
        // Trim leading and trailing empty lines
        while (!empty($currentLines) && trim($currentLines[0]) === '') {
            array_shift($currentLines);
        }
        while (!empty($currentLines) && trim(end($currentLines)) === '') {
            array_pop($currentLines);
        }
        if (!empty($currentLines)) {
            $sections[] = [
                'tag' => $currentTag,
                'type' => $currentType,
                'lines' => $currentLines,
            ];
        }
        $currentLines = [];
    };

    foreach ($lines as $idx => $rawLine) {
        $trimmed = trim(preg_replace('/[\x{00A0}]+/u', ' ', $rawLine));

        // Skip first line if it starts with "Chord "
        if ($idx <= 2 && preg_match('/^Chord\s+/i', $trimmed)) {
            continue;
        }

        // Check if we hit trailing metadata like "Urutan Nada Dasar Yang Dipakai :"
        if (preg_match('/^(Urutan\s+Nada\s+Dasar|Catatan\s*:|Note\s*:)/i', $trimmed)) {
            $inMetadataTail = true;
            continue;
        }
        if ($inMetadataTail) {
            continue;
        }

        // Detect section headers
        if (preg_match('/^(Bait|Verse|Stanza)\s*([0-9]*)\s*:?$/i', $trimmed, $m)) {
            $flushSection();
            $hasExplicitSection = true;
            $num = $m[2] !== '' ? (int)$m[2] : (++$verseCounter);
            if ($num > $verseCounter) {
                $verseCounter = $num;
            }
            $currentTag = '[' . $num . ']';
            $currentType = 'verse';
            continue;
        }

        if (preg_match('/^(Reff|Refrein|Chorus)\s*([0-9]*)\s*:?$/i', $trimmed, $m)) {
            $flushSection();
            $hasExplicitSection = true;
            $num = $m[2] !== '' ? (int)$m[2] : 1;
            $currentTag = $num > 1 ? '[chorus ' . $num . ']' : '[chorus]';
            $currentType = 'chorus';
            continue;
        }

        if (preg_match('/^(Pre[\s\-]*Chorus|Pre[\s\-]*Reff)\s*([0-9]*)\s*:?$/i', $trimmed, $m)) {
            $flushSection();
            $hasExplicitSection = true;
            $num = $m[2] !== '' ? (int)$m[2] : 1;
            $currentTag = $num > 1 ? '[prechorus ' . $num . ']' : '[prechorus]';
            $currentType = 'prechorus';
            continue;
        }

        if (preg_match('/^(Bridge|Interlude\s+Lyric)\s*([0-9]*)\s*:?$/i', $trimmed, $m)) {
            $flushSection();
            $hasExplicitSection = true;
            $num = $m[2] !== '' ? (int)$m[2] : 1;
            $currentTag = $num > 1 ? '[bridge ' . $num . ']' : '[bridge]';
            $currentType = 'bridge';
            continue;
        }

        if (preg_match('/^(Ending|Outro|Coda)\s*([0-9]*)\s*:?$/i', $trimmed, $m)) {
            $flushSection();
            $hasExplicitSection = true;
            $currentTag = '[ending]';
            $currentType = 'ending';
            continue;
        }

        if (preg_match('/^(Tag|Worship|Spontaneous)\s*([0-9]*)\s*:?$/i', $trimmed, $m)) {
            $flushSection();
            $hasExplicitSection = true;
            $currentTag = '[tag]';
            $currentType = 'tag';
            continue;
        }

        if (preg_match('/^(Intro|Musik|Music|Interlude|Instrumental|Modulasi|Overtune|Key|Nada\s+Dasar)\s*([0-9]*)\s*:?$/i', $trimmed)) {
            $flushSection();
            $currentType = 'skip';
            continue;
        }

        // Also handle already-formatted Easyslides tags like [1], [chorus], [bridge]
        if (preg_match('/^\[([a-z0-9\s]+)\]$/i', $trimmed, $m)) {
            $flushSection();
            $hasExplicitSection = true;
            $tagInner = strtolower(trim($m[1]));
            $currentTag = '[' . $tagInner . ']';
            if (is_numeric($tagInner)) {
                $currentType = 'verse';
                $verseCounter = max($verseCounter, (int)$tagInner);
            } else {
                $currentType = $tagInner;
            }
            continue;
        }

        if ($currentType === 'skip') {
            // If we are in an Intro/Music/Modulasi block, chord lines and blank lines are skipped,
            // but if an actual lyric line appears (for songs without an explicit "Bait :" header after "Intro :"),
            // automatically switch into a verse section!
            if ($trimmed === '' || isChordLine($trimmed)) {
                continue;
            }
            $verseCounter++;
            $currentTag = '[' . $verseCounter . ']';
            $currentType = 'verse';
        }

        // Skip chord lines
        if (isChordLine($trimmed)) {
            continue;
        }

        if ($trimmed === '') {
            // Preserve paragraph break inside section (avoid consecutive blank lines)
            if (!empty($currentLines) && end($currentLines) !== '') {
                $currentLines[] = '';
            }
            continue;
        }

        // Clean lyric line
        $cleaned = cleanStretchedSyllables($trimmed);
        if ($uppercase) {
            $cleaned = mb_strtoupper($cleaned, 'UTF-8');
        }
        $currentLines[] = $cleaned;
    }

    $flushSection();

    // Post-process sections:
    // 1. If a single verse section has multiple stanzas separated by blank lines and no other numbered verses exist,
    //    or if sections are repeated identically (e.g., Chorus repeated after Modulation), handle cleanly.
    $finalSections = [];
    $seenByTypeAndLyrics = [];
    $typeCounts = [];
    $autoVerseNum = 0;

    foreach ($sections as $sec) {
        $nonEmptyLines = array_values(array_filter($sec['lines'], fn($l) => trim($l) !== ''));
        if (empty($nonEmptyLines)) {
            continue;
        }

        $lyricFingerprint = md5(mb_strtolower(implode("\n", $nonEmptyLines), 'UTF-8'));
        $baseType = preg_replace('/\s+[0-9]+$/', '', $sec['type']);

        if ($deduplicateSections && isset($seenByTypeAndLyrics[$baseType . ':' . $lyricFingerprint])) {
            // Exact duplicate of an earlier section of the same type
            continue;
        }
        $seenByTypeAndLyrics[$baseType . ':' . $lyricFingerprint] = true;

        $tag = $sec['tag'];
        if ($baseType === 'verse') {
            // Ensure sequential verse numbering [1], [2], [3]...
            $autoVerseNum++;
            $tag = '[' . $autoVerseNum . ']';
        } else {
            $typeCounts[$baseType] = ($typeCounts[$baseType] ?? 0) + 1;
            if ($typeCounts[$baseType] > 1 && $tag === '[' . $baseType . ']') {
                $tag = '[' . $baseType . ' ' . $typeCounts[$baseType] . ']';
            }
        }

        // Format lines inside the section: if $twoLineSlides is enabled and there are no blank lines yet
        // (or within stanza blocks), group into 2-line slide pairs separated by a blank line (matching Hari Malaysia 2025.db)
        $formattedLines = [];
        if ($twoLineSlides) {
            // Split by existing blank lines into stanzas first
            $stanzas = [];
            $curStanza = [];
            foreach ($sec['lines'] as $l) {
                if ($l === '') {
                    if (!empty($curStanza)) {
                        $stanzas[] = $curStanza;
                        $curStanza = [];
                    }
                } else {
                    $curStanza[] = $l;
                }
            }
            if (!empty($curStanza)) {
                $stanzas[] = $curStanza;
            }

            $slideBlocks = [];
            foreach ($stanzas as $stanza) {
                $chunks = array_chunk($stanza, 2);
                foreach ($chunks as $chunk) {
                    $slideBlocks[] = implode("\n", $chunk);
                }
            }
            $formattedLines = explode("\n", implode("\n\n", $slideBlocks));
        } else {
            $formattedLines = $sec['lines'];
        }

        $finalSections[] = $tag . "\n" . implode("\n", $formattedLines);
    }

    $contents = implode("\n", $finalSections);
    return [
        'contents' => $contents,
        'section_count' => count($finalSections),
    ];
}

/**
 * Parse a full JRChord HTML page into song array
 */
function parseJrchordHtml(string $html, string $url = '', bool $uppercase = true): ?array {
    if (trim($html) === '') {
        return null;
    }

    // Extract title
    $title = '';
    if (preg_match('/<h1[^>]*class="[^"]*song-detail-title[^"]*"[^>]*>(.*?)<\/h1>/is', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    // Extract artist / author
    $artist = '';
    if (preg_match('/<div[^>]*class="[^"]*song-detail-artist[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m)) {
        $artist = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    // Extract <pre> block and data-key
    $songKey = '';
    $rawPre = '';
    if (preg_match('/<pre([^>]*)>(.*?)<\/pre>/is', $html, $pm)) {
        $preAttrs = $pm[1];
        $rawPre = $pm[2];
        if (preg_match('/data-key="([^"]+)"/i', $preAttrs, $km)) {
            $songKey = trim($km[1]);
        }
    } else {
        return null;
    }

    if ($title === '') {
        return null;
    }

    // Format song name as "<song title> - <artist>" when artist is available
    $cleanArtist = ($artist !== '-' && $artist !== '') ? $artist : '';
    $formattedTitle = $cleanArtist !== '' ? ($title . ' - ' . $cleanArtist) : $title;

    $parsed = convertJrchordToEasyslides($rawPre, $uppercase, true, true);
    if (trim($parsed['contents']) === '') {
        return null;
    }

    return [
        'title1' => $formattedTitle,
        'base_title' => $title,
        'author' => $cleanArtist,
        'songkey' => $songKey,
        'contents' => $parsed['contents'],
        'raw_pre' => html_entity_decode($rawPre, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'url' => $url,
    ];
}

/**
 * Normalize a song's base title (without artist suffix) for deduplication across websites
 */
function normalizeSongTitleKey(string $title, string $author = ''): string {
    $t = trim($title);
    // Strip leading song numbers like "1. "
    $t = preg_replace('/^[0-9]+\.\s*/', '', $t);
    // Strip " - <author>" suffix if present
    if ($author !== '') {
        $suffix = ' - ' . trim($author);
        if (mb_strtolower(mb_substr($t, -mb_strlen($suffix))) === mb_strtolower($suffix)) {
            $t = trim(mb_substr($t, 0, mb_strlen($t) - mb_strlen($suffix)));
        }
    } else {
        // Also strip trailing " - Artist" if formatted that way
        if (preg_match('/^(.*?)\s+-\s+.+$/u', $t, $m)) {
            $t = trim($m[1]);
        }
    }
    $t = mb_strtolower($t, 'UTF-8');
    // Normalize common Indonesian contractions so "S'perti" == "Seperti", "T'lah" == "Telah", "B'ri" == "Beri"
    $t = str_replace(["'", "’", "`", "‘"], "", $t);
    $t = preg_replace('/\bsperti\b/', 'seperti', $t);
    $t = preg_replace('/\btlah\b/', 'telah', $t);
    $t = preg_replace('/\bslalu\b/', 'selalu', $t);
    $t = preg_replace('/\bslamanya\b/', 'selamanya', $t);
    $t = preg_replace('/\bsgala\b/', 'segala', $t);
    $t = preg_replace('/\bbri\b/', 'beri', $t);
    $t = preg_replace('/[^a-z0-9]+/', '', $t);
    return $t;
}

/**
 * Parse a full lagurohani.id HTML page into an Easyslides song array
 */
function parseLagurohaniHtml(string $html, string $url = '', bool $uppercase = true): ?array {
    if (trim($html) === '') {
        return null;
    }

    // 1. Extract H1 title
    $rawTitle = '';
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $hm)) {
        $rawTitle = trim(html_entity_decode(strip_tags($hm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if ($rawTitle === '') {
        return null;
    }

    // Clean suffixes from H1 like "| Lirik Lagu Rohani", "| Lagu Rohani", "(Penyembahan Penutup)", etc.
    $baseTitle = preg_replace('/\s*\|\s*.*$/u', '', $rawTitle);
    $baseTitle = preg_replace('/\s*\((?:Penyembahan[^)]*|Pujian[^)]*|Lirik[^)]*|Lagu\s+Rohani[^)]*)\)\s*$/iu', '', $baseTitle);
    $baseTitle = trim(preg_replace('/\s+/u', ' ', $baseTitle));
    if ($baseTitle === '') {
        return null;
    }

    // Skip non-song blog articles (sermons, Bible verse lists, devotionals) on lagurohani.id
    if (preg_match('/\b(Bahan\s+Khotbah|Khotbah\s+Kristen|Ayat\s+Alkitab|Renungan\s+Kristen|Renungan\s+Harian|Daftar\s+Lagu|Kumpulan\s+Lagu)\b/iu', $rawTitle)) {
        return null;
    }

    // 2. Extract post body inside <div id='body-post-it'>
    $bodyHtml = '';
    if (preg_match('/<div\s+id=[\'"]body-post-it[\'"][^>]*>(.*?)<div\s+style=[\'"]clear:\s*both/is', $html, $bm)) {
        $bodyHtml = $bm[1];
    } elseif (preg_match('/<div[^>]*class=[\'"][^\'"]*post-body[^\'"]*[\'"][^>]*>(.*?)<\/div>/is', $html, $bm2)) {
        $bodyHtml = $bm2[1];
    } else {
        return null;
    }

    // Convert HTML line breaks & block endings to newlines
    $text = preg_replace('/<br\s*\/?>/i', "\n", $bodyHtml);
    $text = preg_replace('/<\/(div|p|li|h[1-6]|tr)>/i', "\n", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xC2\xA0", ' ', $text);

    $lines = preg_split('/\r\n|\r|\n/', $text);

    // 3. Separate intro prose, lyric lines, and outro prose
    $introLines = [];
    $outroLines = [];
    $candidateLines = [];

    // Check if there is a marker line like: Lirik Lagu Rohani "Judul"
    $markerIndex = -1;
    foreach ($lines as $i => $l) {
        $t = trim($l);
        if (preg_match('/^Lirik\s+Lagu(?:\s+Rohani)?\s+[""\'\x{201C}]/iu', $t) && mb_strlen($t) < 120 && !str_contains($t, '. ')) {
            $markerIndex = $i;
            break;
        }
    }

    if ($markerIndex >= 0) {
        $introLines = array_slice($lines, 0, $markerIndex);
        $afterMarker = array_slice($lines, $markerIndex + 1);
    } else {
        $afterMarker = $lines;
    }

    foreach ($afterMarker as $l) {
        $t = trim(preg_replace('/\s+/u', ' ', $l));
        // Detect prose intro/outro paragraphs (long sentences about YouTube, metronome, tempo, etc.)
        if ($t !== '' && (
            preg_match('/^(Lirik\s+Lagu\s+Rohani\s+[""\'\x{201C}]|Jika\s+Anda\s+tergabung|Bagi\s+team\s+musik|Berikut\s+ini\s+cara\s+membawakan|Membawakan\s+lagu|Dalam\s+membawakan\s+lagu|Tempo\s+yang\s+digunakan|Biasanya\s+dalam\s+memimpin|Lagu\s+(?:ini|tersebut|[""\'\x{201C}])|Menyanyikan\s+lagu|Untuk\s+menyanyikan\s+lagu|Urutan\s+dalam\s+menyanyikan|Uratan\s+dalam\s+menyanyikan|Demikianlah\s+lirik|Lihat\s+juga\s*:)/iu', $t)
            || (mb_strlen($t) > 130 && preg_match('/\b(YouTube|metronome|smartphone|dinyanyikan|interlude|modulasi)\b/i', $t))
        )) {
            if (empty($candidateLines)) {
                $introLines[] = $t;
            } else {
                $outroLines[] = $t;
            }
            continue;
        }
        if (!empty($outroLines)) {
            $outroLines[] = $t;
            continue;
        }
        $candidateLines[] = $t;
    }

    $metaSearchText = mb_substr($text, 0, 900, 'UTF-8');
    $outroText = implode(' ', $outroLines);

    // 4. Extract Artist / Hymnal / Key / Timing from metaSearchText
    $artist = '';
    $bookRef = '';
    $songKey = '';
    $timing = '';

    if (preg_match('/(Kidung\s+Jemaat|Pelengkap\s+Kidung\s+Jemaat|Nyanyikanlah\s+Kidung\s+Baru|Gita\s+Bakti|Nyanyian\s+Kemenangan\s+Iman)\s*(?:\(([A-Z]+)\s*([0-9]*)\))?\s*(?:No\.?\s*([0-9]+[A-Za-z]?))?/iu', $metaSearchText, $hm)) {
        $bookName = trim($hm[1]);
        $abbrMap = [
            'kidung jemaat' => 'KJ',
            'pelengkap kidung jemaat' => 'PKJ',
            'nyanyikanlah kidung baru' => 'NKB',
            'gita bakti' => 'GB',
            'nyanyian kemenangan iman' => 'NKI',
        ];
        $abbr = $abbrMap[mb_strtolower($bookName)] ?? ($hm[2] ?: $bookName);
        $num = $hm[4] ?? ($hm[3] ?? '');
        $bookRef = trim($abbr . ($num !== '' ? ' ' . $num : ''));
        $artist = $bookRef;
    }

    if ($artist === '') {
        if (preg_match('/(?:dipopulerkan|dinyanyikan|dibawakan|diciptakan\s+dan\s+dinyanyikan|ditulis\s+dan\s+disusun|hasil\s+karya)\s+(?:oleh\s+)?(?:tim\s+)?((?-i:[A-Z])[A-Za-z0-9\.\s\'&\-\(\)]{1,55}?)(?=\s+dengan\b|\s+dimana\b|\s+dari\s+Album\b|\s+yang\s+diambil\b|\s+pada\s+tahun\b|\s+dalam\s+Album\b|,|;|\.\s+(?:Lagu|Dengan|Diambil|Terdapat|Dalam|Mendapat|Disambut|Hingga|Dimana|Selain|Bisa)|\.?\s*$)/ium', $metaSearchText, $am)) {
            $cand = trim($am[1], " .,\t\n\r");
            if (!preg_match('/^(alunan|iringan|irama|musik|lagu|tim\s+musik|jemaat|saat\s+akan)/i', $cand)) {
                $artist = $cand;
            }
        } elseif (preg_match('/Album\s+(?:Musik\s+)?Rohani\s+([A-Z][A-Za-z\.\s\']{2,35}?),\s+/u', $metaSearchText, $am2)) {
            $artist = trim($am2[1], " .,\t\n\r");
        }
    }

    if (preg_match('/(?:nada\s+dasar\s+(?:Do|1)\s*=\s*|(?:\b1|\bdo)\s*=\s*)([A-G][b#]?m?)\b/i', $metaSearchText, $km)) {
        $songKey = ucfirst(trim($km[1]));
    }
    if (preg_match('/(?:birama\s+|,\s*)([2346]\/[48])\b/i', $metaSearchText, $tm)) {
        $timing = trim($tm[1]);
    }

    // 5. Parse hints from outroText (e.g. chorus pada lirik "...", bridge pada lirik "...", coda pada lirik "...")
    $chorusHint = '';
    $chorus2Hint = '';
    $bridgeHint = '';
    $codaHint = '';
    $startsWithChorus = (bool)preg_match('/awal\s*\(\s*chorus\s*\)/i', $outroText);

    if (preg_match('/\bchorus\s+pada\s+lirik\s+(?:lagu\s+)?[""\'\x{201C}]([^""\'\x{201D}]+)[""\'\x{201D}]/iu', $outroText, $cm)) {
        $chorusHint = preg_replace('/[^a-z0-9]/', '', mb_strtolower($cm[1]));
    }
    if (preg_match('/\bchorus\s*2\s+pada\s+lirik\s+(?:lagu\s+)?[""\'\x{201C}]([^""\'\x{201D}]+)[""\'\x{201D}]/iu', $outroText, $c2m)) {
        $chorus2Hint = preg_replace('/[^a-z0-9]/', '', mb_strtolower($c2m[1]));
    }
    if (preg_match('/\bbridge\s+pada\s+lirik\s+(?:lagu\s+)?[""\'\x{201C}]([^""\'\x{201D}]+)[""\'\x{201D}]/iu', $outroText, $brm)) {
        $bridgeHint = preg_replace('/[^a-z0-9]/', '', mb_strtolower($brm[1]));
    }
    if (preg_match('/\bcoda\s+pada\s+lirik\s+(?:lagu\s+)?[""\'\x{201C}]([^""\'\x{201D}]+)[""\'\x{201D}]/iu', $outroText, $cdm)) {
        $codaHint = preg_replace('/[^a-z0-9]/', '', mb_strtolower($cdm[1]));
    }

    // Check if candidateLines already have explicit Reff/Chorus/Bait headers
    $hasExplicitHeaders = false;
    foreach ($candidateLines as $cl) {
        if (preg_match('/^(Bait|Verse|Reff|Refrein|Chorus|Bridge|Pre[\s\-]*Chorus|Ending|Coda)\s*[0-9]*\s*:?$/i', $cl)) {
            $hasExplicitHeaders = true;
            break;
        }
    }

    if ($hasExplicitHeaders) {
        // Normalize multiple blank lines in older posts (where every line was separated by a blank line)
        // Check if >70% of non-empty lines are single-line blocks separated by a single blank line
        $nonEmptyCount = count(array_filter($candidateLines, fn($l) => $l !== ''));
        $rawJoined = implode("\n", $candidateLines);
        if (str_contains($rawJoined, "\n\n\n")) {
            // Triple newlines separate stanzas, single blank lines were just line spacing
            $rawJoined = preg_replace('/\n{3,}/', "\n<<STANZA_BREAK>>\n", $rawJoined);
            $rawJoined = preg_replace('/\n{2}/', "\n", $rawJoined);
            $rawJoined = str_replace("<<STANZA_BREAK>>", "", $rawJoined);
        }
        $parsed = convertJrchordToEasyslides($rawJoined, $uppercase, true, true);
    } else {
        // Group candidateLines into stanzas by blank lines
        $stanzas = [];
        $cur = [];
        foreach ($candidateLines as $cl) {
            if ($cl === '') {
                if (!empty($cur)) {
                    $stanzas[] = $cur;
                    $cur = [];
                }
            } else {
                // Skip accidental chord lines if any
                if (isChordLine($cl)) {
                    continue;
                }
                $cur[] = $uppercase ? mb_strtoupper($cl, 'UTF-8') : $cl;
            }
        }
        if (!empty($cur)) {
            $stanzas[] = $cur;
        }

        if (empty($stanzas)) {
            return null;
        }

        $verseNum = 0;
        $outSections = [];
        $seenFingerprints = [];

        foreach ($stanzas as $sIdx => $stanza) {
            $firstLineNorm = preg_replace('/[^a-z0-9]/', '', mb_strtolower($stanza[0], 'UTF-8'));
            $fullStanzaNorm = preg_replace('/[^a-z0-9]/', '', mb_strtolower(implode(' ', $stanza), 'UTF-8'));

            // Deduplicate identical trailing 1-line coda repeat if already in previous stanza
            $fp = md5($fullStanzaNorm);
            if (isset($seenFingerprints[$fp])) {
                continue;
            }
            $seenFingerprints[$fp] = true;

            $tag = '';
            if ($sIdx === 0 && $startsWithChorus) {
                $tag = '[chorus]';
            } elseif ($chorusHint !== '' && str_starts_with($firstLineNorm, $chorusHint)) {
                $tag = '[chorus]';
            } elseif ($chorus2Hint !== '' && str_starts_with($firstLineNorm, $chorus2Hint)) {
                $tag = '[chorus 2]';
            } elseif ($bridgeHint !== '' && str_starts_with($firstLineNorm, $bridgeHint)) {
                $tag = '[bridge]';
            } elseif ($codaHint !== '' && str_starts_with($firstLineNorm, $codaHint) && count($stanza) <= 2) {
                $tag = '[ending]';
            } elseif ($sIdx === count($stanzas) - 1 && count($stanza) === 1 && count($stanzas) >= 2) {
                $tag = '[ending]';
            } else {
                $verseNum++;
                $tag = '[' . $verseNum . ']';
            }

            // Format stanza into 2-line slide chunks
            $chunks = array_chunk($stanza, 2);
            $blocks = array_map(fn($c) => implode("\n", $c), $chunks);
            $outSections[] = $tag . "\n" . implode("\n\n", $blocks);
        }

        $parsed = [
            'contents' => implode("\n", $outSections),
            'section_count' => count($outSections),
        ];
    }

    if (trim($parsed['contents']) === '') {
        return null;
    }

    $formattedTitle = $artist !== '' ? ($baseTitle . ' - ' . $artist) : $baseTitle;

    return [
        'title1' => $formattedTitle,
        'base_title' => $baseTitle,
        'author' => $artist,
        'songkey' => $songKey,
        'timing' => $timing,
        'bookreference' => $bookRef,
        'contents' => $parsed['contents'],
        'url' => $url,
    ];
}

/**
 * Insert or update a song item in an Easyslides SQLite database
 */
function upsertEasyslidesSong(PDO $pdo, array $song, ?string $origTitle1 = null, ?string $origAuthor = null): bool {
    $title1 = trim($song['title1'] ?? '');
    $author = trim($song['author'] ?? '');
    if ($title1 === '') {
        throw new InvalidArgumentException('Song title (title1) cannot be empty.');
    }

    $title2 = trim($song['title2'] ?? '');
    $contents = str_replace("\r\n", "\n", trim($song['contents'] ?? ''));
    $sequence = trim($song['sequence'] ?? '');
    $copyright = trim($song['copyright'] ?? '');
    $capo = isset($song['capo']) && $song['capo'] !== '' ? (int)$song['capo'] : -1;
    $timing = trim($song['timing'] ?? '');
    $songkey = trim($song['songkey'] ?? '');
    $notations = trim($song['notations'] ?? '');
    $category = trim($song['category'] ?? '');
    $songnumber = isset($song['songnumber']) && $song['songnumber'] !== '' ? (int)$song['songnumber'] : 0;
    $folderno = isset($song['folderno']) && $song['folderno'] !== '' ? (int)$song['folderno'] : 1;
    $oldfolderno = isset($song['oldfolderno']) && $song['oldfolderno'] !== '' ? (int)$song['oldfolderno'] : 0;
    $bookreference = trim($song['bookreference'] ?? '');
    $userreference = trim($song['userreference'] ?? ($song['url'] ?? ''));
    $cclisongnumber = trim($song['cclisongnumber'] ?? '');
    $formatdata = $song['formatdata'] ?? DEFAULT_FORMATDATA;
    $lastmodified = $song['lastmodified'] ?? date('Y-m-d');

    $cjkwordcount = '00';
    $cjkstrokecount = '000' . mb_strtolower($title1, 'UTF-8');

    // If editing an existing record whose primary key (title1, author) changed, delete or update old record
    if ($origTitle1 !== null && $origAuthor !== null && ($origTitle1 !== $title1 || $origAuthor !== $author)) {
        $del = $pdo->prepare('DELETE FROM "items" WHERE title1 = ? AND author = ?');
        $del->execute([$origTitle1, $origAuthor]);
    }

    $sql = 'INSERT OR REPLACE INTO "items" (
        itemid, title1, title2, titlesnormalized, songnumber, folderno, oldfolderno,
        contents, contentsnormalized, sequence, author, authornormalized,
        copyright, copyrightnormalized, capo, timing, songkey, notations, category,
        cjkwordcount, cjkstrokecount, licenceadmin1, licenceadmin2,
        bookreference, userreference, cclisongnumber, rotatepattern,
        formatdata, lastmodified, foldername, t1
    ) VALUES (
        NULL, :title1, :title2, "", :songnumber, :folderno, :oldfolderno,
        :contents, "", :sequence, :author, "",
        :copyright, "", :capo, :timing, :songkey, :notations, :category,
        :cjkwordcount, :cjkstrokecount, "", "",
        :bookreference, :userreference, :cclisongnumber, "",
        :formatdata, :lastmodified, "", ""
    )';

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':title1' => $title1,
        ':title2' => $title2,
        ':songnumber' => $songnumber,
        ':folderno' => $folderno,
        ':oldfolderno' => $oldfolderno,
        ':contents' => $contents,
        ':sequence' => $sequence,
        ':author' => $author,
        ':copyright' => $copyright,
        ':capo' => $capo,
        ':timing' => $timing,
        ':songkey' => $songkey,
        ':notations' => $notations,
        ':category' => $category,
        ':cjkwordcount' => $cjkwordcount,
        ':cjkstrokecount' => $cjkstrokecount,
        ':bookreference' => $bookreference,
        ':userreference' => $userreference,
        ':cclisongnumber' => $cclisongnumber,
        ':formatdata' => $formatdata,
        ':lastmodified' => $lastmodified,
    ]);
}
