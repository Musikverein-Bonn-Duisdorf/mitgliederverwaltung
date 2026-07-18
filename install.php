<?php
/**
 * First-time database setup. Open without login while schema is fresh;
 * otherwise requires logged-in Meldeliste admin.
 */
session_start();

$configFile = __DIR__.'/common/config.php';
if(!is_readable($configFile)) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Install</title>';
    echo '<link rel="stylesheet" href="styles/w3.css"></head><body class="w3-container">';
    echo '<div class="w3-panel w3-red"><h2>Konfiguration fehlt</h2>';
    echo '<p>Bitte <code>common/config_sample.php</code> nach <code>common/config.php</code> kopieren.</p></div></body></html>';
    exit;
}

require_once $configFile;

if(!function_exists('sqlerror')) {
    function sqlerror() {
        if(!isset($GLOBALS['conn']) || !mysqli_errno($GLOBALS['conn'])) return;
        echo '<div class="w3-panel w3-red"><b>SQL ERROR</b> '
            .htmlspecialchars(mysqli_errno($GLOBALS['conn']).': '.mysqli_error($GLOBALS['conn']))
            .'</div>';
    }
}

require_once __DIR__.'/libs/helpers.php';
require_once __DIR__.'/libs/SQLtable.php';
require_once __DIR__.'/config/ConfigDefaults.php';
require_once __DIR__.'/config/SchemaVersion.php';
require_once __DIR__.'/libs/SchemaManager.php';
require_once __DIR__.'/libs/IdentityUser.php';

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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mitgliederverwaltung — Installation</title>
  <link rel="stylesheet" href="styles/w3.css">
</head>
<body class="w3-light-grey">
<div class="w3-container w3-teal">
  <h1>Datenbank-Installation</h1>
</div>
<div class="w3-container w3-margin">
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
    <button class="w3-button w3-blue" type="submit" name="action" value="check">Nur prüfen</button>
<?php if($isFresh) { ?>
    <button class="w3-button w3-green" type="submit" name="action" value="create">Datenbank anlegen</button>
<?php } elseif($isAdminSession) { ?>
    <button class="w3-button w3-orange" type="submit" name="action" value="repair">Datenbank reparieren</button>
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
