<?php

namespace UtilityCli\Converter;

use UtilityCli\Converter\Functions\ValueFunction;
use UtilityCli\Heurist\BaseField;
use UtilityCli\Heurist\DateFieldValue;
use UtilityCli\Heurist\Field;
use UtilityCli\Heurist\FileFieldValue;
use UtilityCli\Heurist\GenericFieldValue;
use UtilityCli\Heurist\GeoFieldValue;
use UtilityCli\Heurist\HeuristData;
use UtilityCli\Heurist\RecordPointerFieldValue;
use UtilityCli\Heurist\RecordType;
use UtilityCli\Heurist\Term;
use UtilityCli\Heurist\TermFieldValue;
use UtilityCli\Log\Log;
use UtilityCli\Rocrate\ClassDefinition;
use UtilityCli\Rocrate\Entity;
use UtilityCli\Rocrate\Metadata;
use UtilityCli\Rocrate\PropertyDefinition;

/**
 * Class converting a Heurist database to RO-Crate.
 */
class Converter
{
    /**
     * The Heurist data from the export.
     *
     * @var HeuristData
     */
    protected HeuristData $heuristData;

    /**
     * The configuration data.
     *
     * @var Configuration
     */
    protected Configuration $configuration;

    /**
     * The RO-Crate metadata of the result RO-Crate.
     *
     * @var Metadata
     */
    protected Metadata $metadata;

    /**
     * The name resolver.
     *
     * @var NameResolver
     */
    protected NameResolver $nameResolver;

    /**
     * The namespace used for custom classes and properties.
     *
     * @var string
     */
    protected string $contextNamespace;

    /**
     * An associative array maps the Heurist entity identifier to the RO-Crate entity ID.
     *
     * The keys are the unique identifier created from `\UtilityCli\Converter\Utility::createEntityIdentifier`.
     * The values are the RO-Crate entity ID created from the Heurist entities.
     *
     * @var string[]
     */
    protected array $idMap;

    /**
     * The stats of the conversion process.
     *
     * The keys of the array are the names of stats category. Each element is the value of that category.
     *
     * @var int[]
     */
    protected array $stats;

    /**
     * The list of filenames of Heurist uploaded files.
     *
     * @var string[]
     */
    protected array $uploadedFiles = [];

    /**
     * Constructor.
     *
     * @param HeuristData $heuristData
     *   The Heurist data from the export.
     * @param Configuration|null $configuration
     *   The configuration data. If set to null, an empty configuration will be used.
     */
    public function __construct(HeuristData $heuristData, ?Configuration $configuration = null)
    {
        $this->heuristData = $heuristData;
        if (isset($configuration)) {
            $this->configuration = $configuration;
        } else {
            // Initialise an empty configuration if it's not set.
            $this->configuration = new Configuration();
        }
        $this->metadata = new Metadata();
        if (!empty($this->heuristData->getName())) {
            $this->metadata->setRootEntityName($this->heuristData->getName());
        }
        if (!empty($this->heuristData->getDescription())) {
            $this->metadata->setRootEntityDescription($this->heuristData->getDescription());
        }
        $this->nameResolver = new NameResolver();
        $this->contextNamespace = 'https://w3id.org/ro/terms/' . $this->heuristData->getDbName();
        $this->idMap = [];
        Log::info('Processing configurations...');
        $this->processConfiguration();
        Log::info('Processing Heurist terms...');
        $this->convertTerms();
        Log::info('Processing Heurist records...');
        $this->convertRecords();
    }

    /**
     * Get the converted RO-Crate metadata.
     *
     * @return Metadata
     */
    public function getRocrateMetadata(): Metadata
    {
        return $this->metadata;
    }

