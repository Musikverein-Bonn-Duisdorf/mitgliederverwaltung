<?php
function identityPrefix() {
    return isset($GLOBALS['identityPrefix']) ? $GLOBALS['identityPrefix'] : 'meldeliste_';
}

function loadconfig() {
    $optionsDB = array();
    $sql = sprintf('SELECT * FROM `%sconfig`;', $GLOBALS['dbprefix']);
    // PHP 8+ mysqli may throw; missing config = fresh install / defaults only.
    try {
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
    }
    catch(Throwable $e) {
        $dbr = false;
    }
    if($dbr) {
        while($row = mysqli_fetch_array($dbr)) {
            $optionsDB[$row['Parameter']] = $row['Value'];
        }
    }
    if(function_exists('getConfigDefaults')) {
        foreach(getConfigDefaults() as $item) {
            if(!array_key_exists($item['Parameter'], $optionsDB)) {
                $optionsDB[$item['Parameter']] = $item['Value'];
            }
        }
    }
    return $optionsDB;
}

function loggedIn() {
    if(!isset($_SESSION['userid'])) {
        session_destroy();
        return false;
    }
    if((int)$_SESSION['userid'] > 0) {
        return true;
    }
    session_destroy();
    return false;
}

function requireLogin() {
    if(!loggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sqlerror() {
    if(!isset($GLOBALS['conn']) || !mysqli_errno($GLOBALS['conn'])) {
        return;
    }
    if(php_sapi_name() === 'cli') {
        fwrite(STDERR, 'SQL ERROR '.mysqli_errno($GLOBALS['conn']).': '.mysqli_error($GLOBALS['conn'])."\n");
        return;
    }
    $color = isset($GLOBALS['optionsDB']['colorLogError']) ? $GLOBALS['optionsDB']['colorLogError'] : 'w3-pale-red';
    echo '<div class="w3-container '.$color.' w3-mobile w3-border w3-padding"><b>SQL ERROR </b>'
        .h(mysqli_errno($GLOBALS['conn']).': '.mysqli_error($GLOBALS['conn']))
        .'</div>';
}

function getPage($string) {
    if(!isset($_SESSION['page'])) {
        echo isset($GLOBALS['optionsDB']['colorNav']) ? $GLOBALS['optionsDB']['colorNav'] : 'w3-dark-grey';
        return;
    }
    if($string === $_SESSION['page']) {
        echo isset($GLOBALS['optionsDB']['colorTitleBar']) ? $GLOBALS['optionsDB']['colorTitleBar'] : 'w3-pale-blue';
    }
    else {
        echo isset($GLOBALS['optionsDB']['colorNav']) ? $GLOBALS['optionsDB']['colorNav'] : 'w3-dark-grey';
    }
}

function loginUserBySsoId($userId) {
    $userId = (int)$userId;
    if($userId < 1) {
        return false;
    }
    $user = new IdentityUser();
    if(!$user->load_by_id($userId) || (int)$user->Deleted === 1) {
        return false;
    }
    $_SESSION['userid'] = (int)$user->Index;
    $_SESSION['Vorname'] = $user->Vorname;
    $_SESSION['Nachname'] = $user->Nachname;
    $_SESSION['username'] = $user->getName();
    $_SESSION['admin'] = (bool)$user->Admin;
    $_SESSION['singleUsePW'] = (bool)$user->singleUsePW;
    return true;
}

function tryMeldeSsoLoginFromRequest() {
    if(!isset($_GET['sso']) || trim((string)$_GET['sso']) === '') {
        return false;
    }
    $userId = SsoTicket::redeem($_GET['sso']);
    if(!$userId) {
        return false;
    }
    return loginUserBySsoId($userId);
}

function validateUser($login, $password) {
    $_SESSION['userid'] = 0;
    $login = trim((string)$login);
    if($login === '' || $password === null || $password === '') {
        return false;
    }
    $user = new IdentityUser();
    if(!$user->load_by_login($login)) {
        return false;
    }
    if(!password_verify((string)$password, (string)$user->Passhash)) {
        return false;
    }
    $_SESSION['userid'] = (int)$user->Index;
    $_SESSION['Vorname'] = $user->Vorname;
    $_SESSION['Nachname'] = $user->Nachname;
    $_SESSION['username'] = $user->getName();
    $_SESSION['admin'] = (bool)$user->Admin;
    $_SESSION['singleUsePW'] = (bool)$user->singleUsePW;
    return true;
}

function maskIban($ibanEnc) {
    $raw = trim((string)$ibanEnc);
    if($raw === '') {
        return '';
    }
    $len = strlen($raw);
    if($len <= 4) {
        return str_repeat('*', $len);
    }
    return str_repeat('*', max(4, $len - 4)).substr($raw, -4);
}

function mkNULLstr($val) {
    if($val === null || $val === '') {
        return 'NULL';
    }
    return '"'.mysqli_real_escape_string($GLOBALS['conn'], (string)$val).'"';
}

function germanDate($string) {
    if($string === '' || $string === null) {
        return '';
    }
    $y = substr((string)$string, 0, 4);
    $m = substr((string)$string, 5, 2);
    $d = substr((string)$string, 8, 2);
    return $d.'.'.$m.'.'.$y;
}
?>
