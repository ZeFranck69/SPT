<?php
if (!class_exists('WPvivid_S3Lite_Xml')):

class WPvivid_S3Lite_Xml
{
    public static function first($xml, $tag)
    {
        $xml = (string)$xml; $tag = (string)$tag;
        if ($xml === '') return null;
        $pattern = '#<' . preg_quote($tag, '#') . '>([^<]+)</' . preg_quote($tag, '#') . '>#';
        if (preg_match($pattern, $xml, $m) === 1) return (string)$m[1];
        return null;
    }

    public static function error_code($xml)
    {
        $c = self::first($xml, 'Code');
        return $c ? $c : null;
    }

    public static function error_message($xml)
    {
        $m = self::first($xml, 'Message');
        return $m ? $m : null;
    }
}

endif;
