<?php

namespace UtilityCli\Converter;

use UtilityCli\Heurist\Field;
use UtilityCli\Heurist\Term;
use UtilityCli\Log\Log;
use UtilityCli\Rocrate\Entity;

/**
 * Class of Heurist to RO-Crate configuration.
 *
 * The configuration itself is an RO-Crate which provides the mapping information between Heurist and RO-Crate.
 */
class Configuration extends \UtilityCli\Rocrate\Metadata
{
    /**
     * The term (vocabulary) class map. Keyed by the term ID. Each element is the mapped class name.
     *
     * @var string[]
     */
    protected array $termClassMap = [];

    /**
     * The term property map. Keyed by term attribute ID. Each element is the property name.
     *
     * @var array
     */
    protected array $termPropertyMap = [];

    /**
     * The record type class map. Keyed by the record type ID. Each element is the mapped class name.
     *
     * @var string[]
     */
    protected array $recordTypeClassMap = [];

    /**
     * The field property map. Keyed by field/base field ID. Each element is the property name.
     *
     * @var string[]
     */
    protected array $fieldPropertyMap = [];

    /**
     * The field functions map. Keyed by field/base field ID. Each element is the RO-Crate entity of the function.
     *
     * @var Entity[]
     */
    protected array $fieldFunctionMap = [];

    /**
     * The custom class map. Keyed by the class name. Each element is the class definition entity.
     *
     * @var Entity[]
     */
    protected array $customClassMap = [];

    /**
     * The custom property map. Keyed by the property name. Each element is the property definition entity.
     *
     * @var Entity[]
     */
    protected array $customPropertyMap = [];

    /**
     * The property names to be excluded when map the properties.
     *
     * @var string[]
     */
    protected array $excludedProperties;

    /**
     * Constructor.
     *
     * @param array $data
     *   The raw RO-Crate data of the configuration.
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->excludedProperties = [
            '@id',
            '@type',
            '_sourceType',
            'name',
        ];
        if (!empty($data)) {
            $this->parseCustomDefinitions();
            $this->parseMappingData();
        }
    }

    /**
     * Parse the custom class and property definitions.
     *
     * @return void
     */
    protected function parseCustomDefinitions(): void
    {
        foreach ($this->entities as $entity) {
            if ($entity->getType() === 'rdf:Property' && !str_starts_with($entity->get('rdfs:label'), '_')) {
                // Handle custom property entities.
                $this->customPropertyMap[$entity->get('rdfs:label')] = $entity;
            } elseif ($entity->getType() === 'rdfs:Class' && !str_starts_with($entity->get('rdfs:label'), '_')) {
                // Handle custom class entities.
                $this->customClassMap[$entity->get('rdfs:label')] = $entity;
            }
        }
    }

    /**
     * Parse the mapping information from the configuration crate.
     *
     * @return void
     */
    protected function parseMappingData(): void
    {
        foreach ($this->entities as $entity) {
            $sourceType = $entity->get('_sourceType');
            if (!empty($sourceType)) {
                // Handle mapping entities.
                $sourceID = $sourceType->get('_sourceIdentifier');
                if (empty($sourceID)) {
                    Log::warning("The `_sourceIdentifier` is missing in the source type ({$sourceType->getID()})");
                    continue;
                }
                $mappedClassName = $entity->getType();
                if (empty($mappedClassName)) {
                    Log::warning("Missing target class type of entity ({$entity->getID()})");
                    continue;
                }
                if ($sourceType->getType() === '_RecordType') {
                    $this->recordTypeClassMap[$sourceID] = $mappedClassName;
                    // Map properties and fields.
                    $this->mapRecordProperties($entity, $sourceID);
                } elseif ($sourceType->getType() === '_Vocabulary') {
                    $this->termClassMap[$sourceID] = $mappedClassName;
                    // Map properties and term attributes.
                    $this->mapTermAttributes($entity, $sourceID);
                } else {
                    Log::warning("Unsupported source type `{$sourceType->getType()}` ({$sourceType->getID()})");
                    continue;
                }

            } elseif ($entity->getType() === 'rdf:Property' && !str_starts_with($entity->get('rdfs:label'), '_')) {
                // Handle custom property entities.
                $this->customPropertyMap[$entity->get('rdfs:label')] = $entity;
            } elseif ($entity->getType() === 'rdfs:Class' && !str_starts_with($entity->get('rdfs:label'), '_')) {
                // Handle custom class entities.
                $this->customClassMap[$entity->get('rdfs:label')] = $entity;
            }
        }
    }

