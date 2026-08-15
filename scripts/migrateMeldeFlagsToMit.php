<?php
/**
 * Migrate Melde User.Birthday → mit_MemberProfile and Mitglied=1 → period membership.
 * Safe to re-run. Prefer this before Melde schema drops Birthday/Mitglied.
 *
 * Usage: php scripts/migrateMeldeFlagsToMit.php [--dry-run]
 */
$root = dirname(__DIR__);
chdir($root);
require_once $root.'/common/config.php';
mysqli_set_charset($GLOBALS['conn'], 'utf8mb4');
mysqli_select_db($GLOBALS['conn'], $sql['database']);
require_once $root.'/common/include.php';

$dryRun = in_array('--dry-run', $argv, true);
$identity = IdentityUser::identityPrefix();
$userTable = $identity.'User';
$conn = $GLOBALS['conn'];

function meldeColExists($table, $col) {
    $dbr = mysqli_query($GLOBALS['conn'], sprintf(
        'SHOW COLUMNS FROM `%s` LIKE "%s";',
        mysqli_real_escape_string($GLOBALS['conn'], $table),
        mysqli_real_escape_string($GLOBALS['conn'], $col)
    ));
    return ($dbr && mysqli_fetch_row($dbr)) ? true : false;
}

$hasBirthday = meldeColExists($userTable, 'Birthday');
$hasMitglied = meldeColExists($userTable, 'Mitglied');
$birthdayMoved = 0;
$membershipCreated = 0;

$cols = array('`Index`');
if($hasBirthday) {
    $cols[] = '`Birthday`';
}
if($hasMitglied) {
    $cols[] = '`Mitglied`';
}
$sql = sprintf('SELECT %s FROM `%s` WHERE `Deleted` != 1;', implode(', ', $cols), $userTable);
$dbr = mysqli_query($conn, $sql);
if(!$dbr) {
    fwrite(STDERR, mysqli_error($conn)."\n");
    exit(1);
}

while($row = mysqli_fetch_assoc($dbr)) {
    $uid = (int)$row['Index'];
    if($hasBirthday && !empty($row['Birthday']) && $row['Birthday'] !== '0000-00-00') {
        $profile = new MemberProfile();
        $profile->load_or_create($uid);
        if($profile->Birthday === null || $profile->Birthday === '' || $profile->Birthday === '0000-00-00') {
            if(!$dryRun) {
                $profile->Birthday = $row['Birthday'];
                $profile->save();
            }
            $birthdayMoved++;
            echo "User {$uid}: Birthday → MemberProfile\n";
        }
    }
    if($hasMitglied && !empty($row['Mitglied'])) {
        if(MembershipPeriod::userIsMemberOn($uid)) {
            continue;
        }
        if(!$dryRun) {
            $mem = new Membership();
            if(!$mem->ensure_for_user($uid)) {
                echo "User {$uid}: failed Membership shell\n";
                continue;
            }
            if(!MembershipPeriod::openTenure((int)$mem->Index, '2000-01-01', 'Migration Melde Mitglied=1')) {
                echo "User {$uid}: failed tenure\n";
                continue;
            }
            MembershipTypePeriod::openType((int)$mem->Index, 'aktiv', '2000-01-01', 'Migration Melde Mitglied=1');
        }
        $membershipCreated++;
        echo "User {$uid}: Mitglied → aktiv tenure\n";
    }
}

echo $dryRun
    ? "Dry-run: would move {$birthdayMoved} birthdays, create {$membershipCreated} memberships.\n"
    : "Done: birthdays {$birthdayMoved}, memberships {$membershipCreated}.\n";
exit(0);
?>
