<?php
include __DIR__.'/config.php';
if(isset($GLOBALS['conn']) && $GLOBALS['conn']) {
    mysqli_set_charset($GLOBALS['conn'], 'utf8mb4');
    @mysqli_query($GLOBALS['conn'], 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
}
include __DIR__.'/../config/ConfigDefaults.php';
include __DIR__.'/../config/SchemaVersion.php';
include __DIR__.'/../libs/helpers.php';
include __DIR__.'/../libs/SQLtable.php';
include __DIR__.'/../libs/SchemaManager.php';
include __DIR__.'/../libs/IdentityUser.php';
include __DIR__.'/../libs/Membership.php';
include __DIR__.'/../libs/MembershipPeriod.php';
include __DIR__.'/../libs/SepaMandate.php';
include __DIR__.'/../libs/Document.php';
$optionsDB = loadconfig();
global $optionsDB;
include __DIR__.'/version.php';
?>
