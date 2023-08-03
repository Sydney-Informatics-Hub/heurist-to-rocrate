<?php

namespace UtilityCli\Command\Rocrate;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class Create extends Command
{

    protected function configure()
    {
        $this->setName('rocrate:create');
        $this->setDescription('Heurist XML to Ro-crate Json');
        $this->addArgument('inputPath', InputArgument::REQUIRED, 'Input path');
        $this->addArgument('outputPath', InputArgument::REQUIRED, 'Output path');
        $this->addArgument('dbName', InputArgument::OPTIONAL, 'Database name');
        $this->addArgument('dbDescription', InputArgument::OPTIONAL, 'Database description');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $inputPath = $input->getArgument('inputPath');
        $outputPath = $input->getArgument('outputPath');
        $databaseName = $input->getArgument('dbName') ?: "";;
        $databaseDescription = $input->getArgument('dbDescription') ?: "";;

        $reservedTypes = ['CreativeWork', 'Dataset', 'File', 'DefinedTerm', 'DefinedTermSet', 'GeoCoordinates', 'GeoShape'];
        $idPrefix = "https://w3id.org/ro/terms/" . strtolower($databaseName) . "#";

        // Load the XML content into a SimpleXMLElement object
        $databaseStructure = $this->loadXmlFile($inputPath . '/Database_Structure.xml');
        $recordsStructure = $this->loadXmlFile($inputPath . '/Record_Structure.xml');

        $customTerms = [];
        //Load record types
        $recordTypeData = $this->loadRecordTypes($databaseStructure->RecTypes->rty, $customTerms, $idPrefix, $reservedTypes, strtoupper($databaseName));
        //Load fields and base fields
        $fieldData = $this->loadFields($databaseStructure->RecStructure->rst, $databaseStructure->DetailTypes->dty, $recordTypeData['recordTypeMap'], $customTerms, $idPrefix, $recordTypeData['recordTypeSet']);
        //Load vocabularies and terms
        $vocabAndTermData = $this->loadVocabAndTerms($databaseStructure->Terms->trm);
        //Load records
        $recordData = $this->loadRecords($recordsStructure->records->record, $reservedTypes, strtoupper($databaseName));
        //Build context data
        $contextData = $this->buildContext($customTerms);
        //Build root data entity
        $rootDataEntity = $this->buildHeader($vocabAndTermData['vocabIds'], $recordData['recordIds'], $databaseName, $databaseDescription);

        $standardOutput = [];
        $graphData = [];

        //Build json output
        $graphData = array_merge($graphData, $rootDataEntity);
        $graphData = array_merge($graphData, $recordTypeData['res']);
        $graphData = array_merge($graphData, $fieldData);
        $graphData = array_merge($graphData, $vocabAndTermData['res']);
        $graphData = array_merge($graphData, $recordData['res']);
        $standardOutput['@context'] = $contextData;
        $standardOutput['@graph'] = $graphData;

        $jsonData = json_encode($standardOutput, JSON_PRETTY_PRINT);
        $filePath = "../../../../" . $outputPath . "/standardResult.json";
        file_put_contents(__DIR__ . $filePath, $jsonData);

        //Stats
        $output->writeln("Done!");
        $output->writeln(count($recordTypeData['recordTypeMap']) . " record types are generated");
        $output->writeln(count($fieldData) . " fields are generated");
        $output->writeln((count($vocabAndTermData['vocabIds']) . " vocabularies are generated along with " .  count($vocabAndTermData['termMap']) . " terms"));
        $output->writeln(count($recordData['recordIds']) . " records are generated");
    }


