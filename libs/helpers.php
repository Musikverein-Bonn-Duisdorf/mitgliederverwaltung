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
    // Migrate legacy w3-* color class defaults to Melde Hex kit when still stored.
    $legacyW3Colors = array(
        'colorBackground' => 'w3-light-grey',
        'colorTitle' => 'w3-teal',
        'colorTitleBar' => 'w3-pale-blue',
        'colorNav' => 'w3-dark-grey',
        'colorNavAdmin' => 'w3-grey',
        'colorInputBackground' => 'w3-white',
        'colorBtnSubmit' => 'w3-blue',
        'colorSuccess' => 'w3-pale-green',
        'colorLogError' => 'w3-pale-red',
        'colorLogWarning' => 'w3-pale-yellow',
    );
    if(function_exists('getConfigDefaults')) {
        $defaultsByKey = array();
        foreach(getConfigDefaults() as $item) {
            $defaultsByKey[$item['Parameter']] = $item['Value'];
        }
        foreach($legacyW3Colors as $param => $legacy) {
            if(isset($optionsDB[$param]) && (string)$optionsDB[$param] === $legacy
                && isset($defaultsByKey[$param])) {
                $optionsDB[$param] = $defaultsByKey[$param];
            }
        }
    }
    if(function_exists('getColorConfigParameters') && function_exists('colorToCssClass')) {
        $colorParams = getColorConfigParameters();
        foreach($optionsDB as $param => $value) {
            if(isset($colorParams[$param]) || (function_exists('isHexColor') && isHexColor($value))) {
                $optionsDB[$param] = colorToCssClass($value);
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

if(!function_exists('sqlerror')) {
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
}

function getPage($string, $groupId = '') {
    $page = isset($_SESSION['page']) ? (string)$_SESSION['page'] : '';
    if($string === $page) {
        echo isset($GLOBALS['optionsDB']['colorTitleBar']) ? $GLOBALS['optionsDB']['colorTitleBar'] : '';
        return;
    }
    if($groupId !== '' && function_exists('navGroupClass')) {
        echo navGroupClass($groupId);
        return;
    }
    echo isset($GLOBALS['optionsDB']['colorNav']) ? $GLOBALS['optionsDB']['colorNav'] : '';
}

function getAdminPage($string) {
    $page = isset($_SESSION['page']) ? (string)$_SESSION['page'] : '';
    if($string === $page && !empty($_SESSION['adminpage'])) {
        echo isset($GLOBALS['optionsDB']['colorTitleBar']) ? $GLOBALS['optionsDB']['colorTitleBar'] : '';
    }
    else {
        echo isset($GLOBALS['optionsDB']['colorNavAdmin']) ? $GLOBALS['optionsDB']['colorNavAdmin'] : '';
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

function redirectAfterPost($url) {
    while(ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: '.$url);
    exit;
}

/**
 * Request helper (POST preferred, then GET).
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function mitRequest($key, $default = null) {
    if(isset($_POST[$key])) {
        return $_POST[$key];
    }
    if(isset($_GET[$key])) {
        return $_GET[$key];
    }
    return $default;
}

function csrf_token() {
    if(empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify($token) {
    if(!isset($_SESSION['csrf_token']) || !is_string($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="'
        .htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8').'">';
}

function bool2string($val) {
    if($val) return 'ja';
    return 'nein';
}

function formatConfigLogValue($value, $type = '') {
    if($value === null || $value === '') {
        return '(leer)';
    }
    if($type === 'bool') {
        return bool2string($value);
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function logConfigChange($parameter, $oldValue, $newValue, $type = '') {
    if((string)$oldValue === (string)$newValue) {
        return;
    }
    $label = (string)$parameter;
    if($type === '' && function_exists('getConfigDefaults')) {
        foreach(getConfigDefaults() as $item) {
            if($item['Parameter'] === $parameter) {
                $type = isset($item['Type']) ? (string)$item['Type'] : '';
                if(!empty($item['Description'])) {
                    $label = $parameter.' ('.$item['Description'].')';
                }
                break;
            }
        }
    }
    if(!class_exists('Log')) {
        return;
    }
    $logentry = new Log;
    $logentry->DBupdate(sprintf(
        'Config <b>%s</b>: %s &rArr; <b>%s</b>',
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
        formatConfigLogValue($oldValue, $type),
        formatConfigLogValue($newValue, $type)
    ));
}

/**
 * Params edited outside the config form (schemes UI / updater).
 */
function mitIsHiddenConfigParam($parameter) {
    return in_array((string)$parameter, array(
        'colorSchemeActive',
        'colorSchemes',
        'SchemaVersion',
    ), true);
}

/**
 * True when a DBupdate log message encodes a real field change (Melde parity).
 */
function logMessageHasChanges($message) {
    $message = (string)$message;
    if($message === '') {
        return false;
    }
    if(strpos($message, '&rArr;') !== false) {
        return true;
    }
    if(strpos($message, '(vorher:') !== false) {
        return true;
    }
    if(preg_match('/\b(?:Passhash|activeLink)\s+geändert\b/u', $message)) {
        return true;
    }
    if(strpos($message, 'zurückgesetzt') !== false) {
        return true;
    }
    if(strpos($message, 'umbenannt:') !== false) {
        return true;
    }
    return false;
}

/**
 * Melde-compat: until IdentityPermissions is ported, only User.Admin grants access.
 * @param string $perm e.g. perm_editConfig / perm_showLog (ignored except for API shape)
 */
function hasPermission($perm = '') {
    return !empty($_SESSION['admin']);
}

/**
 * Require Melde User.Admin (session). Later: perm_editConfig via IdentityPermissions.
 * @param string $perm unused until Permissions matrix is wired
 */
function requirePermission($perm = 'perm_editConfig') {
    if(!loggedIn()) {
        if(!headers_sent()) {
            header('Location: login.php');
        }
        exit;
    }
    if(hasPermission($perm)) {
        return;
    }
    denyAccess('Keine Berechtigung für diesen Bereich.');
}

function requireMitAdmin() {
    requirePermission('perm_editConfig');
}

/**
 * @param string $message
 */
function denyAccess($message = 'Keine Berechtigung für diesen Bereich.') {
    if(!headers_sent()) {
        http_response_code(403);
    }
    $color = isset($GLOBALS['optionsDB']['colorLogWarning'])
        ? $GLOBALS['optionsDB']['colorLogWarning']
        : 'w3-orange';
    $panel = '<div class="w3-panel '.$color.' w3-padding w3-margin">'
        .'<h3>Zugriff verweigert</h3>'
        .'<p>'.htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8').'</p>'
        .'</div>';
    if(!empty($GLOBALS['mlHeaderRendered']) && file_exists(__DIR__.'/../common/footer.php')) {
        echo $panel;
        include __DIR__.'/../common/footer.php';
    }
    else {
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Zugriff verweigert</title>'
            .'<link rel="stylesheet" href="styles/w3.css"></head><body>'
            .$panel
            .'<p class="w3-padding"><a href="index.php">Zur Übersicht</a> · <a href="login.php">Login</a></p>'
            .'</body></html>';
    }
    exit;
}
?>
