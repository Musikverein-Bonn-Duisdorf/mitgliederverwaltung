<?php
/**
 * Membership tenure (Eintritt/Austritt). Source of truth for "is Vereinsmitglied".
 */
class MembershipPeriod
{
    private $_data = array(
        'Index' => null,
        'Membership' => null,
        'DateFrom' => null,
        'DateTo' => null,
        'ExitReason' => null,
        'Note' => null,
    );

    public function __get($key) {
        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    public function __set($key, $val) {
        if(!array_key_exists($key, $this->_data)) {
            return;
        }
        if($key === 'Index' || $key === 'Membership') {
            $this->_data[$key] = (int)$val;
            return;
        }
        $this->_data[$key] = ($val === '' || $val === null) ? null : trim((string)$val);
    }

    public static function tableName() {
        return $GLOBALS['dbprefix'].'MembershipPeriod';
    }

    public function is_valid() {
        return (int)$this->Membership > 0 && $this->DateFrom !== null && $this->DateFrom !== '';
    }

    public function load_by_id($id) {
        $id = (int)$id;
        if($id < 1) {
            return false;
        }
        $sql = sprintf('SELECT * FROM `%s` WHERE `Index` = %d LIMIT 1;', self::tableName(), $id);
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
     * @return MembershipPeriod[]
     */
    public static function listForMembership($membershipId) {
        $membershipId = (int)$membershipId;
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `Membership` = %d ORDER BY `DateFrom` DESC, `Index` DESC;',
            self::tableName(),
            $membershipId
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $out = array();
        if(!$dbr) {
            return $out;
        }
        while($row = mysqli_fetch_assoc($dbr)) {
            $p = new self();
            $p->fill_from_row($row);
            $out[] = $p;
        }
        return $out;
    }

    public static function openForMembership($membershipId) {
        $membershipId = (int)$membershipId;
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `Membership` = %d AND (`DateTo` IS NULL) ORDER BY `DateFrom` DESC LIMIT 1;',
            self::tableName(),
            $membershipId
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
        if(!$row) {
            return null;
        }
        $p = new self();
        $p->fill_from_row($row);
        return $p;
    }

    /**
     * SQL fragment: user column is Vereinsmitglied on $date (Y-m-d).
     * @param string $userColumnSql e.g. 'u.`Index`'
     */
    public static function sqlUserIsMemberOn($userColumnSql, $date = null) {
        $date = self::normalizeDate($date);
        $mem = Membership::tableName();
        $per = self::tableName();
        return sprintf(
            'EXISTS (SELECT 1 FROM `%s` m INNER JOIN `%s` p ON p.`Membership` = m.`Index`
             WHERE m.`User` = %s AND p.`DateFrom` <= "%s"
             AND (p.`DateTo` IS NULL OR p.`DateTo` >= "%s"))',
            $mem,
            $per,
            $userColumnSql,
            mysqli_real_escape_string($GLOBALS['conn'], $date),
            mysqli_real_escape_string($GLOBALS['conn'], $date)
        );
    }

    public static function userIsMemberOn($userId, $date = null) {
        $userId = (int)$userId;
        if($userId < 1) {
            return false;
        }
        $date = self::normalizeDate($date);
        $sql = sprintf(
            'SELECT 1 FROM `%s` m INNER JOIN `%s` p ON p.`Membership` = m.`Index`
             WHERE m.`User` = %d AND p.`DateFrom` <= "%s"
             AND (p.`DateTo` IS NULL OR p.`DateTo` >= "%s") LIMIT 1;',
            Membership::tableName(),
            self::tableName(),
            $userId,
            mysqli_real_escape_string($GLOBALS['conn'], $date),
            mysqli_real_escape_string($GLOBALS['conn'], $date)
        );
        try {
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
        }
        catch(Throwable $e) {
            return false;
        }
        return ($dbr && mysqli_fetch_row($dbr)) ? true : false;
    }

    /** Open tenure starting DateFrom (closes nothing). */
    public static function openTenure($membershipId, $dateFrom, $note = '') {
        $p = new self();
        $p->Membership = (int)$membershipId;
        $p->DateFrom = $dateFrom;
        $p->DateTo = null;
        $p->ExitReason = null;
        $p->Note = $note;
        return $p->save() ? $p : null;
    }

    /** Close open tenure on $dateTo with reason. */
    public static function closeOpenTenure($membershipId, $dateTo, $exitReason = 'austritt', $note = '') {
        $open = self::openForMembership($membershipId);
        if(!$open) {
            return false;
        }
        $open->DateTo = $dateTo;
        $open->ExitReason = $exitReason;
        if($note !== '') {
            $open->Note = trim((string)$open->Note.' '.$note);
        }
        return $open->save();
    }

    public function getVars() {
        $uid = mitMembershipUserId((int)$this->Membership);
        $parts = array($uid > 0 ? mitLogUserHeader($uid) : 'Membership: '.(int)$this->Membership);
        $parts[] = 'Tenure-ID: '.(int)$this->Index;
        $parts[] = logPart('Von', logEsc(germanDate($this->DateFrom)));
        if($this->DateTo) {
            $parts[] = logPart('Bis', logEsc(germanDate($this->DateTo)));
        }
        else {
            $parts[] = logPart('Bis', 'offen');
        }
        logAppendFilled($parts, 'Austrittsgrund', $this->ExitReason);
        logAppendFilled($parts, 'Notiz', $this->Note);
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new self();
        if(!$old->load_by_id((int)$this->Index)) {
            return $this->getVars();
        }
        $uid = mitMembershipUserId((int)$this->Membership);
        $header = ($uid > 0 ? mitLogUserHeader($uid) : 'Membership: '.(int)$this->Membership)
            .', Tenure-ID: '.(int)$this->Index;
        $parts = array();
        if((string)$old->DateFrom !== (string)$this->DateFrom) {
            $parts[] = 'Von: '.logEsc(germanDate($old->DateFrom)).' &rArr; <b>'.logEsc(germanDate($this->DateFrom)).'</b>';
        }
        if((string)$old->DateTo !== (string)$this->DateTo) {
            $o = $old->DateTo ? logEsc(germanDate($old->DateTo)) : 'offen';
            $n = $this->DateTo ? logEsc(germanDate($this->DateTo)) : 'offen';
            $parts[] = 'Bis: '.$o.' &rArr; <b>'.$n.'</b>';
        }
        if((string)$old->ExitReason !== (string)$this->ExitReason) {
            $parts[] = 'Austrittsgrund: '.logEsc($old->ExitReason ?: '(leer)').' &rArr; <b>'.logEsc($this->ExitReason ?: '(leer)').'</b>';
        }
        if((string)$old->Note !== (string)$this->Note) {
            $parts[] = 'Notiz: '.logEsc($old->Note ?: '(leer)').' &rArr; <b>'.logEsc($this->Note ?: '(leer)').'</b>';
        }
        if(!$parts) {
            return $header;
        }
        return $header.', '.implode(', ', $parts);
    }

    public function save() {
        if(!$this->is_valid()) {
            return false;
        }
        if((int)$this->Index > 0) {
            if(class_exists('Log')) {
                $log = new Log();
                $log->DBupdate($this->getChanges());
            }
            return $this->update();
        }
        if(!$this->insert()) {
            return false;
        }
        if(class_exists('Log')) {
            $log = new Log();
            $log->DBinsert($this->getVars());
        }
        return true;
    }

    public function delete() {
        if((int)$this->Index < 1) {
            return false;
        }
        if(class_exists('Log')) {
            $log = new Log();
            $log->DBdelete($this->getVars());
        }
        $sql = sprintf('DELETE FROM `%s` WHERE `Index` = %d LIMIT 1;', self::tableName(), (int)$this->Index);
        $ok = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        return (bool)$ok;
    }

    protected function insert() {
        $sql = sprintf(
            'INSERT INTO `%s` (`Membership`, `DateFrom`, `DateTo`, `ExitReason`, `Note`) VALUES (%d, %s, %s, %s, %s);',
            self::tableName(),
            (int)$this->Membership,
            mkNULLstr($this->DateFrom),
            mkNULLstr($this->DateTo),
            mkNULLstr($this->ExitReason),
            mkNULLstr($this->Note)
        );
        $ok = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$ok) {
            return false;
        }
        $this->_data['Index'] = (int)mysqli_insert_id($GLOBALS['conn']);
        return true;
    }

