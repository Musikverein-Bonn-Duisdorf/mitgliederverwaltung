<?php
/**
 * Schema create / check / repair based on config/DBconfig.json.
 * Safe for shared hosting (mysqli only, no shell).
 */
class SchemaManager
{
    private $schema = array();
    private $schemaPath;
    private $report = array();

    public function __construct($schemaPath = null) {
        if($schemaPath === null) {
            $schemaPath = dirname(__DIR__).'/config/DBconfig.json';
        }
        $this->schemaPath = $schemaPath;
        $this->loadSchema();
    }

    private function loadSchema() {
        if(!is_readable($this->schemaPath)) {
            throw new RuntimeException('DBconfig not readable: '.$this->schemaPath);
        }
        $json = file_get_contents($this->schemaPath);
        $data = json_decode($json, true);
        if(!is_array($data)) {
            throw new RuntimeException('Invalid DBconfig JSON');
        }
        $this->schema = $data;
    }

    public function getSchema() {
        return $this->schema;
    }

    public function getReport() {
        return $this->report;
    }

    /**
     * Statuses that matter in check/repair UI (skip noisy "ok").
     * @param string $status
     * @return bool
     */
    public static function isNotableStatus($status) {
        return in_array((string)$status, array(
            'created',
            'fixed',
            'removed',
            'missing',
            'mismatch',
            'error',
            'obsolete',
        ), true);
    }

    public function hasErrors() {
        foreach($this->report as $entry) {
            if(in_array($entry['status'], array('error', 'missing', 'mismatch'), true)) {
                return true;
            }
        }
        return false;
    }

    public function hasChanges() {
        foreach($this->report as $entry) {
            if(in_array($entry['status'], array('created', 'fixed', 'missing', 'mismatch', 'removed'), true)) {
                return true;
            }
        }
        return false;
    }

    private function addReport($level, $target, $status, $message = '', $detail = null) {
        $this->report[] = array(
            'level' => $level,
            'target' => $target,
            'status' => $status,
            'message' => $message,
            'detail' => $detail,
        );
    }

    /**
     * True if mit_ config table is missing or schema version is zero.
     */
    public function isFreshInstall() {
        $configTable = new SQLtable('config');
        if(!$configTable->exists()) {
            return true;
        }
        return $this->getInstalledSchemaVersion() < 1;
    }

    public function check() {
        $this->report = array();
        $this->processSchema(false, false);
        $this->pruneObsoleteSchema(false);
        $this->checkConfigDefaults(false);
        $this->pruneObsoleteConfig(false);
        return $this->report;
    }

    public function create() {
        $this->report = array();
        $installedBefore = $this->getInstalledSchemaVersion();
        $this->migratePhone2IntoPhone($installedBefore, true);
        $this->migrateBeitragMindestToEuro($installedBefore, true);
        $this->processSchema(true, false);
        $this->pruneObsoleteSchema(true);
        $this->checkConfigDefaults(true);
        $this->pruneObsoleteConfig(true);
        $this->migratePermissionGrants($installedBefore, true);
        $this->ensureAllTablesUtf8mb4();
        $this->finalizeSchemaVersion();
        return $this->report;
    }

    public function repair() {
        $this->report = array();
        $installedBefore = $this->getInstalledSchemaVersion();
        $this->migratePhone2IntoPhone($installedBefore, true);
        $this->migrateBeitragMindestToEuro($installedBefore, true);
        $this->processSchema(true, true);
        $this->pruneObsoleteSchema(true);
        $this->checkConfigDefaults(true);
        $this->pruneObsoleteConfig(true);
        $this->migratePermissionGrants($installedBefore, true);
        $this->ensureAllTablesUtf8mb4();
        $this->finalizeSchemaVersion();
        return $this->report;
    }

    public function getExpectedSchemaVersion($forceReload = false) {
        if(!function_exists('getExpectedSchemaVersion')) {
            require_once dirname(__DIR__).'/config/SchemaVersion.php';
        }
        return (int)call_user_func('getExpectedSchemaVersion', $forceReload);
    }

