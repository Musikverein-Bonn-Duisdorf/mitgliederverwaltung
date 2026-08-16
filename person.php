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
$feeMin = '';
if($hasMembership) {
    $feeEuro = $membership->AnnualFeeCents !== null
        ? number_format(((int)$membership->AnnualFeeCents) / 100, 2, '.', '')
        : number_format(MembershipForm::minFeeCents($currentType ?: 'aktiv') / 100, 2, '.', '');
    $feeMin = number_format(MembershipForm::minFeeCents($currentType ?: 'aktiv') / 100, 2, '.', '');
}
$addr = trim(implode(', ', array_filter(array(
    $profile->Street,
    trim((string)$profile->Zip.' '.$profile->City),
    $profile->Country,
))));

$pendingApp = null;
foreach($applications as $a) {
    if((string)$a->Status !== 'applied') {
        $pendingApp = $a;
        break;
    }
}
$entryDateLabel = ($openTenure && $openTenure->DateFrom) ? germanDate($openTenure->DateFrom) : '';
?>
<div class="person-page">
<?php if($canEdit) { ?>
<form method="post" action="savePerson.php" class="profile-form person-stammdaten">
  <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
  <input type="hidden" name="action" value="save_profile" />

  <div class="profile-grid profile-grid--3">
    <section class="profile-col" aria-labelledby="person-col-person">
      <h3 id="person-col-person" class="profile-col-title">Person</h3>
      <div class="profile-field">
        <label class="profile-label" for="person-vorname">Vorname</label>
        <input id="person-vorname" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="vorname" value="<?php echo h((string)$user->Vorname); ?>" required autocomplete="given-name" />
      </div>
      <div class="profile-field">
        <label class="profile-label" for="person-nachname">Nachname</label>
        <input id="person-nachname" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="nachname" value="<?php echo h((string)$user->Nachname); ?>" required autocomplete="family-name" />
      </div>
      <div class="profile-field">
        <label class="profile-label" for="person-birthday">Geburtstag</label>
        <input id="person-birthday" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="birthday" value="<?php echo h((string)$profile->Birthday); ?>" />
      </div>
      <div class="profile-field">
        <span class="profile-label">Login</span>
        <div class="profile-value"><?php echo h((string)$user->login !== '' ? (string)$user->login : '—'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Mitgliedschaft</span>
        <div class="profile-value"><?php echo h($memberLabel); ?></div>
      </div>
    </section>

    <section class="profile-col" aria-labelledby="person-col-kontakt">
      <h3 id="person-col-kontakt" class="profile-col-title">Kontakt</h3>
      <div class="profile-field">
        <label class="profile-label" for="person-email">E-Mail</label>
        <input id="person-email" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="email" name="email" value="<?php echo h((string)$user->Email); ?>" autocomplete="email" />
      </div>
      <div class="profile-field">
        <label class="profile-label" for="person-email2">E-Mail 2</label>
        <input id="person-email2" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="email" name="email2" value="<?php echo h((string)$user->Email2); ?>" />
      </div>
      <div class="profile-field">
        <label class="profile-label" for="person-phone">Telefon</label>
        <input id="person-phone" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="tel" name="phone" value="<?php echo h((string)$profile->Phone); ?>" autocomplete="tel" />
      </div>
    </section>

    <section class="profile-col" aria-labelledby="person-col-adresse">
      <h3 id="person-col-adresse" class="profile-col-title">Adresse &amp; Beitrag</h3>
      <div class="profile-field">
        <label class="profile-label" for="person-street">Straße</label>
        <input id="person-street" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="street" value="<?php echo h((string)$profile->Street); ?>" autocomplete="street-address" />
      </div>
      <div class="profile-fields-inline">
        <div class="profile-field">
          <label class="profile-label" for="person-zip">PLZ</label>
          <input id="person-zip" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="zip" value="<?php echo h((string)$profile->Zip); ?>" autocomplete="postal-code" />
        </div>
        <div class="profile-field">
          <label class="profile-label" for="person-city">Ort</label>
          <input id="person-city" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="city" value="<?php echo h((string)$profile->City); ?>" autocomplete="address-level2" />
        </div>
        <div class="profile-field">
          <label class="profile-label" for="person-country">Land</label>
          <input id="person-country" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="country" value="<?php echo h((string)$profile->Country); ?>" autocomplete="country" />
        </div>
      </div>
      <div class="profile-field">
        <label class="profile-label" for="person-holder">Kontoinhaber</label>
        <input id="person-holder" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="account_holder" value="<?php echo h((string)$profile->AccountHolder); ?>" />
      </div>
<?php if($hasMembership) { ?>
      <div class="profile-field">
        <label class="profile-label" for="person-fee">Jahresbeitrag (€)</label>
        <input id="person-fee" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="number" name="annual_fee_euro" step="0.01" min="<?php echo h($feeMin); ?>" value="<?php echo h($feeEuro); ?>" inputmode="decimal" />
      </div>
<?php } ?>
      <div class="profile-actions">
        <button type="submit" class="w3-button profile-btn-primary <?php echo h($optionsDB['colorBtnSubmit']); ?>">Speichern</button>
      </div>
    </section>
  </div>
</form>
<?php } else { ?>
<div class="profile-grid profile-grid--3">
  <section class="profile-col">
    <h3 class="profile-col-title">Person</h3>
    <div class="profile-field"><span class="profile-label">Name</span><div class="profile-value"><?php echo h($user->getName()); ?></div></div>
    <div class="profile-field"><span class="profile-label">Geburtstag</span><div class="profile-value"><?php echo $profile->Birthday ? h(germanDate($profile->Birthday)) : '—'; ?></div></div>
    <div class="profile-field"><span class="profile-label">Login</span><div class="profile-value"><?php echo h((string)$user->login !== '' ? (string)$user->login : '—'); ?></div></div>
    <div class="profile-field"><span class="profile-label">Mitgliedschaft</span><div class="profile-value"><?php echo h($memberLabel); ?></div></div>
  </section>
  <section class="profile-col">
    <h3 class="profile-col-title">Kontakt</h3>
    <div class="profile-field"><span class="profile-label">E-Mail</span><div class="profile-value"><?php echo h((string)$user->Email !== '' ? (string)$user->Email : '—'); ?></div></div>
    <div class="profile-field"><span class="profile-label">E-Mail 2</span><div class="profile-value"><?php echo h((string)$user->Email2 !== '' ? (string)$user->Email2 : '—'); ?></div></div>
    <div class="profile-field"><span class="profile-label">Telefon</span><div class="profile-value"><?php echo h((string)$profile->Phone ?: '—'); ?></div></div>
  </section>
  <section class="profile-col">
    <h3 class="profile-col-title">Adresse &amp; Beitrag</h3>
    <div class="profile-field"><span class="profile-label">Anschrift</span><div class="profile-value"><?php echo h($addr !== '' ? $addr : '—'); ?></div></div>
    <div class="profile-field"><span class="profile-label">Kontoinhaber</span><div class="profile-value"><?php echo h((string)$profile->AccountHolder ?: '—'); ?></div></div>
<?php if($hasMembership && $membership->AnnualFeeCents !== null) { ?>
    <div class="profile-field"><span class="profile-label">Jahresbeitrag</span><div class="profile-value"><?php echo h(MembershipForm::formatEuroFromCents((int)$membership->AnnualFeeCents)); ?></div></div>
<?php } ?>
  </section>
</div>
<?php } ?>

<?php if($canEdit) { ?>
<section class="person-section" aria-labelledby="person-sec-mitgliedschaft">
  <h3 id="person-sec-mitgliedschaft" class="profile-col-title">Mitgliedschaft</h3>

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
    <div class="person-workflow-actions">
<?php if($pendingApp) { ?>
      <a class="w3-button <?php echo h($optionsDB['colorBtnEdit']); ?>" href="membership-form.php?id=<?php echo (int)$pendingApp->Index; ?>">
        <i class="fas fa-file-signature"></i>
        Beitrittsformular
        <span class="person-workflow-meta">#<?php echo (int)$pendingApp->Index; ?> · <?php echo h((string)$pendingApp->Status); ?></span>
      </a>
<?php } else { ?>
      <a class="w3-button <?php echo h($optionsDB['colorBtnEdit']); ?>" href="membership-form.php?user=<?php echo (int)$userId; ?>">
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
            <button type="submit" class="w3-button <?php echo h($optionsDB['colorBtnSubmit']); ?>">Eintritt</button>
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
          <button type="submit" class="w3-button <?php echo h($optionsDB['colorBtnSubmit']); ?>">Wechseln</button>
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
          <button type="submit" class="w3-button <?php echo h($optionsDB['colorLogError']); ?>" onclick="return confirm('Mitgliedschaft beenden?');">Beenden</button>
        </div>
      </div>
    </form>
<?php } ?>
  </div>
</section>
<?php } ?>

<?php if($canShowJubilees) { ?>
<section class="person-section" aria-labelledby="person-sec-jubilaeen">
  <h3 id="person-sec-jubilaeen" class="profile-col-title">Jubiläen <a class="person-section-link" href="calendar.php" title="Kalender"><i class="fas fa-calendar-alt" aria-hidden="true"></i></a></h3>
<?php if(count($nextJubilees)) { ?>
  <ul class="person-meta-list">
<?php foreach($nextJubilees as $jub) { ?>
    <li>
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

<section class="person-section" aria-labelledby="person-sec-verlauf">
  <h3 id="person-sec-verlauf" class="profile-col-title">Verlauf</h3>
  <div class="person-meta-grid person-meta-grid--2">
    <div class="person-meta-block">
      <h4 class="person-meta-heading">Mitgliedszeiten</h4>
<?php if(count($periods)) { ?>
      <ul class="person-meta-list person-edit-list">
<?php foreach($periods as $p) { ?>
        <li>
<?php if($canEdit) { ?>
          <form method="post" action="savePerson.php" class="person-inline-edit">
            <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
            <input type="hidden" name="period_id" value="<?php echo (int)$p->Index; ?>" />
            <div class="person-inline-grid">
              <input class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="date_from" value="<?php echo h(substr((string)$p->DateFrom, 0, 10)); ?>" required title="Von" />
              <input class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="date_to" value="<?php echo $p->DateTo ? h(substr((string)$p->DateTo, 0, 10)) : ''; ?>" title="Bis (leer = offen)" />
              <select class="w3-select w3-border profile-control <?php echo $inputBg; ?>" name="exit_reason" title="Grund">
                <option value="">—</option>
                <option value="austritt"<?php echo (string)$p->ExitReason === 'austritt' ? ' selected' : ''; ?>>Austritt</option>
                <option value="tod"<?php echo (string)$p->ExitReason === 'tod' ? ' selected' : ''; ?>>Tod</option>
              </select>
              <input class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="note" value="<?php echo h((string)$p->Note); ?>" placeholder="Notiz" />
              <button type="submit" name="action" value="update_period" class="w3-button w3-small <?php echo h($optionsDB['colorBtnSubmit']); ?>">OK</button>
              <button type="submit" name="action" value="delete_period" class="w3-button w3-small <?php echo h($optionsDB['colorLogError']); ?>" onclick="return confirm('Mitgliedszeit löschen?');">×</button>
            </div>
          </form>
<?php } else { ?>
          <?php echo h(germanDate($p->DateFrom)); ?>
          — <?php echo $p->DateTo ? h(germanDate($p->DateTo)) : 'offen'; ?>
<?php if($p->ExitReason) { ?> <span class="person-meta-note">(<?php echo h((string)$p->ExitReason); ?>)</span><?php } ?>
<?php if($p->Note) { ?> <span class="person-meta-note"><?php echo h((string)$p->Note); ?></span><?php } ?>
<?php } ?>
        </li>
<?php } ?>
      </ul>
<?php } else { ?>
      <p class="person-meta-empty">—</p>
<?php } ?>
    </div>
    <div class="person-meta-block">
      <h4 class="person-meta-heading">Typzeiten</h4>
<?php if(count($typePeriods)) { ?>
      <ul class="person-meta-list person-edit-list">
<?php foreach($typePeriods as $t) { ?>
        <li>
<?php if($canEdit) { ?>
          <form method="post" action="savePerson.php" class="person-inline-edit">
            <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
            <input type="hidden" name="type_period_id" value="<?php echo (int)$t->Index; ?>" />
            <div class="person-inline-grid">
              <select class="w3-select w3-border profile-control <?php echo $inputBg; ?>" name="type" required>
                <option value="aktiv"<?php echo (string)$t->Type === 'aktiv' ? ' selected' : ''; ?>>aktiv</option>
                <option value="foerdernd"<?php echo (string)$t->Type === 'foerdernd' ? ' selected' : ''; ?>>fördernd</option>
              </select>
              <input class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="date_from" value="<?php echo h(substr((string)$t->DateFrom, 0, 10)); ?>" required title="Von" />
              <input class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="date_to" value="<?php echo $t->DateTo ? h(substr((string)$t->DateTo, 0, 10)) : ''; ?>" title="Bis (leer = offen)" />
              <input class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="note" value="<?php echo h((string)$t->Note); ?>" placeholder="Notiz" />
              <button type="submit" name="action" value="update_type_period" class="w3-button w3-small <?php echo h($optionsDB['colorBtnSubmit']); ?>">OK</button>
              <button type="submit" name="action" value="delete_type_period" class="w3-button w3-small <?php echo h($optionsDB['colorLogError']); ?>" onclick="return confirm('Typzeit löschen?');">×</button>
            </div>
          </form>
<?php } else { ?>
          <strong><?php echo h((string)$t->Type); ?></strong>
          <?php echo h(germanDate($t->DateFrom)); ?>
          — <?php echo $t->DateTo ? h(germanDate($t->DateTo)) : 'offen'; ?>
<?php if($t->Note) { ?> <span class="person-meta-note"><?php echo h((string)$t->Note); ?></span><?php } ?>
<?php } ?>
        </li>
<?php } ?>
      </ul>
<?php } else { ?>
      <p class="person-meta-empty">—</p>
<?php } ?>
    </div>
  </div>
</section>

<section class="person-section" aria-labelledby="person-sec-antraege">
  <h3 id="person-sec-antraege" class="profile-col-title">Anträge</h3>
<?php if(count($applications)) { ?>
  <ul class="person-meta-list">
<?php foreach($applications as $a) { ?>
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
        <button type="submit" class="w3-button w3-small <?php echo h($optionsDB['colorLogError']); ?>" onclick="return confirm('Antrag löschen? Mitgliedschaft bleibt unverändert.');">Löschen</button>
      </form>
<?php } ?>
    </li>
<?php } ?>
  </ul>
<?php } else { ?>
  <p class="person-meta-empty">—</p>
<?php } ?>
</section>

<section class="person-section" aria-labelledby="person-sec-anhang">
  <h3 id="person-sec-anhang" class="profile-col-title">SEPA &amp; Dokumente</h3>
  <div class="person-meta-grid person-meta-grid--2">
    <div class="person-meta-block">
      <h4 class="person-meta-heading">SEPA</h4>
<?php if(count($mandates)) { ?>
      <ul class="person-meta-list person-edit-list">
<?php foreach($mandates as $mandate) {
    $mid = (int)$mandate->Index;
    $ibanDisp = formatIbanDisplay((string)$mandate->IbanEnc);
    $bankDisp = trim((string)$mandate->BankName);
    if($bankDisp === '' && class_exists('BlzDirectory')) {
        $bankDisp = BlzDirectory::bankNameFromIban((string)$mandate->IbanEnc);
    }
    $bankFieldId = 'sepa-bank-'.$mid;
?>
        <li>
<?php if($canEdit) { ?>
          <form method="post" action="savePerson.php" class="person-inline-edit">
            <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
            <input type="hidden" name="mandate_id" value="<?php echo $mid; ?>" />
            <div class="person-inline-grid person-inline-grid--sepa">
              <input class="w3-input w3-border profile-control js-iban-check <?php echo $inputBg; ?>" type="text" name="iban" id="sepa-iban-<?php echo $mid; ?>" value="<?php echo h($ibanDisp); ?>" required data-iban-required="1" data-iban-bank="<?php echo h($bankFieldId); ?>" autocomplete="off" spellcheck="false" inputmode="text" title="IBAN" placeholder="IBAN" />
              <input class="w3-input w3-border profile-control js-iban-bank <?php echo $inputBg; ?>" type="text" name="bank_name" id="<?php echo h($bankFieldId); ?>" value="<?php echo h($bankDisp); ?>" autocomplete="organization" title="Kreditinstitut" placeholder="Kreditinstitut" />
              <input class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="valid_from" value="<?php echo h(substr((string)$mandate->ValidFrom, 0, 10)); ?>" required title="Gültig ab" />
              <input class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="valid_to" value="<?php echo $mandate->ValidTo ? h(substr((string)$mandate->ValidTo, 0, 10)) : ''; ?>" title="Gültig bis" />
              <label class="person-check"><input type="checkbox" name="active" value="1"<?php echo ((int)$mandate->Active === 1) ? ' checked' : ''; ?> /> aktiv</label>
              <button type="submit" name="action" value="sepa_update" class="w3-button w3-small <?php echo h($optionsDB['colorBtnSubmit']); ?>">OK</button>
              <button type="submit" name="action" value="sepa_delete" class="w3-button w3-small <?php echo h($optionsDB['colorLogError']); ?>" onclick="return confirm('Mandat löschen?');">×</button>
            </div>
          </form>
<?php } else { ?>
          <?php echo h((string)$mandate->MandateRef); ?>
          — <?php echo h($ibanDisp); ?>
<?php if($bankDisp !== '') { ?>
          — <?php echo h($bankDisp); ?>
<?php } ?>
          — <?php echo ((int)$mandate->Active === 1) ? 'aktiv' : 'inaktiv'; ?>
<?php } ?>
        </li>
<?php } ?>
      </ul>
<?php } else { ?>
      <p class="person-meta-empty">—</p>
<?php } ?>
<?php if($canEdit) { ?>
      <details class="person-workflow-manual">
        <summary>SEPA anlegen</summary>
        <form method="post" action="savePerson.php" class="person-action-form">
          <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
          <input type="hidden" name="action" value="sepa_create" />
          <div class="person-action-grid">
            <div class="profile-field">
              <label class="profile-label">IBAN</label>
              <input class="w3-input w3-border profile-control js-iban-check <?php echo $inputBg; ?>" type="text" name="iban" id="sepa-iban-new" required data-iban-required="1" data-iban-bank="sepa-bank-new" autocomplete="off" spellcheck="false" inputmode="text" />
            </div>
            <div class="profile-field">
              <label class="profile-label">Kreditinstitut</label>
              <input class="w3-input w3-border profile-control js-iban-bank <?php echo $inputBg; ?>" type="text" name="bank_name" id="sepa-bank-new" autocomplete="organization" />
            </div>
            <div class="profile-field">
              <label class="profile-label">Gültig ab</label>
              <input class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="valid_from" value="<?php echo h($today); ?>" required />
            </div>
            <div class="profile-field">
              <label class="profile-label">Gültig bis</label>
              <input class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="valid_to" />
            </div>
            <div class="profile-field">
              <label class="person-check"><input type="checkbox" name="active" value="1" checked /> aktiv</label>
            </div>
            <div class="profile-field person-action-submit">
              <button type="submit" class="w3-button <?php echo h($optionsDB['colorBtnSubmit']); ?>">Anlegen</button>
            </div>
          </div>
        </form>
      </details>
<?php } ?>
    </div>
    <div class="person-meta-block">
      <h4 class="person-meta-heading">Dokumente</h4>
<?php if(count($documents)) { ?>
      <ul class="person-meta-list person-edit-list">
<?php foreach($documents as $doc) {
    $hasFile = $doc->absolutePath() !== null;
    $uploadedLabel = $doc->UploadedAt ? germanDate(substr((string)$doc->UploadedAt, 0, 10)) : '';
?>
        <li class="person-doc-row">
          <span class="person-doc-type"><?php echo h((string)$doc->DocType); ?></span>
<?php if($hasFile) { ?>
          <a href="getDocument.php?id=<?php echo (int)$doc->Index; ?>" target="_blank" rel="noopener"><?php echo h($doc->displayName()); ?></a>
<?php } else { ?>
          <span><?php echo h($doc->displayName()); ?></span>
<?php } ?>
<?php if($uploadedLabel !== '') { ?>
          <span class="person-workflow-meta"><?php echo h($uploadedLabel); ?></span>
<?php } ?>
<?php if($canEdit) { ?>
          <form method="post" action="savePerson.php" class="person-inline-delete" onsubmit="return confirm('Dokument löschen?');">
            <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
            <input type="hidden" name="document_id" value="<?php echo (int)$doc->Index; ?>" />
            <button type="submit" name="action" value="document_delete" class="w3-button w3-small <?php echo h($optionsDB['colorLogError']); ?>" title="Löschen">×</button>
          </form>
<?php } ?>
        </li>
<?php } ?>
      </ul>
<?php } else { ?>
      <p class="person-meta-empty">—</p>
<?php } ?>
<?php if($canEdit) { ?>
      <details class="person-workflow-manual">
        <summary>Dokument hochladen</summary>
        <form method="post" action="savePerson.php" class="person-action-form" enctype="multipart/form-data">
          <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>" />
          <input type="hidden" name="action" value="document_upload" />
          <div class="person-action-grid">
            <div class="profile-field">
              <label class="profile-label">Typ</label>
              <select class="w3-select w3-border profile-control <?php echo $inputBg; ?>" name="doc_type" required>
<?php foreach(Document::allowedTypes() as $t) { ?>
                <option value="<?php echo h($t); ?>"<?php echo $t === Document::TYPE_BEITRITT ? ' selected' : ''; ?>><?php echo h($t); ?></option>
<?php } ?>
              </select>
            </div>
            <div class="profile-field">
              <label class="profile-label">Datei</label>
              <input class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,application/pdf,image/*" required />
            </div>
            <div class="profile-field">
              <label class="profile-label">Notiz</label>
              <input class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="doc_note" placeholder="optional" />
            </div>
            <div class="profile-field person-action-submit">
              <button type="submit" class="w3-button <?php echo h($optionsDB['colorBtnSubmit']); ?>">Hochladen</button>
            </div>
          </div>
        </form>
      </details>
<?php } ?>
    </div>
  </div>
</section>
</div>
<?php
adminListPageEnd();
include 'common/footer.php';
?>