    /**
     * Process the data from the configuration.
     *
     * @return void
     * @throws \Exception
     */
    protected function processConfiguration(): void
    {
        // Add term class names.
        foreach ($this->configuration->getTermClassMap() as $termID => $className) {
            $term = $this->heuristData->findTerm($termID);
            if (isset($term)) {
                $identifier = Utility::createEntityIdentifierFromID(Utility::ENTITY_TYPE_TERM, $termID);
                $this->nameResolver->addEntryFromIdentifier($identifier, $className);
            } else {
                Log::warning("Can't find the mapped term ({$termID}) defined in the configuration");
            }
        }
        // Add term property names.
        foreach ($this->configuration->getTermPropertyMap() as $termAttrID => $propertyName) {
            $identifier = Utility::createEntityIdentifierFromID(Utility::ENTITY_TYPE_TERM, $termAttrID);
            $this->nameResolver->addEntryFromIdentifier($identifier, $propertyName);
        }
        // Add record type class names.
        foreach ($this->configuration->getRecordTypeClassMap() as $recordTypeID => $className) {
            $recordType = $this->heuristData->findRecordType($recordTypeID);
            if (isset($recordType)) {
                $identifier = Utility::createEntityIdentifierFromID(Utility::ENTITY_TYPE_RECORD_TYPE, $recordTypeID);
                $this->nameResolver->addEntryFromIdentifier($identifier, $className);
            } else {
                Log::warning("Can't find the mapped record type ({$recordTypeID}) defined in the configuration");
            }
        }
        // Add field property names.
        foreach ($this->configuration->getFieldPropertyMap() as $fieldID => $propertyName) {
            if (str_contains($fieldID, ':')) {
                $field = $this->heuristData->findField($fieldID);
                if (isset($field)) {
                    $identifier = Utility::createEntityIdentifierFromID(Utility::ENTITY_TYPE_FIELD, $fieldID);
                    $this->nameResolver->addEntryFromIdentifier($identifier, $propertyName);
                } else {
                    Log::warning("Can't find the mapped field ({$fieldID}) defined in the configuration");
                }
            } else {
                $baseField = $this->heuristData->findBaseField($fieldID);
                if (isset($baseField)) {
                    $identifier = Utility::createEntityIdentifierFromID(Utility::ENTITY_TYPE_BASE_FIELD, $fieldID);
                    $this->nameResolver->addEntryFromIdentifier($identifier, $propertyName);
                } else {
                    Log::warning("Can't find the mapped base field ({$fieldID}) defined in the configuration");
                }
            }
        }
        // Add custom classes.
        foreach ($this->configuration->getCustomClassMap() as $classDefinition) {
            $className = $classDefinition->get('rdfs:label');
            $classID = $this->contextNamespace . '#' . $className;
            // Mutate the id with the new namespace.
            $classDefinition->set('@id', $classID);
            // Add to the context.
            $this->metadata->getContext()->add($className, $classID);
            // Add the definition to the metadata.
            $this->metadata->addEntity($classDefinition);
        }
        // Add custom properties.
        foreach ($this->configuration->getCustomPropertyMap() as $propertyDefinition) {
            $propertyName = $propertyDefinition->get('rdfs:label');
            $propertyID = $this->contextNamespace . '#' . $propertyName;
            // Mutate the id with the new namespace.
            $propertyDefinition->set('@id', $propertyID);
            // Add to the context.
            $this->metadata->getContext()->add($propertyName, $propertyID);
            // Add the definition to the metadata.
            $this->metadata->addEntity($propertyDefinition);
        }
    }

