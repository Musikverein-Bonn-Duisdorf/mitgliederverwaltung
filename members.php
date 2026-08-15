<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'members';
$_SESSION['adminpage'] = false;
include 'common/header.php';
requirePermission('perm_showUsers');

$filter = isset($_GET['filter']) ? (string)$_GET['filter'] : 'all';
if(!in_array($filter, array('all', 'member', 'aktiv', 'foerdernd', 'none'), true)) {
    $filter = 'all';
}
$rows = IdentityUser::listHub($filter);
$n = count($rows);
$canEdit = hasPermission('perm_editUsers');

$filterLinks = array(
    'all' => 'Alle',
    'member' => 'Mitglied heute',
    'aktiv' => 'Aktiv',
    'foerdernd' => 'Fördernd',
    'none' => 'Kein Mitglied',
);

$actions = '';
if($canEdit) {
    $actions = '<a class="w3-button '.h($optionsDB['colorBtnSubmit']).'" href="new-person.php"><i class="fas fa-plus"></i> Neu</a>';
}

adminListPageBegin('Personen', 'Personen ('.$n.')', array('actionsHtml' => $actions));
adminListSearchField('Name, Email, Typ…', array('onkeyup' => 'filterListRows()'));
?>
<div id="listHeader" class="inv-sort-bar">
  <div class="inv-sort-bar-filters" role="toolbar" aria-label="Filter">
<?php foreach($filterLinks as $key => $label) {
    $active = ($key === $filter);
    ?>
    <a class="inv-sort-chip inv-filter-chip<?php echo $active ? ' is-active' : ''; ?>"
       href="members.php?filter=<?php echo rawurlencode($key); ?>"
       aria-pressed="<?php echo $active ? 'true' : 'false'; ?>"><?php echo h($label); ?></a>
<?php } ?>
  </div>
  <div class="inv-sort-bar-sorts" role="toolbar" aria-label="Sortierung">
    <button type="button" class="inv-sort-chip list-sort" data-sort="name" data-type="string">Person</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="email" data-type="string">Email</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="type" data-type="string">Mitgliedschaft</button>
  </div>
</div>
<div id="Liste">
<?php foreach($rows as $row) {
    /** @var IdentityUser $u */
    $u = $row['user'];
    $name = $u->getName();
    $email = trim((string)$u->Email);
    $isMember = !empty($row['isMember']);
    $type = isset($row['type']) ? $row['type'] : null;
    if($isMember && $type === 'foerdernd') {
        $typeLabel = 'fördernd';
    }
    elseif($isMember && $type === 'aktiv') {
        $typeLabel = 'aktiv';
    }
    elseif($isMember) {
        $typeLabel = 'Mitglied';
    }
    else {
        $typeLabel = '—';
    }
    $search = $name.' '.$email.' '.$typeLabel.' '.$u->Index.' '.(string)$u->login;
    ?>
  <a class="list-row w3-padding w3-border-bottom w3-block"
     href="person.php?id=<?php echo (int)$u->Index; ?>"
     data-search="<?php echo h($search); ?>"
     data-sort-name="<?php echo h($name); ?>"
     data-sort-email="<?php echo h($email); ?>"
     data-sort-type="<?php echo h($typeLabel); ?>">
    <div class="w3-row">
      <div class="w3-col l4 m5 s12"><?php echo h($name); ?> <span class="w3-small w3-text-grey">#<?php echo (int)$u->Index; ?></span></div>
      <div class="w3-col l4 m4 s12 w3-small"><?php echo h($email !== '' ? $email : '—'); ?></div>
      <div class="w3-col l4 m3 s12"><?php echo h($typeLabel); ?></div>
    </div>
  </a>
<?php } ?>
<?php if(!$n) { ?>
  <div class="w3-panel w3-padding">Keine Personen für diesen Filter.</div>
<?php } ?>
</div>
<?php
adminListPageEnd();
?>
<script src="<?php echo assetUrl('js/filterListRows.js'); ?>"></script>
<script src="<?php echo assetUrl('js/sortList.js'); ?>"></script>
<script>
bindListSort({ headerId: 'listHeader', listId: 'Liste', defaultKey: 'name', defaultDir: 'asc', defaultType: 'string' });
</script>
<?php include 'common/footer.php'; ?>