    /**
     * Loads record types 
     *
     * Each record type is represented as a custom term of class in RO-Crate.
     * The names of certain record types are reserved for RO-Crate, and any conflicting Heurist record types have their names prepended
     * with the Heurist database name to avoid conflict.
     *
     * Static Properties:
     *   - @id: URL for the term, e.g., "https://w3id.org/ro/terms/omaa#Text".
     *   - @type: "rdfs:Class".
     *
     * Property Mapping:
     *   - rty_Name: Mapped to rdfs:label in PascalCase, with potential prepend of the Heurist database name.
     *   - rty_Description: Mapped to rdfs:comment.
     * 
     * @param array $recordTypes    An array of record types from Heurist to be processed.
     * @param array &$customTerms   A reference to array customTerms, store all custom term names peprended with Idprefix. used for context building.
     * @param string $idPrefix      A prefix to prepend to the record type IDs.
     * @param array $reservedTypes  An array of reserved type names to check for conflict against reserved class name.
     * @param string $databaseName  The name of the database being used.
     * 
     * @return array Returns an associative array containing the processed record types ('res'), 
     *               a map of record id to record type object ('recordTypeMap'), and 
     *               a set of all record type label ('recordTypeSet').
     */
    private function loadRecordTypes($recordTypes, &$customTerms, $idPrefix, $reservedTypes, $databaseName)
    {
        $typeValue = "rdfs:Class";

        $recordTypeMap = [];
        $recordTypeSet = [];
        $res = [];

        foreach ($recordTypes as $recordType) {

            $recordTypeMap[(string)($recordType->rty_ID)] = $recordType;

            $label = $this->convertToPascalCase((string) $recordType->rty_Name);
            $recordTypeSet[(string) $recordType->rty_Name] = true;

            // Check for reserved type name
            if (in_array($label, $reservedTypes)) {
                $label =  $databaseName . $label;
            }

            $customTerms[$label] = $idPrefix . $label;

            $res[] =  [
                '@id' => $idPrefix . $label,
                '@type' => $typeValue,
                'rdfs:label' => $label,
                'rdfs:comment' => (string) $recordType->rty_Description,
            ];
        }

        return [
            'res' => $res,
            'recordTypeMap' => $recordTypeMap,
            'recordTypeSet' => $recordTypeSet,
        ];;
    }

    /**
     * Process fields. Fields are processed through base fields.
     *
     * The function takes base fields and other related data to create RO-Crate custom term objects.
     * Base fields are used to avoid duplicate terms, as some fields are shared across different record types.
     * The generated custom terms follow specific static properties and class types based on the Heurist field types,
     * and comply with special mapping rules such as ID conversion, label naming conventions, and inclusion rules.
 
     * @param array $fields         An array of field objects from Heurist.
     * @param array $baseFields     An array of base field objects from Heurist to be mapped.
     * @param array $recordTypeMap  A map of record type IDs to record type objects.
     * @param array &$customTerms   A reference to array customTerms, store all custom term names peprended with Idprefix. used for context building.
     * @param string $idPrefix      A prefix to prepend to the field IDs.
     * @param array $recordTypeSet  A set containing all record type label.used to check whether the label is taken by another record type. prepend if taken.

     * 
     * @return array Returns an array containing the processed term objects,
     */
    private function loadFields($fields, $baseFields, $recordTypeMap, &$customTerms, $idPrefix, $recordTypeSet)
    {
        $typeValue = "rdf:Property";
        $res = [];

        //Construct baseField map
        $baseFieldMap = [];
        foreach ($baseFields as $baseField) {
            $baseFieldMap[(string) $baseField->dty_ID] = $baseField;
        }

        // Process fields
        foreach ($fields as $field) {
            $baseFieldId = (string) $field->rst_DetailTypeID;

            if (isset($baseFieldMap[$baseFieldId])) {

                $baseField = $baseFieldMap[$baseFieldId];

                //Check whether the label is taken by another record type. prepend if it is taken by another recordType
                $label =  isset($recordTypeSet[(string) $baseField->dty_Name]) ? $this->convertToCamelCase($baseField->dty_Name) . $baseField->dty_Name : $this->convertToCamelCase($baseField->dty_Name);

                $customTerms[$label] = $idPrefix . $label;
                $roCrateTermObject = [
                    '@id' => $idPrefix . $label,
                    '@type' => $typeValue,
                    'rdfs:label' => $label,
                    'rdfs:comment' => (string) $baseField->dty_HelpText,
                ];

                //Handle domainIncludes
                if (isset($field->rst_RecTypeID)) {
                    $recTypeId = (string) $field->rst_RecTypeID;
                    $referedRecordTypeId =  $idPrefix . $this->convertToPascalCase($recordTypeMap[$recTypeId]->rty_Name);
                    $roCrateTermObject['domainIncludes'] = $referedRecordTypeId;
                }

                //Handle rangeIncludes
                if (isset($baseField->dty_PtrTargetRectypeIDs)) {
                    $rangeInclude = null;
                    $ptyTargetRecTypeIds = (string) $baseField->dty_PtrTargetRectypeIDs;
                    $recordType = (string) $baseField->dty_Type;

                    if (isset($ptyTargetRecTypeIds)) {
                        //Record pointer type field
                        //TODO APPLY SAME FOR DOMAIN INCLUDES
                        $rangeInclude = [];
                        foreach (explode(',', $ptyTargetRecTypeIds) as $ptyTargetRecTypeId) {
                            if ($ptyTargetRecTypeId) {
                                $rangeInclude[] = ['@id' => $idPrefix . $this->convertToPascalCase($recordTypeMap[$ptyTargetRecTypeId]->rty_Name)];
                            }
                        }

                        if (count($rangeInclude) === 1) {
                            $rangeInclude = $rangeInclude[0];
                        }
                    } else if ($recordType === 'file') {
                        //	File type field
                        $rangeInclude = ["@id" => "http://schema.org/MediaObject"];
                    } else if ($recordType === 'geo') {
                        //Spatial type field
                        $rangeInclude = [["@id" => "http://schema.org/GeoCoordinates"], ["@id" => "http://schema.org/GeoShape"]];
                    } else if ($recordType === 'enum') {
                        // Term Type Field
                        $rangeInclude = ["@id" => "https://schema.org/DefinedTerm"];
                    }

                    if (isset($rangeInclude)) {
                        $roCrateTermObject['rangeIncludes'] = $rangeInclude;
                    }
                }

                $res[] = $roCrateTermObject;
            } else {
                echo "base field reference not found: " . $baseFieldId;
            }
        }

        return $res;
    }

