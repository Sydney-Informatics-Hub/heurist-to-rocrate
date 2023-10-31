<?php

namespace UtilityCli\Log;

/**
 * Log channel interface.
 */
interface ChannelInterface
{
    /**
     * Write a general message to the channel.
     *
     * @param string $message
     * @return void
     */
    public function info(string $message): void;

    /**
     * Write an error message to the channel.
     *
     * @param string $message
     * @return void
     */
    public function error(string $message): void;

    /**
     * Write a warning message to the channel.
     *
     * @param string $message
     * @return void
     */
    public function warning(string $message): void;
}