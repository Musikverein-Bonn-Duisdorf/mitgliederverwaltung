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

/**
 * Melde platform flag: no User.Admin bypass (same as Melde SSO gate).
 * @param int $userId
 * @return bool
 */
function userMayAccessMitgliederverwaltung($userId) {
    $userId = (int)$userId;
    if($userId < 1) {
        return false;
    }
    return IdentityPermissions::loadForUser($userId)->getPermission('perm_accessMitgliederverwaltung');
}

/** Clear auth fields without destroying the whole session (flash/errors may remain). */
function clearAuthSession() {
    unset(
        $_SESSION['userid'],
        $_SESSION['Vorname'],
        $_SESSION['Nachname'],
        $_SESSION['username'],
        $_SESSION['admin'],
        $_SESSION['singleUsePW']
    );
}

/**
 * Admin = Melde User.Admin OR Melde ops flags OR any mit_Permissions flag.
 * @param int $userId
 * @param bool $legacyAdmin User.Admin column
 * @return bool
 */
function computeAdminForUser($userId, $legacyAdmin = false) {
    if($legacyAdmin) {
        return true;
    }
    $userId = (int)$userId;
    if($userId < 1) {
        return false;
    }
    if(class_exists('Permissions') && Permissions::loadByUser($userId)->isAdmin()) {
        return true;
    }
    if(class_exists('Permissions') && Permissions::bootstrapEditAllowed($userId)) {
        return true;
    }
    return IdentityPermissions::loadForUser($userId)->isAdmin();
}

/** Refresh $_SESSION['admin'] from Melde User.Admin OR Permissions. */
function refreshSessionAdmin() {
    if(!isset($_SESSION['userid'])) {
        return;
    }
    $uid = (int)$_SESSION['userid'];
    if($uid < 1) {
        return;
    }
    $sql = sprintf(
        "SELECT `Admin` FROM `%sUser` WHERE `Index` = %d LIMIT 1;",
        identityPrefix(),
        $uid
    );
    $dbr = @mysqli_query($GLOBALS['conn'], $sql);
    $row = ($dbr) ? mysqli_fetch_assoc($dbr) : null;
    $legacy = $row ? (bool)$row['Admin'] : false;
    $_SESSION['admin'] = computeAdminForUser($uid, $legacy);
}

/**
 * Drop session if logged in without Melde module access.
 * @return bool true if still allowed (or not logged in)
 */
