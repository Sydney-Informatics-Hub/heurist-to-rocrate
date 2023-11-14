<?php

namespace UtilityCli\Heurist;

class Field extends Entity
{
    /**
     * @var RecordType $recordType
     *   The record type which the field belongs to.
     */
    protected RecordType $recordType;

    /**
     * @var BaseField $baseField
     *   The base field.
     */
    protected BaseField $baseField;

    /**
     * Get the ID of the field.
     *
     * This is different from the internal field ID from Heurist as the internal ID doesn't seem to be used anywhere.
     * To identify a field, it uses the combination of the record type ID and the base field ID in the format of
     * `{rst_RecTypeID}:{rst_DetailTypeID}`. For example, `1:1`.
     *
     * @return string|null
     */
    public function getID(): ?string
    {
        return self::createFieldID($this->getProperty('rst_RecTypeID'), $this->getProperty('rst_DetailTypeID'));
    }

    /**
     * Get the name of the field.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->getProperty('rst_DisplayName');
    }

    /**
     * Get the description of the field.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->getProperty('rst_DisplayHelpText');
    }

    /**
     * Get the ID of the record type which the field belongs to.
     *
     * @return string|null
     */
    public function getRecordTypeID(): ?string
    {
        return $this->getProperty('rst_RecTypeID');
    }

    /**
     * Get the ID of the base field.
     *
     * @return string|null
     */
    public function getBaseFieldID(): ?string
    {
        return $this->getProperty('rst_DetailTypeID');
    }

    /**
     * Get the record type which the field belongs to.
     *
     * @return RecordType|null
     */
    public function getRecordType(): ?RecordType
    {
        return $this->recordType;
    }

    /**
     * Set the record type which the field belongs to.
     *
     * @param RecordType $recordType
     * @return void
     */
    public function setRecordType(RecordType $recordType): void
    {
        $this->recordType = $recordType;
    }

    /**
     * Get the base field.
     *
     * @return BaseField|null
     */
    public function getBaseField(): ?BaseField
    {
        return $this->baseField;
    }

    /**
     * Set the base field.
     *
     * @param BaseField $baseField
     * @return void
     */
    public function setBaseField(BaseField $baseField): void
    {
        $this->baseField = $baseField;
    }

    /**
     * Create the field ID based on the record type ID and the base field ID.
     *
     * This is different from the internal field ID from Heurist as the internal ID doesn't seem to be used anywhere.
     * To identify a field, it uses the combination of the record type ID and the base field ID in the format of
     * `{rst_RecTypeID}:{rst_DetailTypeID}`. For example, `1:1`.
     *
     * @param string $recordTypeID
     * @param string $baseFieldID
     * @return string
     */
    public static function createFieldID(string $recordTypeID, string $baseFieldID): string
    {
        return "{$recordTypeID}:{$baseFieldID}";
    }
}