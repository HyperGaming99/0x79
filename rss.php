<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';

header('Content-Type: text/xml; charset=UTF-8');

function rssDate($val): string {
    if (empty($val)) {
        return date(DATE_RSS);
    }
    if (is_numeric($val)) {
        return date(DATE_RSS, (int)$val);
    }
    $ts = strtotime((string)$val);
    if ($ts === false || $ts === -1) {
        return date(DATE_RSS);
    }
    return date(DATE_RSS, $ts);
}

$rows = db()->query(
    'SELECT id, title, description, image, pub_date
     FROM posts ORDER BY pub_date DESC LIMIT 50'
)->fetchAll();

$lastBuildDate = $rows
    ? rssDate($rows[0]['pub_date'])
    : date(DATE_RSS);

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<rss version="2.0"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:media="http://search.yahoo.com/mrss/">
<channel>
    <title><?= xml(FEED_TITLE) ?></title>
    <link><?= xml(FEED_LINK) ?></link>
    <description><?= xml(FEED_DESCRIPTION) ?></description>
    <language>de-DE</language>
    <lastBuildDate><?= $lastBuildDate ?></lastBuildDate>
    <atom:link href="<?= xml(FEED_URL) ?>" rel="self" type="application/rss+xml" />
<?php foreach ($rows as $row):
    $url     = postUrl((int) $row['id']);
    $img     = imageUrl($row['image'] ?? null);
    $imgPath = ($row['image'] ?? '') ? IMAGE_DIR . '/' . $row['image'] : '';
    $imgLen  = ($imgPath !== '' && is_file($imgPath)) ? filesize($imgPath) : 0;
    // Bild oben in die Beschreibung einbetten (für Reader, die kein enclosure zeigen)
    $body = ($img ? '<p><img src="' . xml($img) . '" alt="' . xml($row['title']) . '"></p>' : '')
          . ($row['description'] ?? '');
?>
    <item>
        <title><?= xml($row['title'] ?? '') ?></title>
        <link><?= xml($url) ?></link>
<?php if ($img): ?>
        <enclosure url="<?= xml($img) ?>" type="image/jpeg" length="<?= $imgLen ?>" />
        <media:content url="<?= xml($img) ?>" type="image/jpeg" medium="image" />
<?php endif; ?>
        <description><?= cdata($body) ?></description>
        <pubDate><?= rssDate($row['pub_date']) ?></pubDate>
        <guid isPermaLink="true"><?= xml($url) ?></guid>
    </item>
<?php endforeach; ?>
</channel>
</rss>
