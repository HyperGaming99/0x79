<?php
declare(strict_types=1);

// Rebuild public/company-icons from a locally extracted Simple Icons package.
$package = rtrim((string)($argv[1] ?? ''), '/');
$sourceIcons = $package . '/icons';
$metadataFile = $package . '/data/simple-icons.json';
$target = dirname(__DIR__) . '/public/company-icons';
$customSource = __DIR__ . '/company-icon-sources/reqable.png';

if (!is_dir($sourceIcons) || !is_file($metadataFile)) {
    fwrite(STDERR, "usage: php scripts/sync-company-icons.php /path/to/simple-icons/package\n");
    exit(1);
}

$coreSlugs = [
    '3m', 'abb', 'abbott', 'abbvie', 'accenture', 'acer', 'adidas', 'adp', 'airbnb', 'airbus',
    'akamai', 'alibabacloud', 'alibabadotcom', 'aliexpress', 'alipay', 'amd', 'americanairlines',
    'americanexpress', 'anthropic', 'apple', 'asana', 'atlassian', 'audi', 'autodesk', 'baidu',
    'blackberry', 'bmw', 'boeing', 'bookingdotcom', 'bosch', 'broadcom', 'bugatti', 'bytedance',
    'cisco', 'cloudflare', 'coinbase', 'dell', 'dhl', 'discord', 'docker', 'dropbox', 'ebay',
    'epicgames', 'etsy', 'facebook', 'fedex', 'figma', 'ford', 'github', 'gitlab', 'google',
    'googlechrome', 'googlecloud', 'honda', 'huawei', 'ikea', 'imdb', 'instagram', 'intel', 'intuit',
    'lenovo', 'lg', 'mastercard', 'mcdonalds', 'meta', 'mongodb', 'netflix', 'nike', 'nissan',
    'nvidia', 'paypal', 'pinterest', 'porsche', 'qualcomm', 'reddit', 'samsung', 'sap', 'shopify',
    'siemens', 'sony', 'spacex', 'spotify', 'stripe', 'telegram', 'tesla', 'tiktok', 'toyota',
    'twitch', 'uber', 'ubisoft', 'unilever', 'unity', 'visa', 'volkswagen', 'whatsapp', 'wikipedia',
    'x', 'xiaomi', 'youtube', 'zoom',
];

$metadata = json_decode((string)file_get_contents($metadataFile), true, 512, JSON_THROW_ON_ERROR);
$bySlug = [];
foreach ($metadata as $icon) $bySlug[(string)$icon['slug']] = $icon;

