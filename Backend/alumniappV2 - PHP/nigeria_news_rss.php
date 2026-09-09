<?php
/**
 * Nigerian daily news via RSS feeds, full content only.
 *
 * Strategy:
 *  1. Pull article lists from open RSS feeds (free, no Cloudflare block).
 *  2. For each article, fetch its page to get the FULL body + og:image.
 *  3. Keep ONLY articles successfully enriched from the page
 *     (content_source = "page"). RSS-summary-only items are dropped.
 *
 * Cache: enriching ~50+ pages is slow, so results are cached. Normal loads
 * serve the cache instantly; add ?refresh=1 to rebuild.
 *
 * Run:  php nigeria_news_rss.php   (or open in a browser)
 */

set_time_limit(180);

// --- Config ---------------------------------------------------------

// Feeds to pull from. Cloudflare-gated feeds report "blocked" and are skipped.
$feeds = [
    'Premium Times'    => 'https://www.premiumtimesng.com/feed',
    'Nairametrics'     => 'https://nairametrics.com/feed',
    'The Cable'        => 'https://www.thecable.ng/feed',
    'PM News'          => 'https://www.pmnewsnigeria.com/feed',
    'Vanguard'         => 'https://www.vanguardngr.com/feed',
    'Daily Post'       => 'https://dailypost.ng/feed',
    'The Nation'       => 'https://thenationonlineng.net/feed',
    'Channels TV'      => 'https://www.channelstv.com/feed',
    'Naija News'       => 'https://www.naijanews.com/feed',
    'Legit'            => 'https://www.legit.ng/rss/all.rss',
    'Nigerian Eye'     => 'https://feeds.feedburner.com/Nigerianeye',
    'Sahara Reporters' => 'https://saharareporters.com/feed',
];

$perFeedLimit = 20;   // max items to read from each feed

// We enrich articles down the newest-first list and keep only the ones whose
// full body we successfully pull from the page, until we have this many.
$targetCount  = 50;   // how many full ("page") articles to return
$maxEnrich    = 110;  // safety cap on page fetches per run (controls run time)

// Cache
$cacheEnabled = true;
$cacheFile    = __DIR__ . '/news_cache.json';
$cacheTtl     = 900;  // seconds (15 minutes)

// --- Serve from cache if fresh -------------------------------------

if ($cacheEnabled && empty($_GET['refresh']) && is_readable($cacheFile)
    && (time() - filemtime($cacheFile)) < $cacheTtl) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Cache: HIT');
    echo file_get_contents($cacheFile);
    exit;
}

// --- Helpers --------------------------------------------------------

function fetchFeed($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; NewsAggregator/1.0)',
    ]);
    $body     = curl_exec($ch);
    $err      = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $body === '') {
        return ['xml' => null, 'error' => $err ?: 'empty response'];
    }
    if ($httpCode === 403 || $httpCode === 503 || stripos($body, 'Just a moment') !== false) {
        return ['xml' => null, 'error' => "blocked (HTTP $httpCode)"];
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body);
    libxml_clear_errors();

    if ($xml === false) {
        return ['xml' => null, 'error' => 'invalid XML'];
    }
    return ['xml' => $xml, 'error' => null];
}

function htmlToText($html)
{
    if (!$html) {
        return null;
    }
    $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#</p>#i', "</p>\n\n", $html);
    $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return $text !== '' ? $text : null;
}

// Fetch the article page; return full body text + og:image.
function enrichFromPage($pageUrl)
{
    $ch = curl_init($pageUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
            . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => ['Accept-Language: en-US,en;q=0.9'],
    ]);
    $html     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($html === false || $html === '' || $httpCode === 403 || $httpCode === 503) {
        return ['text' => null, 'image' => null];
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);

    $image = null;
    foreach (['//meta[@property="og:image"]/@content',
              '//meta[@name="og:image"]/@content',
              '//meta[@name="twitter:image"]/@content'] as $q) {
        $n = $xpath->query($q);
        if ($n && $n->length > 0) {
            $image = trim($n->item(0)->nodeValue);
            break;
        }
    }

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

    return ['text' => $text !== '' ? $text : null, 'image' => $image];
}

// --- Pull every feed (discovery only) -------------------------------

$candidates = [];
$feedReport = [];

foreach ($feeds as $name => $url) {
    $res = fetchFeed($url);

    if ($res['xml'] === null) {
        $feedReport[$name] = 'failed: ' . $res['error'];
        continue;
    }

    $items = $res['xml']->channel->item ?? [];
    $count = 0;

    foreach ($items as $item) {
        if ($count >= $perFeedLimit) {
            break;
        }
        $pubTs = strtotime((string) $item->pubDate) ?: 0;
        $candidates[] = [
            'title'       => trim((string) $item->title),
            'description' => htmlToText((string) $item->description),
            'source'      => $name,
            'url'         => trim((string) $item->link),
            'pub_ts'      => $pubTs,
        ];
        $count++;
    }

    $feedReport[$name] = "ok ($count items)";
}

// Newest first
usort($candidates, fn($a, $b) => $b['pub_ts'] <=> $a['pub_ts']);

// --- Enrich down the list, keep only full "page" articles -----------

$articles = [];
$enriched = 0;

foreach ($candidates as $c) {
    if (count($articles) >= $targetCount || $enriched >= $maxEnrich) {
        break;
    }
    if (empty($c['url'])) {
        continue;
    }

    $page = enrichFromPage($c['url']);
    $enriched++;

    // Keep only articles whose full body we actually got from the page
    if ($page['text'] === null) {
        continue;
    }

    $articles[] = [
        'title'          => $c['title'],
        'description'    => $c['description'],
        'source'         => $c['source'],
        'image'          => $page['image'],
        'url'            => $c['url'],
        'published_at'   => $c['pub_ts'] ? date('c', $c['pub_ts']) : null,
        'fullcontent'    => $page['text'],
        'content_source' => 'page',
    ];
}

// --- Output ---------------------------------------------------------

// Count how many of the final articles came from each source
$sources = [];
foreach ($articles as $a) {
    $sources[$a['source']] = ($sources[$a['source']] ?? 0) + 1;
}
arsort($sources); // most-contributing source first

$output = [
    'count'         => count($articles),
    'generated_at'  => date('c'),
    'pages_fetched' => $enriched,
    'sources'       => $sources,   // how many articles per paper in this result
    'feeds'         => $feedReport, // raw feed fetch status
    'articles'      => $articles,
];

$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if ($cacheEnabled) {
    @file_put_contents($cacheFile, $json, LOCK_EX);
}

header('Content-Type: application/json; charset=utf-8');
header('X-Cache: MISS');
echo $json;