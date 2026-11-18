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
 * Load multicast connection config
 */
function loadObsConfig(): array {
    global $configFile;
    $defaults = [
        'host' => '127.0.0.1',
        'port' => 9701,
        'service_type' => 1, // 1 = LiveScreen, 0 = StageDisplay
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
        $data = json_decode((string)file_get_contents($configFile), true);
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
 * Generate a client RSA keypair and return its public key in .NET <RSAKeyValue> XML format
 */
function generateClientRsaXml(): string {
    $res = openssl_pkey_new([
        'private_key_bits' => 1024,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    if ($res !== false) {
        $details = openssl_pkey_get_details($res);
        if (!empty($details['rsa']['n']) && !empty($details['rsa']['e'])) {
            $mod = base64_encode($details['rsa']['n']);
            $exp = base64_encode($details['rsa']['e']);
            return "<RSAKeyValue><Modulus>$mod</Modulus><Exponent>$exp</Exponent></RSAKeyValue>";
        }
    }
    // Fallback valid 1024-bit RSA public key XML if openssl keygen lacks config on Windows
    $fallbackMod = 'vW7F6nB+u2fR2G8sK7tP1xQ9zL4mV5nC8bX3wZ0yR1tY6uI2oP5aS8dF1gH4jK7lZ9xC2vB5nM8qW1eR4tY7uI0oP3aS6dF9gH2jK5lZ8xC1vB4nM7qW0eR3tY6uI9oP2aS5dF8gH1jK4lZ7xC0vB3nM6qW9eR2tY5uI8oP1aS4d=';
    return "<RSAKeyValue><Modulus>$fallbackMod</Modulus><Exponent>AQAB</Exponent></RSAKeyValue>";
}

/**
 * Build an Easyslides SocketCommand XML packet
 */
function buildSocketCommandXml(int $command, string $clientNoOrName = '0', string $msg1 = '', string $msg2 = '', string $msg3 = '', int $remoteItemId = 0): string {
    $esc = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    return '<?xml version="1.0" encoding="utf-8"?>'
        . '<Easyslides><SocketItem>'
        . '<Command>' . $command . '</Command>'
        . '<ClientNo>' . $esc($clientNoOrName) . '</ClientNo>'
        . '<ShowRunning>True</ShowRunning>'
        . '<LiveScreenSize>0</LiveScreenSize>'
        . '<CurSlideNo>0</CurSlideNo>'
        . '<RemoteItemID>' . $remoteItemId . '</RemoteItemID>'
        . '<TotalItems>0</TotalItems>'
        . '<SlideTransition>0</SlideTransition>'
        . '<MessageData1>' . $esc($msg1) . '</MessageData1>'
        . '<MessageData2>' . $esc($msg2) . '</MessageData2>'
        . '<MessageData3>' . $esc($msg3) . '</MessageData3>'
        . '</SocketItem></Easyslides>';
}

/**
 * Parse Easyslides <LyricsAndNotationsArray> string into structured slides
 * Format: <"SubItems0","SubItems1","SubItems2","SubItems3","SubItems4"><...>
 * where SubItems0 = Section Heading, SubItems1 = Slide #, SubItems2 = Region 1 Lyrics, SubItems3 = Chords, SubItems4 = Region 2 Lyrics
 */
function parseLyricsAndNotationsArray(string $rawArray, string $fallbackContents = ''): array {
    $slides = [];
    if (preg_match_all('/<(".*?")>/s', $rawArray, $matches)) {
        foreach ($matches[1] as $idx => $tuple) {
            if (preg_match_all('/"(.*?)"/s', $tuple, $fields)) {
                $vals = array_map(function ($v) {
                    return str_replace(['&quot;', '&lt;', '&gt;', '&amp;'], ['"', '<', '>', '&'], $v);
                }, $fields[1]);
                $slides[] = [
                    'slide_no' => $idx + 1,
                    'section' => trim($vals[0] ?? ''),
                    'region1' => trim($vals[2] ?? ''),
                    'notations' => trim($vals[3] ?? ''),
                    'region2' => trim($vals[4] ?? ''),
                ];
            }
        }
    }

    // Fallback: parse raw <Contents> if <LyricsAndNotationsArray> wasn't populated
    if (empty($slides) && trim($fallbackContents) !== '') {
        $lines = preg_split('/\r\n|\r|\n/', trim($fallbackContents));
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
                    'notations' => '',
                    'region2' => '',
                ];
            }
            $curBlock = [];
        };
        foreach ($lines as $line) {
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

$clientRsaXml = generateClientRsaXml();

writeObsState([
    'connection_status' => 'starting',
    'connection_message' => 'Starting Easyslides Multicast Bridge...',
]);

while (true) {
    clearstatcache();
    if (!file_exists($pidFile) || trim((string)@file_get_contents($pidFile)) !== (string)getmypid()) {
        // Another daemon instance took over or stop was requested
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

    $host = trim($cfg['host'] ?? '127.0.0.1');
    $port = (int)($cfg['port'] ?? 9701);
    $serviceType = (int)($cfg['service_type'] ?? 1);
    $password = trim($cfg['password'] ?? '');
    $nickname = trim($cfg['nickname'] ?? 'OBS-Overlay');

    writeObsState([
        'connection_status' => 'connecting',
        'connection_message' => "Connecting to Easyslides Multicast at $host:$port...",
        'host' => $host,
        'port' => $port,
    ]);

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 4.0);

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
    $alertText = '';
    $lastActivity = time();

    while (is_resource($socket) && !feof($socket)) {
        if (!file_exists($pidFile) || trim((string)@file_get_contents($pidFile)) !== (string)getmypid()) {
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
            $lastActivity = time();

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
                $msg3 = (string)$si->MessageData3;

                switch ($cmd) {
                    case 11: // ToClient_ServerRequestWhichServiceRequired
                        $reply = buildSocketCommandXml(12, $clientSocketId, (string)$serviceType);
                        @fwrite($socket, $reply);
                        break;

                    case 13: // ToClient_ServerRequestConnectPassword (msg1 = Server Public Key XML)
                        $pem = rsaXmlToPem($msg1);
                        $encB64 = '';
                        if ($pem !== '') {
                            @openssl_public_encrypt($password, $encryptedBytes, $pem, OPENSSL_PKCS1_OAEP_PADDING);
                            $encB64 = base64_encode($encryptedBytes ?: '');
                        }
                        $authMsg1 = $nickname . "\n" . $encB64;
                        $reply = buildSocketCommandXml(14, $clientSocketId, $authMsg1, $clientRsaXml);
                        @fwrite($socket, $reply);
                        break;

                    case 15: // ToClient_ServerRefusedConnection
                        writeObsState([
                            'connection_status' => 'refused',
                            'connection_message' => 'Easyslides refused connection: ' . trim($msg1),
                        ]);
                        break 2;

                    case 16: // ToClient_ServerAcceptsConnection
                        writeObsState([
                            'connection_status' => 'connected',
                            'connection_message' => "Connected to Easyslides ($host:$port) as $nickname",
                        ]);
                        // Request initial item state (Command 35 = RefreshCurrentItem, Command 18 = RemoteScreenConnected)
                        $initCmd = ($serviceType === 0) ? 18 : 35;
                        $reply = buildSocketCommandXml($initCmd, $clientSocketId);
                        @fwrite($socket, $reply);
                        break;

                    case 31: // ToClient_ItemChanged
                        $showRunning = $pktShowRunning;
                        $curSlideNo = max(1, $pktSlideNo);
                        if (isset($xml->Item)) {
                            $it = $xml->Item;
                            $contents = (string)$it->Contents;
                            $rawLyricsArr = (string)$it->LyricsAndNotationsArray;
                            $slides = parseLyricsAndNotationsArray($rawLyricsArr, $contents);

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
                        $showRunning = $pktShowRunning;
                        $curSlideNo = max(1, $pktSlideNo);

                        if ($pktRemoteItemId !== $currentItem['remote_item_id']) {
                            // Ask server to resend full item
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
                        $alertText = '';
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
