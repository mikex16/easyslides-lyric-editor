<?php
/**
 * Concurrent JRChord Sitemap Scraper (CLI & Background Runner)
 * Scrapes all songs from https://www.jrchord.com/ sitemaps (tanpa chord)
 * and saves them in Easyslides SQLite database format.
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/config.php';

$targetDb = $argv[1] ?? DEFAULT_DB_FILE;
$uppercase = isset($argv[2]) ? (bool)(int)$argv[2] : true;
$limit = isset($argv[3]) ? (int)$argv[3] : 0; // 0 = all songs
$statusFile = __DIR__ . '/scraper_status.json';

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
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return is_string($res) ? $res : '';
}

echo "=== JRChord Full Scraper (Tanpa Chord -> Easyslides DB) ===\n";
echo "Target DB: " . resolveDbPath($targetDb) . "\n";

updateScraperStatus([
    'state' => 'discovering',
    'target_db' => $targetDb,
    'started_at' => date('Y-m-d H:i:s'),
    'total' => 0,
    'processed' => 0,
    'saved' => 0,
    'failed' => 0,
    'message' => 'Fetching sitemap_index.xml from jrchord.com...',
]);

// 1. Discover post sitemaps from sitemap_index.xml
$sitemapIndexXml = fetchUrl('https://www.jrchord.com/sitemap_index.xml');
$postSitemaps = [];
if (preg_match_all('/<loc>(https?:\/\/[^<]+post-sitemap[^<]*\.xml)<\/loc>/i', $sitemapIndexXml, $smMatches)) {
    $postSitemaps = $smMatches[1];
}
if (empty($postSitemaps)) {
    $postSitemaps = [
        'https://www.jrchord.com/post-sitemap.xml',
        'https://www.jrchord.com/post-sitemap2.xml',
    ];
}

echo "Found " . count($postSitemaps) . " post sitemaps.\n";

$songUrls = [];
foreach ($postSitemaps as $smUrl) {
    echo "Fetching $smUrl ...\n";
    $xml = fetchUrl($smUrl);
    if (preg_match_all('/<loc>(https?:\/\/[^<]+)<\/loc>/i', $xml, $uMatches)) {
        foreach ($uMatches[1] as $u) {
            $u = trim($u);
            // Skip image URLs or homepage
            if (preg_match('/\.(jpg|jpeg|png|webp|gif|svg)$/i', $u)) {
                continue;
            }
            if (rtrim($u, '/') === 'https://www.jrchord.com') {
                continue;
            }
            $songUrls[$u] = $u;
        }
    }
}

$songUrls = array_values($songUrls);
if ($limit > 0 && count($songUrls) > $limit) {
    $songUrls = array_slice($songUrls, 0, $limit);
}

$totalUrls = count($songUrls);
echo "Total unique song URLs discovered: $totalUrls\n";

$pdo = getDbConnection($targetDb);
$pdo->exec('PRAGMA synchronous = OFF');
$pdo->exec('PRAGMA journal_mode = MEMORY');

$batchSize = 25;
$processed = 0;
$saved = 0;
$failed = 0;
$failedUrls = [];
$startTime = microtime(true);

updateScraperStatus([
    'state' => 'running',
    'target_db' => $targetDb,
    'started_at' => date('Y-m-d H:i:s'),
    'total' => $totalUrls,
    'processed' => 0,
    'saved' => 0,
    'failed' => 0,
    'message' => "Scraping $totalUrls songs from jrchord.com...",
]);

$batches = array_chunk($songUrls, $batchSize);

foreach ($batches as $bIdx => $batchUrls) {
    $mh = curl_multi_init();
    $handles = [];

    foreach ($batchUrls as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$url] = $ch;
    }

    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
        if ($active) {
            curl_multi_select($mh, 0.5);
        }
    } while ($active && $mrc == CURLM_OK);

    $pdo->beginTransaction();
    foreach ($handles as $url => $ch) {
        $html = curl_multi_getcontent($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        $processed++;
        if ($httpCode === 200 && is_string($html) && $html !== '') {
            $song = parseJrchordHtml($html, $url, $uppercase);
            if ($song !== null) {
                try {
                    upsertEasyslidesSong($pdo, $song);
                    $saved++;
                } catch (Throwable $e) {
                    $failed++;
                    $failedUrls[] = $url;
                }
            } else {
                $failed++;
                $failedUrls[] = $url;
            }
        } else {
            $failed++;
            $failedUrls[] = $url;
        }
    }
    $pdo->commit();
    curl_multi_close($mh);

    $elapsed = round(microtime(true) - $startTime, 1);
    echo sprintf(
        "[Batch %d/%d] Processed: %d/%d | Saved: %d | Failed: %d | Elapsed: %ss\n",
        $bIdx + 1,
        count($batches),
        $processed,
        $totalUrls,
        $saved,
        $failed,
        $elapsed
    );

    updateScraperStatus([
        'state' => 'running',
        'target_db' => $targetDb,
        'started_at' => date('Y-m-d H:i:s', (int)$startTime),
        'total' => $totalUrls,
        'processed' => $processed,
        'saved' => $saved,
        'failed' => $failed,
        'elapsed_sec' => $elapsed,
        'message' => "Scraped $processed / $totalUrls songs ($saved saved)...",
    ]);
}

// Retry failed URLs sequentially once
if (!empty($failedUrls)) {
    echo "Retrying " . count($failedUrls) . " failed URLs...\n";
    $stillFailed = 0;
    $pdo->beginTransaction();
    foreach ($failedUrls as $url) {
        $html = fetchUrl($url, 25);
        if ($html !== '') {
            $song = parseJrchordHtml($html, $url, $uppercase);
            if ($song !== null) {
                try {
                    upsertEasyslidesSong($pdo, $song);
                    $saved++;
                    $failed--;
                    continue;
                } catch (Throwable $e) {
                }
            }
        }
        $stillFailed++;
    }
    $pdo->commit();
}

$totalInDb = (int)$pdo->query('SELECT COUNT(*) FROM "items"')->fetchColumn();
$elapsed = round(microtime(true) - $startTime, 1);

echo "\n=== Scraping Complete! ===\n";
echo "Total URLs processed: $processed\n";
echo "Successfully parsed & saved: $saved\n";
echo "Total unique songs in $targetDb: $totalInDb\n";
echo "Time elapsed: {$elapsed}s\n";

updateScraperStatus([
    'state' => 'completed',
    'target_db' => $targetDb,
    'completed_at' => date('Y-m-d H:i:s'),
    'total' => $totalUrls,
    'processed' => $processed,
    'saved' => $saved,
    'failed' => $failed,
    'total_in_db' => $totalInDb,
    'elapsed_sec' => $elapsed,
    'message' => "Completed! $totalInDb songs stored in $targetDb ({$elapsed}s).",
]);
