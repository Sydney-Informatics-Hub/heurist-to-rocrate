<?php

namespace UtilityCli\Rocrate;

class PropertyDefinition extends Entity
{
    /**
     * @inheritdoc
     */
    public function __construct(?string $id = null)
    {
        parent::__construct('rdf:Property', $id);
    }

    /**
     * Get the property name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->get('rdfs:label');
    }

    /**
     * Set the property name.
     *
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->set('rdfs:label', $name);
    }

    /**
     * Get the property description.
     */
    public function getDescription(): string
    {
        return $this->get('rdfs:comment');
    }

    /**
     * Set the property description.
     */
    public function setDescription(string $description): void
    {
        $this->set('rdfs:comment', $description);
    }

    /**
     * Get the property domain classes.
     *
     * @return ClassDefinition[]|null
     */
    public function getDomains(): ?array
    {
        $domain = $this->get('domainIncludes');
        if ($domain instanceof ClassDefinition) {
            return [$domain];
        } elseif (is_array($domain)) {
            return $domain;
        } else {
            return null;
        }
    }

    /**
     * Add a domain class to the property.
     *
     * @param ClassDefinition $domain
     */
    public function addDomain(ClassDefinition $domain): void
    {
        $this->append('domainIncludes', $domain);
    }

    /**
     * Add a domain from the RO-Crate ID.
     *
     * This is useful when adding an external domain (e.g. classes from schema.org).
     *
     * @param string $id
     * @return void
     */
    public function addDomainFromID(string $id): void
    {
        $this->append('domainIncludes', ['@id' => $id]);
    }

    /**
     * Set the domain classes of the property.
     *
     * @param ClassDefinition[] $domains
     */
    public function setDomains(array $domains): void
    {
        $this->set('domainIncludes', $domains);
    }

    /**
     * Get the property range classes.
     *
     * @return ClassDefinition[]|null
     */
    public function getRanges(): ?array
    {
        $range = $this->get('rangeIncludes');
        if ($range instanceof ClassDefinition) {
            return [$range];
        } elseif (is_array($range)) {
            return $range;
        } else {
            return null;
        }
    }

    /**
     * Add a range class to the property.
     *
     * @param ClassDefinition $range
     */
    public function addRange(ClassDefinition $range): void
    {
        $this->append('rangeIncludes', $range);
    }

    /**
     * Add a range from the RO-Crate ID.
     *
     * This is useful when adding an external range (e.g. classes from schema.org).
     *
     * @param string $id
     * @return void
     */
    public function addRangeFromID(string $id): void
    {
        $this->append('rangeIncludes', ['@id' => $id]);
    }

    /**
     * Set the range classes of the property.
     *
     * @param ClassDefinition[] $ranges
     */
    public function setRanges(array $ranges): void
    {
        $this->set('rangeIncludes', $ranges);
    }
}