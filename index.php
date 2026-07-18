<?php
session_start();
$_SESSION['page'] = 'home';
include 'common/header.php';
?>
<div class="w3-container <?php echo h($optionsDB['colorTitleBar']); ?>">
  <h2>Übersicht</h2>
  <p>Mitgliederverwaltung — Schema v<?php echo (int)$optionsDB['SchemaVersion']; ?>.</p>
</div>
<div class="w3-row-padding w3-margin-top">
  <div class="w3-third">
    <div class="w3-card w3-padding">
      <h3><i class="fas fa-users"></i> Mitglieder</h3>
      <p>Mitgliedschaften und Laufzeiten verwalten.</p>
      <a class="w3-button w3-blue" href="members.php">Öffnen</a>
    </div>
  </div>
  <div class="w3-third">
    <div class="w3-card w3-padding">
      <h3><i class="fas fa-university"></i> SEPA</h3>
      <p>Lastschriftmandate (IBAN maskiert).</p>
      <a class="w3-button w3-blue" href="sepa.php">Öffnen</a>
    </div>
  </div>
  <div class="w3-third">
    <div class="w3-card w3-padding">
      <h3><i class="fas fa-file-alt"></i> Dokumente</h3>
      <p>Metadaten zu Nextcloud-Pfaden.</p>
      <a class="w3-button w3-blue" href="documents.php">Öffnen</a>
    </div>
  </div>
</div>
<?php include 'common/footer.php'; ?>
