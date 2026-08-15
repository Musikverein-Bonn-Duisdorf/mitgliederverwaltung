<?php
/**
 * Melde-compatible UI shell helpers (MIT UI port).
 * No Melde PHP includes — copied/adapted patterns only.
 */

function isHexColor($value) {
    if(!is_string($value)) return false;
    return (bool)preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($value));
}

function normalizeHexColor($value) {
    $value = strtoupper(trim((string)$value));
    if(!isHexColor($value)) return '';
    if(strlen($value) === 4) {
        return '#'.$value[1].$value[1].$value[2].$value[2].$value[3].$value[3];
    }
    return $value;
}

/** Map legacy w3 / highway / mvd color classes to hex for &lt;input type="color"&gt;. */
function w3ColorToHex($class) {
    $map = array(
        'w3-red' => '#F44336',
        'w3-pink' => '#E91E63',
        'w3-purple' => '#9C27B0',
        'w3-deep-purple' => '#673AB7',
        'w3-indigo' => '#3F51B5',
        'w3-blue' => '#2196F3',
        'w3-light-blue' => '#03A9F4',
        'w3-aqua' => '#00BCD4',
        'w3-cyan' => '#00BCD4',
        'w3-teal' => '#009688',
        'w3-green' => '#4CAF50',
        'w3-light-green' => '#8BC34A',
        'w3-lime' => '#CDDC39',
        'w3-sand' => '#FDF5E6',
        'w3-khaki' => '#F0E68C',
        'w3-yellow' => '#FFEB3B',
        'w3-amber' => '#FFC107',
        'w3-orange' => '#FF9800',
        'w3-deep-orange' => '#FF5722',
        'w3-blue-gray' => '#607D8B',
        'w3-brown' => '#795548',
        'w3-light-gray' => '#F1F1F1',
        'w3-gray' => '#9E9E9E',
        'w3-dark-gray' => '#616161',
        'w3-pale-red' => '#FFDDDD',
        'w3-pale-green' => '#DDFFDD',
        'w3-pale-yellow' => '#FFFFCC',
        'w3-pale-blue' => '#DDFFFF',
        'w3-highway-brown' => '#633517',
        'w3-highway-red' => '#A6001A',
        'w3-highway-orange' => '#E06000',
        'w3-highway-schoolbus' => '#EE9600',
        'w3-highway-yellow' => '#FFAB00',
        'w3-highway-green' => '#004D33',
        'w3-highway-blue' => '#00477E',
        'w3-mvd-white' => '#FDFFFC',
        'w3-mvd-black' => '#040006',
        'w3-mvd-blue' => '#345A95',
        'w3-mvd-gray' => '#969696',
        'w3-mvd-darkgray' => '#454545',
        'w3-mvd-egg' => '#FDF9E7',
        'w3-mvd-yellow' => '#FFC300',
        'w3-mvd-lightblue' => '#7F9DC1',
    );
    $class = trim((string)$class);
    return isset($map[$class]) ? $map[$class] : '#808080';
}

function colorPickerValue($raw) {
    $raw = trim((string)$raw);
    if($raw === '') return '#808080';
    if(isHexColor($raw)) return normalizeHexColor($raw);
    return w3ColorToHex($raw);
}

function hexContrastText($hex) {
    $hex = normalizeHexColor($hex);
    if($hex === '') return '#000000';
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));
    $luma = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    return ($luma > 0.55) ? '#000000' : '#FFFFFF';
}

function colorToCssClass($value) {
    $value = trim((string)$value);
    if($value === '') return '';
    if(isHexColor($value)) {
        $hex = normalizeHexColor($value);
        $class = 'cfg-hex-'.strtolower(substr($hex, 1));
        if(!isset($GLOBALS['cfgColorCssRules'])) {
            $GLOBALS['cfgColorCssRules'] = array();
        }
        $GLOBALS['cfgColorCssRules'][$class] = array(
            'bg' => $hex,
            'fg' => hexContrastText($hex),
        );
        return $class;
    }
    return $value;
}

