<?php

namespace UtilityCli\Heurist;

class GenericFieldValue
{
    /**
     * @var Field $field
     *   The field instance of the field value.
     */
    protected Field $field;

    /**
     * @var string $value
     *   The value of the field.
     */
    protected string $value;

    public function __construct(Field $field, string $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    /**
     * Get the value of the field.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get the field instance of the field value.
     *
     * @return Field
     */
    public function getField(): Field
    {
        return $this->field;
    }
}