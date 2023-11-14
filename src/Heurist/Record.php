<?php

namespace UtilityCli\Heurist;

class Record
{
    /**
     * @var array $properties
     *   The record properties key-value pairs.
     */
    protected array $properties;

    /**
     * @var array $fields
     *   The values of the fields of the record. Keyed by the field ID. Each element is an array of field value
     *   instances.
     */
    protected array $fields;

    /**
     * @var RecordType $recordType
     *   The record type of the record.
     */
    protected RecordType $recordType;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->properties = [];
        $this->fields = [];
    }

    /**
     * Set the value of a property.
     *
     * @param string $name
     *   Property name.
     * @param string $value
     *   Property value.
     * @return void
     */
    protected function setProperty(string $name, string $value): void
    {
        $this->properties[$name] = $value;
    }

    /**
     * Get the value of a property.
     *
     * @param string $name
     *   Property name.
     * @return string|null
     */
    protected function getProperty(string $name): ?string
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * Get the ID of the record.
     *
     * @return string|null
     */
    public function getID(): ?string
    {
        return $this->getProperty('id');
    }

    /**
     * Set the ID of the record.
     *
     * @param string $id
     * @return void
     */
    public function setID(string $id): void
    {
        $this->setProperty('id', $id);
    }

    /**
     * Get the title of the record.
     *
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->getProperty('title');
    }

    /**
     * Set the title of the record.
     *
     * @param string $title
     * @return void
     */
    public function setTitle(string $title): void
    {
        $this->setProperty('title', $title);
    }

    /**
     * Get the record type of the record.
     *
     * @return RecordType|null
     */
    public function getRecordType(): ?RecordType
    {
        return $this->recordType;
    }

    /**
     * Set the record type of the record.
     *
     * @param RecordType $recordType
     * @return void
     */
    public function setRecordType(RecordType $recordType): void
    {
        $this->recordType = $recordType;
    }

    /**
     * Add a field value to the record.
     *
     * @param GenericFieldValue $fieldValue
     * @return void
     */
    public function addFieldValue(GenericFieldValue $fieldValue): void
    {
        $field = $fieldValue->getField();
        $fieldID = $field->getID();
        if (!isset($this->fields[$fieldID])) {
            $this->fields[$fieldID] = [];
        }
        $this->fields[$fieldID][] = $fieldValue;
    }

    /**
     * Get the field values of the record.
     *
     * @return array
     *   An associative array keyed by the field ID. Each element is an array of field value instances.
     */
    public function getAllFieldValues(): array
    {
        return $this->fields;
    }

    /**
     * Get the values of a single field.
     *
     * @param $fieldID
     * @return GenericFieldValue[]|null
     */
    public function getFieldValues($fieldID): ?array
    {
        return $this->fields[$fieldID] ?? null;
    }

    /**
     * Get the values of a single field by its base field ID>
     *
     * @param $baseFieldID
     * @return GenericFieldValue[]|null
     */
    public function getFieldValuesByBaseFieldID($baseFieldID): ?array
    {
        $fieldID = Field::createFieldID($this->getRecordType()->getID(), $baseFieldID);
        return $this->getFieldValues($fieldID);
    }

}