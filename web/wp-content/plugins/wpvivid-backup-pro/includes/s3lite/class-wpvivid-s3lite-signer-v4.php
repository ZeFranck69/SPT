<?php
if (!class_exists('WPvivid_S3Lite_Signer_V4')):

class WPvivid_S3Lite_Signer_V4
{
    private $cfg;
    private $service = 's3';

    public function __construct($cfg)
    {
        $this->cfg = is_array($cfg)?$cfg:array();
    }

    /**
     * Sign request (SigV4). For streaming uploads, payload hash uses UNSIGNED-PAYLOAD when len > 0 and method is PUT.
     * This keeps compatibility with many S3 compatibles. If you need strict hashing, change below.
     *
     * @param array $req build_request result
     * @param string $body
     * @param int $stream_len 0 for non-stream or unknown
     * @return array signed req (updates headers_lines + headers)
     */
    public function sign($req, $body, $stream_len)
    {
        require_once dirname(__FILE__) . '/class-wpvivid-s3lite-utils.php';

        $method = $req['method'];
        $headers = $req['headers'];
        $query = $req['query'];
        $path = $req['path'];

        $amz_date = gmdate('Ymd\THis\Z');
        $date_stamp = gmdate('Ymd');

        // Payload hash
        $payload_hash = '';
        if ($stream_len > 0 && ($method === 'PUT' || $method === 'POST')) {
            // For compatibility in streaming, use unsigned payload.
            $payload_hash = 'UNSIGNED-PAYLOAD';
        } else {
            $payload_hash = hash('sha256', (string)$body);
        }

        $headers['x-amz-content-sha256'] = $payload_hash;
        $headers['x-amz-date'] = $amz_date;

        if (!empty($this->cfg['session_token'])) {
            $headers['x-amz-security-token'] = (string)$this->cfg['session_token'];
        }

        // Canonical headers
        $headers_lc = array();
        foreach ($headers as $k => $v) $headers_lc[strtolower($k)] = WPvivid_S3Lite_Utils::normalize_spaces($v);
        ksort($headers_lc);

        $canonical_headers = '';
        $signed_names = array();
        foreach ($headers_lc as $k => $v) {
            $canonical_headers .= $k . ':' . $v . "\n";
            $signed_names[] = $k;
        }
        $signed_headers = implode(';', $signed_names);

        $canonical_query = WPvivid_S3Lite_Utils::canonical_query_string($query);

        $canonical_request =
            $method . "\n" .
            $path . "\n" .
            $canonical_query . "\n" .
            $canonical_headers . "\n" .
            $signed_headers . "\n" .
            $payload_hash;

        $algorithm = 'AWS4-HMAC-SHA256';
        $region = (string)$this->cfg['region'];
        $credential_scope = $date_stamp . '/' . $region . '/' . $this->service . '/aws4_request';

        $string_to_sign =
            $algorithm . "\n" .
            $amz_date . "\n" .
            $credential_scope . "\n" .
            hash('sha256', $canonical_request);

        $signing_key = $this->getSignatureKey((string)$this->cfg['secret_key'], $date_stamp, $region, $this->service);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        $authorization =
            $algorithm . ' ' .
            'Credential=' . (string)$this->cfg['access_key'] . '/' . $credential_scope . ', ' .
            'SignedHeaders=' . $signed_headers . ', ' .
            'Signature=' . $signature;

        $headers_lc['authorization'] = $authorization;

        // rebuild header lines
        $lines = array();
        foreach ($headers_lc as $k => $v) $lines[] = WPvivid_S3Lite_Utils::header_name($k) . ': ' . $v;

        $req['headers'] = $headers_lc;
        $req['headers_lines'] = $lines;
        return $req;
    }

    private function getSignatureKey($key, $dateStamp, $regionName, $serviceName)
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}

endif;
