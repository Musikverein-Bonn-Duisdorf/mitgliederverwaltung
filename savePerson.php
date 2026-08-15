<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
include 'common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']);

if(!loggedIn()) {
    header('Location: login.php');
    exit;
}
requirePermission('perm_editUsers');

$action = isset($_POST['action']) ? (string)$_POST['action'] : 'save_profile';

if($action === 'create_user') {
    if(!csrf_verify(isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '')) {
        $_SESSION['personFlash'] = 'Ungültige Sitzung — bitte erneut versuchen.';
        header('Location: new-person.php');
        exit;
    }
    $vorname = isset($_POST['vorname']) ? trim((string)$_POST['vorname']) : '';
    $nachname = isset($_POST['nachname']) ? trim((string)$_POST['nachname']) : '';
    $login = isset($_POST['login']) ? trim((string)$_POST['login']) : '';
    if($vorname === '' || $nachname === '') {
        $_SESSION['personFlash'] = 'Vor- und Nachname sind Pflicht.';
        header('Location: new-person.php');
        exit;
    }
    if($login !== '') {
        $clash = new IdentityUser();
        if($clash->load_by_login($login)) {
            $_SESSION['personFlash'] = 'Login bereits vergeben.';
            header('Location: new-person.php');
            exit;
        }
    }
    $created = IdentityUser::createPerson(array(
        'Vorname' => $vorname,
        'Nachname' => $nachname,
        'Email' => isset($_POST['email']) ? trim((string)$_POST['email']) : '',
        'Email2' => isset($_POST['email2']) ? trim((string)$_POST['email2']) : '',
        'login' => $login,
    ));
    if(!$created || (int)$created->Index < 1) {
        $_SESSION['personFlash'] = 'Person konnte nicht angelegt werden.';
        header('Location: new-person.php');
        exit;
    }
    $userId = (int)$created->Index;

    $hasProfile = false;
    foreach(array('birthday', 'phone', 'phone2', 'street', 'zip', 'city', 'country', 'account_holder') as $k) {
        if(isset($_POST[$k]) && trim((string)$_POST[$k]) !== '') {
            $hasProfile = true;
            break;
        }
    }
    if($hasProfile) {
        $profile = new MemberProfile();
        $profile->User = $userId;
        $profile->Birthday = isset($_POST['birthday']) ? trim((string)$_POST['birthday']) : null;
        $profile->Phone = isset($_POST['phone']) ? trim((string)$_POST['phone']) : null;
        $profile->Phone2 = isset($_POST['phone2']) ? trim((string)$_POST['phone2']) : null;
        $profile->Street = isset($_POST['street']) ? trim((string)$_POST['street']) : null;
        $profile->Zip = isset($_POST['zip']) ? trim((string)$_POST['zip']) : null;
        $profile->City = isset($_POST['city']) ? trim((string)$_POST['city']) : null;
        $profile->Country = isset($_POST['country']) ? trim((string)$_POST['country']) : null;
        $profile->AccountHolder = isset($_POST['account_holder']) ? trim((string)$_POST['account_holder']) : null;
        $profile->save();
    }

    $_SESSION['personFlash'] = 'Person angelegt.';
    header('Location: person.php?id='.$userId);
    exit;
}

$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$user = new IdentityUser();
if($userId < 1 || !$user->load_by_id($userId) || (int)$user->Deleted === 1) {
    $_SESSION['personFlash'] = 'Person nicht gefunden.';
    header('Location: members.php');
    exit;
}

