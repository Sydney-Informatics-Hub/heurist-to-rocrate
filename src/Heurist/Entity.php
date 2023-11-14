<?php

namespace UtilityCli\Heurist;

/**
 * Heurist Generic Entity.
 */
class Entity
{
    /**
     * @var array $properties
     *   Stores raw property values from Heurist.
     */
    protected array $properties;

    /**
     * Constructor.
     *
     * @param array $properties
     *   Raw property values from Heurist.
     */
    public function __construct(array $properties = [])
    {
        $this->properties = $properties;
    }

    /**
     * Get the value of a property.
     *
     * @param string $name
     *   Property name.
     * @return string|null
     */
    public function getProperty(string $name): ?string
    {
        if (isset($this->properties[$name]) && (string) $this->properties[$name] !== '') {
            return $this->properties[$name];
        }
        return null;
    }
}