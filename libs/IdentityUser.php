<?php
/**
 * Canonical identity users (meldeliste_User by default).
 * Login/Passhash remain Melde-owned; Stammdaten (Name/Email) may be updated from MIT.
 */
class IdentityUser
{
    private $_data = array(
        'Index' => null,
        'Nachname' => null,
        'Vorname' => null,
        'Email' => null,
        'Email2' => null,
        'login' => null,
        'Passhash' => null,
        'Admin' => null,
        'Deleted' => null,
        'singleUsePW' => null,
    );

    public function __get($key) {
        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    public function __set($key, $val) {
        if(!array_key_exists($key, $this->_data)) {
            return;
        }
        if(in_array($key, array('Index', 'Admin', 'Deleted', 'singleUsePW'), true)) {
            $this->_data[$key] = (int)$val;
            return;
        }
        if(in_array($key, array('Nachname', 'Vorname', 'Email', 'Email2', 'login', 'Passhash'), true)) {
            $this->_data[$key] = ($val === null) ? null : trim((string)$val);
            return;
        }
        $this->_data[$key] = $val;
    }

    public static function identityPrefix() {
        return isset($GLOBALS['identityPrefix']) ? $GLOBALS['identityPrefix'] : 'meldeliste_';
    }

    public static function tableName($suffix = 'User') {
        return self::identityPrefix().$suffix;
    }

    public function getName() {
        return trim((string)$this->Vorname.' '.(string)$this->Nachname);
    }

    /**
     * Cached column names of the identity User table.
     * @return array<string,true>
     */
    public static function userColumns() {
        static $cols = null;
        if(is_array($cols)) {
            return $cols;
        }
        $cols = array();
        $sql = sprintf('SHOW COLUMNS FROM `%s`;', self::tableName('User'));
        try {
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
        }
        catch(Throwable $e) {
            return $cols;
        }
        while($dbr && ($row = mysqli_fetch_assoc($dbr))) {
            if(!empty($row['Field'])) {
                $cols[(string)$row['Field']] = true;
            }
        }
        return $cols;
    }

    /**
     * Create a new Melde identity user from MIT (Name/Email; orchestra defaults inactive).
     * Optional login (must be unique if set). Does not set password — Melde-owned.
     * @param array{Vorname?:string,Nachname?:string,Email?:string,Email2?:string,login?:string} $data
     * @return IdentityUser|null
     */
    public static function createPerson(array $data) {
        $u = new self();
        $u->Vorname = isset($data['Vorname']) ? trim((string)$data['Vorname']) : '';
        $u->Nachname = isset($data['Nachname']) ? trim((string)$data['Nachname']) : '';
        $u->Email = isset($data['Email']) ? trim((string)$data['Email']) : '';
        $u->Email2 = isset($data['Email2']) ? trim((string)$data['Email2']) : '';
        $u->login = isset($data['login']) ? trim((string)$data['login']) : '';
        if($u->Vorname === '' || $u->Nachname === '') {
            return null;
        }
        if($u->login !== '') {
            $clash = new self();
            if($clash->load_by_login($u->login)) {
                return null;
            }
        }
        if(!$u->insertPerson()) {
            return null;
        }
        if(class_exists('Log')) {
            $log = new Log();
            $log->DBinsert($u->getCreateVars());
        }
        return $u;
    }

    public function getCreateVars() {
        $parts = array(mitLogUserHeader((int)$this->Index));
        $parts[] = logPart('Vorname', logEsc($this->Vorname));
        $parts[] = logPart('Nachname', logEsc($this->Nachname));
        logAppendFilled($parts, 'Email', $this->Email);
        logAppendFilled($parts, 'Email2', $this->Email2);
        logAppendFilled($parts, 'Login', $this->login);
        $parts[] = 'angelegt (MIT)';
        return implode(', ', $parts);
    }

    /**
     * Insert into identity User with Melde-compatible defaults (Active=0, Instrument=0).
     */
    protected function insertPerson() {
        $cols = self::userColumns();
        if(!$cols) {
            return false;
        }
        $values = array(
            'Nachname' => (string)$this->Nachname,
            'Vorname' => (string)$this->Vorname,
            'Email' => (string)$this->Email,
            'Email2' => (string)$this->Email2,
            'login' => (string)$this->login,
            'Passhash' => '',
            'Active' => 0,
            'Instrument' => 0,
            'Admin' => 0,
            'Deleted' => 0,
            'RegisterLead' => 0,
            'getMail' => 0,
            'notifyInbox' => 1,
            'notifyAppMail' => 1,
            'notifyAppTerminNew' => 1,
            'notifyAppTerminChange' => 1,
            'notifyAppTerminSoon' => 0,
            'singleUsePW' => 0,
            'activeLink' => bin2hex(random_bytes(16)),
        );
        $fieldSql = array();
        $valueSql = array();
        foreach($values as $field => $val) {
            if(!isset($cols[$field])) {
                continue;
            }
            $fieldSql[] = '`'.$field.'`';
            if(is_int($val)) {
                $valueSql[] = (string)$val;
            }
            else {
                $valueSql[] = '"'.mysqli_real_escape_string($GLOBALS['conn'], (string)$val).'"';
            }
        }
        if(!in_array('`Nachname`', $fieldSql, true) || !in_array('`Vorname`', $fieldSql, true)) {
            return false;
        }
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s);',
            self::tableName('User'),
            implode(', ', $fieldSql),
            implode(', ', $valueSql)
        );
        try {
            $ok = mysqli_query($GLOBALS['conn'], $sql);
        }
        catch(Throwable $e) {
            return false;
        }
        sqlerror();
        if(!$ok) {
            return false;
        }
        $this->_data['Index'] = (int)mysqli_insert_id($GLOBALS['conn']);
        $this->Passhash = '';
        $this->Admin = 0;
        $this->Deleted = 0;
        return (int)$this->Index > 0;
    }

    public function load_by_id($id) {
        $id = (int)$id;
        if($id < 1) {
            return false;
        }
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `Index` = %d LIMIT 1;',
            self::tableName('User'),
            $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
        if(!$row) {
            return false;
        }
        $this->fill_from_row($row);
        return true;
    }

    public function load_by_login($login) {
        $login = trim((string)$login);
        if($login === '') {
            return false;
        }
        $sql = sprintf(
            "SELECT * FROM `%s` WHERE `login` = '%s' AND `Deleted` != 1 LIMIT 1;",
            self::tableName('User'),
            mysqli_real_escape_string($GLOBALS['conn'], $login)
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
        if(!$row) {
            return false;
        }
        $this->fill_from_row($row);
        return true;
    }

    /**
     * Persist Stammdaten on the shared User row (Name + Email).
     * Does not change login, password, Admin or Deleted.
     */
    public function saveStammdaten() {
        if((int)$this->Index < 1) {
            return false;
        }
        $vorname = trim((string)$this->Vorname);
        $nachname = trim((string)$this->Nachname);
        if($vorname === '' || $nachname === '') {
            return false;
        }
        $this->Vorname = $vorname;
        $this->Nachname = $nachname;
        $this->Email = trim((string)$this->Email);
        $this->Email2 = trim((string)$this->Email2);

        if(class_exists('Log')) {
            $log = new Log();
            $log->DBupdate($this->getStammdatenChanges());
        }

        $sql = sprintf(
            'UPDATE `%s` SET `Vorname` = "%s", `Nachname` = "%s", `Email` = "%s", `Email2` = "%s" WHERE `Index` = %d AND `Deleted` != 1 LIMIT 1;',
            self::tableName('User'),
            mysqli_real_escape_string($GLOBALS['conn'], $vorname),
            mysqli_real_escape_string($GLOBALS['conn'], $nachname),
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->Email),
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->Email2),
            (int)$this->Index
        );
        try {
            $ok = mysqli_query($GLOBALS['conn'], $sql);
        }
        catch(Throwable $e) {
            return false;
        }
        sqlerror();
        return (bool)$ok;
    }

    /** Melde-parity change message for Name/Email updates from MIT. */
    public function getStammdatenChanges() {
        $old = new self();
        $old->load_by_id((int)$this->Index);
        $header = mitLogUserHeader((int)$this->Index);
        $parts = array();
        if((string)$this->Vorname !== (string)$old->Vorname) {
            $parts[] = 'Vorname: '.logEsc($old->Vorname).' &rArr; <b>'.logEsc($this->Vorname).'</b>';
        }
        if((string)$this->Nachname !== (string)$old->Nachname) {
            $parts[] = 'Nachname: '.logEsc($old->Nachname).' &rArr; <b>'.logEsc($this->Nachname).'</b>';
        }
        if((string)$this->Email !== (string)$old->Email) {
            $parts[] = 'Email: '.logEsc($old->Email).' &rArr; <b>'.logEsc($this->Email).'</b>';
        }
        if((string)$this->Email2 !== (string)$old->Email2) {
            $parts[] = 'Email2: '.logEsc($old->Email2).' &rArr; <b>'.logEsc($this->Email2).'</b>';
        }
        if(!$parts) {
            return $header;
        }
        return $header.', '.implode(', ', $parts);
    }

    /**
     * @return IdentityUser[]
     */
    public static function listActive($limit = 500) {
        $limit = max(1, (int)$limit);
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `Deleted` != 1 ORDER BY `Nachname`, `Vorname` LIMIT %d;',
            self::tableName('User'),
            $limit
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $out = array();
        if(!$dbr) {
            return $out;
        }
        while($row = mysqli_fetch_assoc($dbr)) {
            $u = new self();
            $u->fill_from_row($row);
            $out[] = $u;
        }
        return $out;
    }

    /**
     * Active Melde users with optional membership filter (derived from periods today).
     * @param string $filter all|aktiv|foerdernd|none|member
     * @return array<int,array{user:IdentityUser,membership:?Membership,type:?string,isMember:bool}>
     */
    public static function listHub($filter = 'all', $limit = 2000) {
        $limit = max(1, (int)$limit);
        $filter = (string)$filter;
        $memTable = Membership::tableName();
        $userTable = self::tableName('User');
        $today = date('Y-m-d');
        $sql = sprintf(
            'SELECT u.*, m.`Index` AS `mIndex`
             FROM `%s` u
             LEFT JOIN (
               SELECT m1.`Index`, m1.`User` FROM `%s` m1
               INNER JOIN (
                 SELECT `User`, MAX(`Index`) AS `mx` FROM `%s` GROUP BY `User`
               ) latest ON latest.`User` = m1.`User` AND latest.`mx` = m1.`Index`
             ) m ON m.`User` = u.`Index`
             WHERE u.`Deleted` != 1',
            $userTable,
            $memTable,
            $memTable
        );
        if($filter === 'aktiv' || $filter === 'foerdernd') {
            $sql .= ' AND '.MembershipPeriod::sqlUserIsMemberOn('u.`Index`', $today);
            $sql .= sprintf(
                ' AND EXISTS (SELECT 1 FROM `%s` t WHERE t.`Membership` = m.`Index` AND t.`Type` = "%s"
                  AND t.`DateFrom` <= "%s" AND (t.`DateTo` IS NULL OR t.`DateTo` >= "%s"))',
                MembershipTypePeriod::tableName(),
                mysqli_real_escape_string($GLOBALS['conn'], $filter),
                mysqli_real_escape_string($GLOBALS['conn'], $today),
                mysqli_real_escape_string($GLOBALS['conn'], $today)
            );
        }
        elseif($filter === 'member') {
            $sql .= ' AND '.MembershipPeriod::sqlUserIsMemberOn('u.`Index`', $today);
        }
        elseif($filter === 'none') {
            $sql .= ' AND NOT ('.MembershipPeriod::sqlUserIsMemberOn('u.`Index`', $today).')';
        }
        $sql .= sprintf(' ORDER BY u.`Nachname`, u.`Vorname` LIMIT %d;', $limit);

        $out = array();
        try {
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
        }
        catch(Throwable $e) {
            return $out;
        }
        sqlerror();
        if(!$dbr) {
            return $out;
        }
        while($row = mysqli_fetch_assoc($dbr)) {
            $u = new self();
            $u->fill_from_row($row);
            $membership = null;
            $type = null;
            $isMember = false;
            if(!empty($row['mIndex'])) {
                $membership = new Membership();
                $membership->Index = (int)$row['mIndex'];
                $membership->User = (int)$u->Index;
                $isMember = MembershipPeriod::userIsMemberOn((int)$u->Index, $today);
                $type = $isMember ? MembershipTypePeriod::userTypeOn((int)$u->Index, $today) : null;
            }
            $out[] = array(
                'user' => $u,
                'membership' => $membership,
                'type' => $type,
                'isMember' => $isMember,
            );
        }
        return $out;
    }

    private function fill_from_row($row) {
        foreach(array_keys($this->_data) as $key) {
            if(array_key_exists($key, $row)) {
                $this->_data[$key] = $row[$key];
            }
        }
    }
}
?>
