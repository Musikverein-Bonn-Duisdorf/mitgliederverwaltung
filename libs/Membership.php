<?php
/**
 * Membership shell: one row per Melde User. Current type/status derived from periods.
 * AnnualFeeCents = individueller Jahresbeitrag ( Cent; mindestens Config-Mindestbetrag je Typ ).
 */
class Membership
{
    private $_data = array(
        'Index' => null,
        'User' => null,
        'AnnualFeeCents' => null,
    );

    public function __get($key) {
        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    public function __set($key, $val) {
        if(!array_key_exists($key, $this->_data)) {
            return;
        }
        if($key === 'AnnualFeeCents') {
            if($val === null || $val === '') {
                $this->_data[$key] = null;
                return;
            }
            $this->_data[$key] = max(0, (int)$val);
            return;
        }
        $this->_data[$key] = (int)$val;
    }

    public function is_valid() {
        return (int)$this->User > 0;
    }

    public static function tableName() {
        return $GLOBALS['dbprefix'].'Membership';
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

    public function load_by_user($userId) {
        $userId = (int)$userId;
        if($userId < 1) {
            return false;
        }
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `User` = %d ORDER BY `Index` DESC LIMIT 1;',
            self::tableName(),
            $userId
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

    /** Load or create shell for user. */
    public function ensure_for_user($userId) {
        $userId = (int)$userId;
        if($userId < 1) {
            return false;
        }
        if($this->load_by_user($userId)) {
            return true;
        }
        $this->User = $userId;
        return $this->save();
    }

    /**
     * @return Membership[]
     */
    public static function listAll($limit = 500) {
        $limit = max(1, (int)$limit);
        $sql = sprintf('SELECT * FROM `%s` ORDER BY `Index` DESC LIMIT %d;', self::tableName(), $limit);
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $out = array();
        if(!$dbr) {
            return $out;
        }
        while($row = mysqli_fetch_assoc($dbr)) {
            $m = new self();
            $m->fill_from_row($row);
            $out[] = $m;
        }
        return $out;
    }

    public function getVars() {
        $parts = array(mitLogUserHeader((int)$this->User));
        $parts[] = 'Membership-ID: '.(int)$this->Index;
        if($this->AnnualFeeCents !== null && $this->AnnualFeeCents !== '') {
            $fee = class_exists('MembershipForm')
                ? MembershipForm::formatEuroFromCents((int)$this->AnnualFeeCents)
                : ((int)$this->AnnualFeeCents).' Cent';
            $parts[] = logPart('Jahresbeitrag', logEsc($fee));
        }
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new self();
        if(!$old->load_by_id((int)$this->Index)) {
            return mitLogUserHeader((int)$this->User);
        }
        $header = mitLogUserHeader((int)$this->User).', Membership-ID: '.(int)$this->Index;
        $parts = array();
        $oldFee = $old->AnnualFeeCents;
        $newFee = $this->AnnualFeeCents;
        if((string)$oldFee !== (string)$newFee) {
            $fmt = function ($c) {
                if($c === null || $c === '') {
                    return '(leer)';
                }
                return class_exists('MembershipForm')
                    ? MembershipForm::formatEuroFromCents((int)$c)
                    : ((int)$c).' Cent';
            };
            $parts[] = 'Jahresbeitrag: '.logEsc($fmt($oldFee)).' &rArr; <b>'.logEsc($fmt($newFee)).'</b>';
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
        $feeSql = ($this->AnnualFeeCents === null || $this->AnnualFeeCents === '')
            ? 'NULL'
            : (string)(int)$this->AnnualFeeCents;
        if((int)$this->Index > 0) {
            if(class_exists('Log')) {
                $log = new Log();
                $log->DBupdate($this->getChanges());
            }
            $sql = sprintf(
                'UPDATE `%s` SET `User` = %d, `AnnualFeeCents` = %s WHERE `Index` = %d;',
                self::tableName(),
                (int)$this->User,
                $feeSql,
                (int)$this->Index
            );
            $ok = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            return (bool)$ok;
        }
        $sql = sprintf(
            'INSERT INTO `%s` (`User`, `AnnualFeeCents`) VALUES (%d, %s);',
            self::tableName(),
            (int)$this->User,
            $feeSql
        );
        $ok = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$ok) {
            return false;
        }
        $this->_data['Index'] = (int)mysqli_insert_id($GLOBALS['conn']);
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

    /** @return bool */
    public function isMemberOn($date = null) {
        return MembershipPeriod::userIsMemberOn((int)$this->User, $date);
    }

    /** @return string|null aktiv|foerdernd */
    public function typeOn($date = null) {
        return MembershipTypePeriod::userTypeOn((int)$this->User, $date);
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
