<?php

namespace UtilityCli\Heurist;

use UtilityCli\Log\Log;

/**
 * Data source from Heurist.
 */
class HeuristData
{
    /**
     * @var string $dbName
     *   The Heurist database name.
     */
    protected string $dbName;

    /**
     * @var string|null $name
     *   The Human readable name of the Heurist database.
     */
    protected ?string $name = null;

    /**
     * @var string|null $description
     *   The description of the Heurist database.
     */
    protected ?string $description = null;

    /**
     * @var \SimpleXMLElement $structureXML
     *   The parsed XML from the structure file.
     */
    protected \SimpleXMLElement $structureXML;

    /**
     * @var \SimpleXMLElement $dataXML
     *   The parsed XML from the data file.
     */
    protected \SimpleXMLElement $dataXML;

    /**
     * @var Term[] $terms
     *   Array of terms from Heurist. Keyed by term ID.
     */
    protected array $terms;

    /**
     * @var RecordType[] $recordTypes
     *   Array of record types from Heurist. Keyed by record type ID.
     */
    protected array $recordTypes;

    /**
     * @var BaseField[] $baseFields
     *   Array of base fields from Heurist. Keyed by base field ID.
     */
    protected array $baseFields;

    /**
     * @var Field[] $fields
     *   Array of fields from Heurist. Keyed by field ID.
     */
    protected array $fields;

    /**
     * @var Record[] $records
     *   Array of records from Heurist. Keyed by record ID.
     */
    protected array $records;

    /**
     * Constructor.
     *
     * @param \SimpleXMLElement $structureXML
     *   The parsed XML from the structure file.
     * @param \SimpleXMLElement $dataXML
     *   The parsed XML from the data file.
     */
    public function __construct(\SimpleXMLElement $structureXML, \SimpleXMLElement $dataXML)
    {
        $this->structureXML = $structureXML;
        $this->dataXML = $dataXML;
        $this->parseTerms();
        $this->parseRecordTypes();
        $this->parseBaseFields();
        $this->parseFields();
        $this->parseEntityRelationships();
        $this->parseRecords();
    }

    /**
     * Parse terms from the structure XML.
     *
     * @return void
     */
    protected function parseTerms(): void
    {
        $this->terms = [];
        $sourceTerms = $this->structureXML->xpath('//Terms/trm');
        foreach ($sourceTerms as $sourceTerm) {
            $term = new Term((array) $sourceTerm);
            $this->terms[$term->getID()] = $term;
        }
    }

    /**
     * Parse record types from the structure XML.
     *
     * @return void
     */
    protected function parseRecordTypes(): void
    {
        $this->recordTypes = [];
        $sourceRecordTypes = $this->structureXML->xpath('//RecTypes/rty');
        foreach ($sourceRecordTypes as $sourceRecordType) {
            $recordType = new RecordType((array) $sourceRecordType);
            $this->recordTypes[$recordType->getID()] = $recordType;
        }
    }

    /**
     * Parse base fields from the structure XML.
     *
     * @return void
     */
    protected function parseBaseFields(): void
    {
        $this->baseFields = [];
        $sourceBaseFields = $this->structureXML->xpath('//DetailTypes/dty');
        foreach ($sourceBaseFields as $sourceBaseField) {
            $baseField = new BaseField((array) $sourceBaseField);
            $this->baseFields[$baseField->getID()] = $baseField;
        }
    }

    /**
     * Parse fields from the structure XML.
     *
     * @return void
     */
    protected function parseFields(): void
    {
        $this->fields = [];
        $sourceFields = $this->structureXML->xpath('//RecStructure/rst');
        foreach ($sourceFields as $sourceField) {
            $field = new Field((array) $sourceField);
            $this->fields[$field->getID()] = $field;
        }
    }