function renderConfigColorCss($wrapStyleTag = true) {
    $css = '';
    $pageBg = '#FDFFFC';
    $bgClass = isset($GLOBALS['optionsDB']['colorBackground']) ? (string)$GLOBALS['optionsDB']['colorBackground'] : '';
    if($bgClass !== ''
        && !empty($GLOBALS['cfgColorCssRules'][$bgClass]['bg'])
        && isHexColor($GLOBALS['cfgColorCssRules'][$bgClass]['bg'])) {
        $pageBg = normalizeHexColor($GLOBALS['cfgColorCssRules'][$bgClass]['bg']);
    }
    $css .= ':root{--app-page-bg:'.$pageBg.';}';
    if(!empty($GLOBALS['cfgColorCssRules']) && is_array($GLOBALS['cfgColorCssRules'])) {
        foreach($GLOBALS['cfgColorCssRules'] as $class => $colors) {
            $css .= '.'.preg_replace('/[^a-z0-9\-]/i', '', $class)
                .'{color:'.$colors['fg'].' !important;background-color:'.$colors['bg'].' !important;}';
        }
    }
    if($css === '') return '';
    return $wrapStyleTag ? '<style type="text/css">'.$css.'</style>' : $css;
}

/**
 * Melde-parity group chrome colors for MIT permission groups.
 */
function renderPermissionGroupColorCss($wrapStyleTag = true) {
    $systemAccent = '#345A95';
    $systemStrong = '#7F9DC1';
    $css = '';
    $css .= '.admin-list-shell:has(.admin-list-hero--system){--page-title-accent:'.$systemAccent.';}';
    $css .= '.profile-shell .profile-hero.admin-list-hero--system,.w3-container.admin-list-hero--system{background:'.$systemStrong.';border-left-color:'.$systemAccent.';--page-title-accent:'.$systemAccent.';}';
    $css .= '.app-nav .admin-nav-perm--system{background:#E8EEF5 !important;border-color:'.$systemAccent.';color:#222 !important;}';
    if(class_exists('Permissions')) {
        foreach(Permissions::permissionGroups() as $group) {
            $id = preg_replace('/[^a-z0-9_-]/i', '', (string)$group['id']);
            if($id === '') {
                continue;
            }
            $hex = isset($group['color']) ? normalizeHexColor($group['color']) : '#42A5F5';
            // Soft fill for matrix headers/cells (approx. 78% toward white).
            $r = hexdec(substr($hex, 1, 2));
            $g = hexdec(substr($hex, 3, 2));
            $b = hexdec(substr($hex, 5, 2));
            $soft = sprintf('#%02X%02X%02X', (int)($r + (255 - $r) * 0.78), (int)($g + (255 - $g) * 0.78), (int)($b + (255 - $b) * 0.78));
            $css .= '.admin-list-shell:has(.admin-list-hero--'.$id.'){--page-title-accent:'.$hex.';}';
            $css .= '.profile-shell .profile-hero.admin-list-hero--'.$id.',.w3-container.admin-list-hero--'.$id.'{background:'.$soft.';border-left-color:'.$hex.';--page-title-accent:'.$hex.';}';
            $css .= '.perm-matrix th.perm-group--'.$id.',.perm-matrix td.perm-group--'.$id.'.perm-on{background:'.$soft.';}';
            $css .= '.perm-matrix th.perm-group--'.$id.'{border-bottom-color:'.$hex.';}';
            $css .= '.app-nav .admin-nav-perm--'.$id.',.app-nav a[class*="nav-group--'.$id.'"]{--nav-group-accent:'.$hex.';}';
        }
    }
    return $wrapStyleTag ? '<style type="text/css" id="perm-group-colors">'.$css.'</style>' : $css;
}

function getColorConfigParameters() {
    static $params = null;
    if($params !== null) return $params;
    $params = array();
    if(function_exists('getConfigDefaults')) {
        foreach(getConfigDefaults() as $item) {
            if(isset($item['Type']) && $item['Type'] === 'color' && isset($item['Parameter'])) {
                $storage = (string)$item['Parameter'];
                $params[$storage] = true;
                if(function_exists('archivConfigAliases')) {
                    foreach(archivConfigAliases() as $logical => $archivKey) {
                        if($archivKey === $storage) {
                            $params[$logical] = true;
                        }
                    }
                }
            }
        }
    }
    return $params;
}

