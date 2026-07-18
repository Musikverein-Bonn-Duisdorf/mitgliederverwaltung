<?php
class Membership
{
    private $_data = array(
        'Index' => null,
        'User' => null,
        'Type' => null,
        'Status' => null,
    );

    public function __get($key) {
        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    public function __set($key, $val) {
        if(!array_key_exists($key, $this->_data)) {
            return;
        }
        if($key === 'User' || $key === 'Index') {
            $this->_data[$key] = (int)$val;
            return;
        }
        $this->_data[$key] = trim((string)$val);
    }

    public function is_valid() {
        return (int)$this->User > 0 && $this->Type !== '' && $this->Status !== '';
    }

    public function load_by_id($id) {
        $id = (int)$id;
        if($id < 1) {
            return false;
        }
        $sql = sprintf(
            'SELECT * FROM `%sMembership` WHERE `Index` = %d LIMIT 1;',
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

    public function load_by_user($userId) {
        $userId = (int)$userId;
        if($userId < 1) {
            return false;
        }
        $sql = sprintf(
            'SELECT * FROM `%sMembership` WHERE `User` = %d ORDER BY `Index` DESC LIMIT 1;',
            $GLOBALS['dbprefix'],
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

    /**
     * @return Membership[]
     */
    public static function listAll($limit = 500) {
        $limit = max(1, (int)$limit);
        $sql = sprintf(
            'SELECT * FROM `%sMembership` ORDER BY `Index` DESC LIMIT %d;',
            $GLOBALS['dbprefix'],
            $limit
        );
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
            'DELETE FROM `%sMembership` WHERE `Index` = %d LIMIT 1;',
            $GLOBALS['dbprefix'],
            (int)$this->Index
        );
        $ok = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        return (bool)$ok;
    }

    protected function insert() {
        $sql = sprintf(
            'INSERT INTO `%sMembership` (`User`, `Type`, `Status`) VALUES (%d, "%s", "%s");',
            $GLOBALS['dbprefix'],
            (int)$this->User,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->Type),
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->Status)
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
            'UPDATE `%sMembership` SET `User` = %d, `Type` = "%s", `Status` = "%s" WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            (int)$this->User,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->Type),
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->Status),
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