    /**
     * Link entities from Heurist by their relationships.
     *
     * @return void
     */
    protected function parseEntityRelationships(): void
    {
        // Set term relationships.
        foreach ($this->terms as $term) {
            // Term parent.
            $parentID = $term->getParentID();
            if (!empty($parentID) && isset($this->terms[$parentID])) {
                $term->setParent($this->terms[$parentID]);
            }
        }

        // Set base field relationships.
        foreach ($this->baseFields as $baseField) {
            // Base field reference target record types.
            $refTargetRecordTypeIDs = $baseField->getRefTargetRecordTypeIDs();
            if (!empty($refTargetRecordTypeIDs)) {
                $refTargetRecordTypes = [];
                foreach ($refTargetRecordTypeIDs as $refTargetRecordTypeID) {
                    if (isset($this->recordTypes[$refTargetRecordTypeID])) {
                        $refTargetRecordTypes[] = $this->recordTypes[$refTargetRecordTypeID];
                    }
                }
                $baseField->setRefTargetRecordTypes($refTargetRecordTypes);
            }
        }

        // Set field relationships.
        foreach ($this->fields as $field) {
            // Field record type.
            $recordTypeID = $field->getRecordTypeID();
            if (!empty($recordTypeID) && isset($this->recordTypes[$recordTypeID])) {
                $field->setRecordType($this->recordTypes[$recordTypeID]);
            }
            // Field base field.
            $baseFieldID = $field->getBaseFieldID();
            if (!empty($baseFieldID) && isset($this->baseFields[$baseFieldID])) {
                $field->setBaseField($this->baseFields[$baseFieldID]);
            }
        }
    }

    /**
     * Parse records from the data XML.
     *
     * @return void
     */
    protected function parseRecords(): void
    {
        $this->records = [];

        // Load all the records.
        $sourceRecords = $this->dataXML->records->record;
        foreach ($sourceRecords as $sourceRecord) {
            $record = new Record();
            $record->setID($sourceRecord->id);
            $record->setTitle($sourceRecord->title);
            $recordType = $this->findRecordType($sourceRecord->type['id']);
            $record->setRecordType($recordType);
            $this->records[$record->getID()] = $record;
        }

        // Load field values for each records.
        $sourceRecords = $this->dataXML->records->record;
        foreach ($sourceRecords as $sourceRecord) {
            $record = $this->findRecord($sourceRecord->id);
            foreach ($sourceRecord->detail as $detail) {
                $fieldValue = $this->createFieldValue($record->getRecordType()->getID(), $detail);
                if (isset($fieldValue)) {
                    $record->addFieldValue($fieldValue);
                }
            }
        }
    }

    /**
     * Get the database name.
     *
     * @return string
     */
    public function getDbName(): string
    {
        return $this->dbName;
    }

    /**
     * Set the database name.
     *
     * @param string $dbName
     */
    public function setDbName(string $dbName): void
    {
        $this->dbName = $dbName;
    }

    /**
     * Get the human readable name of the database.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the human readable name of the database.
     *
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Get the description of the database.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set the description of the database.
     *
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Find a term by ID.
     *
     * @param string $termID
     * @return Term|null
     */
    public function findTerm(string $termID): ?Term
    {
        return $this->terms[$termID] ?? null;
    }

    /**
     * Find a record type by ID.
     *
     * @param string $recordTypeID
     * @return RecordType|null
     */
    public function findRecordType(string $recordTypeID): ?RecordType
    {
        return $this->recordTypes[$recordTypeID] ?? null;
    }

    /**
     * Find a base field by ID.
     *
     * @param string $baseFieldID
     * @return BaseField|null
     */
    public function findBaseField(string $baseFieldID): ?BaseField
    {
        return $this->baseFields[$baseFieldID] ?? null;
    }

    /**
     * Find a field by ID.
     *
     * @param string $fieldID
     * @return Field|null
     */
    public function findField(string $fieldID): ?Field
    {
        return $this->fields[$fieldID] ?? null;
    }

    /**
     * Find a field by record type and base field.
     *
     * @param string $recordTypeID
     * @param string $baseFieldID
     * @return Field|null
     */
    public function findFieldByRecordTypeAndBaseField(string $recordTypeID, string $baseFieldID): ?Field
    {
        $fieldID = Field::createFieldID($recordTypeID, $baseFieldID);
        return $this->fields[$fieldID] ?? null;
    }

    /**
     * Find a record by ID.
     *
     * @param string $recordID
     * @return Record|null
     */
    public function findRecord(string $recordID): ?Record
    {
        return $this->records[$recordID] ?? null;
    }

    /**
     * Get the record types which have fields from a base field.
     *
     * @param BaseField $baseField
     * @return RecordType[]
     */
    public function getBaseFieldUsedRecordTypes(BaseField $baseField): array
    {
        $recordTypes = [];
        foreach ($this->fields as $field) {
            if ($field->getBaseFieldID() === $baseField->getID()) {
                $recordTypes[] = $field->getRecordType();
            }
        }
        return $recordTypes;
    }

