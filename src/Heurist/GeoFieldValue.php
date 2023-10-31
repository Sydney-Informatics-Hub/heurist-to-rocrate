<?php

namespace UtilityCli\Heurist;

class GeoFieldValue extends GenericFieldValue
{
    const GEO_TYPE_POINT = 'point';

    const GEO_TYPE_PATH = 'path';

    /**
     * @var string $type
     *   The Geo type.
     */
    protected string $type;

    /**
     * Get the geo type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set the geo type.
     *
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * Get the coordinates string from the raw WKT value.
     *
     * @return string|null
     *   The coordinates string. Or null if it's not a valid WKT format.
     */
    public function getCoordinatesString(): ?string
    {
        if (preg_match('/[a-zA-Z]+\s*\((.+)\)/', $this->getValue(), $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get the long/lat coordinates of a point.
     *
     * @return array|null
     *   The long/lat coordinates of a point. Or null if it's not a valid point type.
     */
    public function getPointCoordinates(): ?array
    {
        if (preg_match('/point\(([\d\-\.]+)\s([\d\-\.]+)\)/i', $this->getValue(), $matches)) {
            return [floatval($matches[2]), floatval($matches[1])];
        }
        return null;
    }
}