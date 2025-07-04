<?php
// Fetch calendar data from a webcal URL and display upcoming events.

function fetch_ics(string $url): ?string {
    // Convert webcal scheme to https for fetching
    $httpUrl = preg_replace('/^webcal:/i', 'https:', $url);
    $context = stream_context_create([
        'http' => ['timeout' => 10, 'user_agent' => 'PHP ICS Fetcher'],
        'https' => ['timeout' => 10, 'user_agent' => 'PHP ICS Fetcher']
    ]);
    $data = @file_get_contents($httpUrl, false, $context);
    return $data !== false ? $data : null;
}

function unfold_ics_lines(string $data): array {
    // Join folded lines (lines starting with space or tab)
    $lines = preg_split('/\r?\n/', $data);
    $unfolded = [];
    foreach ($lines as $line) {
        if (!empty($unfolded) && (isset($line[0]) && ($line[0] === ' ' || $line[0] === "\t"))) {
            $unfolded[count($unfolded) - 1] .= substr($line, 1);
        } else {
            $unfolded[] = $line;
        }
    }
    return $unfolded;
}

function parse_ics(string $data): array {
    $events = [];
    $lines = unfold_ics_lines($data);
    $event = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === 'BEGIN:VEVENT') {
            $event = [];
        } elseif ($line === 'END:VEVENT') {
            if ($event) {
                $events[] = $event;
            }
            $event = null;
        } elseif ($event !== null && strpos($line, ':') !== false) {
            list($name, $value) = explode(':', $line, 2);
            $propName = strtoupper(explode(';', $name, 2)[0]);
            $event[$propName] = $value;
        }
    }
    return $events;
}

function format_ics_datetime(string $value): string {
    // Handles both date (YYYYMMDD) and datetime (YYYYMMDDTHHMMSS[Z])
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m)) {
        $dt = DateTime::createFromFormat('Ymd', $m[0]);
        return $dt ? $dt->format('Y-m-d') : $value;
    }
    if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})(Z)?$/', $value, $m)) {
        $tz = !empty($m[7]) ? new DateTimeZone('UTC') : null;
        $dt = DateTime::createFromFormat('YmdHis', $m[1].$m[2].$m[3].$m[4].$m[5].$m[6], $tz);
        if ($dt && !empty($m[7])) {
            $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
        }
        return $dt ? $dt->format('Y-m-d H:i') : $value;
    }
    return $value;
}

$icalUrl = 'webcal://outlook.office365.com/owa/calendar/a70595e6fb2948d48e89e24db6618d47@sktelecom.com/2a53ebb31c5443e7a55589eaf556331714645327997896019143/S-1-8-274681949-3373596907-3888491435-2108270514/reachcalendar.ics';
$icsData = fetch_ics($icalUrl);
$events = $icsData ? parse_ics($icsData) : [];
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
<p>일정을 불러오지 못했습니다.</p>
<?php else: ?>
<ul>
<?php foreach ($events as $e): ?>
    <li>
        <strong><?= htmlspecialchars($e['SUMMARY'] ?? '제목 없음') ?></strong><br>
        <?= htmlspecialchars(format_ics_datetime($e['DTSTART'] ?? '')) ?> ~
        <?= htmlspecialchars(format_ics_datetime($e['DTEND'] ?? '')) ?>
    </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</body>
</html>