    /**
     * Get all terms (keyed by term ID).
     *
     * @return Term[]
     */
    public function getTerms(): array
    {
        return $this->terms;
    }

    /**
     * Get the number of terms.
     *
     * @return int
     */
    public function getTermsCount(): int
    {
        return count($this->terms);
    }

    /**
     * Get all vocabularies (top-level terms).
     *
     * @return Term[]
     */
    public function getVocabularies(): array
    {
        $vocabularies = [];
        foreach ($this->terms as $term) {
            if ($term->isVocabulary()) {
                $vocabularies[] = $term;
            }
        }
        return $vocabularies;
    }

    /**
     * Get the child terms of a term.
     *
     * @param Term $parent
     * @return Term[]|null
     */
    public function getChildTerms(Term $parent): ?array
    {
        return $this->search('terms', ['trm_ParentTermID' => $parent->getID()]);
    }

    /**
     * Get all record types (keyed by record type ID).
     *
     * @return RecordType[]
     */
    public function getRecordTypes(): array
    {
        return $this->recordTypes;
    }

    /**
     * Get the number of record types.
     *
     * @return int
     */
    public function getRecordTypesCount(): int
    {
        return count($this->recordTypes);
    }

    /**
     * Get all base fields (keyed by base field ID).
     *
     * @return BaseField[]
     */
    public function getBaseFields(): array
    {
        return $this->baseFields;
    }

    /**
     * Get the number of base fields.
     *
     * @return int
     */
    public function getBaseFieldsCount(): int
    {
        return count($this->baseFields);
    }

    /**
     * Get all fields (keyed by field ID).
     *
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Get the number of fields.
     *
     * @return int
     */
    public function getFieldsCount(): int
    {
        return count($this->fields);
    }

    /**
     * Get all records (keyed by record ID).
     *
     * @return Record[]
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * Get the number of records.
     *
     * @return int
     */
    public function getRecordsCount(): int
    {
        return count($this->records);
    }

    /**
     * Search entities by type and criteria.
     *
     * @param string $entityType
     *   The type of the entity.
     * @param array $criteria
     *   The criteria to search for. It's an associative array of property name and value.
     * @param bool $first
     *   Whether to return the first matched entity only.
     * @return null|Entity|Entity[]
     */
    public function search(string $entityType, array $criteria = [], bool $first = false): mixed
    {
        if (isset($this->{$entityType})) {
            if (empty($criteria) && !$first) {
                $entities = array_values($this->{$entityType});
            } else {
                $entities = [];
                foreach ($this->{$entityType} as $entity) {
                    $match = true;
                    foreach ($criteria as $key => $value) {
                        if ($entity->getProperty($key) !== $value) {
                            $match = false;
                            break;
                        }
                    }
                    if ($match) {
                        if ($first) {
                            return $entity;
                        } else {
                            $entities[] = $entity;
                        }
                    }
                }
            }
            if (!empty($entities)) {
                return $entities;
            }
        }
        return null;
    }

    /**
     * Create the field value instance from the XML element.
     *
     * @param string $recordTypeID
     *   The record type ID of the field value.
     * @param \SimpleXMLElement $xml
     *   The XML element (<detail>) of the field value.
     * @return GenericFieldValue|null
     *   The field value instance. This can be the generic field value (`GenericFieldValue`) or more specific field
     *   value instance. It returns null if the field is invalid on the record type (some bugs from Heurist).
     */
    protected function createFieldValue(string $recordTypeID, \SimpleXMLElement $xml): ?GenericFieldValue
    {
        $baseFieldID = $xml['id'];
        $field = $this->findFieldByRecordTypeAndBaseField($recordTypeID, $baseFieldID);
        if (!isset($field)) {
            // Some record from Heurist has ad-hoc values on base fields which don't have corresponding fields
            // defined in the Heurist structure. To avoid the data loss, we create the field on the fly.
            $baseField = $this->findBaseField($baseFieldID);
            $recordType = $this->findRecordType($recordTypeID);
            if (isset($baseField) && isset($recordType)) {
                // Create the non-standard field.
                $field = $this->createNonStandardField($recordType, $baseField);
                $field->setRecordType($recordType);
                $field->setBaseField($baseField);
                $this->fields[$field->getID()] = $field;
            }
        }
        if (isset($field)) {
            switch ($field->getBaseField()->getType()) {
                case BaseField::TYPE_TERM:
                    return $this->createTermFieldValue($recordTypeID, $xml);
                case BaseField::TYPE_DATE:
                    return $this->createDateFieldValue($recordTypeID, $xml);
                case BaseField::TYPE_GEO:
                    return $this->createGeoFieldValue($recordTypeID, $xml);
                case BaseField::TYPE_FILE:
                    return $this->createFileFieldValue($recordTypeID, $xml);
                case BaseField::TYPE_RECORD_POINTER:
                    return $this->createRecordPointerFieldValue($recordTypeID, $xml);
                default:
                    return $this->createGenericFieldValue($recordTypeID, $xml);
            }
        }
        return null;
    }

