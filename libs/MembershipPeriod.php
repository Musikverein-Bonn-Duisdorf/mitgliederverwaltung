<?php
class MembershipPeriod
{
    private $_data = array(
        'Index' => null,
        'Membership' => null,
        'DateFrom' => null,
        'DateTo' => null,
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

    public function is_valid() {
        return (int)$this->Membership > 0 && $this->DateFrom !== null && $this->DateFrom !== '';
    }

    public function load_by_id($id) {
        $id = (int)$id;
        if($id < 1) {
            return false;
        }
        $sql = sprintf(
            'SELECT * FROM `%sMembershipPeriod` WHERE `Index` = %d LIMIT 1;',
            $GLOBALS['dbprefix'],
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

    /**
     * @return MembershipPeriod[]
     */
    public static function listForMembership($membershipId) {
        $membershipId = (int)$membershipId;
        $sql = sprintf(
            'SELECT * FROM `%sMembershipPeriod` WHERE `Membership` = %d ORDER BY `DateFrom` DESC;',
            $GLOBALS['dbprefix'],
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

    public function save() {
        if(!$this->is_valid()) {
            return false;
        }
        if((int)$this->Index > 0) {
            return $this->update();
        }
        return $this->insert();
    }

    public function delete() {
        if((int)$this->Index < 1) {
            return false;
        }
        $sql = sprintf(
            'DELETE FROM `%sMembershipPeriod` WHERE `Index` = %d LIMIT 1;',
            $GLOBALS['dbprefix'],
            (int)$this->Index
        );
        $ok = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        return (bool)$ok;
    }

    protected function insert() {
        $sql = sprintf(
            'INSERT INTO `%sMembershipPeriod` (`Membership`, `DateFrom`, `DateTo`) VALUES (%d, %s, %s);',
            $GLOBALS['dbprefix'],
            (int)$this->Membership,
            mkNULLstr($this->DateFrom),
            mkNULLstr($this->DateTo)
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
            'UPDATE `%sMembershipPeriod` SET `Membership` = %d, `DateFrom` = %s, `DateTo` = %s WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            (int)$this->Membership,
            mkNULLstr($this->DateFrom),
            mkNULLstr($this->DateTo),
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
