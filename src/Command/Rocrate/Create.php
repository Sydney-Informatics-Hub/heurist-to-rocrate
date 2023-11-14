<?php

namespace UtilityCli\Command\Rocrate;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use UtilityCli\Converter\Configuration;
use UtilityCli\Converter\Converter;
use UtilityCli\Helper\XML;
use UtilityCli\Heurist\HeuristData;
use UtilityCli\Log\ConsoleChannel;
use UtilityCli\Log\Log;

class Create extends Command
{

    protected function configure()
    {
        $this->setName('rocrate:create');
        $this->setDescription('Create a RO-Crate from a Heurist archive');
        $this->addArgument(
            'hueristDBName',
            InputArgument::REQUIRED,
            'The machine name of the Heurist database'
        );
        $this->addArgument(
            'hueristArchivePath',
            InputArgument::REQUIRED,
            'The directory path of the Heurist archive'
        );
        $this->addArgument(
            'outputPath',
            InputArgument::REQUIRED,
            'The path of the output RO-Crate metadata file'
        );
        
        // Adding options.
        $this->addOption(
            'heurist-name',
            null,
            InputOption::VALUE_OPTIONAL,
            'The human readable name of the Heurist database'
        );
        $this->addOption(
            'heurist-description',
            null,
            InputOption::VALUE_OPTIONAL,
            'The description of the Heurist database'
        );
        $this->addOption(
            'configuration',
            null,
            InputOption::VALUE_OPTIONAL,
            'The path of the configuration RO-Crate'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get arguments and options.
        $inputPath = $input->getArgument('hueristArchivePath');
        $outputPath = $input->getArgument('outputPath');
        $heuristDBName = $input->getArgument('hueristDBName');

        $heuristName = $input->getOption('heurist-name');
        $heuristDescription = $input->getOption('heurist-description');
        $configurationPath = $input->getOption('configuration');

        // Set up the channel for the logger.
        $channel = new ConsoleChannel($output);
        Log::init($channel);

        // Validate source files.
        $heuristStructurePath = $inputPath . '/Database_Structure.xml';
        $heuristDataPath = $inputPath . '/' . $heuristDBName . '.xml';
        if (!file_exists($heuristStructurePath)) {
            Log::error("Unable to read the Heurist structure from `{$heuristStructurePath}`. Please make sure
                the Heurist archive path provided is correct");
            return;
        }
        if (!file_exists($heuristDataPath)) {
            Log::error("Unable to read the Heurist data from `{$heuristDataPath}`. Please make sure
                the Heurist archive path or Heurist database name provided is correct");
            return;
        }
        if (!empty($configurationPath) && !file_exists($configurationPath)) {
            Log::error("Unable to read the configuration from `{$configurationPath}`. Please make sure
                the configuration path provided is correct");
            return;
        }

        // Load the Heurist data.
        Log::info('Loading Heurist data...');
        $structureXML = XML::parseFromFile($heuristStructurePath);
        Log::info("Loaded the Heurist structure from `{$heuristStructurePath}`");
        $dataXML = XML::parseFromFile($heuristDataPath);
        Log::info("Loaded the Heurist data from `{$heuristDataPath}`");
        $heurist = new HeuristData($structureXML, $dataXML);
        Log::info('Loaded the following entities from Heurist data:');
        Log::info('- ' . $heurist->getRecordTypesCount() . ' record types');
        Log::info('- ' . $heurist->getBaseFieldsCount() . ' base fields');
        Log::info('- ' . $heurist->getFieldsCount() . ' fields');
        Log::info('- ' . $heurist->getTermsCount() . ' terms');
        Log::info('- ' . $heurist->getRecordsCount() . ' records');

        // Set the attributes for the Heurist data instance.
        $heurist->setDbName($heuristDBName);
        if (!empty($heuristName)) {
            $heurist->setName($heuristName);
        }
        if (!empty($heuristDescription)) {
            $heurist->setDescription($heuristDescription);
        }

        // Load the configuration.
        $configuration = null;
        if (!empty($configurationPath)) {
            Log::info('Loading configurations...');
            $configJson = file_get_contents($configurationPath);
            $configData = json_decode($configJson, true);
            $configuration = new Configuration($configData);
            Log::info("Loaded configurations from `{$configurationPath}`");
        }

        // Convert to RO-Crate.
        Log::info('Converting Heurist data to RO-Crate...');
        $converter = new Converter($heurist, $configuration);
        $stats = $converter->getStats();
        foreach ($stats as $category => $count) {
            Log::info("- {$count} {$category} have been converted from Heurist to RO-Crate");
        }

        // Save the RO-Crate.
        Log::info('Generating the RO-Crate file...');
        $metadata = $converter->getRocrateMetadata();
        $metadataJSON = json_encode(
            $metadata->toArray(),
            JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
        );
        file_put_contents($outputPath, $metadataJSON);
        Log::info("Saved the RO-Crate to `{$outputPath}`");
    }
}
