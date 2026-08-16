<?php
/**
 * Download a person document from the local vault.
 * GET id=<documentId>
 */
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
include 'common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']);

if(!loggedIn()) {
    header('Location: login.php');
    exit;
}
requirePermission('perm_showUsers');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$doc = new Document();
if($id < 1 || !$doc->load_by_id($id)) {
    http_response_code(404);
    echo 'Dokument nicht gefunden.';
    exit;
}

$path = $doc->absolutePath();
if($path === null) {
    http_response_code(404);
    echo 'Datei nicht gefunden.';
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

$downloadName = preg_replace('/[^\w.\-]+/u', '_', $doc->DocType.'-'.(int)$doc->Index.'.'.$ext);
header('Content-Type: '.$mime);
header('Content-Length: '.(string)filesize($path));
header('Content-Disposition: inline; filename="'.$downloadName.'"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
