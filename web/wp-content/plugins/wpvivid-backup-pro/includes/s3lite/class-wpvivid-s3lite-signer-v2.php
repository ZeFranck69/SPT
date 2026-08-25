<?php
if (!class_exists('WPvivid_S3Lite_Signer_V2')):

/**
 * SigV2 signer (legacy).
 * NOTE: Many modern AWS regions require SigV4. SigV2 here is for some S3-compatible storages.
 */
class WPvivid_S3Lite_Signer_V2
{
    private $cfg;

    public function __construct($cfg)
    {
        $this->cfg = is_array($cfg)?$cfg:array();
    }

    public function sign($req, $body, $stream_len)
    {
        require_once dirname(__FILE__) . '/class-wpvivid-s3lite-utils.php';

        $method = $req['method'];
        $path = $req['path'];
        $headers = $req['headers'];

        // Date header (RFC 2822) - SigV2 uses Date unless x-amz-date is used
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $headers['date'] = $date;

        // Canonicalized Amz headers
        $amz = array();
        foreach ($headers as $k => $v) {
            $lk = strtolower((string)$k);
            if (WPvivid_S3Lite_Utils::starts_with($lk, 'x-amz-')) {
                $amz[$lk] = WPvivid_S3Lite_Utils::normalize_spaces($v);
            }
        }
        ksort($amz);
        $canonical_amz = '';
        foreach ($amz as $k => $v) {
            $canonical_amz .= $k . ':' . $v . "\n";
        }

        // Content-MD5 and Content-Type
        $content_md5 = isset($headers['content-md5']) ? (string)$headers['content-md5'] : '';
        $content_type = isset($headers['content-type']) ? (string)$headers['content-type'] : '';

        // Canonicalized resource: path + subresources (we include query keys like uploads, uploadId, partNumber)
        $resource = $path;
        $sub = array();
        if (!empty($req['query']) && is_array($req['query'])) {
            foreach ($req['query'] as $k => $v) {
                $k = (string)$k;
                if ($k === 'acl' || $k === 'location' || $k === 'logging' || $k === 'torrent' ||
                    $k === 'lifecycle' || $k === 'versioning' || $k === 'uploads' || $k === 'uploadId' ||
                    $k === 'partNumber' || $k === 'website' || $k === 'delete') {
                    $sub[] = ($v === '') ? $k : ($k.'='.$v);
                }
            }
        }
        if (!empty($sub)) {
            sort($sub);
            $resource .= '?' . implode('&', $sub);
        }

        $string_to_sign =
            $method . "\n" .
            $content_md5 . "\n" .
            $content_type . "\n" .
            $date . "\n" .
            $canonical_amz .
            $resource;

        $sig = base64_encode(hash_hmac('sha1', $string_to_sign, (string)$this->cfg['secret_key'], true));
        $auth = 'AWS ' . (string)$this->cfg['access_key'] . ':' . $sig;

        // rebuild headers lower-case
        $headers_lc = array();
        foreach ($headers as $k => $v) $headers_lc[strtolower($k)] = WPvivid_S3Lite_Utils::normalize_spaces($v);
        $headers_lc['authorization'] = $auth;

        $lines = array();
        foreach ($headers_lc as $k => $v) $lines[] = WPvivid_S3Lite_Utils::header_name($k) . ': ' . $v;

        $req['headers'] = $headers_lc;
        $req['headers_lines'] = $lines;
        return $req;
    }
}

endif;
