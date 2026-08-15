<?php
/**
 * Read-only Melde Permissions for Mitgliederverwaltung access + admin gates (MELD-171).
 * Personal Permissions row OR-merged with Group PermissionSpec when the user
 * matches a simplified MemberSpec (explicit users[] / role chips).
 * Does not create Melde rows.
 */
class IdentityPermissions
{
    private $flags = array();

    private static $cache = array();

    /**
     * Melde permission keys relevant to MIT ops (subset of Melde catalog).
     * @return string[]
     */
    public static function mitPermissionKeys() {
        // Melde-side keys still used in MIT (login + legacy ops). In-app user rights live in mit_Permissions.
        return array(
            'perm_accessMitgliederverwaltung',
            'perm_showLog',
            'perm_editConfig',
        );
    }

    /**
     * @param int $userId
     * @return IdentityPermissions
     */
    public static function loadForUser($userId) {
        $userId = (int)$userId;
        if(isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }
        $p = new self();
        if($userId < 1) {
            self::$cache[$userId] = $p;
            return $p;
        }
        $table = identityPrefix().'Permissions';
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `User` = %d LIMIT 1;',
            $table,
            $userId
        );
        $dbr = @mysqli_query($GLOBALS['conn'], $sql);
        if($dbr && ($row = mysqli_fetch_assoc($dbr))) {
            foreach($row as $key => $val) {
                if(strpos($key, 'perm_') === 0) {
                    $p->flags[$key] = (int)$val;
                }
            }
        }
        foreach(self::groupPermissionKeysForUser($userId) as $key) {
            $p->flags[$key] = 1;
        }
        self::$cache[$userId] = $p;
        return $p;
    }

    /**
     * OR-merge PermissionSpec from Melde groups when MemberSpec includes the user
     * (explicit users[] or role chips users/members/musicians/nonmembers).
     * Full AudienceSpec/register resolution is not ported — only common JSON shapes.
     *
     * @param int $userId
     * @return string[]
     */
    public static function groupPermissionKeysForUser($userId) {
        $userId = (int)$userId;
        $out = array();
        if($userId < 1 || !isset($GLOBALS['conn'])) {
            return $out;
        }
        $table = identityPrefix().'Group';
        $sql = sprintf('SELECT `MemberSpec`, `PermissionSpec` FROM `%s`;', $table);
        $dbr = @mysqli_query($GLOBALS['conn'], $sql);
        if(!$dbr) {
            return $out;
        }
        $mitglied = null;
        $hasInstrument = null;
        while($row = mysqli_fetch_assoc($dbr)) {
            $perms = self::decodePermissionSpec(isset($row['PermissionSpec']) ? $row['PermissionSpec'] : null);
            if(!count($perms)) {
                continue;
            }
            if(!self::memberSpecIncludesUser(
                isset($row['MemberSpec']) ? $row['MemberSpec'] : null,
                $userId,
                $mitglied,
                $hasInstrument
            )) {
                continue;
            }
            foreach($perms as $key) {
                $out[$key] = true;
            }
        }
        return array_keys($out);
    }

    /**
     * @param string|null $raw
     * @return string[]
     */
    private static function decodePermissionSpec($raw) {
        if(!is_string($raw) || $raw === '') {
            return array();
        }
        $decoded = json_decode($raw, true);
        if(!is_array($decoded)) {
            return array();
        }
        $out = array();
        foreach($decoded as $key) {
            if(is_string($key) && strpos($key, 'perm_') === 0) {
                $out[] = $key;
            }
        }
        return $out;
    }

    /**
     * @param string|null $raw
     * @param int $userId
     * @param bool|null &$mitglied
     * @param bool|null &$hasInstrument
     * @return bool
     */
    private static function memberSpecIncludesUser($raw, $userId, &$mitglied, &$hasInstrument) {
        if(!is_string($raw) || $raw === '') {
            return false;
        }
        $decoded = json_decode($raw, true);
        if(!is_array($decoded)) {
            return false;
        }
        if(isset($decoded['users']) && is_array($decoded['users'])) {
            foreach($decoded['users'] as $id) {
                if((int)$id === $userId) {
                    return true;
                }
            }
        }
        $roles = array();
        if(isset($decoded['groups']) && is_array($decoded['groups'])) {
            foreach($decoded['groups'] as $g) {
                if(is_string($g)) {
                    $roles[] = $g;
                }
            }
        }
        if(in_array('users', $roles, true)) {
            return true;
        }
        if(in_array('members', $roles, true) || in_array('nonmembers', $roles, true) || in_array('musicians', $roles, true)) {
            if($mitglied === null || $hasInstrument === null) {
                self::loadUserMembershipFlags($userId, $mitglied, $hasInstrument);
            }
            if(in_array('members', $roles, true) && $mitglied) {
                return true;
            }
            if(in_array('nonmembers', $roles, true) && !$mitglied) {
                return true;
            }
            if(in_array('musicians', $roles, true) && $hasInstrument) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param int $userId
     * @param bool|null &$mitglied
     * @param bool|null &$hasInstrument
     */
    private static function loadUserMembershipFlags($userId, &$mitglied, &$hasInstrument) {
        $mitglied = false;
        $hasInstrument = false;
        $sql = sprintf(
            'SELECT `Mitglied`, `Instrument` FROM `%sUser` WHERE `Index` = %d LIMIT 1;',
            identityPrefix(),
            (int)$userId
        );
        $dbr = @mysqli_query($GLOBALS['conn'], $sql);
        if($dbr && ($row = mysqli_fetch_assoc($dbr))) {
            $mitglied = !empty($row['Mitglied']);
            $hasInstrument = isset($row['Instrument']) && (int)$row['Instrument'] > 0;
        }
    }

    /**
     * Melde-style admin: any ops flag (not platform access alone).
     * @return bool
     */
    public function isAdmin() {
        foreach(self::mitPermissionKeys() as $key) {
            if($key === 'perm_accessMitgliederverwaltung') {
                continue;
            }
            if(!empty($this->flags[$key])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $perm
     * @return bool
     */
    public function getPermission($perm) {
        return !empty($this->flags[$perm]);
    }

    /** @internal tests */
    public static function clearCacheForTests() {
        self::$cache = array();
    }
}
