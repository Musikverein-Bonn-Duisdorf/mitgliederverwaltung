<?php
/**
 * AJAX modal fragments for ajaxModalHost (UI-SHELL / Melde getModal parity).
 */
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
include 'common/include.php';

header('Content-Type: text/html; charset=utf-8');

if(!isset($_SESSION['userid']) || !(int)$_SESSION['userid']) {
    http_response_code(401);
    echo '<div class="w3-container w3-padding"><p>Nicht angemeldet.</p><button class="w3-button" onclick="closeModal()">Schließen</button></div>';
    exit;
}

$type = isset($_GET['type']) ? (string)$_GET['type'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0 || $type === '') {
    http_response_code(400);
    echo '<div class="w3-container w3-padding"><p>Ungültige Anfrage.</p><button class="w3-button" onclick="closeModal()">Schließen</button></div>';
    exit;
}

switch($type) {
case 'user':
case 'person':
    if(!hasPermission('perm_showUsers') && (int)$_SESSION['userid'] !== $id) {
        http_response_code(403);
        echo '<div class="w3-container w3-padding"><p>Keine Berechtigung.</p><button class="w3-button" onclick="closeModal()">Schließen</button></div>';
        exit;
    }
    $user = new IdentityUser();
    if(!$user->load_by_id($id) || (int)$user->Deleted === 1) {
        http_response_code(404);
        echo '<div class="w3-container w3-padding"><p>Person nicht gefunden.</p><button class="w3-button" onclick="closeModal()">Schließen</button></div>';
        exit;
    }
    echo mitPersonModalHtml($user);
    break;

default:
    http_response_code(400);
    echo '<div class="w3-container w3-padding"><p>Unbekannter Modal-Typ.</p><button class="w3-button" onclick="closeModal()">Schließen</button></div>';
    break;
}
