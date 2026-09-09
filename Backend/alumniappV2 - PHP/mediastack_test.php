<?php
/**
 * mediastack News API - test script
 *
 * Run from the terminal:   php mediastack_test.php
 * Or drop it on a server and open it in the browser.
 *
 * NOTE: The free plan does NOT support HTTPS. If you are on a free key,
 * keep the scheme as http://. Switch to https:// only on a paid plan.
 */

// 1. Put your key here
$accessKey = 'be13f3d52b1a3c86ff4e3a25b5f0cadf';
// 2. http:// for free plan, https:// for paid plans
$endpoint = 'http://api.mediastack.com/v1/news';

// 3. Full-content scraping
$includeFullContent = true;  // set false to skip scraping (much faster)
$scrapeLimit        = 8;     // max articles to scrape per run, avoids PHP timeouts

// 4. Optional: scraping-API fallback for Cloudflare-protected sites (Tribune, Punch...)
//    Leave empty to use direct curl only. Sign up at scraperapi.com for a free key.
//    The script tries direct curl first and only spends a credit when it hits a 403.
$scraperApiKey = ''; // e.g. 'a1b2c3...'

// Scraping is slow (one HTTP request per article), so give the script room
if ($includeFullContent) {
    set_time_limit(120);
}

$queryString = http_build_query([
    'access_key' => $accessKey,
    'countries'  => 'ng',               // Nigeria
    'languages'  => 'en',               // English-language sources
    'sort'       => 'published_desc',   // newest first
    'limit'      => 25,                 // how many results to return
]);

$url = sprintf('%s?%s', $endpoint, $queryString);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$json = curl_exec($ch);

// --- Error handling -------------------------------------------------

if ($json === false) {
    die('cURL error: ' . curl_error($ch) . PHP_EOL);
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$apiResult = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die('JSON decode error: ' . json_last_error_msg() . PHP_EOL
        . 'Raw response: ' . $json . PHP_EOL);
}

// mediastack returns auth/quota errors as HTTP 200 with an "error" object
if (isset($apiResult['error'])) {
    $err = $apiResult['error'];
    die('API error [' . ($err['code'] ?? 'unknown') . ']: '
        . ($err['message'] ?? 'no message') . PHP_EOL);
}

// --- Success --------------------------------------------------------

/**
 * Low-level GET. Returns [html, http_code, error, via].
 * If $scraperApiKey is given, routes the request through ScraperAPI.
 */
function httpGet($pageUrl, $scraperApiKey = '')
{
    if ($scraperApiKey !== '') {
        // ScraperAPI wrapper: handles proxies + Cloudflare challenge
        $fetchUrl = 'https://api.scraperapi.com/?' . http_build_query([
            'api_key'    => $scraperApiKey,
            'url'        => $pageUrl,
            'render'     => 'true',   // run JS so the challenge resolves
            'country_code' => 'ng',   // request a Nigerian exit IP
        ]);
        $via = 'scraperapi';
    } else {
        $fetchUrl = $pageUrl;
        $via = 'direct';
    }

    $ch = curl_init($fetchUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 60, // scraping APIs can be slow
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
            . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
        ],
    ]);
    $html     = curl_exec($ch);
    $err      = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'html'      => ($html === false) ? '' : $html,
        'http_code' => $httpCode,
        'error'     => $err,
        'via'       => $via,
    ];
}

/** Does this response look like a bot-block / challenge page? */
function looksBlocked($httpCode, $html)
{
    return $httpCode === 403 || $httpCode === 503
        || stripos($html, 'cf-browser-verification') !== false
        || stripos($html, 'Just a moment') !== false
        || stripos($html, 'Attention Required') !== false
        || stripos($html, 'Enable JavaScript and cookies to continue') !== false;
}

/**
 * Fetch an article page and extract the main body text.
 * Tries direct curl first; if blocked and a ScraperAPI key is set, retries via the API.
 */