    /**
     * Map the properties to Heurist fields/base fields.
     *
     * @param Entity $entity
     *   A mapping entity from RO-Crate which maps a Heurist record type.
     * @param string $recordTypeID
     *   The Heurist record type ID.
     * @return void
     */
    protected function mapRecordProperties(Entity $entity, string $recordTypeID): void
    {
        $properties = $entity->getProperties();
        foreach ($properties as $name => $target) {
            if (!in_array($name, $this->excludedProperties)) {
                if ($name === '_name') {
                    $name = 'name';
                }
                if ($target instanceof Entity) {
                    $sourceTargetID = $target->get('_sourceIdentifier');
                    if (empty($sourceTargetID)) {
                        Log::warning("The `_sourceIdentifier` is missing in the target ({$target->getID()})");
                        continue;
                    }
                    if ($target->getType() === '_Field') {
                        $sourceTargetID = Field::createFieldID($recordTypeID, $sourceTargetID);
                    } elseif ($target->getType() === '_BaseField') {
                        // Do nothing here.
                    } else {
                        Log::warning("Invalid mapping type ({$target->getType()}) of property `{$name}` in entity ({$entity->getID()})");
                        continue;
                    }
                    $this->fieldPropertyMap[$sourceTargetID] = $name;
                    $function = $target->get('_valueFunction');
                    if (isset($function)) {
                        if ($function instanceof Entity) {
                            $this->fieldFunctionMap[$sourceTargetID] = $function;
                        } else {
                            Log::warning("Invalid value function in entity ({$target->getID()})");
                        }
                    }
                } else {
                    Log::warning("Invalid mapping of property `{$name}` in entity ({$entity->getID()})");
                }
            }
        }
    }

    /**
     * Map the properties to term attributes.
     *
     * @param Entity $entity
     *   A mapping entity from RO-Crate which maps a Heurist term (vocabulary).
     * @param string $termID
     *   The Heurist term ID.
     * @return void
     */
    protected function mapTermAttributes(Entity $entity, string $termID): void
    {
        $properties = $entity->getProperties();
        foreach ($properties as $name => $target) {
            if (!in_array($name, $this->excludedProperties)) {
                if ($name === '_name') {
                    $name = 'name';
                }
                if ($target instanceof Entity) {
                    if ($target->getType() === '_VocabularyTermLabel') {
                        $attrID = Term::createTermAttributeID($termID, Term::ATTR_LABEL);
                    } elseif ($target->getType() === '_VocabularyTermDescription') {
                        $attrID = Term::createTermAttributeID($termID, Term::ATTR_DESCRIPTION);
                    } elseif ($target->getType() === '_VocabularyTermCode') {
                        $attrID = Term::createTermAttributeID($termID, Term::ATTR_CODE);
                    } else {
                        Log::warning("Invalid mapping type ({$target->getType()}) of property `{$name}` in entity ({$entity->getID()})");
                        continue;
                    }
                    $this->termPropertyMap[$attrID] = $name;
                } else {
                    Log::warning("Invalid mapping of property `{$name}` in entity ({$entity->getID()})");
                }
            }
        }
    }

    /**
     * Get the term(vocabulary) to class name map.
     *
     * @return string[]
     *   The term class map. Keyed by the term ID. Each element is the mapped class name.
     */
    public function getTermClassMap(): array
    {
        return $this->termClassMap;
    }

    /**
     * Check whether a term(vocabulary) has a mapped class.
     *
     * @param string $termID
     * @return bool
     */
    public function hasTermClass(string $termID): bool
    {
        return isset($this->termClassMap[$termID]);
    }

    /**
     * Get the mapped class name of a term(vocabulary).
     *
     * @param string $termID
     * @return string|null
     */
    public function getTermClass(string $termID): ?string
    {
        return $this->termClassMap[$termID] ?? null;
    }

    /**
     * Get the term(vocabulary) attributes to property name map.
     *
     * @return string[]
     *   The term property map. Keyed by term attribute ID. Each element is the property name.
     */
    public function getTermPropertyMap(): array
    {
        return $this->termPropertyMap;
    }

