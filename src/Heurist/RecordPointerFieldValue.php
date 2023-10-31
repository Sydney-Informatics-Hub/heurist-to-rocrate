<?php

namespace UtilityCli\Heurist;

class RecordPointerFieldValue extends GenericFieldValue
{
    /**
     * @var Record $target
     *   The target record of the record pointer field.
     */
    protected Record $target;

    /**
     * Get the target record.
     *
     * @return Record
     */
    public function getTarget(): Record
    {
        return $this->target;
    }

    /**
     * Set the target record.
     *
     * @param Record $target
     */
    public function setTarget(Record $target): void
    {
        $this->target = $target;
    }
}