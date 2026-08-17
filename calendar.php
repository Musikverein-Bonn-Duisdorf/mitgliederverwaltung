<?php
/**
 * Jubiläen-Kalender (Geburtstag + Mitgliedschaft) und Jahresliste.
 */
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();

$view = isset($_GET['view']) ? (string)$_GET['view'] : 'month';
if(!in_array($view, array('month', 'year'), true)) {
    $view = 'month';
}
$_SESSION['page'] = ($view === 'year') ? 'calendar-list' : 'calendar';
$_SESSION['adminpage'] = false;
include 'common/header.php';
requirePermission('perm_showJubilees');

$monthNames = array(
    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
    5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
);
$weekdays = array('Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So');

$todayY = (int)date('Y');
$todayM = (int)date('n');
$year = isset($_GET['y']) ? (int)$_GET['y'] : $todayY;
if($year < 1970 || $year > 2100) {
    $year = $todayY;
}
$month = isset($_GET['m']) ? (int)$_GET['m'] : $todayM;
if($month < 1 || $month > 12) {
    $month = $todayM;
}

adminListPageBegin('Jubiläen', $view === 'month'
    ? $monthNames[$month].' '.$year
    : 'Jahr '.$year, array('groupId' => 'jubilaeen'));
adminListChromeClose(false);

$prevM = $month - 1;
$prevY = $year;
if($prevM < 1) {
    $prevM = 12;
    $prevY--;
}
$nextM = $month + 1;
$nextY = $year;
if($nextM > 12) {
    $nextM = 1;
    $nextY++;
}
?>
<style>
.mit-cal-page { max-width: 72rem; margin: 0 auto; padding: 0 0 1rem; }
.mit-cal-toolbar {
  display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
  gap: 0.65rem; padding: 0.75rem 0; margin: 0;
}
.mit-cal-pickers { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center; }
.mit-cal-spinner { display: inline-flex; align-items: stretch; }
.mit-cal-spinner select {
  text-align: center; font-weight: bold; padding: 6px 4px; margin: 0; border-radius: 0;
  max-width: 9.5rem;
}
.mit-cal-step { margin: 0; padding: 0; width: 2.15rem; min-width: 2.15rem;
  display: inline-flex; align-items: center; justify-content: center; }
