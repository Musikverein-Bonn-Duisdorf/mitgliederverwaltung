<?php
/**
 * MIT person profile (sensitive data), 1:1 with Melde User.Index.
 */
class MemberProfile
{
    private $_data = array(
        'Index' => null,
        'User' => null,
        'Birthday' => null,
        'Phone' => null,
        'Street' => null,
        'Zip' => null,
        'City' => null,
        'Country' => null,
        'AccountHolder' => null,
    );

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
        if($val === null || $val === '') {
            $this->_data[$key] = null;
            return;
        }
        $this->_data[$key] = is_string($val) ? trim($val) : $val;
    }

    public static function tableName() {
        return $GLOBALS['dbprefix'].'MemberProfile';
    }

    public function load_by_id($id) {
        $id = (int)$id;
        if($id < 1) {
            return false;
        }
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `Index` = %d LIMIT 1;',
            self::tableName(),
            $id
        );
        try {
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
        }
        catch(Throwable $e) {
            return false;
        }
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
            'SELECT * FROM `%s` WHERE `User` = %d LIMIT 1;',
            self::tableName(),
            $userId
        );
        try {
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
        }
        catch(Throwable $e) {
            return false;
        }
        sqlerror();
        $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
        if(!$row) {
            $this->User = $userId;
            return false;
        }
        $this->fill_from_row($row);
        return true;
    }

    /**
     * Load existing row or prepare empty in-memory shell (no DB insert until save).
     */
    public function load_or_create($userId) {
        $userId = (int)$userId;
        if($this->load_by_user($userId)) {
            return true;
        }
        $this->User = $userId;
        return true;
    }

    public function getVars() {
        $parts = array(mitLogUserHeader((int)$this->User));
        $parts[] = 'Profil-ID: '.(int)$this->Index;
        logAppendFilled($parts, 'Geburtstag', $this->Birthday, $this->Birthday ? logEsc(germanDate($this->Birthday)) : null);
        logAppendFilled($parts, 'Telefon', $this->Phone);
        logAppendFilled($parts, 'Straße', $this->Street);
        logAppendFilled($parts, 'PLZ', $this->Zip);
        logAppendFilled($parts, 'Ort', $this->City);
        logAppendFilled($parts, 'Land', $this->Country);
        logAppendFilled($parts, 'Kontoinhaber', $this->AccountHolder);
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new self();
        if(!$old->load_by_id((int)$this->Index)) {
            return mitLogUserHeader((int)$this->User);
        }
        $parts = array();
        $fields = array(
            'Birthday' => 'Geburtstag',
            'Phone' => 'Telefon',
            'Street' => 'Straße',
            'Zip' => 'PLZ',
            'City' => 'Ort',
            'Country' => 'Land',
            'AccountHolder' => 'Kontoinhaber',
        );
        foreach($fields as $key => $label) {
            $a = (string)$old->$key;
            $b = (string)$this->$key;
            if($a === $b) {
                continue;
            }
            $oldDisp = ($key === 'Birthday' && $a !== '') ? logEsc(germanDate($a)) : logEsc($a);
            $newDisp = ($key === 'Birthday' && $b !== '') ? logEsc(germanDate($b)) : logEsc($b);
            if($oldDisp === '') {
                $oldDisp = '(leer)';
            }
            if($newDisp === '') {
                $newDisp = '(leer)';
            }
            $parts[] = $label.': '.$oldDisp.' &rArr; <b>'.$newDisp.'</b>';
        }
        $header = mitLogUserHeader((int)$this->User).', Profil-ID: '.(int)$this->Index;
        if(!$parts) {
            return $header;
        }
        return $header.', '.implode(', ', $parts);
    }

    public function save() {
        if((int)$this->User < 1) {
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

    private function insert() {
        $sql = sprintf(
            'INSERT INTO `%s` (`User`, `Birthday`, `Phone`, `Street`, `Zip`, `City`, `Country`, `AccountHolder`) VALUES (%d, %s, %s, %s, %s, %s, %s, %s);',
            self::tableName(),
            (int)$this->User,
            mkNULLstr($this->Birthday),
            mkNULLstr($this->Phone),
            mkNULLstr($this->Street),
            mkNULLstr($this->Zip),
            mkNULLstr($this->City),
            mkNULLstr($this->Country),
            mkNULLstr($this->AccountHolder)
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
        return true;
    }

    private function update() {
        $sql = sprintf(
            'UPDATE `%s` SET `Birthday` = %s, `Phone` = %s, `Street` = %s, `Zip` = %s, `City` = %s, `Country` = %s, `AccountHolder` = %s WHERE `Index` = %d;',
            self::tableName(),
            mkNULLstr($this->Birthday),
            mkNULLstr($this->Phone),
            mkNULLstr($this->Street),
            mkNULLstr($this->Zip),
            mkNULLstr($this->City),
            mkNULLstr($this->Country),
            mkNULLstr($this->AccountHolder),
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

    public function delete() {
        if((int)$this->Index < 1) {
            return false;
        }
        if(class_exists('Log')) {
            $log = new Log();
            $log->DBdelete($this->getVars());
        }
        $sql = sprintf('DELETE FROM `%s` WHERE `Index` = %d LIMIT 1;', self::tableName(), (int)$this->Index);
        try {
            $ok = mysqli_query($GLOBALS['conn'], $sql);
        }
        catch(Throwable $e) {
            return false;
        }
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
