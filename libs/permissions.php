<?php
/**
 * MIT-owned permissions (mit_Permissions), keyed by Melde User.Index.
 * Login remains Melde perm_accessMitgliederverwaltung; these flags gate in-app actions.
 */
class Permissions
{
    private $_data = array(
        'Index' => null,
        'User' => null,
        'perm_showUsers' => 0,
        'perm_editUsers' => 0,
        'perm_editPermissions' => 0,
    );

    private static $cache = array();
    private static $anyoneCanEditCache = null;

    public function __get($key) {
        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    public function __set($key, $val) {
        if(!array_key_exists($key, $this->_data)) {
            return;
        }
        if($key === 'Index' || $key === 'User') {
            $this->_data[$key] = (int)$val;
            return;
        }
        $this->_data[$key] = (int)$val ? 1 : 0;
    }

    /** @return string[] */
    public static function permissionKeys() {
        return array(
            'perm_showUsers',
            'perm_editUsers',
            'perm_editPermissions',
        );
    }

    /** @return bool */
    public static function isMitKey($perm) {
        return in_array((string)$perm, self::permissionKeys(), true);
    }

    /**
     * @return array<string,array{short:string,label:string}>
     */
    public static function permissionLabels() {
        return array(
            'perm_showUsers' => array('short' => 'Lesen', 'label' => 'Nutzerdaten lesen'),
            'perm_editUsers' => array('short' => 'Schreiben', 'label' => 'Nutzerdaten schreiben'),
            'perm_editPermissions' => array('short' => 'Rechte', 'label' => 'Berechtigungen verwalten'),
        );
    }

    /**
     * @return array<int,array{id:string,title:string,color:string,keys:string[]}>
     */
    public static function permissionGroups() {
        return array(
            array(
                'id' => 'nutzer',
                'title' => 'Nutzer',
                'color' => '#42A5F5',
                'keys' => array('perm_showUsers', 'perm_editUsers', 'perm_editPermissions'),
            ),
        );
    }

    /**
     * @return array<int,array{key:string,group:string,short:string,label:string}>
     */
    public static function permissionCatalog() {
        $labels = self::permissionLabels();
        $out = array();
        foreach(self::permissionGroups() as $group) {
            foreach($group['keys'] as $key) {
                $meta = isset($labels[$key]) ? $labels[$key] : array('short' => $key, 'label' => $key);
                $out[] = array(
                    'key' => $key,
                    'group' => $group['id'],
                    'short' => $meta['short'],
                    'label' => $meta['label'],
                );
            }
        }
        return $out;
    }

    /** @return string */
    public static function groupIdForPermission($perm) {
        foreach(self::permissionGroups() as $group) {
            if(in_array($perm, $group['keys'], true)) {
                return $group['id'];
            }
        }
        return 'nutzer';
    }

    public static function tableName() {
        return $GLOBALS['dbprefix'].'Permissions';
    }

    public static function clearCache($userId = null) {
        if($userId === null) {
            self::$cache = array();
        }
        else {
            unset(self::$cache[(int)$userId]);
        }
        self::$anyoneCanEditCache = null;
    }

    /** @return bool */
    public static function anyoneHasEditPermissions() {
        if(self::$anyoneCanEditCache !== null) {
            return self::$anyoneCanEditCache;
        }
        if(!isset($GLOBALS['conn'])) {
            return self::$anyoneCanEditCache = false;
        }
        $sql = sprintf(
            'SELECT 1 FROM `%s` WHERE `perm_editPermissions` = 1 LIMIT 1;',
            self::tableName()
        );
        $dbr = @mysqli_query($GLOBALS['conn'], $sql);
        self::$anyoneCanEditCache = ($dbr && mysqli_fetch_row($dbr)) ? true : false;
        return self::$anyoneCanEditCache;
    }

    /**
     * First-run bootstrap: Melde User.Admin may manage MIT rights until someone has perm_editPermissions.
     * @param int $userId
     * @return bool
     */
    public static function bootstrapEditAllowed($userId) {
        $userId = (int)$userId;
        if($userId < 1 || self::anyoneHasEditPermissions()) {
            return false;
        }
        $sql = sprintf(
            'SELECT `Admin` FROM `%sUser` WHERE `Index` = %d LIMIT 1;',
            identityPrefix(),
            $userId
        );
        $dbr = @mysqli_query($GLOBALS['conn'], $sql);
        $row = ($dbr) ? mysqli_fetch_assoc($dbr) : null;
        return $row && !empty($row['Admin']);
    }

    /**
     * @param int $userId
     * @return Permissions
     */
    public static function loadByUser($userId) {
        $userId = (int)$userId;
        if(isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }
        $p = new self();
        $p->User = $userId;
        if($userId < 1 || !isset($GLOBALS['conn'])) {
            self::$cache[$userId] = $p;
            return $p;
        }
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `User` = %d LIMIT 1;',
            self::tableName(),
            $userId
        );
        $dbr = @mysqli_query($GLOBALS['conn'], $sql);
        if($dbr && ($row = mysqli_fetch_assoc($dbr))) {
            $p->fill($row);
        }
        self::$cache[$userId] = $p;
        return $p;
    }

