<?php

/**
 * WooCommerce Hesabe Encryption Class
 *
 * Extends Encryption to provide additional data
 *
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Hesabe_Crypt
{


    public static function encrypt($str, $key, $ivKey)
    {
        $str = self::pkcs5Pad($str);
        $encrypted = openssl_encrypt($str, 'AES-256-CBC', $key, OPENSSL_ZERO_PADDING, $ivKey);
        $encrypted = base64_decode($encrypted);
        $encrypted = unpack('C*', $encrypted);
        $encrypted = self::byteArray2Hex($encrypted);
        return urlencode($encrypted);
    }

    private static function pkcs5Pad($text)
    {
        $blockSize = 32;
        $pad = $blockSize - (strlen($text) % $blockSize);
        return $text . str_repeat(chr($pad), $pad);
    }

    public static function byteArray2Hex($byteArray)
    {
        $chars = array_map("chr", $byteArray);
        $bin = join($chars);
        return bin2hex($bin);
    }

    //Decryption Method for AES Algorithm Starts

    public static function decrypt($code, $key, $ivKey)
    {
        if (!(ctype_xdigit($code) && strlen($code) % 2 == 0)) {
            return false;
        }
        $code = self::hex2ByteArray(trim($code));
        $code = self::byteArray2String($code);
        $code = base64_encode($code);
        $decrypted = openssl_decrypt($code, 'AES-256-CBC', $key, OPENSSL_ZERO_PADDING, $ivKey);
        return self::pkcs5Unpad($decrypted);
    }

    public static function hex2ByteArray($hexString)
    {
        $string = hex2bin($hexString);
        return unpack('C*', $string);
    }

    public static function byteArray2String($byteArray)
    {
        $chars = array_map("chr", $byteArray);
        return join($chars);
    }

    public static function pkcs5Unpad($text)
    {
        $pad = ord($text[strlen($text) - 1]);
        if ($pad > strlen($text)) {
            return false;
        }
        if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) {
            return false;
        }
        return substr($text, 0, -1 * $pad);
    }
}
?>