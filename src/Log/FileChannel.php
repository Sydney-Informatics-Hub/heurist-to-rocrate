<?php

namespace UtilityCli\Log;

use UtilityCli\Output\TxtWriter;

class FileChannel implements ChannelInterface
{
    /**
     * @var TxtWriter $txtWriter
     *   The log file writer.
     */
    protected TxtWriter $txtWriter;

    /**
     * Constructor.
     *
     * @param string $filePath
     *   The log file path.
     */
    public function __construct(string $filePath)
    {
        $this->txtWriter = new TxtWriter($filePath);
    }

    /**
     * @inheritdoc
     */
    public function info(string $message): void
    {
        $this->txtWriter->writeLine('[INFO]: ' . $message);
    }

    /**
     * @inheritdoc
     */
    public function error(string $message): void
    {
        $this->txtWriter->writeLine('[ERROR]: ' . $message);
    }

    /**
     * @inheritdoc
     */
    public function warning(string $message): void
    {
        $this->txtWriter->writeLine('[WARNING]: ' . $message);
    }
}