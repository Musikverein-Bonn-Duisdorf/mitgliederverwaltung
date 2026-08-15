<?php
/**
 * Upload / download / delete scanned Beitrittserklärung.
 */
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
include 'common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']);

if(!loggedIn()) {
    header('Location: login.php');
    exit;
}

$appId = 0;
if(isset($_POST['id'])) {
    $appId = (int)$_POST['id'];
}
elseif(isset($_GET['id'])) {
    $appId = (int)$_GET['id'];
}

$app = new MembershipApplication();
if($appId < 1 || !$app->load_by_id($appId)) {
    http_response_code(404);
    echo 'Antrag nicht gefunden.';
    exit;
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if($isPost) {
    requirePermission('perm_editUsers');
    if($app->Status === 'applied') {
        http_response_code(403);
        echo 'Antrag bereits angewendet.';
        exit;
    }
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    if($action === 'deleteScan') {
        if(!MembershipForm::deleteScan($app)) {
            http_response_code(500);
            echo 'Scan konnte nicht gelöscht werden.';
            exit;
        }
        header('Location: membership-form.php?id='.$appId);
        exit;
    }
    if($action !== 'upload' || !isset($_FILES['scan'])) {
        http_response_code(400);
        echo 'Upload fehlgeschlagen.';
        exit;
    }
    // Accept form field snapshot from the same POST (save + upload in one step).
    if(isset($_POST['DesiredEntryDate']) || isset($_POST['DesiredType']) || isset($_POST['PaymentMethod'])) {
        MembershipForm::applyPostFields($app, $_POST);
    }
    $name = MembershipForm::storeUpload($appId, $_FILES['scan']);
    if($name === false) {
        http_response_code(400);
        echo 'Datei konnte nicht gespeichert werden.';
        exit;
    }
    $app->ScanFile = $name;
    if($app->Status === 'draft') {
        $app->Status = 'ready';
    }
    $app->save();

    $entry = trim((string)$app->DesiredEntryDate);
    if($entry === '') {
        $entry = date('Y-m-d');
        $app->DesiredEntryDate = $entry;
        $app->save();
    }
    if($app->Status !== 'applied' && $app->apply($entry)) {
        $sepaNote = ($app->PaymentMethod === 'sepa' && trim((string)$app->Iban) !== '')
            ? ' SEPA-Mandat angelegt.'
            : '';
        $_SESSION['personFlash'] = 'Scan gespeichert — Beitritt angewendet (Eintritt '.germanDate($entry)
            .', '.(string)$app->DesiredType.').'.$sepaNote;
        header('Location: person.php?id='.(int)$app->User);
        exit;
    }
    $_SESSION['membershipFormFlash'] = 'Scan gespeichert, Beitritt konnte nicht automatisch angewendet werden — bitte prüfen und anwenden.';
    header('Location: membership-form.php?id='.$appId);
    exit;
}

requirePermission('perm_showUsers');
$path = MembershipForm::resolveStoredFile($appId, (string)$app->ScanFile);
if($path === null) {
    http_response_code(404);
    echo 'Scan nicht gefunden.';
    exit;
}

$mime = 'application/octet-stream';
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$map = array(
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
);
if(isset($map[$ext])) {
    $mime = $map[$ext];
}

header('Content-Type: '.$mime);
header('Content-Length: '.(string)filesize($path));
header('Content-Disposition: inline; filename="'.basename($path).'"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
?>
