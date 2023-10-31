<?php

namespace UtilityCli\Rocrate;

use UtilityCli\Helper\Collection;
use UtilityCli\Helper\Text;

/**
 * The entity from the RO-Crate metadata.
 */
class Entity implements Jsonify, ModelInterface
{
    /**
     * The entity data.
     *
     * @var array
     */
    protected array $data;

    /**
     * Constructor.
     *
     * @param string $type
     *   The type of the data entity.
     * @param string|null $id
     *   The ID of the data entity. If omitted, it will assign an automatically generated UUID.
     */
    public function __construct(string $type, ?string $id = null)
    {
        $this->data = [];
        // Generate an UUID if ID is empty.
        if (empty($id)) {
            $id = '#' . Text::uuid();
        }
        $this->set('@id', $id);
        $this->set('@type', $type);
    }

    /**
     * Get the ID of the data entity.
     *
     * @return string
     */
    public function getID(): string
    {
        return $this->get('@id');
    }

    /**
     * Get the type of the data entity.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->get('@type');
    }

    /**
     * Add a part to this data entity.
     *
     * @param Entity $part
     *   The part entity.
     * @return void
     */
    public function addPart(Entity $part): void
    {
        $this->append('hasPart', $part);
    }

    /**
     * Get the parts of the current data entity.
     *
     * @return Entity|Entity[]
     */
    public function getParts(): Entity|array
    {
        return $this->data['hasPart'];
    }

    /**
     * Get the value of a property.
     *
     * @param string $name
     *   The name of the property.
     * @return mixed|Entity|array|null
     */
    public function get(string $name): mixed
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
        return null;
    }

    /**
     * Get all properties of the entity.
     *
     * @return array
     */
    public function getProperties(): array
    {
        return $this->data;
    }

    /**
     * Set the value of a property.
     *
     * @param string $name
     *   The name of the property.
     * @param mixed|Entity|array $value
     *   The value of the property
     * @return void
     */
    public function set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * Append a value to a property.
     *
     * This won't overwrite the property value if the property already has one or many values. It will append the value
     * to the existing value as an array.
     *
     * @param string $name
     *   The name of the property.
     * @param mixed|Entity|array $value
     *   The value of the property.
     * @return void
     */
    public function append(string $name, mixed $value): void
    {
        if (isset($this->data[$name])) {
            if (is_array($this->data[$name]) && !Collection::isAssociativeArray($this->data[$name])) {
                // Add the value to array if it's an array already.
                $this->data[$name][] = $value;
            } else {
                // Make the property as an array if it has a single value.
                $this->data[$name] = [$this->data[$name], $value];
            }
        } else {
            $this->set($name, $value);
        }
    }

    /**
     * Unset a property.
     *
     * @param string $name
     *   The name of the property.
     * @return void
     */
    public function unset(string $name): void
    {
        unset($this->data[$name]);
    }

    /**
     * Convert the entity to an array.
     *
     * The referenced entities from this entity will be converted into the result array
     * as well. Deep nested entities will be converted recursively.
     *
     * @return array
     *   The array of entities and their referenced entities. Each element is an associative array representing the
     *   entity.
     */
    public function toArray(): array
    {
        // Array holding all entities. Keyed by the ID of the entity.
        $result = [];
        $data = $this->data;
        /**
         * @var Entity[] $linkedEntities
         *   Array holding the referenced entities from this entity. Keyed by the ID of the entity.
         */
        $linkedEntities = [];
        foreach ($data as $key => $value) {
            if ($value instanceof Entity) {
                $data[$key] = ['@id' => $value->getID()];
                $linkedEntities[$value->getID()] = $value;
            } elseif (is_array($value) && !Collection::isAssociativeArray($value)) {
                foreach ($value as $index => $item) {
                    if ($item instanceof Entity) {
                        $data[$key][$index] = ['@id' => $item->getID()];
                        $linkedEntities[$item->getID()] = $item;
                    }
                }
            }
        }
        $result[$this->getID()] = $data;
        // Convert the linked entities.
        if (!empty($linkedEntities)) {
            foreach ($linkedEntities as $linkedEntity) {
                $linkedResult = $linkedEntity->toArray();
                $result = array_merge($result, $linkedResult);
            }
        }
        return array_values($result);
    }

    /**
     * Create an entity object from entity data.
     *
     * @param array $data
     * @return Entity
     */
    public static function createFromArray(array $data): Entity
    {
        switch ($data['@type']) {
            case 'rdfs:Class':
                $entity = new ClassDefinition($data['@id']);
                break;
            case 'rdf:Property':
                $entity = new PropertyDefinition($data['@id']);
                break;
            default:
                $entity = new Entity($data['@type'], $data['@id']);
        }
        foreach ($data as $name => $value) {
            $entity->set($name, $value);
        }
        return $entity;
    }
}
