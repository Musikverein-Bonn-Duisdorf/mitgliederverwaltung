<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'members';
$_SESSION['adminpage'] = false;
include 'common/header.php';
requirePermission('perm_showUsers');

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = new IdentityUser();
if($userId < 1 || !$user->load_by_id($userId) || (int)$user->Deleted === 1) {
    echo '<div class="w3-panel '.h($optionsDB['colorLogError']).'"><p>Person nicht gefunden.</p></div>';
    include 'common/footer.php';
    exit;
}

$canEdit = hasPermission('perm_editUsers');
MembershipPeriod::wipeBankDataIfDueForUser($userId);
$profile = new MemberProfile();
$profile->load_by_user($userId);
$membership = new Membership();
$hasMembership = $membership->load_by_user($userId);
$periods = $hasMembership ? MembershipPeriod::listForMembership((int)$membership->Index) : array();
$typePeriods = $hasMembership ? MembershipTypePeriod::listForMembership((int)$membership->Index) : array();
$mandates = SepaMandate::listForUser($userId);
$documents = Document::listForUser($userId);
$applications = MembershipApplication::listForUser($userId);
$today = date('Y-m-d');
$isMemberToday = MembershipPeriod::userIsMemberOn($userId, $today);
$currentType = $isMemberToday ? MembershipTypePeriod::userTypeOn($userId, $today) : null;
$openTenure = $hasMembership ? MembershipPeriod::openForMembership((int)$membership->Index) : null;
$retentionDue = MembershipPeriod::retentionDueDateForUser($userId, $today);
$retentionStatus = MembershipPeriod::userRetentionStatus($userId, $today);
$canShowJubilees = hasPermission('perm_showJubilees');
$nextJubilees = $canShowJubilees ? JubileeCalendar::nextForUser($userId, $today, 1) : array();
$pastJubilees = $canShowJubilees ? JubileeCalendar::pastForUser($userId, $today, 1) : array();

$flash = '';
if(!empty($_SESSION['personFlash'])) {
    $flash = (string)$_SESSION['personFlash'];
    unset($_SESSION['personFlash']);
}

$actions = '<a class="w3-button '.h($optionsDB['colorBtnSubmit']).'" href="members.php" title="Zurück"><i class="fas fa-arrow-left"></i></a>';
adminListPageBegin('Personen', $user->getName(), array('actionsHtml' => $actions));
adminListChromeClose(false);

if($flash !== '') {
    echo '<div class="w3-panel '.h($optionsDB['colorBtnSubmit']).' w3-padding"><p>'.h($flash).'</p></div>';
}
if($retentionStatus === 'due' || $retentionStatus === 'upcoming') {
    $dueLabel = $retentionDue ? germanDate($retentionDue) : '';
    $panelColor = $retentionStatus === 'due' ? h($optionsDB['colorLogError']) : h($optionsDB['colorWarning']);
    echo '<div class="w3-panel '.$panelColor.' w3-padding"><p>Löschung fällig'
        .($dueLabel !== '' ? ': '.h($dueLabel) : '')
        .' ('.(int)MembershipPeriod::retentionYears().' Jahre nach Austritt/Tod).</p></div>';
}

$memberLabel = $isMemberToday
    ? (($currentType === 'foerdernd') ? 'Fördernd' : 'Aktiv')
    : 'kein Mitglied';
$inputBg = h($optionsDB['colorInputBackground']);
$feeEuro = '';
$feeMinErm = '';
$feeReducedMem = false;
if($hasMembership) {
    $feeReducedMem = (int)$membership->FeeReduced === 1;
    $feeEuro = $membership->AnnualFeeCents !== null
        ? number_format(((int)$membership->AnnualFeeCents) / 100, 2, '.', '')
        : number_format(MembershipForm::minFeeCents($currentType ?: 'aktiv', $feeReducedMem) / 100, 2, '.', '');
    $feeMinErm = number_format(MembershipForm::minFeeCentsReduced() / 100, 2, '.', '');
}
$addr = trim(implode(', ', array_filter(array(
    $profile->Street,
    trim((string)$profile->Zip.' '.$profile->City),
    $profile->Country,
))));

