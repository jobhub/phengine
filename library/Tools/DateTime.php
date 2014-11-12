<?php

namespace Library\Tools;


class DateTime extends \DateTime {
    const ONE_DAY = 86400;
	  const ONE_HOUR = 3600;
	  const ONE_MINUTE = 60;


    private static $config;

    /**
     * The format to use when formatting a time using `DateTime::nice()`
     *
     * The format should use the locale strings as defined in the PHP docs under
     * `strftime` (http://php.net/manual/en/function.strftime.php)
     *
     * @var string
     */
    public static $niceFormat = '%a, %b %eS %Y, %H:%M';

    /**
     * The format to use when formatting a time using `DateTime::timeAgoInWords()`
     * and the difference is more than `DateTime::$wordEnd`
     *
     * @var string
     */
    public static $wordFormat = 'j/n/y';

    /**
     * The format to use when formatting a time using `DateTime::niceShort()`
     * and the difference is between 3 and 7 days
     *
     * @var string
     */
    public static $niceShortFormat = '%B %d, %H:%M';

    /**
     * The format to use when formatting a time using `DateTime::timeAgoInWords()`
     * and the difference is less than `DateTime::$wordEnd`
     *
     * @var array
     */
    public static $wordAccuracy = array(
        'year' => "day",
        'month' => "day",
        'week' => "day",
        'day' => "hour",
        'hour' => "minute",
        'minute' => "minute",
        'second' => "second",
    );

    /**
     * The end of relative time telling
     *
     * @var string
     */
    public static $wordEnd = '+1 month';

    /**
     * Temporary variable containing the timestamp value, used internally in convertSpecifiers()
     *
     * @var integer
     */
    protected static $_time = null;
    
    public static function setConfig($config)
    {
        self::$config = $config;
    }

    /**
     * Magic set method for backwards compatibility.
     * Used by helpers to modify static variables in DateTime
     *
     * @param string $name Variable name
     * @param mixes
     * @return void
     */
    public function __set($name, $value) {
        switch ($name) {
            case 'niceFormat':
                self::${$name} = $value;
                break;
        }
    }

    /**
     * Magic set method for backwards compatibility.
     * Used by helpers to get static variables in DateTime
     *
     * @param string $name Variable name
     * @return mixed
     */
    public function __get($name) {
        switch ($name) {
            case 'niceFormat':
                return self::${$name};
            default:
                return null;
        }
    }

    /**
     * Converts a string representing the format for the function strftime and returns a
     * windows safe and i18n aware format.
     *
     * @param string $format Format with specifiers for strftime function.
     * Accepts the special specifier %S which mimics the modifier S for date()
     * @param string $time UNIX timestamp
     * @return string windows safe and date() function compatible format for strftime
     */
    public static function convertSpecifiers($format, $time = null) {
        if (!$time) {
            $time = time();
        }
        self::$_time = $time;
        return preg_replace_callback('/\%(\w+)/', array('\Core\Utils\DateTime', '_translateSpecifier'), $format);
    }

    /**
     * Auxiliary function to translate a matched specifier element from a regular expression into
     * a windows safe and i18n aware specifier
     *
     * @param array $specifier match from regular expression
     * @return string converted element
     */
    protected static function _translateSpecifier($specifier) {
        switch ($specifier[1]) {
            case 'a':
                $abday = 'abday';
                if (is_array($abday)) {
                    return $abday[date('w', self::$_time)];
                }
                break;
            case 'A':
                $day = 'day';
                if (is_array($day)) {
                    return $day[date('w', self::$_time)];
                }
                break;
            case 'c':
                $format = 'd_t_fmt';
                if ($format !== 'd_t_fmt') {
                    return self::convertSpecifiers($format, self::$_time);
                }
                break;
            case 'C':
                return sprintf("%02d", date('Y', self::$_time) / 100);
            case 'D':
                return '%m/%d/%y';
            case 'e':
                if (DIRECTORY_SEPARATOR === '/') {
                    return '%e';
                }
                $day = date('j', self::$_time);
                if ($day < 10) {
                    $day = ' ' . $day;
                }
                return $day;
            case 'eS' :
                return date('jS', self::$_time);
            case 'b':
            case 'h':
                $months = 'abmon';
                if (is_array($months)) {
                    return $months[date('n', self::$_time) - 1];
                }
                return '%b';
            case 'B':
                $months = 'mon';
                if (is_array($months)) {
                    return $months[date('n', self::$_time) - 1];
                }
                break;
            case 'n':
                return "\n";
            case 'p':
            case 'P':
                $default = array('am' => 0, 'pm' => 1);
                $meridiem = $default[date('a', self::$_time)];
                $format = 'am_pm';
                if (is_array($format)) {
                    $meridiem = $format[$meridiem];
                    return ($specifier[1] === 'P') ? strtolower($meridiem) : strtoupper($meridiem);
                }
                break;
            case 'r':
                $complete = 't_fmt_ampm';
                if ($complete !== 't_fmt_ampm') {
                    return str_replace('%p', self::_translateSpecifier(array('%p', 'p')), $complete);
                }
                break;
            case 'R':
                return date('H:i', self::$_time);
            case 't':
                return "\t";
            case 'T':
                return '%H:%M:%S';
            case 'u':
                return ($weekDay = date('w', self::$_time)) ? $weekDay : 7;
            case 'x':
                $format = 'd_fmt';
                if ($format !== 'd_fmt') {
                    return self::convertSpecifiers($format, self::$_time);
                }
                break;
            case 'X':
                $format = 't_fmt';
                if ($format !== 't_fmt') {
                    return self::convertSpecifiers($format, self::$_time);
                }
                break;
        }
        return $specifier[0];
    }

