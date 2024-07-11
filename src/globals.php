<?php
/**
 * Global helper functions.
 */

declare(strict_types=1);

use AI_Logger\AI_Logger;

/**
 * Return the logger singleton instance.
 */
function logger(): AI_Logger
{
    return ai_logger();
}

/**
 * Log severity 0 (Emergency): system is unusable.
 *
 * @param string  $message log message
 * @param mixed[] $context log context
 */
function emergency($message, array $context = []): void
{
    logger()->emergency($message, $context);
}

/**
 * Log severity 1 (Alert): Action must be taken immediately.
 *
 * Example: Entire website down, database unavailable, etc. This should
 * trigger the SMS alerts and wake you up.
 *
 * @param string  $message log message
 * @param mixed[] $context log context
 */
function alert($message, array $context = []): void
{
    logger()->alert($message, $context);
}

/**
 * Log severity 2 (Critical): Critical conditions.
 *
 * Example: Application component unavailable, unexpected exception.
 *
 * @param string  $message log message
 * @param mixed[] $context log context
 */
function critical($message, array $context = []): void
{
    logger()->critical($message, $context);
}

/**
 * Log severity 3 (Error): Runtime errors that do not require immediate action but should
 * typically be logged and monitored.
 *
 * @param string  $message log message
 * @param mixed[] $context log context
 */
function error($message, array $context = []): void
{
    logger()->error($message, $context);
}

/**
 * Log severity 4 (Warning): Exceptional occurrences that are not errors.
 *
 * Example: Use of deprecated APIs, poor use of an API, undesirable things
 * that are not necessarily wrong.
 *
 * @param string  $message log message
 * @param mixed[] $context log context
 */
function warning($message, array $context = []): void
{
    logger()->warning($message, $context);
}

/**
 * Log severity 5 (Notice): Normal but significant events.
 *
 * @param string  $message log message
 * @param mixed[] $context log context
 */
function notice($message, array $context = []): void
{
    logger()->notice($message, $context);
}

/**
 * Log severity 6 (Info): Interesting events.
 *
 * Example: User logs in, SQL logs.
 *
 * @param string  $message log message
 * @param mixed[] $context log context
 */
function info($message, array $context = []): void
{
    logger()->info($message, $context);
}

/**
 * Log severity 7 (Debug): Detailed debug information.
 *
 * @param string  $message log message
 * @param mixed[] $context log context
 */
function debug($message, array $context = []): void
{
    logger()->debug($message, $context);
}
