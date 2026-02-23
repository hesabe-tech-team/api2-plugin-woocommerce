<?php
/**
 * WooCommerce Hesabe Encryption Class
 *
 * Handles AES-256-CBC encryption and decryption for Hesabe API communication
 * Based on Hesabe PHP Kit HesabeCrypt class
 *
 * @package Hesabe_WooCommerce
 * @since 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Hesabe_Crypt Class
 * Exact implementation from PHP kit
 */
class WC_Hesabe_Crypt {

    /**
     * AES Encryption Method
     * Exact implementation from PHP kit HesabeCrypt::encrypt()
     *
     * @param string $str String to encrypt
     * @param string $key Secret key
     * @param string $ivKey IV key
     * @return string Encrypted string
     */
    public static function encrypt($str, $key, $ivKey) {
        $str = self::pkcs5_pad($str);
        $encrypted = openssl_encrypt($str, 'AES-256-CBC', $key, OPENSSL_ZERO_PADDING, $ivKey);
        $encrypted = base64_decode($encrypted);
        $encrypted = unpack('C*', ($encrypted));
        $encrypted = self::byteArray2Hex($encrypted);
        $encrypted = urlencode($encrypted);
        return $encrypted;
    }

    /**
     * PKCS5 padding
     * Exact implementation from PHP kit
     *
     * @param string $text Text to pad
     * @return string Padded text
     */
    private static function pkcs5_pad($text) {
        $blocksize = 32;
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }

    /**
     * Convert byte array to hex string
     * Exact implementation from PHP kit
     *
     * @param array $byteArray Byte array
     * @return string Hex string
     */
    private static function byteArray2Hex($byteArray) {
        $chars = array_map('chr', $byteArray);
        $bin = join($chars);
        return bin2hex($bin);
    }

    /**
     * AES Decryption Method
     * Exact implementation from PHP kit HesabeCrypt::decrypt()
     *
     * @param string $code Encrypted string
     * @param string $key Secret key
     * @param string $ivKey IV key
     * @return string|false Decrypted string or false on failure
     */
    public static function decrypt($code, $key, $ivKey) {
        // Validate hex string
        if (!(ctype_xdigit($code) && strlen($code) % 2 == 0)) {
            return false;
        }
        
        $code = self::hex2ByteArray(trim($code));
        $code = self::byteArray2String($code);
        $iv = $key;
        $code = base64_encode($code);
        $decrypted = openssl_decrypt($code, 'AES-256-CBC', $key, OPENSSL_ZERO_PADDING, $ivKey);
        return self::pkcs5_unpad($decrypted);
    }

    /**
     * PKCS5 unpadding
     * Exact implementation from PHP kit
     *
     * @param string $text Text to unpad
     * @return string|false Unpadded text or false on failure
     */
    private static function pkcs5_unpad($text) {
        if (empty($text)) {
            return false;
        }
        
        $pad = ord($text[strlen($text) - 1]);
        if ($pad > strlen($text)) {
            return false;
        }
        if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) {
            return false;
        }
        return substr($text, 0, -1 * $pad);
    }

    /**
     * Convert hex string to byte array
     * Exact implementation from PHP kit
     *
     * @param string $hexString Hex string
     * @return array Byte array
     */
    private static function hex2ByteArray($hexString) {
        $string = hex2bin($hexString);
        return unpack('C*', $string);
    }

    /**
     * Convert byte array to string
     * Exact implementation from PHP kit
     *
     * @param array $byteArray Byte array
     * @return string String
     */
    private static function byteArray2String($byteArray) {
        $chars = array_map('chr', $byteArray);
        return join($chars);
    }
}