    /**
     * Converts given time (in server's time zone) to user's local time, given his/her timezone.
     *
     * @param int $serverTime UNIX timestamp
     * @param string|\DateTimeZone $timezone User's timezone string or \DateTimeZone object
     * @return integer UNIX timestamp
     */
    public static function convert($serverTime, $timezone) {
        static $serverTimezone = null;
        if ($serverTimezone === null || (date_default_timezone_get() !== $serverTimezone->getName())) {
            $serverTimezone = new \DateTimeZone(date_default_timezone_get());
        }
        $serverOffset = $serverTimezone->getOffset(new \DateTime('@' . $serverTime));
        $gmtTime = $serverTime - $serverOffset;
        if (is_numeric($timezone)) {
            $userOffset = $timezone * (60 * 60);
        } else {
            $timezone = self::timezone($timezone);
            $userOffset = $timezone->getOffset(new \DateTime('@' . $gmtTime));
        }
        $userTime = $gmtTime + $userOffset;
        return (int)$userTime;
    }

    /**
     * Returns a timezone object from a string or the user's timezone object
     *
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * If null it tries to get timezone from 'Config.timezone' config var
     * @return \DateTimeZone Timezone object
     */
    public static function timezone($timezone = null) {
        static $tz = null;

        if (is_object($timezone)) {
            if ($tz === null || $tz->getName() !== $timezone->getName()) {
                $tz = $timezone;
            }
        } else {
            if ($timezone === null) {
                $timezone = self::$config->application->timezone;
                if ($timezone === null) {
                    $timezone = date_default_timezone_get();
                }
            }

            if ($tz === null || $tz->getName() !== $timezone) {
                $tz = new \DateTimeZone($timezone);
            }
        }

        return $tz;
    }

    /**
     * Returns server's offset from GMT in seconds.
     *
     * @return integer Offset
     */
    public static function serverOffset() {
        return date('Z', time());
    }

    /**
     * Returns a UNIX timestamp, given either a UNIX timestamp or a valid strtotime() date string.
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return string Parsed timestamp
     */
    public static function fromString($dateString, $timezone = null) {
        if (empty($dateString)) {
            return false;
        }

        $containsDummyDate = (is_string($dateString) && substr($dateString, 0, 10) === '0000-00-00');
        if ($containsDummyDate) {
            return false;
        }

        if (is_int($dateString) || is_numeric($dateString)) {
            $date = intval($dateString);
        } elseif (
            $dateString instanceof DateTime &&
            $dateString->getTimezone()->getName() != date_default_timezone_get()
        ) {
            $clone = clone $dateString;
            $clone->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            $date = (int)$clone->format('U') + $clone->getOffset();
        } elseif ($dateString instanceof DateTime) {
            $date = (int)$dateString->format('U');
        } else {
            $date = strtotime($dateString);
        }

        if ($date === -1 || empty($date)) {
            return false;
        }

        if ($timezone === null) {
            $timezone = self::$config->application->timezone;
        }

        if ($timezone !== null) {
            return self::convert($date, $timezone);
        }
        return $date;
    }

    /**
     * Returns a nicely formatted date string for given Datetime string.
     *
     * See http://php.net/manual/en/function.strftime.php for information on formatting
     * using locale strings.
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @param string $format The format to use. If null, `TimeHelper::$niceFormat` is used
     * @return string Formatted date string
     */
    public static function nice($dateString = null, $timezone = null, $format = null) {
        if (!$dateString) {
            $dateString = time();
        }
        $date = self::fromString($dateString, $timezone);

        if (!$format) {
            $format = self::$niceFormat;
        }
        return self::_strftime(self::convertSpecifiers($format, $date), $date);
    }

