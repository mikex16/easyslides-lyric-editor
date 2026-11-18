<?php
/**
 * Concurrent LaguRohani.id Sitemap Scraper & MasterLyricDB Builder
 * 1. Copies all songs from JRChord.db into MasterLyricDB.db
 * 2. Scrapes all ~3,143 songs from https://www.lagurohani.id/sitemap.xml
 * 3. Skips any song already present in JRChord.db (by normalized song title)
 * 4. Inserts all new songs into MasterLyricDB.db
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/config.php';

$jrchordDbName = 'JRChord.db';
$masterDbName = $argv[1] ?? 'MasterLyricDB.db';
$uppercase = isset($argv[2]) ? (bool)(int)$argv[2] : true;
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

echo "=== LaguRohani.id Scraper -> $masterDbName ===\n";

// 1. Seed MasterLyricDB.db from JRChord.db
$jrPath = resolveDbPath($jrchordDbName);
$masterPath = resolveDbPath($masterDbName);

if (file_exists($jrPath)) {
    copy($jrPath, $masterPath);
    registerDatabaseInEasyslides($masterDbName);
    echo "Seeded $masterDbName with existing songs from $jrchordDbName.\n";
}

$jrPdo = getDbConnection($jrchordDbName);
$masterPdo = getDbConnection($masterDbName);
$masterPdo->exec('PRAGMA synchronous = OFF');
$masterPdo->exec('PRAGMA journal_mode = MEMORY');

// Build normalized title lookup from JRChord.db
$existingTitleKeys = [];
$jrRows = $jrPdo->query('SELECT title1, author FROM "items"')->fetchAll(PDO::FETCH_ASSOC);
foreach ($jrRows as $r) {
    $key = normalizeSongTitleKey($r['title1'], $r['author']);
    if ($key !== '') {
        $existingTitleKeys[$key] = true;
    }
}
$jrInitialCount = count($jrRows);
echo "Loaded $jrInitialCount existing songs (" . count($existingTitleKeys) . " normalized title keys) from $jrchordDbName.\n";

updateScraperStatus([
    'state' => 'discovering',
    'target_db' => $masterDbName,
    'started_at' => date('Y-m-d H:i:s'),
    'total' => 0,
    'processed' => 0,
    'saved' => 0,
    'skipped' => 0,
    'failed' => 0,
    'message' => 'Fetching sitemaps from lagurohani.id...',
]);

// 2. Discover all song URLs from https://www.lagurohani.id/sitemap.xml
$sitemapXml = fetchUrl('https://www.lagurohani.id/sitemap.xml');
$sitemapPages = [];
if (preg_match_all('/<loc>(https?:\/\/[^<]+)<\/loc>/i', $sitemapXml, $smMatches)) {
    $sitemapPages = $smMatches[1];
}
if (empty($sitemapPages)) {
    $sitemapPages = [
        'https://www.lagurohani.id/sitemap.xml?page=1',
        'https://www.lagurohani.id/sitemap.xml?page=2',
        'https://www.lagurohani.id/sitemap.xml?page=3',
        'https://www.lagurohani.id/sitemap.xml?page=4',
    ];
}

$songUrls = [];
foreach ($sitemapPages as $smUrl) {
    echo "Fetching $smUrl ...\n";
    $xml = fetchUrl($smUrl);
    if (preg_match_all('/<loc>(https?:\/\/[^<]+)<\/loc>/i', $xml, $uMatches)) {
        foreach ($uMatches[1] as $u) {
            $u = trim($u);
            $songUrls[$u] = $u;
        }
    }
}

$songUrls = array_values($songUrls);
$totalUrls = count($songUrls);
echo "Total unique URLs discovered on lagurohani.id: $totalUrls\n";

$batchSize = 35;
$processed = 0;
$saved = 0;
$skipped = 0;
$failed = 0;
$startTime = microtime(true);

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
            curl_multi_select($mh, 0.4);
        }
    } while ($active && $mrc == CURLM_OK);

    $masterPdo->beginTransaction();
    foreach ($handles as $url => $ch) {
        $html = curl_multi_getcontent($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        $processed++;
        if ($httpCode === 200 && is_string($html) && $html !== '') {
            $song = parseLagurohaniHtml($html, $url, $uppercase);
            if ($song !== null) {
                $normKey = normalizeSongTitleKey($song['base_title'], $song['author']);
                // Skip if already in JRChord.db (or already added with same normalized title + author)
                if ($normKey !== '' && isset($existingTitleKeys[$normKey])) {
                    $skipped++;
                    continue;
                }
                try {
                    upsertEasyslidesSong($masterPdo, $song);
                    if ($normKey !== '') {
                        // Also prevent duplicate non-hymnal songs within lagurohani.id unless different hymnal book
                        if (empty($song['bookreference'])) {
                            $existingTitleKeys[$normKey] = true;
                        }
                    }
                    $saved++;
                } catch (Throwable $e) {
                    $failed++;
                }
            } else {
                $failed++;
            }
        } else {
            $failed++;
        }
    }
    $masterPdo->commit();
    curl_multi_close($mh);

    $elapsed = round(microtime(true) - $startTime, 1);
    if (($bIdx + 1) % 5 === 0 || ($bIdx + 1) === count($batches)) {
        echo sprintf(
            "[Batch %d/%d] Processed: %d/%d | New Saved: %d | Skipped (in JRChord): %d | Failed: %d | Elapsed: %ss\n",
            $bIdx + 1,
            count($batches),
            $processed,
            $totalUrls,
            $saved,
            $skipped,
            $failed,
            $elapsed
        );
    }

    updateScraperStatus([
        'state' => 'running',
        'target_db' => $masterDbName,
        'started_at' => date('Y-m-d H:i:s', (int)$startTime),
        'total' => $totalUrls,
        'processed' => $processed,
        'saved' => $saved,
        'skipped' => $skipped,
        'failed' => $failed,
        'elapsed_sec' => $elapsed,
        'message' => "LaguRohani.id: $processed/$totalUrls processed ($saved new added, $skipped skipped as duplicates)...",
    ]);
}

$totalInMaster = (int)$masterPdo->query('SELECT COUNT(*) FROM "items"')->fetchColumn();
$elapsed = round(microtime(true) - $startTime, 1);

echo "\n=== MasterLyricDB Build Complete! ===\n";
echo "Initial songs from JRChord.db: $jrInitialCount\n";
echo "LaguRohani.id URLs processed: $processed\n";
echo "Skipped (already in JRChord.db / duplicate): $skipped\n";
echo "New songs added from LaguRohani.id: $saved\n";
echo "Total combined songs in $masterDbName: $totalInMaster\n";
echo "Time elapsed: {$elapsed}s\n";

updateScraperStatus([
    'state' => 'completed',
    'target_db' => $masterDbName,
    'completed_at' => date('Y-m-d H:i:s'),
    'total' => $totalUrls,
    'processed' => $processed,
    'saved' => $saved,
    'skipped' => $skipped,
    'failed' => $failed,
    'total_in_db' => $totalInMaster,
    'elapsed_sec' => $elapsed,
    'message' => "Completed! $totalInMaster total songs in $masterDbName ($jrInitialCount from JRChord + $saved new from LaguRohani.id, $skipped skipped).",
]);
