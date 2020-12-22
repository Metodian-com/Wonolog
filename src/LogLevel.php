<?php

/**
 * This file is part of the Wonolog package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Inpsyde\Wonolog;

use Monolog\Logger;

/**
 * Utility object used to build default min logging level based WordPress and environment settings.
 * It also has a method to check the validity of a value as level identifier.
 */
abstract class LogLevel
{
    public const DEBUG = Logger::DEBUG;
    public const INFO = Logger::INFO;
    public const NOTICE = Logger::NOTICE;
    public const WARNING = Logger::WARNING;
    public const ERROR = Logger::ERROR;
    public const CRITICAL = Logger::CRITICAL;
    public const ALERT = Logger::ALERT;
    public const EMERGENCY = Logger::EMERGENCY;

    private static $allLevels;

    /**
     * @var int|null
     */
    private static $minLevel;

    /**
     * @return array<string, int>
     */
    final public static function allLevels(): array
    {
        if (self::$allLevels === null) {
            self::$allLevels = Logger::getLevels();
        }

        return self::$allLevels;
    }

    /**
     * Returns the minimum default log level based on environment variable or WordPress debug
     * settings (in this order of priority).
     *
     * The level is set once per request and it is filterable.
     *
     * @return int
     */
    final public static function defaultMinLevel(): int
    {
        if (self::$minLevel !== null) {
            return self::$minLevel;
        }

        $envLevel = getenv('WONOLOG_DEFAULT_MIN_LEVEL');

        $minLevel = static::normalizeLevel($envLevel ?: null);

        // If no valid level is defined via env var, then let's resort to WP constants.
        if (!$minLevel) {
            $const = defined('WP_DEBUG_LOG') ? 'WP_DEBUG_LOG' : 'WP_DEBUG';
            $minLevel = (defined($const) && constant($const)) ? Logger::DEBUG : Logger::WARNING;
        }

        self::$minLevel = $minLevel;

        return $minLevel;
    }

    /**
     * @param int $numLevel
     * @return string
     */
    final public static function toPsrLevel(int $numLevel): string
    {
        switch ($numLevel) {
            case self::EMERGENCY:
                return \Psr\Log\LogLevel::EMERGENCY;
            case self::ALERT:
                return \Psr\Log\LogLevel::ALERT;
            case self::CRITICAL:
                return \Psr\Log\LogLevel::CRITICAL;
            case self::ERROR:
                return \Psr\Log\LogLevel::ERROR;
            case self::WARNING:
                return \Psr\Log\LogLevel::WARNING;
            case self::NOTICE:
                return \Psr\Log\LogLevel::NOTICE;
            case self::INFO:
                return \Psr\Log\LogLevel::INFO;
        }

        return \Psr\Log\LogLevel::DEBUG;
    }

    /**
     * @param string $psrLevel
     * @return int
     */
    final public static function toNumericLevel(string $psrLevel): int
    {
        return static::normalizeLevel($psrLevel) ?? self::DEBUG;
    }

    /**
     * In Monolog/Wonolog there're two ways to indicate a logger level:
     * - a integer value
     * - level "names".
     * Names are defined in the PSR-3 specification, integers are used in Monolog to allow severity
     * comparison: the higher the number, the higher the severity.
     *
     * This method always return a numerical representation of a log level.
     *
     * If there's no way to resolve the given level, null is returned.
     *
     * @param mixed $level
     * @return int|null
     *
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    final public static function normalizeLevel($level): ?int
    {
        // phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration

        if (!$level) {
            return null;
        }

        if (is_numeric($level)) {
            $level = (int)$level;

            return in_array($level, self::allLevels(), true) ? $level : null;
        }

        if (!is_string($level)) {
            return null;
        }

        try {
            return static::normalizeLevel(Logger::toMonologLevel(trim($level)));
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