    /**
     * Convert the Heurist terms to RO-Crate metadata.
     *
     * @return void
     */
    protected function convertTerms(): void
    {
        $vocabularies = $this->heuristData->getVocabularies();
        foreach ($vocabularies as $vocabulary) {
            if ($this->configuration->hasTermClass($vocabulary->getID())) {
                // Create terms as entities of the class defined in the configuration.
                // In this case, the top-level term (vocabulary) will not be created as it will be indicated from
                // the `@type` name of each terms within that vocabulary.
                $className = $this->configuration->getTermClass($vocabulary->getID());
                $termLabelPropertyName = $this->configuration->getTermProperty(
                    Term::createTermAttributeID($vocabulary->getID(), Term::ATTR_LABEL)
                );
                $termDescriptionPropertyName = $this->configuration->getTermProperty(
                    Term::createTermAttributeID($vocabulary->getID(), Term::ATTR_DESCRIPTION)
                );
                $termCodePropertyName = $this->configuration->getTermProperty(
                    Term::createTermAttributeID($vocabulary->getID(), Term::ATTR_CODE)
                );
                $children = $this->heuristData->getChildTerms($vocabulary);
                if (!empty($children)) {
                    $this->createMappedTerms(
                        $children, $className, $termLabelPropertyName, $termDescriptionPropertyName,
                        $termCodePropertyName);
                }
            } else {
                // Create standard `DefinedTermSet` and `DefinedTerm`.
                $vocabularyEntity = new Entity('DefinedTermSet', '#term_' . $vocabulary->getID());
                $vocabularyEntity->set('name', $vocabulary->getLabel());
                if (!empty($vocabulary->getDescription())) {
                    $vocabularyEntity->set('description', $vocabulary->getDescription());
                }
                // Add child terms.
                $children = $this->heuristData->getChildTerms($vocabulary);
                if (!empty($children)) {
                    $this->addChildTerms($vocabularyEntity, $children);
                }
                $this->metadata->getRootEntity()->addPart($vocabularyEntity);
                $this->metadata->addEntity($vocabularyEntity);
                $this->idMap[Utility::createEntityIdentifier($vocabulary)] = $vocabularyEntity->getID();
                $this->addStats('terms');
            }
        }
    }

    /**
     * Create the RO-Crate entities for terms mapped from the configuration.
     *
     * @param Term[] $terms
     *   The Heurist entities of terms.
     * @param string $className
     *   The class name to use for the terms in the RO-Crate entities.
     * @param string|null $termLabelPropertyName
     *   The property name to use for the term label in the RO-Crate entities.
     * @param string|null $termDescriptionPropertyName
     *   The property name to use for the term description in the RO-Crate entities.
     * @param string|null $termCodePropertyName
     *   The property name to use for the term code in the RO-Crate entities.
     * @return void
     * @throws \Exception
     */
    protected function createMappedTerms(
        array $terms,
        string $className,
        string $termLabelPropertyName = null,
        string $termDescriptionPropertyName = null,
        string $termCodePropertyName = null
    ): void {
        foreach ($terms as $term) {
            $termEntity = new Entity($className, '#term_' . $term->getID());
            if (isset($termLabelPropertyName)) {
                $termEntity->set($termLabelPropertyName, $term->getLabel());
            }
            if (isset($termDescriptionPropertyName) && !empty($term->getDescription())) {
                $termEntity->set($termDescriptionPropertyName, $term->getDescription());
            }
            if (isset($termCodePropertyName) && !empty($term->getCode())) {
                $termEntity->set($termCodePropertyName, $term->getCode());
            }
            $this->metadata->getRootEntity()->addPart($termEntity);
            $this->metadata->addEntity($termEntity);
            $this->idMap[Utility::createEntityIdentifier($term)] = $termEntity->getID();
            $this->addStats('terms');
            $children = $this->heuristData->getChildTerms($term);
            if (!empty($children)) {
                $this->createMappedTerms(
                    $children, $className, $termLabelPropertyName, $termDescriptionPropertyName, $termCodePropertyName);
            }
        }
    }

    /**
     * Add the child terms to a vocabulary RO-Crate entity.
     *
     * @param Entity $vocabularyEntity
     *   The RO-Crate entity of the vocabulary.
     * @param Term[] $terms
     *   The Heurist entities of child terms.
     * @return void
     * @throws \Exception
     */
    protected function addChildTerms(Entity &$vocabularyEntity, array $terms): void
    {
        foreach ($terms as $term) {
            $termEntity = new Entity('DefinedTerm', '#term_' . $term->getID());
            $termEntity->set('name', $term->getLabel());
            if (!empty($term->getDescription())) {
                $termEntity->set('description', $term->getDescription());
            }
            if (!empty($term->getCode())) {
                $termEntity->set('termCode', $term->getCode());
            }
            $vocabularyEntity->append('hasDefinedTerm', $termEntity);
            $this->metadata->addEntity($termEntity);
            $this->idMap[Utility::createEntityIdentifier($term)] = $termEntity->getID();
            $this->addStats('terms');
            $children = $this->heuristData->getChildTerms($term);
            if (!empty($children)) {
                $this->addChildTerms($vocabularyEntity, $children);
            }
        }
    }