$pendingApp = null;
$appliedApplications = array();
foreach($applications as $a) {
    if((string)$a->Status === 'applied') {
        $appliedApplications[] = $a;
    }
    elseif($pendingApp === null) {
        $pendingApp = $a;
    }
}
$entryDateLabel = ($openTenure && $openTenure->DateFrom) ? germanDate($openTenure->DateFrom) : '';
$profileFormId = 'person-profile-form';
$timelineEvents = MembershipPeriod::timelineEvents($periods, $typePeriods);
$periodsById = array();
foreach($periods as $p) {
    $periodsById[(int)$p->Index] = $p;
}
$typePeriodsById = array();
foreach($typePeriods as $t) {
    $typePeriodsById[(int)$t->Index] = $t;
}
$showMembershipSection = $canEdit || count($timelineEvents) > 0 || count($applications) > 0;
?>
<div class="person-page">
<?php if($canEdit) { ?>
<form id="<?php echo h($profileFormId); ?>" method="post" action="savePerson.php" class="person-stammdaten-form">
  <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
  <input type="hidden" name="action" value="save_profile" />
</form>

  <div class="profile-grid profile-grid--3 person-stammdaten">
    <section class="profile-col" aria-labelledby="person-col-person">
      <h3 id="person-col-person" class="profile-col-title">Person</h3>
      <div class="profile-field">
        <label class="profile-label" for="person-vorname">Vorname</label>
        <input id="person-vorname" form="<?php echo h($profileFormId); ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="vorname" value="<?php echo h((string)$user->Vorname); ?>" required autocomplete="given-name" />
      </div>
      <div class="profile-field">
        <label class="profile-label" for="person-nachname">Nachname</label>
        <input id="person-nachname" form="<?php echo h($profileFormId); ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="nachname" value="<?php echo h((string)$user->Nachname); ?>" required autocomplete="family-name" />
      </div>
      <div class="profile-field">
        <label class="profile-label" for="person-birthday">Geburtstag</label>
        <input id="person-birthday" form="<?php echo h($profileFormId); ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="birthday" value="<?php echo h((string)$profile->Birthday); ?>" />
      </div>
      <div class="profile-field">
        <label class="profile-label" for="person-street">Straße</label>
        <input id="person-street" form="<?php echo h($profileFormId); ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="street" value="<?php echo h((string)$profile->Street); ?>" autocomplete="street-address" />
      </div>
      <div class="profile-fields-inline">
        <div class="profile-field">
          <label class="profile-label" for="person-zip">PLZ</label>
          <input id="person-zip" form="<?php echo h($profileFormId); ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="zip" value="<?php echo h((string)$profile->Zip); ?>" autocomplete="postal-code" />
        </div>
        <div class="profile-field">
          <label class="profile-label" for="person-city">Ort</label>
          <input id="person-city" form="<?php echo h($profileFormId); ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="city" value="<?php echo h((string)$profile->City); ?>" autocomplete="address-level2" />
        </div>
        <div class="profile-field">
          <label class="profile-label" for="person-country">Land</label>
          <input id="person-country" form="<?php echo h($profileFormId); ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="country" value="<?php echo h((string)$profile->Country); ?>" autocomplete="country" />
        </div>
      </div>
    </section>

    <section class="profile-col" aria-labelledby="person-col-kontakt">
      <h3 id="person-col-kontakt" class="profile-col-title">Kontakt</h3>
      <div class="profile-field">
        <label class="profile-label" for="person-email">E-Mail</label>
        <input id="person-email" form="<?php echo h($profileFormId); ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="email" name="email" value="<?php echo h((string)$user->Email); ?>" autocomplete="email" />
      </div>
      <div class="profile-field">
        <label class="profile-label" for="person-phone">Telefon</label>
        <input id="person-phone" form="<?php echo h($profileFormId); ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="tel" name="phone" value="<?php echo h((string)$profile->Phone); ?>" autocomplete="tel" />
      </div>
    </section>

    <section class="profile-col person-col-bank" aria-labelledby="person-col-bank">
      <h3 id="person-col-bank" class="profile-col-title">Bank / SEPA</h3>
      <div class="profile-field">
        <label class="profile-label" for="person-holder">Kontoinhaber</label>
        <input id="person-holder" form="<?php echo h($profileFormId); ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="account_holder" value="<?php echo h((string)$profile->AccountHolder); ?>" />
      </div>

<?php if(count($mandates)) { ?>
      <ul class="person-meta-list person-edit-list person-sepa-list">
<?php foreach($mandates as $mandate) {
    $mid = (int)$mandate->Index;
    $ibanDisp = formatIbanDisplay((string)$mandate->IbanEnc);
    $bankDisp = trim((string)$mandate->BankName);
    if($bankDisp === '' && class_exists('BlzDirectory')) {
        $bankDisp = BlzDirectory::bankNameFromIban((string)$mandate->IbanEnc);
    }
    $bankFieldId = 'sepa-bank-'.$mid;
?>
        <li class="person-sepa-item">
          <form method="post" action="savePerson.php" class="person-sepa-form">
            <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
            <input type="hidden" name="mandate_id" value="<?php echo $mid; ?>" />
            <div class="profile-field">
              <label class="profile-label" for="sepa-iban-<?php echo $mid; ?>">IBAN</label>
              <input class="w3-input w3-border profile-control js-iban-check <?php echo $inputBg; ?>" type="text" name="iban" id="sepa-iban-<?php echo $mid; ?>" value="<?php echo h($ibanDisp); ?>" required data-iban-required="1" data-iban-bank="<?php echo h($bankFieldId); ?>" autocomplete="off" spellcheck="false" inputmode="text" />
            </div>
            <div class="profile-field">
              <label class="profile-label" for="<?php echo h($bankFieldId); ?>">Kreditinstitut</label>
              <input class="w3-input w3-border profile-control js-iban-bank <?php echo $inputBg; ?>" type="text" name="bank_name" id="<?php echo h($bankFieldId); ?>" value="<?php echo h($bankDisp); ?>" autocomplete="organization" />
            </div>
            <div class="person-sepa-meta">
              <div class="profile-field">
                <label class="profile-label" for="sepa-from-<?php echo $mid; ?>">Gültig ab</label>
                <input id="sepa-from-<?php echo $mid; ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="valid_from" value="<?php echo h(substr((string)$mandate->ValidFrom, 0, 10)); ?>" required />
              </div>
              <div class="profile-field">
                <label class="profile-label" for="sepa-to-<?php echo $mid; ?>">Gültig bis</label>
                <input id="sepa-to-<?php echo $mid; ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="valid_to" value="<?php echo $mandate->ValidTo ? h(substr((string)$mandate->ValidTo, 0, 10)) : ''; ?>" />
              </div>
            </div>
            <div class="person-sepa-actions">
              <button type="submit" name="action" value="sepa_update" class="w3-button w3-mobile <?php echo h($optionsDB['colorBtnSubmit']); ?>">Mandat speichern</button>
              <button type="submit" name="action" value="sepa_delete" class="w3-button w3-mobile <?php echo h($optionsDB['colorLogError']); ?>" data-confirm="Mandat löschen?" data-confirm-ok="Mandat löschen" data-confirm-ok-class="w3-btn w3-border w3-mobile <?php echo h($optionsDB['colorLogError']); ?>">Mandat löschen</button>
            </div>
          </form>
        </li>
<?php } ?>
      </ul>
<?php } else { ?>
      <p class="person-meta-empty">Kein SEPA-Mandat</p>
<?php } ?>

      <details class="person-workflow-manual">
        <summary>SEPA anlegen</summary>
        <form method="post" action="savePerson.php" class="person-action-form">
          <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
          <input type="hidden" name="action" value="sepa_create" />
          <div class="profile-field">
            <label class="profile-label" for="sepa-iban-new">IBAN</label>
            <input class="w3-input w3-border profile-control js-iban-check <?php echo $inputBg; ?>" type="text" name="iban" id="sepa-iban-new" required data-iban-required="1" data-iban-bank="sepa-bank-new" autocomplete="off" spellcheck="false" inputmode="text" />
          </div>
          <div class="profile-field">
            <label class="profile-label" for="sepa-bank-new">Kreditinstitut</label>
            <input class="w3-input w3-border profile-control js-iban-bank <?php echo $inputBg; ?>" type="text" name="bank_name" id="sepa-bank-new" autocomplete="organization" />
          </div>
          <div class="person-sepa-meta">
            <div class="profile-field">
              <label class="profile-label" for="sepa-from-new">Gültig ab</label>
              <input id="sepa-from-new" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="valid_from" value="<?php echo h($today); ?>" required />
            </div>
            <div class="profile-field">
              <label class="profile-label" for="sepa-to-new">Gültig bis</label>
              <input id="sepa-to-new" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="valid_to" />
            </div>
          </div>
          <div class="profile-field person-action-submit">
            <button type="submit" class="w3-button w3-mobile <?php echo h($optionsDB['colorBtnSubmit']); ?>">Anlegen</button>
          </div>
        </form>
      </details>

<?php if($hasMembership) { ?>
      <div class="profile-field person-fee-field">
        <label class="profile-label" for="person-fee">Jahresbeitrag / €</label>
        <input id="person-fee" form="<?php echo h($profileFormId); ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="number" name="annual_fee_euro" step="0.01" min="<?php echo h($feeMinErm); ?>" value="<?php echo h($feeEuro); ?>" inputmode="decimal" />
      </div>
      <div class="profile-field">
        <label class="person-check"><input form="<?php echo h($profileFormId); ?>" type="checkbox" name="fee_reduced" value="1"<?php echo $feeReducedMem ? ' checked' : ''; ?> /> Ermäßigt (Studierende / Minderjährige)</label>
      </div>
<?php } ?>
    </section>
  </div>
  <div class="profile-actions person-stammdaten-actions">
    <button type="submit" form="<?php echo h($profileFormId); ?>" class="w3-button profile-btn-primary w3-mobile <?php echo h($optionsDB['colorBtnSubmit']); ?>">Stammdaten speichern</button>
  </div>
<?php } else { ?>
  <div class="profile-grid profile-grid--3 person-stammdaten">
    <section class="profile-col">
      <h3 class="profile-col-title">Person</h3>
      <div class="profile-field"><span class="profile-label">Name</span><div class="profile-value"><?php echo h($user->getName()); ?></div></div>
      <div class="profile-field"><span class="profile-label">Geburtstag</span><div class="profile-value"><?php echo $profile->Birthday ? h(germanDate($profile->Birthday)) : '—'; ?></div></div>
      <div class="profile-field"><span class="profile-label">Anschrift</span><div class="profile-value"><?php echo h($addr !== '' ? $addr : '—'); ?></div></div>
    </section>
    <section class="profile-col">
      <h3 class="profile-col-title">Kontakt</h3>
      <div class="profile-field"><span class="profile-label">E-Mail</span><div class="profile-value"><?php echo h((string)$user->Email !== '' ? (string)$user->Email : '—'); ?></div></div>
      <div class="profile-field"><span class="profile-label">Telefon</span><div class="profile-value"><?php echo h((string)$profile->Phone ?: '—'); ?></div></div>
    </section>
    <section class="profile-col person-col-bank">
      <h3 class="profile-col-title">Bank / SEPA</h3>
      <div class="profile-field"><span class="profile-label">Kontoinhaber</span><div class="profile-value"><?php echo h((string)$profile->AccountHolder ?: '—'); ?></div></div>
<?php if(count($mandates)) { ?>
      <ul class="person-meta-list">
<?php foreach($mandates as $mandate) {
    $ibanDisp = formatIbanDisplay((string)$mandate->IbanEnc);
    $bankDisp = trim((string)$mandate->BankName);
    if($bankDisp === '' && class_exists('BlzDirectory')) {
        $bankDisp = BlzDirectory::bankNameFromIban((string)$mandate->IbanEnc);
    }
?>
        <li>
          <?php echo h($ibanDisp); ?>
<?php if($bankDisp !== '') { ?>
          · <?php echo h($bankDisp); ?>
<?php } ?>
        </li>
<?php } ?>
      </ul>
<?php } else { ?>
      <p class="person-meta-empty">—</p>
<?php } ?>
<?php if($hasMembership && $membership->AnnualFeeCents !== null) { ?>
      <div class="profile-field"><span class="profile-label">Jahresbeitrag / €</span><div class="profile-value"><?php echo h(MembershipForm::formatEuroFromCents((int)$membership->AnnualFeeCents)); ?><?php echo ((int)$membership->FeeReduced === 1) ? ' (ermäßigt)' : ''; ?></div></div>
<?php } ?>
    </section>
  </div>
<?php } ?>

