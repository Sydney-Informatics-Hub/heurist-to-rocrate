<?php

namespace UtilityCli\Converter;

use UtilityCli\Heurist\BaseField;
use UtilityCli\Heurist\Field;
use UtilityCli\Heurist\Record;
use UtilityCli\Heurist\RecordType;
use UtilityCli\Heurist\Term;

class Utility
{
    const ENTITY_TYPE_RECORD_TYPE = 'record_type';
    const ENTITY_TYPE_FIELD = 'field';
    const ENTITY_TYPE_BASE_FIELD = 'base_field';
    const ENTITY_TYPE_TERM = 'term';
    const ENTITY_TYPE_RECORD = 'record';

    /**
     * Converts a given string to a standard JSON-LD class name (in PascalCase format).
     * Removes any content within parentheses.
     * Removes all non-alphanumeric characters, except for spaces.
     *
     * @param string $str The input string to be converted to PascalCase.
     * @return string Returns the converted string in PascalCase format.
     */
    public static function createClassName(string $str): string
    {
        // Remove content within parentheses and non-alphanumeric characters
        $str = preg_replace("/\([^)]+\)/", "", $str);  // Remove content within parentheses
        $words = preg_split('/\s+/', preg_replace("/[^A-Za-z0-9 ]/", ' ', $str));

        $processedWords = array();

        foreach ($words as $word) {
            $processedWords[] = ucfirst($word);
        }

        return implode('', $processedWords);
    }

    /**
     * Converts a given string to camelCase format.
     * Removes any content within parentheses.
     * Removes all non-alphanumeric characters, except for spaces.
     *
     * @param string $str The input string to be converted to PascalCase.
     * @return string Returns the converted string in camelCase format.
     */
    public static function createPropertyName(string $str): string
    {
        // Remove content within parentheses and non-alphanumeric characters
        $str = preg_replace("/\([^)]+\)/", "", $str);  // Remove content within parentheses
        $words = preg_split('/\s+/', preg_replace("/[^A-Za-z0-9 ]/", ' ', $str));

        $processedWords = array();

        foreach ($words as $index => $word) {
            // Convert first word to lower case, rest to upper case first letter
            if ($index == 0) {
                $processedWords[] = strtolower($word);
            } else {
                $processedWords[] = ucfirst($word);
            }
        }

        return implode('', $processedWords);
    }

    /**
     * Create an unique identifier based on entity type and ID for a Heurist entity.
     *
     * @param string $entityType
     *   The type of the entity. This should be one of the `ENTITY_TYPE_*` constants from this class.
     * @param string $entityID
     *   The ID of the entity.
     * @return string
     * @throws \Exception
     */
    public static function createEntityIdentifierFromID(string $entityType, string $entityID): string
    {
        switch ($entityType) {
            case self::ENTITY_TYPE_RECORD_TYPE:
                return 'r' . $entityID;
            case self::ENTITY_TYPE_BASE_FIELD:
                return 'b' . $entityID;
            case self::ENTITY_TYPE_FIELD:
                return 'f' . $entityID;
            case self::ENTITY_TYPE_TERM:
                return 't' . $entityID;
            case self::ENTITY_TYPE_RECORD:
                return 'c' . $entityID;
            default:
                throw new \Exception('Invalid entity type to create the identifier');
        }
    }

    /**
     * Create an unique identifier for a Heurist entity.
     *
     * @param RecordType|BaseField|Field|Term|Record $entity
     * @return string
     * @throws \Exception
     */
    public static function createEntityIdentifier(mixed $entity): string
    {
        if ($entity instanceof RecordType) {
            return self::createEntityIdentifierFromID(self::ENTITY_TYPE_RECORD_TYPE, $entity->getID());
        } elseif ($entity instanceof BaseField) {
            return self::createEntityIdentifierFromID(self::ENTITY_TYPE_BASE_FIELD, $entity->getID());
        } elseif ($entity instanceof Field) {
            return self::createEntityIdentifierFromID(self::ENTITY_TYPE_FIELD, $entity->getID());
        } elseif ($entity instanceof Term) {
            return self::createEntityIdentifierFromID(self::ENTITY_TYPE_TERM, $entity->getID());
        } elseif ($entity instanceof Record) {
            return self::createEntityIdentifierFromID(self::ENTITY_TYPE_RECORD, $entity->getID());
        } else {
            throw new \Exception('Invalid entity type to create the identifier');
        }
    }
}