    /**
     * Convert the Heurist records to RO-Crate metadata.
     *
     * @return void
     */
    protected function convertRecords(): void
    {
        $records = $this->heuristData->getRecords();
        // First process: create RO-Crate entity shells for all records.
        // This is necessary as some fields need to reference the record RO-Crate entity. Therefore, the RO-Crate entity
        // have to be created upfront.
        foreach ($records as $record) {
            $recordType = $record->getRecordType();
            $className = $this->getRecordTypeClassName($recordType);
            $entityID = '#rec_' . $record->getID();
            $recordEntity = new Entity($className, $entityID);
            $this->metadata->getRootEntity()->addPart($recordEntity);
            $this->metadata->addEntity($recordEntity);
            // Add the ID to the map.
            $this->idMap[Utility::createEntityIdentifier($record)] = $entityID;
            $this->addStats('records');
        }
        // Second process: handle all fields on each record.
        foreach ($records as $record) {
            // Retrieve the record RO-Crate entity.
            $recordEntityID = $this->getRocrateEntityID($record);
            $recordEntity = $this->metadata->getEntity($recordEntityID);
            $recordType = $record->getRecordType();
            $fieldData = $record->getAllFieldValues();
            foreach ($fieldData as $fieldID => $fieldValues) {
                $field = $this->heuristData->findField($fieldID);
                if (isset($field)) {
                    $baseField = $field->getBaseField();
                    if ($this->nameResolver->hasNameConflict($baseField)) {
                        // Use the field instead.
                        $propName = $this->getFieldPropertyName($field);
                    } else {
                        // Use the base field.
                        $propName = $this->getBaseFieldPropertyName($baseField);
                    }
                    /**
                     * @var GenericFieldValue $fieldValue
                     */
                    foreach ($fieldValues as $fieldValue) {
                        $this->setRecordFieldValue($recordEntity, $propName, $fieldValue);
                    }
                } else {
                    Log::warning("Can't find the field ({$fieldID}) on record type ({$recordType->getID()})");
                }
            }
        }
    }

    /**
     * Set the field value to the record RO-Crate entity.
     *
     * @param Entity $recordEntity
     *   The record RO-Crate entity.
     * @param string $propName
     *   The property name of the field.
     * @param GenericFieldValue $fieldValue
     *   The field value instance.
     * @return void
     * @throws \Exception
     */
    protected function setRecordFieldValue(Entity &$recordEntity, string $propName, GenericFieldValue $fieldValue): void
    {
        $field = $fieldValue->getField();
        $baseField = $field->getBaseField();
        // Check the value function from the configuration.
        $valueFunction = null;
        if ($this->configuration->hasFieldFunction($field->getID())) {
            $valueFunction = ValueFunction::create(
                $this->configuration->getFieldFunction($field->getID()), $fieldValue);
        } elseif ($this->configuration->hasFieldFunction($baseField->getID())) {
            $valueFunction = ValueFunction::create(
                $this->configuration->getFieldFunction($baseField->getID()), $fieldValue);
        }
        if (isset($valueFunction)) {
            // Set the property value through the value function.
            $recordEntity->append($propName, $valueFunction->getValue());
        } else {
            // Write the field value by base field type.
            switch ($baseField->getType()) {
                case BaseField::TYPE_DATE:
                    /**
                     * @var DateFieldValue $fieldValue
                     */
                    $this->setRecordDateFieldValue($recordEntity, $propName, $fieldValue);
                    break;
                case BaseField::TYPE_GEO:
                    /**
                     * @var GeoFieldValue $fieldValue
                     */
                    $this->setRecordGeoFieldValue($recordEntity, $propName, $fieldValue);
                    break;
                case BaseField::TYPE_FILE:
                    /**
                     * @var FileFieldValue $fieldValue
                     */
                    $this->setRecordFileFieldValue($recordEntity, $propName, $fieldValue);
                    break;
                case BaseField::TYPE_TERM:
                    /**
                     * @var TermFieldValue $fieldValue
                     */
                    $this->setRecordTermFieldValue($recordEntity, $propName, $fieldValue);
                    break;
                case BaseField::TYPE_RECORD_POINTER:
                    /**
                     * @var RecordPointerFieldValue $fieldValue
                     */
                    $this->setRecordRecordPointerFieldValue($recordEntity, $propName, $fieldValue);
                    break;
                default:
                    $recordEntity->append($propName, $fieldValue->getValue());
            }
        }
    }

