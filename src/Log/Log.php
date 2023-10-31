<?php

namespace UtilityCli\Log;

class Log
{
    /**
     * The logging channel.
     *
     * @var ChannelInterface
     */
    protected static ChannelInterface $channel;

    /**
     * Initialise the log instance.
     *
     * @param ChannelInterface $channel
     *   The logging channel.
     * @return void
     */
    public static function init(ChannelInterface $channel): void
    {
        self::$channel = $channel;
    }

    /**
     * Log a general message.
     *
     * @param string $message
     * @return void
     */
    public static function info(string $message): void
    {
        if (isset(self::$channel)) {
            self::$channel->info($message);
        }
    }

    /**
     * Log an error message.
     *
     * @param string $message
     * @return void
     */
    public static function error(string $message): void
    {
        if (isset(self::$channel)) {
            self::$channel->error($message);
        }
    }

    /**
     * Log a warning message.
     *
     * @param string $message
     * @return void
     */
    public static function warning(string $message): void
    {
        if (isset(self::$channel)) {
            self::$channel->warning($message);
        }
    }

}