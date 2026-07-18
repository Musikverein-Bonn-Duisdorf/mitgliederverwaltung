<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="styles/w3.css">
  <link rel="stylesheet" href="styles/w3-colors-highway.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" crossorigin="anonymous">
  <?php include __DIR__.'/include.php'; ?>
  <link rel="icon" href="<?php echo h($optionsDB['favicon']); ?>" type="image/x-icon">
  <?php
  if(!loggedIn()) {
      echo '<meta http-equiv="refresh" content="2; URL=\'login.php\'" />';
      die('<div class="w3-panel '.$optionsDB['colorLogWarning'].'"><h2>Nicht eingeloggt...</h2></div>');
  }
  ?>
  <title><?php echo h($optionsDB['WebSiteName']); ?></title>
</head>
<body class="<?php echo h($optionsDB['colorBackground']); ?>">
<?php include __DIR__.'/nav.php'; ?>