    /**
     * Returns a formatted descriptive date string for given datetime string.
     *
     * If the given date is today, the returned string could be "Today, 16:54".
     * If the given date is tomorrow, the returned string could be "Tomorrow, 16:54".
     * If the given date was yesterday, the returned string could be "Yesterday, 16:54".
     * If the given date is within next or last week, the returned string could be "On Thursday, 16:54".
     * If $dateString's year is the current year, the returned string does not
     * include mention of the year.
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return string Described, relative date string
     */
    public static function niceShort($dateString = null, $timezone = null) {
        if (!$dateString) {
            $dateString = time();
        }
        $date = self::fromString($dateString, $timezone);

        if (self::isToday($dateString, $timezone)) {
            return sprintf('Today, %s', self::_strftime("%H:%M", $date));
        }
        if (self::wasYesterday($dateString, $timezone)) {
            return sprintf('Yesterday, %s', self::_strftime("%H:%M", $date));
        }
        if (self::isTomorrow($dateString, $timezone)) {
            return sprintf('Tomorrow, %s', self::_strftime("%H:%M", $date));
        }

        $d = self::_strftime("%w", $date);
        $day = array(
            'Sunday',
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday'
        );
        if (self::wasWithinLast('7 days', $dateString, $timezone)) {
            return sprintf('%s %s', $day[$d], self::_strftime(self::$niceShortFormat, $date));
        }
        if (self::isWithinNext('7 days', $dateString, $timezone)) {
            return sprintf('On %s %s', $day[$d], self::_strftime(self::$niceShortFormat, $date));
        }

        $y = '';
        if (!self::isThisYear($date)) {
            $y = ' %Y';
        }
        return self::_strftime(self::convertSpecifiers("%b %eS{$y}, %H:%M", $date), $date);
    }

    /**
     * Returns true if given datetime string is today.
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return boolean True if datetime string is today
     */
    public static function isToday($dateString, $timezone = null) {
        $timestamp = self::fromString($dateString, $timezone);
        $now = self::fromString('now', $timezone);
        return date('Y-m-d', $timestamp) == date('Y-m-d', $now);
    }

    /**
     * Returns true if given datetime string is in the future.
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return boolean True if datetime string is in the future
     */
    public static function isFuture($dateString, $timezone = null) {
        $timestamp = self::fromString($dateString, $timezone);
        return $timestamp > time();
    }

    /**
     * Returns true if given datetime string is in the past.
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return boolean True if datetime string is in the past
     */
    public static function isPast($dateString, $timezone = null) {
        $timestamp = self::fromString($dateString, $timezone);
        return $timestamp < time();
    }

    /**
     * Returns true if given datetime string is within this week.
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return boolean True if datetime string is within current week
     */
    public static function isThisWeek($dateString, $timezone = null) {
        $timestamp = self::fromString($dateString, $timezone);
        $now = self::fromString('now', $timezone);
        return date('W o', $timestamp) === date('W o', $now);
    }

    /**
     * Returns true if given datetime string is within this month
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return boolean True if datetime string is within current month
     */
    public static function isThisMonth($dateString, $timezone = null) {
        $timestamp = self::fromString($dateString, $timezone);
        $now = self::fromString('now', $timezone);
        return date('m Y', $timestamp) === date('m Y', $now);
    }

    /**
     * Returns true if given datetime string is within current year.
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return boolean True if datetime string is within current year
     */
    public static function isThisYear($dateString, $timezone = null) {
        $timestamp = self::fromString($dateString, $timezone);
        $now = self::fromString('now', $timezone);
        return date('Y', $timestamp) === date('Y', $now);
    }

    /**
     * Returns true if given datetime string was yesterday.
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return boolean True if datetime string was yesterday
     */
    public static function wasYesterday($dateString, $timezone = null) {
        $timestamp = self::fromString($dateString, $timezone);
        $yesterday = self::fromString('yesterday', $timezone);
        return date('Y-m-d', $timestamp) === date('Y-m-d', $yesterday);
    }

    /**
     * Returns true if given datetime string is tomorrow.
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return boolean True if datetime string was yesterday
     */
    public static function isTomorrow($dateString, $timezone = null) {
        $timestamp = self::fromString($dateString, $timezone);
        $tomorrow = self::fromString('tomorrow', $timezone);
        return date('Y-m-d', $timestamp) === date('Y-m-d', $tomorrow);
    }

    /**
     * Returns the quarter
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param boolean $range if true returns a range in Y-m-d format
     * @return mixed 1, 2, 3, or 4 quarter of year or array if $range true
     */
    public static function toQuarter($dateString, $range = false) {
        $time = self::fromString($dateString);
        $date = ceil(date('m', $time) / 3);
        if ($range === false) {
            return $date;
        }

        $year = date('Y', $time);
        switch ($date) {
            case 1:
                return array($year . '-01-01', $year . '-03-31');
            case 2:
                return array($year . '-04-01', $year . '-06-30');
            case 3:
                return array($year . '-07-01', $year . '-09-30');
            case 4:
                return array($year . '-10-01', $year . '-12-31');
        }
    }

