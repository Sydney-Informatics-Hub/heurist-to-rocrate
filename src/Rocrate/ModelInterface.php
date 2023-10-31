<?php

namespace UtilityCli\Rocrate;

interface ModelInterface
{
    /**
     * Create an instance from data array.
     *
     * @param array $data
     *   The data to create the object.
     * @return static
     */
    public static function createFromArray(array $data): self;
}
