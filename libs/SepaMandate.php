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

    /** Delete every mandate for user (logs each). */
    public static function deleteAllForUser($userId) {
        $userId = (int)$userId;
        $ok = true;
        foreach(self::listForUser($userId) as $m) {
            if(!$m->delete()) {
                $ok = false;
            }
        }
        return $ok;
    }

    public function getVars() {
        $parts = array(mitLogUserHeader((int)$this->User));
        $parts[] = 'SEPA-ID: '.(int)$this->Index;
        $parts[] = logPart('Mandatsreferenz', logEsc($this->MandateRef));
        $parts[] = logPart('IBAN', logEsc(maskIban($this->IbanEnc)));
        logAppendFilled($parts, 'BIC', $this->Bic);
        $parts[] = logPart('Gültig ab', logEsc(germanDate($this->ValidFrom)));
        if($this->ValidTo) {
            $parts[] = logPart('Gültig bis', logEsc(germanDate($this->ValidTo)));
        }
        $parts[] = logPart('Aktiv', bool2string($this->Active));
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new self();
        if(!$old->load_by_id((int)$this->Index)) {
            return $this->getVars();
        }
        $header = mitLogUserHeader((int)$this->User).', SEPA-ID: '.(int)$this->Index;
        $parts = array();
        if((string)$old->MandateRef !== (string)$this->MandateRef) {
            $parts[] = 'Mandatsreferenz: '.logEsc($old->MandateRef).' &rArr; <b>'.logEsc($this->MandateRef).'</b>';
        }
        if((string)$old->IbanEnc !== (string)$this->IbanEnc) {
            $parts[] = 'IBAN: '.logEsc(maskIban($old->IbanEnc)).' &rArr; <b>'.logEsc(maskIban($this->IbanEnc)).'</b>';
        }
        if((string)$old->Bic !== (string)$this->Bic) {
            $parts[] = 'BIC: '.logEsc($old->Bic ?: '(leer)').' &rArr; <b>'.logEsc($this->Bic ?: '(leer)').'</b>';
        }
        if((string)$old->ValidFrom !== (string)$this->ValidFrom) {
            $parts[] = 'Gültig ab: '.logEsc(germanDate($old->ValidFrom)).' &rArr; <b>'.logEsc(germanDate($this->ValidFrom)).'</b>';
        }
        if((string)$old->ValidTo !== (string)$this->ValidTo) {
            $o = $old->ValidTo ? logEsc(germanDate($old->ValidTo)) : '(offen)';
            $n = $this->ValidTo ? logEsc(germanDate($this->ValidTo)) : '(offen)';
            $parts[] = 'Gültig bis: '.$o.' &rArr; <b>'.$n.'</b>';
        }
        if(boolsDiffer($old->Active, $this->Active)) {
            $parts[] = 'Aktiv: '.bool2string($old->Active).' &rArr; <b>'.bool2string($this->Active).'</b>';
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
