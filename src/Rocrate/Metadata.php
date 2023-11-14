<?php

namespace UtilityCli\Rocrate;

use UtilityCli\Helper\Collection;

/**
 * The RO-Crate metadata.
 */
class Metadata implements Jsonify
{
    /**
     * The main entity of the RO-Crate metadata.
     *
     * @var Entity $mainEntity
     */
    protected Entity $mainEntity;

    /**
     * The data entities from the RO-Crate metadata (keyed by the entity ID).
     *
     * @var Entity[]
     */
    protected array $entities;

    /**
     * The context of the RO-Crate metadata.
     *
     * @var Context
     */
    protected Context $context;

    /**
     * Constructor.
     *
     * @param array $data
     *   The RO-Crate metadata JSON parsed in array.
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->parse($data);
        } else {
            $this->entities = [];
            $this->context = new Context();
            $this->mainEntity = self::createMainEntity();
            $this->entities['./'] = self::createRootEntity();
        }
    }

    /**
     * Get the context of the metadata.
     *
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * Get the entity by ID.
     *
     * @param string $id
     * @return Entity|null
     */
    public function getEntity(string $id): ?Entity
    {
        if (isset($this->entities[$id])) {
            return $this->entities[$id];
        }
        return null;
    }

    /**
     * Add an entity to the metadata.
     *
     * @param Entity $entity
     */
    public function addEntity(Entity $entity): void
    {
        $this->entities[$entity->getID()] = $entity;
    }

    /**
     * Get the root entity.
     *
     * @return Entity|null
     */
    public function getRootEntity(): ?Entity
    {
        return $this->getEntity('./');
    }

    /**
     * Set the name of the root entity.
     *
     * @param string $name
     */
    public function setRootEntityName(string $name): void
    {
        $rootEntity = $this->getRootEntity();
        $rootEntity?->set('name', $name);
    }

    /**
     * Set the description of the root entity.
     *
     * @param string $description
     */
    public function setRootEntityDescription(string $description): void
    {
        $rootEntity = $this->getRootEntity();
        $rootEntity?->set('description', $description);
    }

    /**
     * @inheritdoc
     */
    public function toArray(): array
    {
        $result = $this->context->toArray();
        $entityData = $this->mainEntity->toArray();
        $addedEntityIDs = [];
        foreach ($this->entities as $entity) {
            if (!in_array($entity->getID(), $addedEntityIDs)) {
                $entityResult = $entity->toArray();
                foreach ($entityResult as $item) {
                    if (!in_array($item['@id'], $addedEntityIDs)) {
                        $entityData[] = $item;
                        $addedEntityIDs[] = $item['@id'];
                    }
                }
            }
        }
        $result['@graph'] = $entityData;
        return $result;
    }

    /**
     * Parse the metadata data.
     *
     * This method parses the original JSON data into the entity models.
     */
    protected function parse(array $data): void
    {
        // Create the context.
        $this->context = Context::createFromArray($data['@context']);

        // Loop through all the graph entities.
        $this->entities = [];
        foreach ($data['@graph'] as $entityData) {
            $entity = Entity::createFromArray($entityData);
            if ($entity->getID() === 'ro-crate-metadata.json' && $entity->getType() === 'CreativeWork') {
                $this->mainEntity = $entity;
            } else {
                $this->entities[$entity->getID()] = $entity;
            }
        }
        // Link all entities by relationships.
        $this->linkEntities();
    }

    /**
     * Link entity through references.
     */
    protected function linkEntities(): void
    {
        foreach ($this->entities as $entity) {
            $properties = $entity->getProperties();
            foreach ($properties as $name => $value) {
                if (is_array($value)) {
                    if (isset($value['@id'])) {
                        $linkedEntity = $this->getEntity($value['@id']);
                        if (isset($linkedEntity)) {
                            $entity->set($name, $linkedEntity);
                        }
                    } elseif (!Collection::isAssociativeArray($value)) {
                        $linkedEntities = [];
                        foreach ($value as $item) {
                            if (isset($item['@id'])) {
                                $linkedEntity = $this->getEntity($item['@id']);
                                if (isset($linkedEntity)) {
                                    $linkedEntities[] = $linkedEntity;
                                }
                            }
                        }
                        $entity->set($name, $linkedEntities);
                    }
                }
            }
        }
    }

    /**
     * Create the main entity.
     *
     * @return Entity
     */
    public static function createMainEntity(): Entity
    {
        $entity = new Entity('CreativeWork', 'ro-crate-metadata.json');
        $entity->set('conformsTo', ['@id' => 'https://w3id.org/ro/crate/1.1']);
        $entity->set('about', ['@id' => './']);
        return $entity;
    }

    /**
     * Create the root entity.
     *
     * @param string $name
     *   The name property of the root entity.
     * @param string $description
     *   The description property of the root entity.
     * @return Entity
     */
    public static function createRootEntity(string $name = '', string $description = ''): Entity
    {
        $entity = new Entity('Dataset', './');
        if (!empty($name)) {
            $entity->set('name', $name);
        }
        if (!empty($description)) {
            $entity->set('description', $description);
        }
        return $entity;
    }
}
