<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'sepa';
$_SESSION['adminpage'] = false;
include 'common/header.php';
requirePermission('perm_showUsers');

$mandates = SepaMandate::listAll();
$n = count($mandates);
$today = date('Y-m-d');
adminListPageBegin('SEPA', 'Mandate ('.$n.')');
adminListSearchField('Referenz, Person, IBAN…', array('onkeyup' => 'filterListRows()'));
?>
<div id="listHeader" class="inv-sort-bar">
  <div class="inv-sort-bar-sorts" role="toolbar" aria-label="Sortierung">
    <button type="button" class="inv-sort-chip list-sort" data-sort="ref" data-type="string">Referenz</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="person" data-type="string">Person</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="type" data-type="string">Mitgliedschaft</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="iban" data-type="string">IBAN</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="valid" data-type="date">Gültig</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="mandate" data-type="string">Mandat</button>
  </div>
</div>
<div id="Liste" class="inv-list">
<?php foreach($mandates as $m) {
    $u = new IdentityUser();
    $u->load_by_id((int)$m->User);
    $name = $u->getName();
    $ibanRaw = (string)$m->IbanEnc;
    $ibanMasked = maskIban($ibanRaw);
    $validFrom = germanDate($m->ValidFrom);
    $validTo = $m->ValidTo ? germanDate($m->ValidTo) : '';
    $validLabel = $validFrom.($validTo !== '' ? ' – '.$validTo : '');
    $mandateActive = (int)$m->Active === 1;
    $mandateLabel = $mandateActive ? 'aktiv' : 'inaktiv';
    $isMember = MembershipPeriod::userIsMemberOn((int)$m->User, $today);
    $memType = $isMember ? MembershipTypePeriod::userTypeOn((int)$m->User, $today) : null;
    if($isMember && $memType === 'foerdernd') {
        $typeLabel = 'Fördernd';
    }
    elseif($isMember && $memType === 'aktiv') {
        $typeLabel = 'Aktiv';
    }
    elseif($isMember) {
        $typeLabel = 'Mitglied';
    }
    else {
        $typeLabel = 'kein Mitglied';
    }
    $search = $m->MandateRef.' '.$name.' '.$typeLabel.' '.$ibanRaw.' '.$ibanMasked.' '.$validLabel.' '.$mandateLabel;
    $rowClass = 'inv-row list-row'.($mandateActive ? ' inv-row--insured' : '');
    ?>
  <div class="<?php echo h($rowClass); ?>"
       data-search="<?php echo h($search); ?>"
       data-sort-ref="<?php echo h((string)$m->MandateRef); ?>"
       data-sort-person="<?php echo h($name); ?>"
       data-sort-type="<?php echo h($typeLabel); ?>"
       data-sort-iban="<?php echo h($ibanMasked); ?>"
       data-sort-valid="<?php echo h((string)$m->ValidFrom); ?>"
       data-sort-mandate="<?php echo h($mandateLabel); ?>">
    <div class="inv-id">
      <div class="inv-reg"><?php echo h((string)$m->MandateRef); ?></div>
      <div class="inv-typ"><?php echo h($typeLabel); ?></div>
    </div>
    <div class="inv-rail" aria-hidden="true"<?php
      if($typeLabel === 'Fördernd') {
          echo ' style="--inv-rail-color:#7b1fa2"';
      }
      elseif($typeLabel === 'kein Mitglied') {
          echo ' style="--inv-rail-color:#b0892e"';
      }
    ?>></div>
    <div class="inv-main">
      <div class="inv-product"><?php echo entityOpenHtml('user', (int)$m->User, $name !== '' ? $name : 'User #'.(int)$m->User); ?></div>
      <div class="inv-meta-line">
        <span class="inv-meta-item"><span class="inv-meta-k">IBAN</span> <?php echo ibanRevealHtml($ibanRaw); ?></span>
        <span class="inv-meta-item"><span class="inv-meta-k">Gültig</span> <?php echo h($validLabel); ?></span>
        <span class="inv-meta-item"><span class="inv-meta-k">Mandat</span> <?php echo h($mandateLabel); ?></span>
      </div>
    </div>
  </div>
<?php } ?>
<?php if(!$n) { ?>
  <div class="w3-panel w3-padding inv-list-empty">Keine SEPA-Mandate vorhanden.</div>
<?php } ?>
</div>
<?php
adminListPageEnd();
?>
<script src="<?php echo assetUrl('js/filterListRows.js'); ?>"></script>
<script src="<?php echo assetUrl('js/sortList.js'); ?>"></script>
<script>
bindListSort({ headerId: 'listHeader', listId: 'Liste', defaultKey: 'person', defaultDir: 'asc', defaultType: 'string' });
</script>
<?php include 'common/footer.php'; ?>
