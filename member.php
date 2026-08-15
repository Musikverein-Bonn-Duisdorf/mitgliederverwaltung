<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'members';
$_SESSION['adminpage'] = false;
include 'common/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$membership = new Membership();
if($id < 1 || !$membership->load_by_id($id)) {
    echo '<div class="w3-panel '.h($optionsDB['colorLogError']).'"><p>Mitgliedschaft nicht gefunden.</p></div>';
    include 'common/footer.php';
    exit;
}

$user = new IdentityUser();
$user->load_by_id((int)$membership->User);
$periods = MembershipPeriod::listForMembership((int)$membership->Index);
$documents = Document::listForUser((int)$membership->User);
$back = '<a class="w3-button '.h($optionsDB['colorBtnSubmit']).'" href="members.php" title="Zurück"><i class="fas fa-arrow-left"></i></a>';
adminListPageBegin('Mitglieder', $user->getName(), array('actionsHtml' => $back));
adminListChromeClose(false);
?>
<div class="profile-grid w3-padding">
  <div class="profile-field">
    <span class="profile-label">Typ</span>
    <span class="profile-value"><?php echo h($membership->Type); ?></span>
  </div>
  <div class="profile-field">
    <span class="profile-label">Status</span>
    <span class="profile-value"><?php echo h($membership->Status); ?></span>
  </div>
  <div class="profile-field">
    <span class="profile-label">User</span>
    <span class="profile-value">#<?php echo (int)$membership->User; ?></span>
  </div>
</div>

<div class="w3-container w3-margin-top">
  <h3 class="profile-kicker">Zeiträume</h3>
  <?php if(count($periods)) { ?>
  <div id="Liste">
    <?php foreach($periods as $p) { ?>
    <div class="list-row w3-padding w3-border-bottom">
      <?php echo h(germanDate($p->DateFrom)); ?>
      — <?php echo $p->DateTo ? h(germanDate($p->DateTo)) : 'offen'; ?>
    </div>
    <?php } ?>
  </div>
  <?php } else { ?>
  <p class="w3-small w3-text-grey">Keine Zeiträume — Stub für spätere Pflege.</p>
  <?php } ?>
</div>

<div class="w3-container w3-margin-top">
  <h3 class="profile-kicker">Dokumente</h3>
  <?php if(count($documents)) { ?>
  <ul class="w3-ul">
    <?php foreach($documents as $doc) { ?>
    <li><?php echo h($doc->DocType); ?>: <code><?php echo h($doc->NextcloudPath); ?></code></li>
    <?php } ?>
  </ul>
  <?php } else { ?>
  <p class="w3-small w3-text-grey">Keine Dokumente verknüpft.</p>
  <?php } ?>
  <a class="w3-button <?php echo h($optionsDB['colorBtnSubmit']); ?> w3-margin-top" href="documents.php?user=<?php echo (int)$membership->User; ?>">Dokument hinzufügen</a>
</div>
<?php
adminListPageEnd();
include 'common/footer.php';
?>
