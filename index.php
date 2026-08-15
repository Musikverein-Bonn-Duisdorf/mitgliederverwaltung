<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'home';
$_SESSION['adminpage'] = false;
include 'common/header.php';

$nMembers = hasPermission('perm_showUsers') ? count(IdentityUser::listHub('all')) : 0;
$nSepa = hasPermission('perm_showUsers') ? count(SepaMandate::listAll()) : 0;
$nDocs = hasPermission('perm_showUsers') ? count(Document::listAll()) : 0;

adminListPageBegin('Mitgliederverwaltung', 'Übersicht');
adminListChromeClose(false);
?>
<div id="Liste">
<?php if(hasPermission('perm_showUsers')) { ?>
  <a class="list-row w3-padding w3-border-bottom w3-block" href="members.php" data-search="personen mitglieder mitgliedschaften">
    <div class="w3-row">
      <div class="w3-col l8 m8 s8"><i class="fas fa-users" aria-hidden="true"></i> Personen</div>
      <div class="w3-col l4 m4 s4 w3-right-align"><?php echo (int)$nMembers; ?></div>
    </div>
  </a>
  <a class="list-row w3-padding w3-border-bottom w3-block" href="sepa.php" data-search="sepa mandate lastschrift">
    <div class="w3-row">
      <div class="w3-col l8 m8 s8"><i class="fas fa-university" aria-hidden="true"></i> SEPA</div>
      <div class="w3-col l4 m4 s4 w3-right-align"><?php echo (int)$nSepa; ?></div>
    </div>
  </a>
  <a class="list-row w3-padding w3-border-bottom w3-block" href="documents.php" data-search="dokumente nextcloud">
    <div class="w3-row">
      <div class="w3-col l8 m8 s8"><i class="fas fa-file-alt" aria-hidden="true"></i> Dokumente</div>
      <div class="w3-col l4 m4 s4 w3-right-align"><?php echo (int)$nDocs; ?></div>
    </div>
  </a>
<?php } else { ?>
  <div class="w3-panel w3-padding <?php echo h($optionsDB['colorLogWarning']); ?>">
    Eingeloggt, aber ohne Recht „Nutzerdaten lesen“. Bitte einen Admin um Rechte in <b>Berechtigungen</b> bitten.
  </div>
<?php } ?>
</div>
<?php
adminListPageEnd();
include 'common/footer.php';
?>
