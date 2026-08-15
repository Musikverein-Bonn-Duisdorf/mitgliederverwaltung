<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'backup';
$_SESSION['adminpage'] = true;

// Download must run before any HTML output (header.php)
if(isset($_GET['download']) && $_GET['download'] === '1') {
    include_once 'common/include.php';
    mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));
    requireLoggedInOrRedirect();
    if(!hasPermission('perm_editConfig')) {
        denyAccess('Keine Berechtigung für Backup/Restore.');
    }
    require_once __DIR__.'/libs/backup.php';
    try {
        sendBackupDownload();
    }
    catch(Throwable $e) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Backup failed: '.$e->getMessage()."\n";
        exit(1);
    }
}

include_once 'common/header.php';

if(!hasPermission('perm_editConfig')) {
    denyAccess('Keine Berechtigung für Backup/Restore.');
}

require_once __DIR__.'/libs/backup.php';

$flash = null;
$restoreResult = null;

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_confirm'])) {
    if(!csrf_verify(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $flash = array('type' => 'error', 'message' => 'Ungültiges Sicherheits-Token.');
    }
    elseif(empty($_POST['confirm_text']) || trim((string)$_POST['confirm_text']) !== 'RESTORE') {
        $flash = array('type' => 'error', 'message' => 'Bitte zur Bestätigung RESTORE eintippen.');
    }
    elseif(empty($_FILES['backup_zip']) || !is_uploaded_file($_FILES['backup_zip']['tmp_name'])) {
        $flash = array('type' => 'error', 'message' => 'Keine ZIP-Datei hochgeladen.');
    }
    else {
        try {
            $restoreResult = restoreBackupZip($_FILES['backup_zip']['tmp_name'], true);
            if(!empty($restoreResult['errors'])) {
                $flash = array(
                    'type' => 'error',
                    'message' => 'Restore mit Fehlern: '.implode('; ', $restoreResult['errors']),
                );
            }
            else {
                $flash = array(
                    'type' => 'ok',
                    'message' => 'Restore erfolgreich ('.$restoreResult['statements'].' Statements'
                        .($restoreResult['repaired'] ? ', Schema repariert' : '')
                        .').',
                );
                $logentry = new Log;
                $logentry->info('<b>Database restore</b> from uploaded backup ZIP');
            }
        }
        catch(Throwable $e) {
            $flash = array('type' => 'error', 'message' => 'Restore fehlgeschlagen: '.$e->getMessage());
        }
    }
}

$manifest = buildBackupManifest();
$ver = $manifest['version'];
$schema = $manifest['schemaVersion'];
adminListPageBegin('System', 'Backup');
?>
<?php if($flash) {
    $toastType = ($flash['type'] === 'ok') ? 'success' : 'error';
    echo renderFlashHtml(array('type' => $toastType, 'message' => $flash['message']));
} ?>

  <div class="w3-card w3-padding w3-margin-bottom">
    <h3>Stand</h3>
    <p>
      Version: <b><?php echo htmlspecialchars($ver['String'], ENT_QUOTES, 'UTF-8'); ?></b><br>
      Schema: <b><?php echo (int)$schema['installed']; ?></b> / <b><?php echo (int)$schema['expected']; ?></b><br>
      Prefix: <code><?php echo htmlspecialchars($manifest['dbprefix'], ENT_QUOTES, 'UTF-8'); ?></code>
    </p>
    <p class="w3-small">Backup enthält nur Tabellen mit diesem Prefix — keine Melde-Identity-Tabellen.</p>
    <p><a href="help.php#help-admin-verwaltung">Hilfe: Backup</a></p>
  </div>

  <div class="w3-card w3-padding w3-margin-bottom">
    <h3>Download</h3>
    <p><a class="w3-button w3-border <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?>" href="backup.php?download=1"><i class="fas fa-download"></i> Backup laden</a></p>
  </div>

  <div class="w3-card w3-padding w3-margin-bottom w3-pale-red">
    <h3>Restore</h3>
    <form method="post" enctype="multipart/form-data" action="backup.php" data-app-confirm="Restore ausführen? Mitgliedschafts-Tabellen (mit_*) werden überschrieben.">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="restore_confirm" value="1">
      <label>ZIP</label>
      <input class="w3-input w3-border w3-margin-bottom" type="file" name="backup_zip" accept=".zip,application/zip" required>
      <label>Bestätigung <code>RESTORE</code></label>
      <input class="w3-input w3-border w3-margin-bottom" type="text" name="confirm_text" autocomplete="off" required>
      <button type="submit" class="w3-button w3-border w3-red">Einspielen</button>
    </form>
  </div>
<?php
adminListPageEnd();
include 'common/footer.php';
?>
