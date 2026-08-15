<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'sepa';
$_SESSION['adminpage'] = false;
include 'common/header.php';
requirePermission('perm_showUsers');

$mandates = SepaMandate::listAll();
$n = count($mandates);
adminListPageBegin('SEPA', 'Mandate ('.$n.')');
adminListSearchField('Referenz, Person, IBAN…', array('onkeyup' => 'filterListRows()'));
?>
<div id="listHeader" class="inv-sort-bar">
  <div class="inv-sort-bar-sorts" role="toolbar" aria-label="Spalten">
    <span class="inv-sort-chip">Ref</span>
    <span class="inv-sort-chip">Person</span>
    <span class="inv-sort-chip">IBAN</span>
    <span class="inv-sort-chip">Gültig</span>
    <span class="inv-sort-chip">Aktiv</span>
  </div>
</div>
<div id="Liste">
<?php foreach($mandates as $m) {
    $u = new IdentityUser();
    $u->load_by_id((int)$m->User);
    $name = $u->getName();
    $iban = $m->maskedIban();
    $valid = germanDate($m->ValidFrom).($m->ValidTo ? ' – '.germanDate($m->ValidTo) : '');
    $active = (int)$m->Active ? 'ja' : 'nein';
    $search = $m->MandateRef.' '.$name.' '.$iban.' '.$valid.' '.$active;
    ?>
  <div class="list-row w3-padding w3-border-bottom"
       data-search="<?php echo h($search); ?>">
    <div class="w3-row">
      <div class="w3-col l2 m2 s12"><?php echo h($m->MandateRef); ?></div>
      <div class="w3-col l3 m3 s12"><?php echo h($name); ?></div>
      <div class="w3-col l3 m3 s12"><code><?php echo h($iban); ?></code></div>
      <div class="w3-col l2 m2 s8"><?php echo h($valid); ?></div>
      <div class="w3-col l2 m2 s4"><?php echo h($active); ?></div>
    </div>
  </div>
<?php } ?>
<?php if(!$n) { ?>
  <div class="w3-panel w3-padding">Keine SEPA-Mandate vorhanden.</div>
<?php } ?>
</div>
<?php
adminListPageEnd();
?>
<script src="<?php echo assetUrl('js/filterListRows.js'); ?>"></script>
<?php include 'common/footer.php'; ?>
