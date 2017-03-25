<?php

namespace WASP\Util;

use Psr\Log\NullLogger;

/**
 * Static variant of Psr\Log\LoggerAwareTrait
 */
trait LoggerAwareStaticTrait
{
    /**
     * The logger instance.
     *
     * @var LoggerInterface
     */
    protected static $logger;

    /**
     * Sets a logger.
     *
     * @param LoggerInterface $logger
     */
    public static function setLogger(LoggerInterface $logger = null)
    {
        if ($logger === null)
            $logger = LoggerFactory::getLogger([static::class]);
        self::$logger = $logger;
    }

    /**
     * Get a logger. If not available yet, it will be created using a Hook, or
     * a NullLogger is instantiated.
     */
    public static function getLogger()
    {
        if (self::$logger === null)
        {
            $result = Hook::execute(
                "WASP.Util.LoggerAware.GetLogger", 
                ["logger" => null, "class" => static::class]
            );

            self::$logger = $result['logger'] ?? new NullLogger();
        }
    }
}