<?php if($showMembershipSection) { ?>
<section class="person-section" aria-labelledby="person-sec-mitgliedschaft">
  <h3 id="person-sec-mitgliedschaft" class="profile-col-title">Mitgliedschaft</h3>

<?php if(count($timelineEvents)) { ?>
  <ol class="person-timeline">
<?php foreach($timelineEvents as $ev) {
    $editKey = '';
    if($canEdit && $ev['kind'] === 'entry' && !empty($ev['periodId']) && isset($periodsById[(int)$ev['periodId']])) {
        $editKey = 'period';
    }
    elseif($canEdit && $ev['kind'] === 'exit' && !empty($ev['periodId']) && isset($periodsById[(int)$ev['periodId']])) {
        $editKey = 'exit';
    }
    elseif($canEdit && $ev['kind'] === 'type' && !empty($ev['typePeriodId']) && isset($typePeriodsById[(int)$ev['typePeriodId']])) {
        $editKey = 'type';
    }
?>
    <li class="person-timeline-item">
      <div class="person-timeline-marker" aria-hidden="true"></div>
      <div class="person-timeline-body">
        <div class="person-timeline-row">
          <time class="person-timeline-date" datetime="<?php echo h($ev['date']); ?>"><?php echo h(germanDate($ev['date'])); ?></time>
          <span class="mail-recipient-chip mail-recipient-chip--<?php echo h($ev['chipMod']); ?>"><?php echo h($ev['chip']); ?></span>
<?php if($ev['note'] !== '') { ?>
          <span class="person-timeline-note"><?php echo h($ev['note']); ?></span>
<?php } ?>
        </div>
<?php if($editKey === 'period') {
    $p = $periodsById[(int)$ev['periodId']];
    $pid = (int)$p->Index;
?>
        <details class="person-timeline-edit">
          <summary>Bearbeiten</summary>
          <form method="post" action="savePerson.php" class="person-period-form">
            <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
            <input type="hidden" name="period_id" value="<?php echo $pid; ?>" />
            <div class="person-period-dates">
              <div class="profile-field">
                <label class="profile-label" for="period-from-<?php echo $pid; ?>">Eintritt</label>
                <input id="period-from-<?php echo $pid; ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="date_from" value="<?php echo h(substr((string)$p->DateFrom, 0, 10)); ?>" required />
              </div>
              <div class="profile-field">
                <label class="profile-label" for="period-to-<?php echo $pid; ?>">Austritt</label>
                <input id="period-to-<?php echo $pid; ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="date_to" value="<?php echo $p->DateTo ? h(substr((string)$p->DateTo, 0, 10)) : ''; ?>" />
              </div>
            </div>
            <div class="profile-field">
              <label class="profile-label" for="period-reason-<?php echo $pid; ?>">Grund</label>
              <select id="period-reason-<?php echo $pid; ?>" class="w3-select w3-border profile-control <?php echo $inputBg; ?>" name="exit_reason">
                <option value="">—</option>
                <option value="austritt"<?php echo (string)$p->ExitReason === 'austritt' ? ' selected' : ''; ?>>Austritt</option>
                <option value="tod"<?php echo (string)$p->ExitReason === 'tod' ? ' selected' : ''; ?>>Tod</option>
              </select>
            </div>
            <div class="profile-field">
              <label class="profile-label" for="period-note-<?php echo $pid; ?>">Notiz</label>
              <input id="period-note-<?php echo $pid; ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="note" value="<?php echo h((string)$p->Note); ?>" />
            </div>
            <div class="person-sepa-actions">
              <button type="submit" name="action" value="update_period" class="w3-button w3-mobile <?php echo h($optionsDB['colorBtnSubmit']); ?>">Zeit speichern</button>
              <button type="submit" name="action" value="delete_period" class="w3-button w3-mobile <?php echo h($optionsDB['colorLogError']); ?>" data-confirm="Mitgliedschaftszeit löschen?" data-confirm-ok="Zeit löschen" data-confirm-ok-class="w3-btn w3-border w3-mobile <?php echo h($optionsDB['colorLogError']); ?>">Zeit löschen</button>
            </div>
          </form>
        </details>
<?php } elseif($editKey === 'exit') {
    $p = $periodsById[(int)$ev['periodId']];
    $pid = (int)$p->Index;
?>
        <details class="person-timeline-edit">
          <summary>Bearbeiten</summary>
          <form method="post" action="savePerson.php" class="person-period-form">
            <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
            <input type="hidden" name="period_id" value="<?php echo $pid; ?>" />
            <input type="hidden" name="date_from" value="<?php echo h(substr((string)$p->DateFrom, 0, 10)); ?>" />
            <div class="person-period-dates">
              <div class="profile-field">
                <label class="profile-label" for="exit-edit-to-<?php echo $pid; ?>">Austritt</label>
                <input id="exit-edit-to-<?php echo $pid; ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="date_to" value="<?php echo $p->DateTo ? h(substr((string)$p->DateTo, 0, 10)) : ''; ?>" required />
              </div>
              <div class="profile-field">
                <label class="profile-label" for="exit-edit-reason-<?php echo $pid; ?>">Grund</label>
                <select id="exit-edit-reason-<?php echo $pid; ?>" class="w3-select w3-border profile-control <?php echo $inputBg; ?>" name="exit_reason">
                  <option value="austritt"<?php echo (string)$p->ExitReason === 'austritt' ? ' selected' : ''; ?>>Austritt</option>
                  <option value="tod"<?php echo (string)$p->ExitReason === 'tod' ? ' selected' : ''; ?>>Tod</option>
                </select>
              </div>
            </div>
            <div class="profile-field">
              <label class="profile-label" for="exit-edit-note-<?php echo $pid; ?>">Notiz</label>
              <input id="exit-edit-note-<?php echo $pid; ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="note" value="<?php echo h((string)$p->Note); ?>" />
            </div>
            <div class="person-sepa-actions">
              <button type="submit" name="action" value="update_period" class="w3-button w3-mobile <?php echo h($optionsDB['colorBtnSubmit']); ?>">Zeit speichern</button>
              <button type="submit" name="action" value="delete_period" class="w3-button w3-mobile <?php echo h($optionsDB['colorLogError']); ?>" data-confirm="Mitgliedschaftszeit löschen?" data-confirm-ok="Zeit löschen" data-confirm-ok-class="w3-btn w3-border w3-mobile <?php echo h($optionsDB['colorLogError']); ?>">Zeit löschen</button>
            </div>
          </form>
        </details>
<?php } elseif($editKey === 'type') {
    $t = $typePeriodsById[(int)$ev['typePeriodId']];
    $tid = (int)$t->Index;
?>
        <details class="person-timeline-edit">
          <summary>Bearbeiten</summary>
          <form method="post" action="savePerson.php" class="person-period-form">
            <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
            <input type="hidden" name="type_period_id" value="<?php echo $tid; ?>" />
            <div class="profile-field">
              <label class="profile-label" for="type-kind-<?php echo $tid; ?>">Typ</label>
              <select id="type-kind-<?php echo $tid; ?>" class="w3-select w3-border profile-control <?php echo $inputBg; ?>" name="type" required>
                <option value="aktiv"<?php echo (string)$t->Type === 'aktiv' ? ' selected' : ''; ?>>aktiv</option>
                <option value="foerdernd"<?php echo (string)$t->Type === 'foerdernd' ? ' selected' : ''; ?>>fördernd</option>
              </select>
            </div>
            <div class="person-period-dates">
              <div class="profile-field">
                <label class="profile-label" for="type-from-<?php echo $tid; ?>">Von</label>
                <input id="type-from-<?php echo $tid; ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="date_from" value="<?php echo h(substr((string)$t->DateFrom, 0, 10)); ?>" required />
              </div>
              <div class="profile-field">
                <label class="profile-label" for="type-to-<?php echo $tid; ?>">Bis</label>
                <input id="type-to-<?php echo $tid; ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="date_to" value="<?php echo $t->DateTo ? h(substr((string)$t->DateTo, 0, 10)) : ''; ?>" />
              </div>
            </div>
            <div class="profile-field">
              <label class="profile-label" for="type-note-<?php echo $tid; ?>">Notiz</label>
              <input id="type-note-<?php echo $tid; ?>" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="note" value="<?php echo h((string)$t->Note); ?>" />
            </div>
            <div class="person-sepa-actions">
              <button type="submit" name="action" value="update_type_period" class="w3-button w3-mobile <?php echo h($optionsDB['colorBtnSubmit']); ?>">Zeit speichern</button>
              <button type="submit" name="action" value="delete_type_period" class="w3-button w3-mobile <?php echo h($optionsDB['colorLogError']); ?>" data-confirm="Typzeit löschen?" data-confirm-ok="Zeit löschen" data-confirm-ok-class="w3-btn w3-border w3-mobile <?php echo h($optionsDB['colorLogError']); ?>">Zeit löschen</button>
            </div>
          </form>
        </details>
<?php } ?>
      </div>
    </li>
<?php } ?>
  </ol>
<?php } ?>

  <div class="person-workflow<?php echo $openTenure ? ' person-workflow--member' : ''; ?>">
    <div class="person-workflow-status">
      <span class="profile-label">Status</span>
      <div class="profile-value">
        <?php echo h($memberLabel); ?>
<?php if($openTenure && $entryDateLabel !== '') { ?>
        <span class="person-workflow-meta">seit <?php echo h($entryDateLabel); ?></span>
<?php } ?>
      </div>
    </div>
<?php if($canEdit) { ?>
    <div class="person-workflow-actions">
<?php if($pendingApp) { ?>
      <a class="w3-button w3-mobile <?php echo h($optionsDB['colorBtnEdit']); ?>" href="membership-form.php?id=<?php echo (int)$pendingApp->Index; ?>">
        <i class="fas fa-file-signature"></i>
        Beitrittsformular
        <span class="person-workflow-meta">#<?php echo (int)$pendingApp->Index; ?> · <?php echo h((string)$pendingApp->Status); ?></span>
      </a>
<?php } else { ?>
      <a class="w3-button w3-mobile <?php echo h($optionsDB['colorBtnEdit']); ?>" href="membership-form.php?user=<?php echo (int)$userId; ?>">
        <i class="fas fa-file-signature"></i>
        Beitrittsformular
      </a>
<?php } ?>
    </div>
<?php if(!$openTenure) { ?>
    <details class="person-workflow-manual">
      <summary>Manuell ohne Scan</summary>
      <form method="post" action="savePerson.php" class="person-action-form">
        <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
        <input type="hidden" name="action" value="open_tenure" />
        <div class="person-action-grid">
          <div class="profile-field">
            <label class="profile-label" for="tenure-from">Eintritt</label>
            <input id="tenure-from" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="tenure_from" value="<?php echo h($today); ?>" required />
          </div>
          <div class="profile-field">
            <label class="profile-label" for="tenure-type">Typ</label>
            <select id="tenure-type" class="w3-select w3-border profile-control <?php echo $inputBg; ?>" name="tenure_type">
              <option value="aktiv">aktiv</option>
              <option value="foerdernd">fördernd</option>
            </select>
          </div>
          <div class="profile-field person-action-submit">
            <button type="submit" class="w3-button w3-mobile <?php echo h($optionsDB['colorBtnSubmit']); ?>">Eintritt</button>
          </div>
        </div>
      </form>
    </details>
<?php } else { ?>
    <form method="post" action="savePerson.php" class="person-action-form">
      <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
      <input type="hidden" name="action" value="switch_type" />
      <div class="person-action-grid">
        <div class="profile-field">
          <label class="profile-label" for="type-from">Typwechsel ab</label>
          <input id="type-from" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="type_from" value="<?php echo h($today); ?>" required />
        </div>
        <div class="profile-field">
          <label class="profile-label" for="new-type">Neuer Typ</label>
          <select id="new-type" class="w3-select w3-border profile-control <?php echo $inputBg; ?>" name="new_type">
            <option value="aktiv"<?php echo $currentType === 'aktiv' ? ' selected' : ''; ?>>aktiv</option>
            <option value="foerdernd"<?php echo $currentType === 'foerdernd' ? ' selected' : ''; ?>>fördernd</option>
          </select>
        </div>
        <div class="profile-field">
          <label class="profile-label" for="type-note">Notiz</label>
          <input id="type-note" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="type_note" />
        </div>
        <div class="profile-field person-action-submit">
          <button type="submit" class="w3-button w3-mobile <?php echo h($optionsDB['colorBtnSubmit']); ?>">Wechseln</button>
        </div>
      </div>
    </form>

    <form method="post" action="savePerson.php" class="person-action-form">
      <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
      <input type="hidden" name="action" value="close_tenure" />
      <div class="person-action-grid">
        <div class="profile-field">
          <label class="profile-label" for="tenure-to">Austritt</label>
          <input id="tenure-to" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="tenure_to" value="<?php echo h($today); ?>" required />
        </div>
        <div class="profile-field">
          <label class="profile-label" for="exit-reason">Grund</label>
          <select id="exit-reason" class="w3-select w3-border profile-control <?php echo $inputBg; ?>" name="exit_reason">
            <option value="austritt">Austritt</option>
            <option value="tod">Tod</option>
          </select>
        </div>
        <div class="profile-field person-action-submit">
          <button type="submit" class="w3-button w3-mobile <?php echo h($optionsDB['colorLogError']); ?>" data-confirm="Mitgliedschaft beenden?" data-confirm-ok="Beenden" data-confirm-ok-class="w3-btn w3-border w3-mobile <?php echo h($optionsDB['colorLogError']); ?>">Beenden</button>
        </div>
      </div>
    </form>
<?php } ?>
<?php } ?>
  </div>

<?php if(count($appliedApplications)) { ?>
  <h4 class="person-meta-heading">Anträge</h4>
  <ul class="person-meta-list">
<?php foreach($appliedApplications as $a) { ?>
    <li class="person-app-row">
      <a href="membership-form.php?id=<?php echo (int)$a->Index; ?>">#<?php echo (int)$a->Index; ?></a>
      · <?php echo h((string)$a->Status); ?>
      · <?php echo h((string)$a->DesiredType); ?>
<?php if($a->DesiredEntryDate) { ?> · <?php echo h(germanDate($a->DesiredEntryDate)); ?><?php } ?>
<?php if($canEdit) { ?>
      <form method="post" action="savePerson.php" class="person-inline-delete">
        <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
        <input type="hidden" name="application_id" value="<?php echo (int)$a->Index; ?>" />
        <input type="hidden" name="action" value="delete_application" />
        <button type="submit" class="w3-button w3-small <?php echo h($optionsDB['colorLogError']); ?>" data-confirm="Antrag löschen? Mitgliedschaft bleibt unverändert." data-confirm-ok="Löschen" data-confirm-ok-class="w3-btn w3-border w3-mobile <?php echo h($optionsDB['colorLogError']); ?>">Löschen</button>
      </form>
<?php } ?>
    </li>
<?php } ?>
  </ul>
<?php } ?>
</section>
<?php } ?>

