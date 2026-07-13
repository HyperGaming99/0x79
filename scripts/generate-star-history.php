<?php
declare(strict_types=1);

$repository = trim((string)(getenv('GITHUB_REPOSITORY') ?: 'HyperGaming99/0x79'));
$token = trim((string)(getenv('GITHUB_TOKEN') ?: getenv('GH_TOKEN')));

if ($token === '' || !preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository)) {
    fwrite(STDERR, "GITHUB_TOKEN and a valid GITHUB_REPOSITORY are required.\n");
    exit(1);
}

function githubJson(string $url, string $token, string $accept = 'application/vnd.github+json'): array
{
    $headers = [
        'Accept: ' . $accept,
        'Authorization: Bearer ' . $token,
        'User-Agent: 0x79-star-history',
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers),
        'ignore_errors' => true,
        'timeout' => 25,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    if ($raw === false || !preg_match('/\s2\d\d\s/', $statusLine)) {
        throw new RuntimeException('GitHub API request failed: ' . $statusLine);
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) throw new RuntimeException('GitHub API returned invalid JSON.');
    return $decoded;
}

function sx(float $timestamp, float $start, float $span, float $left, float $width): float
{
    return $left + (($timestamp - $start) / $span) * $width;
}

function sy(float $count, float $maximum, float $top, float $height): float
{
    return $top + $height - ($count / $maximum) * $height;
}

function point(float $x, float $y): string
{
    return number_format($x, 1, '.', '') . ',' . number_format($y, 1, '.', '');
}

try {
    $base = 'https://api.github.com/repos/' . $repository;
    $metadata = githubJson($base, $token);
    $events = [];
    for ($page = 1; $page <= 100; $page++) {
        $batch = githubJson($base . '/stargazers?per_page=100&page=' . $page, $token, 'application/vnd.github.star+json');
        foreach ($batch as $event) {
            $timestamp = strtotime((string)($event['starred_at'] ?? ''));
            if ($timestamp !== false) $events[] = $timestamp;
        }
        if (count($batch) < 100) break;
    }
    sort($events, SORT_NUMERIC);

    $start = (float)(strtotime((string)($metadata['created_at'] ?? '')) ?: ($events[0] ?? time()));
    $end = (float)time();
    if ($end <= $start) $end = $start + 86400;
    $span = $end - $start;
    $maximum = (float)max(1, count($events));
    $left = 68.0; $top = 38.0; $plotWidth = 792.0; $plotHeight = 202.0;

    $points = [[(float)$start, 0.0]];
    $count = 0;
    foreach ($events as $event) {
        $count++;
        $points[] = [(float)$event, (float)$count];
    }
    $points[] = [$end, (float)$count];
    $polyline = implode(' ', array_map(
        fn(array $p): string => point(sx($p[0], $start, $span, $left, $plotWidth), sy($p[1], $maximum, $top, $plotHeight)),
        $points
    ));
    $area = point($left, $top + $plotHeight) . ' ' . $polyline . ' ' . point($left + $plotWidth, $top + $plotHeight);
    $repoLabel = htmlspecialchars($repository, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $generated = gmdate('Y-m-d H:i') . ' UTC';
    $xDates = [
        [$left, gmdate('M Y', (int)$start), 'start'],
        [$left + $plotWidth / 2, gmdate('M Y', (int)(($start + $end) / 2)), 'middle'],
        [$left + $plotWidth, gmdate('M Y', (int)$end), 'end'],
    ];

    $grid = '';
    $gridSteps = (int)min(4, $maximum);
    for ($i = 0; $i <= $gridSteps; $i++) {
        $value = $maximum * $i / $gridSteps;
        $y = sy($value, $maximum, $top, $plotHeight);
        $grid .= '<line x1="' . $left . '" y1="' . $y . '" x2="' . ($left + $plotWidth) . '" y2="' . $y . '" class="grid"/>';
        $grid .= '<text x="56" y="' . ($y + 4) . '" text-anchor="end" class="axis">' . (int)round($value) . '</text>';
    }
    $dateLabels = '';
    foreach ($xDates as [$x, $label, $anchor]) {
        $dateLabels .= '<text x="' . $x . '" y="267" text-anchor="' . $anchor . '" class="axis">' . $label . '</text>';
    }

    $starLabel = count($events) === 1 ? '1 star' : count($events) . ' stars';
    $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
        '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="300" viewBox="0 0 900 300" role="img" aria-labelledby="title desc">' .
        '<title id="title">Star history for ' . $repoLabel . '</title><desc id="desc">' . $starLabel . ' as of ' . $generated . '</desc>' .
        '<style>:root{color-scheme:light dark}.bg{fill:#f7f7f4}.title{fill:#11110f;font:700 16px Arial,sans-serif}.meta,.axis{fill:#66665f;font:11px ui-monospace,monospace}.grid{stroke:#d8d7d0;stroke-width:1}.area{fill:#b8ff31;fill-opacity:.28}.line{fill:none;stroke:#11110f;stroke-width:3;stroke-linejoin:round;stroke-linecap:round}.dot{fill:#b8ff31;stroke:#11110f;stroke-width:3}@media(prefers-color-scheme:dark){.bg{fill:#11110f}.title{fill:#e8e6df}.meta,.axis{fill:#98978f}.grid{stroke:#34342f}.line{stroke:#e8e6df}.dot{stroke:#e8e6df}}</style>' .
        '<rect class="bg" width="900" height="300"/><text x="28" y="25" class="title">' . $repoLabel . '</text><text x="872" y="25" text-anchor="end" class="meta">' . $starLabel . ' · ' . $generated . '</text>' .
        $grid . $dateLabels . '<polygon points="' . $area . '" class="area"/><polyline points="' . $polyline . '" class="line"/>' .
        '<circle cx="' . sx($end, $start, $span, $left, $plotWidth) . '" cy="' . sy((float)$count, $maximum, $top, $plotHeight) . '" r="5" class="dot"/></svg>' . "\n";

    $output = dirname(__DIR__) . '/assets/star-history.svg';
    if (file_put_contents($output, $svg, LOCK_EX) === false) throw new RuntimeException('Could not write ' . $output);
    echo 'Updated ' . $output . ' with ' . $starLabel . ".\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