function enforceMitgliederverwaltungAccess() {
    if(!loggedIn()) {
        return true;
    }
    $uid = (int)$_SESSION['userid'];
    if(userMayAccessMitgliederverwaltung($uid)) {
        refreshSessionAdmin();
        return true;
    }
    clearAuthSession();
    $GLOBALS['loginDeniedNoAccess'] = true;
    return false;
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
    if(!userMayAccessMitgliederverwaltung((int)$user->Index)) {
        clearAuthSession();
        $GLOBALS['loginDeniedNoAccess'] = true;
        return false;
    }
    $_SESSION['userid'] = (int)$user->Index;
    $_SESSION['Vorname'] = $user->Vorname;
    $_SESSION['Nachname'] = $user->Nachname;
    $_SESSION['username'] = $user->getName();
    $_SESSION['admin'] = computeAdminForUser((int)$user->Index, (bool)$user->Admin);
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
    clearAuthSession();
    $GLOBALS['loginDeniedNoAccess'] = false;
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
    if(!userMayAccessMitgliederverwaltung((int)$user->Index)) {
        clearAuthSession();
        $GLOBALS['loginDeniedNoAccess'] = true;
        return false;
    }
    $_SESSION['userid'] = (int)$user->Index;
    $_SESSION['Vorname'] = $user->Vorname;
    $_SESSION['Nachname'] = $user->Nachname;
    $_SESSION['username'] = $user->getName();
    $_SESSION['admin'] = computeAdminForUser((int)$user->Index, (bool)$user->Admin);
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

function requireLoggedInOrRedirect() {
    if(!loggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function setFlash($type, $message) {
    $_SESSION['flash'] = array(
        'type' => (string)$type,
        'message' => (string)$message,
    );
}

function getFlash() {
    if(!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Queue a toast outside .app-main (footer dumps $mlDeferredToasts).
 * @param array|null $flash
 * @return string Always empty (toast is deferred)
 */
function renderFlashHtml($flash = null) {
    if($flash === null) {
        $flash = getFlash();
    }
    if(!$flash || $flash['message'] === '') {
        return '';
    }
    $isError = ($flash['type'] === 'error');
    $mod = $isError ? 'app-toast--error' : 'app-toast--success';
    $role = $isError ? 'alert' : 'status';
    $attrs = $isError ? '' : ' data-autodismiss="3500"';
    $close = $isError
        ? '<button type="button" class="app-toast-close" aria-label="Hinweis schließen">&times;</button>'
        : '';
    $html = '<div class="app-toast '.$mod.'" role="'.$role.'"'.$attrs.'>'
        .'<div class="app-toast-body">'
        .htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8')
        .'</div>'
        .$close
        .'</div>';
    if(!isset($GLOBALS['mlDeferredToasts'])) {
        $GLOBALS['mlDeferredToasts'] = '';
    }
    $GLOBALS['mlDeferredToasts'] .= $html;
    return '';
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
 * Non-fatal permission check.
 * MIT keys (mit_Permissions): no Melde Admin bypass — only assigned MIT rights (+ bootstrap for editPermissions).
 * Melde ops keys (config/log): Melde User.Admin or Melde Permissions.
 * @param string $perm e.g. perm_showUsers / perm_editConfig
 * @return bool
 */
function hasPermission($perm = '') {
    $uid = isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : 0;
    if($uid < 1) {
        return false;
    }
    if($perm !== '' && class_exists('Permissions') && Permissions::isMitKey($perm)) {
        if(Permissions::loadByUser($uid)->getPermission($perm)) {
            return true;
        }
        if($perm === 'perm_editPermissions' && Permissions::bootstrapEditAllowed($uid)) {
            return true;
        }
        return false;
    }
    $sql = sprintf(
        "SELECT `Admin` FROM `%sUser` WHERE `Index` = %d LIMIT 1;",
        identityPrefix(),
        $uid
    );
    $dbr = @mysqli_query($GLOBALS['conn'], $sql);
    $row = ($dbr) ? mysqli_fetch_assoc($dbr) : null;
    if($row && !empty($row['Admin'])) {
        return true;
    }
    if($perm === '') {
        return computeAdminForUser($uid, false);
    }
    return IdentityPermissions::loadForUser($uid)->getPermission($perm);
}

/**
 * Require a permission (MIT mit_Permissions or Melde ops). Exits on deny.
 * @param string $perm e.g. perm_showUsers / perm_editConfig
 */
function requirePermission($perm = 'perm_editConfig') {
    if(!loggedIn()) {
        if(!headers_sent()) {
            header('Location: login.php');
        }
        exit;
    }
    refreshSessionAdmin();
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

/**
 * Run a git command in the repo root; return stdout or null on failure.
 * @param string $args Arguments after `git` (already escaped where needed)
 * @return string|null
 */
function gitRepoOutput($args) {
    $root = dirname(__DIR__);
    if(!is_dir($root.'/.git') && !is_file($root.'/.git')) {
        return null;
    }
    $cmd = 'git -C '.escapeshellarg($root).' '.$args.' 2>/dev/null';
    $out = array();
    $code = 1;
    exec($cmd, $out, $code);
    if($code !== 0) {
        return null;
    }
    return implode("\n", $out);
}

/** Short git HEAD hash, or null if unavailable. */
function getGitHeadShort() {
    $head = gitRepoOutput('rev-parse --short=7 HEAD');
    if($head === null || $head === '') {
        return null;
    }
    return trim($head);
}

/** Full hash of the git commit that created the current VERSION string, or null. */
function getGitReleaseCommitHash() {
    $version = isset($GLOBALS['version']['String']) ? (string)$GLOBALS['version']['String'] : '';
    if($version === '') {
        return null;
    }
    $hash = gitRepoOutput('log -1 --fixed-strings --grep='.escapeshellarg('release '.$version).' --pretty=%H');
    if($hash === null || $hash === '') {
        return null;
    }
    return trim(explode("\n", $hash)[0]);
}

/** True when working tree HEAD is not exactly the release commit for VERSION. */
function isUnreleasedGitCheckout() {
    $head = gitRepoOutput('rev-parse HEAD');
    $release = getGitReleaseCommitHash();
    if($head === null || $release === null) {
        return false;
    }
    return trim($head) !== trim($release);
}

/**
 * Commit subjects since the VERSION release commit (for unreleased changelog row).
 * @return string[]
 */
function collectUnreleasedGitNotes() {
    $release = getGitReleaseCommitHash();
    if($release === null) {
        return array();
    }
    $log = gitRepoOutput('log --pretty=%s '.escapeshellarg($release.'..HEAD'));
    if($log === null || $log === '') {
        return array();
    }
    $notes = array();
    $seen = array();
    foreach(explode("\n", $log) as $subj) {
        $subj = trim($subj);
        if($subj === '' || isset($seen[$subj])) {
            continue;
        }
        if(preg_match('/^Merge /i', $subj)) {
            continue;
        }
        if(preg_match('/^release\s+/i', $subj)) {
            continue;
        }
        if(preg_match('/^Sync release/i', $subj)) {
            continue;
        }
        $seen[$subj] = true;
        $notes[] = $subj;
        if(count($notes) >= 20) {
            break;
        }
    }
    return $notes;
}

/**
 * Parse CHANGELOG.md into structured release entries.
 * @return array<int,array{version:string,date:string,notes:string[],unreleased?:bool}>
 */
function parseChangelogEntries() {
    $path = dirname(__DIR__).'/CHANGELOG.md';
    if(!is_file($path)) {
        return array();
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if($lines === false) {
        return array();
    }
    $entries = array();
    $current = null;
    foreach($lines as $line) {
        $line = rtrim($line);
        if(preg_match('/^##\s+(\S+)\s+\((\d{4}-\d{2}-\d{2})\)\s*$/', $line, $m)) {
            if($current !== null) {
                $entries[] = $current;
            }
            $current = array(
                'version' => $m[1],
                'date' => $m[2],
                'notes' => array(),
                'unreleased' => false
            );
            continue;
        }
        if($current === null) {
            continue;
        }
        if(preg_match('/^-\s+(.+)$/', $line, $m)) {
            $current['notes'][] = $m[1];
        }
    }
    if($current !== null) {
        $entries[] = $current;
    }
    return $entries;
}

/**
 * Render CHANGELOG.md as an HTML table for the Hilfe page.
 * Prepends an unreleased row when HEAD is ahead of the VERSION release commit.
 */
function renderChangelogHtml() {
    $entries = parseChangelogEntries();
    $currentVersion = isset($GLOBALS['version']['String']) ? (string)$GLOBALS['version']['String'] : '';
    $unreleased = isUnreleasedGitCheckout();
    $headShort = $unreleased ? getGitHeadShort() : null;

    if($unreleased) {
        $notes = collectUnreleasedGitNotes();
        if(!$notes) {
            $notes = array('Noch nicht released (Commit '.($headShort ? $headShort : 'dev').')');
        }
        array_unshift($entries, array(
            'version' => $headShort ? ('unreleased-'.$headShort) : 'unreleased',
            'date' => date('Y-m-d'),
            'notes' => $notes,
            'unreleased' => true
        ));
    }

    if(!$entries) {
        return '<p class="w3-text-gray">Kein Changelog vorhanden.</p>';
    }

    $html = '<div class="help-changelog-wrap">'."\n";
    $html .= '<table class="w3-table w3-striped w3-bordered help-changelog-table">'."\n";
    $html .= '<thead><tr>'
        .'<th>Version</th>'
        .'<th>Datum</th>'
        .'<th>&Auml;nderungen</th>'
        .'</tr></thead>'."\n<tbody>\n";
    foreach($entries as $entry) {
        $notes = $entry['notes'];
        if(!$notes) {
            $notes = array('(keine weiteren Notizen)');
        }
        $isUnreleasedRow = !empty($entry['unreleased']);
        $isCurrent = $isUnreleasedRow
            ? true
            : (!$unreleased && $currentVersion !== '' && $entry['version'] === $currentVersion);
        $rowClass = '';
        if($isCurrent && $isUnreleasedRow) {
            $rowClass = ' class="help-changelog-current help-changelog-unreleased"';
        }
        elseif($isCurrent) {
            $rowClass = ' class="help-changelog-current"';
        }
        elseif($isUnreleasedRow) {
            $rowClass = ' class="help-changelog-unreleased"';
        }
        $html .= '<tr'.$rowClass.'>';
        $html .= '<td class="help-changelog-version"><code>'
            .htmlspecialchars($entry['version'], ENT_QUOTES, 'UTF-8')
            .'</code>';
        if($isCurrent && $isUnreleasedRow) {
            $html .= ' <span class="help-changelog-badge help-changelog-badge-unreleased">nicht released</span>';
        }
        elseif($isCurrent) {
            $html .= ' <span class="help-changelog-badge">aktuell</span>';
        }
        $html .= '</td>';
        $html .= '<td class="help-changelog-date">'
            .htmlspecialchars($entry['date'], ENT_QUOTES, 'UTF-8')
            .'</td>';
        $html .= '<td class="help-changelog-notes"><ul class="help-changelog-list">';
        foreach($notes as $note) {
            $html .= '<li>'.htmlspecialchars($note, ENT_QUOTES, 'UTF-8').'</li>';
        }
        $html .= '</ul></td></tr>'."\n";
    }
    $html .= "</tbody></table>\n</div>\n";
    return $html;
}

/**
 * Render a view under views/{name}.php
 * @param string $view Relative path without .php (e.g. help/guide)
 * @param array $vars Variables extracted into the view scope
 * @return string
 */
function render($view, $vars = array()) {
    $path = dirname(__DIR__).'/views/'.$view.'.php';
    if(!is_file($path)) {
        trigger_error('View not found: '.$view, E_USER_WARNING);
        return '';
    }
    extract($vars, EXTR_SKIP);
    ob_start();
    include $path;
    return ob_get_clean();
}
?>
