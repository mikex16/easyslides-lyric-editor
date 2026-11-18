<?php
/**
 * Easyslides 5.2 Multicast TCP Client Daemon -> Real-time OBS Overlay State
 * Connects to Easyslides Multicast Server (default TCP port 9701),
 * completes the RSA-OAEP XML handshake, and streams live slide changes to obs_state.json.
 */

set_time_limit(0);
ini_set('memory_limit', '128M');

$stateFile = __DIR__ . '/obs_state.json';
$configFile = __DIR__ . '/obs_config.json';
$pidFile = __DIR__ . '/obs_daemon.pid';

file_put_contents($pidFile, (string)getmypid());

/**
 * Detect local LAN IPv4 address (Easyslides binds TcpListener to the active LAN IP)
 */
function detectLocalLanIp(): string {
    $ip = @gethostbyname((string)@gethostname());
    if (is_string($ip) && $ip !== '' && $ip !== '127.0.0.1' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $ip;
    }
    return '127.0.0.1';
}

/**
 * Load multicast connection config
 */
function loadObsConfig(): array {
    global $configFile;
    $defaults = [
        'host' => detectLocalLanIp(),
        'port' => 9701,
        'service_type' => 1, // 1 = LiveScreen, 0 = StageDisplay
        'password' => '',
        'nickname' => 'OBS-Overlay',
        'enabled' => true,
        'theme' => 'glass',
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
        $data = json_decode((string)@file_get_contents($configFile), true);
        if (is_array($data)) {
            return array_merge($defaults, $data);
        }
    }
    return $defaults;
}

/**
 * Atomically update obs_state.json
 */
