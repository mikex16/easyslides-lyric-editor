<?php
/**
 * JSON API Backend for Easyslides Song & Lyric DB Editor + JRChord Scraper
 */

require_once __DIR__ . '/config.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'download_db') {
    $dbFile = $_GET['db'] ?? DEFAULT_DB_FILE;
    $path = resolveDbPath($dbFile);
    if (!file_exists($path)) {
        http_response_code(404);
        echo "Database not found.";
        exit;
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Parse JSON body if sent
$rawBody = file_get_contents('php://input');
$body = [];
if ($rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

$req = array_merge($_GET, $_POST, $body);
$action = $req['action'] ?? '';
$dbFile = $req['db'] ?? DEFAULT_DB_FILE;

try {
    switch ($action) {
        case 'list_dbs': {
            $dbs = getAvailableDatabases();
            foreach ($dbs as &$d) {
                try {
                    $pdo = new PDO('sqlite:' . $d['path']);
                    $d['song_count'] = (int)$pdo->query('SELECT COUNT(*) FROM "items"')->fetchColumn();
                } catch (Throwable $e) {
                    $d['song_count'] = 0;
                }
            }
            jsonResponse([
                'ok' => true,
                'db_dir' => EASYSLIDES_DB_DIR,
                'databases' => $dbs,
            ]);
            break;
        }

        case 'create_db': {
            $newName = trim($req['name'] ?? '');
            if ($newName === '') {
                jsonResponse(['ok' => false, 'error' => 'Database name cannot be empty.'], 400);
            }
            $cleanName = preg_replace('/[^A-Za-z0-9 _\-\(\)\.]/', '', $newName);
            if (!preg_match('/\.db$/i', $cleanName)) {
                $cleanName .= '.db';
            }
            $pdo = getDbConnection($cleanName);
            registerDatabaseInEasyslides($cleanName);
            jsonResponse([
                'ok' => true,
                'filename' => $cleanName,
                'message' => "Created and registered database '$cleanName' in Easyslides.",
            ]);
            break;
        }

        case 'list_songs': {
            $pdo = getDbConnection($dbFile);
            $q = trim($req['q'] ?? '');
            $letter = trim($req['letter'] ?? '');
            $artist = trim($req['artist'] ?? '');
            $page = max(1, (int)($req['page'] ?? 1));
            $perPage = max(10, min(500, (int)($req['per_page'] ?? 100)));
            $sort = $req['sort'] ?? 'title_asc';

            $where = [];
            $params = [];

            if ($q !== '') {
                $where[] = '(title1 LIKE :q OR author LIKE :q OR contents LIKE :q OR songkey LIKE :q)';
                $params[':q'] = '%' . $q . '%';
            }
            if ($letter !== '' && $letter !== 'ALL') {
                if ($letter === '#') {
                    $where[] = " SUBSTR(TRIM(title1), 1, 1) GLOB '[0-9]*' ";
                } else {
                    $where[] = 'UPPER(SUBSTR(TRIM(title1), 1, 1)) = :letter';
                    $params[':letter'] = strtoupper(substr($letter, 0, 1));
                }
            }
            if ($artist !== '') {
                $where[] = 'author = :artist';
                $params[':artist'] = $artist;
            }

            $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $orderBy = 'title1 COLLATE NOCASE ASC, author COLLATE NOCASE ASC';
            if ($sort === 'title_desc') {
                $orderBy = 'title1 COLLATE NOCASE DESC, author COLLATE NOCASE ASC';
            } elseif ($sort === 'author_asc') {
                $orderBy = 'CASE WHEN author = "" THEN 1 ELSE 0 END, author COLLATE NOCASE ASC, title1 COLLATE NOCASE ASC';
            } elseif ($sort === 'recent') {
                $orderBy = 'lastmodified DESC, rowid DESC';
            }

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM \"items\" $whereSql");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $offset = ($page - 1) * $perPage;
            $sql = "SELECT rowid, title1, title2, author, songkey, capo, timing, sequence, songnumber, folderno, lastmodified, userreference,
                           SUBSTR(contents, 1, 180) AS preview
                    FROM \"items\"
                    $whereSql
                    ORDER BY $orderBy
                    LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll();

            // Also fetch top artists for filter dropdown
            $artists = $pdo->query('SELECT author, COUNT(*) as cnt FROM "items" WHERE TRIM(author) != "" GROUP BY author ORDER BY cnt DESC, author ASC LIMIT 100')->fetchAll();

            jsonResponse([
                'ok' => true,
                'db' => $dbFile,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => max(1, (int)ceil($total / $perPage)),
                'items' => $items,
                'artists' => $artists,
            ]);
            break;
        }

        case 'get_song': {
            $pdo = getDbConnection($dbFile);
            $rowid = isset($req['rowid']) ? (int)$req['rowid'] : 0;
            if ($rowid > 0) {
                $stmt = $pdo->prepare('SELECT rowid, * FROM "items" WHERE rowid = ?');
                $stmt->execute([$rowid]);
            } else {
                $title1 = $req['title1'] ?? '';
                $author = $req['author'] ?? '';
                $stmt = $pdo->prepare('SELECT rowid, * FROM "items" WHERE title1 = ? AND author = ?');
                $stmt->execute([$title1, $author]);
            }
            $song = $stmt->fetch();
            if (!$song) {
                jsonResponse(['ok' => false, 'error' => 'Song not found.'], 404);
            }
            jsonResponse(['ok' => true, 'song' => $song]);
            break;
        }

        case 'save_song': {
            $pdo = getDbConnection($dbFile);
            $rowid = isset($req['rowid']) && $req['rowid'] !== '' ? (int)$req['rowid'] : null;

            $origTitle1 = null;
            $origAuthor = null;
            $existingFormatData = DEFAULT_FORMATDATA;

            if ($rowid !== null && $rowid > 0) {
                $st = $pdo->prepare('SELECT title1, author, formatdata FROM "items" WHERE rowid = ?');
                $st->execute([$rowid]);
                $old = $st->fetch();
                if ($old) {
                    $origTitle1 = $old['title1'];
                    $origAuthor = $old['author'];
                    if (!empty($old['formatdata'])) {
                        $existingFormatData = $old['formatdata'];
                    }
                }
            }

            $songData = [
                'title1' => $req['title1'] ?? '',
                'title2' => $req['title2'] ?? '',
                'author' => $req['author'] ?? '',
                'contents' => $req['contents'] ?? '',
                'sequence' => $req['sequence'] ?? '',
                'songkey' => $req['songkey'] ?? '',
                'capo' => $req['capo'] ?? -1,
                'timing' => $req['timing'] ?? '',
                'songnumber' => $req['songnumber'] ?? 0,
                'folderno' => $req['folderno'] ?? 1,
                'copyright' => $req['copyright'] ?? '',
                'bookreference' => $req['bookreference'] ?? '',
                'userreference' => $req['userreference'] ?? '',
                'cclisongnumber' => $req['cclisongnumber'] ?? '',
                'formatdata' => $existingFormatData,
                'lastmodified' => date('Y-m-d'),
            ];

            upsertEasyslidesSong($pdo, $songData, $origTitle1, $origAuthor);

            // Fetch saved rowid
            $st2 = $pdo->prepare('SELECT rowid, * FROM "items" WHERE title1 = ? AND author = ?');
            $st2->execute([trim($songData['title1']), trim($songData['author'])]);
            $savedSong = $st2->fetch();

            jsonResponse([
                'ok' => true,
                'message' => 'Song saved successfully!',
                'song' => $savedSong,
            ]);
            break;
        }

        case 'delete_song': {
            $pdo = getDbConnection($dbFile);
            $rowids = $req['rowids'] ?? [];
            if (isset($req['rowid'])) {
                $rowids[] = (int)$req['rowid'];
            }
            $rowids = array_values(array_filter(array_map('intval', $rowids), fn($id) => $id > 0));
            if (empty($rowids)) {
                jsonResponse(['ok' => false, 'error' => 'No song IDs specified for deletion.'], 400);
            }
            $placeholders = implode(',', array_fill(0, count($rowids), '?'));
            $stmt = $pdo->prepare("DELETE FROM \"items\" WHERE rowid IN ($placeholders)");
            $stmt->execute($rowids);
            jsonResponse([
                'ok' => true,
                'deleted' => $stmt->rowCount(),
                'message' => 'Deleted ' . $stmt->rowCount() . ' song(s).',
            ]);
            break;
        }

        case 'copy_to_db': {
            $sourceDb = $req['source_db'] ?? $dbFile;
            $targetDb = $req['target_db'] ?? '';
            $rowids = $req['rowids'] ?? [];
            $prefixNumber = trim($req['prefix_number'] ?? '');

            if ($targetDb === '') {
                jsonResponse(['ok' => false, 'error' => 'Target database is required.'], 400);
            }
            $rowids = array_values(array_filter(array_map('intval', $rowids), fn($id) => $id > 0));
            if (empty($rowids)) {
                jsonResponse(['ok' => false, 'error' => 'No songs selected to copy.'], 400);
            }

            $srcPdo = getDbConnection($sourceDb);
            $dstPdo = getDbConnection($targetDb);

            $placeholders = implode(',', array_fill(0, count($rowids), '?'));
            $stmt = $srcPdo->prepare("SELECT * FROM \"items\" WHERE rowid IN ($placeholders)");
            $stmt->execute($rowids);
            $songs = $stmt->fetchAll();

            $copied = 0;
            $dstPdo->beginTransaction();
            foreach ($songs as $s) {
                if ($prefixNumber !== '' && count($songs) === 1) {
                    $s['title1'] = $prefixNumber . '. ' . preg_replace('/^[0-9]+\.\s*/', '', $s['title1']);
                }
                if (empty($s['formatdata'])) {
                    $s['formatdata'] = DEFAULT_FORMATDATA;
                }
                $s['lastmodified'] = date('Y-m-d');
                upsertEasyslidesSong($dstPdo, $s);
                $copied++;
            }
            $dstPdo->commit();

            jsonResponse([
                'ok' => true,
                'copied' => $copied,
                'message' => "Copied $copied song(s) to $targetDb.",
            ]);
            break;
        }

        case 'format_lyrics': {
            $raw = $req['raw_text'] ?? '';
            $uppercase = isset($req['uppercase']) ? (bool)$req['uppercase'] : true;
            $deduplicate = isset($req['deduplicate']) ? (bool)$req['deduplicate'] : true;
            $twoLine = isset($req['two_line_slides']) ? (bool)$req['two_line_slides'] : true;

            $res = convertJrchordToEasyslides($raw, $uppercase, $deduplicate, $twoLine);
            jsonResponse([
                'ok' => true,
                'contents' => $res['contents'],
                'section_count' => $res['section_count'],
            ]);
            break;
        }

        case 'scrape_url': {
            $url = trim($req['url'] ?? '');
            $saveToDb = !empty($req['save']);
            $uppercase = isset($req['uppercase']) ? (bool)$req['uppercase'] : true;

            $isJrchord = (bool)preg_match('/^https?:\/\/(www\.)?jrchord\.com\/.+/i', $url);
            $isLagurohani = (bool)preg_match('/^https?:\/\/(www\.)?lagurohani\.id\/.+/i', $url);

            if ($url === '' || (!$isJrchord && !$isLagurohani)) {
                jsonResponse(['ok' => false, 'error' => 'Please enter a valid jrchord.com or lagurohani.id song URL.'], 400);
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ]);
            $html = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200 || !is_string($html) || $html === '') {
                jsonResponse(['ok' => false, 'error' => "Failed to fetch URL (HTTP $code)."], 400);
            }

            $song = $isLagurohani
                ? parseLagurohaniHtml($html, $url, $uppercase)
                : parseJrchordHtml($html, $url, $uppercase);

            if (!$song) {
                jsonResponse(['ok' => false, 'error' => 'Could not parse song lyrics from that page.'], 400);
            }

            $savedRow = null;
            if ($saveToDb) {
                $pdo = getDbConnection($dbFile);
                upsertEasyslidesSong($pdo, $song);
                $st = $pdo->prepare('SELECT rowid, * FROM "items" WHERE title1 = ? AND author = ?');
                $st->execute([$song['title1'], $song['author']]);
                $savedRow = $st->fetch();
            }

            jsonResponse([
                'ok' => true,
                'song' => $savedRow ?: $song,
                'saved' => $saveToDb,
                'message' => $saveToDb
                    ? "Scraped & saved '{$song['title1']}' to $dbFile!"
                    : "Scraped '{$song['title1']}'.",
            ]);
            break;
        }

        case 'scrape_status': {
            $statusFile = __DIR__ . '/scraper_status.json';
            if (file_exists($statusFile)) {
                $st = json_decode(file_get_contents($statusFile), true) ?: [];
            } else {
                $st = ['state' => 'idle'];
            }
            jsonResponse(['ok' => true, 'status' => $st]);
            break;
        }

        case 'start_full_scrape': {
            $targetDb = $req['target_db'] ?? DEFAULT_DB_FILE;
            $source = $req['source'] ?? 'jrchord';
            $uppercase = isset($req['uppercase']) && !$req['uppercase'] ? 0 : 1;
            $phpExe = (defined('PHP_BINARY') && PHP_BINARY && is_file(PHP_BINARY))
                ? PHP_BINARY
                : (is_file('c:\\xampp\\php\\php.exe') ? 'c:\\xampp\\php\\php.exe' : 'php');
            $script = ($source === 'lagurohani')
                ? (__DIR__ . DIRECTORY_SEPARATOR . 'scraper_lagurohani_cli.php')
                : (__DIR__ . DIRECTORY_SEPARATOR . 'scraper_cli.php');
            $cmd = sprintf('start /B "" "%s" "%s" "%s" %d > NUL 2>&1', $phpExe, $script, $targetDb, $uppercase);
            pclose(popen($cmd, 'r'));
            jsonResponse([
                'ok' => true,
                'message' => "Started background scrape ($source) into $targetDb.",
            ]);
            break;
        }

        default:
            jsonResponse(['ok' => false, 'error' => "Unknown action: $action"], 400);
    }
} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