    /**
     * Ensure a DB row exists (empty flags).
     * @param int $userId
     * @return Permissions
     */
    public function load_by_user($userId) {
        $userId = (int)$userId;
        $loaded = self::loadByUser($userId);
        foreach($loaded->_data as $k => $v) {
            $this->_data[$k] = $v;
        }
        if($this->Index || $userId < 1) {
            return $this;
        }
        $this->User = $userId;
        $this->insert();
        self::clearCache($userId);
        $fresh = self::loadByUser($userId);
        foreach($fresh->_data as $k => $v) {
            $this->_data[$k] = $v;
        }
        return $this;
    }

    /** @param array $row */
    private function fill($row) {
        foreach($row as $key => $val) {
            if(array_key_exists($key, $this->_data)) {
                $this->_data[$key] = ($key === 'Index' || $key === 'User') ? (int)$val : ((int)$val ? 1 : 0);
            }
        }
    }

    /** @return bool */
    public function hasAnyPermission() {
        foreach(self::permissionKeys() as $key) {
            if(!empty($this->_data[$key])) {
                return true;
            }
        }
        return false;
    }

    /**
     * editUsers implies showUsers.
     * @param string $perm
     * @return bool
     */
    public function getPermission($perm) {
        if(!self::isMitKey($perm)) {
            return false;
        }
        if($perm === 'perm_showUsers' && !empty($this->_data['perm_editUsers'])) {
            return true;
        }
        return !empty($this->_data[$perm]);
    }

    /** @return bool */
    public function isAdmin() {
        return $this->hasAnyPermission();
    }

    public function save() {
        if((int)$this->User < 1) {
            return false;
        }
        if((int)$this->Index > 0) {
            $ok = $this->update();
        }
        else {
            $ok = $this->insert();
        }
        self::clearCache((int)$this->User);
        return $ok;
    }

    private function insert() {
        $sql = sprintf(
            'INSERT INTO `%s` (`User`, `perm_showUsers`, `perm_editUsers`, `perm_editPermissions`) VALUES (%d, %d, %d, %d);',
            self::tableName(),
            (int)$this->User,
            (int)$this->perm_showUsers,
            (int)$this->perm_editUsers,
            (int)$this->perm_editPermissions
        );
        $ok = mysqli_query($GLOBALS['conn'], $sql);
        if($ok) {
            $this->Index = (int)mysqli_insert_id($GLOBALS['conn']);
            if(class_exists('Log')) {
                $log = new Log();
                $log->DBinsert($this->logSummary('angelegt'));
            }
        }
        return (bool)$ok;
    }

    private function update() {
        $sql = sprintf(
            'UPDATE `%s` SET `User` = %d, `perm_showUsers` = %d, `perm_editUsers` = %d, `perm_editPermissions` = %d WHERE `Index` = %d;',
            self::tableName(),
            (int)$this->User,
            (int)$this->perm_showUsers,
            (int)$this->perm_editUsers,
            (int)$this->perm_editPermissions,
            (int)$this->Index
        );
        $ok = mysqli_query($GLOBALS['conn'], $sql);
        if($ok && class_exists('Log')) {
            $log = new Log();
            $log->DBupdate($this->logSummary('geändert'));
        }
        return (bool)$ok;
    }

    /** @param string $verb */
    private function logSummary($verb) {
        $name = (string)$this->User;
        $u = new IdentityUser();
        if($u->load_by_id((int)$this->User)) {
            $name = $u->getName();
        }
        $parts = array();
        foreach(self::permissionKeys() as $key) {
            $parts[] = $key.'='.(int)$this->$key;
        }
        return sprintf(
            'MIT-Rechte %s für User (%d) <b>%s</b>: %s',
            $verb,
            (int)$this->User,
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            implode(', ', $parts)
        );
    }
}
