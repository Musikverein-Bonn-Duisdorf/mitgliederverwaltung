<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
?>
<!DOCTYPE html>
<html lang="de">
  <head>
    <?php
      if(!headers_sent()) {
          header('Content-Type: text/html; charset=utf-8');
      }
      include 'common/include.php';
    ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?php echo assetUrl('styles/w3.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('styles/w3-colors-highway.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('styles/w3-color-mvd.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('styles/custom.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('styles/fontawesome-free-6.4.2-web/css/fontawesome.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('styles/fontawesome-free-6.4.2-web/css/solid.css'); ?>">
    <?php echo renderConfigColorCss(); ?>
    <link rel="icon" href="<?php echo htmlspecialchars((string)$GLOBALS['optionsDB']['favicon'], ENT_QUOTES, 'UTF-8'); ?>" type="image/x-icon">
    <title><?php echo htmlspecialchars((string)$optionsDB['WebSiteName'], ENT_QUOTES, 'UTF-8'); ?></title>
  </head>
  <body class="<?php echo htmlspecialchars((string)$GLOBALS['optionsDB']['colorBackground'], ENT_QUOTES, 'UTF-8'); ?>">
    <div class="w3-container <?php echo htmlspecialchars((string)$optionsDB['colorTitle'], ENT_QUOTES, 'UTF-8'); ?>">
      <h1><?php echo htmlspecialchars((string)$optionsDB['WebSiteName'], ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
    <?php
if(tryMeldeSsoLoginFromRequest()) {
      ?>
    <meta http-equiv="refresh" content="0; URL='index.php'" />
    <?php
    die("<div class=\"w3-panel ".$GLOBALS['optionsDB']['colorSuccess']."\"><h2>Login erfolgreich (SSO).</h2></div>");
}
// Password login before any loggedIn()/enforce path that might touch the session.
if(isset($_POST['triggerlogin'])) {
    $r = validateUser($_POST['login'] ?? '', $_POST['password'] ?? '');
    if(!$r) {
        $msg = !empty($GLOBALS['loginDeniedNoAccess'])
            ? 'Keine Berechtigung für die Mitgliederverwaltung.'
            : 'Login fehlgeschlagen.';
      ?>
    <div class="w3-panel <?php echo $GLOBALS['optionsDB']['colorLogError']; ?>"><h2><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></h2></div>
    <?php
    }
}
enforceMitgliederverwaltungAccess();
if(!empty($GLOBALS['loginDeniedNoAccess'])) {
      ?>
    <div class="w3-panel <?php echo $GLOBALS['optionsDB']['colorLogError']; ?>"><h2>Keine Berechtigung für die Mitgliederverwaltung.</h2></div>
    <?php
}
if(isset($_SESSION['userid']) && (int)$_SESSION['userid'] > 0) {
      ?>
    <meta http-equiv="refresh" content="0; URL='index.php'" />
    <?php
    die("<div class=\"w3-panel ".$GLOBALS['optionsDB']['colorSuccess']."\"><h2>Login erfolgreich.</h2></div>");
}
      ?>
    <div class="w3-panel w3-mobile w3-center w3-col s3 l4">
    </div>
    <div class="w3-panel w3-mobile w3-center w3-border w3-col s6 l4">
      <div class="w3-panel <?php echo $GLOBALS['optionsDB']['colorTitleBar']; ?> w3-mobile">
	<h2>Login</h2>
      </div>
      <form class="w3-container" action="" method="POST">
	<label>Benutzer</label>
	<input class="w3-input w3-border <?php echo $GLOBALS['optionsDB']['colorInputBackground']; ?> w3-margin-bottom w3-mobile" type="text" name="login" autocomplete="username" />
	<label>Passwort</label>
	<input class="w3-input w3-border <?php echo $GLOBALS['optionsDB']['colorInputBackground']; ?> w3-margin-bottom w3-mobile" type="password" name="password" autocomplete="current-password" />
	<button class="w3-btn <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?> w3-border w3-mobile" type="submit" name="triggerlogin">Login</button>
	<a class="w3-btn w3-margin-top w3-margin-bottom <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?> w3-border w3-mobile" href="<?php echo htmlspecialchars((string)$optionsDB['MasterPage'], ENT_QUOTES, 'UTF-8'); ?>">Vereinshomepage</a>
      </form>
    </div>
    <div class="w3-panel w3-mobile w3-center w3-col s3 l4">
    </div>
    <?php
      include "common/footer.php";
      ?>
