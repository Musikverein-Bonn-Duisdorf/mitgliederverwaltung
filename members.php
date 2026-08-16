<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'members';
$_SESSION['adminpage'] = false;
include 'common/header.php';
requirePermission('perm_showUsers');

$filter = isset($_GET['filter']) ? (string)$_GET['filter'] : 'all';
if(!in_array($filter, array('all', 'member', 'aktiv', 'foerdernd', 'none', 'retention_due'), true)) {
    $filter = 'all';
}
$rows = IdentityUser::listHub($filter);
$n = count($rows);

$filterLinks = array(
    'all' => 'Alle',
    'member' => 'Mitglied heute',
    'aktiv' => 'Aktiv',
    'foerdernd' => 'Fördernd',
    'none' => 'Kein Mitglied',
    'retention_due' => 'Löschung fällig',
);

adminListPageBegin('Personen', 'Personen ('.$n.')');
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
    $retentionDue = isset($row['retentionDue']) ? $row['retentionDue'] : null;
    $retentionDueToday = ($retentionDue !== null && $retentionDue <= date('Y-m-d'));
    if($isMember && $type === 'foerdernd') {
        $typeLabel = 'Fördernd';
        $chipMod = 'namedGroup';
        $chipText = 'Fördernd';
        $rowMod = 'user-row--foerdernd';
    }
    elseif($isMember && $type === 'aktiv') {
        $typeLabel = 'Aktiv';
        $chipMod = 'member';
        $chipText = 'Aktiv';
        $rowMod = 'user-row--member';
    }
    elseif($isMember) {
        $typeLabel = 'Mitglied';
        $chipMod = 'member';
        $chipText = 'Mitglied';
        $rowMod = 'user-row--member';
    }
    elseif($retentionDueToday) {
        $typeLabel = 'Löschung fällig';
        $chipMod = 'nomember';
        $chipText = 'Löschung fällig';
        $rowMod = 'user-row--retention';
    }
    else {
        $typeLabel = 'kein Mitglied';
        $chipMod = 'nomember';
        $chipText = 'kein Mitglied';
        $rowMod = 'user-row--nomember';
    }
    $search = $name.' '.$email.' '.$typeLabel.' '.$u->Index.' '.(string)$u->login;
    ?>
  <div class="user-row list-row <?php echo h($rowMod); ?>"
       role="button" tabindex="0"
       onclick="openModal('user', <?php echo (int)$u->Index; ?>)"
       onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openModal('user', <?php echo (int)$u->Index; ?>);}"
       data-search="<?php echo h($search); ?>"
       data-sort-name="<?php echo h($name); ?>"
       data-sort-email="<?php echo h($email); ?>"
       data-sort-type="<?php echo h($typeLabel); ?>">
    <div class="user-id">
      <div class="user-id-num"><span class="user-id-k">User-ID</span> <?php echo (int)$u->Index; ?></div>
      <div class="user-id-chips" aria-label="Mitgliedschaft">
        <div class="user-id-chip-line mail-recipient-chips">
          <span class="mail-recipient-chip mail-recipient-chip--<?php echo h($chipMod); ?>"><?php echo h($chipText); ?></span>
        </div>
      </div>
    </div>
    <div class="user-rail" aria-hidden="true"></div>
    <div class="user-main">
      <div class="user-name"><?php echo h($name); ?></div>
<?php if($email !== '') { ?>
      <div class="user-email"><a href="mailto:<?php echo h($email); ?>" onclick="event.stopPropagation()"><?php echo h($email); ?></a></div>
<?php } ?>
      <div class="user-meta-line">
        <span class="user-meta-item"><span class="user-meta-k">Mitgliedschaft</span> <?php echo h($typeLabel); ?></span>
<?php if(!$isMember && $retentionDue) { ?>
        <span class="user-meta-item"><span class="user-meta-k">Löschung</span> <?php echo h(germanDate($retentionDue)); ?></span>
<?php } ?>
<?php if((string)$u->login !== '') { ?>
        <span class="user-meta-item"><span class="user-meta-k">Login</span> <?php echo h((string)$u->login); ?></span>
<?php } ?>
      </div>
    </div>
  </div>
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