    /**
     * Loads and processes vocabularies and terms from Heurist, creating entities for RO-Crate.
     *
     * Vocabularies are created as entities of class DefinedTermSet, and their terms are listed in the property 'hasDefinedTerm'.
     * Terms are created as entities of class DefinedTerm. T
     * 
     *
     * Static Properties:
     *   - @type: "DefinedTermSet" for vocabularies, "DefinedTerm" for terms.
     *   - hasDefinedTerm: Only applies to vocabularies, containing references to terms.
     *
     * Property Mapping:
     *   - trm_ID: Mapped to @id, prefixed by "#trm_".
     *   - trm_Label: Mapped to 'name'.
     *   - trm_Description: Mapped to 'description'.
     *   - trm_Code: Mapped to 'termCode', only applies to terms.
     *
     * @param array $items Array of vocabularies and terms to process.
     * @return array Returns an associative array containing the processed vocabularies and terms ('res'), 
     *               a map of term id to term object ('termMap'), and 
     *               a set of all vocabulary IDs ('vocabIds').
     * @throws InvalidArgumentException If an item is missing trm_ID.
     *
     */
    private function loadVocabAndTerms($items)
    {

        $idPrefix = "#trm_";
        $vocabularyType = "DefinedTermSet";
        $termType = "DefinedTerm";

        $vocabularyToTermMap = []; // Map to keep track of term id for each vocabulary
        $vocabularies = [];
        $terms = [];
        $vocabIds = [];

        // Categorize items into vocabularies and terms
        foreach ($items as $item) {
            if (!isset($item->trm_ID)) {
                throw new InvalidArgumentException("Item is missing trm_ID: " . json_encode($item));
            }

            if (isset($item->trm_ParentTermID) && $item->trm_ParentTermID != 0) {
                //Term
                $terms[(string) $item->trm_ID] = $item;

                // Update map
                $vocabularyToTermMap[(string)$item->trm_ParentTermID][] = $item->trm_ID;
            } else {
                // Vocabulary
                $vocabularies[(string)$item->trm_ID] = $item;
            }
        }

        $res = [];

        // Process vocabularies
        foreach ($vocabularies as $vocabularyId => $vocabulary) {
            // Prepare the term set for this vocabulary
            $definedTermSet = [];
            foreach ($vocabularyToTermMap[$vocabularyId] ?? [] as $termId) {
                $definedTermSet[] = ['@id' => $idPrefix . $termId];
            }

            $res[] = [
                '@id' => $idPrefix . $vocabularyId,
                '@type' => $vocabularyType,
                'name' => (string) $vocabulary->trm_Label ?? '',
                'description' => (string) $vocabulary->trm_Description ?? '',
                'hasDefinedTerm' => $definedTermSet,
            ];

            $vocabIds[] = $idPrefix . $vocabularyId;
        }

        // Process terms
        foreach ($terms as $termId => $term) {
            $res[] = [
                '@id' => $idPrefix . $termId,
                '@type' => $termType,
                'name' => (string) $term->trm_Label ?? '',
                'description' => (string) $term->trm_Description ?? '',
                'termCode' => (string) $term->trm_Code ?? '',
            ];
        }

        return [
            'res' => $res,
            'termMap' => $terms,
            'vocabIds' => $vocabIds,
        ];
    }

