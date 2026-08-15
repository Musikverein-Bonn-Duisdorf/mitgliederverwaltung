<?php
/**
 * Beitrittsantrag: form snapshot + scan; apply creates tenure + type + profile + SEPA.
 */
class MembershipApplication
{
    private $_data = array(
        'Index' => null,
        'User' => null,
        'DesiredType' => null,
        'DesiredEntryDate' => null,
        'AnnualFeeCents' => null,
        'Birthday' => null,
        'Phone' => null,
        'Phone2' => null,
        'Street' => null,
        'Zip' => null,
        'City' => null,
        'Country' => null,
        'AccountHolder' => null,
        'BankName' => null,
        'Iban' => null,
        'Bic' => null,
        'PaymentMethod' => 'sepa',
        'ScanFile' => null,
        'Status' => 'draft',
        'CreatedAt' => null,
        'AppliedAt' => null,
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
        if($key === 'DesiredType') {
            $v = strtolower(trim((string)$val));
            $this->_data[$key] = in_array($v, array('aktiv', 'foerdernd'), true) ? $v : 'aktiv';
            return;
        }
        if($key === 'PaymentMethod') {
            $v = strtolower(trim((string)$val));
            $this->_data[$key] = ($v === 'ueberweisung') ? 'ueberweisung' : 'sepa';
            return;
        }
        if($key === 'Status') {
            $v = strtolower(trim((string)$val));
            $this->_data[$key] = in_array($v, array('draft', 'ready', 'applied'), true) ? $v : 'draft';
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
        $this->_data[$key] = ($val === '' || $val === null) ? null : trim((string)$val);
    }

    public static function tableName() {
        return $GLOBALS['dbprefix'].'MembershipApplication';
    }

    public function is_valid() {
        return (int)$this->User > 0 && $this->DesiredType !== null;
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

    public static function latestForUser($userId) {
        $userId = (int)$userId;
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `User` = %d ORDER BY `Index` DESC LIMIT 1;',
            self::tableName(),
            $userId
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
        if(!$row) {
            return null;
        }
        $a = new self();
        $a->fill_from_row($row);
        return $a;
    }

    /**
     * @return MembershipApplication[]
     */
    public static function listForUser($userId) {
        $userId = (int)$userId;
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `User` = %d ORDER BY `Index` DESC;',
            self::tableName(),
            $userId
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $out = array();
        if(!$dbr) {
            return $out;
        }
        while($row = mysqli_fetch_assoc($dbr)) {
            $a = new self();
            $a->fill_from_row($row);
            $out[] = $a;
        }
        return $out;
    }

    public function getVars() {
        $parts = array(mitLogUserHeader((int)$this->User));
        $parts[] = 'Antrag-ID: '.(int)$this->Index;
        $parts[] = logPart('Typ', logEsc($this->DesiredType));
        $parts[] = logPart('Status', logEsc($this->Status));
        logAppendFilled($parts, 'Eintritt', $this->DesiredEntryDate, $this->DesiredEntryDate ? logEsc(germanDate($this->DesiredEntryDate)) : null);
        if($this->AnnualFeeCents !== null && $this->AnnualFeeCents !== '') {
            $fee = class_exists('MembershipForm')
                ? MembershipForm::formatEuroFromCents((int)$this->AnnualFeeCents)
                : ((int)$this->AnnualFeeCents).' Cent';
            $parts[] = logPart('Jahresbeitrag', logEsc($fee));
        }
        logAppendFilled($parts, 'Scan', $this->ScanFile);
        if($this->Iban !== null && $this->Iban !== '') {
            $parts[] = logPart('IBAN', logEsc(maskIban($this->Iban)));
        }
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new self();
        if(!$old->load_by_id((int)$this->Index)) {
            return $this->getVars();
        }
        $header = mitLogUserHeader((int)$this->User).', Antrag-ID: '.(int)$this->Index;
        $parts = array();
        $map = array(
            'DesiredType' => 'Typ',
            'Status' => 'Status',
            'DesiredEntryDate' => 'Eintritt',
            'ScanFile' => 'Scan',
            'Note' => 'Notiz',
        );
        foreach($map as $key => $label) {
            if((string)$old->$key === (string)$this->$key) {
                continue;
            }
            $o = (string)$old->$key;
            $n = (string)$this->$key;
            if($key === 'DesiredEntryDate') {
                $o = $o !== '' ? germanDate($o) : '(leer)';
                $n = $n !== '' ? germanDate($n) : '(leer)';
            }
            $parts[] = $label.': '.logEsc($o !== '' ? $o : '(leer)').' &rArr; <b>'.logEsc($n !== '' ? $n : '(leer)').'</b>';
        }
        if((string)$old->AnnualFeeCents !== (string)$this->AnnualFeeCents) {
            $fmt = function ($c) {
                if($c === null || $c === '') {
                    return '(leer)';
                }
                return class_exists('MembershipForm')
                    ? MembershipForm::formatEuroFromCents((int)$c)
                    : ((int)$c).' Cent';
            };
            $parts[] = 'Jahresbeitrag: '.logEsc($fmt($old->AnnualFeeCents)).' &rArr; <b>'.logEsc($fmt($this->AnnualFeeCents)).'</b>';
        }
        if((string)$old->Iban !== (string)$this->Iban) {
            $parts[] = 'IBAN: '.logEsc(maskIban($old->Iban)).' &rArr; <b>'.logEsc(maskIban($this->Iban)).'</b>';
        }
        if((string)$old->AppliedAt !== (string)$this->AppliedAt && $this->AppliedAt) {
            $parts[] = 'Angewendet: '.logEsc($this->AppliedAt);
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

    /**
     * Apply application: create/update profile, open tenure + type, optional SEPA, mark applied.
     * @param string $entryDate Y-m-d
     */
    public function apply($entryDate) {
        if((int)$this->Index < 1 || $this->Status === 'applied') {
            return false;
        }
        $entryDate = MembershipPeriod::normalizeDate($entryDate);
        $userId = (int)$this->User;

        $mem = new Membership();
        if(!$mem->ensure_for_user($userId)) {
            return false;
        }
        if(MembershipPeriod::userIsMemberOn($userId, $entryDate)) {
            // Already member — still allow type switch + profile update
        }
        else {
            if(!MembershipPeriod::openTenure((int)$mem->Index, $entryDate, 'Beitritt Antrag #'.(int)$this->Index)) {
                return false;
            }
        }
        if(!MembershipTypePeriod::switchType((int)$mem->Index, $this->DesiredType, $entryDate, 'Antrag #'.(int)$this->Index)) {
            return false;
        }

        $fee = (int)$this->AnnualFeeCents;
        if(class_exists('MembershipForm')) {
            $fee = MembershipForm::clampFeeCents($fee, $this->DesiredType);
        }
        $mem->AnnualFeeCents = $fee;
        if(!$mem->save()) {
            return false;
        }
        $this->AnnualFeeCents = $fee;

        $profile = new MemberProfile();
        $profile->load_or_create($userId);
        $profile->Birthday = $this->Birthday;
        $profile->Phone = $this->Phone;
        $profile->Phone2 = $this->Phone2;
        $profile->Street = $this->Street;
        $profile->Zip = $this->Zip;
        $profile->City = $this->City;
        $profile->Country = $this->Country !== null ? $this->Country : 'DE';
        $profile->AccountHolder = $this->AccountHolder;
        if(!$profile->save()) {
            return false;
        }

        if($this->PaymentMethod === 'sepa' && $this->Iban !== null && $this->Iban !== '') {
            $iban = preg_replace('/\s+/', '', strtoupper((string)$this->Iban));
            $mandate = new SepaMandate();
            $mandate->User = $userId;
            $mandate->IbanEnc = $iban;
            $mandate->Bic = $this->Bic;
            $mandate->MandateRef = 'MVD-SEPA-'.(int)$this->Index.'-'.date('Ymd');
            $mandate->ValidFrom = $entryDate;
            $mandate->ValidTo = null;
            $mandate->Active = 1;
            if(!$mandate->save()) {
                return false;
            }
        }

        $this->Status = 'applied';
        $this->DesiredEntryDate = $entryDate;
        $this->AppliedAt = date('Y-m-d H:i:s');
        return $this->save();
    }

    protected function insert() {
        $sql = sprintf(
            'INSERT INTO `%s` (`User`, `DesiredType`, `DesiredEntryDate`, `AnnualFeeCents`, `Birthday`, `Phone`, `Phone2`, `Street`, `Zip`, `City`, `Country`, `AccountHolder`, `BankName`, `Iban`, `Bic`, `PaymentMethod`, `ScanFile`, `Status`, `Note`)
             VALUES (%d, "%s", %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, "%s", %s, "%s", %s);',
            self::tableName(),
            (int)$this->User,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->DesiredType),
            mkNULLstr($this->DesiredEntryDate),
            ($this->AnnualFeeCents === null || $this->AnnualFeeCents === '') ? 'NULL' : (string)(int)$this->AnnualFeeCents,
            mkNULLstr($this->Birthday),
            mkNULLstr($this->Phone),
            mkNULLstr($this->Phone2),
            mkNULLstr($this->Street),
            mkNULLstr($this->Zip),
            mkNULLstr($this->City),
            mkNULLstr($this->Country),
            mkNULLstr($this->AccountHolder),
            mkNULLstr($this->BankName),
            mkNULLstr($this->Iban),
            mkNULLstr($this->Bic),
            mysqli_real_escape_string($GLOBALS['conn'], (string)($this->PaymentMethod ?: 'sepa')),
            mkNULLstr($this->ScanFile),
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->Status),
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
            'UPDATE `%s` SET `User` = %d, `DesiredType` = "%s", `DesiredEntryDate` = %s, `AnnualFeeCents` = %s, `Birthday` = %s,
             `Phone` = %s, `Phone2` = %s, `Street` = %s, `Zip` = %s, `City` = %s, `Country` = %s,
             `AccountHolder` = %s, `BankName` = %s, `Iban` = %s, `Bic` = %s, `PaymentMethod` = "%s", `ScanFile` = %s,
             `Status` = "%s", `AppliedAt` = %s, `Note` = %s WHERE `Index` = %d;',
            self::tableName(),
            (int)$this->User,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->DesiredType),
            mkNULLstr($this->DesiredEntryDate),
            ($this->AnnualFeeCents === null || $this->AnnualFeeCents === '') ? 'NULL' : (string)(int)$this->AnnualFeeCents,
            mkNULLstr($this->Birthday),
            mkNULLstr($this->Phone),
            mkNULLstr($this->Phone2),
            mkNULLstr($this->Street),
            mkNULLstr($this->Zip),
            mkNULLstr($this->City),
            mkNULLstr($this->Country),
            mkNULLstr($this->AccountHolder),
            mkNULLstr($this->BankName),
            mkNULLstr($this->Iban),
            mkNULLstr($this->Bic),
            mysqli_real_escape_string($GLOBALS['conn'], (string)($this->PaymentMethod ?: 'sepa')),
            mkNULLstr($this->ScanFile),
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->Status),
            mkNULLstr($this->AppliedAt),
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