    /**
     * Returns a UNIX timestamp from a textual datetime description. Wrapper for PHP function strtotime().
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return integer Unix timestamp
     */
    public static function toUnix($dateString, $timezone = null) {
        return self::fromString($dateString, $timezone);
    }

    /**
     * Returns a formatted date in server's timezone.
     *
     * If a DateTime object is given or the dateString has a timezone
     * segment, the timezone parameter will be ignored.
     *
     * If no timezone parameter is given and no DateTime object, the passed $dateString will be
     * considered to be in the UTC timezone.
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @param string $format date format string
     * @return mixed Formatted date
     */
    public static function toServer($dateString, $timezone = null, $format = 'Y-m-d H:i:s') {
        if ($timezone === null) {
            $timezone = new \DateTimeZone('UTC');
        } elseif (is_string($timezone)) {
            $timezone = new \DateTimeZone($timezone);
        } elseif (!($timezone instanceof \DateTimeZone)) {
            return false;
        }

        if ($dateString instanceof DateTime) {
            $date = $dateString;
        } elseif (is_int($dateString) || is_numeric($dateString)) {
            $dateString = (int)$dateString;

            $date = new \DateTime('@' . $dateString);
            $date->setTimezone($timezone);
        } else {
            $date = new \DateTime($dateString, $timezone);
        }

        $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        return $date->format($format);
    }

    /**
     * Returns a date formatted for Atom RSS feeds.
     *
     * @param string $dateString Datetime string or Unix timestamp
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return string Formatted date string
     */
    public static function toAtom($dateString, $timezone = null) {
        return date('Y-m-d\TH:i:s\Z', self::fromString($dateString, $timezone));
    }

    /**
     * Formats date for RSS feeds
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return string Formatted date string
     */
    public static function toRSS($dateString, $timezone = null) {
        $date = self::fromString($dateString, $timezone);

        if ($timezone === null) {
            return date("r", $date);
        }

        $userOffset = $timezone;
        if (!is_numeric($timezone)) {
            if (!is_object($timezone)) {
                $timezone = new \DateTimeZone($timezone);
            }
            $currentDate = new \DateTime('@' . $date);
            $currentDate->setTimezone($timezone);
            $userOffset = $timezone->getOffset($currentDate) / 60 / 60;
        }

        $timezone = '+0000';
        if ($userOffset != 0) {
            $hours = (int)floor(abs($userOffset));
            $minutes = (int)(fmod(abs($userOffset), $hours) * 60);
            $timezone = ($userOffset < 0 ? '-' : '+') . str_pad($hours, 2, '0', STR_PAD_LEFT) . str_pad($minutes, 2, '0', STR_PAD_LEFT);
        }
        return date('D, d M Y H:i:s', $date) . ' ' . $timezone;
    }