    /**
     * Loads and processes Heurist records
     *
     * @param array $records Array of Heurist records
     * @param array $reservedTypes List of reserved class names
     * @param string $databaseName Name of database
     *
     * @return array Returns an associative array containing the processed RO-Crate entities ('res') and 
     *               the list of record IDs ('recordIds').
     */
    private function loadRecords($records, $reservedTypes, $databaseName)
    {
        $idPrefix = "#rec_";
        $res = [];
        $recordIds = [];

        foreach ($records as $record) {

            $recordType = $this->convertToPascalCase(trim($record->type));
            if (in_array($recordType, $reservedTypes)) {
                $recordType = $databaseName . $recordType;
            }
            //Check for reserved type

            $roCrateObject = [
                '@id' => $idPrefix . trim($record->id),
                '@type' =>  $recordType,
            ];

            $recordIds[] = $idPrefix . trim($record->id);

            $grospatialObject = [];
            $fileOrMediaUrlObject = [];

            // loop through the details
            foreach ($record->detail as $detail) {
                $detailArray = (array)$detail;
                $attributes = $detailArray['@attributes'];
                $value = isset($detailArray['0']) ? $detailArray['0'] : null;
                $basename = trim($attributes['basename']);

                //Store as array first for duplicate basename
                if (!isset($roCrateObject[$this->convertToCamelCase($basename)])) {
                    $roCrateObject[$this->convertToCamelCase($basename)] = [];
                }

                if (isset($attributes['isRecordPointer']) && $attributes['isRecordPointer'] === 'true') {
                    //Record Pointer Value is the refered record id
                    $roCrateObject[$this->convertToCamelCase($basename)][] = ['@id' => '#rec_' . trim($value)];
                } else if (isset($attributes['termID'])) {
                    // Term
                    $roCrateObject[$this->convertToCamelCase($basename)][] = ['@id' => '#trm_' . trim($attributes['termID'])];
                } else if (isset($detailArray['raw'])) {
                    //Date 
                    $rawDate = json_decode($detailArray['raw']) != null ? json_decode($detailArray['raw']) : $detailArray['raw'];
                    $roCrateObject[$this->convertToCamelCase($basename)][] = $this->getDateDisplay($rawDate);
                } else if (isset($detailArray['geo'])) {
                    //Geospatial

                    //The @id of the geo entity should be a generated UUID (v4) prefixed by #.
                    $uuid4 = "#" .  Uuid::uuid4()->toString();
                    $roCrateObject[$this->convertToCamelCase($basename)][] = ['@id' => $uuid4];

                    if (preg_match('/point\(([\d\-\.]+)\s([\d\-\.]+)\)/i', $detailArray['geo']->wkt, $matches)) {
                        $longitude =  floatval($matches[1]);
                        $latitude =  floatval($matches[2]);

                        $grospatialObject[] = [
                            '@id' => $uuid4,
                            '@type' => 'GeoCoordinates',
                            'latitude' => $latitude,
                            'longitude' => $longitude
                        ];
                    }
                } else if (isset($detailArray['file'])) {
                    //File or media URL

                    $roCrateObject[$this->convertToCamelCase($basename)][] = ['@id' => trim($detailArray['file']->origName)];

                    $fileOrMediaUrlObject[] = [
                        '@id' => trim($detailArray['file']->origName),
                        '@type' => 'File',
                        'name' => trim($detailArray['file']->origName),
                        'contentSize' => trim($detailArray['file']->fileSize) . '000',
                        'encodingFormat' => trim($detailArray['file']->mimeType),
                        'uploadDate' => trim($detailArray['file']->date),
                    ];
                } else {
                    $roCrateObject[$this->convertToCamelCase($basename)][] = trim($value);
                }
            }

            // Represent as object if no no duplicate name is found
            foreach ($roCrateObject as $key => $value) {
                if (gettype($value) == 'array' && count($value) == 1) {
                    $roCrateObject[$key] = $value[0];
                }
            }
            $res[] = $roCrateObject;

            // Add referenced Geospatial
            if (count($grospatialObject) > 0) {
                foreach ($grospatialObject as $value) {
                    $res[] = $value;
                }
            }

            // File or media URL
            if (count($fileOrMediaUrlObject) > 0) {
                foreach ($fileOrMediaUrlObject as $value) {
                    $res[] = $value;
                }
            }
        }

        return [
            'res' => $res,
            'recordIds' => $recordIds
        ];
    }

    /**
     * Builds the context array for an RO-Crate
     * Incluing all the custom terms for recordtypes and fields
     *
     * @param array $customTerms An associative array of key-value pairs representing the custom terms.
     * @return array Returns context array that combines the RO-Crate standard context with the provided custom terms.
     */
    private function buildContext($customTerms)
    {
        $contextArray = [
            "https://w3id.org/ro/crate/1.1/context",
            []
        ];

        foreach ($customTerms as $key => $value) {
            $contextArray[1][$key] = $value;
        }

        return $contextArray;
    }

