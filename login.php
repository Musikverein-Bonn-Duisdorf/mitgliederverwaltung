<?php
session_start();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="styles/w3.css">
  <link rel="stylesheet" href="styles/w3-colors-highway.css">
  <?php include 'common/include.php'; ?>
  <title><?php echo h($optionsDB['WebSiteName']); ?> — Login</title>
</head>
<body class="<?php echo h($optionsDB['colorBackground']); ?>">
<div class="w3-container <?php echo h($optionsDB['colorTitle']); ?>">
  <h1><?php echo h($optionsDB['WebSiteName']); ?></h1>
</div>
<?php
if(isset($_POST['triggerlogin'])) {
    if(!validateUser($_POST['login'] ?? '', $_POST['password'] ?? '')) {
        echo '<div class="w3-panel '.$optionsDB['colorLogError'].'"><h2>Login fehlgeschlagen.</h2></div>';
    }
}
if(loggedIn()) {
    echo '<meta http-equiv="refresh" content="0; URL=\'index.php\'" />';
    die('<div class="w3-panel '.$optionsDB['colorSuccess'].'"><h2>Login erfolgreich.</h2></div>');
}
?>
<div class="w3-panel w3-mobile w3-center w3-col s3 l4"></div>
<div class="w3-panel w3-mobile w3-center w3-border w3-col s6 l4">
  <div class="w3-panel <?php echo h($optionsDB['colorTitleBar']); ?>">
    <h2>Login</h2>
    <p class="w3-small">Meldeliste-Benutzer (<?php echo h(identityPrefix()); ?>User)</p>
  </div>
  <form class="w3-container" action="" method="POST">
    <label>Benutzer</label>
    <input class="w3-input w3-border w3-margin-bottom" type="text" name="login" autocomplete="username" />
    <label>Passwort</label>
    <input class="w3-input w3-border w3-margin-bottom" type="password" name="password" autocomplete="current-password" />
    <button class="w3-btn <?php echo h($optionsDB['colorBtnSubmit']); ?>" type="submit" name="triggerlogin">Login</button>
  </form>
</div>
<?php include 'common/footer.php'; ?>
