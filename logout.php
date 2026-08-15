<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
include 'common/include.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <link rel="stylesheet" href="<?php echo assetUrl('styles/w3.css'); ?>">
  <link rel="stylesheet" href="<?php echo assetUrl('styles/w3-color-mvd.css'); ?>">
  <link rel="stylesheet" href="<?php echo assetUrl('styles/custom.css'); ?>">
  <?php echo renderConfigColorCss(); ?>
  <title><?php echo h($optionsDB['WebSiteName']); ?></title>
</head>
<body class="<?php echo h($optionsDB['colorBackground']); ?> app-layout">
<div class="app-titlebar <?php echo h($optionsDB['colorTitle']); ?>">
  <h1 class="app-titlebar-name"><?php echo h($optionsDB['WebSiteName']); ?></h1>
</div>
<meta http-equiv="refresh" content="2; URL='login.php'" />
<div class="w3-panel w3-center <?php echo h($optionsDB['colorSuccess']); ?>"><h2>Logout erfolgreich.</h2></div>
<?php
session_destroy();
include 'common/footer.php';
?>
