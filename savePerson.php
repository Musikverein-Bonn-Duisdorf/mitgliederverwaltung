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
        $_SESSION['personFlash'] = 'Austritt erfasst ('.$reason.').';
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

header('Location: person.php?id='.$userId);
exit;
?>
