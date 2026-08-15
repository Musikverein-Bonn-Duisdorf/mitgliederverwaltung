<?php
/**
 * Membership type segments (aktiv / foerdernd) within tenure. Source of truth for type on a date.
 */
class MembershipTypePeriod
{
    private $_data = array(
        'Index' => null,
        'Membership' => null,
        'Type' => null,
        'DateFrom' => null,
        'DateTo' => null,
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
        if($key === 'Type') {
            $v = strtolower(trim((string)$val));
            $this->_data[$key] = in_array($v, array('aktiv', 'foerdernd'), true) ? $v : null;
            return;
        }
        $this->_data[$key] = ($val === '' || $val === null) ? null : trim((string)$val);
    }

    public static function tableName() {
        return $GLOBALS['dbprefix'].'MembershipTypePeriod';
    }

    public function is_valid() {
        return (int)$this->Membership > 0
            && $this->Type !== null
            && $this->DateFrom !== null
            && $this->DateFrom !== '';
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
     * @return MembershipTypePeriod[]
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

    public static function userTypeOn($userId, $date = null) {
        $userId = (int)$userId;
        if($userId < 1) {
            return null;
        }
        $date = MembershipPeriod::normalizeDate($date);
        $sql = sprintf(
            'SELECT t.`Type` FROM `%s` m
             INNER JOIN `%s` t ON t.`Membership` = m.`Index`
             WHERE m.`User` = %d AND t.`DateFrom` <= "%s"
             AND (t.`DateTo` IS NULL OR t.`DateTo` >= "%s")
             ORDER BY t.`DateFrom` DESC, t.`Index` DESC LIMIT 1;',
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
            return null;
        }
        $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
        return $row ? $row['Type'] : null;
    }

    /**
     * SQL: current type is foerdernd on $date (for Melde hide).
     * @param string $userColumnSql
     */
    public static function sqlUserIsFoerderndOn($userColumnSql, $date = null) {
        $date = MembershipPeriod::normalizeDate($date);
        $mem = Membership::tableName();
        $tp = self::tableName();
        $d = mysqli_real_escape_string($GLOBALS['conn'], $date);
        return sprintf(
            'EXISTS (SELECT 1 FROM `%s` m INNER JOIN `%s` t ON t.`Membership` = m.`Index`
             WHERE m.`User` = %s AND t.`Type` = "foerdernd" AND t.`DateFrom` <= "%s"
             AND (t.`DateTo` IS NULL OR t.`DateTo` >= "%s"))',
            $mem,
            $tp,
            $userColumnSql,
            $d,
            $d
        );
    }

    public static function openType($membershipId, $type, $dateFrom, $note = '') {
        $p = new self();
        $p->Membership = (int)$membershipId;
        $p->Type = $type;
        $p->DateFrom = $dateFrom;
        $p->DateTo = null;
        $p->Note = $note;
        return $p->save() ? $p : null;
    }

    /**
     * Close open type period day before switch, open new type from $dateFrom.
     * If no open period, just open new.
     */
    public static function switchType($membershipId, $newType, $dateFrom, $note = '') {
        $open = self::openForMembership($membershipId);
        if($open) {
            if($open->Type === $newType) {
                return $open;
            }
            $prev = date('Y-m-d', strtotime($dateFrom.' -1 day'));
            if($prev >= $open->DateFrom) {
                $open->DateTo = $prev;
                if(!$open->save()) {
                    return null;
                }
            }
            else {
                $open->DateTo = $dateFrom;
                if(!$open->save()) {
                    return null;
                }
            }
        }
        return self::openType($membershipId, $newType, $dateFrom, $note);
    }

    public static function closeOpenType($membershipId, $dateTo) {
        $open = self::openForMembership($membershipId);
        if(!$open) {
            return true;
        }
        $open->DateTo = $dateTo;
        return $open->save();
    }

    public function getVars() {
        $uid = mitMembershipUserId((int)$this->Membership);
        $parts = array($uid > 0 ? mitLogUserHeader($uid) : 'Membership: '.(int)$this->Membership);
        $parts[] = 'TypPeriod-ID: '.(int)$this->Index;
        $parts[] = logPart('Typ', logEsc($this->Type));
        $parts[] = logPart('Von', logEsc(germanDate($this->DateFrom)));
        $parts[] = logPart('Bis', $this->DateTo ? logEsc(germanDate($this->DateTo)) : 'offen');
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
            .', TypPeriod-ID: '.(int)$this->Index;
        $parts = array();
        if((string)$old->Type !== (string)$this->Type) {
            $parts[] = 'Typ: '.logEsc($old->Type).' &rArr; <b>'.logEsc($this->Type).'</b>';
        }
        if((string)$old->DateFrom !== (string)$this->DateFrom) {
            $parts[] = 'Von: '.logEsc(germanDate($old->DateFrom)).' &rArr; <b>'.logEsc(germanDate($this->DateFrom)).'</b>';
        }
        if((string)$old->DateTo !== (string)$this->DateTo) {
            $o = $old->DateTo ? logEsc(germanDate($old->DateTo)) : 'offen';
            $n = $this->DateTo ? logEsc(germanDate($this->DateTo)) : 'offen';
            $parts[] = 'Bis: '.$o.' &rArr; <b>'.$n.'</b>';
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
            'INSERT INTO `%s` (`Membership`, `Type`, `DateFrom`, `DateTo`, `Note`) VALUES (%d, "%s", %s, %s, %s);',
            self::tableName(),
            (int)$this->Membership,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->Type),
            mkNULLstr($this->DateFrom),
            mkNULLstr($this->DateTo),
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
            'UPDATE `%s` SET `Membership` = %d, `Type` = "%s", `DateFrom` = %s, `DateTo` = %s, `Note` = %s WHERE `Index` = %d;',
            self::tableName(),
            (int)$this->Membership,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->Type),
            mkNULLstr($this->DateFrom),
            mkNULLstr($this->DateTo),
            mkNULLstr($this->Note),
            (int)$this->Index
        );
        $ok = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        return (bool)$ok;
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
