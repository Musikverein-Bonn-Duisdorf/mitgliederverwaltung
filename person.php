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

$memberLabel = $isMemberToday
    ? (($currentType === 'foerdernd') ? 'fördernd' : 'aktiv')
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
      <div class="profile-field">
        <label class="profile-label" for="person-phone2">Handy</label>
        <input id="person-phone2" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="tel" name="phone2" value="<?php echo h((string)$profile->Phone2); ?>" />
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
    <div class="profile-field"><span class="profile-label">Handy</span><div class="profile-value"><?php echo h((string)$profile->Phone2 ?: '—'); ?></div></div>
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

<?php if(!$openTenure) { ?>
  <div class="person-workflow">
    <div class="person-workflow-status">
      <span class="profile-label">Status</span>
      <div class="profile-value"><?php echo h($memberLabel); ?></div>
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
  </div>

<?php } else { ?>
  <div class="person-workflow person-workflow--member">
    <div class="person-workflow-status">
      <span class="profile-label">Status</span>
      <div class="profile-value">
        <?php echo h($memberLabel); ?>
<?php if($entryDateLabel !== '') { ?>
        <span class="person-workflow-meta">seit <?php echo h($entryDateLabel); ?></span>
<?php } ?>
      </div>
    </div>

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
  </div>
<?php } ?>
</section>
<?php } ?>

<section class="person-section" aria-labelledby="person-sec-verlauf">
  <h3 id="person-sec-verlauf" class="profile-col-title">Verlauf</h3>
  <div class="person-meta-grid person-meta-grid--2">
    <div class="person-meta-block">
      <h4 class="person-meta-heading">Tenure</h4>
<?php if(count($periods)) { ?>
      <ul class="person-meta-list">
<?php foreach($periods as $p) { ?>
        <li>
          <?php echo h(germanDate($p->DateFrom)); ?>
          — <?php echo $p->DateTo ? h(germanDate($p->DateTo)) : 'offen'; ?>
<?php if($p->ExitReason) { ?> <span class="person-meta-note">(<?php echo h((string)$p->ExitReason); ?>)</span><?php } ?>
<?php if($p->Note) { ?> <span class="person-meta-note"><?php echo h((string)$p->Note); ?></span><?php } ?>
        </li>
<?php } ?>
      </ul>
<?php } else { ?>
      <p class="person-meta-empty">—</p>
<?php } ?>
    </div>
    <div class="person-meta-block">
      <h4 class="person-meta-heading">Typ</h4>
<?php if(count($typePeriods)) { ?>
      <ul class="person-meta-list">
<?php foreach($typePeriods as $t) { ?>
        <li>
          <strong><?php echo h((string)$t->Type); ?></strong>
          <?php echo h(germanDate($t->DateFrom)); ?>
          — <?php echo $t->DateTo ? h(germanDate($t->DateTo)) : 'offen'; ?>
<?php if($t->Note) { ?> <span class="person-meta-note"><?php echo h((string)$t->Note); ?></span><?php } ?>
        </li>
<?php } ?>
      </ul>
<?php } else { ?>
      <p class="person-meta-empty">—</p>
<?php } ?>
    </div>
  </div>
</section>

<section class="person-section" aria-labelledby="person-sec-anhang">
  <h3 id="person-sec-anhang" class="profile-col-title">SEPA &amp; Dokumente</h3>
  <div class="person-meta-grid person-meta-grid--2">
    <div class="person-meta-block">
      <h4 class="person-meta-heading">SEPA</h4>
<?php if(count($mandates)) { ?>
      <ul class="person-meta-list">
<?php foreach($mandates as $mandate) { ?>
        <li>
          <?php echo h((string)$mandate->MandateRef); ?>
          — <?php echo h($mandate->maskedIban()); ?>
          — <?php echo ((int)$mandate->Active === 1) ? 'aktiv' : 'inaktiv'; ?>
        </li>
<?php } ?>
      </ul>
<?php } else { ?>
      <p class="person-meta-empty"><a href="sepa.php">SEPA-Liste</a></p>
<?php } ?>
    </div>
    <div class="person-meta-block">
      <h4 class="person-meta-heading">Dokumente</h4>
<?php if(count($documents)) { ?>
      <ul class="person-meta-list">
<?php foreach($documents as $doc) { ?>
        <li><?php echo h($doc->DocType); ?>: <code><?php echo h($doc->NextcloudPath); ?></code></li>
<?php } ?>
      </ul>
<?php } else { ?>
      <p class="person-meta-empty">—</p>
<?php } ?>
<?php if($canEdit) { ?>
      <p class="person-meta-actions"><a class="w3-button w3-small <?php echo h($optionsDB['colorBtnSubmit']); ?>" href="documents.php?user=<?php echo (int)$userId; ?>">Dokument</a></p>
<?php } ?>
    </div>
  </div>
</section>
</div>
<?php
adminListPageEnd();
include 'common/footer.php';
?>
