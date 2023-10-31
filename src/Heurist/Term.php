<?php

namespace UtilityCli\Heurist;

class Term extends Entity
{
    const ATTR_LABEL = 'label';
    const ATTR_DESCRIPTION = 'description';
    const ATTR_CODE = 'code';

    /**
     * @var Term $parent
     *   The parent term.
     */
    protected Term $parent;

    /**
     * Get the ID of the term.
     *
     * @return string|null
     */
    public function getID(): ?string
    {
        return $this->getProperty('trm_ID');
    }

    /**
     * Get the label of the term.
     *
     * @return string|null
     */
    public function getLabel(): ?string
    {
        return $this->getProperty('trm_Label');
    }

    /**
     * Get the description of the term.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->getProperty('trm_Description');
    }

    /**
     * Get the parent term ID.
     *
     * @return string|null
     */
    public function getParentID(): ?string
    {
        return $this->getProperty('trm_ParentTermID');
    }

    /**
     * Get the code of the term.
     *
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->getProperty('trm_Code');
    }

    /**
     * Check if the term is a vocabulary.
     *
     * @return bool
     */
    public function isVocabulary(): bool
    {
        return empty($this->getParentID());
    }

    /**
     * Get the parent term.
     *
     * @return Term|null
     */
    public function getParent(): ?Term
    {
        return $this->parent;
    }

    /**
     * Set the parent term.
     *
     * @param Term $parent
     * @return void
     */
    public function setParent(Term $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * Create an identifier for an attribute of a term
     *
     * @param string $termID
     * @param string $attributeName
     * @return string
     */
    public static function createTermAttributeID(string $termID, string $attributeName): string
    {
        return $termID . ':' . $attributeName;
    }
}