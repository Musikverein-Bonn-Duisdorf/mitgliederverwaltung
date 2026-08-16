<?php
/**
 * Printable Beitrittserklärung (admin): fill → print → scan upload → auto-apply.
 * Query: user=<meldeUserId> | id=<applicationId>
 */
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'members';
$_SESSION['adminpage'] = false;
include 'common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']);

if(!loggedIn()) {
    header('Location: login.php');
    exit;
}
requirePermission('perm_editUsers');

$appId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$userId = isset($_GET['user']) ? (int)$_GET['user'] : (isset($_POST['user']) ? (int)$_POST['user'] : 0);

$app = new MembershipApplication();
if($appId > 0) {
    if(!$app->load_by_id($appId)) {
        echo 'Antrag nicht gefunden.';
        exit;
    }
    $userId = (int)$app->User;
}
elseif($userId > 0) {
    $latest = MembershipApplication::latestForUser($userId);
    if($latest && $latest->Status !== 'applied') {
        $app = $latest;
    }
    else {
        $app->User = $userId;
        $app->DesiredType = 'aktiv';
        $app->DesiredEntryDate = date('Y-m-d');
        $app->Status = 'draft';
        $app->save();
    }
}
else {
    header('Location: members.php');
    exit;
}

$user = new IdentityUser();
if($userId < 1 || !$user->load_by_id($userId) || (int)$user->Deleted === 1) {
    echo 'Person nicht gefunden.';
    exit;
}