    /**
     * Set the date type field value to the record.
     *
     * @param Entity $recordEntity
     * @param string $propName
     * @param DateFieldValue $fieldValue
     * @return void
     */
    protected function setRecordDateFieldValue(Entity &$recordEntity, string $propName, DateFieldValue $fieldValue): void
    {
        $recordEntity->append($propName, $fieldValue->getISODate());
    }

    /**
     * Set the geo type field value to the record.
     *
     * @param Entity $recordEntity
     * @param string $propName
     * @param GeoFieldValue $fieldValue
     * @return void
     */
    protected function setRecordGeoFieldValue(Entity &$recordEntity, string $propName, GeoFieldValue $fieldValue): void
    {
        $geoEntity = null;
        if ($fieldValue->getType() === GeoFieldValue::GEO_TYPE_POINT) {
            $coordinates = $fieldValue->getPointCoordinates();
            if (isset($coordinates)) {
                $geoEntity = new Entity('GeoCoordinates');
                $geoEntity->set('latitude', $coordinates[1]);
                $geoEntity->set('longitude', $coordinates[0]);
            } else {
                Log::warning("Unsupported Geo point format ({$fieldValue->getValue()})");
            }
        } elseif ($fieldValue->getType() === GeoFieldValue::GEO_TYPE_PATH) {
            $coordinates = $fieldValue->getCoordinatesString();
            if (isset($coordinates)) {
                $geoEntity = new Entity('GeoShape');
                $geoEntity->set('line', $coordinates);
            } else {
                Log::warning("Unsupported Geo path format ({$fieldValue->getValue()})");
            }
        } else {
            Log::warning("Unsupported Geo type ({$fieldValue->getType()})");
        }
        if (isset($geoEntity)) {
            $this->metadata->addEntity($geoEntity);
            $recordEntity->append($propName, $geoEntity);
        }
    }

    /**
     * Set the file type field value to the record.
     *
     * @param Entity $recordEntity
     * @param string $propName
     * @param FileFieldValue $fieldValue
     * @return void
     */
    protected function setRecordFileFieldValue(Entity &$recordEntity, string $propName, FileFieldValue $fieldValue): void
    {
        if ($fieldValue->isRemote()) {
            $fileEntity = new Entity('File', $fieldValue->getUrl());
        } else {
            $fileEntity = new Entity('File', $fieldValue->getLocalName());
            $this->uploadedFiles[] = $fieldValue->getLocalName();
        }
        if (!empty($fieldValue->getFileName())) {
            $fileEntity->set('name', $fieldValue->getFileName());
        }
        if (!empty($fieldValue->getFileSize())) {
            $fileEntity->set('contentSize', $fieldValue->getFileSize());
        }
        if (!empty($fieldValue->getMimeType())) {
            $fileEntity->set('encodingFormat', $fieldValue->getMimeType());
        }
        if (!empty($fieldValue->getDate())) {
            $fileEntity->set('uploadDate', $fieldValue->getDate());
        }
        $this->metadata->addEntity($fileEntity);
        $recordEntity->append($propName, $fileEntity);
    }

    /**
     * Set the term type field value to the record.
     *
     * @param Entity $recordEntity
     * @param string $propName
     * @param TermFieldValue $fieldValue
     * @return void
     * @throws \Exception
     */
    protected function setRecordTermFieldValue(Entity &$recordEntity, string $propName, TermFieldValue $fieldValue): void
    {
        $term = $fieldValue->getTerm();
        $termEntityID = $this->getRocrateEntityID($term);
        if (isset($termEntityID)) {
            $termEntity = $this->metadata->getEntity($termEntityID);
            $recordEntity->append($propName, $termEntity);
        } else {
            Log::warning("Can't find the referenced term ({$term->getID()})");
        }
    }