    /**
     * Returns either a relative or a formatted absolute date depending
     * on the difference between the current time and given datetime.
     * $datetime should be in a *strtotime* - parsable format, like MySQL's datetime datatype.
     *
     * ### Options:
     *
     * - `format` => a fall back format if the relative time is longer than the duration specified by end
     * - `accuracy` => Specifies how accurate the date should be described (array)
     * - year => The format if years > 0 (default "day")
     * - month => The format if months > 0 (default "day")
     * - week => The format if weeks > 0 (default "day")
     * - day => The format if weeks > 0 (default "hour")
     * - hour => The format if hours > 0 (default "minute")
     * - minute => The format if minutes > 0 (default "minute")
     * - second => The format if seconds > 0 (default "second")
     * - `end` => The end of relative time telling
     * - `relativeString` => The printf compatible string when outputting relative time
     * - `absoluteString` => The printf compatible string when outputting absolute time
     * - `userOffset` => Users offset from GMT (in hours) *Deprecated* use timezone intead.
     * - `timezone` => The user timezone the timestamp should be formatted in.
     *
     * Relative dates look something like this:
     *
     * - 3 weeks, 4 days ago
     * - 15 seconds ago
     *
     * Default date formatting is d/m/yy e.g: on 18/2/09
     *
     * The returned string includes 'ago' or 'on' and assumes you'll properly add a word
     * like 'Posted ' before the function output.
     *
     * NOTE: If the difference is one week or more, the lowest level of accuracy is day
     *
     * @param integer|string|DateTime $dateTime Datetime UNIX timestamp, strtotime() valid string or DateTime object
     * @param array $options Default format if timestamp is used in $dateString
     * @return string Relative time string.
     */
    public static function timeAgoInWords($dateTime, $options = array()) {
        $timezone = null;
        $format = self::$wordFormat;
        $end = self::$wordEnd;
        $relativeString = '%s ago';
        $absoluteString = 'on %s';
        $accuracy = self::$wordAccuracy;

        if (is_array($options)) {
            if (isset($options['timezone'])) {
                $timezone = $options['timezone'];
            } elseif (isset($options['userOffset'])) {
                $timezone = $options['userOffset'];
            }

            if (isset($options['accuracy'])) {
                if (is_array($options['accuracy'])) {
                    $accuracy = array_merge($accuracy, $options['accuracy']);
                } else {
                    foreach ($accuracy as $key => $level) {
                        $accuracy[$key] = $options['accuracy'];
                    }
                }
            }

            if (isset($options['format'])) {
                $format = $options['format'];
            }
            if (isset($options['end'])) {
                $end = $options['end'];
            }
            if (isset($options['relativeString'])) {
                $relativeString = $options['relativeString'];
                unset($options['relativeString']);
            }
            if (isset($options['absoluteString'])) {
                $absoluteString = $options['absoluteString'];
                unset($options['absoluteString']);
            }
            unset($options['end'], $options['format']);
        } else {
            $format = $options;
        }

        $now = self::fromString(time(), $timezone);
        $inSeconds = self::fromString($dateTime, $timezone);
        $backwards = ($inSeconds > $now);

        $futureTime = $now;
        $pastTime = $inSeconds;
        if ($backwards) {
            $futureTime = $inSeconds;
            $pastTime = $now;
        }
        $diff = $futureTime - $pastTime;

        if (!$diff) {
            return sprintf('just now', 'just now');
        }

        if ($diff > abs($now - self::fromString($end))) {
            return sprintf($absoluteString, date($format, $inSeconds));
        }

// If more than a week, then take into account the length of months
        if ($diff >= 604800) {
            list($future['H'], $future['i'], $future['s'], $future['d'], $future['m'], $future['Y']) = explode('/', date('H/i/s/d/m/Y', $futureTime));

            list($past['H'], $past['i'], $past['s'], $past['d'], $past['m'], $past['Y']) = explode('/', date('H/i/s/d/m/Y', $pastTime));
            $years = $months = $weeks = $days = $hours = $minutes = $seconds = 0;

            $years = $future['Y'] - $past['Y'];
            $months = $future['m'] + ((12 * $years) - $past['m']);

            if ($months >= 12) {
                $years = floor($months / 12);
                $months = $months - ($years * 12);
            }
            if ($future['m'] < $past['m'] && $future['Y'] - $past['Y'] === 1) {
                $years--;
            }

            if ($future['d'] >= $past['d']) {
                $days = $future['d'] - $past['d'];
            } else {
                $daysInPastMonth = date('t', $pastTime);
                $daysInFutureMonth = date('t', mktime(0, 0, 0, $future['m'] - 1, 1, $future['Y']));

                if (!$backwards) {
                    $days = ($daysInPastMonth - $past['d']) + $future['d'];
                } else {
                    $days = ($daysInFutureMonth - $past['d']) + $future['d'];
                }

                if ($future['m'] != $past['m']) {
                    $months--;
                }
            }

            if (!$months && $years >= 1 && $diff < ($years * 31536000)) {
                $months = 11;
                $years--;
            }

            if ($months >= 12) {
                $years = $years + 1;
                $months = $months - 12;
            }

            if ($days >= 7) {
                $weeks = floor($days / 7);
                $days = $days - ($weeks * 7);
            }
        } else {
            $years = $months = $weeks = 0;
            $days = floor($diff / 86400);

            $diff = $diff - ($days * 86400);

            $hours = floor($diff / 3600);
            $diff = $diff - ($hours * 3600);

            $minutes = floor($diff / 60);
            $diff = $diff - ($minutes * 60);
            $seconds = $diff;
        }

        $fWord = $accuracy['second'];
        if ($years > 0) {
            $fWord = $accuracy['year'];
        } elseif (abs($months) > 0) {
            $fWord = $accuracy['month'];
        } elseif (abs($weeks) > 0) {
            $fWord = $accuracy['week'];
        } elseif (abs($days) > 0) {
            $fWord = $accuracy['day'];
        } elseif (abs($hours) > 0) {
            $fWord = $accuracy['hour'];
        } elseif (abs($minutes) > 0) {
            $fWord = $accuracy['minute'];
        }

        $fNum = str_replace(array('year', 'month', 'week', 'day', 'hour', 'minute', 'second'), array(1, 2, 3, 4, 5, 6, 7), $fWord);

        $relativeDate = '';
        if ($fNum >= 1 && $years > 0) {
            $relativeDate .= ($relativeDate ? ', ' : '') . sprintf('%d year', $years);
        }
        if ($fNum >= 2 && $months > 0) {
            $relativeDate .= ($relativeDate ? ', ' : '') . sprintf('%d month', $months);
        }
        if ($fNum >= 3 && $weeks > 0) {
            $relativeDate .= ($relativeDate ? ', ' : '') . sprintf('%d week', $weeks);
        }
        if ($fNum >= 4 && $days > 0) {
            $relativeDate .= ($relativeDate ? ', ' : '') . sprintf('%d day', $days);
        }
        if ($fNum >= 5 && $hours > 0) {
            $relativeDate .= ($relativeDate ? ', ' : '') . sprintf('%d hour', $hours);
        }
        if ($fNum >= 6 && $minutes > 0) {
            $relativeDate .= ($relativeDate ? ', ' : '') . sprintf('%d minute', $minutes);
        }
        if ($fNum >= 7 && $seconds > 0) {
            $relativeDate .= ($relativeDate ? ', ' : '') . sprintf('%d second', $seconds);
        }

// When time has passed
        if (!$backwards && $relativeDate) {
            return sprintf($relativeString, $relativeDate);
        }
        if (!$backwards) {
            $aboutAgo = array(
                'second' => 'about a second ago',
                'minute' => 'about a minute ago',
                'hour' => 'about an hour ago',
                'day' => 'about a day ago',
                'week' => 'about a week ago',
                'year' => 'about a year ago'
            );

            return $aboutAgo[$fWord];
        }

// When time is to come
        if (!$relativeDate) {
            $aboutIn = array(
                'second' => 'in about a second',
                'minute' => 'in about a minute',
                'hour' => 'in about an hour',
                'day' => 'in about a day',
                'week' => 'in about a week',
                'year' => 'in about a year'
            );

            return $aboutIn[$fWord];
        }

        return $relativeDate;
    }

