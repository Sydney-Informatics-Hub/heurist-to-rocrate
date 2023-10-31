<?php

namespace UtilityCli\Converter\Functions;

use UtilityCli\Heurist\GenericFieldValue;
use UtilityCli\Rocrate\Entity;

/**
 * Base class of field value function.
 */
abstract class ValueFunction
{
    const FUNCTION_TO_TEXT = 'to_text';

    /**
     * The field value instance.
     *
     * @var GenericFieldValue
     */
    protected GenericFieldValue $fieldValue;

    /**
     * Constructor.
     *
     * @param GenericFieldValue $fieldValue
     *   The field value instance.
     */
    public function __construct(GenericFieldValue $fieldValue)
    {
        $this->fieldValue = $fieldValue;
    }

    /**
     * Get the value of the field mutated by the function.
     *
     * @return mixed
     */
    abstract public function getValue(): mixed;

    /**
     * Create a value function instance based on the function definition from the configuration.
     *
     * @param Entity $entity
     *   The RO-Crate entity of the function from the configuration.
     * @param GenericFieldValue $fieldValue
     *   The field value instance.
     * @return self
     * @throws \Exception
     */
    public static function create(Entity $entity, GenericFieldValue $fieldValue): self
    {
        switch ($entity->get('name')) {
            case self::FUNCTION_TO_TEXT:
                return new ToTextValueFunction($fieldValue);
            default:
                throw new \Exception('Invalid value function ' . $entity->get('name'));
        }
    }
}