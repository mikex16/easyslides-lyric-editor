<?php
/**
 * API Backend for OBS Overlay & Easyslides Multicast Controller
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$stateFile = __DIR__ . '/obs_state.json';
$configFile = __DIR__ . '/obs_config.json';
$pidFile = __DIR__ . '/obs_daemon.pid';

$rawBody = file_get_contents('php://input');
$body = $rawBody !== '' ? (json_decode($rawBody, true) ?: []) : [];
$req = array_merge($_GET, $_POST, $body);
$action = $req['action'] ?? 'state';

function getObsConfig(): array {
    global $configFile;
    $defaults = [
        'host' => '127.0.0.1',
        'port' => 9701,
        'service_type' => 1,
        'password' => '',
        'nickname' => 'OBS-Overlay',
        'enabled' => true,
        'theme' => 'glass', // glass, cinema, gradient, stage
        'font_size' => 42,
        'show_title' => true,
        'show_section' => true,
        'show_region2' => true,
        'position' => 'bottom',
        'bg_opacity' => 0.78,
        'text_color' => '#ffffff',
        'accent_color' => '#f97316',
    ];
    if (file_exists($configFile)) {
        $d = json_decode((string)@file_get_contents($configFile), true);
        if (is_array($d)) {
            return array_merge($defaults, $d);
        }
    }
    return $defaults;
}

function getObsState(): array {
    global $stateFile, $pidFile;
    $defaults = [
        'connection_status' => 'idle',
        'connection_message' => 'Daemon not started.',
        'mode' => 'idle',
        'show_running' => false,
        'is_black' => false,
        'is_clear' => false,
        'cur_slide_no' => 0,
        'total_slides' => 0,
        'alert_text' => '',
        'item' => [
            'title1' => '',
            'author' => '',
            'slides' => [],
        ],
        'active_slide' => [
            'slide_no' => 0,
            'section' => '',
            'region1' => '',
            'region2' => '',
        ],
        'next_slide' => null,
        'updated_at_ms' => 0,
    ];
    if (file_exists($stateFile)) {
        $d = json_decode((string)@file_get_contents($stateFile), true);
        if (is_array($d)) {
            $defaults = array_merge($defaults, $d);
        }
    }
    $defaults['daemon_running'] = file_exists($pidFile) && trim((string)@file_get_contents($pidFile)) !== '';
    return $defaults;
}

function saveObsState(array $patch): array {
    global $stateFile;
    $cur = getObsState();
    $merged = array_merge($cur, $patch, [
        'updated_at_ms' => (int)round(microtime(true) * 1000),
    ]);
    $tmp = $stateFile . '.tmp';
    file_put_contents($tmp, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    @rename($tmp, $stateFile);
    return $merged;
}

switch ($action) {
    case 'state': {
        echo json_encode([
            'ok' => true,
            'config' => getObsConfig(),
            'state' => getObsState(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;
    }

    case 'save_config': {
        $cur = getObsConfig();
        $updated = [
            'host' => trim($req['host'] ?? $cur['host']),
            'port' => (int)($req['port'] ?? $cur['port']),
            'service_type' => (int)($req['service_type'] ?? $cur['service_type']),
            'password' => (string)($req['password'] ?? $cur['password']),
            'nickname' => trim($req['nickname'] ?? $cur['nickname']),
            'enabled' => isset($req['enabled']) ? (bool)$req['enabled'] : $cur['enabled'],
            'theme' => trim($req['theme'] ?? $cur['theme']),
            'font_size' => max(18, min(96, (int)($req['font_size'] ?? $cur['font_size']))),
            'show_title' => isset($req['show_title']) ? (bool)$req['show_title'] : $cur['show_title'],
            'show_section' => isset($req['show_section']) ? (bool)$req['show_section'] : $cur['show_section'],
            'show_region2' => isset($req['show_region2']) ? (bool)$req['show_region2'] : $cur['show_region2'],
            'position' => trim($req['position'] ?? $cur['position']),
            'bg_opacity' => max(0, min(1, (float)($req['bg_opacity'] ?? $cur['bg_opacity']))),
            'text_color' => trim($req['text_color'] ?? $cur['text_color']),
            'accent_color' => trim($req['accent_color'] ?? $cur['accent_color']),
        ];
        file_put_contents($configFile, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        // Touch state timestamp so OBS overlay picks up theme changes immediately
        saveObsState([]);
        echo json_encode(['ok' => true, 'config' => $updated, 'message' => 'OBS & Multicast settings saved!']);
        break;
    }

    case 'start_daemon': {
        // Stop any existing daemon first so it cleanly closes its TCP slot on Easyslides
        if (file_exists($pidFile)) {
            $oldPid = (int)trim((string)@file_get_contents($pidFile));
            @unlink($pidFile);
            if ($oldPid > 0) {
                usleep(1100000);
            }
        }
        $phpExe = getPhpCliExecutable();
        $script = __DIR__ . DIRECTORY_SEPARATOR . 'multicast_daemon.php';
        $cmd = sprintf('start /B "" "%s" "%s" > NUL 2>&1', $phpExe, $script);
        pclose(popen($cmd, 'r'));
        usleep(600000);
        echo json_encode([
            'ok' => true,
            'message' => 'Easyslides Multicast Listener started!',
            'state' => getObsState(),
        ]);
        break;
    }

    case 'stop_daemon': {
        if (file_exists($pidFile)) {
            @unlink($pidFile);
        }
        $st = saveObsState([
            'connection_status' => 'stopped',
            'connection_message' => 'Multicast listener stopped.',
        ]);
        echo json_encode([
            'ok' => true,
            'message' => 'Multicast Listener stopped.',
            'state' => $st,
        ]);
        break;
    }

    case 'push_slide': {
        // Allows pushing a song + slide directly from the Web UI / Test Controller to OBS!
        $title1 = trim($req['title1'] ?? '');
        $author = trim($req['author'] ?? '');
        $slides = $req['slides'] ?? [];
        $slideNo = max(1, (int)($req['slide_no'] ?? 1));

        $activeSlide = $slides[$slideNo - 1] ?? [
            'slide_no' => $slideNo,
            'section' => $req['section'] ?? '',
            'region1' => $req['region1'] ?? '',
            'region2' => $req['region2'] ?? '',
        ];
        $nextSlide = $slides[$slideNo] ?? null;

        $st = saveObsState([
            'mode' => 'manual_web',
            'show_running' => true,
            'is_black' => false,
            'is_clear' => false,
            'cur_slide_no' => $slideNo,
            'total_slides' => count($slides),
            'item' => [
                'title1' => $title1,
                'author' => $author,
                'slides' => $slides,
            ],
            'active_slide' => $activeSlide,
            'next_slide' => $nextSlide,
        ]);
        echo json_encode(['ok' => true, 'state' => $st]);
        break;
    }

    case 'toggle_state': {
        $cur = getObsState();
        $patch = [];
        if (isset($req['is_black'])) $patch['is_black'] = (bool)$req['is_black'];
        if (isset($req['is_clear'])) $patch['is_clear'] = (bool)$req['is_clear'];
        if (isset($req['show_running'])) $patch['show_running'] = (bool)$req['show_running'];
        $st = saveObsState($patch);
        echo json_encode(['ok' => true, 'state' => $st]);
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Unknown action: $action"]);
}