function writeObsState(array $patch): void {
    global $stateFile;
    $current = [];
    if (file_exists($stateFile)) {
        $decoded = json_decode((string)@file_get_contents($stateFile), true);
        if (is_array($decoded)) {
            $current = $decoded;
        }
    }
    $merged = array_merge($current, $patch, [
        'updated_at_ms' => (int)round(microtime(true) * 1000),
        'daemon_pid' => getmypid(),
    ]);
    $tmp = $stateFile . '.tmp';
    file_put_contents($tmp, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    @rename($tmp, $stateFile);
}

/**
 * Convert .NET <RSAKeyValue><Modulus>...</Modulus><Exponent>...</Exponent></RSAKeyValue> to OpenSSL PEM Public Key
 */
function rsaXmlToPem(string $xml): string {
    if (!preg_match('/<Modulus>(.*?)<\/Modulus>/s', $xml, $mMod) || !preg_match('/<Exponent>(.*?)<\/Exponent>/s', $xml, $mExp)) {
        return '';
    }
    $modulus = base64_decode(trim($mMod[1]));
    $exponent = base64_decode(trim($mExp[1]));
    if ($modulus === false || $exponent === false || $modulus === '' || $exponent === '') {
        return '';
    }

    if (ord($modulus[0]) >= 0x80) {
        $modulus = "\x00" . $modulus;
    }
    if (ord($exponent[0]) >= 0x80) {
        $exponent = "\x00" . $exponent;
    }

    $encodeLen = function (int $len): string {
        if ($len < 0x80) return chr($len);
        $bytes = '';
        while ($len > 0) {
            $bytes = chr($len & 0xFF) . $bytes;
            $len >>= 8;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    };

    $asnInt = fn(string $b) => "\x02" . $encodeLen(strlen($b)) . $b;
    $asnSeq = fn(string $b) => "\x30" . $encodeLen(strlen($b)) . $b;

    $rsaPubKey = $asnSeq($asnInt($modulus) . $asnInt($exponent));
    $bitString = "\x03" . $encodeLen(strlen($rsaPubKey) + 1) . "\x00" . $rsaPubKey;
    $algId = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
    $spki = $asnSeq($algId . $bitString);

    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/**
 * Build an Easyslides SocketCommand XML packet
 * Must be formatted with CRLF & indentation matching .NET XmlTextWriter(Formatting.Indented)
 * so Easyslides' MoveToXMLItemElement reader does not skip sibling elements!
 */
function buildSocketCommandXml(int $command, string $clientNoOrName = '0', string $msg1 = '', string $msg2 = '', string $msg3 = '', int $remoteItemId = 0): string {
    $esc = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    return "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n"
        . "<Easyslides>\r\n"
        . "  <SocketItem>\r\n"
        . "    <Command>" . $command . "</Command>\r\n"
        . "    <ClientNo>" . $esc($clientNoOrName) . "</ClientNo>\r\n"
        . "    <ShowRunning>True</ShowRunning>\r\n"
        . "    <LiveScreenSize>0</LiveScreenSize>\r\n"
        . "    <CurSlideNo>0</CurSlideNo>\r\n"
        . "    <RemoteItemID>" . $remoteItemId . "</RemoteItemID>\r\n"
        . "    <TotalItems>0</TotalItems>\r\n"
        . "    <SlideTransition>0</SlideTransition>\r\n"
        . "    <MessageData1>" . $esc($msg1) . "</MessageData1>\r\n"
        . "    <MessageData2>" . $esc($msg2) . "</MessageData2>\r\n"
        . "    <MessageData3>" . $esc($msg3) . "</MessageData3>\r\n"
        . "  </SocketItem>\r\n"
        . "</Easyslides>";
}

/**
 * Map Easyslides numerical section code (from SlidesArray / LyricsAndNotationsArray) to human label
 */
function mapEasyslidesSectionCode(int $code, string $prevLabel = ''): string {
    if ($code < 0) {
        return $prevLabel;
    }
    if ($code >= 1 && $code <= 99) {
        return "Verse $code";
    }
    $map = [
        5011 => 'PreChorus',
        5012 => 'PreChorus 2',
        5013 => 'PreChorus 3',
        5014 => 'PreChorus 4',
        5021 => 'Chorus',
        5022 => 'Chorus 2',
        5023 => 'Chorus 3',
        5024 => 'Chorus 4',
        5031 => 'Bridge',
        5032 => 'Bridge 2',
        5033 => 'Bridge 3',
        5034 => 'Bridge 4',
        5041 => 'Intro',
        5051 => 'Tag',
        5061 => 'End',
    ];
    return $map[$code] ?? ($code > 0 ? (string)$code : $prevLabel);
}

/**
 * Parse Easyslides <LyricsAndNotationsArray> + <SlidesArray> into structured slides
 *
 * In Easyslides 5.2:
 * - <LyricsAndNotationsArray> is a list of individual lines: <"secCode","regionNo","lyricLine","chordLine","metrics">
 * - <SlidesArray> is a list of slide definitions (1-based after 0th dummy tuple):
 *   (0,0,0,-1,0,0,0,0)(secCode, reg1StartLine, reg1EndLine, reg2StartLine, reg2EndLine, ...)
 */
function parseEasyslidesItemSlides(string $rawLyricsArray, string $rawSlidesArray, string $fallbackContents = ''): array {
    $lines = [];
    if (preg_match_all('/<(".*?")>/s', $rawLyricsArray, $matches)) {
        foreach ($matches[1] as $tuple) {
            if (preg_match_all('/"(.*?)"/s', $tuple, $fields)) {
                $vals = array_map(function ($v) {
                    return str_replace(['&quot;', '&lt;', '&gt;', '&amp;'], ['"', '<', '>', '&'], $v);
                }, $fields[1]);
                $lines[] = [
                    'sec_code' => (int)($vals[0] ?? 0),
                    'region' => (int)($vals[1] ?? 1),
                    'lyric' => trim($vals[2] ?? ''),
                    'notations' => trim($vals[3] ?? ''),
                ];
            }
        }
    }

    $slides = [];
    if (!empty($lines) && preg_match_all('/\(([^)]+)\)/', $rawSlidesArray, $sMatches)) {
        $tuples = $sMatches[1];
        $lastSectionLabel = '';
        // Index 0 in SlidesArray is (0,0,0,-1,0,0,0,0); actual slides start at index 1
        for ($i = 1; $i < count($tuples); $i++) {
            $nums = array_map('intval', explode(',', $tuples[$i]));
            $secCode = $nums[0] ?? 0;
            $r1Start = $nums[1] ?? -1;
            $r1End = $nums[2] ?? -1;
            $r2Start = $nums[3] ?? -1;
            $r2End = $nums[4] ?? -1;

            $secLabel = mapEasyslidesSectionCode($secCode, $lastSectionLabel);
            if ($secLabel !== '') {
                $lastSectionLabel = $secLabel;
            }

            $r1Lines = [];
            if ($r1Start >= 0 && $r1End >= $r1Start) {
                for ($l = $r1Start; $l <= $r1End; $l++) {
                    if (isset($lines[$l]) && $lines[$l]['lyric'] !== '') {
                        $r1Lines[] = $lines[$l]['lyric'];
                    }
                }
            }

            $r2Lines = [];
            if ($r2Start >= 0 && $r2End >= $r2Start) {
                for ($l = $r2Start; $l <= $r2End; $l++) {
                    if (isset($lines[$l]) && $lines[$l]['lyric'] !== '') {
                        $r2Lines[] = $lines[$l]['lyric'];
                    }
                }
            }

            $slides[] = [
                'slide_no' => $i,
                'section' => $secLabel,
                'region1' => implode("\n", $r1Lines),
                'region2' => implode("\n", $r2Lines),
            ];
        }
    }

    // Fallback if SlidesArray was not present: group lines by 2 or parse <Contents>
    if (empty($slides) && !empty($lines)) {
        $chunks = array_chunk($lines, 2);
        foreach ($chunks as $idx => $chunk) {
            $slides[] = [
                'slide_no' => $idx + 1,
                'section' => mapEasyslidesSectionCode($chunk[0]['sec_code'] ?? 0),
                'region1' => implode("\n", array_column($chunk, 'lyric')),
                'region2' => '',
            ];
        }
    }

    if (empty($slides) && trim($fallbackContents) !== '') {
        $rawLines = preg_split('/\r\n|\r|\n/', trim($fallbackContents));
        $curSection = '';
        $curBlock = [];
        $slideNo = 1;
        $flush = function () use (&$slides, &$curSection, &$curBlock, &$slideNo) {
            $text = trim(implode("\n", $curBlock));
            if ($text !== '') {
                $slides[] = [
                    'slide_no' => $slideNo++,
                    'section' => $curSection,
                    'region1' => $text,
                    'region2' => '',
                ];
            }
            $curBlock = [];
        };
        foreach ($rawLines as $line) {
            $t = trim($line);
            if (preg_match('/^\[([^\]]+)\]$/', $t, $m)) {
                $flush();
                $sec = strtolower($m[1]);
                $curSection = is_numeric($sec) ? "Verse $sec" : ucfirst($sec);
            } elseif ($t === '') {
                $flush();
            } else {
                $curBlock[] = $t;
            }
        }
        $flush();
    }

    return $slides;
}

writeObsState([
    'connection_status' => 'starting',
    'connection_message' => 'Starting Easyslides Multicast Bridge...',
]);

while (true) {
    clearstatcache();
    if (!file_exists($pidFile) || trim((string)@file_get_contents($pidFile)) !== (string)getmypid()) {
        exit(0);
    }

    $cfg = loadObsConfig();
    if (empty($cfg['enabled'])) {
        writeObsState([
            'connection_status' => 'disabled',
            'connection_message' => 'Multicast listener paused.',
        ]);
        sleep(2);
        continue;
    }

    $host = trim($cfg['host'] ?? '');
    if ($host === '') {
        $host = detectLocalLanIp();
    }
    $port = (int)($cfg['port'] ?? 9701);
    $serviceType = (int)($cfg['service_type'] ?? 1);
    $serviceName = ($serviceType === 0) ? 'StageDisplay' : 'LiveScreen';
    $password = trim($cfg['password'] ?? '');
    $nickname = trim($cfg['nickname'] ?? 'OBS-Overlay');

    writeObsState([
        'connection_status' => 'connecting',
        'connection_message' => "Connecting to Easyslides Multicast at $host:$port ($serviceName)...",
        'host' => $host,
        'port' => $port,
    ]);

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 3.5);

    // If 127.0.0.1 failed because Easyslides binds exclusively to the LAN IP, automatically try the detected LAN IP!
    if (!$socket && $host === '127.0.0.1') {
        $lanIp = detectLocalLanIp();
        if ($lanIp !== '127.0.0.1') {
            $socket = @stream_socket_client("tcp://$lanIp:$port", $errno, $errstr, 3.5);
            if ($socket) {
                $host = $lanIp;
            }
        }
    }

    if (!$socket) {
        writeObsState([
            'connection_status' => 'disconnected',
            'connection_message' => "Waiting for Easyslides Multicast on $host:$port ($errstr)...",
        ]);
        sleep(2);
        continue;
    }

    stream_set_blocking($socket, false);
    $buffer = '';
    $clientSocketId = '0';
    $currentItem = [
        'remote_item_id' => 0,
        'title1' => '',
        'title2' => '',
        'author' => '',
        'copyright' => '',
        'songkey' => '',
        'slides' => [],
    ];
    $curSlideNo = 1;
    $showRunning = true;
    $isBlack = false;
    $isClear = false;

    while (is_resource($socket) && !feof($socket)) {
        clearstatcache();
        if (!file_exists($pidFile) || trim((string)@file_get_contents($pidFile)) !== (string)getmypid()) {
            @fwrite($socket, buildSocketCommandXml(105, $clientSocketId));
            fclose($socket);
            exit(0);
        }

        $read = [$socket];
        $write = null;
        $except = null;
        $changed = @stream_select($read, $write, $except, 1, 0);

        if ($changed === false) {
            break;
        }

        if ($changed > 0) {
            $chunk = @fread($socket, 65536);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buffer .= $chunk;

            // Process all complete <?xml ... </Easyslides> frames in buffer
            while (($endPos = strpos($buffer, '</Easyslides>')) !== false) {
                $startPos = strpos($buffer, '<?xml');
                if ($startPos === false || $startPos > $endPos) {
                    $buffer = substr($buffer, $endPos + 13);
                    continue;
                }
                $xmlPacket = substr($buffer, $startPos, ($endPos + 13) - $startPos);
                $buffer = substr($buffer, $endPos + 13);

                $xml = @simplexml_load_string($xmlPacket);
                if (!$xml || !isset($xml->SocketItem)) {
                    continue;
                }

                $si = $xml->SocketItem;
                $cmd = (int)$si->Command;
                $clientSocketId = (string)$si->ClientNo;
                $pktShowRunning = strtolower((string)$si->ShowRunning) === 'true';
                $pktSlideNo = (int)$si->CurSlideNo;
                $pktRemoteItemId = (int)$si->RemoteItemID;
                $msg1 = (string)$si->MessageData1;
                $msg2 = (string)$si->MessageData2;

                switch ($cmd) {
                    case 11: // ToClient_ServerRequestWhichServiceRequired
                        // Easyslides expects the enum name string: "LiveScreen" or "StageDisplay"
                        $reply = buildSocketCommandXml(12, $clientSocketId, $serviceName);
                        @fwrite($socket, $reply);
                        break;

                    case 13: // ToClient_ServerRequestConnectPassword (msg2 = Server Public Key XML)
                        $serverPubKeyXml = trim($msg2) !== '' ? trim($msg2) : trim($msg1);
                        $pem = rsaXmlToPem($serverPubKeyXml);
                        $encB64 = '';
                        if ($pem !== '') {
                            @openssl_public_encrypt($password, $encryptedBytes, $pem, OPENSSL_PKCS1_OAEP_PADDING);
                            $encB64 = base64_encode($encryptedBytes ?: '');
                        }
                        $authMsg1 = $nickname . "\n" . $encB64;
                        // Pass valid 1024-bit <RSAKeyValue> XML in MessageData2
                        $reply = buildSocketCommandXml(14, $clientSocketId, $authMsg1, $serverPubKeyXml);
                        @fwrite($socket, $reply);
                        break;

                    case 15: // ToClient_ServerRefusedConnection
                        writeObsState([
                            'connection_status' => 'refused',
                            'connection_message' => 'Easyslides refused connection: ' . trim($msg1),
                        ]);
                        break 2;

                    case 16: // ToClient_ServerAcceptsConnection
                        $assignedName = trim(explode("\n", $msg1)[0] ?? $nickname);
                        writeObsState([
                            'connection_status' => 'connected',
                            'connection_message' => "Connected to Easyslides ($host:$port) as $assignedName",
                        ]);
                        // Request initial item state (Command 35 = RefreshCurrentItem, Command 18 = RemoteScreenConnected)
                        $initCmd = ($serviceType === 0) ? 18 : 35;
                        $reply = buildSocketCommandXml($initCmd, $clientSocketId);
                        @fwrite($socket, $reply);
                        break;

                    case 31: // ToClient_ItemChanged
                        $showRunning = true;
                        $curSlideNo = max(1, $pktSlideNo);
                        if (isset($xml->Item)) {
                            $it = $xml->Item;
                            $contents = (string)$it->Contents;
                            $rawLyricsArr = (string)$it->LyricsAndNotationsArray;
                            $rawSlidesArr = (string)$it->SlidesArray;
                            $slides = parseEasyslidesItemSlides($rawLyricsArr, $rawSlidesArr, $contents);

                            $currentItem = [
                                'remote_item_id' => $pktRemoteItemId,
                                'type' => (string)$it->Type,
                                'title1' => (string)$it->Title1,
                                'title2' => (string)$it->Title2,
                                'author' => (string)$it->Author,
                                'copyright' => (string)$it->Copyright,
                                'songkey' => (string)$it->SongKey,
                                'capo' => (string)$it->Capo,
                                'slides' => $slides,
                            ];
                        }
                        $activeSlide = $currentItem['slides'][$curSlideNo - 1] ?? [
                            'slide_no' => $curSlideNo,
                            'section' => '',
                            'region1' => '',
                            'region2' => '',
                        ];
                        $nextSlide = $currentItem['slides'][$curSlideNo] ?? null;

                        writeObsState([
                            'connection_status' => 'connected',
                            'mode' => 'multicast',
                            'show_running' => $showRunning,
                            'is_black' => $isBlack,
                            'is_clear' => $isClear,
                            'cur_slide_no' => $curSlideNo,
                            'total_slides' => count($currentItem['slides']),
                            'item' => $currentItem,
                            'active_slide' => $activeSlide,
                            'next_slide' => $nextSlide,
                        ]);

                        // Confirm receipt of RemoteItemID (Command 19)
                        $confirm = buildSocketCommandXml(19, $clientSocketId, '', '', '', $pktRemoteItemId);
                        @fwrite($socket, $confirm);
                        break;

                    case 34: // ToClient_SlideChanged
                        $showRunning = true;
                        $curSlideNo = max(1, $pktSlideNo);

                        if ($pktRemoteItemId !== $currentItem['remote_item_id'] || empty($currentItem['slides'])) {
                            $reqItem = buildSocketCommandXml(35, $clientSocketId, '', '', '', $pktRemoteItemId);
                            @fwrite($socket, $reqItem);
                        }

                        $activeSlide = $currentItem['slides'][$curSlideNo - 1] ?? [
                            'slide_no' => $curSlideNo,
                            'section' => '',
                            'region1' => '',
                            'region2' => '',
                        ];
                        $nextSlide = $currentItem['slides'][$curSlideNo] ?? null;

                        writeObsState([
                            'connection_status' => 'connected',
                            'mode' => 'multicast',
                            'show_running' => $showRunning,
                            'is_black' => $isBlack,
                            'is_clear' => $isClear,
                            'cur_slide_no' => $curSlideNo,
                            'total_slides' => count($currentItem['slides']),
                            'active_slide' => $activeSlide,
                            'next_slide' => $nextSlide,
                        ]);
                        break;

                    case 92: // ToClient_LiveBlackClearChanged ("black;clear;fade")
                        $parts = explode(';', $msg1);
                        $isBlack = ((int)($parts[0] ?? 0)) === 1;
                        $isClear = ((int)($parts[1] ?? 0)) === 1;
                        writeObsState([
                            'is_black' => $isBlack,
                            'is_clear' => $isClear,
                        ]);
                        break;

                    case 51: // ToClient_ReferenceAlertShow
                    case 53: // ToClient_GeneralAlertShow
                        $parts = explode("\n", $msg1);
                        $alertText = trim($parts[0] ?? $msg1);
                        writeObsState(['alert_text' => $alertText]);
                        break;

                    case 52: // ToClient_ReferenceAlertHide
                    case 54: // ToClient_GeneralAlertHide
                        writeObsState(['alert_text' => '']);
                        break;

                    case 17: // ToClient_ServerStopped
                    case 106: // ToClient_EndLiveScreen
                        $showRunning = false;
                        writeObsState([
                            'show_running' => false,
                            'connection_message' => $cmd === 17 ? 'Easyslides server stopped.' : 'Live show ended.',
                        ]);
                        if ($cmd === 17) {
                            break 2;
                        }
                        break;
                }
            }
        }
    }

    @fclose($socket);
    writeObsState([
        'connection_status' => 'disconnected',
        'connection_message' => "Disconnected from $host:$port. Reconnecting...",
    ]);
    sleep(2);
}
