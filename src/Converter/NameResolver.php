<?php

namespace UtilityCli\Converter;

use UtilityCli\Heurist\BaseField;
use UtilityCli\Heurist\Field;
use UtilityCli\Heurist\RecordType;

/**
 * Class to resolve the Heurist fields and record types to the class and property names in the RO-Crate metadata.
 */
class NameResolver
{
    /**
     * @var string[] $reservedClasses
     *  The reserved class names.
     */
    protected array $reservedClasses;

    /**
     * @var string[] $resolved
     *   The resolved names map. Keyed by the entity identifier.
     */
    protected array $resolved;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->reservedClasses = [
            'CreativeWork',
            'Dataset',
            'File',
            'DefinedTerm',
            'DefinedTermSet',
            'GeoCoordinates',
            'GeoShape'
        ];
        $this->resolved = [];
    }

    /**
     * Check whether an entity's name has been resolved.
     *
     * @param RecordType|BaseField|Field $entity
     * @return bool
     * @throws \Exception
     */
    public function hasResolved(mixed $entity): bool
    {
        $identifier = Utility::createEntityIdentifier($entity);
        return isset($this->resolved[$identifier]);
    }

    /**
     * Resolve an entity's name.
     *
     * @param RecordType|BaseField|Field $entity
     * @param string $contextName
     *   The context name used to resolve the naming conflict.
     * @return string
     *   The resolved name.
     * @throws \Exception
     */
    public function resolve(mixed $entity, string $contextName = ''): string
    {
        $identifier = Utility::createEntityIdentifier($entity);
        if (isset($this->resolved[$identifier])) {
            return $this->resolved[$identifier];
        }

        if ($entity instanceof RecordType) {
            // Resolve class name.
            $name = Utility::createClassName($entity->getName());
            if (in_array($name, array_values($this->resolved)) || in_array($name, $this->reservedClasses)) {
                // Prepend the context name.
                $name = Utility::createClassName($contextName . ' ' . $entity->getName());
            }
        } else {
            // Resolve property name.
            $name = Utility::createPropertyName($entity->getName());
            if (in_array($name, array_values($this->resolved))) {
                // Prepend the context name.
                $name = Utility::createPropertyName($contextName . ' ' . $entity->getName());
            }
        }
        // Check there's on naming conflict.
        if (in_array($name, array_values($this->resolved))) {
            $delta = 1;
            while (in_array($name . $delta, array_values($this->resolved))) {
                $delta++;
            }
            $name .= $delta;
        }
        // Add the resolved entry.
        $this->resolved[$identifier] = $name;
        return $name;
    }

    /**
     * Add a resolved entry from an entity identifier.
     *
     * @param string $identifier
     *   The identifier of the entity.
     * @param string $resolvedName
     *   The resolved name.
     * @return void
     */
    public function addEntryFromIdentifier(string $identifier, string $resolvedName): void
    {
        $this->resolved[$identifier] = $resolvedName;
    }

    /**
     * Add an entry to the resolved map.
     *
     * @param mixed $entity
     * @param string $resolvedName
     * @return void
     * @throws \Exception
     */
    public function addEntry(mixed $entity, string $resolvedName): void
    {
        $identifier = Utility::createEntityIdentifier($entity);
        $this->addEntryFromIdentifier($identifier, $resolvedName);
    }

    /**
     * Check whether an entity's name has a naming conflict.
     *
     * @param RecordType|BaseField|Field $entity
     * @return bool
     */
    public function hasNameConflict(mixed $entity): bool
    {
        $identifier = Utility::createEntityIdentifier($entity);
        if (isset($this->resolved[$identifier])) {
            // The entity has been resolved once that there's no conflict.
            return false;
        }

        if ($entity instanceof RecordType) {
            // Check class name.
            $name = Utility::createClassName($entity->getName());
            if (in_array($name, array_values($this->resolved))) {
                return true;
            }
        } else {
            // Check property name.
            $name = Utility::createPropertyName($entity->getName());
            if (in_array($name, array_values($this->resolved))) {
                return true;
            }
        }
        return false;
    }
}