<?php
/**
 * Computed jubilees: round birthdays + membership anniversaries.
 */
class JubileeCalendar
{
    /**
     * Birthday milestone ages from config (fixed list + step after max).
     * @return int[]
     */
    public static function birthdayAges() {
        return self::milestonesFromConfig(
            'jubileeBirthdayAges',
            'jubileeBirthdayStepAfter',
            array(10, 20, 30, 40, 50, 60, 70),
            5,
            120
        );
    }

    /**
     * Membership jubilee years from config.
     * @return int[]
     */
    public static function membershipYears() {
        return self::milestonesFromConfig(
            'jubileeMembershipYears',
            'jubileeMembershipStepAfter',
            array(20, 25, 40, 45, 50),
            5,
            120
        );
    }

    /**
     * Fixed comma-separated milestones + continuing step after the largest fixed value.
     * @param string $listKey
     * @param string $stepKey
     * @param int[] $defaultFixed
     * @param int $defaultStep
     * @param int $max
     * @return int[]
     */
    public static function milestonesFromConfig($listKey, $stepKey, array $defaultFixed, $defaultStep, $max = 120) {
        $fixed = $defaultFixed;
        if(isset($GLOBALS['optionsDB'][$listKey])) {
            $raw = trim((string)$GLOBALS['optionsDB'][$listKey]);
            if($raw !== '') {
                $parsed = array();
                foreach(preg_split('/[\s,;]+/', $raw) as $p) {
                    $n = (int)$p;
                    if($n > 0) {
                        $parsed[$n] = $n;
                    }
                }
                if($parsed) {
                    $fixed = array_values($parsed);
                    sort($fixed, SORT_NUMERIC);
                }
            }
        }
        $step = max(1, (int)$defaultStep);
        if(isset($GLOBALS['optionsDB'][$stepKey])) {
            $step = max(1, (int)$GLOBALS['optionsDB'][$stepKey]);
        }
        $from = $fixed ? max($fixed) : 0;
        $out = array();
        foreach($fixed as $y) {
            $out[$y] = $y;
        }
        if($from > 0) {
            for($y = $from + $step; $y <= (int)$max; $y += $step) {
                $out[$y] = $y;
            }
        }
        ksort($out);
        return array_values($out);
    }

    /**
     * Next jubilees for one user: the next event per category (Geburtstag / Mitgliedschaft),
     * looking ahead up to $horizonYears so distant milestones still appear.
     * @return array<int,array{kind:string,label:string,milestone:int,date:string}>
     */
    public static function nextForUser($userId, $fromDate = null, $perKind = 1, $horizonYears = 80) {
        return self::pickPerKind($userId, $fromDate, $perKind, $horizonYears, false);
    }

    /**
     * Most recent past jubilees per category (Geburtstag / Mitgliedschaft).
     * @return array<int,array{kind:string,label:string,milestone:int,date:string}>
     */
    public static function pastForUser($userId, $fromDate = null, $perKind = 1, $horizonYears = 80) {
        return self::pickPerKind($userId, $fromDate, $perKind, $horizonYears, true);
    }

    /**
     * @return array<int,array{kind:string,label:string,milestone:int,date:string}>
     */
    protected static function pickPerKind($userId, $fromDate, $perKind, $horizonYears, $past) {
        $userId = (int)$userId;
        $fromDate = MembershipPeriod::normalizeDate($fromDate);
        $perKind = max(1, (int)$perKind);
        $horizonYears = max(1, (int)$horizonYears);
        if($past) {
            $start = date('Y-m-d', strtotime($fromDate.' -'.$horizonYears.' years'));
            // Yesterday so "today" stays in the upcoming list.
            $end = date('Y-m-d', strtotime($fromDate.' -1 day'));
            if($end < $start) {
                return array();
            }
            $events = self::eventsForUserInRange($userId, $start, $end);
            usort($events, function ($a, $b) {
                return strcmp($b['date'], $a['date']);
            });
        }
        else {
            $until = date('Y-m-d', strtotime($fromDate.' +'.$horizonYears.' years'));
            $events = self::eventsForUserInRange($userId, $fromDate, $until);
            usort($events, function ($a, $b) {
                return strcmp($a['date'], $b['date']);
            });
        }
        $picked = array('birthday' => 0, 'membership' => 0);
        $out = array();
        foreach($events as $ev) {
            $kind = isset($ev['kind']) ? (string)$ev['kind'] : '';
            if(!isset($picked[$kind]) || $picked[$kind] >= $perKind) {
                continue;
            }
            $out[] = $ev;
            $picked[$kind]++;
            if($picked['birthday'] >= $perKind && $picked['membership'] >= $perKind) {
                break;
            }
        }
        usort($out, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        return $out;
    }

    /**
     * @return array<int,array{kind:string,label:string,milestone:int,date:string,userId:int,name:string}>
     */
    public static function eventsForMonth($year, $month) {
        $year = (int)$year;
        $month = (int)$month;
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
        return self::eventsInRange($start, $end);
    }

    /**
     * @return array<int,array{kind:string,label:string,milestone:int,date:string,userId:int,name:string}>
     */
    public static function eventsForYear($year) {
        $year = (int)$year;
        return self::eventsInRange(sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year));
    }