    /**
     * Set a record pointer type field value to the record.
     *
     * @param Entity $recordEntity
     * @param string $propName
     * @param RecordPointerFieldValue $fieldValue
     * @return void
     * @throws \Exception
     */
    protected function setRecordRecordPointerFieldValue(
        Entity &$recordEntity, string $propName, RecordPointerFieldValue $fieldValue
    ): void {
        $record = $fieldValue->getTarget();
        $recordEntityID = $this->getRocrateEntityID($record);
        if (isset($recordEntityID)) {
            // To avoid deep nested record reference consuming a lot of memories, use the static id instead.
            $recordEntity->append($propName, ['@id' => $recordEntityID]);
        } else {
            Log::warning("Can't find the referenced record ({$record->getID()})");
        }
    }

    /**
     * Resolve the RO-Crate class name from a Heurist record type.
     *
     * @param RecordType $recordType
     * @return string
     * @throws \Exception
     */
    protected function getRecordTypeClassName(RecordType $recordType): string
    {
        // Add the custom class if it's never been resolved.
        if (!$this->nameResolver->hasResolved($recordType)) {
            $name = $this->nameResolver->resolve($recordType, $this->heuristData->getDbName());
            $rocrateID = $this->contextNamespace . '#' . $name;
            // Add the context.
            $this->metadata->getContext()->add($name, $rocrateID);
            // Add the definition.
            $definition = new ClassDefinition($rocrateID);
            $definition->setName($name);
            if (!empty($recordType->getDescription())) {
                $definition->setDescription($recordType->getDescription());
            }
            $this->metadata->addEntity($definition);
            // Add the ID to the map.
            $this->idMap[Utility::createEntityIdentifier($recordType)] = $rocrateID;
        }
        return $this->nameResolver->resolve($recordType);
    }

    /**
     * Resolve the RO-Crate property name from a Heurist base field.
     *
     * @param BaseField $baseField
     * @return string
     * @throws \Exception
     */
    protected function getBaseFieldPropertyName(BaseField $baseField): string
    {
        if (!$this->nameResolver->hasResolved($baseField)) {
            $name = $this->nameResolver->resolve($baseField);
            $rocrateID = $this->contextNamespace . '#' . $name;
            // Add the context.
            $this->metadata->getContext()->add($name, $rocrateID);
            // Add the definition.
            $definition = new PropertyDefinition($rocrateID);
            $definition->setName($name);
            if (!empty($baseField->getDescription())) {
                $definition->setDescription($baseField->getDescription());
            }
            // Handle the domain.
            $domainRecordTypes = $this->heuristData->getBaseFieldUsedRecordTypes($baseField);
            if (!empty($domainRecordTypes)) {
                $this->setPropertyDomains($definition, $domainRecordTypes);
            }
            // Handle the ranges.
            $this->setPropertyRanges($definition, $baseField);
            $this->metadata->addEntity($definition);
            // Add the ID to the map.
            $this->idMap[Utility::createEntityIdentifier($baseField)] = $rocrateID;
        }
        return $this->nameResolver->resolve($baseField);
    }

    /**
     * Resolve the RO-Crate property name from a Heurist field.
     *
     * @param Field $field
     * @return string
     * @throws \Exception
     */
    protected function getFieldPropertyName(Field $field): string
    {
        if (!$this->nameResolver->hasResolved($field)) {
            $name = $this->nameResolver->resolve($field, $field->getRecordType()->getName());
            $rocrateID = $this->contextNamespace . '#' . $name;
            // Add the context.
            $this->metadata->getContext()->add($name, $rocrateID);
            // Add the definition.
            $definition = new PropertyDefinition($rocrateID);
            $definition->setName($name);
            if (!empty($field->getDescription())) {
                $definition->setDescription($field->getDescription());
            }
            // Handle the domain.
            $this->setPropertyDomains($definition, [$field->getRecordType()]);
            // Handle the ranges.
            $this->setPropertyRanges($definition, $field->getBaseField());
            $this->metadata->addEntity($definition);
            // Add the ID to the map.
            $this->idMap[Utility::createEntityIdentifier($field)] = $rocrateID;
        }
        return $this->nameResolver->resolve($field);
    }

