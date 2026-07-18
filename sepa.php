<?php
session_start();
$_SESSION['page'] = 'sepa';
include 'common/header.php';

$mandates = SepaMandate::listAll();
?>
<div class="w3-container <?php echo h($optionsDB['colorTitleBar']); ?>">
  <h2>SEPA-Mandate</h2>
</div>
<div class="w3-row w3-padding w3-border-bottom">
  <div class="w3-col l2"><b>Ref</b></div>
  <div class="w3-col l3"><b>Person</b></div>
  <div class="w3-col l3"><b>IBAN</b></div>
  <div class="w3-col l2"><b>Gültig</b></div>
  <div class="w3-col l1"><b>Aktiv</b></div>
</div>
<?php foreach($mandates as $m) {
    $u = new IdentityUser();
    $u->load_by_id((int)$m->User);
    ?>
<div class="w3-row w3-padding w3-border-bottom">
  <div class="w3-col l2"><?php echo h($m->MandateRef); ?></div>
  <div class="w3-col l3"><?php echo h($u->getName()); ?></div>
  <div class="w3-col l3"><code><?php echo h($m->maskedIban()); ?></code></div>
  <div class="w3-col l2"><?php echo h(germanDate($m->ValidFrom)); ?><?php if($m->ValidTo) echo ' – '.h(germanDate($m->ValidTo)); ?></div>
  <div class="w3-col l1"><?php echo (int)$m->Active ? 'ja' : 'nein'; ?></div>
</div>
<?php } ?>
<?php if(!count($mandates)) { ?>
<div class="w3-panel w3-pale-yellow">Keine SEPA-Mandate vorhanden.</div>
<?php } ?>
<?php include 'common/footer.php'; ?>
