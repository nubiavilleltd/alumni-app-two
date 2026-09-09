<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * News controller — Nigerian news aggregator.
 *
 * Endpoint:  GET|POST /news/feeds
 * Auth:      X-API-Key header (same alumni_key as the Api controller)
 *
 * Pulls the latest Nigerian news from open RSS feeds, then enriches each
 * article from its own page to get the FULL body text + og:image. Only
 * articles with full content AND an image are returned.
 *
 * Self-contained: replicates the Api controller's token-check helpers so
 * it can live on its own without depending on Api.php.
 */
class News extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /*=======================================================
        NEWS FEEDS — AGGREGATE NIGERIAN NEWS
        GET or POST /news/feeds
        Auth: API token only (public read — no JWT)

        Optional params (query string or JSON body):
          limit   = number of articles to return (default 50, max 100)
          refresh = 1  → bypass cache and rebuild

        Response shape:
          status, message, count, generated_at, pages_fetched,
          sources { "Premium Times": 9, ... },   // per-paper counts
          feeds   { "Premium Times": "ok (20 items)", ... }, // fetch status
          articles [ { title, description, source, image, url,
                       published_at, fullcontent, content_source } ]
    ========================================================*/
    public function feeds()
    {
        $contentType = $this->input->server('CONTENT_TYPE');
        if (strpos($contentType, 'application/json') !== false) {
            $object = json_decode(file_get_contents('php://input'), true);
        } else {
            // Merge query-string (GET) and form-data (POST) params
            $object = array_merge(
                $this->input->get() ?: [],
                $this->input->post() ?: []
            );
        }
        $object = is_array($object) ? $object : [];

        // --- API token ---
        $token = isset($object['token']) ? trim($object['token']) : '';
        if (!$this->checkAPI_token_from_header()) {
            $this->output->set_status_header(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return;
        }

        // --- Config ---
        // Cloudflare-gated feeds (Vanguard, Daily Post, Guardian) report
        // "blocked" in the feeds map and are simply skipped.
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

        $perFeedLimit = 20;
        $targetCount  = isset($object['limit'])
            ? min(max(intval($object['limit']), 1), 100)
            : 50;
        $maxEnrich    = 110;   // safety cap on page fetches per build
        $requireImage = true;  // only keep articles that have an image

        // --- Cache (per requested limit) ---
        $cacheTtl     = 900;   // 15 minutes
        $cacheDir     = APPPATH . 'cache/';
        $cacheFile    = $cacheDir . 'news_feeds_' . $targetCount . '.json';
        $forceRefresh = !empty($object['refresh']);

        if (!$forceRefresh && is_readable($cacheFile)
            && (time() - filemtime($cacheFile)) < $cacheTtl) {
            $this->output->set_status_header(200);
            header('Content-Type: application/json');
            header('X-Cache: HIT');
            echo file_get_contents($cacheFile);
            return;
        }

        // Building can fetch many pages — give it room
        @set_time_limit(180);

        // --- Pull every feed (discovery only) ---
        $candidates = [];
        $feedReport = [];

        foreach ($feeds as $name => $url) {
            $res = $this->_fetchNewsFeed($url);

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
                    'description' => $this->_newsHtmlToText((string) $item->description),
                    'source'      => $name,
                    'url'         => trim((string) $item->link),
                    'pub_ts'      => $pubTs,
                ];
                $count++;
            }

            $feedReport[$name] = "ok ($count items)";
        }

        // Newest first across all feeds
        usort($candidates, function ($a, $b) {
            return $b['pub_ts'] <=> $a['pub_ts'];
        });

        // --- Enrich down the list, keep only full "page" articles ---
        $articles = [];
        $enriched = 0;

        foreach ($candidates as $c) {
            if (count($articles) >= $targetCount || $enriched >= $maxEnrich) {
                break;
            }
            if (empty($c['url'])) {
                continue;
            }

            $page = $this->_enrichNewsPage($c['url']);
            $enriched++;

            // Skip anything we couldn't pull a full body for
            if ($page['text'] === null) {
                continue;
            }
            // Optionally require an image
            if ($requireImage && empty($page['image'])) {
                continue;
            }

            $articles[] = [
                'id'             => substr(md5($c['url']), 0, 12),
                'slug'           => $this->_slugify($c['title']),
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

        // Per-source rollup of what made it into the result
        $sources = [];
        foreach ($articles as $a) {
            $sources[$a['source']] = ($sources[$a['source']] ?? 0) + 1;
        }
        arsort($sources);

        // --- Output ---
        $output = [
            'status'        => 200,
            'message'       => 'News feeds retrieved successfully',
            'count'         => count($articles),
            'generated_at'  => date('c'),
            'pages_fetched' => $enriched,
            'sources'       => $sources,
            'feeds'         => $feedReport,
            'articles'      => $articles,
        ];

        $json = json_encode(
            $output,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        // Best-effort cache write (ignore failures)
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            @file_put_contents($cacheFile, $json, LOCK_EX);
        }

        $this->output->set_status_header(200);
        header('Content-Type: application/json');
        header('X-Cache: MISS');
        echo $json;
    }

    /*──────────────────────────────────────────────────────────
    |  Private: fetch + parse a single RSS feed
    |  Returns ['xml' => SimpleXMLElement|null, 'error' => string|null]
    ──────────────────────────────────────────────────────────*/
    private function _fetchNewsFeed($url)
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

    /*──────────────────────────────────────────────────────────
    |  Private: convert HTML to clean plain-text paragraphs
    ──────────────────────────────────────────────────────────*/
    private function _newsHtmlToText($html)
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

    /*──────────────────────────────────────────────────────────
    |  Private: fetch an article page → full body text + og:image
    |  Returns ['text' => string|null, 'image' => string|null]
    ──────────────────────────────────────────────────────────*/
    private function _enrichNewsPage($pageUrl)
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

        // Image: og:image, then twitter:image
        $image = null;
        foreach ([
            '//meta[@property="og:image"]/@content',
            '//meta[@name="og:image"]/@content',
            '//meta[@name="twitter:image"]/@content',
        ] as $q) {
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

        // Prefer a known article container, fall back to whole document
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
            'text'  => $text !== '' ? $text : null,
            'image' => $image,
        ];
    }

    /*──────────────────────────────────────────────────────────
    |  Private: build a URL-friendly slug from a title
    ──────────────────────────────────────────────────────────*/
    private function _slugify($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(strip_tags($text));

        // Transliterate accented characters to ASCII where possible
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        $text = strtolower($text);
        $text = preg_replace('~[^a-z0-9]+~', '-', $text); // non-alnum → hyphen
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);         // collapse repeats

        if ($text === '') {
            return 'article';
        }
        // Keep slugs reasonable in length
        return substr($text, 0, 80);
    }
}