    /**
     * Constructs the header for an RO-Crate, 
     * including metadata and the references to vocabularies and records.
     *
     *
     * @param array $vocabIds An array of vocabulary IDs to be included in the RO-Crate.
     * @param array $recordIds An array of record IDs to be included in the RO-Crate.
     * @param string $databaseName The name of the database.
     * @param string $databaseDescription A description of the database.
     * @return array Returns the header array containing the metadata and main dataset info
     */
    private function buildHeader($vocabIds, $recordIds, $databaseName, $databaseDescription)
    {
        // Transform vocabIds and recordIds to required format
        $transformedVocabIds = array_map(function ($id) {
            return ['@id' => $id];
        }, $vocabIds);
        $transformedRecordIds = array_map(function ($id) {
            return ['@id' => $id];
        }, $recordIds);

        $allIds = array_merge($transformedVocabIds, $transformedRecordIds);

        return [
            [
                "@type" => "CreativeWork",
                "@id" => "ro-crate-metadata.json",
                "conformsTo" => ["@id" => "https://w3id.org/ro/crate/1.1"],
                "about" => ["@id" => "./"]
            ],
            [
                "@id" => "./",
                "@type" => "Dataset",
                "name" => $databaseName,
                "description" => $databaseDescription,
                "hasPart" => $allIds,
            ]
        ];
    }

    /**
     * Converts a given string to PascalCase format.
     * Removes any content within parentheses.
     * Removes all non-alphanumeric characters, except for spaces.
     * 
     * @param string $str The input string to be converted to PascalCase.
     * @return string Returns the converted string in PascalCase format.
     */
    private function convertToPascalCase($str)
    {
        // Remove content within parentheses and non-alphanumeric characters
        $str = preg_replace("/\([^)]+\)/", "", $str);  // Remove content within parentheses
        $words = preg_split('/\s+/', preg_replace("/[^A-Za-z0-9 ]/", '', $str));

        $processedWords = array();

        foreach ($words as $word) {
            $processedWords[] = ucfirst($word);
        }

        return implode('', $processedWords);
    }

    /**
     * Converts a given string to camelCase format.
     * Removes any content within parentheses.
     * Removes all non-alphanumeric characters, except for spaces.
     * 
     * @param string $str The input string to be converted to PascalCase.
     * @return string Returns the converted string in camelCase format.
     */
    private function convertToCamelCase($str)
    {
        // Remove content within parentheses and non-alphanumeric characters
        $str = preg_replace("/\([^)]+\)/", "", $str);  // Remove content within parentheses
        $words = preg_split('/\s+/', preg_replace("/[^A-Za-z0-9 ]/", '', $str));

        $processedWords = array();

        foreach ($words as $index => $word) {
            // Convert first word to lower case, rest to upper case first letter
            if ($index == 0) {
                $processedWords[] = strtolower($word);
            } else {
                $processedWords[] = ucfirst($word);
            }
        }

        return implode('', $processedWords);
    }

    /**
     * Formats a Heurist date object into ISO 8601 date format.
     * Accepts various date formats, including timestamps, ranges, string dates, single years, and integer years,
     *
     * TODO: Add handle for possible detailed time input.
     * 
     * @param mixed $date The date value to be formatted. It can be an object, string, or integer.
     * @return string ISO 8601 date format
     */
    private function getDateDisplay($date)
    {

        $display = '';

        // Handling timestamps
        if (is_object($date) && isset($date->timestamp) && isset($date->timestamp->in)) {
            $display .= $date->timestamp->in;
        }
        // Handling range of years
        elseif (is_object($date) && isset($date->start) && isset($date->end)) {
            $display .= $date->start->earliest . '/' . $date->end->latest;
        }
        // Handling string dates and single years
        elseif (is_string($date)) {
            // If the pattern matches a year followed by a dash (with optional spaces), extract the year only.
            if (preg_match('/(\d{4})\s?-/', $date, $matches)) {
                $display .= $matches[1];
            } else {
                $display .= $date;
            }
        }
        // Handling integer years
        elseif (is_int($date)) {
            $display .= (string) $date;
        }

        return $display;
    }

    //Load xml file.  remove unnecessary whitespaces, tabs, and new lines
    private function loadXmlFile($filePath) {
       
        $xmlContent = file_get_contents($filePath);
        $xmlContent = preg_replace("/\t|\n/", "", $xmlContent);
        return simplexml_load_string($xmlContent);
    }
}
