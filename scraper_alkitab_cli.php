<?php
/**
 * Concurrent Alkitab.app Songbook Scraper (CLI & Background Runner)
 * Scrapes Indonesian Christian Songbooks from https://alkitab.app/songs
 * into separate per-book Easyslides SQLite databases (e.g. KJ.db, PKJ.db, NKB.db, NKI.db, etc.)
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/config.php';

$defaultBooks = [
    'KJ',   // Kidung Jemaat (503)
    'PKJ',  // Pelengkap Kidung Jemaat (308)
    'NKB',  // Nyanyikanlah Kidung Baru (239)
    'NKI',  // Nyanyian Kemenangan Iman (370)
    'GB',   // Gita Bakti (411)
    'KK',   // Kidung Keesaan (802)
    'KPPK', // Kidung Puji-Pujian Kristen (425)
    'NP',   // Nyanyian Pujian (363)
    'KLIK', // Kumpulan Lagu Ibadah Kreatif dan KKR (479)
    'KC',   // Kidung Ceria (402)
    'NR',   // Nafiri Rohani (320)
    'NRM',  // Nyanyian Rohani Methodist (329)
    'NNBT', // Nyanyikanlah Nyanyian Baru bagi Tuhan (50)
    'PPK',  // Puji-pujian Kristen (255)
    'PPPR', // Puji-pujian Pemuda/Remaja (110)
    'JB',   // Jiwaku Bersukacita: Pujian Sekolah Minggu (138)
    'LS',   // Lagu Sion Edisi Lengkap (524)
    'KPRI', // Kidung Persekutuan Reformed Injili 2004 (171)
    'KRI',  // Kidung Reformed Injili 2017 (333)
    'MNR1', // Mazmur & Nyanyian Rohani Buku 1 (100)
    'MNR2', // Mazmur & Nyanyian Rohani Buku 2 (205)
    'GBL',  // Gita Bakti Edisi Lama (313)
    'BN',   // Buku Nyanyian HKBP (874)
];

$bookArg = strtoupper(trim($argv[1] ?? 'ALL'));
$uppercase = isset($argv[2]) ? (bool)(int)$argv[2] : true;
$statusFile = __DIR__ . '/scraper_status.json';

$booksToScrape = ($bookArg === '' || $bookArg === 'ALL')
    ? $defaultBooks
    : array_filter(array_map('trim', explode(',', $bookArg)));

function updateScraperStatus(array $data): void {
    global $statusFile;
    file_put_contents($statusFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function fetchUrl(string $url, int $timeout = 20): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return is_string($res) ? $res : '';
}

echo "=== Alkitab.app Songbooks Scraper (Per-Book .db) ===\n";
echo "Books to scrape (" . count($booksToScrape) . "): " . implode(', ', $booksToScrape) . "\n";

$startTime = microtime(true);
$bookLists = [];
$grandTotalSongs = 0;

updateScraperStatus([
    'state' => 'discovering',
    'started_at' => date('Y-m-d H:i:s'),
    'total' => 0,
    'processed' => 0,
    'saved' => 0,
    'failed' => 0,
    'message' => 'Fetching song lists from alkitab.app/songs/api...',
]);

foreach ($booksToScrape as $book) {
    $json = fetchUrl("https://alkitab.app/songs/api/$book/list");
    $list = json_decode($json, true);
    if (is_array($list) && !empty($list)) {
        $bookLists[$book] = $list;
        $cnt = count($list);
        $grandTotalSongs += $cnt;
        echo "Discovered $book: $cnt songs\n";
    } else {
        echo "Warning: No songs returned for $book\n";
    }
}

echo "Grand total songs to scrape across " . count($bookLists) . " books: $grandTotalSongs\n\n";

$globalProcessed = 0;
$globalSaved = 0;
$globalFailed = 0;
$batchSize = 25;
$mh = curl_multi_init();
if (defined('CURLMOPT_PIPELINING') && defined('CURLPIPE_MULTIPLEX')) {
    @curl_multi_setopt($mh, CURLMOPT_PIPELINING, CURLPIPE_MULTIPLEX);
}

foreach ($bookLists as $book => $songs) {
    $dbFilename = $book . '.db';
    $pdo = getDbConnection($dbFilename);
    $pdo->exec('PRAGMA synchronous = OFF');
    $pdo->exec('PRAGMA journal_mode = MEMORY');

    // Load already scraped URLs in this DB so resuming skips already-saved songs
    $existingUrls = array_flip($pdo->query('SELECT userreference FROM "items" WHERE userreference != ""')->fetchAll(PDO::FETCH_COLUMN));

    $remainingSongs = [];
    foreach ($songs as $item) {
        $code = (string)($item['code'] ?? '');
        if ($code === '') continue;
        $url = "https://alkitab.app/$book/" . rawurlencode($code);
        if (isset($existingUrls[$url])) {
            $globalProcessed++;
            $globalSaved++;
        } else {
            $remainingSongs[] = $item;
        }
    }

    $bookTotal = count($songs);
    $alreadyCount = $bookTotal - count($remainingSongs);
    if (empty($remainingSongs)) {
        echo "--- Skipping $book -> $dbFilename (All $bookTotal songs already saved!) ---\n";
        continue;
    }

    $bookSaved = $alreadyCount;
    $bookFailed = 0;
    $batches = array_chunk($remainingSongs, $batchSize);

    echo "--- Scraping $book -> $dbFilename (" . count($remainingSongs) . " remaining of $bookTotal songs) ---\n";

    foreach ($batches as $bIdx => $batchItems) {
        $handles = [];

        foreach ($batchItems as $item) {
            $code = (string)($item['code'] ?? '');
            if ($code === '') continue;
            $url = "https://alkitab.app/$book/" . rawurlencode($code);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_ENCODING => '',
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$code] = ['ch' => $ch, 'url' => $url, 'item' => $item];
        }

        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh, 0.2);
            }
        } while ($active && $mrc == CURLM_OK);

        $pdo->beginTransaction();
        foreach ($handles as $code => $hInfo) {
            $ch = $hInfo['ch'];
            $url = $hInfo['url'];
            $html = curl_multi_getcontent($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            $globalProcessed++;
            if ($httpCode === 200 && is_string($html) && $html !== '') {
                $song = parseAlkitabAppSongHtml($html, $book, (string)$code, $url, $uppercase);
                if ($song !== null) {
                    try {
                        upsertEasyslidesSong($pdo, $song);
                        if (isset($masterPdo)) {
                            upsertEasyslidesSong($masterPdo, $song);
                        }
                        $bookSaved++;
                        $globalSaved++;
                    } catch (Throwable $e) {
                        $bookFailed++;
                        $globalFailed++;
                    }
                } else {
                    $bookFailed++;
                    $globalFailed++;
                }
            } else {
                $bookFailed++;
                $globalFailed++;
            }
        }
        $pdo->commit();

        $elapsed = round(microtime(true) - $startTime, 1);
        updateScraperStatus([
            'state' => 'running',
            'target_db' => $dbFilename,
            'total' => $grandTotalSongs,
            'processed' => $globalProcessed,
            'saved' => $globalSaved,
            'failed' => $globalFailed,
            'elapsed_sec' => $elapsed,
            'message' => "[$book -> $dbFilename] Scraped $globalProcessed / $grandTotalSongs songs ($globalSaved saved)...",
        ]);
    }

    $elapsed = round(microtime(true) - $startTime, 1);
    echo "Finished $dbFilename: $bookSaved/$bookTotal saved | Total progress: $globalProcessed/$grandTotalSongs ({$elapsed}s)\n";
}
curl_multi_close($mh);

$elapsed = round(microtime(true) - $startTime, 1);
echo "\n=== All Alkitab.app Songbooks Scraped! ===\n";
echo "Books created: " . count($bookLists) . " (.db files)\n";
echo "Total songs processed: $globalProcessed\n";
echo "Total songs saved: $globalSaved\n";
echo "Time elapsed: {$elapsed}s\n";

updateScraperStatus([
    'state' => 'completed',
    'target_db' => 'Alkitab.app Songbooks (' . count($bookLists) . ' DBs)',
    'completed_at' => date('Y-m-d H:i:s'),
    'total' => $grandTotalSongs,
    'processed' => $globalProcessed,
    'saved' => $globalSaved,
    'failed' => $globalFailed,
    'elapsed_sec' => $elapsed,
    'message' => "Completed! $globalSaved songs saved across " . count($bookLists) . " songbook .db files ({$elapsed}s).",
]);
