<?php

namespace UtilityCli\Heurist;

class BaseField extends Entity
{
    const TYPE_MEMO = 'blocktext';
    const TYPE_DATE = 'date';
    const TYPE_TERM = 'enum';
    const TYPE_FILE = 'file';
    const TYPE_DECIMAL = 'float';
    const TYPE_TEXT = 'freetext';
    const TYPE_GEO = 'geo';
    const TYPE_INTEGER = 'integer';
    const TYPE_RELATION_TYPE = 'relationtype';
    const TYPE_RELATIONSHIP = 'relmarker';
    const TYPE_RECORD_POINTER = 'resource';
    const TYPE_SEPARATOR = 'separator';

    /**
     * @var RecordType[] $refTargetRecordTypes
     *   The allowed target record types of the record pointer field.
     */
    protected array $refTargetRecordTypes = [];

    /**
     * Get the ID of the base field.
     *
     * @return string|null
     */
    public function getID(): ?string
    {
        return $this->getProperty('dty_ID');
    }

    /**
     * Get the name of the base field.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->getProperty('dty_Name');
    }

    /**
     * Get the description of the base field.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->getProperty('dty_HelpText');
    }

    /**
     * Get the type of the base field.
     *
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->getProperty('dty_Type');
    }

    /**
     * Get the allowed target record type IDs of the record pointer field.
     *
     * @return array|null
     */
    public function getRefTargetRecordTypeIDs(): ?array
    {
        $value = $this->getProperty('dty_PtrTargetRectypeIDs');
        if (!empty($value)) {
            return explode(',', $value);
        }
        return null;
    }

    /**
     * Get the allowed target record types of the record pointer field.
     *
     * @return RecordType[]|null
     */
    public function getRefTargetRecordTypes(): ?array
    {
        return $this->refTargetRecordTypes;
    }

    /**
     * Set the allowed target record types of the record pointer field.
     *
     * @param RecordType[] $recordTypes
     * @return void
     */
    public function setRefTargetRecordTypes(array $recordTypes): void
    {
        $this->refTargetRecordTypes = $recordTypes;
    }
}