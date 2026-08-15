<?php
/**
 * First-time database setup. Open without login while schema is fresh;
 * otherwise requires logged-in Meldeliste admin.
 */
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();

$configFile = __DIR__.'/common/config.php';
if(!is_readable($configFile)) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Install</title>';
    echo '<link rel="stylesheet" href="styles/w3.css"><link rel="stylesheet" href="styles/custom.css"></head><body class="w3-container">';
    echo '<div class="w3-panel w3-red"><h2>Konfiguration fehlt</h2>';
    echo '<p>Bitte <code>common/config_sample.php</code> nach <code>common/config.php</code> kopieren.</p></div></body></html>';
    exit;
}

require_once $configFile;
require_once __DIR__.'/config/ConfigDefaults.php';
require_once __DIR__.'/libs/helpers.php';
require_once __DIR__.'/libs/uiShell.php';
require_once __DIR__.'/libs/SQLtable.php';
require_once __DIR__.'/config/SchemaVersion.php';
require_once __DIR__.'/libs/SchemaManager.php';
require_once __DIR__.'/libs/IdentityUser.php';
$optionsDB = loadconfig();
global $optionsDB;
require_once __DIR__.'/common/version.php';

$manager = new SchemaManager();
$isFresh = $manager->isFreshInstall();
$isAdminSession = loggedIn() && !empty($_SESSION['admin']);

if(!$isFresh && !$isAdminSession) {
    header('Location: login.php');
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$reportManager = null;
$allowedActions = array('check', 'create', 'repair');

if(in_array($action, $allowedActions, true)) {
    if($action === 'check') {
        $manager->check();
    }
    elseif($action === 'create') {
        $manager->create();
    }
    else {
        $manager->repair();
    }
    $reportManager = $manager;
    $isFresh = $manager->isFreshInstall();
}

function renderInstallReport($report) {
    echo '<table class="w3-table w3-bordered w3-white"><tr><th>Status</th><th>Ziel</th><th>Meldung</th></tr>';
    foreach($report as $entry) {
        echo '<tr><td>'.htmlspecialchars($entry['status']).'</td><td>'
            .htmlspecialchars($entry['target']).'</td><td>'
            .htmlspecialchars($entry['message']).'</td></tr>';
    }
    echo '</table>';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Mitgliederverwaltung — Installation</title>
  <link rel="stylesheet" href="<?php echo assetUrl('styles/w3.css'); ?>">
  <link rel="stylesheet" href="<?php echo assetUrl('styles/w3-color-mvd.css'); ?>">
  <link rel="stylesheet" href="<?php echo assetUrl('styles/custom.css'); ?>">
  <link rel="stylesheet" href="<?php echo assetUrl('styles/fontawesome-free-6.4.2-web/css/fontawesome.css'); ?>">
  <link rel="stylesheet" href="<?php echo assetUrl('styles/fontawesome-free-6.4.2-web/css/solid.css'); ?>">
  <?php echo renderConfigColorCss(); ?>
</head>
<body class="<?php echo h($optionsDB['colorBackground']); ?> app-layout">
<div class="app-titlebar <?php echo h($optionsDB['colorTitle']); ?>">
  <h1 class="app-titlebar-name">Datenbank-Installation</h1>
</div>
<div class="w3-container w3-margin profile-shell">
<?php if($isFresh) { ?>
  <div class="w3-panel w3-pale-blue">
    <p>Neue Instanz — <code>mit_</code>-Tabellen werden aus <code>config/DBconfig.json</code> angelegt.</p>
    <p>Login erfolgt über Meldeliste-Identity (<code><?php echo htmlspecialchars(identityPrefix()); ?>User</code>).</p>
  </div>
<?php } else { ?>
  <div class="w3-panel w3-pale-yellow">
    <p>Schema vorhanden. Als Admin prüfen oder reparieren.</p>
  </div>
<?php } ?>

  <form method="post" class="w3-margin-bottom">
    <button class="w3-button <?php echo h($optionsDB['colorBtnSubmit']); ?>" type="submit" name="action" value="check">Nur prüfen</button>
<?php if($isFresh) { ?>
    <button class="w3-button <?php echo h($optionsDB['colorSuccess']); ?>" type="submit" name="action" value="create">Datenbank anlegen</button>
<?php } elseif($isAdminSession) { ?>
    <button class="w3-button <?php echo h($optionsDB['colorLogWarning']); ?>" type="submit" name="action" value="repair">Datenbank reparieren</button>
<?php } ?>
  </form>

<?php
if($reportManager) {
    echo '<div class="w3-card w3-white w3-padding"><h3>Ergebnis</h3>';
    renderInstallReport($reportManager->getReport());
    if(($action === 'create' || $action === 'repair') && !$reportManager->hasErrors()) {
        echo '<div class="w3-panel w3-green"><p><b>Schema bereit.</b> Weiter zum <a href="login.php">Login</a>.</p></div>';
    }
    echo '</div>';
}
?>
</div>
</body>
</html>
