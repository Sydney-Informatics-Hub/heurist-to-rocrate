<?php

namespace UtilityCli\Converter\Functions;

class ToTextValueFunction extends ValueFunction
{
    /**
     * Return the field value as a plain text string.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->fieldValue->getValue();
    }

}