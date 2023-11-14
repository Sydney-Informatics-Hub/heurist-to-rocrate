<?php

namespace UtilityCli\Heurist;

class TermFieldValue extends GenericFieldValue
{
    /**
     * @var Term $term
     *   The term instance of the field value.
     */
    protected Term $term;

    /**
     * Set the term for the term field value.
     *
     * @param Term $term
     * @return void
     */
    public function setTerm(Term $term): void
    {
        $this->term = $term;
    }

    /**
     * Get the term instance of the field value.
     *
     * @return Term
     */
    public function getTerm(): Term
    {
        return $this->term;
    }
}