    /**
     * Create the generic field value from the XML element.
     *
     * @param string $recordTypeID
     *   The record type ID of the field value.
     * @param \SimpleXMLElement $xml
     *   The XML element (<detail>) of the field value.
     * @return GenericFieldValue
     */
    protected function createGenericFieldValue(string $recordTypeID, \SimpleXMLElement $xml): GenericFieldValue
    {
        $baseFieldID = $xml['id'];
        $field = $this->findFieldByRecordTypeAndBaseField($recordTypeID, $baseFieldID);
        return new GenericFieldValue($field, (string) $xml);
    }

    /**
     * Create the term field value from the XML element.
     *
     * @param string $recordTypeID
     *   The record type ID of the field value.
     * @param \SimpleXMLElement $xml
     *   The XML element (<detail>) of the field value.
     * @return TermFieldValue|null
     *   The term field value instance, or null if the referenced term is not valid.
     */
    protected function createTermFieldValue(string $recordTypeID, \SimpleXMLElement $xml): ?TermFieldValue
    {
        $baseFieldID = $xml['id'];
        $field = $this->findFieldByRecordTypeAndBaseField($recordTypeID, $baseFieldID);
        $term = $this->findTerm($xml['termID']);
        if (isset($term)) {
            $fieldValue = new TermFieldValue($field, (string) $xml);
            $fieldValue->setTerm($term);
            return $fieldValue;
        }
        return null;
    }

    /**
     * Create the record pointer field value from the XML element.
     *
     * @param string $recordTypeID
     *   The record type ID of the field value.
     * @param \SimpleXMLElement $xml
     *   The XML element (<detail>) of the field value.
     * @return RecordPointerFieldValue|null
     *   The record pointer field value, or null if the referenced record is not valid.
     */
    protected function createRecordPointerFieldValue(string $recordTypeID, \SimpleXMLElement $xml): ?RecordPointerFieldValue
    {
        $baseFieldID = $xml['id'];
        $field = $this->findFieldByRecordTypeAndBaseField($recordTypeID, $baseFieldID);
        $record = $this->findRecord((string) $xml);
        if (isset($record)) {
            $fieldValue = new RecordPointerFieldValue($field, (string) $xml);
            $fieldValue->setTarget($record);
            return $fieldValue;
        }
        return null;
    }

    /**
     * Create the geo field value from the XML element.
     *
     * @param string $recordTypeID
     *   The record type ID of the field value.
     * @param \SimpleXMLElement $xml
     *   The XML element (<detail>) of the field value.
     * @return GeoFieldValue
     */
    protected function createGeoFieldValue(string $recordTypeID, \SimpleXMLElement $xml): GeoFieldValue
    {
        $baseFieldID = $xml['id'];
        $field = $this->findFieldByRecordTypeAndBaseField($recordTypeID, $baseFieldID);
        $fieldValue = new GeoFieldValue($field, (string) $xml->geo->wkt);
        $fieldValue->setType((string) $xml->geo->type);
        return $fieldValue;
    }

