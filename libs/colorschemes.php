<?php
/**
 * Five editable brand color schemes (Melde-parity for Mitgliederverwaltung).
 * Live color* config params mirror the active scheme.
 *
 * Only MIT UI colors (see getConfigDefaults). No Melde RSVP / appointment /
 * member-status / unused log-chip keys.
 *
 * Markenkit:
 *   #FDFFFC Weiß | #040006 Schwarz | #345A95 Blau | #969696 Grau
 *   #454545 Dunkelgrau | #FDF9E7 Creme | #FFC300 Gold | #7F9DC1 Hellblau
 */

function getColorSchemeIds() {
    return array('classic', 'light', 'dark', 'gold', 'soft');
}

/** Brand palette tokens (uppercase hex). */
function getBrandPalette() {
    return array(
        'white' => '#FDFFFC',
        'black' => '#040006',
        'blue' => '#345A95',
        'gray' => '#969696',
        'darkGray' => '#454545',
        'egg' => '#FDF9E7',
        'gold' => '#FFC300',
        'lightBlue' => '#7F9DC1',
    );
}

/**
 * Complete color map for one scheme — every Archiv color* config key must be present.
 * Only brand-kit hex (or empty) allowed.
 */
function buildBrandSchemeColors($chrome) {
    $p = getBrandPalette();
    $c = array_merge(array(
        'colorBackground' => $p['egg'],
        'colorInputBackground' => $p['egg'],
        'colorTitle' => $p['blue'],
        'colorTitleBar' => $p['blue'],
        'colorNav' => $p['blue'],
        'colorNavAdmin' => $p['darkGray'],
        'colorBtnSubmit' => $p['blue'],
        'colorBtnEdit' => $p['lightBlue'],
        'colorWarning' => $p['gold'],
        'colorSuccess' => $p['blue'],
        'colorBtnDelete' => $p['darkGray'],
        'colorLogFatal' => $p['black'],
        'colorLogError' => $p['darkGray'],
        'colorLogWarning' => $p['gold'],
    ), $chrome);

    return $c;
}

function getDefaultColorSchemes() {
    $p = getBrandPalette();
    return array(
        'classic' => array(
            'name' => 'Klassisch',
            'colors' => array(
                'colorBackground' => '#FDFFFC',
                'colorTitle' => '#FDF9E7',
                'colorTitleBar' => '#345A95',
                'colorWarning' => '#FDF9E7',
                'colorBtnDelete' => '#F44336',
                'colorInputBackground' => '#F1F1F1',
                'colorBtnSubmit' => '#9C27B0',
                'colorLogFatal' => '#F44336',
                'colorLogError' => '#FFC300',
                'colorLogWarning' => '#FFC300',
                'colorBtnEdit' => '#009688',
                'colorSuccess' => '#4CAF50',
                'colorNav' => '#969696',
                'colorNavAdmin' => '#607D8B',
            ),
        ),
        'light' => array(
            'name' => 'Hell',
            'colors' => buildBrandSchemeColors(array(
                'colorBackground' => $p['white'],
                'colorInputBackground' => $p['egg'],
                'colorTitle' => $p['blue'],
                'colorTitleBar' => $p['lightBlue'],
                'colorNav' => $p['blue'],
                'colorNavAdmin' => $p['gray'],
                'colorBtnSubmit' => $p['blue'],
                'colorBtnEdit' => $p['lightBlue'],
                'colorWarning' => $p['gold'],
                'colorLogWarning' => $p['lightBlue'],
            )),
        ),
        'dark' => array(
            'name' => 'Dunkel',
            'colors' => buildBrandSchemeColors(array(
                'colorBackground' => $p['black'],
                'colorInputBackground' => $p['darkGray'],
                'colorTitle' => $p['blue'],
                'colorTitleBar' => $p['darkGray'],
                'colorNav' => $p['blue'],
                'colorNavAdmin' => $p['darkGray'],
                'colorBtnSubmit' => $p['lightBlue'],
                'colorBtnEdit' => $p['gold'],
                'colorWarning' => $p['gold'],
                'colorSuccess' => $p['lightBlue'],
                'colorBtnDelete' => $p['gray'],
                'colorLogFatal' => $p['gold'],
                'colorLogError' => $p['gray'],
                'colorLogWarning' => $p['gold'],
            )),
        ),
        'gold' => array(
            'name' => 'Gold',
            'colors' => buildBrandSchemeColors(array(
                'colorBackground' => $p['egg'],
                'colorInputBackground' => $p['white'],
                'colorTitle' => $p['gold'],
                'colorTitleBar' => $p['blue'],
                'colorNav' => $p['blue'],
                'colorNavAdmin' => $p['darkGray'],
                'colorBtnSubmit' => $p['gold'],
                'colorBtnEdit' => $p['blue'],
                'colorWarning' => $p['gold'],
                'colorSuccess' => $p['blue'],
                'colorLogWarning' => $p['gold'],
            )),
        ),
        'soft' => array(
            'name' => 'Soft-Blau',
            'colors' => buildBrandSchemeColors(array(
                'colorBackground' => $p['white'],
                'colorInputBackground' => $p['egg'],
                'colorTitle' => $p['lightBlue'],
                'colorTitleBar' => $p['lightBlue'],
                'colorNav' => $p['lightBlue'],
                'colorNavAdmin' => $p['gray'],
                'colorBtnSubmit' => $p['blue'],
                'colorBtnEdit' => $p['blue'],
                'colorWarning' => $p['gold'],
                'colorSuccess' => $p['blue'],
                'colorLogWarning' => $p['lightBlue'],
            )),
        ),
    );
}

