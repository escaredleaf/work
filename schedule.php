<?php
function fetch_ics($url) {
    $url = preg_replace('/^webcal:/i', 'https:', $url);
    return @file_get_contents($url);
}

function parse_ics($data) {
    $events = [];
    $lines = preg_split('/\r?\n/', $data);
    $event = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === 'BEGIN:VEVENT') {
            $event = [];
        } elseif ($line === 'END:VEVENT') {
            if ($event !== null) {
                $events[] = $event;
            }
            $event = null;
        } elseif ($event !== null && strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $event[strtoupper($key)] = $value;
        }
    }
    return $events;
}

function format_ics_date($value) {
    if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})Z?$/', $value, $m)) {
        $tz = substr($value, -1) === 'Z' ? new DateTimeZone('UTC') : null;
        $dt = new DateTime(sprintf('%s-%s-%s %s:%s:%s', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]), $tz);
        return $dt->format('Y-m-d H:i:s');
    }
    return $value;
}

$icalUrl = 'webcal://outlook.office365.com/owa/calendar/a70595e6fb2948d48e89e24db6618d47@sktelecom.com/2a53ebb31c5443e7a55589eaf556331714645327997896019143/S-1-8-274681949-3373596907-3888491435-2108270514/reachcalendar.ics';
$data = fetch_ics($icalUrl);
$events = $data ? parse_ics($data) : [];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>일정 보기</title>
</head>
<body>
<h1>일정</h1>
<?php if (empty($events)): ?>
<p>일정을 불러올 수 없습니다.</p>
<?php else: ?>
<ul>
<?php foreach ($events as $e): ?>
<li>
<strong><?= htmlspecialchars($e['SUMMARY'] ?? '제목 없음') ?></strong><br>
<?= htmlspecialchars(format_ics_date($e['DTSTART'] ?? '')) ?> ~ <?= htmlspecialchars(format_ics_date($e['DTEND'] ?? '')) ?>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</body>
</html>