    public function getInstalledSchemaVersion() {
        $configTable = new SQLtable('config');
        if(!$configTable->exists()) {
            return 0;
        }
        $sql = sprintf(
            "SELECT `Value` FROM `%sconfig` WHERE `Parameter` = 'SchemaVersion' LIMIT 1;",
            $GLOBALS['dbprefix']
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        $row = $dbr ? mysqli_fetch_array($dbr) : null;
        if(!$row || !isset($row['Value'])) {
            return 0;
        }
        return (int)$row['Value'];
    }

    public function isSchemaOutdated($forceReload = false) {
        return $this->getInstalledSchemaVersion() < $this->getExpectedSchemaVersion($forceReload);
    }

    public function setInstalledSchemaVersion($version) {
        $version = (int)$version;
        $configTable = new SQLtable('config');
        if(!$configTable->exists()) {
            $this->addReport('data', 'SchemaVersion', 'error', 'config-Tabelle fehlt — Version nicht gesetzt');
            return false;
        }
        $param = 'SchemaVersion';
        $sql = sprintf(
            "SELECT `Parameter` FROM `%sconfig` WHERE `Parameter` = '%s' LIMIT 1;",
            $GLOBALS['dbprefix'],
            mysqli_real_escape_string($GLOBALS['conn'], $param)
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        $row = $dbr ? mysqli_fetch_array($dbr) : null;
        $exists = $row && isset($row['Parameter']) && $row['Parameter'] === $param;

        if($exists) {
            $update = sprintf(
                "UPDATE `%sconfig` SET `Value` = '%d' WHERE `Parameter` = '%s';",
                $GLOBALS['dbprefix'],
                $version,
                mysqli_real_escape_string($GLOBALS['conn'], $param)
            );
            $ok = mysqli_query($GLOBALS['conn'], $update);
        }
        else {
            $insert = sprintf(
                "INSERT INTO `%sconfig` (`Parameter`, `Value`, `Type`, `Description`) VALUES ('%s', '%d', 'int', '%s');",
                $GLOBALS['dbprefix'],
                mysqli_real_escape_string($GLOBALS['conn'], $param),
                $version,
                mysqli_real_escape_string($GLOBALS['conn'], 'Installierte DB-Schema-Version (Soll: config/SchemaVersion.php)')
            );
            $ok = mysqli_query($GLOBALS['conn'], $insert);
        }

        if($ok) {
            if(isset($GLOBALS['optionsDB']) && is_array($GLOBALS['optionsDB'])) {
                $GLOBALS['optionsDB']['SchemaVersion'] = (string)$version;
            }
            return true;
        }
        $this->addReport(
            'data',
            'SchemaVersion',
            'error',
            'SchemaVersion konnte nicht gespeichert werden',
            mysqli_errno($GLOBALS['conn']).': '.mysqli_error($GLOBALS['conn'])
        );
        return false;
    }

    private function finalizeSchemaVersion() {
        $expected = $this->getExpectedSchemaVersion();
        $installed = $this->getInstalledSchemaVersion();
        if($this->hasErrors()) {
            $this->addReport(
                'data',
                'SchemaVersion',
                'mismatch',
                sprintf('Version nicht gesetzt (Fehler vorhanden). Installiert: %d, Soll: %d', $installed, $expected)
            );
            return;
        }
        if($installed === $expected) {
            $this->addReport('data', 'SchemaVersion', 'ok', 'Schema-Version '.$expected);
            return;
        }
        if($this->setInstalledSchemaVersion($expected)) {
            $this->addReport(
                'data',
                'SchemaVersion',
                'fixed',
                sprintf('Schema-Version %d → %d', $installed, $expected)
            );
        }
    }

    private function processSchema($applyCreate, $applyRepair) {
        foreach($this->schema as $tableName => $columns) {
            $SQL = new SQLtable($tableName);

            if(!$SQL->exists()) {
                if($applyCreate) {
                    $result = $SQL->create();
                    if($result === true) {
                        $this->addReport('table', $tableName, 'created', 'Tabelle angelegt');
                    }
                    else {
                        $this->addReport('table', $tableName, 'error', 'Tabelle konnte nicht angelegt werden', $SQL->getLastError());
                        continue;
                    }
                }
                else {
                    $this->addReport('table', $tableName, 'missing', 'Tabelle fehlt');
                    continue;
                }
            }
            else {
                $this->addReport('table', $tableName, 'ok', 'Tabelle vorhanden');
            }

            foreach($columns as $columnName => $definition) {
                $target = $tableName.'.'.$columnName;
                if(!$SQL->columnExists($columnName)) {
                    if($applyCreate) {
                        $result = $SQL->createColumn($columnName, $definition);
                        if($result === true) {
                            $this->addReport('column', $target, 'created', 'Spalte angelegt');
                        }
                        elseif($result === -1) {
                            $this->addReport('column', $target, 'ok', 'Spalte vorhanden');
                        }
                        else {
                            $this->addReport('column', $target, 'error', 'Spalte konnte nicht angelegt werden', $SQL->getLastError());
                        }
                    }
                    else {
                        $this->addReport('column', $target, 'missing', 'Spalte fehlt');
                    }
                    continue;
                }

                $diffs = $SQL->compareColumn($columnName, $definition);
                if(empty($diffs)) {
                    $this->addReport('column', $target, 'ok', 'Spalte ok');
                    continue;
                }

                if($applyRepair) {
                    if($SQL->modifyColumn($columnName, $definition)) {
                        $newDiffs = $SQL->compareColumn($columnName, $definition);
                        if(empty($newDiffs)) {
                            $this->addReport('column', $target, 'fixed', 'Spalte angepasst', $diffs);
                        }
                        else {
                            $this->addReport('column', $target, 'mismatch', 'Abweichung nach Repair noch vorhanden', $newDiffs);
                        }
                    }
                    else {
                        $this->addReport('column', $target, 'error', 'Spalte konnte nicht angepasst werden', $SQL->getLastError());
                    }
                }
                else {
                    $this->addReport('column', $target, 'mismatch', 'Spalte weicht ab', $diffs);
                }
            }
        }
    }

    private function pruneObsoleteSchema($apply) {
        foreach($this->schema as $tableName => $columns) {
            $SQL = new SQLtable($tableName);
            if(!$SQL->exists()) {
                continue;
            }
            $defined = array_keys($columns);
            foreach($SQL->listColumns() as $columnName) {
                if(in_array($columnName, $defined, true)) {
                    continue;
                }
                $target = $tableName.'.'.$columnName;
                if(!$apply) {
                    $this->addReport('column', $target, 'obsolete', 'Spalte nicht mehr in DBconfig');
                    continue;
                }
                if($SQL->dropColumn($columnName)) {
                    $this->addReport('column', $target, 'removed', 'Veraltete Spalte entfernt');
                }
                else {
                    $this->addReport(
                        'column',
                        $target,
                        'error',
                        'Veraltete Spalte konnte nicht entfernt werden',
                        $SQL->getLastError()
                    );
                }
            }
        }
    }

    private function checkConfigDefaults($apply) {
        if(!function_exists('getConfigDefaults')) {
            require_once dirname(__DIR__).'/config/ConfigDefaults.php';
        }
        $defaults = getConfigDefaults();
        $configTable = new SQLtable('config');
        if(!$configTable->exists()) {
            $this->addReport('config', 'config', 'missing', 'config-Tabelle fehlt — Defaults übersprungen');
            return;
        }

        foreach($defaults as $item) {
            $param = $item['Parameter'];
            $sql = sprintf(
                "SELECT `Parameter` FROM `%sconfig` WHERE `Parameter` = '%s' LIMIT 1;",
                $GLOBALS['dbprefix'],
                mysqli_real_escape_string($GLOBALS['conn'], $param)
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            $row = $dbr ? mysqli_fetch_array($dbr) : null;
            $exists = $row && isset($row['Parameter']) && $row['Parameter'] === $param;

            if($exists) {
                $this->addReport('config', $param, 'ok', 'Config-Parameter vorhanden');
                continue;
            }

            if(!$apply) {
                $this->addReport('config', $param, 'missing', 'Config-Parameter fehlt');
                continue;
            }

            $insert = sprintf(
                "INSERT INTO `%sconfig` (`Parameter`, `Value`, `Type`, `Description`) VALUES ('%s', '%s', '%s', '%s');",
                $GLOBALS['dbprefix'],
                mysqli_real_escape_string($GLOBALS['conn'], $param),
                mysqli_real_escape_string($GLOBALS['conn'], (string)$item['Value']),
                mysqli_real_escape_string($GLOBALS['conn'], $item['Type']),
                mysqli_real_escape_string($GLOBALS['conn'], $item['Description'])
            );
            $ok = mysqli_query($GLOBALS['conn'], $insert);
            if($ok) {
                $this->addReport('config', $param, 'created', 'Config-Parameter eingefügt');
            }
            else {
                $this->addReport(
                    'config',
                    $param,
                    'error',
                    'Config-Parameter konnte nicht eingefügt werden',
                    mysqli_errno($GLOBALS['conn']).': '.mysqli_error($GLOBALS['conn'])
                );
            }
        }
    }

    /**
     * Config keys removed from ConfigDefaults (still present in older DBs).
     * @return string[]
     */
    public static function obsoleteConfigParameters() {
        return array(
            'jubileeBirthdayRule',
            'BeitragMindestAktivCents',
            'BeitragMindestFoerderndCents',
        );
    }

    private function pruneObsoleteConfig($apply) {
        $configTable = new SQLtable('config');
        if(!$configTable->exists()) {
            return;
        }
        foreach(self::obsoleteConfigParameters() as $param) {
            $sql = sprintf(
                "SELECT `Parameter` FROM `%sconfig` WHERE `Parameter` = '%s' LIMIT 1;",
                $GLOBALS['dbprefix'],
                mysqli_real_escape_string($GLOBALS['conn'], $param)
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            $row = $dbr ? mysqli_fetch_array($dbr) : null;
            if(!$row || !isset($row['Parameter'])) {
                continue;
            }
            if(!$apply) {
                $this->addReport('config', $param, 'obsolete', 'Config-Parameter nicht mehr in Defaults');
                continue;
            }
            $del = sprintf(
                "DELETE FROM `%sconfig` WHERE `Parameter` = '%s' LIMIT 1;",
                $GLOBALS['dbprefix'],
                mysqli_real_escape_string($GLOBALS['conn'], $param)
            );
            if(mysqli_query($GLOBALS['conn'], $del)) {
                $this->addReport('config', $param, 'removed', 'Veralteten Config-Parameter entfernt');
                if(isset($GLOBALS['optionsDB']) && is_array($GLOBALS['optionsDB'])) {
                    unset($GLOBALS['optionsDB'][$param]);
                }
            }
            else {
                $this->addReport(
                    'config',
                    $param,
                    'error',
                    'Veralteter Config-Parameter konnte nicht entfernt werden',
                    mysqli_errno($GLOBALS['conn']).': '.mysqli_error($GLOBALS['conn'])
                );
            }
        }
    }

    /**
     * v19: BeitragMindest*Cents → BeitragMindest* (€ string).
     * @param int $installedBefore
     * @param bool $apply
     */
    private function migrateBeitragMindestToEuro($installedBefore, $apply) {
        $installedBefore = (int)$installedBefore;
        if($installedBefore >= 19) {
            return;
        }
        $configTable = new SQLtable('config');
        if(!$configTable->exists()) {
            return;
        }
        $pairs = array(
            'BeitragMindestAktivCents' => array(
                'BeitragMindestAktiv',
                'Mindest-Jahresbeitrag aktives Mitglied (€)',
            ),
            'BeitragMindestFoerderndCents' => array(
                'BeitragMindestFoerdernd',
                'Mindest-Jahresbeitrag förderndes Mitglied (€)',
            ),
        );
        $prefix = $GLOBALS['dbprefix'];
        foreach($pairs as $oldParam => $info) {
            $newParam = $info[0];
            $desc = $info[1];
            $sqlNew = sprintf(
                "SELECT `Parameter` FROM `%sconfig` WHERE `Parameter` = '%s' LIMIT 1;",
                $prefix,
                mysqli_real_escape_string($GLOBALS['conn'], $newParam)
            );
            $dbrNew = mysqli_query($GLOBALS['conn'], $sqlNew);
            $rowNew = $dbrNew ? mysqli_fetch_assoc($dbrNew) : null;
            if($rowNew) {
                continue;
            }
            $sqlOld = sprintf(
                "SELECT `Value` FROM `%sconfig` WHERE `Parameter` = '%s' LIMIT 1;",
                $prefix,
                mysqli_real_escape_string($GLOBALS['conn'], $oldParam)
            );
            $dbrOld = mysqli_query($GLOBALS['conn'], $sqlOld);
            $rowOld = $dbrOld ? mysqli_fetch_assoc($dbrOld) : null;
            $euro = '20,00';
            if($rowOld && isset($rowOld['Value']) && is_numeric(trim((string)$rowOld['Value']))) {
                $euro = number_format(((int)$rowOld['Value']) / 100, 2, ',', '');
            }
            if(!$apply) {
                $this->addReport('config', $newParam, 'missing', 'Mindestbeitrag € aus '.$oldParam.' ('.$euro.')');
                continue;
            }
            $insert = sprintf(
                "INSERT INTO `%sconfig` (`Parameter`, `Value`, `Type`, `Description`) VALUES ('%s', '%s', 'string', '%s');",
                $prefix,
                mysqli_real_escape_string($GLOBALS['conn'], $newParam),
                mysqli_real_escape_string($GLOBALS['conn'], $euro),
                mysqli_real_escape_string($GLOBALS['conn'], $desc)
            );
            $ok = mysqli_query($GLOBALS['conn'], $insert);
            if($ok) {
                $this->addReport('config', $newParam, 'created', 'Mindestbeitrag € übernommen ('.$euro.')');
            }
            else {
                $this->addReport(
                    'config',
                    $newParam,
                    'error',
                    'Mindestbeitrag € konnte nicht angelegt werden',
                    mysqli_errno($GLOBALS['conn']).': '.mysqli_error($GLOBALS['conn'])
                );
            }
        }
    }

    /**
     * v17: merge Phone2 into Phone (when Phone empty), then prune drops Phone2.
     * @param int $installedBefore
     * @param bool $apply
     */
    private function migratePhone2IntoPhone($installedBefore, $apply) {
        $installedBefore = (int)$installedBefore;
        if($installedBefore >= 17) {
            return;
        }
        foreach(array('MemberProfile', 'MembershipApplication') as $short) {
            $table = new SQLtable($short);
            if(!$table->exists() || !$table->columnExists('Phone2')) {
                continue;
            }
            $target = $short.'.Phone2';
            if(!$apply) {
                $this->addReport('column', $target, 'obsolete', 'Handy → Telefon übernehmen, dann Spalte entfernen');
                continue;
            }
            $name = $GLOBALS['dbprefix'].$short;
            $sql = sprintf(
                'UPDATE `%s` SET `Phone` = `Phone2`
                 WHERE (`Phone` IS NULL OR TRIM(`Phone`) = \'\')
                   AND `Phone2` IS NOT NULL AND TRIM(`Phone2`) != \'\';',
                $name
            );
            $ok = mysqli_query($GLOBALS['conn'], $sql);
            if($ok) {
                $n = (int)mysqli_affected_rows($GLOBALS['conn']);
                $this->addReport('column', $target, 'fixed', 'Handy-Wert in Telefon übernommen ('.$n.' Zeilen)');
            }
            else {
                $this->addReport(
                    'column',
                    $target,
                    'error',
                    'Handy konnte nicht in Telefon übernommen werden',
                    mysqli_errno($GLOBALS['conn']).': '.mysqli_error($GLOBALS['conn'])
                );
            }
        }
    }

    /**
     * One-shot data fixes tied to schema bumps (after columns exist).
     * @param int $installedBefore
     * @param bool $apply
     */
    private function migratePermissionGrants($installedBefore, $apply) {
        $installedBefore = (int)$installedBefore;
        $table = new SQLtable('Permissions');
        if(!$table->exists()) {
            return;
        }

        if($installedBefore < 12 && $table->columnExists('perm_showJubilees')) {
            if(!$apply) {
                $this->addReport('config', 'perm_showJubilees', 'missing', 'Bestehende Nutzer-Leser erhalten Jubiläen-Recht bei Repair');
            }
            else {
                $sql = sprintf(
                    'UPDATE `%s` SET `perm_showJubilees` = 1
                     WHERE `perm_showJubilees` = 0 AND (`perm_showUsers` = 1 OR `perm_editUsers` = 1);',
                    $GLOBALS['dbprefix'].'Permissions'
                );
                $ok = mysqli_query($GLOBALS['conn'], $sql);
                if($ok) {
                    $n = (int)mysqli_affected_rows($GLOBALS['conn']);
                    $this->addReport('config', 'perm_showJubilees', 'fixed', 'Jubiläen-Recht an '.$n.' Nutzer mit Nutzer-Lesen/Schreiben vergeben');
                    if(class_exists('Permissions')) {
                        Permissions::clearCache();
                    }
                }
                else {
                    $this->addReport(
                        'config',
                        'perm_showJubilees',
                        'error',
                        'Jubiläen-Recht konnte nicht migriert werden',
                        mysqli_errno($GLOBALS['conn']).': '.mysqli_error($GLOBALS['conn'])
                    );
                }
            }
        }

        if($installedBefore < 16 && $table->columnExists('perm_showLog')) {
            if(!$apply) {
                $this->addReport('config', 'perm_showLog', 'missing', 'Rechte-Admins und Melde-Admins erhalten Log-Recht bei Repair');
                return;
            }
            $permTable = $GLOBALS['dbprefix'].'Permissions';
            $userTable = (isset($GLOBALS['identityPrefix']) ? $GLOBALS['identityPrefix'] : 'meldeliste_').'User';
            $sql = sprintf(
                'UPDATE `%s` p
                 LEFT JOIN `%s` u ON u.`Index` = p.`User`
                 SET p.`perm_showLog` = 1
                 WHERE p.`perm_showLog` = 0
                   AND (p.`perm_editPermissions` = 1 OR IFNULL(u.`Admin`, 0) = 1);',
                $permTable,
                $userTable
            );
            $ok = mysqli_query($GLOBALS['conn'], $sql);
            if($ok) {
                $n = (int)mysqli_affected_rows($GLOBALS['conn']);
                // Also create rows for Melde Admins without a mit_Permissions row yet
                $sqlIns = sprintf(
                    'INSERT INTO `%s` (`User`, `perm_showUsers`, `perm_editUsers`, `perm_showJubilees`, `perm_showLog`, `perm_editPermissions`)
                     SELECT u.`Index`, 0, 0, 0, 1, 0
                     FROM `%s` u
                     LEFT JOIN `%s` p ON p.`User` = u.`Index`
                     WHERE u.`Admin` = 1 AND IFNULL(u.`Deleted`, 0) != 1 AND p.`Index` IS NULL;',
                    $permTable,
                    $userTable,
                    $permTable
                );
                $okIns = mysqli_query($GLOBALS['conn'], $sqlIns);
                $nIns = $okIns ? (int)mysqli_affected_rows($GLOBALS['conn']) : 0;
                $this->addReport(
                    'config',
                    'perm_showLog',
                    'fixed',
                    'Log-Recht an '.$n.' bestehende Rechte-Zeilen und '.$nIns.' neue Admin-Zeilen vergeben'
                );
                if(class_exists('Permissions')) {
                    Permissions::clearCache();
                }
            }
            else {
                $this->addReport(
                    'config',
                    'perm_showLog',
                    'error',
                    'Log-Recht konnte nicht migriert werden',
                    mysqli_errno($GLOBALS['conn']).': '.mysqli_error($GLOBALS['conn'])
                );
            }
        }
    }

    private function ensureAllTablesUtf8mb4() {
        if(!isset($GLOBALS['conn']) || !isset($GLOBALS['dbprefix'])) {
            return;
        }
        foreach(array_keys($this->schema) as $short) {
            $table = new SQLtable($short);
            if(!$table->exists()) {
                continue;
            }
            $name = $GLOBALS['dbprefix'].$short;
            $check = mysqli_query(
                $GLOBALS['conn'],
                "SELECT `TABLE_COLLATION` AS `c` FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '"
                .mysqli_real_escape_string($GLOBALS['conn'], $name)."' LIMIT 1"
            );
            $row = $check ? mysqli_fetch_assoc($check) : null;
            $collation = $row && isset($row['c']) ? (string)$row['c'] : '';
            if($collation !== '' && stripos($collation, 'utf8mb4') === 0) {
                $this->addReport('table', $short, 'ok', 'utf8mb4');
                continue;
            }
            $ok = mysqli_query(
                $GLOBALS['conn'],
                'ALTER TABLE `'.str_replace('`', '``', $name).'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
            if($ok) {
                $this->addReport('table', $short, 'fixed', 'Nach utf8mb4 konvertiert');
            }
            else {
                $this->addReport(
                    'table',
                    $short,
                    'error',
                    'utf8mb4-Konvertierung fehlgeschlagen',
                    mysqli_errno($GLOBALS['conn']).': '.mysqli_error($GLOBALS['conn'])
                );
            }
        }
    }

    public function formatReportText() {
        $lines = array();
        foreach($this->report as $entry) {
            $line = strtoupper($entry['status'])."\t[".$entry['level']."]\t".$entry['target'];
            if($entry['message']) $line .= "\t".$entry['message'];
            if($entry['detail'] && is_string($entry['detail'])) $line .= "\t".$entry['detail'];
            if($entry['detail'] && is_array($entry['detail'])) {
                $line .= "\t".json_encode($entry['detail']);
            }
            $lines[] = $line;
        }
        return implode("\n", $lines)."\n";
    }
}
?>
