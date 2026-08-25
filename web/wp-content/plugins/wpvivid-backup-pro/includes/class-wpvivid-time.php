<?php
if (!defined('WPVIVID_BACKUP_PRO_PLUGIN_DIR'))
{
    die;
}

class WPvivid_Time
{
    public static function get_wp_timezone()
    {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }

        $timezone_string = get_option('timezone_string');

        if (!empty($timezone_string)) {
            return new DateTimeZone($timezone_string);
        }

        $offset = (float) get_option('gmt_offset', 0);
        $hours = (int) $offset;
        $minutes = abs(($offset - $hours) * 60);

        $sign = ($offset < 0) ? '-' : '+';
        $tz_name = sprintf('%s%02d:%02d', $sign, abs($hours), $minutes);

        return new DateTimeZone($tz_name);
    }

    public static function format_local($format, $timestamp)
    {
        $timestamp = (int) $timestamp;

        //if (function_exists('wp_date')) {
        //    return wp_date($format, $timestamp, self::get_wp_timezone());
        //}

        $dt = new DateTime('@' . $timestamp);
        $dt->setTimezone(self::get_wp_timezone());

        return $dt->format($format);
    }

    public static function format_utc($format, $timestamp)
    {
        return gmdate($format, (int) $timestamp);
    }

    public static function parse_local_datetime_to_timestamp($datetime, $format = 'Y-m-d H:i:s')
    {
        $datetime = trim((string) $datetime);
        if ($datetime === '') {
            return false;
        }

        $tz = self::get_wp_timezone();
        $dt = DateTimeImmutable::createFromFormat('!' . $format, $datetime, $tz);

        if ($dt instanceof DateTimeImmutable) {
            return $dt->getTimestamp();
        }

        try {
            $dt = new DateTimeImmutable($datetime, $tz);
            return $dt->getTimestamp();
        }
        catch (Exception $e) {
            return false;
        }
    }
}

class WPvivid_Schedule_Time
{
    public static function get_next_run_timestamp($time, $local_time = true)
    {
        if (!is_array($time) || !isset($time['type']) || !isset($time['start_time'])) {
            return false;
        }

        $type = $time['type'];
        $start_time = $time['start_time'];

        if (!isset($start_time['current_day'])) {
            return false;
        }

        $tz = $local_time ? WPvivid_Time::get_wp_timezone() : new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $tz);

        $parsed = self::parse_time_string($start_time['current_day']);
        if ($parsed === false) {
            return false;
        }

        $hour   = $parsed['hour'];
        $minute = $parsed['minute'];
        $second = $parsed['second'];

        switch ($type) {
            case 'wpvivid_hourly':
            case 'wpvivid_2hours':
            case 'wpvivid_4hours':
            case 'wpvivid_6hours':
            case 'wpvivid_8hours':
            case 'wpvivid_12hours':
            case 'twicedaily':
            case 'wpvivid_daily':
            case 'wpvivid_2days':
            case 'wpvivid_3days':
            case 'daily':
            case 'onceday':
                return self::get_next_daily_like_run($now, $hour, $minute, $second);

            case 'wpvivid_weekly':
            case 'weekly':
            case 'wpvivid_fortnightly':
            case 'fortnightly':
                return self::get_next_weekly_run($now, $start_time, $hour, $minute, $second);

            case 'wpvivid_monthly':
            case 'monthly':
            case 'montly':
                return self::get_next_monthly_run($now, $start_time, $hour, $minute, $second);
        }

        return false;
    }

    private static function parse_time_string($time_string)
    {
        $time_string = trim((string) $time_string);

        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time_string, $m)) {
            return false;
        }

        return array(
            'hour'   => (int) $m[1],
            'minute' => (int) $m[2],
            'second' => isset($m[3]) ? (int) $m[3] : 0,
        );
    }

    private static function normalize_weekday($week)
    {
        $week = strtolower(trim((string) $week));

        $map = array(
            'mon' => 'Monday',
            'monday' => 'Monday',
            'tue' => 'Tuesday',
            'tues' => 'Tuesday',
            'tuesday' => 'Tuesday',
            'wed' => 'Wednesday',
            'wednesday' => 'Wednesday',
            'thu' => 'Thursday',
            'thur' => 'Thursday',
            'thurs' => 'Thursday',
            'thursday' => 'Thursday',
            'fri' => 'Friday',
            'friday' => 'Friday',
            'sat' => 'Saturday',
            'saturday' => 'Saturday',
            'sun' => 'Sunday',
            'sunday' => 'Sunday',
        );

        return isset($map[$week]) ? $map[$week] : false;
    }

    private static function get_next_daily_like_run(DateTimeImmutable $now, $hour, $minute, $second)
    {
        $candidate = $now->setTime($hour, $minute, $second);

        if ($candidate <= $now) {
            $candidate = $candidate->modify('+1 day');
        }

        return $candidate->getTimestamp();
    }

    private static function get_next_weekly_run(DateTimeImmutable $now, $start_time, $hour, $minute, $second)
    {
        if (empty($start_time['week'])) {
            return false;
        }

        $week = self::normalize_weekday($start_time['week']);
        if ($week === false) {
            return false;
        }

        $today_weekday = $now->format('l');

        if (strcasecmp($today_weekday, $week) === 0) {
            $candidate = $now->setTime($hour, $minute, $second);

            if ($candidate <= $now) {
                $candidate = $candidate->modify('+1 week');
            }

            return $candidate->getTimestamp();
        }

        $candidate = new DateTimeImmutable('next ' . $week, $now->getTimezone());
        $candidate = $candidate->setTime($hour, $minute, $second);

        return $candidate->getTimestamp();
    }

    private static function get_next_monthly_run(DateTimeImmutable $now, $start_time, $hour, $minute, $second)
    {
        $day = isset($start_time['day']) ? (int) $start_time['day'] : 1;
        if ($day < 1) {
            $day = 1;
        }

        $year  = (int) $now->format('Y');
        $month = (int) $now->format('n');

        $last_day = (int) $now->format('t');
        $target_day = min($day, $last_day);

        $candidate = DateTimeImmutable::createFromFormat(
            '!Y-n-j H:i:s',
            sprintf('%04d-%d-%d %02d:%02d:%02d', $year, $month, $target_day, $hour, $minute, $second),
            $now->getTimezone()
        );

        if (!$candidate) {
            return false;
        }

        if ($candidate <= $now) {
            $next_month = $now->modify('first day of next month');
            $year  = (int) $next_month->format('Y');
            $month = (int) $next_month->format('n');
            $last_day = (int) $next_month->format('t');
            $target_day = min($day, $last_day);

            $candidate = DateTimeImmutable::createFromFormat(
                '!Y-n-j H:i:s',
                sprintf('%04d-%d-%d %02d:%02d:%02d', $year, $month, $target_day, $hour, $minute, $second),
                $now->getTimezone()
            );

            if (!$candidate) {
                return false;
            }
        }

        return $candidate->getTimestamp();
    }
}