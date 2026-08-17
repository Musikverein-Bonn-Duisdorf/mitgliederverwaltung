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

    /** Sort key: last name, then first name. */
    public function getSortName() {
        return trim((string)$this->Nachname)."\t".trim((string)$this->Vorname);
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
     * @param string $filter all|aktiv|foerdernd|none|member|retention_due
     * @return array<int,array{user:IdentityUser,membership:?Membership,type:?string,isMember:bool,retentionDue:?string}>
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
        elseif($filter === 'none' || $filter === 'retention_due') {
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
            $retentionDue = MembershipPeriod::retentionDueDateForUser((int)$u->Index, $today);
            if($filter === 'retention_due' && ($retentionDue === null || $retentionDue > $today)) {
                continue;
            }
            $out[] = array(
                'user' => $u,
                'membership' => $membership,
                'type' => $type,
                'isMember' => $isMember,
                'retentionDue' => $retentionDue,
            );
        }
        return $out;
    }

    /**
     * Melde-compatible soft-delete: anonymize identity, keep Index for FK history elsewhere.
     * @return bool
     */
    public function softDeleteIdentity() {
        $userId = (int)$this->Index;
        if($userId < 1) {
            return false;
        }
        $cols = self::userColumns();
        $sets = array(
            '`Deleted` = 1',
            '`Vorname` = "gelöschter"',
            '`Nachname` = "Benutzer"',
            '`Email` = ""',
            '`Email2` = ""',
            '`login` = ""',
            '`Passhash` = ""',
        );
        if(isset($cols['DeletedOn'])) {
            $sets[] = '`DeletedOn` = CURRENT_TIMESTAMP';
        }
        foreach(array('getMail', 'notifyInbox', 'notifyAppMail', 'notifyAppTerminNew', 'notifyAppTerminChange', 'notifyAppTerminSoon') as $flag) {
            if(isset($cols[$flag])) {
                $sets[] = '`'.$flag.'` = 0';
            }
        }
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `Index` = %d AND IFNULL(`Deleted`, 0) != 1 LIMIT 1;',
            self::tableName('User'),
            implode(', ', $sets),
            $userId
        );
        try {
            $ok = mysqli_query($GLOBALS['conn'], $sql);
        }
        catch(Throwable $e) {
            return false;
        }
        sqlerror();
        if(!$ok || mysqli_affected_rows($GLOBALS['conn']) < 1) {
            return false;
        }
        if(class_exists('Log')) {
            $log = new Log();
            $log->DBdelete(mitLogUserHeader($userId).', soft-delete (MIT)');
        }
        $this->_data['Deleted'] = 1;
        $this->_data['Vorname'] = 'gelöschter';
        $this->_data['Nachname'] = 'Benutzer';
        $this->_data['Email'] = '';
        $this->_data['Email2'] = '';
        $this->_data['login'] = '';
        return true;
    }

    /**
     * True if Melde still has open inventory loans for this user (blocks soft-delete).
     */
    public function hasMeldeInventoryBlock() {
        $userId = (int)$this->Index;
        if($userId < 1 || !isset($GLOBALS['conn'])) {
            return false;
        }
        $ip = isset($GLOBALS['identityPrefix']) ? (string)$GLOBALS['identityPrefix'] : 'meldeliste_';
        foreach(array(
            sprintf('SELECT 1 FROM `%sInventories` WHERE `User` = %d LIMIT 1', $ip, $userId),
            sprintf('SELECT 1 FROM `%sInventoriesLoans` WHERE `User` = %d AND (`Returned` IS NULL OR `Returned` = 0) LIMIT 1', $ip, $userId),
        ) as $sql) {
            try {
                $dbr = mysqli_query($GLOBALS['conn'], $sql);
            }
            catch(Throwable $e) {
                continue;
            }
            if($dbr && mysqli_fetch_row($dbr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Wipe all MIT domain data for this user, then soft-delete Melde identity.
     * Refuses while membership is open today, or Melde inventory is assigned.
     * @return string empty on success, else German error
     */
    public function purgePerson() {
        $userId = (int)$this->Index;
        if($userId < 1) {
            return 'Person nicht gefunden.';
        }
        if((int)$this->Deleted === 1) {
            return 'Person ist bereits gelöscht.';
        }
        if(MembershipPeriod::userIsMemberOn($userId, date('Y-m-d'))) {
            return 'Zuerst Austritt erfassen — aktive Mitgliedschaft kann nicht gelöscht werden.';
        }
        if($this->hasMeldeInventoryBlock()) {
            return 'In der Meldeliste ist noch Inventar zugewiesen — dort zuerst zurückgeben.';
        }

        if(class_exists('Document')) {
            foreach(Document::listForUser($userId) as $doc) {
                $doc->delete();
            }
        }
        if(class_exists('MembershipApplication')) {
            foreach(MembershipApplication::listForUser($userId) as $app) {
                $app->delete();
            }
        }
        if(class_exists('SepaMandate')) {
            SepaMandate::deleteAllForUser($userId);
        }

        $mem = new Membership();
        if($mem->load_by_user($userId)) {
            $mid = (int)$mem->Index;
            if(class_exists('MembershipTypePeriod')) {
                foreach(MembershipTypePeriod::listForMembership($mid) as $tp) {
                    $tp->delete();
                }
            }
            if(class_exists('MembershipPeriod')) {
                foreach(MembershipPeriod::listForMembership($mid) as $p) {
                    $p->delete();
                }
            }
            $mem->delete();
        }

        $profile = new MemberProfile();
        if($profile->load_by_user($userId)) {
            $profile->delete();
        }

        if(class_exists('Permissions') && Permissions::tableReady()) {
            $sql = sprintf(
                'DELETE FROM `%s` WHERE `User` = %d LIMIT 1;',
                Permissions::tableName(),
                $userId
            );
            try {
                mysqli_query($GLOBALS['conn'], $sql);
            }
            catch(Throwable $e) {
                // ignore
            }
        }

        // Remove empty person upload dir
        $dir = dirname(__DIR__).'/uploads/persons/'.$userId;
        if(is_dir($dir)) {
            foreach(glob($dir.'/*') ?: array() as $f) {
                if(is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($dir);
        }

        if(!$this->softDeleteIdentity()) {
            return 'MIT-Daten gelöscht, Soft-Delete der Identity fehlgeschlagen.';
        }
        return '';
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
