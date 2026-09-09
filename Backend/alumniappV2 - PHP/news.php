<?php
function fetchNigerianNews($targetCount = 50, $maxPages = 5)
{
    $apiKey = 'pub_d218b6c74e3742a8a59106bd2fee22e4';
    $base   = "https://newsdata.io/api/1/latest?country=ng&language=en&apikey={$apiKey}";

    $items    = [];
    $nextPage = null;
    $pages    = 0;

    do {
        $url = $base . ($nextPage ? "&page={$nextPage}" : "");

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($raw, true);
        if (($data['status'] ?? '') !== 'success') break;

        foreach ($data['results'] as $a) {
            $items[] = [
                'title'   => $a['title']       ?? null,
                'link'    => $a['link']        ?? null,
                'source'  => $a['source_id']   ?? null,
                'pubDate' => $a['pubDate']     ?? null,
                'image'   => $a['image_url']   ?? null,
                'desc'    => $a['description'] ?? null,
            ];
        }

        $nextPage = $data['nextPage'] ?? null;  // token for the next batch
        $pages++;

    } while ($nextPage && count($items) < $targetCount && $pages < $maxPages);

    return array_slice($items, 0, $targetCount);
}

header('Content-Type: application/json');
echo json_encode(fetchNigerianNews(50), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);