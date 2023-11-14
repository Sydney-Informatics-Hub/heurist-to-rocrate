<?php

namespace UtilityCli\Heurist;

class RecordType extends Entity
{

    /**
     * Get the ID of the record type.
     *
     * @return string|null
     */
    public function getID(): ?string
    {
        return $this->getProperty('rty_ID');
    }

    /**
     * Get the name of the record type.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->getProperty('rty_Name');
    }

    /**
     * Get the description of the record type.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->getProperty('rty_Description');
    }
}