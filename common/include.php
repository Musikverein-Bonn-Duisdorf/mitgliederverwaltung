<?php
include __DIR__.'/config.php';
if(isset($GLOBALS['conn']) && $GLOBALS['conn']) {
    mysqli_set_charset($GLOBALS['conn'], 'utf8mb4');
    @mysqli_query($GLOBALS['conn'], 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
}
include __DIR__.'/../config/ConfigDefaults.php';
include __DIR__.'/../config/SchemaVersion.php';
include __DIR__.'/../libs/helpers.php';
include __DIR__.'/../libs/uiShell.php';
include __DIR__.'/../libs/colorschemes.php';
include __DIR__.'/../libs/git.php';
include __DIR__.'/../libs/SQLtable.php';
include __DIR__.'/../libs/SchemaManager.php';
include __DIR__.'/../libs/IdentityUser.php';
include __DIR__.'/../libs/Membership.php';
include __DIR__.'/../libs/MembershipPeriod.php';
include __DIR__.'/../libs/SepaMandate.php';
include __DIR__.'/../libs/Document.php';
include __DIR__.'/../libs/ssoTicket.php';
include __DIR__.'/../libs/log.php';
include __DIR__.'/../libs/listChunk.php';
$optionsDB = loadconfig();
global $optionsDB;
include __DIR__.'/version.php';

// Fresh install: no mit_* schema yet — send browsers to install.php.
if(php_sapi_name() !== 'cli') {
    $script = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
    if($script !== 'install.php') {
        try {
            $freshCheck = new SchemaManager();
            if($freshCheck->isFreshInstall()) {
                header('Location: install.php');
                exit;
            }
        }
        catch(Throwable $e) {
            // SchemaManager/DBconfig unreadable — leave page to surface error.
        }
    }
}
?>
