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
        Content-Type: application/json  (or query string / form-data)

        AUTH:     X-API-Key header only — a "token" field in the body is
                  NOT read. Public read, no JWT.

        REQUIRED: none
        OPTIONAL: category, limit, refresh
        --------------------------------------------------------
        {
            "category": ["business", "sports"],
            "limit": 20,
            "refresh": 1
        }
        --------------------------------------------------------

        category = topic filter. ONE OR MANY of:
                     all, business, entertainment, sports,
                     politics, technology, health, education

                   Many can be sent as a JSON array
                     "category": ["business", "sports"]
                   or as a delimited string (comma, pipe or semicolon)
                     ?category=business,sports

                   OMITTED → the admin's default is used instead:
                     SELECT setup_value FROM setup_parameters
                      WHERE setup_name = 'news_category'   -- "financies;sports"
                   and if that row is missing or blank → "all".
                   A bad slug in that row is skipped (reported in
                   ignored_categories), not 400'd — the caller did not
                   send it. category_source in the response says which of
                   request | setup_parameters | default was used.
                   Multiple topics are OR-ed: an article is kept if it
                   belongs to any of them, and articles[].matched_categories
                   says which. "all" anywhere in the list wins (no filter).
                   Order does not matter — slugs are sorted, so
                   sports,business and business,sports share a cache entry.
                   Aliases (case-insensitive):
                     finance | financies | money | economy | markets
                                                    → business
                     showbiz | celebrity | nollywood | music | lifestyle
                                                    → entertainment
                     sport | football               → sports
                     tech | science                 → technology
                     political | government         → politics
                     medical | wellness             → health
                     school | schools | campus      → education
                   Each picked topic pulls its papers' dedicated feeds AND
                   keyword-filters the general feeds, so a paper with no
                   topic feed still contributes.

                   FREE KEYWORDS: anything that is not one of the slugs or
                   aliases above is used as a search keyword instead of
                   being rejected, so the taxonomy is no longer a ceiling:
                     ?category=women;Vocation
                     "category": ["women", "vocational training"]
                   Keywords come back in the `keywords` field, are matched
                   case-insensitively and whole-word against the item's own
                   RSS tags, its URL path and its headline + summary, and
                   are OR-ed with each other and with any topics — so
                   business;women is "business OR women", not "both".
                   A keyword of 5+ characters also matches up to 3 trailing
                   letters, so Vocation finds vocations and vocational;
                   shorter ones stay exact so art cannot hit artist.
                   Keywords narrow even alongside "all" ("all;women" reads
                   as anything about women), and articles[].matched_
                   categories names the keyword that hit.
                   Only unusable input still 400s: a value with no letter
                   or digit in it, or one longer than 60 characters.

        limit    = articles to return. Default 50, clamped to 1..100.

        refresh  = 1 → bypass the 15-minute cache and rebuild. Cache is
                   keyed per category + limit. Slow: a cold build fetches
                   every article page (30–90s).

        RESPONSE 200:
          status, message, count,
          category,            // "all", or "business,sports,women"
          selected_categories, // ["business","sports"] or ["all"]
          keywords,            // ["women","vocation"] — free keywords
          category_source,     // request | setup_parameters | default
          ignored_categories,  // bad slugs skipped in the setup row
          available_categories,
          generated_at, pages_fetched,
          sources { "Premium Times": 9, ... },        // per-paper counts
          feeds   { "Premium Times": "ok (20 items matching business)",
                    "Vanguard": "failed: blocked (HTTP 403)", ... },
          articles [ { id, slug, title, description, source, image, url,
                       published_at, fullcontent, content_source,
                       categories,          // the paper's own RSS tags
                       matched_categories } ] // topics AND keywords it hit

        RESPONSE 400: { status, message: "Unknown categor(y|ies)",
                        invalid_categories, available_categories }
        RESPONSE 401: { status, message: "Invalid API token" }
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

        // --- Category (optional, one or many) ---
        // Unknown/renamed topic feeds simply come back "failed" in the feeds
        // map and are skipped, so an added URL can never break the endpoint.
        $catDefs = $this->_newsCategoryDefs();

        // A category sent by the caller always wins. With none sent, fall
        // back to the `news_category` row in setup_parameters (a ";" list
        // the admin edits, e.g. "financies;sports"), then to "all".
        $sentCategory = isset($object['category'])
            && (is_array($object['category'])
                ? $object['category'] !== []
                : trim((string) $object['category']) !== '');

        if ($sentCategory) {
            $requested      = $object['category'];
            $categorySource = 'request';
        } else {
            $requested      = $this->_newsSetupCategories();
            $categorySource = $requested !== '' ? 'setup_parameters' : 'default';
        }

        $picked = $this->_newsParseCategories($requested);

        // Anything that is not a known topic is now kept as a free keyword,
        // so 'invalid' only ever holds unusable input (punctuation-only, or
        // over 60 chars). From the caller's own param that is their error and
        // 400s; from the admin's setup row it must not break an innocent
        // request, so it is skipped and reported in ignored_categories.
        $ignoredCategories = [];
        if (!empty($picked['invalid']) && $categorySource !== 'request') {
            $ignoredCategories   = $picked['invalid'];
            $picked['invalid']   = [];
        }

        if (!empty($picked['invalid'])) {
            $this->output->set_status_header(400);
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 400,
                'message' => count($picked['invalid']) === 1
                    ? 'Unknown category'
                    : 'Unknown categories',
                'invalid_categories'   => $picked['invalid'],
                'available_categories' => array_merge(['all'], array_keys($catDefs)),
            ]);
            return;
        }

        // Empty topics AND empty keywords == "all" == no filtering
        $selected = $picked['categories'];
        $keywords = $picked['keywords'];
        $filters  = array_merge($selected, $keywords);
        $category = $filters ? implode(',', $filters) : 'all';

        // Dedicated topic feeds for every picked topic are pulled first; the
        // general feeds still run and get filtered, keeping volume up for
        // papers with no topic feed. $catFeedNames maps feed name -> topic,
        // so an item off a topic feed is in-category without any matching.
        $catFeedNames = [];
        $topicFeeds   = [];
        foreach ($selected as $slug) {
            foreach ($catDefs[$slug]['feeds'] as $cName => $cUrl) {
                $catFeedNames[$cName] = $slug;
                $topicFeeds[$cName]   = $cUrl;
            }
        }
        if ($topicFeeds) {
            $feeds = array_merge($topicFeeds, $feeds);
        }

        $perFeedLimit = 20;
        $targetCount  = isset($object['limit'])
            ? min(max(intval($object['limit']), 1), 100)
            : 50;
        $maxEnrich    = 110;   // safety cap on page fetches per build
        $requireImage = true;  // only keep articles that have an image

        // --- Cache (per requested limit) ---
        $cacheTtl     = 900;   // 15 minutes
        $cacheDir     = APPPATH . 'cache/';
        // Slugs are sorted, so ?category=sports,business shares one cache
        // entry with ?category=business,sports
        $cacheKey     = $selected ? implode('-', $selected) : 'all';
        // Keywords are arbitrary text, so they are hashed rather than pasted
        // into a filename — sorted too, so the entry is order-independent
        if ($keywords) {
            $cacheKey .= '-kw' . substr(md5(implode(',', $keywords)), 0, 10);
        }
        $cacheFile    = $cacheDir . 'news_feeds_' . $cacheKey . '_' . $targetCount . '.json';
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
        $candidates     = [];
        $candidateIndex = [];   // url key -> index in $candidates, for de-duping
        $feedReport     = [];

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
                $itemCats = [];
                if (isset($item->category)) {
                    foreach ($item->category as $cat) {
                        $cat = trim(html_entity_decode((string) $cat, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        if ($cat !== '') {
                            $itemCats[] = $cat;
                        }
                    }
                }
                $itemCats = array_values(array_unique($itemCats));

                $pubTs = strtotime((string) $item->pubDate) ?: 0;
                $candidate = [
                    'title'       => trim((string) $item->title),
                    'description' => $this->_newsHtmlToText((string) $item->description),
                    // Strip the "(Business)" style suffix off topic feeds so
                    // the paper reads the same however the item was found
                    'source'      => trim(preg_replace('/\s*\([^)]*\)$/', '', $name)),
                    'url'         => trim((string) $item->link),
                    'pub_ts'      => $pubTs,
                    'categories'  => $itemCats,
                ];

                // Keep the item if it hits ANY picked topic OR any keyword.
                // An item off a dedicated topic feed is in-category already.
                $matched = [];
                if (isset($catFeedNames[$name])) {
                    $matched[] = $catFeedNames[$name];
                }
                foreach ($selected as $slug) {
                    if (!in_array($slug, $matched, true)
                        && $this->_newsMatchesCategory($candidate, $catDefs[$slug])) {
                        $matched[] = $slug;
                    }
                }
                // Keywords have no feeds of their own, so they are matched
                // against everything the general and topic feeds bring in
                foreach ($keywords as $kw) {
                    if ($this->_newsMatchesKeyword($candidate, $kw)) {
                        $matched[] = $kw;
                    }
                }
                if ($filters && !$matched) {
                    continue;
                }
                $candidate['matched_categories'] = $matched;

                // The same story can arrive from several topic feeds — merge
                // the topics onto the first copy instead of keeping both
                $dupKey = strtolower(rtrim($candidate['url'], '/'));
                if ($dupKey !== '' && isset($candidateIndex[$dupKey])) {
                    $prev = $candidateIndex[$dupKey];
                    $candidates[$prev]['matched_categories'] = array_values(array_unique(
                        array_merge($candidates[$prev]['matched_categories'], $matched)
                    ));
                    $count++;
                    continue;
                }

                $candidateIndex[$dupKey] = count($candidates);
                $candidates[] = $candidate;
                $count++;
            }

            $feedReport[$name] = $filters
                ? "ok ($count items matching " . implode(', ', $filters) . ")"
                : "ok ($count items)";
        }

        // Newest first across all feeds
        usort($candidates, function ($a, $b) {
            return $b['pub_ts'] <=> $a['pub_ts'];
        });

        // --- Enrich down the list, keep only full "page" articles ---
        $articles = [];
        $enriched = 0;
        $seenUrls = [];

        foreach ($candidates as $c) {
            if (count($articles) >= $targetCount || $enriched >= $maxEnrich) {
                break;
            }
            if (empty($c['url'])) {
                continue;
            }
            // A story can arrive from both the general and the topic feed
            $urlKey = strtolower(rtrim($c['url'], '/'));
            if (isset($seenUrls[$urlKey])) {
                continue;
            }
            $seenUrls[$urlKey] = true;

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
                'categories'     => $c['categories'],
                'matched_categories' => $c['matched_categories'],
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
            'category'      => $category,
            'selected_categories'  => $selected ?: ($keywords ? [] : ['all']),
            'keywords'             => $keywords,
            'category_source'      => $categorySource,
            'ignored_categories'   => $ignoredCategories,
            'available_categories' => array_merge(['all'], array_keys($catDefs)),
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
    |  Private: topic definitions used to streamline the feed
    |
    |  feeds    = dedicated topic RSS endpoints, each verified live; anything
    |             that later 404s/blocks is reported in the feeds map and
    |             skipped, so the endpoint degrades to keyword filtering.
    |             Cloudflare-gated papers (Vanguard, Daily Post, The Cable,
    |             Channels TV, The Nation) block topic feeds the same way
    |             they block their main feed, so none are listed here.
    |  tags     = matched against the item's own <category> elements
    |  paths    = matched against the article URL path
    |  keywords = matched as whole words against title + description
    ──────────────────────────────────────────────────────────*/
    private function _newsCategoryDefs()
    {
        return [
            'business' => [
                'feeds' => [
                    'Premium Times (Business)' => 'https://www.premiumtimesng.com/category/business/feed',
                    'Nairametrics (Business)'  => 'https://nairametrics.com/category/business-news/feed',
                    'Nairametrics (Markets)'   => 'https://nairametrics.com/category/market-news/feed',
                    'PM News (Business)'       => 'https://www.pmnewsnigeria.com/category/business/feed',
                    'Legit (Business)'         => 'https://www.legit.ng/rss/business-economy.rss',
                    'Legit (Money)'            => 'https://www.legit.ng/rss/money.rss',
                ],
                'tags'  => ['business', 'economy', 'finance', 'financial', 'money', 'market', 'markets', 'banking', 'oil', 'energy', 'agriculture', 'real estate'],
                'paths' => ['/business', '/economy', '/finance', '/money', '/market'],
                'keywords' => ['naira', 'dollar', 'inflation', 'gdp', 'cbn', 'central bank', 'economy', 'economic', 'stock', 'stocks', 'shares', 'investors', 'investment', 'bank', 'banks', 'banking', 'revenue', 'profit', 'earnings', 'tax', 'taxes', 'budget', 'trade', 'exports', 'imports', 'oil price', 'crude', 'fuel price', 'subsidy', 'fintech', 'startup funding', 'ipo', 'bond', 'bonds', 'interest rate', 'exchange rate', 'forex', 'crypto', 'bitcoin', 'nse', 'ngx', 'debt', 'loan', 'loans', 'salary', 'wage', 'business'],
            ],
            'entertainment' => [
                'feeds' => [
                    'Premium Times (Entertainment)' => 'https://www.premiumtimesng.com/category/entertainment/feed',
                    'PM News (Entertainment)'       => 'https://www.pmnewsnigeria.com/category/entertainment/feed',
                    'Legit (Entertainment)'         => 'https://www.legit.ng/rss/entertainment.rss',
                ],
                'tags'  => ['entertainment', 'celebrity', 'celebrities', 'nollywood', 'music', 'movies', 'film', 'showbiz', 'lifestyle', 'arts', 'culture'],
                'paths' => ['/entertainment', '/celebrity', '/nollywood', '/music', '/lifestyle', '/showbiz'],
                'keywords' => ['nollywood', 'afrobeats', 'singer', 'rapper', 'actress', 'actor', 'movie', 'movies', 'film', 'album', 'concert', 'grammy', 'amvca', 'headies', 'big brother', 'bbnaija', 'celebrity', 'dj', 'skit maker', 'influencer', 'reality show', 'box office', 'netflix', 'premiere', 'wizkid', 'davido', 'burna boy', 'tiwa savage', 'olamide', 'asake', 'rema', 'ayra starr'],
            ],
            'sports' => [
                'feeds' => [
                    'Premium Times (Sports)' => 'https://www.premiumtimesng.com/category/sports/feed',
                    'PM News (Sports)'       => 'https://www.pmnewsnigeria.com/category/sports/feed',
                    'Legit (Sports)'         => 'https://www.legit.ng/rss/sports.rss',
                ],
                'tags'  => ['sport', 'sports', 'football', 'soccer', 'athletics', 'boxing', 'basketball'],
                'paths' => ['/sport', '/sports', '/football'],
                'keywords' => ['super eagles', 'afcon', 'fifa', 'caf', 'nff', 'npfl', 'premier league', 'la liga', 'serie a', 'bundesliga', 'champions league', 'world cup', 'football', 'footballer', 'striker', 'midfielder', 'goalkeeper', 'coach', 'transfer window', 'olympics', 'athletics', 'boxing', 'basketball', 'nba', 'tennis', 'wrestling', 'derby', 'fixture', 'fixtures', 'kick-off', 'goal', 'goals', 'trophy'],
            ],
            'politics' => [
                'feeds' => [
                    'Premium Times (Politics)' => 'https://www.premiumtimesng.com/category/news/politics/feed',
                    'PM News (Politics)'       => 'https://www.pmnewsnigeria.com/category/politics/feed',
                    'Legit (Politics)'         => 'https://www.legit.ng/rss/politics.rss',
                ],
                'tags'  => ['politics', 'political', 'government', 'election', 'elections', 'governance'],
                'paths' => ['/politics', '/election', '/government'],
                'keywords' => ['inec', 'apc', 'pdp', 'labour party', 'senate', 'senator', 'house of representatives', 'national assembly', 'governor', 'governors', 'president', 'presidency', 'tinubu', 'minister', 'ministry', 'election', 'elections', 'campaign', 'primaries', 'ballot', 'impeachment', 'lawmaker', 'lawmakers', 'defection', 'constitution', 'bill', 'motion'],
            ],
            'technology' => [
                'feeds' => [
                    'Nairametrics (Tech)'  => 'https://nairametrics.com/category/tech-news/feed',
                    'Premium Times (Tech)' => 'https://www.premiumtimesng.com/category/news/technology/feed',
                    'Legit (Tech)'         => 'https://www.legit.ng/rss/technology.rss',
                ],
                'tags'  => ['tech', 'technology', 'science', 'gadgets', 'telecoms', 'innovation'],
                'paths' => ['/tech', '/technology', '/science', '/gadget'],
                'keywords' => ['tech', 'technology', 'startup', 'startups', 'app', 'software', 'hardware', 'smartphone', 'iphone', 'android', 'google', 'microsoft', 'apple', 'meta', 'openai', 'artificial intelligence', 'ai', 'machine learning', 'data centre', 'data center', 'cybersecurity', 'hackers', 'hacked', 'broadband', 'telecom', 'telecoms', 'mtn', 'airtel', 'glo', 'ncc', '5g', 'internet', 'developers', 'blockchain', 'satellite'],
            ],
            'health' => [
                'feeds' => [
                    'Premium Times (Health)' => 'https://www.premiumtimesng.com/category/news/health/feed',
                    'PM News (Health)'       => 'https://www.pmnewsnigeria.com/category/health/feed',
                ],
                'tags'  => ['health', 'healthcare', 'medical', 'medicine', 'wellness'],
                'paths' => ['/health', '/medical'],
                'keywords' => ['health', 'hospital', 'hospitals', 'doctor', 'doctors', 'nurse', 'nurses', 'patients', 'disease', 'outbreak', 'epidemic', 'cholera', 'malaria', 'lassa fever', 'covid', 'vaccine', 'vaccination', 'nafdac', 'nphcda', 'mental health', 'cancer', 'hiv', 'tuberculosis', 'maternal', 'drugs', 'clinic', 'surgery', 'immunisation', 'immunization'],
            ],
            'education' => [
                'feeds' => [
                    'Premium Times (Education)' => 'https://www.premiumtimesng.com/category/news/education/feed',
                    'PM News (Education)'       => 'https://www.pmnewsnigeria.com/category/education/feed',
                    'Legit (Education)'         => 'https://www.legit.ng/rss/education.rss',
                ],
                'tags'  => ['education', 'school', 'schools', 'campus', 'students'],
                'paths' => ['/education', '/campus', '/school'],
                'keywords' => ['jamb', 'waec', 'neco', 'utme', 'asuu', 'nysc', 'university', 'universities', 'polytechnic', 'college', 'undergraduate', 'postgraduate', 'student', 'students', 'pupils', 'school', 'schools', 'lecturer', 'lecturers', 'vice-chancellor', 'admission', 'admissions', 'scholarship', 'scholarships', 'tuition', 'curriculum', 'exam', 'exams', 'graduation', 'alumni', 'tetfund'],
            ],
        ];
    }

    /*──────────────────────────────────────────────────────────
    |  Private: normalise a requested category (handles aliases)
    |  Returns the canonical slug, 'all', or null when unknown
    ──────────────────────────────────────────────────────────*/
    private function _newsCanonicalCategory($requested)
    {
        $requested = strtolower(trim((string) $requested));
        if ($requested === '' || $requested === 'all' || $requested === 'general') {
            return 'all';
        }

        $aliases = [
            'finance'    => 'business', 'financial' => 'business',
            'financies'  => 'business', 'money'     => 'business',
            'economy'    => 'business', 'economics' => 'business',
            'market'     => 'business', 'markets'   => 'business',
            'showbiz'    => 'entertainment', 'celebrity' => 'entertainment',
            'nollywood'  => 'entertainment', 'lifestyle' => 'entertainment',
            'music'      => 'entertainment',
            'sport'      => 'sports', 'football' => 'sports',
            'tech'       => 'technology', 'science' => 'technology',
            'political'  => 'politics', 'government' => 'politics',
            'medical'    => 'health', 'wellness' => 'health',
            'school'     => 'education', 'schools' => 'education',
            'campus'     => 'education',
        ];
        if (isset($aliases[$requested])) {
            return $aliases[$requested];
        }

        $defs = $this->_newsCategoryDefs();
        return isset($defs[$requested]) ? $requested : null;
    }

    /*──────────────────────────────────────────────────────────
    |  Private: the admin's default topics, from setup_parameters
    |
    |    SELECT setup_value FROM setup_parameters
    |     WHERE setup_name = 'news_category'      -- e.g. "financies;sports"
    |
    |  Used only when the caller sends no category of their own. Returns the
    |  raw string for _newsParseCategories() to split; '' when the row is
    |  missing, blank, or the table is not there, which means "all".
    ──────────────────────────────────────────────────────────*/
    private function _newsSetupCategories()
    {
        if (!isset($this->db) || !$this->db->table_exists('setup_parameters')) {
            return '';
        }

        $row = $this->db
            ->select('setup_value')
            ->get_where('setup_parameters', ['setup_name' => 'news_category'], 1)
            ->row();

        return $row ? trim((string) $row->setup_value) : '';
    }

    /*──────────────────────────────────────────────────────────
    |  Private: parse the `category` param into canonical slugs
    |
    |  Accepts one topic or many, as either a JSON array
    |    "category": ["business", "sports"]
    |  or a delimited string (comma, pipe, semicolon or newline)
    |    ?category=business,sports
    |
    |  Anything that is not a known topic or alias is kept as a FREE
    |  KEYWORD rather than rejected, so the admin can narrow the feed with
    |  words the taxonomy has no topic for:
    |    "category": "women;Vocation"
    |
    |  Returns:
    |    ['categories' => [...sorted unique slugs...],
    |     'keywords'   => [...sorted unique lowercase phrases...],
    |     'invalid'    => [...]]
    |  'invalid' now only holds input no keyword could be built from (no
    |  letter or digit in it, or longer than 60 characters).
    |  An empty 'categories' AND an empty 'keywords' means "all" — no
    |  filtering. "all" wins over any topic, since it is a superset of
    |  every one of them, but it does NOT clear keywords: "all;women"
    |  reads as "anything about women", which is the only useful meaning
    |  it can have.
    ──────────────────────────────────────────────────────────*/
    private function _newsParseCategories($requested)
    {
        if (is_array($requested)) {
            $parts = $requested;
        } else {
            // \r and \n included: setup_parameters values are often edited
            // as one-per-line in the admin textarea
            $parts = preg_split('/[,|;\r\n]+/', (string) $requested);
        }

        $slugs    = [];
        $keywords = [];
        $invalid  = [];
        $sawAll   = false;

        // Every part is read even once "all" is seen, so "all,women" still
        // picks the keyword up rather than silently swallowing it
        foreach ($parts as $part) {
            if (is_array($part) || is_object($part)) {
                continue;
            }
            $raw = trim((string) $part);
            if ($raw === '') {
                continue;
            }

            $slug = $this->_newsCanonicalCategory($raw);
            if ($slug === 'all') {
                $sawAll = true;
                continue;
            }
            if ($slug !== null) {
                $slugs[$slug] = true;
                continue;
            }

            // Not a topic — treat it as a free keyword. Inner whitespace is
            // collapsed so "women  in tech" and "women in tech" are one key.
            $kw = strtolower(trim(preg_replace('/\s+/u', ' ', $raw)));
            if ($kw === '' || !preg_match('/[\p{L}\p{N}]/u', $kw)
                || mb_strlen($kw) > 60) {
                $invalid[] = $raw;   // punctuation-only, or absurdly long
                continue;
            }
            $keywords[$kw] = true;
        }

        // "all" is a superset of every topic, so it wins over any narrowing
        // by topic. Keywords are not topics, so they survive it.
        $slugs    = $sawAll ? [] : array_keys($slugs);
        $keywords = array_keys($keywords);
        sort($slugs);      // stable order → one cache entry per combination
        sort($keywords);

        return [
            'categories' => $slugs,
            'keywords'   => $keywords,
            'invalid'    => array_values(array_unique($invalid)),
        ];
    }

    /*──────────────────────────────────────────────────────────
    |  Private: does a candidate item match a free keyword?
    |
    |  Same three places _newsMatchesCategory() looks — the paper's own
    |  <category> tags, the URL path, then the headline + summary — but
    |  against one caller-supplied phrase instead of a topic definition.
    |
    |  Matching is whole-word, so "art" cannot hit "start". Keywords of 5+
    |  characters also match up to 3 trailing letters, so "vocation" finds
    |  "vocations" and "vocational"; shorter ones stay exact, since "art"
    |  would otherwise pull in "artist", "arts" and "artery".
    ──────────────────────────────────────────────────────────*/
    private function _newsMatchesKeyword(array $item, $keyword)
    {
        $suffix  = mb_strlen($keyword) >= 5 ? '[\p{L}]{0,3}' : '';
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($keyword, '/')
            . $suffix . '(?![\p{L}\p{N}])/u';

        // 1. The paper's own <category> tags
        foreach ($item['categories'] as $cat) {
            if (preg_match($pattern, strtolower($cat))) {
                return true;
            }
        }

        // 2. Section/slug in the article URL — hyphens are word breaks
        //    here, so "women" hits /2026/09/women-in-tech
        $path = strtolower((string) parse_url($item['url'], PHP_URL_PATH));
        if ($path !== '' && preg_match($pattern, $path)) {
            return true;
        }

        // 3. The headline + summary
        $haystack = strtolower(trim($item['title'] . ' ' . (string) $item['description']));

        return $haystack !== '' && preg_match($pattern, $haystack);
    }

    /*──────────────────────────────────────────────────────────
    |  Private: does a candidate item belong to the topic?
    |  Checks the item's own RSS categories, then the URL path,
    |  then whole-word keywords in the title + description.
    ──────────────────────────────────────────────────────────*/
    private function _newsMatchesCategory(array $item, array $def)
    {
        // 1. The paper's own <category> tags
        foreach ($item['categories'] as $cat) {
            $cat = strtolower($cat);
            foreach ($def['tags'] as $tag) {
                if (strpos($cat, $tag) !== false) {
                    return true;
                }
            }
        }

        // 2. Section in the article URL, e.g. /business/…
        $path = strtolower((string) parse_url($item['url'], PHP_URL_PATH));
        if ($path !== '') {
            foreach ($def['paths'] as $seg) {
                if (strpos($path, $seg) !== false) {
                    return true;
                }
            }
        }

        // 3. Whole-word keywords in the headline + summary
        $haystack = strtolower(trim($item['title'] . ' ' . (string) $item['description']));
        if ($haystack === '') {
            return false;
        }
        foreach ($def['keywords'] as $kw) {
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($kw, '/') . '(?![\p{L}\p{N}])/u', $haystack)) {
                return true;
            }
        }

        return false;
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