function fetchFullContent($pageUrl, $scraperApiKey = '')
{
    $resp = httpGet($pageUrl);              // 1) free direct attempt
    $blocked = $resp['html'] === '' || looksBlocked($resp['http_code'], $resp['html']);

    if ($blocked && $scraperApiKey !== '') {
        $resp = httpGet($pageUrl, $scraperApiKey); // 2) paid fallback only when blocked
    }

    $httpCode = $resp['http_code'];
    $html     = $resp['html'];
    $via      = $resp['via'];

    $result = [
        'text' => null, 'error' => null,
        'http_code' => $httpCode, 'bytes' => strlen($html), 'via' => $via,
    ];

    if ($html === '') {
        $result['error'] = $resp['error'] ?: 'empty response (host blocked or unreachable)';
        return $result;
    }

    if (looksBlocked($httpCode, $html)) {
        $result['error'] = "blocked: challenge/forbidden page (HTTP $httpCode via $via)"
            . ($scraperApiKey === '' ? ' — add a $scraperApiKey to bypass' : '');
        return $result;
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    foreach ($xpath->query('//script | //style | //nav | //header | //footer | //aside | //form | //figure') as $node) {
        if ($node->parentNode) {
            $node->parentNode->removeChild($node);
        }
    }

    $containers = [
        '//*[contains(@class, "entry-content")]',
        '//*[contains(@class, "post-content")]',
        '//*[contains(@class, "td-post-content")]',
        '//*[contains(@class, "article-content")]',
        '//*[contains(@class, "article-body")]',
        '//*[contains(@class, "single-content")]',
        '//article',
        '//main',
    ];

    $root = null;
    foreach ($containers as $q) {
        $nodes = $xpath->query($q);
        if ($nodes && $nodes->length > 0) {
            $root = $nodes->item(0);
            break;
        }
    }

    $pNodes = $root ? $xpath->query('.//p', $root) : $xpath->query('//p');

    $paragraphs = [];
    foreach ($pNodes as $p) {
        $t = trim(preg_replace('/\s+/u', ' ', $p->textContent));
        if (mb_strlen($t) > 40) {
            $paragraphs[] = $t;
        }
    }

    $text = trim(implode("\n\n", $paragraphs));

    if ($text === '') {
        $result['error'] = "no article paragraphs found (HTTP $httpCode, "
            . $result['bytes'] . " bytes via $via) — layout may be unsupported";
        return $result;
    }

    $result['text'] = $text;
    return $result;
}

// Decode HTML entities like &#8217; and [&#8230;] into real characters
function clean($value)
{
    if ($value === null) {
        return null;
    }
    return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Build a clean list of just the fields we care about
$articles = [];
foreach (($apiResult['data'] ?? []) as $i => $article) {
    $row = [
        'title'        => clean($article['title']),
        'description'  => clean($article['description']),
        'source'       => $article['source'],
        'author'       => $article['author'],
        'image'        => $article['image'] ?: null,
        'url'          => $article['url'],
        'published_at' => $article['published_at'],
        'fullcontent'  => null,
    ];

    if (!$includeFullContent) {
        $row['fullcontent_status'] = 'disabled';
    } elseif (empty($article['url'])) {
        $row['fullcontent_status'] = 'no url';
    } elseif ($i >= $scrapeLimit) {
        $row['fullcontent_status'] = 'skipped (beyond scrape_limit)';
    } else {
        $scraped = fetchFullContent($article['url'], $scraperApiKey);
        $row['fullcontent']        = clean($scraped['text']);
        $row['fullcontent_status'] = $scraped['text'] !== null
            ? ('ok (via ' . $scraped['via'] . ')')
            : ('failed: ' . $scraped['error']);
        $row['scrape_http_code']   = $scraped['http_code'];
    }

    $articles[] = $row;
}

$output = [
    'http_status' => $httpCode,
    'count'       => count($articles),
    'total'       => $apiResult['pagination']['total'] ?? 0,
    'scraped'     => $includeFullContent ? min($scrapeLimit, count($articles)) : 0,
    'articles'    => $articles,
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);