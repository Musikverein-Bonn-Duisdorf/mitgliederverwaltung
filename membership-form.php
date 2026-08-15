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
$applied = $app->Status === 'applied';
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
$minFees = MembershipForm::minFeeCentsByType();
$feeCents = MembershipForm::clampFeeCents(
    (int)($app->AnnualFeeCents ?: MembershipForm::minFeeCents($app->DesiredType)),
    $app->DesiredType
);
$feeEuroInput = number_format($feeCents / 100, 2, '.', '');
$sepaTexts = MembershipForm::sepaTextsHtml($feeCents, $app->DesiredType);
$transferTexts = MembershipForm::transferTextsHtml($feeCents, $app->DesiredType);
$mediaConsentParas = MembershipForm::mediaConsentParagraphsHtml();
$mandateRefPreview = 'MVD-SEPA-'.(int)$app->Index;
$email2 = trim((string)$user->Email2);
$phoneFilled = MembershipForm::isFilled($app->Phone);
$phone2Filled = MembershipForm::isFilled($app->Phone2);
$birthdayFilled = MembershipForm::isFilled($app->Birthday);
$countryShow = MembershipForm::isFilled($app->Country) && strtoupper(trim((string)$app->Country)) !== 'DE';
$addrPrint = trim(implode(', ', array_filter(array(
    (string)$app->Street,
    trim((string)$app->Zip.' '.(string)$app->City),
    $countryShow ? (string)$app->Country : '',
))));

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Beitrittserklärung – <?php echo $h($orgName); ?></title>
  <link rel="stylesheet" href="<?php echo $h($cssUrl); ?>">
</head>
<body class="loan-form-print">
  <div class="loan-form-toolbar no-print">
    <div class="loan-form-toolbar-group">
      <a class="loan-form-btn" href="person.php?id=<?php echo (int)$userId; ?>">Zurück</a>
      <button type="button" class="loan-form-btn" onclick="window.print()">Drucken</button>
<?php if(!$applied) { ?>
      <button type="submit" form="membership-form-fields" class="loan-form-btn loan-form-btn--primary" name="action" value="saveFields">Speichern</button>
<?php } ?>
    </div>
    <div class="loan-form-toolbar-group loan-form-toolbar-group--scan">
