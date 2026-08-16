<?php
/**
 * Fill local MIT tables with demo data on existing Melde users.
 * Preserves open memberships; creates a few new Fördernde.
 *
 * Usage: php scripts/seedMitTestData.php
 */
declare(strict_types=1);

if(php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once __DIR__.'/../libs/sessionBootstrap.php';
mitConfigureSession();
$_SERVER['SCRIPT_NAME'] = 'scripts/seedMitTestData.php';
require_once __DIR__.'/../common/include.php';

$NOTE = 'seed-mit-2026';
$stats = array(
    'profile' => 0,
    'aktiv' => 0,
    'foerdernd' => 0,
    'exited' => 0,
    'guest_profile' => 0,
    'sepa' => 0,
    'created_users' => 0,
    'skipped' => 0,
);

function seedLog($msg) {
    echo $msg."\n";
}

function seedProfile($userId, array $data, &$stats) {
    $p = new MemberProfile();
    $p->load_or_create($userId);
    $empty = ((int)$p->Index < 1)
        || ($p->Birthday === null && $p->Street === null && $p->Phone === null);
    if(!$empty && (int)$p->Index > 0) {
        // Keep existing rich profiles (e.g. mschedler); fill only missing fields.
        $changed = false;
        foreach($data as $k => $v) {
            if(($p->$k === null || $p->$k === '') && $v !== null && $v !== '') {
                $p->$k = $v;
                $changed = true;
            }
        }
        if($changed) {
            $p->save();
            $stats['profile']++;
        }
        return $p;
    }
    foreach($data as $k => $v) {
        $p->$k = $v;
    }
    if($p->save()) {
        $stats['profile']++;
    }
    return $p;
}

function seedEnsureOpenMember($userId, $type, $dateFrom, $feeCents, $note, &$stats) {
    if(MembershipPeriod::userIsMemberOn($userId)) {
        $stats['skipped']++;
        return null;
    }
    $mem = new Membership();
    if(!$mem->ensure_for_user($userId)) {
        seedLog("FAIL membership shell user=$userId");
        return null;
    }
    $mem->AnnualFeeCents = (int)$feeCents;
    $mem->save();
    $mid = (int)$mem->Index;
    if(!MembershipPeriod::openTenure($mid, $dateFrom, $note)) {
        seedLog("FAIL tenure user=$userId");
        return null;
    }
    if(!MembershipTypePeriod::openType($mid, $type, $dateFrom, $note)) {
        seedLog("FAIL type user=$userId");
        return null;
    }
    if($type === 'foerdernd') {
        $stats['foerdernd']++;
    }
    else {
        $stats['aktiv']++;
    }
    return $mem;
}

function seedExitedMember($userId, $dateFrom, $dateTo, $note, &$stats) {
    if(MembershipPeriod::userIsMemberOn($userId)) {
        $stats['skipped']++;
        return;
    }
    $mem = new Membership();
    if(!$mem->ensure_for_user($userId)) {
        return;
    }
    $mid = (int)$mem->Index;
    // Already has any period? skip
    if(count(MembershipPeriod::listForMembership($mid)) > 0) {
        $stats['skipped']++;
        return;
    }
    MembershipPeriod::openTenure($mid, $dateFrom, $note);
    MembershipTypePeriod::openType($mid, 'aktiv', $dateFrom, $note);
    MembershipPeriod::closeOpenTenure($mid, $dateTo, 'austritt', $note);
    MembershipTypePeriod::closeOpenType($mid, $dateTo);
    $stats['exited']++;
}

function seedSepa($userId, $iban, $from, &$stats) {
    foreach(SepaMandate::listForUser($userId) as $existing) {
        if((int)$existing->Active === 1) {
            return;
        }
    }
    $ymd = preg_replace('/\D/', '', substr((string)$from, 0, 10));
    if($ymd === '' || strlen($ymd) !== 8) {
        $ymd = date('Ymd');
    }
    $m = new SepaMandate();
    $m->User = $userId;
    $m->IbanEnc = $iban;
    $m->MandateRef = 'MVD-SEPA-TMP-'.$userId; // replaced after insert with Index
    $m->ValidFrom = $from;
    $m->ValidTo = null;
    $m->Active = 1;
    if($m->save()) {
        $m->MandateRef = 'MVD-SEPA-'.(int)$m->Index.'-'.$ymd;
        $m->save();
        $stats['sepa']++;
    }
}

$streets = array(
    'Hauptstraße', 'Bahnhofstraße', 'Mühlenweg', 'Kirchplatz', 'Gartenstraße',
    'Am Sportplatz', 'Lindenallee', 'Rheinufer', 'Beethovenstraße', 'Dürener Straße',
);
$cities = array(
    array('53111', 'Bonn'),
    array('53113', 'Bonn'),
    array('53115', 'Bonn'),
    array('53117', 'Bonn'),
    array('53119', 'Bonn'),
    array('53121', 'Bonn'),
    array('53123', 'Bonn'),
    array('53125', 'Bonn'),
    array('53127', 'Bonn'),
    array('53332', 'Bornheim'),
);

function seedDemoAddress($userId) {
    global $streets, $cities;
    $i = (int)$userId;
    $city = $cities[$i % count($cities)];
    $y = 1965 + ($i % 35);
    $m = 1 + ($i % 12);
    $d = 1 + ($i % 28);
    return array(
        'Birthday' => sprintf('%04d-%02d-%02d', $y, $m, $d),
        'Phone' => sprintf('0228%07d', 1000000 + ($i * 17) % 9000000),
        'Street' => $streets[$i % count($streets)].' '.((string)(1 + ($i % 80))),
        'Zip' => $city[0],
        'City' => $city[1],
        'Country' => 'DE',
        'AccountHolder' => null,
    );
}

// --- Existing real people → aktiv (except keep non-members among orchestra bulk) ---
$aktivExisting = array(3, 4, 5, 7, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20);
// Guests / non-members with profile only
$guestProfiles = array(6, 26, 27, 28, 29, 30, 31, 32, 33, 35, 36, 37);
// Former members (exited)
$exited = array(21, 22, 23, 24, 25);
// Pure non-members (no MIT profile): rest of orchestra + MVD admin + 59, 60

seedLog('Seeding MIT test data…');

foreach($aktivExisting as $uid) {
    $u = new IdentityUser();
    if(!$u->load_by_id($uid) || (int)$u->Deleted === 1) {
        continue;
    }
    $addr = seedDemoAddress($uid);
    $addr['AccountHolder'] = trim($u->Vorname.' '.$u->Nachname);
    seedProfile($uid, $addr, $stats);
    $entry = sprintf('20%02d-%02d-01', 8 + ($uid % 15), 1 + ($uid % 12));
    $fee = 2000 + (($uid % 5) * 500);
    $mem = seedEnsureOpenMember($uid, 'aktiv', $entry, $fee, $NOTE, $stats);
    if($mem && ($uid % 3 === 0)) {
        seedSepa(
            $uid,
            sprintf('DE%02d%d%010d', 10 + ($uid % 80), 37040044, 1000000000 + $uid),
            $entry,
            $stats
        );
        $p = new MemberProfile();
        if($p->load_by_user($uid) && ($p->AccountHolder === null || $p->AccountHolder === '')) {
            $p->AccountHolder = trim($u->Vorname.' '.$u->Nachname);
            $p->save();
        }
    }
}

foreach($guestProfiles as $uid) {
    $u = new IdentityUser();
    if(!$u->load_by_id($uid) || (int)$u->Deleted === 1) {
        continue;
    }
    if(MembershipPeriod::userIsMemberOn($uid)) {
        $stats['skipped']++;
        continue;
    }
    // Non-members: no Geburtstag / Adresse / Bank — only empty profile shell if needed.
    $p = new MemberProfile();
    if(!$p->load_by_user($uid)) {
        $p->User = $uid;
        if($p->save()) {
            $stats['guest_profile']++;
            $stats['profile']++;
        }
    }
    else {
        $stats['skipped']++;
    }
}

foreach($exited as $i => $uid) {
    $u = new IdentityUser();
    if(!$u->load_by_id($uid) || (int)$u->Deleted === 1) {
        continue;
    }
    // Tenure history only — no Stammdaten/Bank after Austritt.
    $from = sprintf('201%1d-%02d-01', 5 + ($i % 4), 3 + $i);
    $to = sprintf('202%1d-%02d-28', 2 + ($i % 3), 6 + $i);
    seedExitedMember($uid, $from, $to, $NOTE.' austritt', $stats);
    MembershipPeriod::wipeBankDataForUser($uid);
    $p = new MemberProfile();
    $p->load_or_create($uid);
    foreach(array('Birthday', 'Street', 'Zip', 'City', 'Country', 'AccountHolder') as $f) {
        $p->$f = null;
    }
    $p->save();
}

// Enrich existing open members (2, 141) with SEPA if missing
foreach(array(2, 141) as $uid) {
    if(!MembershipPeriod::userIsMemberOn($uid)) {
        continue;
    }
    $u = new IdentityUser();
    $u->load_by_id($uid);
    seedProfile($uid, array(
        'AccountHolder' => trim($u->Vorname.' '.$u->Nachname),
    ), $stats);
    seedSepa(
        $uid,
        $uid === 2 ? 'DE89370400440532013000' : 'DE12500105170648489890',
        '2020-01-01',
        $stats
    );
}

// --- New Fördernde (few) ---
$foerdernde = array(
    array(
        'Vorname' => 'Anna',
        'Nachname' => 'Förder',
        'Email' => 'anna.foerder@example.test',
        'login' => 'foerder_anna',
        'Birthday' => '1978-05-12',
        'Street' => 'Förderweg 3',
        'Zip' => '53129',
        'City' => 'Bonn',
        'entry' => '2019-03-01',
        'fee' => 5000,
    ),
    array(
        'Vorname' => 'Bernd',
        'Nachname' => 'Gönner',
        'Email' => 'bernd.goenner@example.test',
        'login' => 'foerder_bernd',
        'Birthday' => '1962-11-03',
        'Street' => 'Patronatsstraße 12',
        'Zip' => '53113',
        'City' => 'Bonn',
        'entry' => '2015-06-15',
        'fee' => 10000,
    ),
    array(
        'Vorname' => 'Carla',
        'Nachname' => 'Mäzen',
        'Email' => 'carla.maezen@example.test',
        'login' => 'foerder_carla',
        'Birthday' => '1985-08-22',
        'Street' => 'Kunstgasse 7',
        'Zip' => '53111',
        'City' => 'Bonn',
        'entry' => '2023-01-10',
        'fee' => 3500,
    ),
);

foreach($foerdernde as $spec) {
    $existing = new IdentityUser();
    if($existing->load_by_login($spec['login'])) {
        $u = $existing;
        seedLog('Reuse Fördernde login='.$spec['login'].' id='.$u->Index);
    }
    else {
        $u = IdentityUser::createPerson(array(
            'Vorname' => $spec['Vorname'],
            'Nachname' => $spec['Nachname'],
            'Email' => $spec['Email'],
            'login' => $spec['login'],
        ));
        if(!$u) {
            seedLog('FAIL create '.$spec['login']);
            continue;
        }
        $stats['created_users']++;
        seedLog('Created Fördernde '.$spec['Vorname'].' '.$spec['Nachname'].' id='.$u->Index);
    }
    $uid = (int)$u->Index;
    seedProfile($uid, array(
        'Birthday' => $spec['Birthday'],
        'Phone' => '0228'.sprintf('%07d', 5000000 + $uid),
        'Street' => $spec['Street'],
        'Zip' => $spec['Zip'],
        'City' => $spec['City'],
        'Country' => 'DE',
        'AccountHolder' => $spec['Vorname'].' '.$spec['Nachname'],
    ), $stats);
    seedEnsureOpenMember($uid, 'foerdernd', $spec['entry'], $spec['fee'], $NOTE.' foerdernd', $stats);
    seedSepa(
        $uid,
        sprintf('DE%02d37040044000000%04d', 20 + ($uid % 50), $uid % 10000),
        $spec['entry'],
        $stats
    );
}

seedLog('--- done ---');
foreach($stats as $k => $v) {
    seedLog(sprintf('  %-16s %d', $k, $v));
}

// Summary counts
$p = $GLOBALS['dbprefix'];
$today = date('Y-m-d');
$q = function ($sql) {
    $r = mysqli_query($GLOBALS['conn'], $sql);
    $row = $r ? mysqli_fetch_assoc($r) : null;
    return $row ? (int)$row['c'] : 0;
};
seedLog('DB snapshot:');
seedLog('  profiles:     '.$q("SELECT COUNT(*) c FROM `{$p}MemberProfile`"));
seedLog('  open members: '.$q(
    "SELECT COUNT(DISTINCT m.User) c FROM `{$p}Membership` m
     INNER JOIN `{$p}MembershipPeriod` per ON per.Membership=m.`Index`
     WHERE per.DateFrom <= '$today' AND (per.DateTo IS NULL OR per.DateTo >= '$today')"
));
seedLog('  open aktiv:   '.$q(
    "SELECT COUNT(DISTINCT m.User) c FROM `{$p}Membership` m
     INNER JOIN `{$p}MembershipTypePeriod` t ON t.Membership=m.`Index`
     WHERE t.Type='aktiv' AND t.DateFrom <= '$today' AND (t.DateTo IS NULL OR t.DateTo >= '$today')"
));
seedLog('  open foerder: '.$q(
    "SELECT COUNT(DISTINCT m.User) c FROM `{$p}Membership` m
     INNER JOIN `{$p}MembershipTypePeriod` t ON t.Membership=m.`Index`
     WHERE t.Type='foerdernd' AND t.DateFrom <= '$today' AND (t.DateTo IS NULL OR t.DateTo >= '$today')"
));
seedLog('  SEPA active:  '.$q("SELECT COUNT(*) c FROM `{$p}SepaMandate` WHERE Active=1"));
