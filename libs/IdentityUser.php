<?php
/**
 * Read-only access to canonical identity users (meldeliste_User by default).
 */
class IdentityUser
{
    private $_data = array(
        'Index' => null,
        'Nachname' => null,
        'Vorname' => null,
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

    private function fill_from_row($row) {
        foreach(array_keys($this->_data) as $key) {
            if(array_key_exists($key, $row)) {
                $this->_data[$key] = $row[$key];
            }
        }
    }
}
?>
