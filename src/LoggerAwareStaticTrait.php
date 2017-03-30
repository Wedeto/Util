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
    public static function setLogger(LoggerInterface $logger)
    {
        self::$logger = $logger;
    }

    /**
     * Set the logger to null, and call getLogger to initialize a new one
     */
    public static function resetLogger()
    {
        static::$logger = null;
        $this->setLogger();
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
                "WASP.Util.GetLogger", 
                ["logger" => null, "class" => static::class]
            );

            self::$logger = $result['logger'] ?? new NullLogger();
        }
        return self::$logger;
    }
}
