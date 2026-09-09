<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Legacy_host_rewrite
 *
 * Historical rows in several tables store absolute image URLs that were baked
 * with base_url()/site_url() while an earlier domain was live (see the table
 * list in $legacy_hosts). Those rows are still served, so after a domain move
 * they point at a host that may no longer resolve.
 *
 * Rather than migrating the data or patching every read site, this hook
 * rewrites any legacy host found in the response body to the host serving the
 * current request. New uploads are unaffected: base_url() already resolves
 * from HTTP_HOST at request time, so they are written correctly to begin with.
 *
 * Registered as a pre_controller hook so the buffer is open before any
 * controller echoes. To retire it, migrate the data and delete the
 * registration in application/config/hooks.php.
 */
class Legacy_host_rewrite
{
    /**
     * Hosts this application was previously served from. Add to this list on
     * each domain move; the current host is always skipped automatically.
     */
    private $legacy_hosts = [
        'alumniportal.nubiaville.com',
    ];

    /** Content types worth rewriting; anything else passes through untouched. */
    private $rewritable_types = ['application/json', 'text/html', 'text/plain', 'text/xml', 'application/xml'];

    public function start_buffer()
    {
        if (is_cli()) {
            return;
        }

        ob_start([$this, 'rewrite']);
    }

    /**
     * ob_start callback. Must return the (possibly modified) buffer.
     */
    public function rewrite($output)
    {
        if ($output === '' || $output === false) {
            return $output;
        }

        if (!$this->is_rewritable()) {
            return $output;
        }

        $base = $this->current_base_url();
        if ($base === null) {
            return $output;
        }

        $search = [];
        $replace = [];

        // JSON encodes "/" as "\/" unless JSON_UNESCAPED_SLASHES is used, and
        // this codebase does both, so each pattern needs an escaped variant.
        $base_escaped = str_replace('/', '\\/', $base);

        foreach ($this->legacy_hosts as $host) {
            if ($host === '' || strcasecmp($host, $this->current_host()) === 0) {
                continue;
            }

            foreach (['https', 'http'] as $scheme) {
                // The "/./" form comes from site_url('./uploads/...').
                // Longest patterns first: str_replace() applies arrays in order.
                $search[] = $scheme . '://' . $host . '/./';
                $replace[] = $base;
                $search[] = $scheme . ':\\/\\/' . $host . '\\/.\\/';
                $replace[] = $base_escaped;

                $search[] = $scheme . '://' . $host . '/';
                $replace[] = $base;
                $search[] = $scheme . ':\\/\\/' . $host . '\\/';
                $replace[] = $base_escaped;
            }
        }

        if (empty($search)) {
            return $output;
        }

        return str_replace($search, $replace, $output);
    }

    private function is_rewritable()
    {
        foreach (headers_list() as $header) {
            // Never touch an encoded body (gzip et al) - it is no longer text.
            if (stripos($header, 'content-encoding:') === 0) {
                return false;
            }
        }

        foreach (headers_list() as $header) {
            if (stripos($header, 'content-type:') !== 0) {
                continue;
            }

            foreach ($this->rewritable_types as $type) {
                if (stripos($header, $type) !== false) {
                    return true;
                }
            }

            return false;
        }

        // No explicit Content-Type: PHP defaults to text/html.
        return true;
    }

    private function current_host()
    {
        return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    }

    /**
     * Mirrors the base_url() derivation in config.php, without depending on
     * the url helper being loaded when the buffer is flushed.
     */
    private function current_base_url()
    {
        $host = $this->current_host();
        if ($host === '') {
            return null;
        }

        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';

        return $scheme . '://' . $host . '/';
    }
}
