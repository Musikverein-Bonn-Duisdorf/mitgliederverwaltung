<?php
/**
 * Chunk loaders for infinite-scroll lists (Melde UI-SHELL, MIT log subset).
 * Returns array: html, nextCursor (string), hasMore (bool).
 */

function listChunkLimit($requested = 50) {
    $n = (int)$requested;
    if($n < 1) $n = 50;
    if($n > 100) $n = 100;
    return $n;
}

/**
 * Configured page size for log infinite scroll (UI-SHELL / Melde logListChunkSize).
 * Clamped to 1–500; default 100 when unset/invalid.
 */
function listChunkLogConfiguredLimit() {
    $n = 100;
    if(isset($GLOBALS['optionsDB']['logListChunkSize'])) {
        $n = (int)$GLOBALS['optionsDB']['logListChunkSize'];
    }
    if($n < 1) $n = 100;
    if($n > 500) $n = 500;
    return $n;
}

function listChunkLogLimit($requested = 0) {
    $n = (int)$requested;
    if($n < 1) {
        return listChunkLogConfiguredLimit();
    }
    if($n > 500) $n = 500;
    return $n;
}

/**
 * Escape a single search token for SQL LIKE.
 * @return string Empty if no usable query; otherwise already mysqli-escaped needle (no %).
 */
function listChunkLogSearchNeedle($q) {
    $q = trim((string)$q);
    if($q === '') {
        return '';
    }
    if(function_exists('mb_substr')) {
        $q = mb_substr($q, 0, 64, 'UTF-8');
    }
    else {
        $q = substr($q, 0, 64);
    }
    $q = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $q);
    return mysqli_real_escape_string($GLOBALS['conn'], $q);
}

/**
 * Split query into whitespace tokens (AND semantics).
 * @return list<array{raw:string,needle:string}>
 */
function listChunkLogSearchTokens($q) {
    $q = trim((string)$q);
    if($q === '') {
        return array();
    }
    if(function_exists('mb_substr')) {
        $q = mb_substr($q, 0, 120, 'UTF-8');
    }
    else {
        $q = substr($q, 0, 120);
    }
    $parts = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
    if(!is_array($parts)) {
        $parts = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
    }
    $out = array();
    foreach($parts as $part) {
        if(count($out) >= 8) {
            break;
        }
        $needle = listChunkLogSearchNeedle($part);
        if($needle === '') {
            continue;
        }
        $out[] = array('raw' => (string)$part, 'needle' => $needle);
    }
    return $out;
}

/**
 * Map free-text search to Log.Type ids when the query looks like a type chip label.
 * @return int[]
 */
function listChunkLogTypeIdsForQuery($q) {
    $q = trim((string)$q);
    if($q === '') {
        return array();
    }
    $u = function_exists('mb_strtoupper') ? mb_strtoupper($q, 'UTF-8') : strtoupper($q);
    if(function_exists('mb_strlen')) {
        if(mb_strlen($u, 'UTF-8') < 3) {
            return array();
        }
    }
    elseif(strlen($u) < 3) {
        return array();
    }
    $labels = array(
        0 => 'FATAL',
        1 => 'ERROR',
        2 => 'WARNING',
        3 => 'DB DELETE',
        4 => 'DB INSERT',
        5 => 'DB UPDATE',
        6 => 'EMAIL',
        7 => 'INFO',
    );
    $ids = array();
    foreach($labels as $id => $label) {
        if($u === $label || strpos($label, $u) !== false) {
            $ids[] = (int)$id;
        }
    }
    return array_values(array_unique($ids));
}

/**
 * One token must match Message, identity user name, SYSTEM, or type label.
 * @param array{raw:string,needle:string} $token
 */
function listChunkLogTokenSqlCondition(array $token) {
    $like = '\'%'.$token['needle'].'%\'';
    $conds = array(
        'l.`Message` LIKE '.$like.' ESCAPE \'\\\\\'',
        'u.`Vorname` LIKE '.$like.' ESCAPE \'\\\\\'',
        'u.`Nachname` LIKE '.$like.' ESCAPE \'\\\\\'',
        'CONCAT(u.`Vorname`, \' \', u.`Nachname`) LIKE '.$like.' ESCAPE \'\\\\\'',
    );
    $raw = (string)$token['raw'];
    if(stripos('SYSTEM', $raw) !== false) {
        $conds[] = 'l.`User` = 0';
    }
    $typeIds = listChunkLogTypeIdsForQuery($raw);
    if($typeIds) {
        $conds[] = 'l.`Type` IN ('.implode(',', array_map('intval', $typeIds)).')';
    }
    return '('.implode(' OR ', $conds).')';
}

