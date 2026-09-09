<?php
/**
 * Nigerian daily news via RSS feeds.
 *
 * Why RSS instead of mediastack + scraping:
 *  - Free, no API key, no monthly request cap.
 *  - Open feeds aren't Cloudflare-blocked (no 403 like scraping article pages).
 *  - WordPress feeds expose <content:encoded>, which is usually the FULL article body.
 *
 * Caveat: only works for papers with open feeds. Cloudflare-gated sites
 * (Tribune, Guardian, Punch) will report "blocked" — those still need a
 * paid scraping API. The script tells you which feeds worked.
 *
 * Run:  php nigeria_news_rss.php   (or open in a browser)
 */

set_time_limit(120);

// --- Config ---------------------------------------------------------

// Feeds to pull from. Add/remove freely. Keys are just labels.
$feeds = [
    'Premium Times' => 'https://www.premiumtimesng.com/feed',
    'Vanguard'      => 'https://www.vanguardngr.com/feed',
    'Nairametrics'  => 'https://nairametrics.com/feed',
    'Daily Post'    => 'https://dailypost.ng/feed',
    'The Cable'     => 'https://www.thecable.ng/feed',
    'PM News'       => 'https://www.pmnewsnigeria.com/feed',
];

$perFeedLimit = 30;  // max items to take from each feed
$totalLimit   = 80;  // max items in the final output

// Enrichment: fetch each article page for full body text + og:image.
// Open-feed sites (Premium Times, Nairametrics, The Cable, PM News) allow this
// with plain curl. Slow (one request per article), so cap it.
$enrichFullContent = true;
$enrichLimit       = 15;  // how many of the newest articles to enrich per run

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
        return ['xml' => null, 'error' => $err ?: 'empty response', 'http_code' => $httpCode];
    }
    if ($httpCode === 403 || $httpCode === 503 || stripos($body, 'Just a moment') !== false) {
        return ['xml' => null, 'error' => "blocked (HTTP $httpCode)", 'http_code' => $httpCode];
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body);
    libxml_clear_errors();

    if ($xml === false) {
        return ['xml' => null, 'error' => 'invalid XML', 'http_code' => $httpCode];
    }
    return ['xml' => $xml, 'error' => null, 'http_code' => $httpCode];
}

// Turn HTML into clean plain-text paragraphs
function htmlToText($html)
{
    if (!$html) {
        return null;
    }
    $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html);
    // Keep paragraph breaks
    $html = preg_replace('#</p>#i', "</p>\n\n", $html);
    $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return $text !== '' ? $text : null;
}

// Pull an image URL from the item (media:content, enclosure, or first <img>)
function extractImage($item, $contentHtml)
{
    $media = $item->children('http://search.yahoo.com/mrss/');
    if (isset($media->content) && $media->content->attributes()->url) {
        return (string) $media->content->attributes()->url;
    }
    if (isset($media->thumbnail) && $media->thumbnail->attributes()->url) {
        return (string) $media->thumbnail->attributes()->url;
    }
    if (isset($item->enclosure) && $item->enclosure->attributes()->url) {
        return (string) $item->enclosure->attributes()->url;
    }
    if ($contentHtml && preg_match('#<img[^>]+src=["\']([^"\']+)["\']#i', $contentHtml, $m)) {
        return $m[1];
    }
    return null;
}

// Fetch the article page and extract full body text + image (og:image).
// Returns ['text' => ?, 'image' => ?, 'status' => string].
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
        return ['text' => null, 'image' => null, 'status' => "page blocked (HTTP $httpCode)"];
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);

    // Image: og:image, then twitter:image
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

    // Strip noise before pulling paragraphs
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

    return [
        'text'   => $text !== '' ? $text : null,
        'image'  => $image,
        'status' => $text !== '' ? 'page' : "no paragraphs found (HTTP $httpCode)",
    ];
}

// --- Pull every feed ------------------------------------------------

$articles    = [];
$feedReport  = [];

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

        // Full body lives in <content:encoded>; description is the summary
        $contentNs   = $item->children('http://purl.org/rss/1.0/modules/content/');
        $contentHtml = isset($contentNs->encoded) ? (string) $contentNs->encoded : '';
        $summaryHtml = (string) $item->description;

        $fullText = htmlToText($contentHtml ?: $summaryHtml);
        $pubTs    = strtotime((string) $item->pubDate) ?: 0;

        $articles[] = [
            'title'        => trim((string) $item->title),
            'description'  => htmlToText($summaryHtml),
            'source'       => $name,
            'image'        => extractImage($item, $contentHtml),
            'url'          => trim((string) $item->link),
            'published_at' => $pubTs ? date('c', $pubTs) : null,
            '_ts'          => $pubTs,
            'fullcontent'  => $fullText,
            'has_full'     => $contentHtml !== '',  // true = real body, false = summary only
        ];
        $count++;
    }

    $feedReport[$name] = "ok ($count items)";
}

// Newest first, then trim to the global limit
usort($articles, fn($a, $b) => $b['_ts'] <=> $a['_ts']);
$articles = array_slice($articles, 0, $totalLimit);

// Enrich the newest articles with full page body + og:image
foreach ($articles as $i => &$a) {
    $a['content_source'] = $a['has_full'] ? 'rss-full' : 'rss-summary';

    if ($enrichFullContent && $i < $enrichLimit && !empty($a['url'])) {
        $page = enrichFromPage($a['url']);
        if ($page['text'] !== null) {
            $a['fullcontent']    = $page['text'];   // override summary with real body
            $a['content_source'] = 'page';
        }
        if (empty($a['image']) && !empty($page['image'])) {
            $a['image'] = $page['image'];
        }
        if ($page['text'] === null) {
            $a['enrich_note'] = $page['status'];
        }
    }

    unset($a['_ts'], $a['has_full']);
}
unset($a);

// --- Output ---------------------------------------------------------

$output = [
    'count'    => count($articles),
    'feeds'    => $feedReport,
    'articles' => $articles,
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);