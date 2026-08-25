<?php

if (!defined('WPVIVID_BACKUP_PRO_PLUGIN_DIR'))
{
    die;
}

class WPvivid_Dashboard_Crypt
{
    private $public_key;
    private $sym_key;

    private $rij;
    private $rsa;

    public function __construct($public_key)
    {
        $this->public_key=$public_key;

        if(!class_exists('Crypt_Rijndael'))
            include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'methods/Crypt/Rijndael.php';

        if(!class_exists('Crypt_RSA'))
            include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'methods/Crypt/RSA.php';

        if(!class_exists('Math_BigInteger'))
            include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'methods/Math/BigInteger.php';
        //
        $this->rij= new Crypt_Rijndael();
        $this->rsa= new Crypt_RSA();
    }

    public function generate_key()
    {
        $this->sym_key = crypt_random_string(32);
        $this->rij->setKey($this->sym_key);
    }

    public function encrypt_message($message)
    {
        $this->generate_key();
        $key=$this->encrypt_key();
        $len=str_pad(dechex(strlen($key)),3,'0', STR_PAD_LEFT);
        $message=$this->rij->encrypt($message);
        if($message===false)
            return false;
        $message_len = str_pad(dechex(strlen($message)), 16, '0', STR_PAD_LEFT);
        return $len.$key.$message_len.$message;
    }

    public function encrypt_key()
    {
        $this->rsa->loadKey($this->public_key);
        return $this->rsa->encrypt($this->sym_key);
    }

    public function decrypt_message($message)
    {
        $len = substr($message, 0, 3);
        $len = hexdec($len);
        $key = substr($message, 3, $len);

        $cipherlen = substr($message, ($len + 3), 16);
        $cipherlen = hexdec($cipherlen);

        $data = substr($message, ($len + 19), $cipherlen);
        $rsa = new Crypt_RSA();
        $rsa->loadKey($this->public_key);
        $key=$rsa->decrypt($key);
        if ($key === false || empty($key))
        {
            return false;
        }
        $rij = new Crypt_Rijndael();
        $rij->setKey($key);
        return $rij->decrypt($data);
    }

    public function encrypt_user_token($token)
    {
        $user_info['token']=$token;
        $info=json_encode($user_info);
        $this->rsa->loadKey($this->public_key);
        return $this->rsa->encrypt($info);
    }


    public function encrypt_token($token)
    {
        $this->rsa->loadKey($this->public_key);
        return $this->rsa->encrypt($token);
    }
}

class WPvivid_Crypt_Ex
{
    private $public_key;
    private $sym_key;

    private $rij;
    private $rsa;

    public function __construct($public_key)
    {
        include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'vendor/autoload.php';

        $this->public_key=$public_key;
        $this->rij = new \WPvividphpseclib3\Crypt\Rijndael('cbc');
        $this->rij->setIV(str_repeat("\0", 16));
        $this->rsa = \WPvividphpseclib3\Crypt\PublicKeyLoader::load($this->public_key)
            ->withPadding(\WPvividphpseclib3\Crypt\RSA::ENCRYPTION_OAEP)
            ->withHash('sha1')
            ->withMGFHash('sha1');
    }

    public function generate_key()
    {
        if (function_exists('crypt_random_string')) {
            $this->sym_key = crypt_random_string(32);
        }
        else if (function_exists('random_bytes')) {
            $this->sym_key = random_bytes(32);
        }
        else {
            $this->sym_key = openssl_random_pseudo_bytes(32);
        }
        $this->rij->setKey($this->sym_key);
    }

    public function encrypt_key()
    {
        return $this->rsa->encrypt($this->sym_key);
    }

    public function encrypt_message($message)
    {
        $this->generate_key();
        $key=$this->encrypt_key();
        $len=str_pad(dechex(strlen($key)),3,'0', STR_PAD_LEFT);

        $message=$this->rij->encrypt($message);
        if($message===false)
            return false;
        $message_len = str_pad(dechex(strlen($message)), 16, '0', STR_PAD_LEFT);
        return $len.$key.$message_len.$message;
    }

    public function decrypt_message($message)
    {
        $len = substr($message, 0, 3);
        $len = hexdec($len);
        $key_cipher = substr($message, 3, $len);

        $cipherlen = substr($message, ($len + 3), 16);
        $cipherlen = hexdec($cipherlen);

        $data = substr($message, ($len + 19), $cipherlen);

        $rsa = \WPvividphpseclib3\Crypt\PublicKeyLoader::load($this->public_key)
            ->withPadding(\WPvividphpseclib3\Crypt\RSA::ENCRYPTION_OAEP)
            ->withHash('sha1')
            ->withMGFHash('sha1');

        $key = $rsa->decrypt($key_cipher);
        if ($key === false || $key === '' || $key === null) {
            return false;
        }

        $rij = new \WPvividphpseclib3\Crypt\Rijndael('cbc');
        $rij->setIV(str_repeat("\0", 16));
        $rij->setKey($key);

        return $rij->decrypt($data);
    }
}

