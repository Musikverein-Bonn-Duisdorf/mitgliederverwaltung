<?php
/**
 * Per-person document vault (local files under uploads/persons/{userId}/).
 */
class Document
{
    const TYPE_BEITRITT = 'Beitritt';
    const TYPE_AUSTRITT = 'Austritt';
    const TYPE_KOMMUNIKATION = 'Kommunikation';
    const TYPE_SONSTIGES = 'Sonstiges';

    private $_data = array(
        'Index' => null,
        'User' => null,
        'DocType' => null,
        'StoredFile' => null,
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
        if(($key === 'Note' || $key === 'StoredFile') && ($val === '' || $val === null)) {
            $this->_data[$key] = null;
            return;
        }
        if($key === 'DocType') {
            $this->_data[$key] = self::normalizeType($val);
            return;
        }
        $this->_data[$key] = trim((string)$val);
    }

    /** @return string[] */
    public static function allowedTypes() {
        return array(
            self::TYPE_BEITRITT,
            self::TYPE_AUSTRITT,
            self::TYPE_KOMMUNIKATION,
            self::TYPE_SONSTIGES,
        );
    }

    public static function normalizeType($type) {
        $t = trim((string)$type);
        if(in_array($t, self::allowedTypes(), true)) {
            return $t;
        }
        return self::TYPE_SONSTIGES;
    }

    public static function storageDir($userId) {
        return dirname(__DIR__).'/uploads/persons/'.(int)$userId;
    }

    public static function resolveStoredFile($userId, $stored) {
        $userId = (int)$userId;
        $stored = trim((string)$stored);
        if($userId < 1 || $stored === '') {
            return null;
        }
        $base = realpath(self::storageDir($userId));
        if($base === false || !is_dir($base)) {
            return null;
        }
        $name = basename(str_replace('\\', '/', $stored));
        if($name === '' || $name === '.' || $name === '..') {
            return null;
        }
        $full = realpath($base.DIRECTORY_SEPARATOR.$name);
        if($full === false || !is_file($full)) {
            return null;
        }
        if(strpos($full, $base.DIRECTORY_SEPARATOR) !== 0 && $full !== $base) {
            return null;
        }
        return $full;
    }

