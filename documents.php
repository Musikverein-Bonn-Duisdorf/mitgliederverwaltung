<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'documents';
$_SESSION['adminpage'] = false;
include 'common/header.php';
requirePermission('perm_showUsers');

$saved = false;
$error = '';
$prefillUser = isset($_GET['user']) ? (int)$_GET['user'] : 0;

if(isset($_POST['save_document'])) {
    requirePermission('perm_editUsers');
    $doc = new Document();
    $doc->User = (int)($_POST['user'] ?? 0);
    $doc->DocType = trim((string)($_POST['doctype'] ?? ''));
    $doc->NextcloudPath = trim((string)($_POST['nextcloud_path'] ?? ''));
    $doc->Note = trim((string)($_POST['note'] ?? ''));
    if($doc->save()) {
        $saved = true;
    }
    else {
        $error = 'Speichern fehlgeschlagen — Pflichtfelder prüfen.';
    }
}

$documents = Document::listAll();
$n = count($documents);
adminListPageBegin('Dokumente', 'Metadaten ('.$n.')');
adminListSearchField('Person, Typ, Pfad…', array('onkeyup' => 'filterListRows()'));
if($saved) {
    echo '<div class="w3-panel '.h($optionsDB['colorSuccess']).'">Dokument gespeichert.</div>';
}
elseif($error !== '') {
    echo '<div class="w3-panel '.h($optionsDB['colorLogError']).'">'.h($error).'</div>';
}
$canEditUsers = hasPermission('perm_editUsers');
?>
<?php if($canEditUsers) { ?>
<div class="profile-shell w3-margin-bottom">
  <header class="profile-hero admin-list-hero admin-list-hero--nutzer">
    <div class="profile-hero-text">
      <p class="profile-kicker">Neu</p>
      <h2 class="profile-title">Dokument anlegen</h2>
    </div>
  </header>
  <form method="post" class="profile-grid w3-padding">
    <div class="profile-field">
      <label>User-ID (Meldeliste)</label>
      <input class="w3-input w3-border profile-control <?php echo h($optionsDB['colorInputBackground']); ?>" type="number" name="user" min="1" value="<?php echo $prefillUser > 0 ? $prefillUser : ''; ?>" required />
    </div>
    <div class="profile-field">
      <label>Dokumenttyp</label>
      <input class="w3-input w3-border profile-control <?php echo h($optionsDB['colorInputBackground']); ?>" type="text" name="doctype" placeholder="z. B. Beitrittsscan" required />
    </div>
    <div class="profile-field">
      <label>Nextcloud-Pfad</label>
      <input class="w3-input w3-border profile-control <?php echo h($optionsDB['colorInputBackground']); ?>" type="text" name="nextcloud_path" placeholder="/Mitglieder/123/beitritt.pdf" required />
    </div>
    <div class="profile-field">
      <label>Notiz</label>
      <input class="w3-input w3-border profile-control <?php echo h($optionsDB['colorInputBackground']); ?>" type="text" name="note" />
    </div>
    <div class="profile-actions">
      <button class="w3-btn <?php echo h($optionsDB['colorBtnSubmit']); ?> w3-border" type="submit" name="save_document">Speichern</button>
    </div>
  </form>
</div>
<?php } ?>
<div id="Liste">
<?php foreach($documents as $doc) {
    $u = new IdentityUser();
    $u->load_by_id((int)$doc->User);
    $name = $u->getName();
    $search = $name.' '.$doc->DocType.' '.$doc->NextcloudPath.' '.$doc->Note;
    ?>
  <div class="list-row w3-padding w3-border-bottom" data-search="<?php echo h($search); ?>">
    <div class="w3-row">
      <div class="w3-col l3 m4 s12"><?php echo h($name); ?> <span class="w3-small w3-text-grey">#<?php echo (int)$doc->User; ?></span></div>
      <div class="w3-col l2 m3 s12"><?php echo h($doc->DocType); ?></div>
      <div class="w3-col l5 m5 s12"><code><?php echo h($doc->NextcloudPath); ?></code></div>
      <div class="w3-col l2 m12 s12 w3-small"><?php echo h($doc->UploadedAt); ?></div>
    </div>
  </div>
<?php } ?>
<?php if(!$n) { ?>
  <div class="w3-panel w3-padding">Noch keine Dokumente.</div>
<?php } ?>
</div>
<?php
adminListPageEnd();
?>
<script src="<?php echo assetUrl('js/filterListRows.js'); ?>"></script>
<?php include 'common/footer.php'; ?>
