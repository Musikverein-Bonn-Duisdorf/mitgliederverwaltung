<?php
/**
 * Audit log for Membership Type changes (aktiv <-> foerdernd).
 */
class MembershipTypeChange
{
    private $_data = array(
        'Index' => null,
        'Membership' => null,
        'FromType' => null,
        'ToType' => null,
        'ChangedAt' => null,
        'ChangedBy' => null,
        'Note' => null,
    );

    public function __get($key) {
        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    public function __set($key, $val) {
        if(!array_key_exists($key, $this->_data)) {
            return;
        }
        if(in_array($key, array('Index', 'Membership', 'ChangedBy'), true)) {
            $this->_data[$key] = (int)$val;
            return;
        }
        $this->_data[$key] = $val === null ? null : trim((string)$val);
    }

    public static function tableName() {
        return $GLOBALS['dbprefix'].'MembershipTypeChange';
    }

    /**
     * @param int $membershipId
     * @return MembershipTypeChange[]
     */
    public static function listForMembership($membershipId, $limit = 100) {
        $membershipId = (int)$membershipId;
        $limit = max(1, (int)$limit);
        $out = array();
        if($membershipId < 1) {
            return $out;
        }
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `Membership` = %d ORDER BY `ChangedAt` DESC, `Index` DESC LIMIT %d;',
            self::tableName(),
            $membershipId,
            $limit
        );
        try {
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
        }
        catch(Throwable $e) {
            return $out;
        }
        if(!$dbr) {
            return $out;
        }
        while($row = mysqli_fetch_assoc($dbr)) {
            $c = new self();
            $c->fill_from_row($row);
            $out[] = $c;
        }
        return $out;
    }

    /**
     * @param int $membershipId
     * @param string $fromType
     * @param string $toType
     * @param int $changedBy
     * @param string $note
     * @return bool
     */
    public static function record($membershipId, $fromType, $toType, $changedBy = 0, $note = '') {
        $membershipId = (int)$membershipId;
        if($membershipId < 1 || $fromType === '' || $toType === '' || $fromType === $toType) {
            return false;
        }
        $c = new self();
        $c->Membership = $membershipId;
        $c->FromType = $fromType;
        $c->ToType = $toType;
        $c->ChangedBy = (int)$changedBy;
        $c->Note = $note;
        return $c->insert();
    }

    private function insert() {
        $sql = sprintf(
            'INSERT INTO `%s` (`Membership`, `FromType`, `ToType`, `ChangedBy`, `Note`) VALUES (%d, "%s", "%s", %d, %s);',
            self::tableName(),
            (int)$this->Membership,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->FromType),
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->ToType),
            (int)$this->ChangedBy,
            mkNULLstr($this->Note)
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

    private function fill_from_row($row) {
        foreach(array_keys($this->_data) as $key) {
            if(array_key_exists($key, $row)) {
                $this->_data[$key] = $row[$key];
            }
        }
    }
}
?>