/** Cache-busting URL for static assets (Melde UI-SHELL: ?v=&h=). */
function assetUrl($rel) {
    $rel = ltrim(str_replace('\\', '/', (string)$rel), '/');
    $ver = isset($GLOBALS['version']['String']) ? (string)$GLOBALS['version']['String'] : '0';
    $hash = isset($GLOBALS['version']['Hash']) ? (string)$GLOBALS['version']['Hash'] : '0';
    $mtime = @filemtime(dirname(__DIR__).'/'.$rel);
    if($mtime === false) {
        $mtime = 0;
    }
    return htmlspecialchars($rel.'?v='.rawurlencode($ver).'&h='.$hash.'-'.$mtime, ENT_QUOTES, 'UTF-8');
}

/**
 * Clickable entity chip for AJAX modals (UI-SHELL).
 * @param string $type user|membership|document|sepa
 * @param int $id
 * @param string $label
 * @param string $chipMod Optional visual modifier
 * @return string HTML
 */
function entityOpenHtml($type, $id, $label, $chipMod = '') {
    $type = strtolower(trim((string)$type));
    $id = (int)$id;
    $label = trim((string)$label);
    $h = function ($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    };
    if($label === '') {
        return '';
    }
    $chipMods = array(
        'user' => 'user',
        'membership' => 'member',
        'document' => 'mailGroup',
        'sepa' => 'insured',
    );
    if($id < 1 || !isset($chipMods[$type])) {
        return $h($label);
    }
    $mod = trim((string)$chipMod);
    if($mod === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $mod)) {
        $mod = $chipMods[$type];
    }
    return '<span class="mail-recipient-chip mail-recipient-chip--'.$mod.' entity-open"'
        .' role="button" tabindex="0"'
        .' data-entity-type="'.$h($type).'"'
        .' data-entity-id="'.$id.'">'
        .$h($label)
        .'</span>';
}

/**
 * Enrich log message HTML with entity chips (display only). MIT: User links.
 * @param string $html
 * @return string
 */
