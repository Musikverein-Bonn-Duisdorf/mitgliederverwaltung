<?php
class Document
{
    private $_data = array(
        'Index' => null,
        'User' => null,
        'DocType' => null,
        'NextcloudPath' => null,
        'UploadedAt' => null,
        'Note' => null,
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
        if($key === 'Note' && ($val === '' || $val === null)) {
            $this->_data[$key] = null;
            return;
        }
        $this->_data[$key] = trim((string)$val);
    }

    public function is_valid() {
        return (int)$this->User > 0
            && $this->DocType !== ''
            && $this->NextcloudPath !== '';
    }

    public function load_by_id($id) {
        $id = (int)$id;
        if($id < 1) {
            return false;
        }
        $sql = sprintf(
            'SELECT * FROM `%sDocument` WHERE `Index` = %d LIMIT 1;',
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
     * @return Document[]
     */
    public static function listForUser($userId) {
        $userId = (int)$userId;
        $sql = sprintf(
            'SELECT * FROM `%sDocument` WHERE `User` = %d ORDER BY `UploadedAt` DESC, `Index` DESC;',
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
            $d = new self();
            $d->fill_from_row($row);
            $out[] = $d;
        }
        return $out;
    }

    /**
     * @return Document[]
     */
    public static function listAll($limit = 500) {
        $limit = max(1, (int)$limit);
        $sql = sprintf(
            'SELECT * FROM `%sDocument` ORDER BY `UploadedAt` DESC, `Index` DESC LIMIT %d;',
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
            $d = new self();
            $d->fill_from_row($row);
            $out[] = $d;
        }
        return $out;
    }

    public function getVars() {
        $parts = array(mitLogUserHeader((int)$this->User));
        $parts[] = 'Document-ID: '.(int)$this->Index;
        $parts[] = logPart('Typ', logEsc($this->DocType));
        $parts[] = logPart('Pfad', logEsc($this->NextcloudPath));
        logAppendFilled($parts, 'Notiz', $this->Note);
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new self();
        if(!$old->load_by_id((int)$this->Index)) {
            return $this->getVars();
        }
        $header = mitLogUserHeader((int)$this->User).', Document-ID: '.(int)$this->Index;
        $parts = array();
        if((string)$old->DocType !== (string)$this->DocType) {
            $parts[] = 'Typ: '.logEsc($old->DocType).' &rArr; <b>'.logEsc($this->DocType).'</b>';
        }
        if((string)$old->NextcloudPath !== (string)$this->NextcloudPath) {
            $parts[] = 'Pfad: '.logEsc($old->NextcloudPath).' &rArr; <b>'.logEsc($this->NextcloudPath).'</b>';
        }
        if((string)$old->Note !== (string)$this->Note) {
            $parts[] = 'Notiz: '.logEsc($old->Note ?: '(leer)').' &rArr; <b>'.logEsc($this->Note ?: '(leer)').'</b>';
        }
        if((int)$old->User !== (int)$this->User) {
            $parts[] = 'User: ('.(int)$old->User.') &rArr; <b>('.((int)$this->User).')</b>';
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
            'DELETE FROM `%sDocument` WHERE `Index` = %d LIMIT 1;',
            $GLOBALS['dbprefix'],
            (int)$this->Index
        );
        $ok = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        return (bool)$ok;
    }

    protected function insert() {
        $sql = sprintf(
            'INSERT INTO `%sDocument` (`User`, `DocType`, `NextcloudPath`, `Note`) VALUES (%d, "%s", "%s", %s);',
            $GLOBALS['dbprefix'],
            (int)$this->User,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->DocType),
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->NextcloudPath),
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
            'UPDATE `%sDocument` SET `User` = %d, `DocType` = "%s", `NextcloudPath` = "%s", `Note` = %s WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            (int)$this->User,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->DocType),
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->NextcloudPath),
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
