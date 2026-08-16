<?php
/**
 * JSON: Kreditinstitut aus DE-IBAN / BLZ (Bundesbank-Datei).
 * GET blz=XXXXXXXX | iban=DE…
 */
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
include 'common/include.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=3600');

if(!isset($_SESSION['userid']) || !(int)$_SESSION['userid']) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'error' => 'auth'));
    exit;
}

$blz = isset($_GET['blz']) ? preg_replace('/\D/', '', (string)$_GET['blz']) : '';
$iban = isset($_GET['iban']) ? (string)$_GET['iban'] : '';

$hit = null;
if(strlen($blz) === 8) {
    $hit = BlzDirectory::lookupBlz($blz);
}
elseif(trim($iban) !== '') {
    $hit = BlzDirectory::lookupIban($iban);
}

if(!$hit) {
    echo json_encode(array('ok' => false, 'blz' => $blz !== '' ? $blz : BlzDirectory::blzFromIban($iban)));
    exit;
}

echo json_encode(array(
    'ok' => true,
    'blz' => $hit['blz'],
    'name' => $hit['name'],
    'bic' => $hit['bic'],
));
