<?php
declare(strict_types=1);

namespace Crustum\Mcp\Support;

use Cake\Log\Log;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Static MCP logger (`OAuthDebugLog::warning(...)`) with auto `mcp` scope.
 *
 * Levels come from {@see AbstractLogger} on a shared inner logger. This class
 * does not extend it — PHP would otherwise block static calls to instance methods.
 *
 * @method static void emergency(\Stringable|string $message, array $context = [])
 * @method static void alert(\Stringable|string $message, array $context = [])
 * @method static void critical(\Stringable|string $message, array $context = [])
 * @method static void error(\Stringable|string $message, array $context = [])
 * @method static void warning(\Stringable|string $message, array $context = [])
 * @method static void notice(\Stringable|string $message, array $context = [])
 * @method static void info(\Stringable|string $message, array $context = [])
 * @method static void debug(\Stringable|string $message, array $context = [])
 * @method static void log(mixed $level, \Stringable|string $message, array $context = [])
 */
class OAuthDebugLog
{
    /**
     * Log scope name for FileLog `scopes` configuration.
     */
    public const SCOPE = 'mcp';

    /**
     * @var \Psr\Log\AbstractLogger|null
     */
    protected static ?AbstractLogger $logger = null;

    /**
     * @param string $name Level method name
     * @param array<int, mixed> $arguments Message and optional context
     * @return void
     */
    public static function __callStatic(string $name, array $arguments): void
    {
        static::logger()->{$name}(...$arguments);
    }

    /**
     * Shared PSR logger that always scopes writes to {@see SCOPE}.
     *
     * @return \Psr\Log\AbstractLogger
     */
    protected static function logger(): AbstractLogger
    {
        return static::$logger ??= new class extends AbstractLogger
        {
            /**
             * @inheritDoc
             */
            public function log($level, Stringable|string $message, array $context = []): void
            {
                $context['scope'] = [OAuthDebugLog::SCOPE];
                Log::write($level, $message, $context);
            }
        };
    }
}
