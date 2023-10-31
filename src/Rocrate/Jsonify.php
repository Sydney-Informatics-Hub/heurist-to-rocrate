<?php

namespace UtilityCli\Rocrate;

interface Jsonify
{
    /**
     * Convert the object to array.
     *
     * @return array
     */
    public function toArray(): array;
}
