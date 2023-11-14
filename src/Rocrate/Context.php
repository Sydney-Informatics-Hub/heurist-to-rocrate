<?php

namespace UtilityCli\Rocrate;

use UtilityCli\Helper\Collection;

/**
 * The context of the RO-Crate metadata.
 */
class Context implements Jsonify, ModelInterface
{
    /**
     * The context data.
     *
     * The data array will omit the default RO-Crate context 'https://w3id.org/ro/crate/1.1/context' and will
     * only contain the custom context items.
     *
     * @var array
     */
    protected array $data;

    /**
     * Constructor.
     *
     */
    public function __construct()
    {
        $this->data = [];
    }

    /**
     * Add a context item.
     *
     * @param string $name
     * @param string $value
     */
    public function add(string $name, string $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * Remove a context item.
     *
     * @param string $name
     */
    public function remove(string $name): void
    {
        unset($this->data[$name]);
    }

    /**
     * Check whether a context item exists.
     *
     * @param string $name
     * @return bool
     */
    public function hasContext(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Get a context item value.
     *
     * @param string $name
     * @return string
     */
    public function get(string $name): ?string
    {
        if ($this->hasContext($name)) {
            return $this->data[$name];
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function toArray(): array
    {
        if (empty($this->data)) {
            return ['@context' => 'https://w3id.org/ro/crate/1.1/context'];
        } else {
            $context = ['https://w3id.org/ro/crate/1.1/context', $this->data];
            return ['@context' => $context];
        }
    }

    /**
     * @inheritdoc
     */
    public static function createFromArray(array $data): self
    {
        $context = new Context();
        // Unpack the context data.
        foreach ($data as $item) {
            if ($item !== 'https://w3id.org/ro/crate/1.1/context') {
                if (Collection::isAssociativeArray($item)) {
                    foreach ($item as $name => $value) {
                        $context->add($name, $value);
                    }
                }
            }
        }
        return $context;
    }
}
