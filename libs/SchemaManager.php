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
        return $this->report;
    }

    public function create() {
        $this->report = array();
        $this->processSchema(true, false);
        $this->pruneObsoleteSchema(true);
        $this->checkConfigDefaults(true);
        $this->ensureAllTablesUtf8mb4();
        $this->finalizeSchemaVersion();
        return $this->report;
    }

    public function repair() {
        $this->report = array();
        $this->processSchema(true, true);
        $this->pruneObsoleteSchema(true);
        $this->checkConfigDefaults(true);
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
