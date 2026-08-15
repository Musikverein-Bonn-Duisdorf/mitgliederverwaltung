<?php
/**
 * Legacy membership-detail URL → person hub by Melde User id.
 */
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
include 'common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$membership = new Membership();
if($id > 0 && $membership->load_by_id($id)) {
    header('Location: person.php?id='.(int)$membership->User);
    exit;
}
header('Location: members.php');
exit;
?>
