<?php
if (!class_exists('WPvivid_S3Lite_Http')):

class WPvivid_S3Lite_Http
{
    private $cfg;
    private $read_fp = null;
    private $read_remaining = 0;
    private $write_fp = null;

    public function __construct($cfg)
    {
        $this->cfg = is_array($cfg) ? $cfg : array();
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required');
        }
    }

    public function request($req, $body, $ch)
    {
        curl_setopt($ch, CURLOPT_URL, $req['url']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $req['method']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $req['headers_lines']);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$body);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            //if (PHP_VERSION_ID < 80500) { curl_close($ch); }
            throw new RuntimeException('cURL error: '.$err);
        }

        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        //if (PHP_VERSION_ID < 80500) { curl_close($ch); }

        $raw_headers = substr($raw, 0, $header_size);
        $resp_body = substr($raw, $header_size);

        require_once dirname(__FILE__) . '/class-wpvivid-s3lite-utils.php';
        $headers = WPvivid_S3Lite_Utils::parse_headers((string)$raw_headers);

        return array(
            'ok' => ($status < 400),
            'status' => $status,
            'headers' => $headers,
            'body' => $resp_body,
            'url' => $req['url'],
        );
    }

    public function requestStreamDownload($req, $save_to, $ch)
    {
        $fp = @fopen($save_to, 'wb');
        if (!$fp) throw new RuntimeException('Failed to open for write: '.$save_to);

        $this->write_fp = $fp;

        curl_setopt($ch, CURLOPT_URL, $req['url']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $req['method']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $req['headers_lines']);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            //if (PHP_VERSION_ID < 80500) { curl_close($ch); }
            fclose($fp);
            $this->write_fp = null;
            throw new RuntimeException('cURL error: '.$err);
        }

        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        //if (PHP_VERSION_ID < 80500) { curl_close($ch); }

        $raw_headers = substr($raw, 0, $header_size);
        $resp_body = substr($raw, $header_size);

        // write body to file
        if ($resp_body !== '') fwrite($fp, $resp_body);

        fclose($fp);
        $this->write_fp = null;

        require_once dirname(__FILE__) . '/class-wpvivid-s3lite-utils.php';
        $headers = WPvivid_S3Lite_Utils::parse_headers((string)$raw_headers);

        return array(
            'ok' => ($status < 400),
            'status' => $status,
            'headers' => $headers,
            'body' => null,
            'url' => $req['url'],
            'saved_to' => $save_to,
        );
    }

    public function requestStreamUpload($req, $fp, $len, $ch)
    {
        $this->read_fp = $fp;
        $this->read_remaining = (int)$len;

        curl_setopt($ch, CURLOPT_URL, $req['url']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $req['method']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $req['headers_lines']);
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, (int)$len);
        curl_setopt($ch, CURLOPT_READFUNCTION, array($this, 'curlReadCallback'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $raw = curl_exec($ch);
        $this->read_fp = null;
        $this->read_remaining = 0;

        if ($raw === false) {
            $err = curl_error($ch);
            //if (PHP_VERSION_ID < 80500) { curl_close($ch); }
            throw new RuntimeException('cURL error: '.$err);
        }

        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        //if (PHP_VERSION_ID < 80500) { curl_close($ch); }

        $raw_headers = substr($raw, 0, $header_size);
        $resp_body = substr($raw, $header_size);

        require_once dirname(__FILE__) . '/class-wpvivid-s3lite-utils.php';
        $headers = WPvivid_S3Lite_Utils::parse_headers((string)$raw_headers);

        return array(
            'ok' => ($status < 400),
            'status' => $status,
            'headers' => $headers,
            'body' => $resp_body,
            'url' => $req['url'],
        );
    }

    public function curlReadCallback($ch, $fd, $length)
    {
        if (!$this->read_fp) return '';
        if ($this->read_remaining <= 0) return '';

        $max = (int)$length;
        if ($max <= 0) $max = 8192;

        if ($max > $this->read_remaining) $max = $this->read_remaining;
        $data = fread($this->read_fp, $max);
        if ($data === false) return '';
        $this->read_remaining -= strlen($data);
        return $data;
    }

    public function initCurl()
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Failed to init curl');
        }

        $timeout = isset($this->cfg['timeout']) ? (int)$this->cfg['timeout'] : 90;
        $ct = isset($this->cfg['connect_timeout']) ? (int)$this->cfg['connect_timeout'] : 60;
        if ($timeout <= 0) $timeout = 90;
        if ($timeout > 240) $timeout = 240;
        if ($ct <= 0) $ct = 15;

        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $ct);

        if (!empty($this->cfg['user_agent'])) curl_setopt($ch, CURLOPT_USERAGENT, (string)$this->cfg['user_agent']);

        $verify = !empty($this->cfg['verify_ssl']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify ? 1 : 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
        if ($verify) {
            if (!empty($this->cfg['ca_bundle'])) curl_setopt($ch, CURLOPT_CAINFO, (string)$this->cfg['ca_bundle']);
            if (!empty($this->cfg['ca_path'])) curl_setopt($ch, CURLOPT_CAPATH, (string)$this->cfg['ca_path']);
        }

        return $ch;
    }

    public function closeCurl($ch)
    {
        if (PHP_VERSION_ID < 80500)
        {
            curl_close($ch);
        }
    }
}

endif;