// Widely used companies, products, platforms and developer brands are preferred.
// If an icon is removed upstream, the deterministic round-robin fallback keeps the set at 500.
$preferredSlugs = [
    'bruno', 'charles', 'hoppscotch', 'httpie',
    '1and1', '1password', '7zip', '99designs', 'activision', 'adafruit', 'adguard', 'adyen',
    'aeroflot', 'aircanada', 'airchina', 'airfrance', 'airindia', 'airtable', 'algolia', 'allegro',
    'alwaysdata', 'americanairlines', 'anaconda', 'android', 'androidauto', 'androidstudio', 'angular',
    'ansible', 'antdesign', 'apache', 'apacheairflow', 'apachecassandra', 'apachecloudstack',
    'apachecordova', 'apachecouchdb', 'apacheflink', 'apachehadoop', 'apachehive', 'apachekafka',
    'apachemaven', 'apacheopenoffice', 'apachespark', 'apachetomcat', 'appgallery', 'applemusic',
    'applepay', 'applepodcasts', 'appletv', 'archlinux', 'arduino', 'argo', 'asana', 'astro',
    'asus', 'auth0', 'autodesk', 'avast', 'awesomelists', 'babel', 'backblaze', 'bandcamp',
    'beatsbydre', 'bitbucket', 'bitcoin', 'bitdefender', 'bitly', 'bitrise', 'bitwarden', 'blender',
    'blogger', 'bluetooth', 'bootstrap', 'brave', 'broadcom', 'bt', 'buffer', 'bugcrowd', 'bun',
    'burgerking', 'buymeacoffee', 'c', 'cachet', 'calendly', 'canva', 'carrefour', 'celery',
    'centos', 'checkmarx', 'chromecast', 'circle', 'circleci', 'clarifai', 'clickhouse', 'clion',
    'clockify', 'cloud66', 'cloudinary', 'cmake', 'cnn', 'codeberg', 'codecademy', 'codechef',
    'codeclimate', 'codecov', 'codeforces', 'codeigniter', 'codepen', 'codesandbox', 'coffeescript',
    'composer', 'confluence', 'consul', 'cplusplus', 'crunchyroll', 'cryptomator', 'css', 'curl',
    'd3', 'dacia', 'dailymotion', 'daisyui', 'databricks', 'datadog', 'datagrip', 'dbeaver',
    'debian', 'deepl', 'deliveroo', 'deno', 'devdotto', 'digitalocean', 'directus', 'django',
    'docusign', 'dotnet', 'douban', 'dribbble', 'duckdb', 'duckduckgo', 'dungeonsanddragons',
    'eclipseadoptium', 'eclipseide', 'elastic', 'electron', 'element', 'elevenlabs', 'elgato',
    'elixir', 'elm', 'elsevier', 'embarcadero', 'emby', 'ericsson', 'esbuild', 'eslint',
    'espressif', 'evernote', 'exercism', 'express', 'fastapi', 'fastly', 'ferrari', 'ferrarinv',
    'fiat', 'fidoalliance', 'filezilla', 'firebase', 'firefox', 'flask', 'flatpak', 'flickr',
    'fluentbit', 'flutter', 'flydotio', 'fmod', 'foursquare', 'freebsd', 'fujifilm', 'fujitsu',
    'garmin', 'gatsby', 'geeksforgeeks', 'gimp', 'git', 'gitea', 'githubactions', 'githubcopilot',
    'githubpages', 'gnome', 'gnu', 'go', 'godaddy', 'godotengine', 'gofundme', 'gogdotcom',
    'goodreads', 'gradle', 'grafana', 'graphql', 'grav', 'greenhouse', 'grubhub', 'gulp',
    'hackaday', 'hackclub', 'hackerrank', 'hashicorp', 'haskell', 'hbo', 'hetzner', 'hilton',
    'homeassistant', 'homebridge', 'homify', 'hotelsdotcom', 'html5', 'hubspot', 'huggingface',
    'hyundai', 'icloud', 'ifixit', 'indeed', 'influxdb', 'inkscape', 'insomnia', 'instacart',
    'internetarchive', 'ionic', 'jaguar', 'jamboard', 'jasmine', 'javascript', 'jbl', 'jeep',
    'jekyll', 'jellyfin', 'jenkins', 'jest', 'jetbrains', 'jira', 'joomla', 'jquery', 'json',
    'jupyter', 'k3s', 'kaggle', 'kaspersky', 'kde', 'keras', 'kia', 'kibana', 'kickstarter',
    'klarna', 'kotlin', 'kubernetes', 'laravel', 'lastpass', 'leetcode', 'lemmy', 'libreoffice',
    'line', 'linear', 'linktree', 'linux', 'linuxfoundation', 'linuxmint', 'lit', 'logitech',
    'lua', 'lufthansa', 'lyft', 'magento', 'mailchimp', 'manjaro', 'mapbox', 'mariadb', 'markdown',
    'marriott', 'maserati', 'materialdesign', 'mattermost', 'mediamarkt', 'medium', 'meetup',
    'messenger', 'microbit', 'minio', 'mitsubishi', 'mixcloud', 'mlflow', 'mocha', 'moodle',
    'motorola', 'mozilla', 'mysql', 'n8n', 'namecheap', 'nestjs', 'netlify', 'newbalance',
    'newyorktimes', 'nextcloud', 'nextdotjs', 'nginx', 'ngrok', 'nodedotjs', 'nodemon', 'notion',
    'npm', 'numpy', 'nuget', 'nuxt', 'obsidian', 'ocaml', 'odoo', 'okta', 'ollama', 'opel',
    'opencv', 'opengl', 'openstreetmap', 'opensourceinitiative', 'opera', 'ovh', 'paloaltonetworks',
    'pandas', 'patreon', 'perplexity', 'php', 'phpstorm', 'picarddotmusicbrainz', 'pihole',
    'pixabay', 'plex', 'plotly', 'pm2', 'podman', 'postcss', 'postgresql', 'postman', 'powershell',
    'preact', 'prettier', 'prometheus', 'proton', 'protonmail', 'protonvpn', 'pubg', 'puma',
    'puppeteer', 'pwa', 'pycharm', 'pypi', 'python', 'pytorch', 'qantas', 'qemu', 'qt',
    'quarkus', 'raspberrypi', 'react', 'reactbootstrap', 'reactquery', 'reactrouter', 'renault',
    'render', 'replit', 'revolut', 'riotgames', 'rockstargames', 'rollsroyce', 'rollupdotjs',
    'rubocop', 'ruby', 'rubyonrails', 'rust', 'ryanair', 'safari', 'sanity', 'sass', 'scaleway',
    'seagate', 'sega', 'selenium', 'semver', 'sentry', 'sequelize', 'serverfault', 'shell',
    'shutterstock', 'signal', 'skoda', 'skype', 'smartthings', 'snapchat', 'snowflake', 'socketdotio',
    'solana', 'solidity', 'sonarqube', 'soundcloud', 'sourceforge', 'southwestairlines', 'spacy',
    'sparkfun', 'speedtest', 'spring', 'sqlite', 'square', 'stackblitz', 'stackexchange',
    'stackoverflow', 'steam', 'steamdeck', 'storyblok', 'strapi', 'subaru', 'sublimetext',
    'substack', 'supabase', 'suse', 'suzuki', 'svelte', 'svg', 'swift', 'symfony', 'tails',
    'tailwindcss', 'tauri', 'tensorflow', 'terraform', 'themoviedatabase', 'threads', 'threema',
    'thunderbird', 'tidal', 'todoist', 'tomtom', 'torbrowser', 'travisci', 'trello', 'tripadvisor',
    'tumblr', 'turborepo', 'typeorm', 'typescript', 'ubuntu', 'udemy', 'unsplash', 'upwork',
    'vagrant', 'vault', 'vercel', 'vimeo', 'vim', 'vinted', 'virtualbox', 'visualstudiocode',
    'vite', 'vivaldi', 'vlcmediaplayer', 'vmware', 'volvo', 'vuedotjs', 'w3schools', 'wayland',
    'waze', 'webassembly', 'webflow', 'webpack', 'webstorm', 'wechat', 'westernunion', 'wetransfer',
    'wikimediafoundation', 'wix', 'woocommerce', 'wordpress', 'xcode', 'xing', 'ycombinator',
    'yelp', 'zara', 'zapier', 'zendesk', 'zillow', 'zomato', 'zotero',
];

