<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'members';
$_SESSION['adminpage'] = false;
include 'common/header.php';
requirePermission('perm_showUsers');

$memberships = Membership::listAll();
$n = count($memberships);
adminListPageBegin('Mitglieder', 'Mitgliedschaften ('.$n.')');
adminListSearchField('Name, Typ, Status…', array('onkeyup' => 'filterListRows()'));
?>
<div id="listHeader" class="inv-sort-bar">
  <div class="inv-sort-bar-sorts" role="toolbar" aria-label="Spalten">
    <span class="inv-sort-chip">ID</span>
    <span class="inv-sort-chip">Person</span>
    <span class="inv-sort-chip">Typ</span>
    <span class="inv-sort-chip">Status</span>
  </div>
</div>
<div id="Liste">
<?php foreach($memberships as $m) {
    $u = new IdentityUser();
    $u->load_by_id((int)$m->User);
    $name = $u->getName();
    $search = $name.' '.$m->Type.' '.$m->Status.' '.$m->Index.' '.$m->User;
    ?>
  <a class="list-row w3-padding w3-border-bottom w3-block"
     href="member.php?id=<?php echo (int)$m->Index; ?>"
     data-search="<?php echo h($search); ?>"
     data-sort-id="<?php echo (int)$m->Index; ?>"
     data-sort-name="<?php echo h($name); ?>"
     data-sort-type="<?php echo h($m->Type); ?>"
     data-sort-status="<?php echo h($m->Status); ?>">
    <div class="w3-row">
      <div class="w3-col l2 m2 s3"><?php echo (int)$m->Index; ?></div>
      <div class="w3-col l4 m4 s9"><?php echo h($name); ?> <span class="w3-small w3-text-grey">#<?php echo (int)$m->User; ?></span></div>
      <div class="w3-col l3 m3 s6"><?php echo h($m->Type); ?></div>
      <div class="w3-col l3 m3 s6"><?php echo h($m->Status); ?></div>
    </div>
  </a>
<?php } ?>
<?php if(!$n) { ?>
  <div class="w3-panel w3-padding">Noch keine Mitgliedschaften angelegt.</div>
<?php } ?>
</div>
<?php
adminListPageEnd();
?>
<script src="<?php echo assetUrl('js/filterListRows.js'); ?>"></script>
<?php include 'common/footer.php'; ?>