function logMessageLinkEntities($html) {
    $html = (string)$html;
    if($html === '' || !function_exists('entityOpenHtml')) {
        return $html;
    }
    if(strpos($html, 'entity-open') !== false && strpos($html, 'data-entity-type') !== false) {
        return logMessageLinkUrls($html);
    }
    $chip = function ($type, $id, $labelRaw) {
        $label = trim(html_entity_decode(strip_tags((string)$labelRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $label = preg_replace('/\s+/u', ' ', $label);
        if($label === '') {
            $label = '#'.(int)$id;
        }
        return entityOpenHtml($type, (int)$id, $label);
    };
    $html = preg_replace_callback(
        '/\bUser:\s*\((\d+)\)\s*<b>(.*?)<\/b>/si',
        function ($m) use ($chip) {
            return 'User: '.$chip('user', $m[1], $m[2]);
        },
        $html
    );
    $html = preg_replace('/\bUser-ID:\s*\d+\s*,\s*(?=User:)/i', '', $html);
    $html = preg_replace_callback(
        '/\bUser-ID:\s*(\d+)\s*,?\s*<b>(.*?)<\/b>/si',
        function ($m) use ($chip) {
            return 'User: '.$chip('user', $m[1], $m[2]);
        },
        $html
    );
    return logMessageLinkUrls($html);
}

/**
 * Turn bare http(s) URLs in log message HTML into links (text nodes only).
 * @param string $html
 * @return string
 */
function logMessageLinkUrls($html) {
    $html = (string)$html;
    if($html === '') {
        return $html;
    }

    $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if($parts === false) {
        return $html;
    }

    $out = '';
    $inAnchor = 0;
    foreach($parts as $part) {
        if($part === '') {
            continue;
        }
        if(isset($part[0]) && $part[0] === '<') {
            if(preg_match('/^<\s*a\b/i', $part)) {
                $inAnchor++;
            } elseif(preg_match('/^<\s*\/\s*a\b/i', $part)) {
                $inAnchor = max(0, $inAnchor - 1);
            }
            $out .= $part;
            continue;
        }
        if($inAnchor > 0) {
            $out .= $part;
            continue;
        }
        $out .= preg_replace_callback(
            '#\bhttps?://[^\s<>"\']+#iu',
            function ($m) {
                $raw = $m[0];
                $trail = '';
                while($raw !== '' && preg_match('/[.,;:!?)\]]$/', $raw)) {
                    $trail = substr($raw, -1).$trail;
                    $raw = substr($raw, 0, -1);
                }
                $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if($decoded === '' || !preg_match('#^https?://#i', $decoded)) {
                    return $m[0];
                }
                $safe = htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8');
                return '<a class="log-msg-url" href="'.$safe.'" target="_blank" rel="noopener noreferrer">'.$safe.'</a>'.$trail;
            },
            $part
        );
    }

    return $out;
}

/** Queue modal HTML outside .app-main (overflow/z-index). */
function deferPageModalHtml($html) {
    $html = (string)$html;
    if($html === '') {
        return;
    }
    if(!isset($GLOBALS['mlDeferredPageModals'])) {
        $GLOBALS['mlDeferredPageModals'] = '';
    }
    $GLOBALS['mlDeferredPageModals'] .= $html;
}

function navGroupClass($groupId) {
    $gid = preg_replace('/[^a-z0-9_-]/i', '', (string)$groupId);
    if($gid === '') {
        $gid = 'system';
    }
    return 'admin-nav-perm admin-nav-perm--'.$gid;
}

function adminListSectionGroupId($kicker) {
    $map = array(
        'Mitgliederverwaltung' => 'system',
        'Mitglieder' => 'system',
        'SEPA' => 'system',
        'Dokumente' => 'system',
        'System' => 'system',
        'Hilfe' => 'system',
        'Konfiguration' => 'system',
        'Log' => 'system',
    );
    $k = trim((string)$kicker);
    return isset($map[$k]) ? $map[$k] : 'system';
}

function adminHeroClass($options = array()) {
    if(isset($options['groupId']) && (string)$options['groupId'] !== '') {
        $gid = (string)$options['groupId'];
    }
    elseif(isset($options['kicker'])) {
        $gid = adminListSectionGroupId((string)$options['kicker']);
    }
    else {
        $gid = 'system';
    }
    $gid = preg_replace('/[^a-z0-9_-]/i', '', (string)$gid);
    if($gid === '') {
        $gid = 'system';
    }
    $withProfile = !array_key_exists('withProfileHero', $options) || !empty($options['withProfileHero']);
    $base = $withProfile ? 'profile-hero admin-list-hero' : 'admin-list-hero';
    return $base.' admin-list-hero--'.$gid;
}

function adminListPageBegin($kicker, $title, $options = array()) {
    $actionsHtml = isset($options['actionsHtml']) ? (string)$options['actionsHtml'] : '';
    $shellClass = isset($options['shellClass']) ? trim((string)$options['shellClass']) : '';
    $shellCls = 'profile-shell admin-list-shell'.($shellClass !== '' ? ' '.$shellClass : '');
    $heroOpts = array('kicker' => $kicker);
    if(isset($options['groupId'])) {
        $heroOpts['groupId'] = $options['groupId'];
    }
    $heroCls = adminHeroClass($heroOpts);
    echo '<div class="profile-page">'."\n";
    echo '  <div class="'.htmlspecialchars($shellCls, ENT_QUOTES, 'UTF-8').'">'."\n";
    echo '    <div class="app-page-chrome">'."\n";
    echo '    <header class="'.htmlspecialchars($heroCls, ENT_QUOTES, 'UTF-8').'">'."\n";
    echo '      <div class="profile-hero-text">'."\n";
    echo '        <p class="profile-kicker">'.htmlspecialchars((string)$kicker, ENT_QUOTES, 'UTF-8').'</p>'."\n";
    echo '        <h2 class="profile-title">'.htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8').'</h2>'."\n";
    echo '      </div>'."\n";
    if($actionsHtml !== '') {
        echo '      <div class="profile-hero-actions">'.$actionsHtml.'</div>'."\n";
    }
    echo '    </header>'."\n";
    $GLOBALS['mlAdminListChrome'] = 'open';
    $GLOBALS['mlAdminListBody'] = null;
    ob_start();
    $GLOBALS['mlAdminListCapturing'] = true;
}

function adminListFlushChromeCapture() {
    if(empty($GLOBALS['mlAdminListCapturing'])) {
        return '';
    }
    $chunk = ob_get_clean();
    $GLOBALS['mlAdminListCapturing'] = false;
    return ($chunk === false) ? '' : $chunk;
}

function adminListChromeClose($captureToBody = false) {
    if(empty($GLOBALS['mlAdminListChrome']) || $GLOBALS['mlAdminListChrome'] !== 'open') {
        return;
    }
    $chunk = adminListFlushChromeCapture();
    if(!$captureToBody && $chunk !== '') {
        echo $chunk;
        $chunk = '';
    }
    echo '    </div><!-- .app-page-chrome -->'."\n";
    echo '    <div class="admin-list-body">'."\n";
    $GLOBALS['mlAdminListChrome'] = 'closed';
    $GLOBALS['mlAdminListBody'] = 'open';
    if($captureToBody && $chunk !== '') {
        echo $chunk;
    }
}

function adminListPageEnd() {
    if(!empty($GLOBALS['mlAdminListChrome']) && $GLOBALS['mlAdminListChrome'] === 'open') {
        adminListChromeClose(true);
    }
    if(!empty($GLOBALS['mlAdminListBody']) && $GLOBALS['mlAdminListBody'] === 'open') {
        echo '    </div><!-- .admin-list-body -->'."\n";
        $GLOBALS['mlAdminListBody'] = 'closed';
    }
    echo '  </div>'."\n";
    echo '</div>'."\n";
}

function adminListSearchField($placeholder, $options = array()) {
    $id = isset($options['id']) ? (string)$options['id'] : 'filterString';
    $onkeyup = isset($options['onkeyup']) ? (string)$options['onkeyup'] : '';
    $extra = isset($options['extraHtml']) ? (string)$options['extraHtml'] : '';
    $inputBg = isset($GLOBALS['optionsDB']['colorInputBackground'])
        ? (string)$GLOBALS['optionsDB']['colorInputBackground'] : '';
    $aria = isset($options['ariaLabel']) && (string)$options['ariaLabel'] !== ''
        ? (string)$options['ariaLabel']
        : (string)$placeholder;
    $pre = adminListFlushChromeCapture();
    if($pre !== '') {
        echo $pre;
    }
    echo '    <div class="admin-list-toolbar">'."\n";
    echo '      <div class="profile-field admin-list-search">'."\n";
    echo '        <input type="search" id="'.htmlspecialchars($id, ENT_QUOTES, 'UTF-8').'"'
        .' class="w3-input w3-border profile-control '.htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8').'"'
        .' placeholder="'.htmlspecialchars((string)$placeholder, ENT_QUOTES, 'UTF-8').'"'
        .' aria-label="'.htmlspecialchars($aria, ENT_QUOTES, 'UTF-8').'"'
        .' autocomplete="off"';
    if($onkeyup !== '') {
        echo ' onkeyup="'.htmlspecialchars($onkeyup, ENT_QUOTES, 'UTF-8').'"';
    }
    echo '>'."\n";
    echo '      </div>'."\n";
    if($extra !== '') {
        echo '      <div class="admin-list-toolbar-extra">'.$extra.'</div>'."\n";
    }
    echo '    </div>'."\n";
    adminListChromeClose(false);
}

function adminNavGroupActiveClass($pages) {
    if(empty($_SESSION['adminpage'])) {
        return '';
    }
    $current = isset($_SESSION['page']) ? (string)$_SESSION['page'] : '';
    if($current === '') {
        return '';
    }
    foreach((array)$pages as $p) {
        if((string)$p === $current) {
            return ' admin-nav-open admin-nav-current-group';
        }
    }
    return '';
}
?>