    /**
     * @param array $file $_FILES entry
     * @return string|false basename
     */
    public static function storeUpload($userId, array $file) {
        $userId = (int)$userId;
        if($userId < 1) {
            return false;
        }
        if(!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        if(!empty($file['error']) && (int)$file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        $orig = isset($file['name']) ? (string)$file['name'] : 'doc';
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowed = array('pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp');
        if(!in_array($ext, $allowed, true)) {
            return false;
        }
        $dir = self::storageDir($userId);
        if(!is_dir($dir) && !mkdir($dir, 0750, true)) {
            return false;
        }
        $name = 'doc-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.'.$ext;
        $target = $dir.DIRECTORY_SEPARATOR.$name;
        if(!move_uploaded_file($file['tmp_name'], $target)) {
            return false;
        }
        return $name;
    }

    /**
     * Copy an already-stored file (e.g. membership scan) into the person vault.
     * @return string|false new basename
     */
    public static function storeCopyFromPath($userId, $sourcePath, $preferredExt = '', $preferredStem = '') {
        $userId = (int)$userId;
        if($userId < 1 || !is_file($sourcePath)) {
            return false;
        }
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if($ext === '' && $preferredExt !== '') {
            $ext = strtolower(ltrim($preferredExt, '.'));
        }
        $allowed = array('pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp');
        if(!in_array($ext, $allowed, true)) {
            $ext = 'pdf';
        }
        $dir = self::storageDir($userId);
        if(!is_dir($dir) && !mkdir($dir, 0750, true)) {
            return false;
        }
        $stem = trim((string)$preferredStem);
        if($stem === '') {
            $stem = 'doc-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
        }
        $name = $stem.'.'.$ext;
        $target = $dir.DIRECTORY_SEPARATOR.$name;
        if(is_file($target)) {
            $name = $stem.'-'.date('Ymd-His').'.'.$ext;
            $target = $dir.DIRECTORY_SEPARATOR.$name;
        }
        if(!copy($sourcePath, $target)) {
            return false;
        }
        return $name;
    }

    public function absolutePath() {
        return self::resolveStoredFile((int)$this->User, (string)$this->StoredFile);
    }

    public function displayName() {
        $note = trim((string)$this->Note);
        if($note !== '') {
            return $note;
        }
        $file = trim((string)$this->StoredFile);
        return $file !== '' ? $file : ('Dokument #'.(int)$this->Index);
    }

    public function is_valid() {
        return (int)$this->User > 0
            && $this->DocType !== ''
            && $this->StoredFile !== null
            && $this->StoredFile !== '';
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

    public function getVars() {
        $parts = array(mitLogUserHeader((int)$this->User));
        $parts[] = 'Document-ID: '.(int)$this->Index;
        $parts[] = logPart('Typ', logEsc($this->DocType));
        $parts[] = logPart('Datei', logEsc($this->StoredFile));
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
        if((string)$old->StoredFile !== (string)$this->StoredFile) {
            $parts[] = 'Datei: '.logEsc($old->StoredFile ?: '(leer)').' &rArr; <b>'.logEsc($this->StoredFile ?: '(leer)').'</b>';
        }
        if((string)$old->Note !== (string)$this->Note) {
            $parts[] = 'Notiz: '.logEsc($old->Note ?: '(leer)').' &rArr; <b>'.logEsc($this->Note ?: '(leer)').'</b>';
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
        $path = $this->absolutePath();
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
        if($ok && $path !== null && is_file($path)) {
            @unlink($path);
        }
        return (bool)$ok;
    }

    /**
     * Create Document from uploaded file.
     * @param array $file $_FILES entry
     * @return Document|null
     */
    public static function createFromUpload($userId, $docType, array $file, $note = null) {
        $stored = self::storeUpload($userId, $file);
        if($stored === false) {
            return null;
        }
        $doc = new self();
        $doc->User = (int)$userId;
        $doc->DocType = $docType;
        $doc->StoredFile = $stored;
        $doc->Note = $note;
        if(!$doc->save()) {
            $path = self::resolveStoredFile((int)$userId, $stored);
            if($path) {
                @unlink($path);
            }
            return null;
        }
        return $doc;
    }

    /**
     * Mirror a membership-form scan into the person document vault (Beitritt).
     * @return Document|null
     */
    public static function createFromMembershipScan(MembershipApplication $app) {
        $userId = (int)$app->User;
        $path = MembershipForm::resolveStoredFile((int)$app->Index, (string)$app->ScanFile);
        if($userId < 1 || $path === null) {
            return null;
        }
        $vorname = '';
        $nachname = '';
        $u = new IdentityUser();
        if($u->load_by_id($userId)) {
            $vorname = MembershipForm::identityNameForInput($u->Vorname, MembershipForm::STUB_VORNAME);
            $nachname = MembershipForm::identityNameForInput($u->Nachname, MembershipForm::STUB_NACHNAME);
        }
        $stem = MembershipForm::fileBasename($userId, $vorname, $nachname);
        $stored = self::storeCopyFromPath($userId, $path, '', $stem);
        if($stored === false) {
            return null;
        }
        $doc = new self();
        $doc->User = $userId;
        $doc->DocType = self::TYPE_BEITRITT;
        $doc->StoredFile = $stored;
        $doc->Note = 'Beitrittserklärung (Antrag #'.(int)$app->Index.')';
        if(!$doc->save()) {
            $p = self::resolveStoredFile($userId, $stored);
            if($p) {
                @unlink($p);
            }
            return null;
        }
        return $doc;
    }

    protected function insert() {
        $sql = sprintf(
            'INSERT INTO `%sDocument` (`User`, `DocType`, `StoredFile`, `Note`) VALUES (%d, "%s", "%s", %s);',
            $GLOBALS['dbprefix'],
            (int)$this->User,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->DocType),
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->StoredFile),
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
            'UPDATE `%sDocument` SET `User` = %d, `DocType` = "%s", `StoredFile` = "%s", `Note` = %s WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            (int)$this->User,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->DocType),
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->StoredFile),
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
                $this->$key = $row[$key];
            }
        }
    }
}
?>