$slugs = [];
foreach (array_merge($coreSlugs, $preferredSlugs) as $slug) {
    if (isset($bySlug[$slug]) && !in_array($slug, $slugs, true)) $slugs[] = $slug;
}

if (count($slugs) < 499) {
    $fallback = array_keys($bySlug);
    sort($fallback, SORT_NATURAL | SORT_FLAG_CASE);
    $groups = [];
    foreach ($fallback as $slug) $groups[strtolower(substr($slug, 0, 1))][] = $slug;
    while (count($slugs) < 499) {
        $added = false;
        foreach ($groups as &$group) {
            while ($group && in_array($group[0], $slugs, true)) array_shift($group);
            if ($group && count($slugs) < 499) { $slugs[] = array_shift($group); $added = true; }
        }
        unset($group);
        if (!$added) break;
    }
}

$slugs = array_slice($slugs, 0, 499);
if (count($slugs) !== 499 || count(array_unique($slugs)) !== 499) {
    throw new RuntimeException('The Simple Icons selection must contain exactly 499 unique slugs.');
}

if (!is_file($customSource)) throw new RuntimeException('Missing custom Reqable icon source.');

if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
    throw new RuntimeException('Could not create target directory.');
}

foreach (glob($target . '/*.svg') ?: [] as $oldIcon) unlink($oldIcon);

$manifest = [];
foreach ($slugs as $slug) {
    $source = $sourceIcons . '/' . $slug . '.svg';
    if (!is_file($source) || !isset($bySlug[$slug])) throw new RuntimeException('Missing icon: ' . $slug);
    $svg = (string)file_get_contents($source);
    $hex = strtoupper((string)$bySlug[$slug]['hex']);
    $svg = preg_replace('/<svg\b/', '<svg fill="#' . $hex . '"', $svg, 1) ?? $svg;
    if (file_put_contents($target . '/' . $slug . '.svg', $svg) === false) throw new RuntimeException('Could not write: ' . $slug);
    $manifest[] = [
        'name' => (string)$bySlug[$slug]['title'],
        'slug' => $slug,
        'file' => $slug . '.svg',
        'hex' => '#' . $hex,
        'source' => (string)$bySlug[$slug]['source'],
    ];
}

// Reqable is not available in Simple Icons. Keep its official GitHub
// organization avatar locally and wrap it in SVG for a consistent public API.
$reqableData = base64_encode((string)file_get_contents($customSource));
$reqableHex = '#FFB638';
$reqableSvg = '<svg fill="' . $reqableHex . '" role="img" viewBox="0 0 460 460" xmlns="http://www.w3.org/2000/svg">'
    . '<title>Reqable</title><image width="460" height="460" href="data:image/png;base64,' . $reqableData . '"/></svg>';
if (file_put_contents($target . '/reqable.svg', $reqableSvg) === false) {
    throw new RuntimeException('Could not write: reqable');
}
$manifest[] = [
    'name' => 'Reqable',
    'slug' => 'reqable',
    'file' => 'reqable.svg',
    'hex' => $reqableHex,
    'source' => 'https://github.com/reqable',
];

usort($manifest, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
file_put_contents($target . '/manifest.json', json_encode([
    'source' => 'Simple Icons and official brand assets',
    'version' => '16.26.0',
    'license' => 'See README and individual sources',
    'count' => count($manifest),
    'icons' => $manifest,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo 'Synced ' . count($manifest) . " company icons.\n";
