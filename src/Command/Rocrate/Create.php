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
use UtilityCli\Helper\Path;
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
            'The path of the output RO-Crate file (.zip)'
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
            'The path of the configuration RO-Crate (.json or .zip)'
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
            if (Path::getFileExtensionFromPath($configurationPath) === 'zip') {
                $zip = new \ZipArchive();
                if ($zip->open($configurationPath)) {
                    // Check whether the zip file contains a file named "ro-crate-metadata.json".
                    $metadataPath = 'ro-crate-metadata.json';
                    if ($zip->locateName($metadataPath) !== false) {
                        $configJson = $zip->getFromName($metadataPath);
                    } else {
                        Log::error("Unable to find the configuration file `{$metadataPath}` in the configuration zip file `{$configurationPath}`");
                        return;
                    }
                } else {
                    Log::error("Unable to open the configuration zip file `{$configurationPath}`");
                    return;
                }
            } else if (Path::getFileExtensionFromPath($configurationPath) === 'json') {
                $configJson = file_get_contents($configurationPath);
            } else {
                Log::error("Invalid configuration file extension. Please provide a .json or .zip file.");
                return;
            }

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
        // Create the zip file.
        $zip = new \ZipArchive();
        if ($zip->open($outputPath, \ZipArchive::CREATE)) {
            $zip->addFromString('ro-crate-metadata.json', $metadataJSON);
            $uploadedFiles = $converter->getUploadedFiles();
            // For each uploaded file, add it to the zip file.
            foreach ($uploadedFiles as $uploadedFile) {
                $filePath = "{$inputPath}/file_uploads/{$uploadedFile}";
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, $uploadedFile);
                } else {
                    Log::warning("Unable to find the uploaded file `{$filePath}`");
                }
            }
            $zip->close();
        } else {
            Log::error("Unable to create the RO-Crate file `{$outputPath}`");
            return;
        }

        // file_put_contents($outputPath, $metadataJSON);
        Log::info("Saved the RO-Crate to `{$outputPath}`");
    }
}
