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
    private static $tableReady = null;

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
     * PHP 8.1+ mysqli throws even with @ — catch missing-table / offline DB.
     * @param string $sql
     * @return mysqli_result|bool|null
     */
    private static function query($sql) {
        if(!isset($GLOBALS['conn']) || !$GLOBALS['conn']) {
            return null;
        }
        try {
            return mysqli_query($GLOBALS['conn'], $sql);
        }
        catch(Throwable $e) {
            return null;
        }
    }

    /** @return bool */
    public static function tableReady() {
        if(self::$tableReady !== null) {
            return self::$tableReady;
        }
        $sql = sprintf('SHOW TABLES LIKE "%s";', self::tableName());
        $dbr = self::query($sql);
        self::$tableReady = ($dbr && mysqli_fetch_row($dbr)) ? true : false;
        return self::$tableReady;
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
        self::$tableReady = null;
    }

    /** @return bool */
    public static function anyoneHasEditPermissions() {
        if(self::$anyoneCanEditCache !== null) {
            return self::$anyoneCanEditCache;
        }
        if(!self::tableReady()) {
            return self::$anyoneCanEditCache = false;
        }
        $sql = sprintf(
            'SELECT 1 FROM `%s` WHERE `perm_editPermissions` = 1 LIMIT 1;',
            self::tableName()
        );
        $dbr = self::query($sql);
        self::$anyoneCanEditCache = ($dbr && mysqli_fetch_row($dbr)) ? true : false;
        return self::$anyoneCanEditCache;
    }

    /**
     * True if any MIT right is assigned to anyone.
     * @return bool
     */
    public static function anyoneHasAnyPermission() {
        if(!self::tableReady()) {
            return false;
        }
        $parts = array();
        foreach(self::permissionKeys() as $key) {
            $parts[] = '`'.$key.'` = 1';
        }
        $sql = sprintf(
            'SELECT 1 FROM `%s` WHERE %s LIMIT 1;',
            self::tableName(),
            implode(' OR ', $parts)
        );
        $dbr = self::query($sql);
        return ($dbr && mysqli_fetch_row($dbr)) ? true : false;
    }

    /**
     * If the MIT rights table is empty of grants, give this user every MIT right.
     * Used on first successful login / session enforce.
     * @param int $userId
     * @return bool true if rights were granted
     */
    public static function grantAllIfNobodyHasRights($userId) {
        $userId = (int)$userId;
        if($userId < 1 || !self::tableReady()) {
            return false;
        }
        if(self::anyoneHasAnyPermission()) {
            return false;
        }
        $p = new self();
        $p->load_by_user($userId);
        foreach(self::permissionKeys() as $key) {
            $p->$key = 1;
        }
        if(!$p->save()) {
            return false;
        }
        self::clearCache($userId);
        if(class_exists('Log')) {
            $log = new Log();
            $log->info(sprintf(
                'MIT-Rechte Erstvergabe: User (%d) erhielt alle Rechte (noch niemand hatte Rechte).',
                $userId
            ));
        }
        return true;
    }

    /**
     * @deprecated Prefer grantAllIfNobodyHasRights on login; kept for UI hint compatibility.
     * @param int $userId
     * @return bool
     */
    public static function bootstrapEditAllowed($userId) {
        $userId = (int)$userId;
        if($userId < 1) {
            return false;
        }
        return !self::anyoneHasAnyPermission();
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
        if($userId < 1 || !isset($GLOBALS['conn']) || !self::tableReady()) {
            self::$cache[$userId] = $p;
            return $p;
        }
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `User` = %d LIMIT 1;',
            self::tableName(),
            $userId
        );
        $dbr = self::query($sql);
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

    public function load_by_id($id) {
        $id = (int)$id;
        if($id < 1 || !self::tableReady()) {
            return false;
        }
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `Index` = %d LIMIT 1;',
            self::tableName(),
            $id
        );
        $dbr = self::query($sql);
        $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
        if(!$row) {
            return false;
        }
        $this->fill($row);
        return true;
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
        if(!self::tableReady()) {
            return false;
        }
        $sql = sprintf(
            'INSERT INTO `%s` (`User`, `perm_showUsers`, `perm_editUsers`, `perm_editPermissions`) VALUES (%d, %d, %d, %d);',
            self::tableName(),
            (int)$this->User,
            (int)$this->perm_showUsers,
            (int)$this->perm_editUsers,
            (int)$this->perm_editPermissions
        );
        $ok = self::query($sql);
        if($ok) {
            $this->Index = (int)mysqli_insert_id($GLOBALS['conn']);
            if(class_exists('Log')) {
                $log = new Log();
                $log->DBinsert($this->getVars());
            }
        }
        return (bool)$ok;
    }

    private function update() {
        if(!self::tableReady()) {
            return false;
        }
        if(class_exists('Log')) {
            $log = new Log();
            $log->DBupdate($this->getChanges());
        }
        $sql = sprintf(
            'UPDATE `%s` SET `User` = %d, `perm_showUsers` = %d, `perm_editUsers` = %d, `perm_editPermissions` = %d WHERE `Index` = %d;',
            self::tableName(),
            (int)$this->User,
            (int)$this->perm_showUsers,
            (int)$this->perm_editUsers,
            (int)$this->perm_editPermissions,
            (int)$this->Index
        );
        return (bool)self::query($sql);
    }

    public function getVars() {
        $parts = array(mitLogUserHeader((int)$this->User));
        $parts[] = 'MIT-Rechte-ID: '.(int)$this->Index;
        foreach(self::permissionKeys() as $key) {
            logAppendTrue($parts, $key, $this->$key);
        }
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new self();
        $old->load_by_id((int)$this->Index);
        $header = mitLogUserHeader((int)$this->User).', MIT-Rechte-ID: '.(int)$this->Index;
        $parts = array();
        $labels = array(
            'perm_showUsers' => 'Nutzer lesen',
            'perm_editUsers' => 'Nutzer schreiben',
            'perm_editPermissions' => 'Rechte verwalten',
        );
        foreach(self::permissionKeys() as $key) {
            if(boolsDiffer($old->$key, $this->$key)) {
                $label = isset($labels[$key]) ? $labels[$key] : $key;
                $parts[] = $label.': '.bool2string($old->$key).' &rArr; <b>'.bool2string($this->$key).'</b>';
            }
        }
        if(!$parts) {
            return $header;
        }
        return $header.', '.implode(', ', $parts);
    }
}