/**
 * @param int $beforeIndex
 * @param int $limit
 * @param string $q Server-side search; whitespace tokens are AND
 * @return array{html:string,nextCursor:string,hasMore:bool}
 */
function listChunkLog($beforeIndex, $limit, $q = '') {
    $limit = listChunkLogLimit($limit);
    $beforeIndex = (int)$beforeIndex;
    $tokens = listChunkLogSearchTokens($q);
    $prefix = $GLOBALS['dbprefix'];
    $userPrefix = function_exists('identityPrefix') ? identityPrefix() : $prefix;

    if(!$tokens) {
        if($beforeIndex > 0) {
            $sql = sprintf(
                'SELECT `Index` FROM `%sLog` WHERE `Index` < %d ORDER BY `Index` DESC LIMIT %d;',
                $prefix,
                $beforeIndex,
                $limit + 1
            );
        }
        else {
            $sql = sprintf(
                'SELECT `Index` FROM `%sLog` ORDER BY `Index` DESC LIMIT %d;',
                $prefix,
                $limit + 1
            );
        }
    }
    else {
        $tokenSql = array();
        foreach($tokens as $token) {
            $tokenSql[] = listChunkLogTokenSqlCondition($token);
        }
        $whereSearch = implode(' AND ', $tokenSql);
        $whereIdx = $beforeIndex > 0 ? ('l.`Index` < '.(int)$beforeIndex.' AND ') : '';
        $sql = sprintf(
            'SELECT l.`Index` FROM `%sLog` l'
            .' LEFT JOIN `%sUser` u ON u.`Index` = l.`User`'
            .' WHERE %s%s'
            .' ORDER BY l.`Index` DESC LIMIT %d;',
            $prefix,
            $userPrefix,
            $whereIdx,
            $whereSearch,
            $limit + 1
        );
    }

    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $ids = array();
    if($dbr) {
        while($row = mysqli_fetch_array($dbr)) {
            $ids[] = (int)$row['Index'];
        }
    }
    $hasMore = count($ids) > $limit;
    if($hasMore) {
        $ids = array_slice($ids, 0, $limit);
    }
    $html = '';
    $next = $beforeIndex;
    ob_start();
    foreach($ids as $id) {
        $M = new Log;
        $M->load_by_id($id);
        $M->printTableLine();
        $next = $id;
    }
    $html = ob_get_clean();
    if($html === false) $html = '';
    return array(
        'html' => $html,
        'nextCursor' => (string)$next,
        'hasMore' => $hasMore,
    );
}

function listChunkRenderSentinelAttrs($type, $cursor, $hasMore, $filterFn = '') {
    $attrs = ' id="listSentinel" data-list-type="'.htmlspecialchars($type, ENT_QUOTES, 'UTF-8').'"'
        .' data-cursor="'.htmlspecialchars((string)$cursor, ENT_QUOTES, 'UTF-8').'"'
        .' data-has-more="'.($hasMore ? '1' : '0').'"';
    if($filterFn !== '') {
        $attrs .= ' data-filter-fn="'.htmlspecialchars($filterFn, ENT_QUOTES, 'UTF-8').'"';
    }
    return $attrs;
}

/**
 * Full status/sentinel bar for infinite scroll (loading + end-of-list).
 * @param string $extraHtmlAttrs e.g. ' data-extra="user=1"'
 */
function listChunkRenderSentinel($type, $cursor, $hasMore, $filterFn = '', $extraHtmlAttrs = '') {
    $attrs = listChunkRenderSentinelAttrs($type, $cursor, $hasMore, $filterFn).$extraHtmlAttrs;
    if($hasMore) {
        return '<div'.$attrs.' style="clear:both;height:1px;padding:0;margin:0;"></div>';
    }
    return '<div'.$attrs.' class="w3-panel w3-padding w3-center w3-margin-top w3-light-grey" style="clear:both;">Keine weiteren Einträge</div>';
}
?>