    protected function update() {
        $sql = sprintf(
            'UPDATE `%s` SET `Membership` = %d, `DateFrom` = %s, `DateTo` = %s, `ExitReason` = %s, `Note` = %s WHERE `Index` = %d;',
            self::tableName(),
            (int)$this->Membership,
            mkNULLstr($this->DateFrom),
            mkNULLstr($this->DateTo),
            mkNULLstr($this->ExitReason),
            mkNULLstr($this->Note),
            (int)$this->Index
        );
        $ok = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        return (bool)$ok;
    }

    public static function countOpenForMembership($membershipId) {
        $membershipId = (int)$membershipId;
        $sql = sprintf(
            'SELECT COUNT(*) AS `c` FROM `%s` WHERE `Membership` = %d AND (`DateTo` IS NULL);',
            self::tableName(),
            $membershipId
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
        return $row ? (int)$row['c'] : 0;
    }

    /** Configured retention years after exit/death (default 5). */
    public static function retentionYears() {
        $n = 5;
        if(isset($GLOBALS['optionsDB']['membershipRetentionYears'])) {
            $n = (int)$GLOBALS['optionsDB']['membershipRetentionYears'];
        }
        return max(1, min(50, $n));
    }

    /**
     * Last closed exit DateTo (austritt|tod) for user, or null.
     * @return string|null Y-m-d
     */
    public static function lastExitDateForUser($userId) {
        $userId = (int)$userId;
        if($userId < 1) {
            return null;
        }
        $sql = sprintf(
            'SELECT p.`DateTo` FROM `%s` m
             INNER JOIN `%s` p ON p.`Membership` = m.`Index`
             WHERE m.`User` = %d AND p.`DateTo` IS NOT NULL
               AND p.`ExitReason` IN ("austritt", "tod")
             ORDER BY p.`DateTo` DESC, p.`Index` DESC LIMIT 1;',
            Membership::tableName(),
            self::tableName(),
            $userId
        );
        try {
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
        }
        catch(Throwable $e) {
            return null;
        }
        $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
        return ($row && !empty($row['DateTo'])) ? substr((string)$row['DateTo'], 0, 10) : null;
    }

    /**
     * Retention due date = last exit + N years; null if still member or no exit.
     * @return string|null Y-m-d
     */
    public static function retentionDueDateForUser($userId, $today = null) {
        $userId = (int)$userId;
        $today = self::normalizeDate($today);
        if(self::userIsMemberOn($userId, $today)) {
            return null;
        }
        $exit = self::lastExitDateForUser($userId);
        if($exit === null) {
            return null;
        }
        $years = self::retentionYears();
        $due = date('Y-m-d', strtotime($exit.' +'.$years.' years'));
        return $due ?: null;
    }

    /**
     * @return string none|upcoming|due
     */
    public static function userRetentionStatus($userId, $today = null) {
        $today = self::normalizeDate($today);
        $due = self::retentionDueDateForUser($userId, $today);
        if($due === null) {
            return 'none';
        }
        if($due <= $today) {
            return 'due';
        }
        $soon = date('Y-m-d', strtotime($today.' +30 days'));
        if($due <= $soon) {
            return 'upcoming';
        }
        return 'none';
    }

    /** Delete all SEPA mandates and clear profile bank holder for user. */
    public static function wipeBankDataForUser($userId) {
        $userId = (int)$userId;
        if($userId < 1) {
            return false;
        }
        if(class_exists('SepaMandate')) {
            SepaMandate::deleteAllForUser($userId);
        }
        if(class_exists('MemberProfile')) {
            $profile = new MemberProfile();
            if($profile->load_by_user($userId)) {
                $profile->AccountHolder = null;
                $profile->save();
            }
        }
        return true;
    }

    public static function normalizeDate($date) {
        if($date === null || $date === '') {
            return date('Y-m-d');
        }
        return substr(trim((string)$date), 0, 10);
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