    /**
     * Returns true if specified datetime was within the interval specified, else false.
     *
     * @param string|integer $timeInterval the numeric value with space then time type.
     * Example of valid types: 6 hours, 2 days, 1 minute.
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return boolean
     */
    public static function wasWithinLast($timeInterval, $dateString, $timezone = null) {
        $tmp = str_replace(' ', '', $timeInterval);
        if (is_numeric($tmp)) {
            $timeInterval = $tmp . ' ' . 'days';
        }

        $date = self::fromString($dateString, $timezone);
        $interval = self::fromString('-' . $timeInterval);
        $now = self::fromString('now', $timezone);

        return $date >= $interval && $date <= $now;
    }

    /**
     * Returns true if specified datetime is within the interval specified, else false.
     *
     * @param string|integer $timeInterval the numeric value with space then time type.
     * Example of valid types: 6 hours, 2 days, 1 minute.
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return boolean
     */
    public static function isWithinNext($timeInterval, $dateString, $timezone = null) {
        $tmp = str_replace(' ', '', $timeInterval);
        if (is_numeric($tmp)) {
            $timeInterval = $tmp . ' ' . 'days';
        }

        $date = self::fromString($dateString, $timezone);
        $interval = self::fromString('+' . $timeInterval);
        $now = self::fromString('now', $timezone);

        return $date <= $interval && $date >= $now;
    }

    /**
     * Returns gmt as a UNIX timestamp.
     *
     * @param integer|string|DateTime $dateString UNIX timestamp, strtotime() valid string or DateTime object
     * @return integer UNIX timestamp
     */
    public static function gmt($dateString = null) {
        $time = time();
        if ($dateString) {
            $time = self::fromString($dateString);
        }
        return gmmktime(
            intval(date('G', $time)),
            intval(date('i', $time)),
            intval(date('s', $time)),
            intval(date('n', $time)),
            intval(date('j', $time)),
            intval(date('Y', $time))
        );
    }

    /**
     * Returns a formatted date string, given either a UNIX timestamp or a valid strtotime() date string.
     * This function also accepts a time string and a format string as first and second parameters.
     * In that case this function behaves as a wrapper for TimeHelper::i18nFormat()
     *
     * ## Examples
     *
     * Create localized & formatted time:
     *
     * {{{
     * DateTime::format('2012-02-15', '%m-%d-%Y'); // returns 02-15-2012
     * DateTime::format('2012-02-15 23:01:01', '%c'); // returns preferred date and time based on configured locale
     * DateTime::format('0000-00-00', '%d-%m-%Y', 'N/A'); // return N/A becuase an invalid date was passed
     * DateTime::format('2012-02-15 23:01:01', '%c', 'N/A', 'America/New_York'); // converts passed date to timezone
     * }}}
     *
     * @param integer|string|DateTime $date UNIX timestamp, strtotime() valid string or DateTime object (or a date format string)
     * @param integer|string|DateTime $format date format string (or UNIX timestamp, strtotime() valid string or DateTime object)
     * @param boolean|string $default if an invalid date is passed it will output supplied default value. Pass false if you want raw conversion value
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return string Formatted date string
     * @see DateTime::i18nFormat()
     */
    public static function formatDate($date, $format = null, $default = false, $timezone = null) {
//Backwards compatible params re-order test
        $time = self::fromString($format, $timezone);

        if ($time === false) {
            return self::i18nFormat($date, $format, $default, $timezone);
        }
        return date($date, $time);
    }

