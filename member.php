<?php
session_start();
$_SESSION['page'] = 'members';
include 'common/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$membership = new Membership();
if($id < 1 || !$membership->load_by_id($id)) {
    echo '<div class="w3-panel w3-red"><p>Mitgliedschaft nicht gefunden.</p></div>';
    include 'common/footer.php';
    exit;
}

$user = new IdentityUser();
$user->load_by_id((int)$membership->User);
$periods = MembershipPeriod::listForMembership((int)$membership->Index);
$documents = Document::listForUser((int)$membership->User);
?>
<div class="w3-container <?php echo h($optionsDB['colorTitleBar']); ?>">
  <h2><?php echo h($user->getName()); ?></h2>
  <p>Typ: <?php echo h($membership->Type); ?> — Status: <?php echo h($membership->Status); ?></p>
</div>

<div class="w3-container w3-margin-top">
  <h3>Mitgliedschaftszeiträume</h3>
  <?php if(count($periods)) { ?>
  <table class="w3-table w3-bordered w3-white">
    <tr><th>Von</th><th>Bis</th></tr>
    <?php foreach($periods as $p) { ?>
    <tr>
      <td><?php echo h(germanDate($p->DateFrom)); ?></td>
      <td><?php echo $p->DateTo ? h(germanDate($p->DateTo)) : '—'; ?></td>
    </tr>
    <?php } ?>
  </table>
  <?php } else { ?>
  <p class="w3-small w3-text-grey">Keine Zeiträume — Stub für spätere Pflege.</p>
  <?php } ?>
</div>

<div class="w3-container w3-margin-top">
  <h3>Dokumente (Stub)</h3>
  <?php if(count($documents)) { ?>
  <ul class="w3-ul">
    <?php foreach($documents as $doc) { ?>
    <li><?php echo h($doc->DocType); ?>: <code><?php echo h($doc->NextcloudPath); ?></code></li>
    <?php } ?>
  </ul>
  <?php } else { ?>
  <p class="w3-small w3-text-grey">Keine Dokumente verknüpft.</p>
  <?php } ?>
  <a class="w3-button w3-blue w3-margin-top" href="documents.php?user=<?php echo (int)$membership->User; ?>">Dokument hinzufügen</a>
</div>

<p class="w3-container"><a href="members.php">&larr; Zurück zur Liste</a></p>
<?php include 'common/footer.php'; ?>