$profile = new MemberProfile();
$profile->load_or_create($userId);
MembershipForm::prefill($app, $user, $profile);

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
if(($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if($action === 'saveFields') {
        MembershipForm::applyPostFields($app, $_POST);
        $ibanCheck = normalizeIban((string)$app->Iban);
        if($app->PaymentMethod === 'sepa' && $ibanCheck !== '' && !isValidIban($ibanCheck)) {
            $_SESSION['membershipFormFlash'] = 'IBAN ungültig.';
            header('Location: membership-form.php?id='.(int)$app->Index);
            exit;
        }
        $syncErr = MembershipForm::syncPersonFromPost($user, $profile, $app, $_POST);
        if($syncErr !== '') {
            $_SESSION['membershipFormFlash'] = $syncErr;
            header('Location: membership-form.php?id='.(int)$app->Index);
            exit;
        }
        $app->save();
        header('Location: membership-form.php?id='.(int)$app->Index);
        exit;
    }
    if($action === 'apply') {
        if(isset($_POST['DesiredEntryDate']) || isset($_POST['DesiredType']) || isset($_POST['PaymentMethod'])) {
            MembershipForm::applyPostFields($app, $_POST);
            $app->save();
        }
        $entry = trim((string)($app->DesiredEntryDate ?: ''));
        if($entry === '') {
            $entry = date('Y-m-d');
            $app->DesiredEntryDate = $entry;
            $app->save();
        }
        if($app->apply($entry)) {
            $_SESSION['personFlash'] = 'Beitritt angewendet (Eintritt '.germanDate($entry).').';
            header('Location: person.php?id='.$userId);
            exit;
        }
        $_SESSION['membershipFormFlash'] = 'Anwenden fehlgeschlagen.';
        header('Location: membership-form.php?id='.(int)$app->Index);
        exit;
    }
    if($action === 'markReady') {
        MembershipForm::applyPostFields($app, $_POST);
        if($app->ScanFile) {
            $app->Status = 'ready';
        }
        $app->save();
        header('Location: membership-form.php?id='.(int)$app->Index);
        exit;
    }
}

$flash = '';
if(!empty($_SESSION['membershipFormFlash'])) {
    $flash = (string)$_SESSION['membershipFormFlash'];
    unset($_SESSION['membershipFormFlash']);
}

$h = function ($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
};

$hasScan = trim((string)$app->ScanFile) !== '';
$statusApplied = $app->Status === 'applied';
$applied = false; // nachträgliche Korrektur: Formular bleibt editierbar
$cssUrl = assetUrl('styles/membership-form.css');
$logoPath = is_file(__DIR__.'/imgs/Logo.png') ? 'imgs/Logo.png' : '';
$orgName = MembershipForm::orgName();
$brandBar = '#345A95';
if(isset($GLOBALS['optionsDB']['colorTitleBar'])) {
    $raw = (string)$GLOBALS['optionsDB']['colorTitleBar'];
    if(function_exists('normalizeHexColor')) {
        $hex = normalizeHexColor($raw);
        if($hex !== '') {
            $brandBar = $hex;
        }
    }
}

$typeAktiv = $app->DesiredType === 'aktiv';
$typeFoerdernd = $app->DesiredType === 'foerdernd';
$paySepa = ($app->PaymentMethod !== 'ueberweisung');
$creditor = MembershipForm::creditorBank();
$privacyParas = MembershipForm::privacyParagraphsHtml();
$membershipRules = MembershipForm::membershipRulesParagraphsHtml();
$feeReduced = (int)$app->FeeReduced === 1;
$minFees = MembershipForm::minFeeCentsByType();
$feeCents = MembershipForm::clampFeeCents(
    (int)($app->AnnualFeeCents ?: MembershipForm::minFeeCents($app->DesiredType, $feeReduced)),
    $app->DesiredType,
    $feeReduced
);
$feeEuroInput = number_format($feeCents / 100, 2, '.', '');
$sepaTexts = MembershipForm::sepaTextsHtml($feeCents, $app->DesiredType, $feeReduced);
$transferTexts = MembershipForm::transferTextsHtml($feeCents, $app->DesiredType, $feeReduced);
$mediaConsentParas = MembershipForm::mediaConsentParagraphsHtml();
$vornameInput = MembershipForm::identityNameForInput($user->Vorname, MembershipForm::STUB_VORNAME);
$nachnameInput = MembershipForm::identityNameForInput($user->Nachname, MembershipForm::STUB_NACHNAME);
$emailInput = trim((string)$user->Email);
$phoneFilled = MembershipForm::isFilled($app->Phone);
$birthdayFilled = MembershipForm::isFilled($app->Birthday);
$countryShow = MembershipForm::isFilled($app->Country) && strtoupper(trim((string)$app->Country)) !== 'DE';
$addrPrint = trim(implode(', ', array_filter(array(
    (string)$app->Street,
    trim((string)$app->Zip.' '.(string)$app->City),
    $countryShow ? (string)$app->Country : '',
))));
$typeLabelLong = $typeFoerdernd ? 'förderndes Mitglied' : 'aktives Mitglied';
$printFileBase = MembershipForm::fileBasename($userId, $vornameInput, $nachnameInput);
$isExistingMember = MembershipPeriod::userIsMemberOn($userId, date('Y-m-d'));
$uploadConfirm = ($statusApplied || $isExistingMember)
    ? 'Scan speichern / ersetzen? Die Mitgliedschaft bleibt unverändert.'
    : 'Scan hochladen und Beitritt mit dem Eintrittsdatum auf dem Formular anwenden?';
$dangerBtnClass = 'w3-btn w3-border w3-mobile '.(isset($optionsDB['colorLogError']) ? (string)$optionsDB['colorLogError'] : 'w3-red');

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $h($printFileBase); ?></title>
  <link rel="stylesheet" href="<?php echo $cssUrl; ?>">
</head>
<body class="loan-form-print">
  <div class="loan-form-toolbar no-print">
    <div class="loan-form-toolbar-group">
      <a class="loan-form-btn" href="person.php?id=<?php echo (int)$userId; ?>">Zurück</a>
      <button type="submit" form="membership-form-fields" class="loan-form-btn loan-form-btn--primary" name="action" value="saveFields">Speichern</button>
      <button type="button" class="loan-form-btn" onclick="window.print()">Drucken</button>
<?php if($statusApplied) { ?>
      <form method="POST" action="savePerson.php" class="loan-form-upload" style="display:inline;" data-confirm="Antrag löschen? Mitgliedschaft bleibt unverändert." data-confirm-ok="Antrag löschen" data-confirm-ok-class="<?php echo $h($dangerBtnClass); ?>">
        <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>">
        <input type="hidden" name="application_id" value="<?php echo (int)$app->Index; ?>">
        <input type="hidden" name="action" value="delete_application">
        <button type="submit" class="loan-form-btn">Antrag löschen</button>
      </form>
<?php } ?>
    </div>
    <div class="loan-form-toolbar-group loan-form-toolbar-group--scan">
<?php if($hasScan) { ?>
      <div class="loan-form-scan-pair">
        <a class="loan-form-btn loan-form-btn--scan" href="membership-contract.php?id=<?php echo (int)$app->Index; ?>">Scan anzeigen</a>
        <form class="loan-form-upload" method="POST" action="membership-contract.php" data-confirm="Scan löschen?" data-confirm-ok="Löschen" data-confirm-ok-class="<?php echo $h($dangerBtnClass); ?>">
          <input type="hidden" name="id" value="<?php echo (int)$app->Index; ?>">
          <input type="hidden" name="action" value="deleteScan">
          <button type="submit" class="loan-form-btn">Löschen</button>
        </form>
      </div>
<?php } ?>
      <label class="loan-form-btn loan-form-btn--file">
        PDF / JPG / PNG
        <input type="file" form="membership-form-fields" name="scan" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
      </label>
      <button type="submit"
              form="membership-form-fields"
              formaction="membership-contract.php"
              class="loan-form-btn loan-form-btn--primary"
              name="action"
              value="upload"
              id="membership-scan-upload"
              data-confirm="<?php echo $h($uploadConfirm); ?>"
              data-confirm-ok="Hochladen">Hochladen</button>
    </div>
  </div>

<?php if($flash !== '') { ?>
  <div class="loan-form-toolbar no-print"><p><?php echo $h($flash); ?></p></div>
<?php } ?>

  <form id="membership-form-fields" method="POST" action="membership-form.php?id=<?php echo (int)$app->Index; ?>" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?php echo (int)$app->Index; ?>">
    <input type="hidden" name="user" value="<?php echo (int)$userId; ?>">

  <article class="loan-form-doc membership-form-doc" style="--loan-brand: <?php echo $h($brandBar); ?>;">
    <header class="loan-form-header">
      <div class="loan-form-brand">
<?php if($logoPath !== '') { ?>
        <img class="loan-form-logo" src="<?php echo $h($logoPath); ?>" alt="">
<?php } ?>
        <div class="loan-form-brand-text">
          <p class="loan-form-org"><?php echo $h($orgName); ?></p>
          <h1>Beitrittserklärung</h1>
        </div>
      </div>
      <p class="loan-form-meta no-print">Antrag Nr. <?php echo (int)$app->Index; ?> · Status: <?php echo $h((string)$app->Status); ?></p>
    </header>

    <section class="loan-form-section loan-form-panel">
      <h2>Anschrift</h2>
      <dl class="loan-form-dl loan-form-dl--2col">
        <div>
          <dt>Vorname</dt>
          <dd>
<?php if(!$applied) { ?>
            <input class="loan-form-input no-print" type="text" name="Vorname" id="membership-Vorname" value="<?php echo $h($vornameInput); ?>" required autocomplete="given-name">
            <span class="loan-form-print-only"><?php echo $h($vornameInput !== '' ? $vornameInput : '—'); ?></span>
<?php } else { echo $h($vornameInput !== '' ? $vornameInput : '—'); } ?>
          </dd>
        </div>
        <div>
          <dt>Nachname</dt>
          <dd>
<?php if(!$applied) { ?>
            <input class="loan-form-input no-print" type="text" name="Nachname" id="membership-Nachname" value="<?php echo $h($nachnameInput); ?>" required autocomplete="family-name">
            <span class="loan-form-print-only"><?php echo $h($nachnameInput !== '' ? $nachnameInput : '—'); ?></span>
<?php } else { echo $h($nachnameInput !== '' ? $nachnameInput : '—'); } ?>
          </dd>
        </div>
        <div class="<?php echo $emailInput !== '' || !$applied ? '' : 'membership-opt--empty'; ?>">
          <dt>E-Mail</dt>
          <dd>
<?php if(!$applied) { ?>
            <input class="loan-form-input no-print" type="email" name="Email" value="<?php echo $h($emailInput); ?>" autocomplete="email">
<?php if($emailInput !== '') { ?>
            <span class="loan-form-print-only"><?php echo $h($emailInput); ?></span>
<?php } ?>
<?php } else { echo $h($emailInput); } ?>
          </dd>
        </div>
<?php if($birthdayFilled || !$applied) { ?>
        <div class="<?php echo $birthdayFilled ? '' : 'membership-opt--empty'; ?>">
          <dt>Geburtsdatum</dt>
          <dd>
<?php if(!$applied) { ?>
            <input class="loan-form-input no-print" type="date" name="Birthday" value="<?php echo $h((string)$app->Birthday); ?>">
<?php if($birthdayFilled) { ?>
            <span class="loan-form-print-only"><?php echo $h(germanDate($app->Birthday)); ?></span>
<?php } ?>
<?php } else {
    echo $h(germanDate($app->Birthday));
} ?>
          </dd>
        </div>
<?php } ?>
<?php if($phoneFilled || !$applied) { ?>
        <div class="<?php echo $phoneFilled ? '' : 'membership-opt--empty'; ?>">
          <dt>Telefon</dt>
          <dd>
<?php if(!$applied) { ?>
            <input class="loan-form-input no-print" type="text" name="Phone" value="<?php echo $h((string)$app->Phone); ?>">
<?php if($phoneFilled) { ?>
            <span class="loan-form-print-only"><?php echo $h((string)$app->Phone); ?></span>
<?php } ?>
<?php } else { echo $h((string)$app->Phone); } ?>
          </dd>
        </div>
<?php } ?>
      </dl>
      <div class="loan-form-field-row loan-form-field-row--stack">
        <span class="loan-form-field-label">Straße</span>
<?php if(!$applied) { ?>
        <input class="loan-form-input no-print" type="text" name="Street" value="<?php echo $h((string)$app->Street); ?>">
        <span class="loan-form-print-only loan-form-field-value"><?php echo $h((string)$app->Street !== '' ? (string)$app->Street : '—'); ?></span>
<?php } else { ?>
        <span class="loan-form-field-value"><?php echo $h((string)$app->Street ?: '—'); ?></span>
<?php } ?>
      </div>
      <div class="loan-form-field-row" style="margin-top:0.25rem;">
        <span class="loan-form-field-label">PLZ / Ort</span>
<?php if(!$applied) { ?>
        <input class="loan-form-input loan-form-input--short no-print" type="text" name="Zip" placeholder="PLZ" value="<?php echo $h((string)$app->Zip); ?>">
        <input class="loan-form-input no-print" type="text" name="City" placeholder="Ort" value="<?php echo $h((string)$app->City); ?>">
        <input class="loan-form-input loan-form-input--short membership-opt--empty no-print" type="text" name="Country" placeholder="Land" value="<?php echo $h((string)$app->Country); ?>" title="Land (nur wenn nicht DE)">
        <span class="loan-form-print-only loan-form-field-value"><?php echo $h($addrPrint !== '' ? $addrPrint : '—'); ?></span>
<?php } else { ?>
        <span class="loan-form-field-value"><?php echo $h($addrPrint !== '' ? $addrPrint : '—'); ?></span>
<?php } ?>
      </div>
    </section>

    <section class="loan-form-section loan-form-panel">
      <h2>Bankverbindung</h2>
      <div class="loan-form-field-row membership-type-row no-print" style="margin-bottom:0.35rem;">
        <span class="loan-form-field-label">Zahlung</span>
        <label class="loan-form-check"><input type="radio" name="PaymentMethod" value="sepa"<?php echo $paySepa ? ' checked' : ''; ?><?php echo $applied ? ' disabled' : ''; ?>> SEPA-Lastschrift</label>
        <label class="loan-form-check"><input type="radio" name="PaymentMethod" value="ueberweisung"<?php echo !$paySepa ? ' checked' : ''; ?><?php echo $applied ? ' disabled' : ''; ?>> Überweisung</label>
      </div>
      <p class="loan-form-print-only membership-legal">Zahlung per <strong id="membership-pay-label" class="loan-form-em"><?php echo $paySepa ? 'SEPA-Lastschrift' : 'Überweisung'; ?></strong>.</p>

      <div id="membership-pay-sepa"<?php echo $paySepa ? '' : ' hidden'; ?>>
        <dl class="loan-form-dl loan-form-dl--2col">
          <div>
            <dt>Kontoinhaber</dt>
            <dd>
<?php if(!$applied) { ?>
              <input class="loan-form-input no-print" type="text" name="AccountHolder" id="membership-AccountHolder" value="<?php echo $h((string)$app->AccountHolder); ?>" autocomplete="name">
              <span class="loan-form-print-only" id="membership-AccountHolder-print"><?php echo $h((string)$app->AccountHolder); ?></span>
<?php } else { echo $h((string)$app->AccountHolder); } ?>
            </dd>
          </div>
          <div>
            <dt>IBAN</dt>
            <dd>
<?php if(!$applied) { ?>
              <input class="loan-form-input no-print js-iban-check" type="text" name="Iban" id="membership-Iban" value="<?php echo $h((string)$app->Iban); ?>" autocomplete="off" spellcheck="false" inputmode="text" data-iban-bank="membership-BankName">
              <span class="loan-form-print-only"><?php echo $h((string)$app->Iban); ?></span>
<?php } else { echo $h(formatIbanDisplay((string)$app->Iban)); } ?>
            </dd>
          </div>
          <div class="<?php echo MembershipForm::isFilled($app->BankName) || !$applied ? '' : 'membership-opt--empty'; ?>">
            <dt>Kreditinstitut</dt>
            <dd>
<?php if(!$applied) { ?>
              <input class="loan-form-input no-print js-iban-bank" type="text" name="BankName" id="membership-BankName" value="<?php echo $h((string)$app->BankName); ?>" autocomplete="organization">
<?php if(MembershipForm::isFilled($app->BankName)) { ?>
              <span class="loan-form-print-only"><?php echo $h((string)$app->BankName); ?></span>
<?php } ?>
<?php } else { echo $h((string)$app->BankName); } ?>
            </dd>
          </div>
        </dl>
<?php if($creditor['creditorId'] !== '') { ?>
        <p class="membership-legal membership-legal--tight" style="margin-top:0.4rem;">Gläubiger-ID des Vereins: <strong class="loan-form-em"><?php echo $h($creditor['creditorId']); ?></strong></p>
<?php } ?>
      </div>

      <div id="membership-pay-transfer"<?php echo $paySepa ? ' hidden' : ''; ?>>
        <dl class="loan-form-dl loan-form-dl--2col membership-creditor">
          <div><dt>Empfänger</dt><dd><?php echo $h($orgName); ?></dd></div>
          <div><dt>Kreditinstitut</dt><dd><?php echo $h($creditor['bank']); ?></dd></div>
          <div><dt>IBAN</dt><dd><?php echo $h($creditor['iban']); ?></dd></div>
        </dl>
      </div>
    </section>

    <section class="loan-form-section loan-form-panel">
      <h2>Beitritt</h2>
      <p class="membership-legal membership-legal--lead">
        <?php
        $declareName = trim($vornameInput.' '.$nachnameInput);
        $nameHtml = '<strong class="loan-form-em" id="membership-declare-name">'
            .$h($declareName !== '' ? $declareName : '—')
            .'</strong>';
        echo MembershipForm::leadSentenceHtml($nameHtml);
        ?>
        <span class="loan-form-print-only"><strong class="loan-form-em"><?php echo $h($typeLabelLong); ?></strong></span><span class="no-print">:</span>
      </p>
      <div class="loan-form-field-row membership-type-row no-print">
        <label class="loan-form-check"><input type="radio" name="DesiredType" value="aktiv"<?php echo $typeAktiv ? ' checked' : ''; ?><?php echo $applied ? ' disabled' : ''; ?>> aktives Mitglied</label>
        <label class="loan-form-check"><input type="radio" name="DesiredType" value="foerdernd"<?php echo $typeFoerdernd ? ' checked' : ''; ?><?php echo $applied ? ' disabled' : ''; ?>> förderndes Mitglied</label>
      </div>
<?php foreach($membershipRules as $para) { ?>
      <p class="membership-legal"><?php echo $para; ?></p>
<?php } ?>
      <p class="membership-legal">
        Der Jahresbeitrag beträgt
        <span class="loan-form-field-value<?php echo $applied ? '' : ' loan-form-print-only'; ?>"><?php echo MembershipForm::feeLiveHtml($feeCents); ?></span><?php if(!$applied) { ?>
        <input id="membership-annual-fee"
               class="loan-form-input loan-form-input--short no-print"
               type="number"
               name="AnnualFeeEuro"
               step="0.01"
               min="<?php echo $h(number_format(($feeReduced ? $minFees['ermaessigt'] : $minFees[$typeAktiv ? 'aktiv' : 'foerdernd']) / 100, 2, '.', '')); ?>"
               value="<?php echo $h($feeEuroInput); ?>"
               data-min-aktiv="<?php echo (int)$minFees['aktiv']; ?>"
               data-min-foerdernd="<?php echo (int)$minFees['foerdernd']; ?>"
               data-min-ermaessigt="<?php echo (int)$minFees['ermaessigt']; ?>"
               required>
        <span class="loan-form-muted no-print">€</span><?php } ?>
        pro Jahr<?php if($feeReduced) { ?>
        <span class="loan-form-print-only"> (ermäßigt)</span><?php } ?>. Eintritt zum
<?php if(!$applied) { ?>
        <input class="loan-form-input loan-form-input--short no-print" type="date" name="DesiredEntryDate" value="<?php echo $h((string)($app->DesiredEntryDate ?: date('Y-m-d'))); ?>" required>
<?php } ?>
        <span class="<?php echo $applied ? '' : 'loan-form-print-only'; ?>"><strong class="loan-form-em"><?php echo $h(germanDate($app->DesiredEntryDate ?: date('Y-m-d'))); ?></strong></span>.
      </p>
<?php if(!$applied) { ?>
      <div class="loan-form-field-row membership-type-row no-print" style="margin:0.25rem 0;">
        <label class="loan-form-check"><input type="checkbox" name="FeeReduced" id="membership-fee-reduced" value="1"<?php echo $feeReduced ? ' checked' : ''; ?>> Ermäßigt (Studierende / Minderjährige)</label>
      </div>
<?php } elseif($feeReduced) { ?>
      <p class="membership-legal membership-legal--note">Ermäßigter Beitrag (Studierende / Minderjährige).</p>
<?php } ?>
      <p class="membership-legal membership-legal--note no-print">
        Mindestbeitrag: aktiv <?php echo $h(MembershipForm::formatEuroFromCents($minFees['aktiv'])); ?>,
        fördernd <?php echo $h(MembershipForm::formatEuroFromCents($minFees['foerdernd'])); ?>,
        ermäßigt <?php echo $h(MembershipForm::formatEuroFromCents($minFees['ermaessigt'])); ?>.
      </p>
      <div id="membership-legal-pay-sepa"<?php echo $paySepa ? '' : ' hidden'; ?>>
<?php foreach($sepaTexts['intro'] as $para) { ?>
        <p class="membership-legal"><?php echo $para; ?></p>
<?php } ?>
<?php foreach($sepaTexts['mandate'] as $para) { ?>
        <p class="membership-legal"><?php echo $para; ?></p>
<?php } ?>
        <p class="membership-legal membership-legal--note"><?php echo $sepaTexts['note']; ?></p>
      </div>
      <div id="membership-legal-pay-transfer"<?php echo $paySepa ? ' hidden' : ''; ?>>
<?php foreach($transferTexts as $para) { ?>
        <p class="membership-legal"><?php echo $para; ?></p>
<?php } ?>
      </div>
    </section>

    <section class="loan-form-section loan-form-panel">
      <h2>Datenschutz</h2>
<?php foreach($privacyParas as $para) { ?>
      <p class="membership-legal"><?php echo $para; ?></p>
<?php } ?>
    </section>

    <section id="membership-media-consent" class="loan-form-section loan-form-panel"<?php echo $typeAktiv ? '' : ' hidden'; ?>>
      <h2>Medien</h2>
<?php foreach($mediaConsentParas as $para) { ?>
      <p class="membership-legal"><?php echo $para; ?></p>
<?php } ?>
    </section>

    <section class="loan-form-section loan-form-panel loan-form-signatures">
      <h2>Unterschriften</h2>
      <div class="loan-form-sign-grid">
        <div class="loan-form-sign">
          <div class="loan-form-sign-space loan-form-sign-space--date"></div>
          <p class="loan-form-sign-caption">Ort, Datum</p>
        </div>
        <div class="loan-form-sign">
          <div class="loan-form-sign-space loan-form-sign-space--sig"></div>
          <p class="loan-form-sign-caption">Unterschrift Mitglied</p>
        </div>
      </div>
      <div id="membership-sign-sepa" class="loan-form-sign-grid" style="margin-top:0.75rem;"<?php echo $paySepa ? '' : ' hidden'; ?>>
        <div class="loan-form-sign">
          <div class="loan-form-sign-space loan-form-sign-space--date"></div>
          <p class="loan-form-sign-caption">Ort, Datum</p>
        </div>
        <div class="loan-form-sign">
          <div class="loan-form-sign-space loan-form-sign-space--sig"></div>
          <p class="loan-form-sign-caption">Unterschrift Kontoinhaber</p>
        </div>
      </div>
    </section>
  </article>

<?php if(!$statusApplied) { ?>
  </form>

<?php if($hasScan) { ?>
  <div class="loan-form-toolbar no-print" style="margin-top:1rem;">
    <form method="POST" action="membership-form.php?id=<?php echo (int)$app->Index; ?>" class="loan-form-upload">
      <input type="hidden" name="id" value="<?php echo (int)$app->Index; ?>">
      <input type="hidden" name="action" value="apply">
      <button type="submit" class="loan-form-btn loan-form-btn--primary" data-confirm="Beitritt mit Eintritt <?php echo $h(germanDate($app->DesiredEntryDate ?: date('Y-m-d'))); ?> anwenden?" data-confirm-ok="Beitritt anwenden">Beitritt anwenden (<?php echo $h(germanDate($app->DesiredEntryDate ?: date('Y-m-d'))); ?>)</button>
    </form>
  </div>
<?php } ?>
<?php } else { ?>
  </form>
<?php } ?>
  <script>
  (function () {
    var cons = document.getElementById('membership-media-consent');
    var fee = document.getElementById('membership-annual-fee');
    var sepa = document.getElementById('membership-pay-sepa');
    var transfer = document.getElementById('membership-pay-transfer');
    var legalSepa = document.getElementById('membership-legal-pay-sepa');
    var legalTransfer = document.getElementById('membership-legal-pay-transfer');
    var signSepa = document.getElementById('membership-sign-sepa');
    var payLabel = document.getElementById('membership-pay-label');
    var vorname = document.getElementById('membership-Vorname');
    var nachname = document.getElementById('membership-Nachname');
    var holder = document.getElementById('membership-AccountHolder');
    var holderPrint = document.getElementById('membership-AccountHolder-print');
    var declareName = document.getElementById('membership-declare-name');
    var autoHolder = null;
    var printUserId = <?php echo (int)$userId; ?>;
    function sanitizeFilePart(raw) {
      var s = String(raw || '').trim();
      if (!s) return '';
      s = s.replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue')
           .replace(/Ä/g, 'Ae').replace(/Ö/g, 'Oe').replace(/Ü/g, 'Ue').replace(/ß/g, 'ss');
      s = s.replace(/[^A-Za-z0-9._-]+/g, '-').replace(/-+/g, '-');
      return s.replace(/^[-._]+|[-._]+$/g, '');
    }
    function syncDeclareName() {
      if (!declareName) return;
      var name = memberName();
      declareName.textContent = name !== '' ? name : '—';
    }
    function syncPrintTitle() {
      var name = sanitizeFilePart(memberName()) || 'ohne-Namen';
      document.title = 'MVD-Beitritt-' + printUserId + '-' + name;
    }
    function memberName() {
      var v = vorname ? vorname.value.trim() : '';
      var n = nachname ? nachname.value.trim() : '';
      return (v + ' ' + n).trim();
    }
    function syncHolderFromName() {
      if (!holder || autoHolder === null) return;
      var name = memberName();
      var cur = holder.value.trim();
      if (cur === '' || cur === autoHolder) {
        holder.value = name;
        autoHolder = name;
        if (holderPrint) holderPrint.textContent = name;
      }
    }
    function initHolderAuto() {
      if (!holder) return;
      var name = memberName();
      var cur = holder.value.trim();
      if (cur === '' || cur === name) {
        autoHolder = name;
        if (cur === '' && name !== '') {
          holder.value = name;
          if (holderPrint) holderPrint.textContent = name;
        }
      } else {
        autoHolder = null;
      }
    }
    var feeReduced = document.getElementById('membership-fee-reduced');
    function selectedType() {
      var aktiv = document.querySelector('input[name="DesiredType"][value="aktiv"]');
      return (aktiv && aktiv.checked) ? 'aktiv' : 'foerdernd';
    }
    function selectedPay() {
      var u = document.querySelector('input[name="PaymentMethod"][value="ueberweisung"]');
      if (u && u.checked) return 'ueberweisung';
      var s = document.querySelector('input[name="PaymentMethod"][value="sepa"]');
      return (s && s.checked) ? 'sepa' : 'ueberweisung';
    }
    function minCentsFor(type) {
      if (!fee) return 2000;
      if (feeReduced && feeReduced.checked) {
        return parseInt(fee.getAttribute('data-min-ermaessigt') || '1000', 10) || 1000;
      }
      var raw = fee.getAttribute(type === 'foerdernd' ? 'data-min-foerdernd' : 'data-min-aktiv');
      return parseInt(raw || '2000', 10) || 2000;
    }
    function setPayBlock(el, on) {
      if (!el) return;
      el.hidden = !on;
    }
    function formatFeeLive(euroStr) {
      var n = parseFloat(euroStr);
      if (isNaN(n)) n = 0;
      var cents = Math.round(n * 100);
      var whole = Math.floor(cents / 100);
      var frac = Math.abs(cents % 100);
      var wholeStr = String(whole).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      return wholeStr + ',' + (frac < 10 ? '0' : '') + frac + ' €';
    }
    function syncFeeDisplays() {
      if (!fee) return;
      var text = formatFeeLive(fee.value);
      document.querySelectorAll('.membership-fee-live').forEach(function (el) {
        el.textContent = text;
      });
    }
    function sync() {
      var type = selectedType();
      var pay = selectedPay();
      var isSepa = pay === 'sepa';
      if (cons) cons.hidden = type !== 'aktiv';
      setPayBlock(sepa, isSepa);
      setPayBlock(transfer, !isSepa);
      setPayBlock(legalSepa, isSepa);
      setPayBlock(legalTransfer, !isSepa);
      setPayBlock(signSepa, isSepa);
      if (payLabel) payLabel.textContent = isSepa ? 'SEPA-Lastschrift' : 'Überweisung';
      if (!fee) return;
      var minC = minCentsFor(type);
      var minEuro = (minC / 100).toFixed(2);
      fee.min = minEuro;
      var cur = parseFloat(fee.value);
      if (isNaN(cur) || cur * 100 < minC - 0.5) {
        fee.value = minEuro;
      }
      syncFeeDisplays();
    }
    document.querySelectorAll('input[name="DesiredType"], input[name="PaymentMethod"]').forEach(function (el) {
      el.addEventListener('change', sync);
    });
    if (feeReduced) {
      feeReduced.addEventListener('change', function () {
        sync();
        if (feeReduced.checked && fee) {
          fee.value = (minCentsFor(selectedType()) / 100).toFixed(2);
        }
        syncFeeDisplays();
      });
    }
    if (vorname) {
      vorname.addEventListener('input', function () {
        syncHolderFromName();
        syncDeclareName();
        syncPrintTitle();
      });
    }
    if (nachname) {
      nachname.addEventListener('input', function () {
        syncHolderFromName();
        syncDeclareName();
        syncPrintTitle();
      });
    }
    if (holder) {
      holder.addEventListener('input', function () {
        var cur = holder.value.trim();
        if (cur === '') {
          autoHolder = '';
          syncHolderFromName();
        } else {
          autoHolder = null;
        }
        if (holderPrint) holderPrint.textContent = holder.value;
      });
    }
    if (fee) {
      fee.addEventListener('change', function () {
        var minC = minCentsFor(selectedType());
        var cur = parseFloat(fee.value);
        if (isNaN(cur) || cur * 100 < minC - 0.5) {
          fee.value = (minC / 100).toFixed(2);
        }
        syncFeeDisplays();
      });
      fee.addEventListener('input', syncFeeDisplays);
    }
    sync();
    initHolderAuto();
    syncDeclareName();
    syncPrintTitle();
    syncFeeDisplays();

    var scanUpload = document.getElementById('membership-scan-upload');
    if (scanUpload) {
      scanUpload.addEventListener('click', function (e) {
        var i = document.querySelector('input[name=scan][form=membership-form-fields]');
        if (i && i.files && i.files.length) {
          return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (typeof appAlert === 'function') {
          appAlert('Bitte zuerst eine Datei wählen.');
        }
      }, true);
    }
  })();
  </script>
  <div id="appConfirmModal" class="w3-modal" role="dialog" aria-modal="true" aria-labelledby="appConfirmTitle" style="display:none;">
    <div class="w3-modal-content">
      <div class="profile-shell modal-shell confirm-delete-modal">
        <header class="profile-hero">
          <div class="profile-hero-text">
            <p class="profile-kicker" id="appConfirmKicker" style="display:none;"></p>
            <h2 class="profile-title" id="appConfirmTitle">Bestätigen</h2>
          </div>
          <div class="profile-hero-actions">
            <button type="button" class="modal-close w3-button" id="appConfirmClose" aria-label="Schließen">&times;</button>
          </div>
        </header>
        <div class="confirm-delete-body">
          <p class="profile-value" id="appConfirmMessage"></p>
          <div class="profile-actions profile-actions--confirm">
            <div class="profile-actions-primary">
              <button type="button" class="w3-btn profile-btn-primary w3-border w3-mobile" id="appConfirmOk">OK</button>
            </div>
            <button type="button" class="w3-btn w3-border w3-mobile" id="appConfirmCancel">Abbrechen</button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="<?php echo assetUrl('js/appDialog.js'); ?>"></script>
  <script src="<?php echo assetUrl('js/ibanCheck.js'); ?>" data-blz-lookup="blzLookup.php"></script>
</body>
</html>