    /**
     * Returns a formatted date string, given either a UNIX timestamp or a valid strtotime() date string.
     * It takes into account the default date format for the current language if a LC_TIME file is used.
     *
     * @param integer|string|DateTime $date UNIX timestamp, strtotime() valid string or DateTime object
     * @param string $format strftime format string.
     * @param boolean|string $default if an invalid date is passed it will output supplied default value. Pass false if you want raw conversion value
     * @param string|\DateTimeZone $timezone Timezone string or \DateTimeZone object
     * @return string Formatted and translated date string
     */
    public static function i18nFormat($date, $format = null, $default = false, $timezone = null) {
        $date = self::fromString($date, $timezone);
        if ($date === false && $default !== false) {
            return $default;
        }
        if (empty($format)) {
            $format = '%x';
        }
        return self::_strftime(self::convertSpecifiers($format, $date), $date);
    }

    /**
     * Get list of timezone identifiers
     *
     * @param integer|string $filter A regex to filter identifer
     * Or one of \DateTimeZone class constants (PHP 5.3 and above)
     * @param string $country A two-letter ISO 3166-1 compatible country code.
     * This option is only used when $filter is set to \DateTimeZone::PER_COUNTRY (available only in PHP 5.3 and above)
     * @param boolean $group If true (default value) groups the identifiers list by primary region
     * @return array List of timezone identifiers
     * @since 2.2
     */
    public static function listTimezones($filter = null, $country = null, $group = true) {
        $regex = null;
        if (is_string($filter)) {
            $regex = $filter;
            $filter = null;
        }
        if (version_compare(PHP_VERSION, '5.3.0', '<')) {
            if ($regex === null) {
                $regex = '#^((Africa|America|Antartica|Arctic|Asia|Atlantic|Australia|Europe|Indian|Pacific)/|UTC)#';
            }
            $identifiers = \DateTimeZone::listIdentifiers();
        } else {
            if ($filter === null) {
                $filter = \DateTimeZone::ALL;
            }
            $identifiers = \DateTimeZone::listIdentifiers($filter, $country);
        }

        if ($regex) {
            foreach ($identifiers as $key => $tz) {
                if (!preg_match($regex, $tz)) {
                    unset($identifiers[$key]);
                }
            }
        }

        if ($group) {
            $return = array();
            foreach ($identifiers as $key => $tz) {
                $item = explode('/', $tz, 2);
                if (isset($item[1])) {
                    $return[$item[0]][$tz] = $item[1];
                } else {
                    $return[$item[0]] = array($tz => $item[0]);
                }
            }
            return $return;
        }
        return array_combine($identifiers, $identifiers);
    }

    /**
     * Multibyte wrapper for strftime.
     *
     * Handles utf8_encoding the result of strftime when necessary.
     *
     * @param string $format Format string.
     * @param integer $date Timestamp to format.
     * @return string formatted string with correct encoding.
     */
    protected static function _strftime($format, $date) {
        $format = strftime($format, $date);
        $encoding = 'UTF-8';

        if (!empty($encoding) && $encoding === 'UTF-8') {
            if (function_exists('mb_check_encoding')) {
                $valid = mb_check_encoding($format, $encoding);
            } else {
                $valid = !self::checkMultibyte($format);
            }
            if (!$valid) {
                $format = utf8_encode($format);
            }
        }
        return $format;
    }

    public static function checkMultibyte($string) {
        $length = strlen($string);

        for ($i = 0; $i < $length; $i++) {
            $value = ord(($string[$i]));
            if ($value > 128) {
                return true;
            }
        }
        return false;
    }

	public static function db_date_to_timestamp($date)
  {
    if ( !is_string($date) )
    {
      return false;
    }
    if  (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}[.0-9]*([+-][0-9]+)?$/', $date))
    {
      preg_match('/^([\d]{4})-([\d]{2})-([\d]{2}) ([\d]{2}):([\d]{2}):([\d]{2})[.0-9]*([+-][0-9]+)?$/', $date, $date);
      //$date=strptime($date, '%Y-%m-%d %H:%M:%S');
      $time=mktime(intval($date[4]), intval($date[5]), intval($date[6]), intval($date[2]), intval($date[3]), intval($date[1]));
      if (isset($data[7])) {
        $time += self::ONE_HOUR*intval($data[7]);
      }
    }
    else
    {
      $time=strtotime($date);
    }
    return $time;
  }

  public static function db_timestamp_to_date($ts)
  {
    if (empty($ts))
    {
      return null;
    }
    return date('Y-m-d H:i:s', $ts);
  }


