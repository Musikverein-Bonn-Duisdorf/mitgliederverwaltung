<?php
/**
 * Migrate legacy Membership Type/Status (+ MembershipTypeChange) into
 * MembershipPeriod + MembershipTypePeriod, then drop obsolete columns/table via schema repair.
 *
 * Run AFTER SchemaManager create/repair has added the new tables (v6), BEFORE or with
 * repair that removes Type/Status from Membership.
 *
 * Usage: php scripts/migrateFlagsToPeriods.php [--dry-run]
 */
$root = dirname(__DIR__);
chdir($root);
require_once $root.'/common/config.php';
mysqli_set_charset($GLOBALS['conn'], 'utf8mb4');
mysqli_select_db($GLOBALS['conn'], $sql['database']);
require_once $root.'/common/include.php';

$dryRun = in_array('--dry-run', $argv, true);
$prefix = $GLOBALS['dbprefix'];
$conn = $GLOBALS['conn'];

function columnExists($table, $column) {
    $sql = sprintf(
        'SHOW COLUMNS FROM `%s` LIKE "%s";',
        mysqli_real_escape_string($GLOBALS['conn'], $table),
        mysqli_real_escape_string($GLOBALS['conn'], $column)
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    return ($dbr && mysqli_fetch_row($dbr)) ? true : false;
}

function tableExists($table) {
    $dbr = mysqli_query($GLOBALS['conn'], sprintf("SHOW TABLES LIKE '%s'", mysqli_real_escape_string($GLOBALS['conn'], $table)));
    return ($dbr && mysqli_fetch_row($dbr)) ? true : false;
}

$memTable = $prefix.'Membership';
$perTable = $prefix.'MembershipPeriod';
$tpTable = $prefix.'MembershipTypePeriod';
$tcTable = $prefix.'MembershipTypeChange';

if(!tableExists($memTable) || !tableExists($perTable) || !tableExists($tpTable)) {
    fwrite(STDERR, "Missing tables. Run SchemaManager create/repair for schema v6 first.\n");
    exit(1);
}

$hasType = columnExists($memTable, 'Type');
$hasStatus = columnExists($memTable, 'Status');
$createdTenure = 0;
$createdType = 0;
$skipped = 0;

$dbr = mysqli_query($conn, sprintf('SELECT * FROM `%s` ORDER BY `Index`;', $memTable));
if(!$dbr) {
    fwrite(STDERR, "Cannot read Membership: ".mysqli_error($conn)."\n");
    exit(1);
}

while($row = mysqli_fetch_assoc($dbr)) {
    $mid = (int)$row['Index'];
    $userId = (int)$row['User'];
    $type = $hasType && isset($row['Type']) ? strtolower(trim((string)$row['Type'])) : 'aktiv';
    if(!in_array($type, array('aktiv', 'foerdernd'), true)) {
        $type = 'aktiv';
    }
    $status = $hasStatus && isset($row['Status']) ? strtolower(trim((string)$row['Status'])) : 'active';
    $dateFrom = '2000-01-01';

    $existingPeriods = MembershipPeriod::listForMembership($mid);
    $existingTypes = MembershipTypePeriod::listForMembership($mid);

    if(count($existingPeriods) === 0) {
        if($status === 'ended') {
            // Closed tenure with unknown dates — skip open period; still record type history if empty
            $skipped++;
            echo "Membership #{$mid} (User {$userId}): Status=ended, no tenure created (add manually).\n";
        }
        else {
            if(!$dryRun) {
                MembershipPeriod::openTenure($mid, $dateFrom, 'Migration aus Status/Type');
            }
            $createdTenure++;
            echo "Membership #{$mid}: tenure from {$dateFrom}\n";
        }
    }

    if(count($existingTypes) === 0 && $status !== 'ended') {
        // Prefer MembershipTypeChange chain if present
        $changes = array();
        if(tableExists($tcTable)) {
            $cdb = mysqli_query($conn, sprintf(
                'SELECT * FROM `%s` WHERE `Membership` = %d ORDER BY `ChangedAt` ASC, `Index` ASC;',
                $tcTable,
                $mid
            ));
            if($cdb) {
                while($c = mysqli_fetch_assoc($cdb)) {
                    $changes[] = $c;
                }
            }
        }
        if(count($changes) > 0) {
            $cursorFrom = $dateFrom;
            $firstFrom = isset($changes[0]['FromType']) ? strtolower(trim((string)$changes[0]['FromType'])) : $type;
            if(!in_array($firstFrom, array('aktiv', 'foerdernd'), true)) {
                $firstFrom = $type;
            }
            // Initial segment until first change
            $firstChangeDate = isset($changes[0]['ChangedAt']) ? substr((string)$changes[0]['ChangedAt'], 0, 10) : $dateFrom;
            $segEnd = date('Y-m-d', strtotime($firstChangeDate.' -1 day'));
            if(!$dryRun) {
                $seg = new MembershipTypePeriod();
                $seg->Membership = $mid;
                $seg->Type = $firstFrom;
                $seg->DateFrom = $cursorFrom;
                $seg->DateTo = ($segEnd >= $cursorFrom) ? $segEnd : $cursorFrom;
                $seg->Note = 'Migration TypeChange';
                $seg->save();
            }
            $createdType++;
            foreach($changes as $i => $c) {
                $to = strtolower(trim((string)$c['ToType']));
                if(!in_array($to, array('aktiv', 'foerdernd'), true)) {
                    continue;
                }
                $fromDate = substr((string)$c['ChangedAt'], 0, 10);
                $toDate = null;
                if(isset($changes[$i + 1])) {
                    $next = substr((string)$changes[$i + 1]['ChangedAt'], 0, 10);
                    $toDate = date('Y-m-d', strtotime($next.' -1 day'));
                }
                if(!$dryRun) {
                    $seg = new MembershipTypePeriod();
                    $seg->Membership = $mid;
                    $seg->Type = $to;
                    $seg->DateFrom = $fromDate;
                    $seg->DateTo = $toDate;
                    $seg->Note = isset($c['Note']) ? (string)$c['Note'] : 'Migration TypeChange';
                    $seg->save();
                }
                $createdType++;
            }
            echo "Membership #{$mid}: type periods from TypeChange (".count($changes)." events)\n";
        }
        else {
            if(!$dryRun) {
                MembershipTypePeriod::openType($mid, $type, $dateFrom, 'Migration aus Type='.$type);
            }
            $createdType++;
            echo "Membership #{$mid}: type {$type} from {$dateFrom}\n";
        }
    }
}

echo $dryRun
    ? "Dry-run: would create ~{$createdTenure} tenures, ~{$createdType} type periods ({$skipped} ended skipped).\n"
    : "Done: created {$createdTenure} tenures, {$createdType} type period segments ({$skipped} ended skipped).\n";

if(!$dryRun && tableExists($tcTable)) {
    mysqli_query($conn, sprintf('DROP TABLE IF EXISTS `%s`;', $tcTable));
    echo "Dropped {$tcTable}.\n";
}

echo "Next: run SchemaManager repair to drop Membership.Type/Status if still present.\n";
exit(0);
?>
