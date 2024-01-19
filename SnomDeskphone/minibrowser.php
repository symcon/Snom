<?php

/**
 * Utilities for Snom XML minibrowser https://service.snom.com/display/wiki/XML+Definitions
 */
class SnomXmlMinibrowser
{
    public static function getSnomIPPhoneTextElement(array $xmlAttributes, int $timeout = 1): string
    {
        $ledValue = ($xmlAttributes["color"] === "none") ? "Off" : "On";

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $xmlRoot = $xml->appendChild($xml->createElement("SnomIPPhoneText"));

        //text tag
        $textToDisplay = $xmlAttributes["variableId"] . " = " . $xmlAttributes["value"];
        $xmlRoot->appendChild($xml->createElement('Text', $textToDisplay));

        //led tag
        $led = $xml->createElement('LED', $ledValue);
        $ledNumber = $xml->createAttribute('number');
        $ledNumber->value = $xmlAttributes["ledNo"];
        $led->appendChild($ledNumber);
        $ledColor = $xml->createAttribute('color');
        $ledColor->value = $xmlAttributes["color"];
        $led->appendChild($ledColor);
        $xmlRoot->appendChild($led);

        //fetch tag
        $fetch = $xml->createElement('fetch', 'snom://mb_exit');
        $fetchTimeout = $xml->createAttribute('mil');
        $timeout = 1;
        $fetchTimeout->value = $timeout;
        $fetch->appendChild($fetchTimeout);
        $xmlRoot->appendChild($fetch);

        $xml->format_output = TRUE;

        return $xml->saveXML();
    }

    public static function executeXml(string $xml): void
    {
        header("Content-Type: text/xml");
        echo $xml;
    }
}