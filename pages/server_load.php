<?php
if ( !defined('IN_HLSTATS') ) { die('Do not access this file directly'); }

// JSON endpoint for server load data with flexible ranges:
// range=1 -> last 24h by hour
// range=2 -> last 7 days by day
// range=3 -> last 30 days by week
// range=4 -> last 365 days by month

$server_id = isset($_GET['server_id']) && is_numeric($_GET['server_id']) ? valid_request($_GET['server_id'], true) : null;
$range = isset($_GET['range']) ? intval($_GET['range']) : 1;

if (!$server_id) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'missing_or_invalid_server_id']);
    exit;
}

$map = [];
switch ($range) {
    case 2: // last 7 days, grouped by day
        $period_count = 7;
        $seconds = $period_count * 86400;
        $sql = "SELECT FLOOR(`timestamp`/86400)*86400 AS ts, MAX(act_players) AS ap, MAX(max_players) AS mp, MIN(uptime) AS up, AVG(fps) AS fps "
             . "FROM hlstats_server_load WHERE server_id='" . $db->escape($server_id) . "' AND `timestamp` >= UNIX_TIMESTAMP() - $seconds "
             . "GROUP BY FLOOR(`timestamp`/86400)*86400 ORDER BY ts ASC";
        $res = $db->query($sql);
        if ($res) while ($r = $db->fetch_array($res)) $map[(int)$r['ts']] = ['ap'=>(int)$r['ap'],'mp'=>(int)$r['mp'],'up'=>(float)$r['up'],'fps'=>(float)$r['fps']];
        $end = floor(time()/86400)*86400;
        $start = $end - ($period_count-1)*86400;
        $step = 86400;
        $labelFmt = 'D d';
        break;

    case 3: // last 30 days, grouped by week
        $days = 30;
        $period_seconds = 86400 * 7; // weekly
        $seconds = $days * 86400;
        $sql = "SELECT FLOOR(`timestamp`/".$period_seconds.")*".$period_seconds." AS ts, MAX(act_players) AS ap, MAX(max_players) AS mp, AVG(uptime) AS up, MAX(fps) AS fps "
             . "FROM hlstats_server_load WHERE server_id='" . $db->escape($server_id) . "' AND `timestamp` >= UNIX_TIMESTAMP() - $seconds "
             . "GROUP BY FLOOR(`timestamp`/".$period_seconds.")*".$period_seconds." ORDER BY ts ASC";
        $res = $db->query($sql);
        if ($res) while ($r = $db->fetch_array($res)) $map[(int)$r['ts']] = ['ap'=>(int)$r['ap'],'mp'=>(int)$r['mp'],'up'=>(float)$r['up'],'fps'=>(float)$r['fps']];
        $end = floor(time()/(86400))*86400; // align to day
        $start = $end - ($days-1)*86400;
        // normalize start to week-bucket boundary (epoch-based)
        $start = floor($start / $period_seconds) * $period_seconds;
        $step = $period_seconds;
        $labelFmt = 'Y-D';
        break;

    case 4: // last year, grouped by month
        $months = 12;
        // compute month boundaries in PHP and use SQL to group by month start
        $sql = "SELECT UNIX_TIMESTAMP(DATE_FORMAT(FROM_UNIXTIME(`timestamp`),'%Y-%m-01')) AS ts, MAX(act_players) AS ap, MAX(max_players) AS mp, AVG(uptime) AS up, MAX(fps) AS fps "
             . "FROM hlstats_server_load WHERE server_id='" . $db->escape($server_id) . "' AND `timestamp` >= UNIX_TIMESTAMP(DATE_SUB(DATE_FORMAT(NOW(),'%Y-%m-01'), INTERVAL 11 MONTH)) "
             . "GROUP BY YEAR(FROM_UNIXTIME(`timestamp`)), MONTH(FROM_UNIXTIME(`timestamp`)) ORDER BY ts ASC";
        $res = $db->query($sql);
        if ($res) while ($r = $db->fetch_array($res)) $map[(int)$r['ts']] = ['ap'=>(int)$r['ap'],'mp'=>(int)$r['mp'],'up'=>(float)$r['up'],'fps'=>(float)$r['fps']];
        // build start/end month timestamps
        $end = strtotime(date('Y-m-01')); // first day of this month
        $start = strtotime(date('Y-m-01', strtotime('-' . ($months-1) . ' months')));
        $step = 'month';
        $labelFmt = 'Y-M';
        break;

    case 1:
    default: // last 24h, grouped by hour
        $period_count = 24;
        $seconds = $period_count * 3600;
        $sql = "SELECT FLOOR(`timestamp`/3600)*3600 AS ts, MAX(act_players) AS ap, MAX(max_players) AS mp, MAX(uptime) AS up, MAX(fps) AS fps "
             . "FROM hlstats_server_load WHERE server_id='" . $db->escape($server_id) . "' AND `timestamp` >= UNIX_TIMESTAMP() - $seconds "
             . "GROUP BY FLOOR(`timestamp`/3600)*3600 ORDER BY ts ASC";
        $res = $db->query($sql);
        if ($res) while ($r = $db->fetch_array($res)) $map[(int)$r['ts']] = ['ap'=>(int)$r['ap'],'mp'=>(int)$r['mp'],'up'=>(float)$r['up'],'fps'=>(float)$r['fps']];
        $end = floor(time()/3600)*3600;
        $start = $end - ($period_count-1)*3600;
        $step = 3600;
        $labelFmt = 'Y-m-d H:00';
        break;
}

$labels = [];
$ap = []; $mp = []; $up = []; $fps = [];

if ($step === 'month') {
    // iterate months
    $t = $start;
    while ($t <= $end) {
        $labels[] = date($labelFmt, $t);
        $ts = $t; // month-start timestamp
        if (isset($map[$ts])) {
            $ap[] = $map[$ts]['ap']; $mp[] = $map[$ts]['mp']; $up[] = $map[$ts]['up']; $fps[] = $map[$ts]['fps'];
        } else {
            $ap[] = $mp[] = $up[] = $fps[] = 0;
        }
        // add one month
        $t = strtotime('+1 month', $t);
    }
} else {
    for ($t = $start; $t <= $end; $t += $step) {
        if ($labelFmt === 'Y-\\WW') {
            // week label, show year-week number
            $labels[] = date('Y', $t) . '-W' . date('W', $t);
        } else {
            $labels[] = date($labelFmt, $t);
        }
        if (isset($map[$t])) {
            $ap[] = $map[$t]['ap']; $mp[] = $map[$t]['mp']; $up[] = $map[$t]['up']; $fps[] = $map[$t]['fps'];
        } else {
            $ap[] = $mp[] = $up[] = $fps[] = 0;
        }
    }
}

$payload = [
    'labels' => $labels,
    'datasets' => [
        ['label' => 'Active Players', 'data' => $ap],
        ['label' => 'Max Players', 'data' => $mp],
        ['label' => 'Uptime', 'data' => $up],
        ['label' => 'FPS', 'data' => $fps],
    ],
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($payload);

?>