class WPvivid_Crypt_File_Ex
{
    private $key;
    private $rij;

    public function __construct($key)
    {
        include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'vendor/autoload.php';

        $this->key = $this->adjust_key_length($key);
        $this->rij = new \WPvividphpseclib3\Crypt\Rijndael('cbc');
        $this->rij->setIV(str_repeat("\0", 16));
    }

    private function adjust_key_length($key)
    {
        $length = strlen($key);
        switch (true) {
            case $length <= 16:
                $key = str_pad($key, 16, "\0");
                break;
            case $length <= 20:
                $key = str_pad($key, 20, "\0");
                break;
            case $length <= 24:
                $key = str_pad($key, 24, "\0");
                break;
            case $length <= 28:
                $key = str_pad($key, 28, "\0");
                break;
            case $length <= 32:
                $key = str_pad($key, 32, "\0");
                break;
            default:
                $key = substr($key, 0, 32);
        }
        return $key;
    }

    public function encrypt($file)
    {
        $encrypted_path = dirname($file).'/encrypt_'.basename($file).'.tmp';

        $data_encrypted = 0;
        $buffer_size = 2097152;

        $file_size = filesize($file);

        $this->rij->setKey($this->key);
        $this->rij->disablePadding();
        $this->rij->enableContinuousBuffer();

        if (file_exists($encrypted_path))
        {
            @wp_delete_file($encrypted_path);
        }
        $encrypted_handle = fopen($encrypted_path, 'wb+');

        $file_handle = fopen($file, 'rb');

        if($file_handle===false)
        {
            $ret['result']='failed';
            $ret['error']=$file.' file not found';
            return $ret;
        }

        while ($data_encrypted < $file_size)
        {
            $file_part = fread($file_handle, $buffer_size);

            $length = strlen($file_part);
            if (0 != $length % 16)
            {
                $pad = 16 - ($length % 16);
                $file_part = str_pad($file_part, $length + $pad, chr($pad));
            }

            $encrypted_data = $this->rij->encrypt($file_part);

            fwrite($encrypted_handle, $encrypted_data);

            $data_encrypted += $buffer_size;
        }

        fclose($encrypted_handle);
        fclose($file_handle);

        $result_path = $file.'.crypt';

        @rename($encrypted_path, $result_path);

        $ret['result']='success';
        $ret['file_path']=$result_path;
        return $ret;
    }

    public function decrypt($file)
    {
        $file_handle = fopen($file, 'rb');

        if($file_handle===false)
        {
            $ret['result']='failed';
            $ret['error']=$file.' file not found';
            return $ret;
        }

        $decrypted_path = dirname($file).'/decrypt_'.basename($file).'.tmp';

        $decrypted_handle = fopen($decrypted_path, 'wb+');

        $this->rij->setKey($this->key);
        $this->rij->disablePadding();
        $this->rij->enableContinuousBuffer();

        $file_size = filesize($file);
        $bytes_decrypted = 0;
        $buffer_size =2097152;

        while ($bytes_decrypted < $file_size)
        {
            $file_part = fread($file_handle, $buffer_size);

            $length = strlen($file_part);
            if (0 != $length % 16) {
                $pad = 16 - ($length % 16);
                $file_part = str_pad($file_part, $length + $pad, chr($pad));
            }

            $decrypted_data = $this->rij->decrypt($file_part);

            $is_last_block = ($bytes_decrypted + strlen($decrypted_data) >= $file_size);

            $write_bytes = min($file_size - $bytes_decrypted, strlen($decrypted_data));
            if ($is_last_block)
            {
                $is_padding = false;
                $last_byte = ord(substr($decrypted_data, -1, 1));
                if ($last_byte < 16)
                {
                    $is_padding = true;
                    for ($j = 1; $j<=$last_byte; $j++)
                    {
                        if (substr($decrypted_data, -$j, 1) != chr($last_byte))
                            $is_padding = false;
                    }
                }
                if ($is_padding)
                {
                    $write_bytes -= $last_byte;
                }
            }

            fwrite($decrypted_handle, $decrypted_data, $write_bytes);
            $bytes_decrypted += $buffer_size;
        }

        // close the main file handle
        fclose($decrypted_handle);
        // close original file
        fclose($file_handle);

        $fullpath_new = preg_replace('/\.crypt$/', '', $file, 1).'.decrypted.zip';

        @rename($decrypted_path, $fullpath_new);
        $ret['result']='success';
        $ret['file_path']=$fullpath_new;

        return $ret;
    }
}

class WPvivid_Remote_Password_Crypt_Ex
{
    const OPTION_PREFIX = 'wpvivid_remote_v1:';
    const CONFIG_KEY = 'WPVIVID_REMOTE_PASSWORD_CRYPT_KEY';

    private $sym_key = false;
    private $vendor_mode = 'legacy';