<section class="person-section" aria-labelledby="person-sec-dokumente">
  <h3 id="person-sec-dokumente" class="profile-col-title">Dokumente</h3>
<?php if(count($documents)) { ?>
  <ul class="person-doc-list">
<?php foreach($documents as $doc) {
    $hasFile = $doc->absolutePath() !== null;
    $uploadedLabel = $doc->UploadedAt ? germanDate(substr((string)$doc->UploadedAt, 0, 10)) : '';
    $docId = (int)$doc->Index;
?>
    <li class="person-doc-item">
      <div class="person-doc-main">
        <span class="person-doc-type"><?php echo h((string)$doc->DocType); ?></span>
<?php if($hasFile) { ?>
        <a class="person-doc-name" href="getDocument.php?id=<?php echo $docId; ?>" target="_blank" rel="noopener noreferrer"><?php echo h($doc->displayName()); ?></a>
<?php } else { ?>
        <span class="person-doc-name"><?php echo h($doc->displayName()); ?></span>
<?php } ?>
<?php if($uploadedLabel !== '') { ?>
        <span class="person-doc-date"><?php echo h($uploadedLabel); ?></span>
<?php } ?>
      </div>
<?php if($canEdit) { ?>
      <form method="post" action="savePerson.php" class="person-doc-delete" data-confirm="Dokument löschen?" data-confirm-ok="Löschen" data-confirm-ok-class="w3-btn w3-border w3-mobile <?php echo h($optionsDB['colorLogError']); ?>">
        <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
        <input type="hidden" name="document_id" value="<?php echo $docId; ?>" />
        <button type="submit" name="action" value="document_delete" class="w3-button w3-mobile <?php echo h($optionsDB['colorLogError']); ?>">Löschen</button>
      </form>
<?php } ?>
    </li>
<?php } ?>
  </ul>
<?php } else { ?>
  <p class="person-meta-empty">—</p>
<?php } ?>
<?php if($canEdit) { ?>
  <form method="post" action="savePerson.php" class="person-doc-upload" enctype="multipart/form-data">
    <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
    <input type="hidden" name="action" value="document_upload" />
    <h4 class="person-meta-heading">Hochladen</h4>
    <div class="person-doc-upload-grid">
      <div class="profile-field">
        <label class="profile-label" for="doc-type">Typ</label>
        <select id="doc-type" class="w3-select w3-border profile-control <?php echo $inputBg; ?>" name="doc_type" required>
<?php foreach(Document::allowedTypes() as $t) { ?>
          <option value="<?php echo h($t); ?>"<?php echo $t === Document::TYPE_BEITRITT ? ' selected' : ''; ?>><?php echo h($t); ?></option>
<?php } ?>
        </select>
      </div>
      <div class="profile-field person-doc-upload-file">
        <label class="profile-label" for="doc-file">Datei</label>
        <input id="doc-file" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required />
      </div>
      <div class="profile-field">
        <label class="profile-label" for="doc-note">Notiz</label>
        <input id="doc-note" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="doc_note" />
      </div>
    </div>
    <div class="profile-actions">
      <button type="submit" class="w3-button profile-btn-primary w3-mobile <?php echo h($optionsDB['colorBtnSubmit']); ?>">Hochladen</button>
    </div>
  </form>
<?php } ?>
</section>