if($action === 'save_profile') {
    $user->Vorname = isset($_POST['vorname']) ? trim((string)$_POST['vorname']) : (string)$user->Vorname;
    $user->Nachname = isset($_POST['nachname']) ? trim((string)$_POST['nachname']) : (string)$user->Nachname;
    $user->Email = isset($_POST['email']) ? trim((string)$_POST['email']) : (string)$user->Email;
    $user->Email2 = isset($_POST['email2']) ? trim((string)$_POST['email2']) : (string)$user->Email2;
    if(trim((string)$user->Vorname) === '' || trim((string)$user->Nachname) === '') {
        $_SESSION['personFlash'] = 'Vor- und Nachname sind Pflicht.';
        header('Location: person.php?id='.$userId);
        exit;
    }
    if(!$user->saveStammdaten()) {
        $_SESSION['personFlash'] = 'Stammdaten (Name/Email) konnten nicht gespeichert werden.';
        header('Location: person.php?id='.$userId);
        exit;
    }

    $profile = new MemberProfile();
    $profile->load_or_create($userId);
    $profile->Birthday = isset($_POST['birthday']) ? trim((string)$_POST['birthday']) : null;
    $profile->Phone = isset($_POST['phone']) ? trim((string)$_POST['phone']) : null;
    $profile->Phone2 = isset($_POST['phone2']) ? trim((string)$_POST['phone2']) : null;
    $profile->Street = isset($_POST['street']) ? trim((string)$_POST['street']) : null;
    $profile->Zip = isset($_POST['zip']) ? trim((string)$_POST['zip']) : null;
    $profile->City = isset($_POST['city']) ? trim((string)$_POST['city']) : null;
    $profile->Country = isset($_POST['country']) ? trim((string)$_POST['country']) : null;
    $profile->AccountHolder = isset($_POST['account_holder']) ? trim((string)$_POST['account_holder']) : null;
    $profile->save();

    $mem = new Membership();
    if($mem->load_by_user($userId) && isset($_POST['annual_fee_euro'])) {
        $type = MembershipTypePeriod::userTypeOn($userId) ?: 'aktiv';
        $parsed = MembershipForm::parseEuroToCents($_POST['annual_fee_euro']);
        if($parsed === null) {
            $parsed = MembershipForm::minFeeCents($type);
        }
        $mem->AnnualFeeCents = MembershipForm::clampFeeCents($parsed, $type);
        $mem->save();
    }
    $_SESSION['personFlash'] = 'Stammdaten gespeichert.';
}
elseif($action === 'open_tenure') {
    $from = isset($_POST['tenure_from']) ? trim((string)$_POST['tenure_from']) : '';
    $type = isset($_POST['tenure_type']) ? trim((string)$_POST['tenure_type']) : 'aktiv';
    if($from === '') {
        $_SESSION['personFlash'] = 'Eintrittsdatum fehlt.';
    }
    elseif(MembershipPeriod::userIsMemberOn($userId, $from)) {
        $_SESSION['personFlash'] = 'Bereits Mitglied an diesem Datum.';
    }
    else {
        $mem = new Membership();
        $mem->ensure_for_user($userId);
        if(MembershipPeriod::openForMembership((int)$mem->Index)) {
            $_SESSION['personFlash'] = 'Es gibt bereits eine offene Mitgliedschaft.';
        }
        else {
            if($mem->AnnualFeeCents === null || (int)$mem->AnnualFeeCents < 1) {
                $mem->AnnualFeeCents = MembershipForm::minFeeCents($type);
                $mem->save();
            }
            MembershipPeriod::openTenure((int)$mem->Index, $from, 'Manueller Eintritt');
            MembershipTypePeriod::openType((int)$mem->Index, $type, $from, 'Manueller Eintritt');
            $_SESSION['personFlash'] = 'Eintritt erfasst.';
        }
    }
}
elseif($action === 'close_tenure') {
    $to = isset($_POST['tenure_to']) ? trim((string)$_POST['tenure_to']) : date('Y-m-d');
    $reason = isset($_POST['exit_reason']) ? trim((string)$_POST['exit_reason']) : 'austritt';
    if(!in_array($reason, array('austritt', 'tod'), true)) {
        $reason = 'austritt';
    }
    $mem = new Membership();
    if(!$mem->load_by_user($userId)) {
        $_SESSION['personFlash'] = 'Keine Mitgliedschaft.';
    }
    else {
        MembershipPeriod::closeOpenTenure((int)$mem->Index, $to, $reason);
        MembershipTypePeriod::closeOpenType((int)$mem->Index, $to);
        MembershipPeriod::wipeBankDataForUser($userId);
        $_SESSION['personFlash'] = 'Austritt erfasst ('.$reason.'). SEPA und Bankverbindung gelöscht.';
    }
}
elseif($action === 'switch_type') {
    $from = isset($_POST['type_from']) ? trim((string)$_POST['type_from']) : date('Y-m-d');
    $type = isset($_POST['new_type']) ? trim((string)$_POST['new_type']) : '';
    $note = isset($_POST['type_note']) ? trim((string)$_POST['type_note']) : '';
    $mem = new Membership();
    if(!$mem->load_by_user($userId) || !MembershipPeriod::userIsMemberOn($userId, $from)) {
        $_SESSION['personFlash'] = 'Kein Mitglied an diesem Datum.';
    }
    elseif(!in_array($type, array('aktiv', 'foerdernd'), true)) {
        $_SESSION['personFlash'] = 'Ungültiger Typ.';
    }
    else {
        MembershipTypePeriod::switchType((int)$mem->Index, $type, $from, $note);
        $_SESSION['personFlash'] = 'Typwechsel erfasst.';
    }
}
elseif($action === 'update_period') {
    $periodId = isset($_POST['period_id']) ? (int)$_POST['period_id'] : 0;
    $p = new MembershipPeriod();
    $mem = new Membership();
    if($periodId < 1 || !$p->load_by_id($periodId) || !$mem->load_by_user($userId) || (int)$p->Membership !== (int)$mem->Index) {
        $_SESSION['personFlash'] = 'Mitgliedszeit nicht gefunden.';
    }
    else {
        $from = isset($_POST['date_from']) ? trim((string)$_POST['date_from']) : '';
        $to = isset($_POST['date_to']) ? trim((string)$_POST['date_to']) : '';
        $reason = isset($_POST['exit_reason']) ? trim((string)$_POST['exit_reason']) : '';
        $note = isset($_POST['note']) ? trim((string)$_POST['note']) : '';
        if($from === '') {
            $_SESSION['personFlash'] = 'Von-Datum fehlt.';
        }
        else {
            $willBeOpen = ($to === '');
            if($willBeOpen) {
                foreach(MembershipPeriod::listForMembership((int)$mem->Index) as $other) {
                    if((int)$other->Index === $periodId) {
                        continue;
                    }
                    if($other->DateTo === null || $other->DateTo === '') {
                        $_SESSION['personFlash'] = 'Es gibt bereits eine offene Mitgliedszeit.';
                        header('Location: person.php?id='.$userId);
                        exit;
                    }
                }
            }
            $p->DateFrom = $from;
            $p->DateTo = $willBeOpen ? null : $to;
            $p->ExitReason = $willBeOpen ? null : (in_array($reason, array('austritt', 'tod'), true) ? $reason : 'austritt');
            $p->Note = $note;
            if($p->save()) {
                $_SESSION['personFlash'] = 'Mitgliedszeit gespeichert.';
            }
            else {
                $_SESSION['personFlash'] = 'Mitgliedszeit konnte nicht gespeichert werden.';
            }
        }
    }
}
elseif($action === 'delete_period') {
    $periodId = isset($_POST['period_id']) ? (int)$_POST['period_id'] : 0;
    $p = new MembershipPeriod();
    $mem = new Membership();
    if($periodId < 1 || !$p->load_by_id($periodId) || !$mem->load_by_user($userId) || (int)$p->Membership !== (int)$mem->Index) {
        $_SESSION['personFlash'] = 'Mitgliedszeit nicht gefunden.';
    }
    elseif($p->delete()) {
        $_SESSION['personFlash'] = 'Mitgliedszeit gelöscht.';
    }
    else {
        $_SESSION['personFlash'] = 'Löschen fehlgeschlagen.';
    }
}
elseif($action === 'update_type_period') {
    $periodId = isset($_POST['type_period_id']) ? (int)$_POST['type_period_id'] : 0;
    $tp = new MembershipTypePeriod();
    $mem = new Membership();
    if($periodId < 1 || !$tp->load_by_id($periodId) || !$mem->load_by_user($userId) || (int)$tp->Membership !== (int)$mem->Index) {
        $_SESSION['personFlash'] = 'Typzeit nicht gefunden.';
    }
    else {
        $from = isset($_POST['date_from']) ? trim((string)$_POST['date_from']) : '';
        $to = isset($_POST['date_to']) ? trim((string)$_POST['date_to']) : '';
        $type = isset($_POST['type']) ? trim((string)$_POST['type']) : '';
        $note = isset($_POST['note']) ? trim((string)$_POST['note']) : '';
        if($from === '' || !in_array($type, array('aktiv', 'foerdernd'), true)) {
            $_SESSION['personFlash'] = 'Typzeit ungültig.';
        }
        else {
            $willBeOpen = ($to === '');
            if($willBeOpen) {
                foreach(MembershipTypePeriod::listForMembership((int)$mem->Index) as $other) {
                    if((int)$other->Index === $periodId) {
                        continue;
                    }
                    if($other->DateTo === null || $other->DateTo === '') {
                        $_SESSION['personFlash'] = 'Es gibt bereits eine offene Typzeit.';
                        header('Location: person.php?id='.$userId);
                        exit;
                    }
                }
            }
            $tp->DateFrom = $from;
            $tp->DateTo = $willBeOpen ? null : $to;
            $tp->Type = $type;
            $tp->Note = $note;
            if($tp->save()) {
                $_SESSION['personFlash'] = 'Typzeit gespeichert.';
            }
            else {
                $_SESSION['personFlash'] = 'Typzeit konnte nicht gespeichert werden.';
            }
        }
    }
}
elseif($action === 'delete_type_period') {
    $periodId = isset($_POST['type_period_id']) ? (int)$_POST['type_period_id'] : 0;
    $tp = new MembershipTypePeriod();
    $mem = new Membership();
    if($periodId < 1 || !$tp->load_by_id($periodId) || !$mem->load_by_user($userId) || (int)$tp->Membership !== (int)$mem->Index) {
        $_SESSION['personFlash'] = 'Typzeit nicht gefunden.';
    }
    elseif($tp->delete()) {
        $_SESSION['personFlash'] = 'Typzeit gelöscht.';
    }
    else {
        $_SESSION['personFlash'] = 'Löschen fehlgeschlagen.';
    }
}
elseif($action === 'delete_application') {
    $appId = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
    $app = new MembershipApplication();
    if($appId < 1 || !$app->load_by_id($appId) || (int)$app->User !== $userId) {
        $_SESSION['personFlash'] = 'Antrag nicht gefunden.';
    }
    elseif($app->delete()) {
        $_SESSION['personFlash'] = 'Antrag gelöscht (Mitgliedschaft unverändert).';
    }
    else {
        $_SESSION['personFlash'] = 'Antrag konnte nicht gelöscht werden.';
    }
}
elseif($action === 'sepa_create' || $action === 'sepa_update') {
    $mandateId = isset($_POST['mandate_id']) ? (int)$_POST['mandate_id'] : 0;
    $m = new SepaMandate();
    if($action === 'sepa_update') {
        if($mandateId < 1 || !$m->load_by_id($mandateId) || (int)$m->User !== $userId) {
            $_SESSION['personFlash'] = 'Mandat nicht gefunden.';
            header('Location: person.php?id='.$userId);
            exit;
        }
    }
    else {
        $m->User = $userId;
    }
    $iban = isset($_POST['iban']) ? preg_replace('/\s+/', '', strtoupper(trim((string)$_POST['iban']))) : '';
    $ref = isset($_POST['mandate_ref']) ? trim((string)$_POST['mandate_ref']) : '';
    $from = isset($_POST['valid_from']) ? trim((string)$_POST['valid_from']) : '';
    $to = isset($_POST['valid_to']) ? trim((string)$_POST['valid_to']) : '';
    $bic = isset($_POST['bic']) ? trim((string)$_POST['bic']) : '';
    $active = isset($_POST['active']) ? 1 : 0;
    if($ref === '' || $from === '') {
        $_SESSION['personFlash'] = 'Mandatsreferenz und Gültig-ab sind Pflicht.';
    }
    elseif($iban === '' && ($action === 'sepa_create' || trim((string)$m->IbanEnc) === '')) {
        $_SESSION['personFlash'] = 'IBAN fehlt.';
    }
    else {
        if($iban !== '') {
            $m->IbanEnc = $iban;
        }
        $m->MandateRef = $ref;
        $m->ValidFrom = $from;
        $m->ValidTo = ($to === '') ? null : $to;
        $m->Bic = $bic;
        $m->Active = $active;
        if($m->save()) {
            $_SESSION['personFlash'] = $action === 'sepa_create' ? 'SEPA-Mandat angelegt.' : 'SEPA-Mandat gespeichert.';
        }
        else {
            $_SESSION['personFlash'] = 'SEPA-Mandat ungültig.';
        }
    }
}
elseif($action === 'sepa_delete') {
    $mandateId = isset($_POST['mandate_id']) ? (int)$_POST['mandate_id'] : 0;
    $m = new SepaMandate();
    if($mandateId < 1 || !$m->load_by_id($mandateId) || (int)$m->User !== $userId) {
        $_SESSION['personFlash'] = 'Mandat nicht gefunden.';
    }
    elseif($m->delete()) {
        $_SESSION['personFlash'] = 'SEPA-Mandat gelöscht.';
    }
    else {
        $_SESSION['personFlash'] = 'Löschen fehlgeschlagen.';
    }
}

header('Location: person.php?id='.$userId);
exit;
?>