  public static function alignTimestamp($ts, $align_to='day')
  {
    if (empty($ts))
      return null;

    $align=0;
    $date = self::db_timestamp_to_date($ts+$align);
    if  (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}[.0-9]*$/', $date))
    {
      preg_match('/^([\d]{4})-([\d]{2})-([\d]{2}) ([\d]{2}):([\d]{2}):([\d]{2})[.0-9]*$/', $date, $date);
      //$date=strptime($date, '%Y-%m-%d %H:%M:%S');
      switch ($align_to) {
        case 'day':
          $time=mktime(23, 59, 59, intval($date[2]), intval($date[3]), intval($date[1]));
          break;
        case 'zeroday':
          $time=mktime(0, 0, 0, intval($date[2]), intval($date[3]), intval($date[1]));
          break;
        case 'zeromonth':
          $time=mktime(0, 0, 0, intval($date[2]), 1, intval($date[1]));
          break;
        case 'quarter':
          $m = 1 + 3*(1+ intval((intval($date[2])-1)/3));
          $y=intval($date[1]);
          if ($m>12) {
            $m-=12;
            $y++;
          }
          $time=mktime(0, 0, 0, $m, 1, $y)-1;
          break;
        case 'zeroquarter':
          $m = 1+3*intval((intval($date[2])-1)/3);
          $time=mktime(0, 0, 0, $m, 1, intval($date[1]));
          break;
        case 'zeroyear':
          $time=mktime(0, 0, 0, 1, 1, intval($date[1]));
          break;
        case 'year':
          $time=mktime(23, 59, 59, 12, 31, intval($date[1]));
          break;
        default:
          return false;
      }
      return $time;
    }
    return false;
  }

  public static function alignDate($date, $align='day') {
    $stamp = self::alignTimestamp(self::toTimestamp($date), $align);
    if (empty($stamp))
      return null;
    $pattern = 'c';
    return date($pattern,$stamp);
  }

  public static function addDays($date, $days, $is_timestamp=true)
  {
    if (!$is_timestamp) {
      $date = self::toTimestamp($date);
    }
    $date+=86400*$days;

    if (!$is_timestamp) {
      $date = self::db_timestamp_to_date($date);
    }
    return $date;
  }

  /**
 * Конвертирует дату-время произвольного формата в Unix Timestamp
 * @param mixed $stamp
 * @return int
 */
  public static function toTimestamp($stamp) {
    if ('now'===$stamp) {
      $stamp = time();
    } elseif (class_exists('\MongoDate') && $stamp instanceof \MongoDate) {
      $stamp = $stamp->sec+($stamp->usec/1000000);
    } elseif (!empty($stamp) && !is_numeric($stamp)) {
      $stamp = self::db_date_to_timestamp($stamp);
    }
    return $stamp;
  }

  /**
   * Конвертирует произвольный тип в строку даты-времени формата ISO. Пытается
   * сохранить часовой пояс, если он был указан в исходном времени
   * @param string|int $time время
   * @return string
   */
  public static function toIsoDate($time) {

    if (is_int($time) || is_float($time) || is_numeric($time)) {
      return date('c', $time);
    }
    if (class_exists('\MongoDate') && $time instanceof \MongoDate) {
      return date('c', self::toTimestamp($time));
    }

    $value = trim($time);
    $pattern = null;
    $tz_hour = null;
    $pattern_map = array(
      '@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+[+-]\d+$@'=>'yyyy-MM-dd HH:mm:ss.SZ',
      '@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+-]\d+$@'=>'yyyy-MM-dd HH:mm:ssZ',
      '@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+$@'=>'yyyy-MM-dd HH:mm:ss.S',
      '@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$@'=>'yyyy-MM-dd HH:mm:ss',
      '@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}Z$@'=>'yyyy-MM-dd HH:mm:ssZ0',
      '@^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$@'=>'yyyy-MM-ddTHH:mm:ssZZZZ',
      '@^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+[+-]\d{2}:\d{2}$@'=>'yyyy-MM-ddTHH:mm:ss.SZZZZ',
    );
    foreach ($pattern_map as $preg=>$p) {
      if (preg_match($preg, $value)) {
        $pattern = $p;
        break;
      }
    }
    if ($pattern && preg_match('@[^Z]Z$@', $pattern)) {
      $value .= '00';
    }
    if ($pattern && preg_match('@Z0$@', $pattern)) {
      $value .= '+0000';
      $pattern = substr($pattern, 0, -1);
    }
    if ($pattern && preg_match('@Z$@', $pattern)) {
      if (preg_match('@([+-]\d{2}):?\d{2}$@', $value, $matches)) {
        $tz_hour = intval($matches[1]);
        if ($tz_hour>12 || $tz_hour<-12) {
          $value = preg_replace('@\d{2}(:?\d{2})$@', '12$1', $value);
        }
      }
    }
    $value = new self($value);

    return $value->format('c');
  }

  public static function previousSunday($raw=false) {
    $dt = new self();
    $dt->modify('last Sunday');
    $dt->setTime(23, 59, 00);
    return ($raw) ? $dt : $dt->format('c');
  }

  public static function nextSunday($raw=false) {
    $dt = new self();
    $dt->modify('next Sunday');
    $dt->setTime(23, 59, 00);
    return ($raw) ? $dt : $dt->format('c');
  }

} 