    public function __construct()
    {
        if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
            $this->vendor_mode = 'modern';
        }
        else {
            $this->vendor_mode = 'legacy';
        }
    }

    public function has_config_key()
    {
        return defined(self::CONFIG_KEY) && constant(self::CONFIG_KEY) !== '';
    }

    public function get_define_line($key)
    {
        return "define('" . self::CONFIG_KEY . "', '" . $key . "');";
    }

    public function get_loaded_key_hash()
    {
        if ($this->sym_key === false || strlen($this->sym_key) !== 32) {
            return '';
        }

        return hash('sha256', $this->sym_key);
    }

    public function get_key_hash_from_base64($key)
    {
        $raw_key = base64_decode($key, true);

        if ($raw_key === false || strlen($raw_key) !== 32) {
            return '';
        }

        return hash('sha256', $raw_key);
    }

    public function generate_key()
    {
        if (function_exists('crypt_random_string')) {
            $this->sym_key = crypt_random_string(32);
        }
        else if (function_exists('random_bytes')) {
            $this->sym_key = random_bytes(32);
        }
        else if (function_exists('openssl_random_pseudo_bytes')) {
            $this->sym_key = openssl_random_pseudo_bytes(32);
        }
        else {
            $this->sym_key = substr(hash('sha256', uniqid('', true) . microtime(true), true), 0, 32);
        }

        return base64_encode($this->sym_key);
    }

    public function load_key_from_config()
    {
        if (!defined(self::CONFIG_KEY) || constant(self::CONFIG_KEY) === '') {
            return false;
        }

        $key = constant(self::CONFIG_KEY);
        $raw_key = base64_decode($key, true);

        if ($raw_key === false || strlen($raw_key) !== 32) {
            return false;
        }

        $this->sym_key = $raw_key;

        return true;
    }

    private function random_bytes_compat($length)
    {
        if (function_exists('crypt_random_string')) {
            return crypt_random_string($length);
        }

        if (function_exists('random_bytes')) {
            return random_bytes($length);
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            return openssl_random_pseudo_bytes($length);
        }

        return substr(hash('sha256', uniqid('', true) . microtime(true), true), 0, $length);
    }


    public function encrypt($message)
    {
        if ($this->sym_key === false || strlen($this->sym_key) !== 32) {
            return false;
        }

        $iv = $this->random_bytes_compat(16);

        $rij = $this->get_rijndael_object($iv);

        if ($rij === false) {
            return false;
        }

        $rij->setKey($this->sym_key);

        $cipher = $rij->encrypt($message);

        if ($cipher === false || $cipher === '') {
            return false;
        }

        $mac = hash_hmac('sha256', $iv . $cipher, $this->sym_key, true);

        return self::OPTION_PREFIX . base64_encode($iv . $mac . $cipher);
    }

    public function decrypt($message)
    {
        if ($this->sym_key === false || strlen($this->sym_key) !== 32) {
            return false;
        }

        if (strpos($message, self::OPTION_PREFIX) !== 0) {
            return false;
        }

        $payload = substr($message, strlen(self::OPTION_PREFIX));
        $payload = base64_decode($payload, true);

        if ($payload === false || strlen($payload) <= 48) {
            return false;
        }

        $iv = substr($payload, 0, 16);
        $mac = substr($payload, 16, 32);
        $cipher = substr($payload, 48);

        $calc_mac = hash_hmac('sha256', $iv . $cipher, $this->sym_key, true);

        if (function_exists('hash_equals')) {
            if (!hash_equals($mac, $calc_mac)) {
                return false;
            }
        }
        else {
            if ($mac !== $calc_mac) {
                return false;
            }
        }

        $rij = $this->get_rijndael_object($iv);

        if ($rij === false) {
            return false;
        }

        $rij->setKey($this->sym_key);

        return $rij->decrypt($cipher);
    }

    private function get_rijndael_object($iv)
    {
        if ($this->vendor_mode === 'modern') {
            return $this->get_modern_rijndael_object($iv);
        }

        return $this->get_legacy_rijndael_object($iv);
    }

    private function get_modern_rijndael_object($iv)
    {
        if (!class_exists('\WPvividphpseclib3\Crypt\Rijndael')) {
            $autoload = WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'vendor/autoload.php';

            if (file_exists($autoload)) {
                include_once $autoload;
            }
        }

        if (!class_exists('\WPvividphpseclib3\Crypt\Rijndael')) {
            return false;
        }

        $rij = new \WPvividphpseclib3\Crypt\Rijndael('cbc');
        $rij->setIV($iv);

        return $rij;
    }

    private function get_legacy_rijndael_object($iv)
    {
        if (!class_exists('Crypt_Rijndael')) {
            include_once WPVIVID_BACKUP_PRO_PLUGIN_DIR . 'methods/Crypt/Rijndael.php';
        }

        if (!class_exists('Crypt_Rijndael')) {
            return false;
        }

        $rij = new Crypt_Rijndael();
        $rij->setIV($iv);

        return $rij;
    }
}