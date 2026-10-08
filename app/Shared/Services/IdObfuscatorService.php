<?php

declare(strict_types=1);

namespace App\Shared\Services;

final class IdObfuscatorService
{
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const BASE = 62;

    private const MASK_15 = 0x7FFF;

    /**
     * Encodes a numeric ID into an obfuscated base62 string.
     */
    public static function encode(int $id): string
    {
        if ($id <= 0) {
            return (string) $id;
        }

        $salt = self::resolveSalt();
        $scrambled = self::feistelEncrypt($id ^ $salt, $salt);

        return self::toBase62($scrambled);
    }

    /**
     * Decodes an obfuscated string back to the original numeric ID.
     */
    public static function decode(mixed $value): ?int
    {
        $numericId = self::parseNumericId($value);

        if ($numericId !== null) {
            return $numericId;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return self::decodeBase62Hash($value);
    }

    private static function parseNumericId(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            $parsed = (int) $value;

            return $parsed > 0 ? $parsed : null;
        }

        return null;
    }

    private static function decodeBase62Hash(string $value): ?int
    {
        $scrambled = self::fromBase62($value);

        if ($scrambled === null) {
            return null;
        }

        $salt = self::resolveSalt();
        $id = self::feistelDecrypt($scrambled, $salt) ^ $salt;

        return $id > 0 ? $id : null;
    }

    private static function resolveSalt(): int
    {
        $key = (string) config('app.key', 'default_salt');

        return abs(crc32($key)) & 0x3FFFFFFF;
    }

    private static function feistelEncrypt(int $val, int $salt): int
    {
        $left = ($val >> 15) & self::MASK_15;
        $right = $val & self::MASK_15;

        $newRight = $left ^ (($right * 1337 + $salt) & self::MASK_15);
        $newLeft = $right;

        return ($newLeft << 15) | $newRight;
    }

    private static function feistelDecrypt(int $val, int $salt): int
    {
        $newLeft = ($val >> 15) & self::MASK_15;
        $newRight = $val & self::MASK_15;

        $right = $newLeft;
        $left = $newRight ^ (($right * 1337 + $salt) & self::MASK_15);

        return ($left << 15) | $right;
    }

    private static function toBase62(int $num): string
    {
        if ($num === 0) {
            return '0';
        }

        $str = '';

        while ($num > 0) {
            $remainder = $num % self::BASE;
            $str = self::ALPHABET[$remainder].$str;
            $num = (int) ($num / self::BASE);
        }

        return str_pad($str, 5, '0', STR_PAD_LEFT);
    }

    private static function fromBase62(string $str): ?int
    {
        $len = strlen($str);
        $num = 0;

        for ($i = 0; $i < $len; $i++) {
            $char = $str[$i];
            $pos = strpos(self::ALPHABET, $char);

            if ($pos === false) {
                return null;
            }

            $num = ($num * self::BASE) + $pos;
        }

        return $num;
    }
}
