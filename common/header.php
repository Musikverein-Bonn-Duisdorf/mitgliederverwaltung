<!DOCTYPE html>
<html lang="de">
  <head>
      <?php
          if(!headers_sent()) {
              header('Content-Type: text/html; charset=utf-8');
          }
      ?>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
      <?php
          include_once __DIR__.'/include.php';
      ?>
      <link rel="stylesheet" href="<?php echo assetUrl('styles/w3.css'); ?>">
      <link rel="stylesheet" href="<?php echo assetUrl('styles/w3-colors-highway.css'); ?>">
      <link rel="stylesheet" href="<?php echo assetUrl('styles/w3-color-mvd.css'); ?>">
      <link rel="stylesheet" href="<?php echo assetUrl('styles/custom.css'); ?>">
      <link rel="stylesheet" href="<?php echo assetUrl('styles/fontawesome-free-6.4.2-web/css/fontawesome.css'); ?>">
      <link rel="stylesheet" href="<?php echo assetUrl('styles/fontawesome-free-6.4.2-web/css/brands.css'); ?>">
      <link rel="stylesheet" href="<?php echo assetUrl('styles/fontawesome-free-6.4.2-web/css/solid.css'); ?>">
      <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
      <?php echo renderConfigColorCss(); ?>
      <?php echo renderPermissionGroupColorCss(); ?>
      <link rel="icon" href="<?php echo htmlspecialchars((string)$GLOBALS['optionsDB']['favicon'], ENT_QUOTES, 'UTF-8'); ?>" type="image/x-icon">
      <?php
        mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));
        tryMeldeSsoLoginFromRequest();
        if(!loggedIn()) {
              ?>
              <meta http-equiv="refresh" content="2; URL='login.php'" />
              <?php
              die("<div class=\"w3-panel ".$optionsDB['colorLogWarning']."\"><h2>Nicht eingeloggt...</h2></div>");
          }
      ?>
      <title><?php
        $docTitle = isset($optionsDB['WebSiteName']) ? trim((string)$optionsDB['WebSiteName']) : '';
        echo htmlspecialchars($docTitle !== '' ? $docTitle : 'Mitgliederverwaltung', ENT_QUOTES, 'UTF-8');
      ?></title>
  </head>
  <body class="<?php echo htmlspecialchars((string)$GLOBALS['optionsDB']['colorBackground'], ENT_QUOTES, 'UTF-8'); ?> app-layout">
<?php
include __DIR__.'/nav.php';
$GLOBALS['mlHeaderRendered'] = true;
?>
