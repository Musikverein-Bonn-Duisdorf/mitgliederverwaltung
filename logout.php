<?php
session_start();
include 'common/include.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="styles/w3.css">
  <title><?php echo h($optionsDB['WebSiteName']); ?></title>
</head>
<body class="<?php echo h($optionsDB['colorBackground']); ?>">
<div class="w3-container <?php echo h($optionsDB['colorTitle']); ?>">
  <h1><?php echo h($optionsDB['WebSiteName']); ?></h1>
</div>
<meta http-equiv="refresh" content="2; URL='login.php'" />
<div class="w3-panel w3-center <?php echo h($optionsDB['colorSuccess']); ?>"><h2>Logout erfolgreich.</h2></div>
<?php
session_destroy();
include 'common/footer.php';
?>