<?php if($hasScan) { ?>
      <div class="loan-form-scan-pair">
        <a class="loan-form-btn loan-form-btn--scan" href="membership-contract.php?id=<?php echo (int)$app->Index; ?>">Scan anzeigen</a>
<?php   if(!$applied) { ?>
        <form class="loan-form-upload" method="POST" action="membership-contract.php" onsubmit="return confirm('Scan löschen?');">
          <input type="hidden" name="id" value="<?php echo (int)$app->Index; ?>">
          <input type="hidden" name="action" value="deleteScan">
          <button type="submit" class="loan-form-btn">Löschen</button>
        </form>
<?php   } ?>
      </div>
<?php } ?>
<?php if(!$applied) { ?>
      <label class="loan-form-btn loan-form-btn--file">
        Datei
        <input type="file" form="membership-form-fields" name="scan" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,application/pdf,image/*">
      </label>
      <button type="submit"
              form="membership-form-fields"
              formaction="membership-contract.php"
              class="loan-form-btn loan-form-btn--primary"
              name="action"
              value="upload"
              onclick="var f=document.getElementById('membership-form-fields'); var i=f&&f.querySelector('input[name=scan]'); if(!i||!i.files||!i.files.length){alert('Bitte zuerst eine Datei wählen.'); return false;} return confirm('Scan hochladen und Beitritt mit dem Eintrittsdatum auf dem Formular anwenden?');">Hochladen</button>
<?php } ?>
    </div>
  </div>

<?php if($flash !== '') { ?>
  <div class="loan-form-toolbar no-print"><p><?php echo $h($flash); ?></p></div>
<?php } ?>

<?php if(!$applied) { ?>
  <form id="membership-form-fields" method="POST" action="membership-form.php?id=<?php echo (int)$app->Index; ?>" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?php echo (int)$app->Index; ?>">
    <input type="hidden" name="user" value="<?php echo (int)$userId; ?>">
<?php } ?>

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
      <h2>Beitritt</h2>
      <p class="membership-legal">Hiermit erkläre ich meinen Beitritt zum <?php echo MembershipForm::em($orgName); ?> als</p>
      <div class="loan-form-field-row membership-type-row no-print">
        <label class="loan-form-check"><input type="radio" name="DesiredType" value="aktiv"<?php echo $typeAktiv ? ' checked' : ''; ?><?php echo $applied ? ' disabled' : ''; ?>> aktives Mitglied</label>
        <label class="loan-form-check"><input type="radio" name="DesiredType" value="foerdernd"<?php echo $typeFoerdernd ? ' checked' : ''; ?><?php echo $applied ? ' disabled' : ''; ?>> förderndes Mitglied</label>
      </div>
      <p class="loan-form-print-only membership-legal"><strong class="loan-form-em"><?php echo $typeFoerdernd ? 'förderndes Mitglied' : 'aktives Mitglied'; ?></strong></p>
<?php foreach($membershipRules as $para) { ?>
      <p class="membership-legal membership-legal--tight"><?php echo $para; ?></p>
<?php } ?>
      <div class="loan-form-field-row" style="margin-top:0.35rem;">
        <span class="loan-form-field-label">Jahresbeitrag</span>
<?php if(!$applied) { ?>
        <input id="membership-annual-fee"
               class="loan-form-input loan-form-input--short no-print"
               type="number"
               name="AnnualFeeEuro"
               step="0.01"
               min="<?php echo $h(number_format($minFees[$typeAktiv ? 'aktiv' : 'foerdernd'] / 100, 2, '.', '')); ?>"
               value="<?php echo $h($feeEuroInput); ?>"
               data-min-aktiv="<?php echo (int)$minFees['aktiv']; ?>"
               data-min-foerdernd="<?php echo (int)$minFees['foerdernd']; ?>"
               required>
        <span class="loan-form-muted no-print">€ / Jahr</span>
<?php } ?>
        <span class="loan-form-field-value<?php echo $applied ? '' : ' loan-form-print-only'; ?>"><strong class="loan-form-em"><?php echo $h(MembershipForm::formatEuroFromCents($feeCents)); ?></strong> / Jahr</span>
      </div>
      <p class="membership-legal membership-legal--note no-print">
        Mindest: aktiv <?php echo $h(MembershipForm::formatEuroFromCents($minFees['aktiv'])); ?>,
        fördernd <?php echo $h(MembershipForm::formatEuroFromCents($minFees['foerdernd'])); ?>.
      </p>
      <div class="loan-form-field-row membership-type-row no-print" style="margin-top:0.35rem;">
        <span class="loan-form-field-label">Zahlung</span>
        <label class="loan-form-check"><input type="radio" name="PaymentMethod" value="sepa"<?php echo $paySepa ? ' checked' : ''; ?><?php echo $applied ? ' disabled' : ''; ?>> SEPA-Lastschrift</label>
        <label class="loan-form-check"><input type="radio" name="PaymentMethod" value="ueberweisung"<?php echo !$paySepa ? ' checked' : ''; ?><?php echo $applied ? ' disabled' : ''; ?>> Überweisung</label>
      </div>
      <p class="loan-form-print-only membership-legal">Zahlung: <strong class="loan-form-em"><?php echo $paySepa ? 'SEPA-Lastschrift' : 'Überweisung'; ?></strong></p>
      <div class="loan-form-field-row" style="margin-top:0.35rem;">
        <span class="loan-form-field-label">Eintritt</span>
<?php if(!$applied) { ?>
        <input class="loan-form-input loan-form-input--short no-print" type="date" name="DesiredEntryDate" value="<?php echo $h((string)($app->DesiredEntryDate ?: date('Y-m-d'))); ?>" required>
<?php } ?>
        <span class="<?php echo $applied ? 'loan-form-field-value' : 'loan-form-print-only'; ?>"><?php echo $h(germanDate($app->DesiredEntryDate ?: date('Y-m-d'))); ?></span>
      </div>
    </section>

    <section id="membership-media-consent" class="loan-form-section loan-form-panel"<?php echo $typeAktiv ? '' : ' hidden'; ?>>
      <h2>Medien</h2>
<?php foreach($mediaConsentParas as $para) { ?>
      <p class="membership-legal"><?php echo $para; ?></p>
<?php } ?>
    </section>

    <section class="loan-form-section loan-form-panel">
      <h2>Person</h2>
      <dl class="loan-form-dl loan-form-dl--2col">
        <div><dt>Name</dt><dd><?php echo $h($user->getName()); ?></dd></div>
<?php if(MembershipForm::isFilled($user->Email)) { ?>
        <div><dt>E-Mail</dt><dd><?php echo $h((string)$user->Email); ?></dd></div>
<?php } ?>
<?php if($email2 !== '') { ?>
        <div><dt>E-Mail 2</dt><dd><?php echo $h($email2); ?></dd></div>
<?php } ?>
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
<?php if($phone2Filled || !$applied) { ?>
        <div class="<?php echo $phone2Filled ? '' : 'membership-opt--empty'; ?>">
          <dt>Handy</dt>
          <dd>
<?php if(!$applied) { ?>
            <input class="loan-form-input no-print" type="text" name="Phone2" value="<?php echo $h((string)$app->Phone2); ?>">
<?php if($phone2Filled) { ?>
            <span class="loan-form-print-only"><?php echo $h((string)$app->Phone2); ?></span>
<?php } ?>
<?php } else { echo $h((string)$app->Phone2); } ?>
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
      <h2>Datenschutz</h2>
<?php foreach($privacyParas as $para) { ?>
      <p class="membership-legal"><?php echo $para; ?></p>
<?php } ?>
    </section>

    <section class="loan-form-section loan-form-panel loan-form-signatures">
      <h2>Unterschrift Beitritt</h2>
      <div class="loan-form-sign-grid">
        <div class="loan-form-sign">
          <div class="loan-form-sign-space loan-form-sign-space--date"></div>
          <p class="loan-form-sign-caption">Ort, Datum</p>
        </div>
        <div class="loan-form-sign">
          <div class="loan-form-sign-space loan-form-sign-space--sig"></div>
          <p class="loan-form-sign-caption">Unterschrift</p>
        </div>
      </div>
    </section>

    <section id="membership-pay-sepa" class="loan-form-section loan-form-panel"<?php echo $paySepa ? '' : ' hidden'; ?>>
      <h2>SEPA-Lastschriftmandat</h2>
<?php if($creditor['creditorId'] !== '') { ?>
      <p class="membership-legal membership-legal--tight">Gläubiger-ID: <strong class="loan-form-em"><?php echo $h($creditor['creditorId']); ?></strong>
        · Mandatsreferenz: <?php echo $h($mandateRefPreview); ?> <span class="loan-form-muted">(vorläufig)</span></p>
<?php } else { ?>
      <p class="membership-legal membership-legal--tight">Mandatsreferenz: <?php echo $h($mandateRefPreview); ?> <span class="loan-form-muted">(vorläufig)</span></p>
<?php } ?>
<?php foreach($sepaTexts['intro'] as $para) { ?>
      <p class="membership-legal"><?php echo $para; ?></p>
<?php } ?>
<?php foreach($sepaTexts['mandate'] as $para) { ?>
      <p class="membership-legal"><?php echo $para; ?></p>
<?php } ?>
      <dl class="loan-form-dl loan-form-dl--2col" style="margin-top:0.4rem;">
        <div>
          <dt>Kontoinhaber</dt>
          <dd>
<?php if(!$applied) { ?>
            <input class="loan-form-input no-print" type="text" name="AccountHolder" value="<?php echo $h((string)$app->AccountHolder); ?>">
            <span class="loan-form-print-only"><?php echo $h((string)$app->AccountHolder); ?></span>
<?php } else { echo $h((string)$app->AccountHolder); } ?>
          </dd>
        </div>
        <div class="<?php echo MembershipForm::isFilled($app->BankName) || !$applied ? '' : 'membership-opt--empty'; ?>">
          <dt>Kreditinstitut</dt>
          <dd>
<?php if(!$applied) { ?>
            <input class="loan-form-input no-print" type="text" name="BankName" value="<?php echo $h((string)$app->BankName); ?>">
<?php if(MembershipForm::isFilled($app->BankName)) { ?>
            <span class="loan-form-print-only"><?php echo $h((string)$app->BankName); ?></span>
<?php } ?>
<?php } else { echo $h((string)$app->BankName); } ?>
          </dd>
        </div>
        <div>
          <dt>IBAN</dt>
          <dd>
<?php if(!$applied) { ?>
            <input class="loan-form-input no-print" type="text" name="Iban" value="<?php echo $h((string)$app->Iban); ?>" autocomplete="off" spellcheck="false">
            <span class="loan-form-print-only"><?php echo $h((string)$app->Iban); ?></span>
<?php } else { echo $h(maskIban((string)$app->Iban)); } ?>
          </dd>
        </div>
        <div class="<?php echo MembershipForm::isFilled($app->Bic) || !$applied ? '' : 'membership-opt--empty'; ?>">
          <dt>BIC</dt>
          <dd>
<?php if(!$applied) { ?>
            <input class="loan-form-input no-print" type="text" name="Bic" value="<?php echo $h((string)$app->Bic); ?>" spellcheck="false">
<?php if(MembershipForm::isFilled($app->Bic)) { ?>
            <span class="loan-form-print-only"><?php echo $h((string)$app->Bic); ?></span>
<?php } ?>
<?php } else { echo $h((string)$app->Bic); } ?>
          </dd>
        </div>
      </dl>
      <p class="membership-legal membership-legal--note"><?php echo $sepaTexts['note']; ?></p>
      <div class="loan-form-sign-grid" style="margin-top:0.55rem;">
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

    <section id="membership-pay-transfer" class="loan-form-section loan-form-panel"<?php echo $paySepa ? ' hidden' : ''; ?>>
      <h2>Zahlung per Überweisung</h2>
<?php foreach($transferTexts as $para) { ?>
      <p class="membership-legal"><?php echo $para; ?></p>
<?php } ?>
      <dl class="loan-form-dl loan-form-dl--2col membership-creditor">
        <div><dt>Empfänger</dt><dd><?php echo $h($orgName); ?></dd></div>
        <div><dt>Kreditinstitut</dt><dd><?php echo $h($creditor['bank']); ?></dd></div>
        <div><dt>IBAN</dt><dd><?php echo $h($creditor['iban']); ?></dd></div>
        <div><dt>BIC</dt><dd><?php echo $h($creditor['bic']); ?></dd></div>
      </dl>
    </section>
  </article>

<?php if(!$applied) { ?>
  </form>

<?php if($hasScan) { ?>
  <div class="loan-form-toolbar no-print" style="margin-top:1rem;">
    <form method="POST" action="membership-form.php?id=<?php echo (int)$app->Index; ?>" class="loan-form-upload">
      <input type="hidden" name="id" value="<?php echo (int)$app->Index; ?>">
      <input type="hidden" name="action" value="apply">
      <button type="submit" class="loan-form-btn loan-form-btn--primary" onclick="return confirm('Beitritt mit Eintritt <?php echo $h(germanDate($app->DesiredEntryDate ?: date('Y-m-d'))); ?> anwenden?');">Beitritt anwenden (<?php echo $h(germanDate($app->DesiredEntryDate ?: date('Y-m-d'))); ?>)</button>
    </form>
  </div>
<?php } ?>
  <script>
  (function () {
    var cons = document.getElementById('membership-media-consent');
    var fee = document.getElementById('membership-annual-fee');
    var sepa = document.getElementById('membership-pay-sepa');
    var transfer = document.getElementById('membership-pay-transfer');
    function selectedType() {
      var aktiv = document.querySelector('input[name="DesiredType"][value="aktiv"]');
      return (aktiv && aktiv.checked) ? 'aktiv' : 'foerdernd';
    }
    function selectedPay() {
      var s = document.querySelector('input[name="PaymentMethod"][value="sepa"]');
      return (s && s.checked) ? 'sepa' : 'ueberweisung';
    }
    function minCentsFor(type) {
      if (!fee) return 2000;
      var raw = fee.getAttribute(type === 'foerdernd' ? 'data-min-foerdernd' : 'data-min-aktiv');
      return parseInt(raw || '2000', 10) || 2000;
    }
    function sync() {
      var type = selectedType();
      var pay = selectedPay();
      if (cons) cons.hidden = type !== 'aktiv';
      if (sepa) sepa.hidden = pay !== 'sepa';
      if (transfer) transfer.hidden = pay !== 'ueberweisung';
      if (!fee) return;
      var minC = minCentsFor(type);
      var minEuro = (minC / 100).toFixed(2);
      fee.min = minEuro;
      var cur = parseFloat(fee.value);
      if (isNaN(cur) || cur * 100 < minC - 0.5) {
        fee.value = minEuro;
      }
    }
    document.querySelectorAll('input[name="DesiredType"], input[name="PaymentMethod"]').forEach(function (el) {
      el.addEventListener('change', sync);
    });
    if (fee) {
      fee.addEventListener('change', function () {
        var minC = minCentsFor(selectedType());
        var cur = parseFloat(fee.value);
        if (isNaN(cur) || cur * 100 < minC - 0.5) {
          fee.value = (minC / 100).toFixed(2);
        }
      });
    }
    sync();
  })();
  </script>
<?php } ?>
</body>
</html>
