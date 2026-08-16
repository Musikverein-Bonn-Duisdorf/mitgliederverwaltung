<?php
/**
 * Download Deutsche Bundesbank Bankleitzahlendatei (CSV) and rebuild lookup.
 *
 * Usage: php scripts/updateBlzData.php
 * Cron (quartalsweise, z. B. 1. März/Juni/Sept/Dez): 0 6 1 3,6,9,12 * php …/scripts/updateBlzData.php
 *
 * Quelle: https://www.bundesbank.de/…/download-bankleitzahlen-602592
 * (Link „blz-aktuell-csv“ auf der Download-Seite; URL ändert sich quartalsweise.)
 */
if(php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
$outDir = $root.'/data/blz';
$csvPath = $outDir.'/BLZ.CSV';
$lookupPath = $outDir.'/lookup.json';
$metaPath = $outDir.'/meta.json';
$downloadPage = 'https://www.bundesbank.de/de/aufgaben/unbarer-zahlungsverkehr/serviceangebot/bankleitzahlen/download-bankleitzahlen-602592';
$base = 'https://www.bundesbank.de';

if(!is_dir($outDir) && !mkdir($outDir, 0755, true)) {
    fwrite(STDERR, "Cannot create $outDir\n");
    exit(1);
}

function blzHttpGet($url) {
    if(function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'Mitgliederverwaltung-BLZ-Update/1.0',
        ));
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if($body === false || $code >= 400) {
            throw new RuntimeException("HTTP $code for $url".($err ? ": $err" : ''));
        }
        return $body;
    }
    $ctx = stream_context_create(array(
        'http' => array(
            'timeout' => 120,
            'header' => "User-Agent: Mitgliederverwaltung-BLZ-Update/1.0\r\n",
        ),
    ));
    $body = @file_get_contents($url, false, $ctx);
    if($body === false) {
        throw new RuntimeException("Failed to fetch $url");
    }
    return $body;
}

/**
 * Build BLZ → name/bic map from Bundesbank CSV (ISO-8859-1, semicolon).
 * Field order per Bundesbank Merkblatt (1-based): BLZ, Merkmal, Bezeichnung, … BIC(8), … Änderung(11), … Nachfolge(13).
 *
 * @return array{map: array<string,array{name:string,bic:string}>, count:int}
 */
function blzBuildLookup($csvPath) {
    $fh = fopen($csvPath, 'rb');
    if(!$fh) {
        throw new RuntimeException("Cannot read $csvPath");
    }
    // Skip header
    fgetcsv($fh, 0, ';');

    $toUtf = function ($s) {
        $s = trim((string)$s);
        if($s === '') {
            return '';
        }
        if(!mb_check_encoding($s, 'UTF-8')) {
            return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }
        // Latin-1 misread as UTF-8: re-encode if high bytes look latin1
        if(preg_match('/[\x80-\xff]/', $s) && !preg_match('/[\xC0-\xFF][\x80-\xBF]/', $s)) {
            return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }
        return $s;
    };

    $active = array();
    $deleted = array();
    while(($row = fgetcsv($fh, 0, ';')) !== false) {
        if(count($row) < 3) {
            continue;
        }
        $blz = preg_replace('/\D/', '', $toUtf($row[0]));
        if(strlen($blz) !== 8) {
            continue;
        }
        if($toUtf($row[1]) !== '1') {
            continue;
        }
        $chg = isset($row[10]) ? $toUtf($row[10]) : 'U';
        $name = $toUtf($row[2]);
        $bic = isset($row[7]) ? $toUtf($row[7]) : '';
        if($chg === 'D') {
            $succ = isset($row[12]) ? preg_replace('/\D/', '', $toUtf($row[12])) : '';
            if(strlen($succ) === 8 && $succ !== '00000000') {
                $deleted[$blz] = $succ;
            }
            continue;
        }
        if($name === '') {
            continue;
        }
        $active[$blz] = array('name' => $name, 'bic' => $bic);
    }
    fclose($fh);

    $map = $active;
    foreach($deleted as $old => $succ) {
        if(isset($map[$old])) {
            continue;
        }
        if(isset($active[$succ])) {
            $map[$old] = $active[$succ];
        }
    }

    ksort($map, SORT_STRING);
    return array('map' => $map, 'count' => count($map));
}

try {
    echo "Fetching download page…\n";
    $html = blzHttpGet($downloadPage);
    if(!preg_match('#href="(/resource/blob/[^"]*blz-aktuell-csv-data\.csv)"#', $html, $m)
        && !preg_match('#href="(/resource/blob/[^"]*blz-aktuell-csv-zip-data\.zip)"#', $html, $m)) {
        throw new RuntimeException('No blz-aktuell-csv link on download page');
    }
    $rel = $m[1];
    $url = (strpos($rel, 'http') === 0) ? $rel : $base.$rel;
    echo "Downloading $url\n";
    $payload = blzHttpGet($url);

    $tmp = $outDir.'/.BLZ.download';
    if(substr($rel, -4) === '.zip' || (strlen($payload) >= 2 && $payload[0] === 'P' && $payload[1] === 'K')) {
        file_put_contents($tmp.'.zip', $payload);
        $zip = new ZipArchive();
        if($zip->open($tmp.'.zip') !== true) {
            throw new RuntimeException('Cannot open ZIP');
        }
        $found = null;
        for($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if(preg_match('/BLZ\.CSV$/i', $name) || preg_match('/\.csv$/i', $name)) {
                $found = $name;
                break;
            }
        }
        if($found === null) {
            $zip->close();
            throw new RuntimeException('No CSV in ZIP');
        }
        $csvBody = $zip->getFromName($found);
        $zip->close();
        @unlink($tmp.'.zip');
        if($csvBody === false) {
            throw new RuntimeException('Cannot extract CSV');
        }
        file_put_contents($csvPath, $csvBody);
    }
    else {
        file_put_contents($csvPath, $payload);
    }

    // Normalize to ISO-8859-1 CSV as published (keep as-is if already)
    $built = blzBuildLookup($csvPath);
    $mapUtf = $built['map'];
    $json = json_encode($mapUtf, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if($json === false || file_put_contents($lookupPath, $json."\n") === false) {
        throw new RuntimeException("Cannot write $lookupPath: ".json_last_error_msg());
    }

    $meta = array(
        'source_page' => $downloadPage,
        'source_url' => $url,
        'updated_at' => date('c'),
        'entry_count' => count($mapUtf),
        'csv_bytes' => filesize($csvPath),
        'publisher' => 'Deutsche Bundesbank – Bankleitzahlendatei',
    );
    file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n");

    echo "OK: {$meta['entry_count']} BLZ → $lookupPath\n";
    echo "CSV: $csvPath (".$meta['csv_bytes']." bytes)\n";
    exit(0);
}
catch(Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