    /**
     * Create the file field value from the XML element.
     *
     * @param string $recordTypeID
     *   The record type ID of the field value.
     * @param \SimpleXMLElement $xml
     *   The XML element (<detail>) of the field value.
     * @return FileFieldValue
     */
    protected function createFileFieldValue(string $recordTypeID, \SimpleXMLElement $xml): FileFieldValue
    {
        $baseFieldID = $xml['id'];
        $field = $this->findFieldByRecordTypeAndBaseField($recordTypeID, $baseFieldID);
        $fieldValue = new FileFieldValue($field, (string) $xml->file->origName);
        if (!empty($xml->file->id)) {
            $fieldValue->setId((string) $xml->file->id);
        }
        if (!empty($xml->file->origName)) {
            $fieldValue->setFileName((string) $xml->file->origName);
        }
        if (!empty($xml->file->mimeType)) {
            $fieldValue->setMimeType((string) $xml->file->mimeType);
        }
        if (!empty($xml->file->date)) {
            $fieldValue->setDate((string) $xml->file->date);
        }
        if (!empty($xml->file->fileSize)) {
            $fieldValue->setFileSize((string) $xml->file->fileSize, (string) $xml->file->fileSize['units']);
        }
        if (!empty($xml->file->url)) {
            $fieldValue->setUrl((string) $xml->file->url);
        }
        return $fieldValue;
    }

    /**
     * Create the date field value from the XML element.
     *
     * @param string $recordTypeID
     *   The record type ID of the field value.
     * @param \SimpleXMLElement $xml
     *   The XML element (<detail>) of the field value.
     * @return DateFieldValue
     */
    protected function createDateFieldValue(string $recordTypeID, \SimpleXMLElement $xml): DateFieldValue
    {
        $baseFieldID = $xml['id'];
        $field = $this->findFieldByRecordTypeAndBaseField($recordTypeID, $baseFieldID);
        $fieldValue = new DateFieldValue($field, (string) $xml->raw);
        if (isset($xml->temporal)) {
            if ((string) $xml->temporal['type'] === 'Date Range') {
                $fieldValue->setIsRange(true);
                foreach ($xml->temporal->date as $date) {
                    $dateType = (string) $date['type'];
                    switch ($dateType) {
                        case 'TPQ':
                            $tpq = new DateFieldValue($field, (string) $date->raw);
                            $tpq = $this->setDateFieldValueElements($tpq, $date);
                            $fieldValue->setTPQ($tpq);
                            break;
                        case 'TAQ':
                            $taq = new DateFieldValue($field, (string) $date->raw);
                            $taq = $this->setDateFieldValueElements($taq, $date);
                            $fieldValue->setTAQ($taq);
                            break;
                        case 'PDB':
                            $pdb = new DateFieldValue($field, (string) $date->raw);
                            $pdb = $this->setDateFieldValueElements($pdb, $date);
                            $fieldValue->setPDB($pdb);
                            break;
                        case 'PDE':
                            $pde = new DateFieldValue($field, (string) $date->raw);
                            $pde = $this->setDateFieldValueElements($pde, $date);
                            $fieldValue->setPDE($pde);
                            break;
                    }
                }
            } elseif ((string) $xml->temporal['type'] === 'Simple Date') {
                foreach ($xml->temporal->date as $date) {
                    $dateType = (string) $date['type'];
                    if ($dateType === 'DAT') {
                        $fieldValue = $this->setDateFieldValueElements($fieldValue, $date);
                        break;
                    }
                }
            }
        } else {
            $fieldValue = $this->setDateFieldValueElements($fieldValue, $xml);
        }
        return $fieldValue;
    }

    /**
     * Set the year, month, day of a date field value from XML.
     *
     * @param DateFieldValue $fieldValue
     *   The field value instance.
     * @param \SimpleXMLElement $xml
     *   The XML element which contains the year, month, day as children.
     * @return DateFieldValue
     *   The filled field value instance.
     */
    protected function setDateFieldValueElements(DateFieldValue $fieldValue, \SimpleXMLElement $xml): DateFieldValue
    {
        if (isset($xml->year)) {
            $fieldValue->setYear((string) $xml->year);
        }
        if (isset($xml->month)) {
            $fieldValue->setMonth((string) $xml->month);
        }
        if (isset($xml->day)) {
            $fieldValue->setDay((string) $xml->day);
        }
        return $fieldValue;
    }

    /**
     * Create a Field instance for a non-standard field (not defined in the structure file).
     *
     * @param RecordType $recordType
     *   The record type instance.
     * @param BaseField $baseField
     *   The base field instance.
     * @return Field
     */
    protected function createNonStandardField(RecordType $recordType, BaseField $baseField): Field
    {
        return new Field([
            'rst_RecTypeID' => $recordType->getID(),
            'rst_DetailTypeID' => $baseField->getID(),
            'rst_DisplayName' => $baseField->getName(),
            'rst_DisplayHelpText' => $baseField->getDescription(),
        ]);
    }
}