<?php

namespace UtilityCli\Helper;

/**
 * XML helper.
 */
class XML
{
    /**
     * Parse XML from a string.
     *
     * @param string $xmlString
     * @return \SimpleXMLElement
     * @throws \Exception
     */
    public static function parseFromString(string $xmlString): \SimpleXMLElement
    {
        // Enable user error handling for XML loading
        libxml_use_internal_errors(true);
        $xmlObject = simplexml_load_string($xmlString);

        if (!$xmlObject) {
            $message = "Failed to load XML. Errors:\n";
            foreach (libxml_get_errors() as $error) {
                $message .= $error->message . "\n";
            }
            throw new \Exception($message);
        }
        return $xmlObject;
    }

    /**
     * Parse XML from a file.
     *
     * @param string $xmlFile
     *   The path to the XML file.
     * @return \SimpleXMLElement
     * @throws \Exception
     */
    public static function parseFromFile(string $xmlFile): \SimpleXMLElement
    {
        $xmlString = file_get_contents($xmlFile);
        return self::parseFromString($xmlString);
    }
}