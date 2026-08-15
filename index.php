<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'home';
$_SESSION['adminpage'] = false;
include 'common/header.php';

$nMembers = count(Membership::listAll());
$nSepa = count(SepaMandate::listAll());
$nDocs = count(Document::listAll());

adminListPageBegin('Mitgliederverwaltung', 'Übersicht');
adminListChromeClose(false);
?>
<div id="Liste">
  <a class="list-row w3-padding w3-border-bottom w3-block" href="members.php" data-search="mitglieder mitgliedschaften">
    <div class="w3-row">
      <div class="w3-col l8 m8 s8"><i class="fas fa-users" aria-hidden="true"></i> Mitglieder</div>
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
</div>
<?php
adminListPageEnd();
include 'common/footer.php';
?>
