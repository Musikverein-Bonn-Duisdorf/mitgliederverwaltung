<?php
session_start();
$_SESSION['page'] = 'documents';
include 'common/header.php';

$saved = false;
$error = '';
$prefillUser = isset($_GET['user']) ? (int)$_GET['user'] : 0;

if(isset($_POST['save_document'])) {
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
?>
<div class="w3-container <?php echo h($optionsDB['colorTitleBar']); ?>">
  <h2>Dokumente</h2>
  <p class="w3-small">Nur Metadaten — Datei liegt in Nextcloud unter dem angegebenen Pfad.</p>
</div>

<?php if($saved) { ?>
<div class="w3-panel <?php echo h($optionsDB['colorSuccess']); ?>">Dokument gespeichert.</div>
<?php } elseif($error !== '') { ?>
<div class="w3-panel <?php echo h($optionsDB['colorLogError']); ?>"><?php echo h($error); ?></div>
<?php } ?>

<div class="w3-container w3-margin-top">
  <h3>Neues Dokument (Metadaten)</h3>
  <form method="post" class="w3-container w3-card w3-padding">
    <label>User-ID (Meldeliste)</label>
    <input class="w3-input w3-border w3-margin-bottom" type="number" name="user" min="1" value="<?php echo $prefillUser > 0 ? $prefillUser : ''; ?>" required />
    <label>Dokumenttyp</label>
    <input class="w3-input w3-border w3-margin-bottom" type="text" name="doctype" placeholder="z. B. Beitrittsscan" required />
    <label>Nextcloud-Pfad</label>
    <input class="w3-input w3-border w3-margin-bottom" type="text" name="nextcloud_path" placeholder="/Mitglieder/123/beitritt.pdf" required />
    <label>Notiz</label>
    <input class="w3-input w3-border w3-margin-bottom" type="text" name="note" />
    <button class="w3-button <?php echo h($optionsDB['colorBtnSubmit']); ?>" type="submit" name="save_document">Speichern</button>
  </form>
</div>

<div class="w3-container w3-margin-top">
  <h3>Vorhandene Dokumente</h3>
  <?php if(count($documents)) { ?>
  <table class="w3-table w3-bordered w3-white">
    <tr><th>User</th><th>Typ</th><th>Pfad</th><th>Hochgeladen</th></tr>
    <?php foreach($documents as $doc) {
        $u = new IdentityUser();
        $u->load_by_id((int)$doc->User);
        ?>
    <tr>
      <td><?php echo h($u->getName()); ?> (#<?php echo (int)$doc->User; ?>)</td>
      <td><?php echo h($doc->DocType); ?></td>
      <td><code><?php echo h($doc->NextcloudPath); ?></code></td>
      <td><?php echo h($doc->UploadedAt); ?></td>
    </tr>
    <?php } ?>
  </table>
  <?php } else { ?>
  <p class="w3-text-grey">Noch keine Dokumente.</p>
  <?php } ?>
</div>
<?php include 'common/footer.php'; ?>
