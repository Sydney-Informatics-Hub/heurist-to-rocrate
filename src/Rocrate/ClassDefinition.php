<?php

namespace UtilityCli\Rocrate;

class ClassDefinition extends Entity
{
    /**
     * @inheritdoc
     */
    public function __construct(?string $id = null)
    {
        parent::__construct('rdfs:Class', $id);
    }

    /**
     * Get the class name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->get('rdfs:label');
    }

    /**
     * Set the class name.
     *
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->set('rdfs:label', $name);
    }

    /**
     * Get the class description.
     */
    public function getDescription(): string
    {
        return $this->get('rdfs:comment');
    }

    /**
     * Set the class description.
     */
    public function setDescription(string $description): void
    {
        $this->set('rdfs:comment', $description);
    }

}