    /**
     * Check whether a term(vocabulary) attribute has a mapped property.
     *
     * @param string $termID
     *   The term attribute ID.
     * @return bool
     */
    public function hasTermProperty(string $termID): bool
    {
        return isset($this->termPropertyMap[$termID]);
    }

    /**
     * Get the mapped property name of a term(vocabulary) attribute.
     *
     * @param string $termID
     *   The term attribute ID.
     * @return string|null
     */
    public function getTermProperty(string $termID): ?string
    {
        return $this->termPropertyMap[$termID] ?? null;
    }

    /**
     * Get the record type to class name map.
     *
     * @return string[]
     *   The record type class map. Keyed by the record type ID. Each element is the mapped class name.
     */
    public function getRecordTypeClassMap(): array
    {
        return $this->recordTypeClassMap;
    }

    /**
     * Check whether a record type has a mapped class.
     *
     * @param string $recordTypeID
     * @return bool
     */
    public function hasRecordTypeClass(string $recordTypeID): bool
    {
        return isset($this->recordTypeClassMap[$recordTypeID]);
    }

    /**
     * Get the mapped class name of a record type.
     *
     * @param string $recordTypeID
     * @return string|null
     */
    public function getRecordTypeClass(string $recordTypeID): ?string
    {
        return $this->recordTypeClassMap[$recordTypeID] ?? null;
    }

    /**
     * Get the field/base field to property name map.
     *
     * @return string[]
     *   The field property map. Keyed by field/base field ID. Each element is the property name.
     */
    public function getFieldPropertyMap(): array
    {
        return $this->fieldPropertyMap;
    }

    /**
     * Check whether a field/base field has a mapped property.
     *
     * @param string $fieldID
     * @return bool
     */
    public function hasFieldProperty(string $fieldID): bool
    {
        return isset($this->fieldPropertyMap[$fieldID]);
    }

    /**
     * Get the mapped property name of a field/base field.
     *
     * @param string $fieldID
     * @return string|null
     */
    public function getFieldProperty(string $fieldID): ?string
    {
        return $this->fieldPropertyMap[$fieldID] ?? null;
    }

    /**
     * Get the field/base field to function map.
     *
     * @return Entity[]
     *   The field function map. Keyed by field/base field ID. Each element is the RO-Crate entity of the function.
     */
    public function getFieldFunctionMap(): array
    {
        return $this->fieldFunctionMap;
    }

    /**
     * Check whether a field/base field has a mapped function.
     *
     * @param string $fieldID
     *   The field or base field ID.
     * @return bool
     */
    public function hasFieldFunction(string $fieldID): bool
    {
        return isset($this->fieldFunctionMap[$fieldID]);
    }

    /**
     * Get the mapped function of a field/base field.
     *
     * @param string $fieldID
     *   The field or base field ID.
     * @return Entity|null
     */
    public function getFieldFunction(string $fieldID): ?Entity
    {
        return $this->fieldFunctionMap[$fieldID] ?? null;
    }

    /**
     * Get the custom class map.
     *
     * @return Entity[]
     *   The custom class map. Keyed by the class name. Each element is the class definition entity.
     */
    public function getCustomClassMap(): array
    {
        return $this->customClassMap;
    }

    /**
     * Get the custom property map.
     *
     * @return Entity[]
     *   The custom property map. Keyed by the property name. Each element is the property definition entity.
     */
    public function getCustomPropertyMap(): array
    {
        return $this->customPropertyMap;
    }

    /**
     * Check whether the configuration has a custom class definition with the given name.
     *
     * @param string $name
     * @return bool
     */
    public function hasCustomClass(string $name): bool
    {
        return isset($this->customClassMap[$name]);
    }

    /**
     * Get the RO-Crate class definition of a custom class.
     *
     * @param string $name
     * @return Entity|null
     */
    public function getCustomClass(string $name): ?Entity
    {
        return $this->customClassMap[$name] ?? null;
    }

    /**
     * Check whether the configuration has a custom property definition with the given name.
     *
     * @param string $name
     * @return bool
     */
    public function hasCustomProperty(string $name): bool
    {
        return isset($this->customPropertyMap[$name]);
    }

    /**
     * Get the RO-Crate property definition of a custom property.
     *
     * @param string $name
     * @return Entity|null
     */
    public function getCustomProperty(string $name): ?Entity
    {
        return $this->customPropertyMap[$name] ?? null;
    }

}