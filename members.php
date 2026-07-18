<?php
session_start();
$_SESSION['page'] = 'members';
include 'common/header.php';

$memberships = Membership::listAll();
?>
<div class="w3-container <?php echo h($optionsDB['colorTitleBar']); ?>">
  <h2>Mitgliedschaften</h2>
</div>
<div class="w3-row w3-padding w3-border-bottom">
  <div class="w3-col l2"><b>ID</b></div>
  <div class="w3-col l4"><b>Person</b></div>
  <div class="w3-col l2"><b>Typ</b></div>
  <div class="w3-col l2"><b>Status</b></div>
  <div class="w3-col l2"><b></b></div>
</div>
<?php foreach($memberships as $m) {
    $u = new IdentityUser();
    $u->load_by_id((int)$m->User);
    ?>
<div class="w3-row w3-padding w3-border-bottom">
  <div class="w3-col l2"><?php echo (int)$m->Index; ?></div>
  <div class="w3-col l4"><?php echo h($u->getName()); ?> (User #<?php echo (int)$m->User; ?>)</div>
  <div class="w3-col l2"><?php echo h($m->Type); ?></div>
  <div class="w3-col l2"><?php echo h($m->Status); ?></div>
  <div class="w3-col l2"><a href="member.php?id=<?php echo (int)$m->Index; ?>">Details</a></div>
</div>
<?php } ?>
<?php if(!count($memberships)) { ?>
<div class="w3-panel w3-pale-yellow">Noch keine Mitgliedschaften angelegt.</div>
<?php } ?>
<?php include 'common/footer.php'; ?>