<?php if($canShowJubilees) { ?>
<section class="person-section" aria-labelledby="person-sec-jubilaeen">
  <h3 id="person-sec-jubilaeen" class="profile-col-title">Jubiläen <a class="person-section-link" href="calendar.php" title="Kalender"><i class="fas fa-calendar-alt" aria-hidden="true"></i></a></h3>
<?php if(count($pastJubilees) || count($nextJubilees)) { ?>
  <ul class="person-meta-list person-jubilee-list">
<?php foreach($pastJubilees as $jub) { ?>
    <li class="person-jubilee person-jubilee--past">
      <?php echo h(JubileeCalendar::formatTitle($jub)); ?>
      · <?php echo h(germanDate($jub['date'])); ?>
    </li>
<?php } ?>
<?php foreach($nextJubilees as $jub) { ?>
    <li class="person-jubilee person-jubilee--next">
      <?php echo h(JubileeCalendar::formatTitle($jub)); ?>
      · <?php echo h(germanDate($jub['date'])); ?>
    </li>
<?php } ?>
  </ul>
<?php } else { ?>
  <p class="person-meta-empty">—</p>
<?php } ?>
</section>
<?php } ?>

<?php if($canEdit && !$isMemberToday) { ?>
<section class="person-section" aria-labelledby="person-sec-loeschen">
  <h3 id="person-sec-loeschen" class="profile-col-title">Löschen</h3>
  <form method="post" action="savePerson.php" data-confirm="Person unwiderruflich löschen? Mitgliedschaftsdaten, SEPA, Dokumente und Stammdaten werden entfernt." data-confirm-ok="Person löschen" data-confirm-ok-class="w3-btn w3-border w3-mobile <?php echo h($optionsDB['colorLogError']); ?>">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
    <input type="hidden" name="action" value="delete_person" />
    <button type="submit" class="w3-button w3-mobile <?php echo h($optionsDB['colorLogError']); ?>">Person löschen</button>
  </form>
</section>
<?php } ?>
</div>
<?php
adminListPageEnd();
include 'common/footer.php';
?>