.mit-cal-legend { display: flex; gap: 0.75rem; flex-wrap: wrap; font-size: 0.85rem; margin: 0 0 0.75rem; }
.mit-cal-legend span { display: inline-flex; align-items: center; gap: 0.35rem; }
.mit-cal-swatch { width: 0.75rem; height: 0.75rem; border-radius: 2px; display: inline-block; }
.mit-cal-swatch--bday { background: #345A95; }
.mit-cal-swatch--club { background: #2E7D32; }
.mit-cal-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 2px; }
.mit-cal-head { font-weight: bold; text-align: center; padding: 6px 2px; font-size: 0.85em; }
.mit-cal-cell {
  min-height: 5.2rem; border: 1px solid #ccc; padding: 4px; background: #fff; overflow: hidden;
}
.mit-cal-cell--out { opacity: 0.45; background: #f5f5f5; }
.mit-cal-cell--weekend { background: #f0f3f7; }
.mit-cal-cell--today { outline: 2px solid #345A95; outline-offset: -2px; }
.mit-cal-daynum { font-size: 0.8em; font-weight: bold; margin-bottom: 2px; }
.mit-cal-chip {
  display: block; width: 100%; box-sizing: border-box; margin: 0 0 2px 0;
  padding: 2px 4px; font-size: 0.7em; line-height: 1.2; text-align: left;
  border: none; border-radius: 2px; color: #fff; text-decoration: none;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  font-family: inherit; cursor: pointer;
}
.mit-cal-chip--birthday { background: #345A95; }
.mit-cal-chip--membership { background: #2E7D32; }
.mit-cal-year-list .list-row {
  display: grid;
  grid-template-columns: 6.5rem minmax(10rem, 1.2fr) 1fr;
  gap: 0.5rem;
  align-items: baseline;
}
@media (max-width: 640px) {
  .mit-cal-cell { min-height: 4rem; }
  .mit-cal-chip { font-size: 0.6em; }
  .mit-cal-year-list .list-row { grid-template-columns: 1fr; gap: 0.15rem; }
}
</style>
<div class="mit-cal-page">
  <div class="mit-cal-toolbar" role="toolbar" aria-label="Kalender-Navigation">
    <div class="mit-cal-pickers">
<?php if($view === 'month') { ?>
      <div class="mit-cal-spinner" role="group" aria-label="Monat">
        <a class="w3-button w3-border mit-cal-step" href="calendar.php?view=month&amp;y=<?php echo (int)$prevY; ?>&amp;m=<?php echo (int)$prevM; ?>" title="Vorheriger Monat"><i class="fas fa-chevron-left" aria-hidden="true"></i></a>
        <select id="calMonthSelect" class="w3-select w3-border" aria-label="Monat">
<?php foreach($monthNames as $num => $name) { ?>
          <option value="<?php echo (int)$num; ?>"<?php echo $num === $month ? ' selected' : ''; ?>><?php echo h($name); ?></option>
<?php } ?>
        </select>
        <a class="w3-button w3-border mit-cal-step" href="calendar.php?view=month&amp;y=<?php echo (int)$nextY; ?>&amp;m=<?php echo (int)$nextM; ?>" title="Nächster Monat"><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
      </div>
<?php } ?>
      <div class="mit-cal-spinner" role="group" aria-label="Jahr">
        <a class="w3-button w3-border mit-cal-step" href="calendar.php?view=<?php echo h($view); ?>&amp;y=<?php echo (int)($year - 1); ?><?php echo $view === 'month' ? '&amp;m='.(int)$month : ''; ?>" title="Vorheriges Jahr"><i class="fas fa-chevron-left" aria-hidden="true"></i></a>
        <select id="calYearSelect" class="w3-select w3-border" aria-label="Jahr">
<?php for($y = $todayY - 10; $y <= $todayY + 10; $y++) { ?>
          <option value="<?php echo $y; ?>"<?php echo $y === $year ? ' selected' : ''; ?>><?php echo $y; ?></option>
<?php } ?>
        </select>
        <a class="w3-button w3-border mit-cal-step" href="calendar.php?view=<?php echo h($view); ?>&amp;y=<?php echo (int)($year + 1); ?><?php echo $view === 'month' ? '&amp;m='.(int)$month : ''; ?>" title="Nächstes Jahr"><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
      </div>
    </div>
    <a class="w3-button w3-border" href="calendar.php<?php echo $view === 'year' ? '?view=year' : ''; ?>">Heute</a>
  </div>
  <div class="mit-cal-legend">
    <span><i class="mit-cal-swatch mit-cal-swatch--bday" aria-hidden="true"></i> Geburtstag</span>
    <span><i class="mit-cal-swatch mit-cal-swatch--club" aria-hidden="true"></i> Mitgliedschaft</span>
  </div>
<script>
(function() {
  var monthSel = document.getElementById('calMonthSelect');
  var yearSel = document.getElementById('calYearSelect');
  var view = <?php echo json_encode($view); ?>;
  function go() {
    if(!yearSel) return;
    var y = parseInt(yearSel.value, 10);
    var url = 'calendar.php?view=' + encodeURIComponent(view) + '&y=' + y;
    if(view === 'month' && monthSel) {
      url += '&m=' + parseInt(monthSel.value, 10);
    }
    window.location.href = url;
  }
  if(monthSel) monthSel.addEventListener('change', go);
  if(yearSel) yearSel.addEventListener('change', go);
})();
</script>
<?php
if($view === 'year') {
    $events = JubileeCalendar::eventsForYear($year);
    ?>
<div id="listHeader" class="inv-sort-bar">
  <div class="inv-sort-bar-sorts" role="toolbar" aria-label="Sortierung">
    <button type="button" class="inv-sort-chip list-sort" data-sort="date" data-type="date">Datum</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="title" data-type="string">Jubiläum</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="name" data-type="string">Person</button>
  </div>
</div>
<div id="Liste" class="inv-list">
<?php if(!$events) { ?>
  <div class="w3-panel w3-padding inv-list-empty">Keine Jubiläen in diesem Jahr.</div>
<?php } ?>
<?php foreach($events as $ev) {
    $title = JubileeCalendar::formatTitle($ev);
    $dateIso = substr((string)$ev['date'], 0, 10);
    $name = (string)$ev['name'];
    $isMembership = ($ev['kind'] === 'membership');
    ?>
  <div class="inv-row list-row"
       role="button" tabindex="0"
       onclick="openModal('user', <?php echo (int)$ev['userId']; ?>)"
       onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openModal('user', <?php echo (int)$ev['userId']; ?>);}"
       data-sort-date="<?php echo h($dateIso); ?>"
       data-sort-title="<?php echo h($title); ?>"
       data-sort-name="<?php echo h(isset($ev['sortName']) ? $ev['sortName'] : $name); ?>">
    <div class="inv-id">
      <div class="inv-reg"><?php echo h(germanDate($ev['date'])); ?></div>
      <div class="inv-typ"><?php echo $isMembership ? 'Mitgliedschaft' : 'Geburtstag'; ?></div>
    </div>
    <div class="inv-rail" aria-hidden="true" style="<?php echo $isMembership ? '--inv-rail-color:#2E7D32' : ''; ?>"></div>
    <div class="inv-main">
      <div class="inv-product"><?php echo h($name); ?></div>
      <div class="inv-meta-line">
        <span class="inv-meta-item"><span class="inv-meta-k">Jubiläum</span> <?php echo h($title); ?></span>
      </div>
    </div>
  </div>
<?php } ?>
</div>
<script src="<?php echo assetUrl('js/sortList.js'); ?>"></script>
<script>
bindListSort({ headerId: 'listHeader', listId: 'Liste', defaultKey: 'date', defaultDir: 'asc', defaultType: 'date' });
</script>
<?php
}
else {
    $events = JubileeCalendar::eventsForMonth($year, $month);
    $byDay = array();
    foreach($events as $ev) {
        $d = substr((string)$ev['date'], 0, 10);
        if(!isset($byDay[$d])) {
            $byDay[$d] = array();
        }
        $byDay[$d][] = $ev;
    }
    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $dow = (int)date('N', strtotime($monthStart)); // 1=Mo
    $gridStart = date('Y-m-d', strtotime($monthStart.' -'.($dow - 1).' days'));
    $endDow = (int)date('N', strtotime($monthEnd));
    $gridEnd = date('Y-m-d', strtotime($monthEnd.' +'.(7 - $endDow).' days'));
    $today = date('Y-m-d');
    ?>
  <div class="mit-cal-grid" role="grid" aria-label="Monatskalender">
<?php foreach($weekdays as $wd) { ?>
    <div class="mit-cal-head" role="columnheader"><?php echo h($wd); ?></div>
<?php } ?>
<?php
    $cursor = $gridStart;
    while($cursor <= $gridEnd) {
        $inMonth = ($cursor >= $monthStart && $cursor <= $monthEnd);
        $dowN = (int)date('N', strtotime($cursor));
        $classes = 'mit-cal-cell';
        if(!$inMonth) {
            $classes .= ' mit-cal-cell--out';
        }
        if($dowN >= 6) {
            $classes .= ' mit-cal-cell--weekend';
        }
        if($cursor === $today) {
            $classes .= ' mit-cal-cell--today';
        }
        $dayEvents = isset($byDay[$cursor]) ? $byDay[$cursor] : array();
        ?>
    <div class="<?php echo $classes; ?>" role="gridcell">
      <div class="mit-cal-daynum"><?php echo (int)date('j', strtotime($cursor)); ?></div>
<?php foreach($dayEvents as $ev) {
    $chipClass = ($ev['kind'] === 'membership') ? 'mit-cal-chip--membership' : 'mit-cal-chip--birthday';
    $title = $ev['name'].' · '.JubileeCalendar::formatTitle($ev);
    ?>
        <button type="button" class="mit-cal-chip <?php echo $chipClass; ?>"
           onclick="openModal('user', <?php echo (int)$ev['userId']; ?>)"
           title="<?php echo h($title); ?>"><?php echo h(JubileeCalendar::formatChip($ev)); ?></button>
<?php } ?>
    </div>
<?php
        $cursor = date('Y-m-d', strtotime($cursor.' +1 day'));
    }
    ?>
  </div>
<?php } ?>
</div>
<?php
adminListPageEnd();
include 'common/footer.php';
?>
