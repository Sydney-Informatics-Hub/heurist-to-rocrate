<?php

namespace UtilityCli\Log;

use Symfony\Component\Console\Output\OutputInterface;

class ConsoleChannel implements ChannelInterface
{
    /**
     * @var OutputInterface $output
     *   The console output.
     */
    protected OutputInterface $output;

    /**
     * Constructor.
     *
     * @param OutputInterface $output
     *   The console output.
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @inheritdoc
     */
    public function info(string $message): void
    {
        $this->output->writeln($message);
    }

    /**
     * @inheritdoc
     */
    public function error(string $message): void
    {
        $this->output->writeln('[ERROR]: ' . $message);
    }

    /**
     * @inheritdoc
     */
    public function warning(string $message): void
    {
        $this->output->writeln('[WARNING]: ' . $message);
    }
}
