<?php
class SepaMandate
{
    private $_data = array(
        'Index' => null,
        'User' => null,
        'IbanEnc' => null,
        'Bic' => null,
        'MandateRef' => null,
        'ValidFrom' => null,
        'ValidTo' => null,
        'Active' => null,
    );

    public function __get($key) {
        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    public function __set($key, $val) {
        if(!array_key_exists($key, $this->_data)) {
            return;
        }
        if(in_array($key, array('Index', 'User', 'Active'), true)) {
            $this->_data[$key] = (int)$val;
            return;
        }
        if($key === 'ValidTo' && ($val === '' || $val === null)) {
            $this->_data[$key] = null;
            return;
        }
        $this->_data[$key] = trim((string)$val);
    }

    public function is_valid() {
        return (int)$this->User > 0
            && $this->IbanEnc !== ''
            && $this->MandateRef !== ''
            && $this->ValidFrom !== '';
    }

    public function maskedIban() {
        return maskIban($this->IbanEnc);
    }

    public function load_by_id($id) {
        $id = (int)$id;
        if($id < 1) {
            return false;
        }
        $sql = sprintf(
            'SELECT * FROM `%sSepaMandate` WHERE `Index` = %d LIMIT 1;',
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
     * @return SepaMandate[]
     */
    public static function listAll($limit = 500) {
        $limit = max(1, (int)$limit);
        $sql = sprintf(
            'SELECT * FROM `%sSepaMandate` ORDER BY `ValidFrom` DESC, `Index` DESC LIMIT %d;',
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

    /**
     * @return SepaMandate[]
     */
    public static function listForUser($userId) {
        $userId = (int)$userId;
        $sql = sprintf(
            'SELECT * FROM `%sSepaMandate` WHERE `User` = %d ORDER BY `ValidFrom` DESC;',
            $GLOBALS['dbprefix'],
            $userId
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
            'DELETE FROM `%sSepaMandate` WHERE `Index` = %d LIMIT 1;',
            $GLOBALS['dbprefix'],
            (int)$this->Index
        );
        $ok = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        return (bool)$ok;
    }

    protected function insert() {
        $sql = sprintf(
            'INSERT INTO `%sSepaMandate` (`User`, `IbanEnc`, `Bic`, `MandateRef`, `ValidFrom`, `ValidTo`, `Active`) VALUES (%d, "%s", %s, "%s", %s, %s, %d);',
            $GLOBALS['dbprefix'],
            (int)$this->User,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->IbanEnc),
            mkNULLstr($this->Bic),
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->MandateRef),
            mkNULLstr($this->ValidFrom),
            mkNULLstr($this->ValidTo),
            (int)$this->Active
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
            'UPDATE `%sSepaMandate` SET `User` = %d, `IbanEnc` = "%s", `Bic` = %s, `MandateRef` = "%s", `ValidFrom` = %s, `ValidTo` = %s, `Active` = %d WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            (int)$this->User,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->IbanEnc),
            mkNULLstr($this->Bic),
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->MandateRef),
            mkNULLstr($this->ValidFrom),
            mkNULLstr($this->ValidTo),
            (int)$this->Active,
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