function getClassicBrandColorDefaults() {
    $schemes = getDefaultColorSchemes();
    return $schemes['classic']['colors'];
}

function getConfigParamRawValue($parameter) {
    if(function_exists('archivResolveConfigParam')) {
        $parameter = archivResolveConfigParam($parameter);
    }
    $sql = sprintf(
        'SELECT `Value` FROM `%sconfig` WHERE `Parameter` = "%s" LIMIT 1;',
        $GLOBALS['dbprefix'],
        mysqli_real_escape_string($GLOBALS['conn'], $parameter)
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    if(!$dbr) return null;
    $row = mysqli_fetch_assoc($dbr);
    return $row ? (string)$row['Value'] : null;
}

function setConfigParamRawValue($parameter, $value, $opts = array()) {
    $silent = !empty($opts['silent']);
    $conn = $GLOBALS['conn'];
    $prefix = isset($GLOBALS['dbprefix']) ? (string)$GLOBALS['dbprefix'] : '';
    $identity = function_exists('identityPrefix') ? (string)identityPrefix() : '';
    if($prefix === '' || ($identity !== '' && $prefix === $identity)) {
        return false;
    }
    // Never write Melde-shared bare keys; map to Archiv* storage names.
    if(function_exists('archivResolveConfigParam')) {
        $parameter = archivResolveConfigParam($parameter);
    }
    $escapedParam = mysqli_real_escape_string($conn, $parameter);
    $escapedValue = mysqli_real_escape_string($conn, (string)$value);

    $oldValue = null;
    $type = 'string';
    $sql = sprintf(
        'SELECT `Parameter`, `Value`, `Type` FROM `%sconfig` WHERE `Parameter` = "%s" LIMIT 1;',
        $prefix,
        $escapedParam
    );
    $dbr = mysqli_query($conn, $sql);
    $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
    if($row) {
        $oldValue = (string)$row['Value'];
        if(isset($row['Type']) && $row['Type'] !== '') {
            $type = (string)$row['Type'];
        }
        $sql = sprintf(
            'UPDATE `%sconfig` SET `Value` = "%s" WHERE `Parameter` = "%s";',
            $prefix,
            $escapedValue,
            $escapedParam
        );
    }
    else {
        $desc = '';
        if(function_exists('getConfigDefaults')) {
            foreach(getConfigDefaults() as $item) {
                if($item['Parameter'] === $parameter) {
                    $type = $item['Type'];
                    $desc = $item['Description'];
                    break;
                }
            }
        }
        $sql = sprintf(
            'INSERT INTO `%sconfig` (`Parameter`, `Value`, `Type`, `Description`) VALUES ("%s", "%s", "%s", "%s");',
            $prefix,
            $escapedParam,
            $escapedValue,
            mysqli_real_escape_string($conn, $type),
            mysqli_real_escape_string($conn, $desc)
        );
    }
    $ok = mysqli_query($conn, $sql);
    sqlerror();
    if($ok && !$silent && $type !== 'internal') {
        logConfigChange($parameter, $oldValue === null ? '' : $oldValue, $value, $type);
    }
    return (bool)$ok;
}

function loadColorSchemes() {
    $defaults = getDefaultColorSchemes();
    $raw = getConfigParamRawValue('colorSchemes');
    if($raw === null || trim($raw) === '') {
        return $defaults;
    }
    $decoded = json_decode($raw, true);
    if(!is_array($decoded)) {
        return $defaults;
    }
    foreach($defaults as $id => $scheme) {
        if(!isset($decoded[$id]) || !is_array($decoded[$id])) {
            $decoded[$id] = $scheme;
            continue;
        }
        if(!isset($decoded[$id]['name']) || $decoded[$id]['name'] === '') {
            $decoded[$id]['name'] = $scheme['name'];
        }
        if(!isset($decoded[$id]['colors']) || !is_array($decoded[$id]['colors'])) {
            $decoded[$id]['colors'] = $scheme['colors'];
        }
        else {
            foreach($scheme['colors'] as $k => $v) {
                if(!array_key_exists($k, $decoded[$id]['colors'])) {
                    $decoded[$id]['colors'][$k] = $v;
                }
            }
        }
    }
    return $decoded;
}

function saveColorSchemes($schemes) {
    $json = json_encode($schemes, JSON_UNESCAPED_UNICODE);
    if($json === false) {
        return false;
    }
    // Farbschema-JSON: Einzeländerungen loggen die betroffenen Farbparameter (savePara).
    return setConfigParamRawValue('colorSchemes', $json, array('silent' => true));
}

function getActiveColorSchemeId() {
    $id = getConfigParamRawValue('colorSchemeActive');
    if($id === null || $id === '') {
        return 'classic';
    }
    $schemes = loadColorSchemes();
    return isset($schemes[$id]) ? $id : 'classic';
}

function applyColorScheme($schemeId) {
    $schemes = loadColorSchemes();
    if(!isset($schemes[$schemeId])) {
        return false;
    }
    setConfigParamRawValue('colorSchemeActive', $schemeId, array('silent' => true));
    $colors = isset($schemes[$schemeId]['colors']) && is_array($schemes[$schemeId]['colors'])
        ? $schemes[$schemeId]['colors']
        : array();
    // Prefer scheme color keys that exist in Archiv ConfigDefaults; fall back to known color params.
    $known = function_exists('getColorConfigParameters') ? getColorConfigParameters() : array();
    $params = array_keys($colors);
    if(count($params) === 0) {
        $params = array_keys($known);
    }
    foreach($params as $param) {
        if(!array_key_exists($param, $colors)) {
            continue;
        }
        if(count($known) > 0 && !isset($known[$param])) {
            continue;
        }
        $value = (string)$colors[$param];
        if($value !== '' && function_exists('isHexColor') && !isHexColor($value)) {
            continue;
        }
        if($value !== '' && function_exists('normalizeHexColor')) {
            $value = normalizeHexColor($value);
        }
        setConfigParamRawValue($param, $value, array('silent' => true));
    }
    return true;
}

function updateActiveSchemeColor($parameter, $value) {
    $schemes = loadColorSchemes();
    $id = getActiveColorSchemeId();
    if(!isset($schemes[$id])) {
        return false;
    }
    if(!isset($schemes[$id]['colors']) || !is_array($schemes[$id]['colors'])) {
        $schemes[$id]['colors'] = array();
    }
    $schemes[$id]['colors'][$parameter] = $value;
    return saveColorSchemes($schemes);
}

function renameActiveColorScheme($name) {
    $name = trim((string)$name);
    if($name === '') {
        return false;
    }
    $schemes = loadColorSchemes();
    $id = getActiveColorSchemeId();
    if(!isset($schemes[$id])) {
        return false;
    }
    $schemes[$id]['name'] = $name;
    return saveColorSchemes($schemes);
}

function resetActiveColorSchemeToFactory() {
    $defaults = getDefaultColorSchemes();
    $id = getActiveColorSchemeId();
    if(!isset($defaults[$id])) {
        return false;
    }
    $schemes = loadColorSchemes();
    $schemes[$id] = $defaults[$id];
    if(!saveColorSchemes($schemes)) {
        return false;
    }
    return applyColorScheme($id);
}

function ensureColorSchemesStored() {
    $raw = getConfigParamRawValue('colorSchemes');
    if($raw !== null && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if(is_array($decoded) && count($decoded) > 0) {
            // Merge missing keys from factory, persist if anything was added
            $merged = loadColorSchemes();
            $json = json_encode($merged, JSON_UNESCAPED_UNICODE);
            if($json !== false && $json !== $raw) {
                saveColorSchemes($merged);
            }
            return true;
        }
    }
    return saveColorSchemes(getDefaultColorSchemes());
}
?>