    /**
     * Set the domains of a property definition.
     *
     * @param PropertyDefinition $definition
     * @param RecordType[] $domainRecordTypes
     * @return void
     * @throws \Exception
     */
    protected function setPropertyDomains(PropertyDefinition &$definition, array $domainRecordTypes): void
    {
        // Use this array to keep a track of added domain IDs when the configuration is in-place, as multiple record
        // types can map to the same class.
        $addedDomainIDs = [];
        foreach ($domainRecordTypes as $domainRecordType) {
            $recordTypeRocrateID = $this->getRocrateEntityID($domainRecordType);
            if (isset($recordTypeRocrateID)) {
                /**
                 * @var ClassDefinition $recordTypeRocrateEntity
                 */
                $recordTypeRocrateEntity = $this->metadata->getEntity($recordTypeRocrateID);
                $definition->addDomain($recordTypeRocrateEntity);
            } elseif (($className = $this->configuration->getRecordTypeClass($domainRecordType->getID())) !== null) {
                $domainID = $this->metadata->getContext()->get($className);
                if (!isset($domainID)) {
                    $domainID = 'http://schema.org/' . $className;
                }
                if (!in_array($domainID, $addedDomainIDs)) {
                    $definition->append('domainIncludes', ['@id' => $domainID]);
                    $addedDomainIDs[] = $domainID;
                }
            }
        }
    }

    /**
     * Set the ranges of a property definition.
     *
     * @param PropertyDefinition $definition
     * @param BaseField $baseField
     *   The base field of the property definition.
     * @return void
     * @throws \Exception
     */
    protected function setPropertyRanges(PropertyDefinition &$definition, BaseField $baseField): void
    {
        if ($baseField->getType() === BaseField::TYPE_TERM) {
            $definition->addRangeFromID('https://schema.org/DefinedTerm');
        } elseif ($baseField->getType() === BaseField::TYPE_FILE) {
            $definition->addRangeFromID('http://schema.org/MediaObject');
        } elseif ($baseField->getType() === BaseField::TYPE_GEO) {
            $definition->addRangeFromID('http://schema.org/GeoCoordinates');
            $definition->addRangeFromID('http://schema.org/GeoShape');
        } elseif ($baseField->getType() === BaseField::TYPE_RECORD_POINTER) {
            $rangeRecordTypes = $baseField->getRefTargetRecordTypes();
            if (!empty($rangeRecordTypes)) {
                foreach ($rangeRecordTypes as $rangeRecordType) {
                    $recordTypeRocrateID = $this->getRocrateEntityID($rangeRecordType);
                    if (isset($recordTypeRocrateID)) {
                        /**
                         * @var ClassDefinition $recordTypeRocrateEntity
                         */
                        $recordTypeRocrateEntity = $this->metadata->getEntity($recordTypeRocrateID);
                        $definition->addRange($recordTypeRocrateEntity);
                    } elseif (($className = $this->configuration->getRecordTypeClass($rangeRecordType->getID())) !== null) {
                        if (($rangeID = $this->metadata->getContext()->get($className)) !== null) {
                            $definition->append('domainIncludes', ['@id' => $rangeID]);
                        } else {
                            $definition->append('domainInclude', ['@id' => 'http://schema.org/' . $className]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Get the RO-Crate ID of a Heurist entity.
     *
     * @param mixed $heuristEntity
     *   The Heurist entity.
     * @return string|null
     * @throws \Exception
     */
    protected function getRocrateEntityID(mixed $heuristEntity): ?string
    {
        $identifier = Utility::createEntityIdentifier($heuristEntity);
        if (isset($this->idMap[$identifier])) {
            return $this->idMap[$identifier];
        }
        return null;
    }

    /**
     * Increase the stats of a category.
     *
     * @param string $category
     *   The stats category.
     * @return void
     */
    protected function addStats(string $category): void
    {
        if (!isset($this->stats[$category])) {
            $this->stats[$category] = 0;
        }
        $this->stats[$category]++;
    }

    /**
     * Get the stats.
     *
     * @return int[]
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Get the list of filenames of Heurist uploaded files.
     *
     * @return string[]
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }
}