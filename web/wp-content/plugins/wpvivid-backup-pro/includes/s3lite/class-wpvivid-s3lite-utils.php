<?php
if (!class_exists('WPvivid_S3Lite_Utils')):

class WPvivid_S3Lite_Utils
{
    public static function normalize_spaces($s)
    {
        $s = trim((string)$s);
        $s = preg_replace('/\s+/', ' ', $s);
        return ($s === null) ? '' : $s;
    }

    public static function encode_key_path($key)
    {
        $segs = explode('/', (string)$key);
        $out = array();
        foreach ($segs as $seg) $out[] = rawurlencode($seg);
        return implode('/', $out);
    }

    public static function build_query_string($query)
    {
        if (!is_array($query) || $query === array()) return '';
        $pairs = array();
        foreach ($query as $k => $v) {
            $pairs[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
        }
        return implode('&', $pairs);
    }

    public static function canonical_query_string($query)
    {
        if (!is_array($query) || $query === array()) return '';
        $pairs = array();
        foreach ($query as $k => $v) {
            $pairs[] = array(rawurlencode((string)$k), rawurlencode((string)$v));
        }
        usort($pairs, array(__CLASS__, 'sort_pairs'));
        $out = array();
        foreach ($pairs as $kv) $out[] = $kv[0] . '=' . $kv[1];
        return implode('&', $out);
    }

    public static function sort_pairs($a, $b)
    {
        if ($a[0] === $b[0]) return strcmp($a[1], $b[1]);
        return strcmp($a[0], $b[0]);
    }

    public static function parse_headers($raw_headers)
    {
        $raw_headers = trim((string)$raw_headers);
        if ($raw_headers === '') return array();

        $blocks = preg_split("/\r\n\r\n/", $raw_headers);
        $last = is_array($blocks) ? (string)end($blocks) : $raw_headers;

        $lines = preg_split("/\r\n/", $last);
        if (!is_array($lines)) return array();

        $headers = array();
        foreach ($lines as $line) {
            if (stripos($line, 'HTTP/') === 0) continue;
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }
        return $headers;
    }

    public static function header_name($lower)
    {
        $lower = strtolower((string)$lower);
        if ($lower === 'authorization') return 'Authorization';
        if ($lower === 'host') return 'Host';
        if ($lower === 'date') return 'Date';
        if ($lower === 'content-type') return 'Content-Type';
        if ($lower === 'content-md5') return 'Content-MD5';
        if ($lower === 'x-amz-date') return 'x-amz-date';
        if ($lower === 'x-amz-content-sha256') return 'x-amz-content-sha256';
        if ($lower === 'x-amz-security-token') return 'x-amz-security-token';

        $parts = explode('-', $lower);
        foreach ($parts as $i => $p) $parts[$i] = ucfirst($p);
        return implode('-', $parts);
    }

    public static function build_request($endpoint, $method, $bucket, $key, $query, $headers, $path_style)
    {
        $endpoint = rtrim((string)$endpoint, '/');
        $method = strtoupper((string)$method);
        $bucket = trim((string)$bucket);
        $key = ltrim((string)$key, '/');

        $ep_parts = parse_url($endpoint);
        if (!is_array($ep_parts) || empty($ep_parts['scheme']) || empty($ep_parts['host'])) {
            throw new RuntimeException('Invalid endpoint: '.$endpoint);
        }
        $scheme = $ep_parts['scheme'];
        $host = $ep_parts['host'];
        $port = isset($ep_parts['port']) ? (int)$ep_parts['port'] : 0;
        $base_path = isset($ep_parts['path']) ? rtrim($ep_parts['path'], '/') : '';

        $req_host = $host;
        $path = $base_path;

        if ($path_style) {
            $path .= '/' . rawurlencode($bucket);
            if ($key !== '') $path .= '/' . self::encode_key_path($key);
        } else {
            // virtual-host style: bucket.host
            $req_host = $bucket . '.' . $host;
            $path .= '/';
            if ($key !== '') $path .= self::encode_key_path($key);
            else $path = rtrim($path, '/');
        }

        if ($path === '') $path = '/';
        if ($path[0] !== '/') $path = '/' . $path;

        $headers_lc = array();
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                $headers_lc[strtolower(trim((string)$k))] = self::normalize_spaces((string)$v);
            }
        }
        $headers_lc['host'] = $req_host;

        $qs = self::build_query_string(is_array($query)?$query:array());
        $url = $scheme . '://' . $req_host . ($port ? (':' . $port) : '') . $path . ($qs !== '' ? ('?' . $qs) : '');

        $headers_lines = array();
        foreach ($headers_lc as $k => $v) {
            $headers_lines[] = self::header_name($k) . ': ' . $v;
        }

        return array(
            'method' => $method,
            'url' => $url,
            'path' => $path,
            'query' => is_array($query)?$query:array(),
            'query_string' => $qs,
            'host' => $req_host,
            'headers' => $headers_lc,
            'headers_lines' => $headers_lines,
            'bucket' => $bucket,
            'key' => $key,
        );
    }

    public static function starts_with($s, $prefix)
    {
        $s = (string)$s; $prefix = (string)$prefix;
        return substr($s, 0, strlen($prefix)) === $prefix;
    }

    public static function safe_substr($s, $max)
    {
        $s = (string)$s;
        if (strlen($s) <= $max) return $s;
        return substr($s, 0, $max);
    }
}

endif;