    /**
     * @return array<int,array{kind:string,label:string,milestone:int,date:string,userId:int,name:string}>
     */
    public static function eventsInRange($start, $end) {
        $out = array();
        $rows = IdentityUser::listHub('all', 5000);
        foreach($rows as $row) {
            /** @var IdentityUser $u */
            $u = $row['user'];
            $uid = (int)$u->Index;
            foreach(self::eventsForUserInRange($uid, $start, $end) as $ev) {
                $ev['userId'] = $uid;
                $ev['name'] = $u->getName();
                $ev['sortName'] = $u->getSortName();
                $out[] = $ev;
            }
        }
        usort($out, function ($a, $b) {
            $c = strcmp($a['date'], $b['date']);
            if($c !== 0) {
                return $c;
            }
            return strcmp($a['sortName'], $b['sortName']);
        });
        return $out;
    }

    /**
     * @return array<int,array{kind:string,label:string,milestone:int,date:string}>
     */
    public static function eventsForUserInRange($userId, $start, $end) {
        $userId = (int)$userId;
        $start = MembershipPeriod::normalizeDate($start);
        $end = MembershipPeriod::normalizeDate($end);
        $out = array();

        $profile = new MemberProfile();
        $profile->load_by_user($userId);
        $bday = trim((string)$profile->Birthday);
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $bday)) {
            foreach(self::birthdayAges() as $age) {
                $date = self::anniversaryOnYear($bday, (int)substr($bday, 0, 4) + $age);
                if($date !== null && $date >= $start && $date <= $end) {
                    $out[] = array(
                        'kind' => 'birthday',
                        'label' => 'Geburtstag',
                        'milestone' => $age,
                        'date' => $date,
                    );
                }
            }
        }

        $mem = new Membership();
        if($mem->load_by_user($userId)) {
            $open = MembershipPeriod::openForMembership((int)$mem->Index);
            if($open && $open->DateFrom) {
                $entry = substr((string)$open->DateFrom, 0, 10);
                if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry)) {
                    $entryYear = (int)substr($entry, 0, 4);
                    foreach(self::membershipYears() as $years) {
                        $date = self::anniversaryOnYear($entry, $entryYear + $years);
                        if($date !== null && $date >= $start && $date <= $end) {
                            $out[] = array(
                                'kind' => 'membership',
                                'label' => 'Mitgliedschaft',
                                'milestone' => $years,
                                'date' => $date,
                            );
                        }
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Display title, e.g. "40. Geburtstag" / "25 Jahre Mitgliedschaft".
     * @param array{kind?:string,milestone?:int|string} $ev
     */
    public static function formatTitle(array $ev) {
        $n = (int)(isset($ev['milestone']) ? $ev['milestone'] : 0);
        $kind = isset($ev['kind']) ? (string)$ev['kind'] : '';
        if($kind === 'membership') {
            return $n.' Jahre Mitgliedschaft';
        }
        return $n.'. Geburtstag';
    }

    /**
     * Compact chip text for calendar cells.
     * @param array{kind?:string,milestone?:int|string,name?:string} $ev
     */
    public static function formatChip(array $ev) {
        $n = (int)(isset($ev['milestone']) ? $ev['milestone'] : 0);
        $name = isset($ev['name']) ? trim((string)$ev['name']) : '';
        $kind = isset($ev['kind']) ? (string)$ev['kind'] : '';
        $tail = ($kind === 'membership') ? ($n.' J.') : ($n.'.');
        return $name !== '' ? ($name.' '.$tail) : $tail;
    }

    /**
     * Same month/day in target calendar year; Feb 29 → Feb 28 on non-leap years.
     * @param string $baseYmd
     * @param int $targetYear
     * @return string|null
     */
    public static function anniversaryOnYear($baseYmd, $targetYear) {
        $targetYear = (int)$targetYear;
        if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $baseYmd, $m)) {
            return null;
        }
        $month = (int)$m[2];
        $day = (int)$m[3];
        if($month === 2 && $day === 29 && !checkdate(2, 29, $targetYear)) {
            $day = 28;
        }
        if(!checkdate($month, $day, $targetYear)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $targetYear, $month, $day);
    }
}
?>
