<?php

declare(strict_types=1);

namespace SptVouchers;

defined('ABSPATH') || exit;

final class Settings
{
    public const OPTION = 'spt_vouchers_settings';

    /** @return array<string, bool|int> */
    public static function defaults(): array
    {
        return [
            'enable_reservations' => false,
            'max_file_size_mb' => 10,
        ];
    }

    public static function installDefaults(): void
    {
        $current = get_option(self::OPTION, []);
        update_option(self::OPTION, wp_parse_args(is_array($current) ? $current : [], self::defaults()), false);
    }

    /** @return array<string, bool|int> */
    public static function all(): array
    {
        $settings = get_option(self::OPTION, []);

        return wp_parse_args(is_array($settings) ? $settings : [], self::defaults());
    }

    public static function getInt(string $key): int
    {
        $settings = self::all();

        return isset($settings[$key]) ? max(0, (int) $settings[$key]) : 0;
    }

    public static function getBool(string $key): bool
    {
        $settings = self::all();

        return !empty($settings[$key]);
    }

    /** @param array<string, mixed> $input
     *  @return array<string, bool|int>
     */
    public static function sanitize(array $input): array
    {
        return [
            'enable_reservations' => !empty($input['enable_reservations']),
            'max_file_size_mb' => min(100, max(1, absint($input['max_file_size_mb'] ?? 10))),
        ];
    }
}
