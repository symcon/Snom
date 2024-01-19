<?php

/**
 * Utilities for Snom XML minibrowser https://service.snom.com/display/wiki/XML+Minibrowser
 */
class SnomXmlMinibrowser
{
    /**
     * Returns an XML with an SnomIPPhonetext lement as content https://service.snom.com/display/wiki/SnomIPPhoneText
     */

    // main tags
    const SNOM_IP_PHONE_TEXT = "SnomIPPhoneText";

    // main subtags
    const LED = "LED";
    const FETCH = "fetch";
    const TEXT = "Text";

    // main attributes
    const NUMBER = "number";
    const COLOR = "color";
    const MIL = "mil";

    public static function getSnomIPPhoneTextElement(array $xmlAttributes, int $timeout = 1): string
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // snomIpPhoneText tag
        $ipPhoneTextElement = $xml->createElement(self::SNOM_IP_PHONE_TEXT);
        $xmlRoot = $xml->appendChild($ipPhoneTextElement);

        //text tag
        $textToDisplay = $xmlAttributes["variableId"] . " = " . $xmlAttributes["value"];
        $textElement = $xml->createElement(self::TEXT, $textToDisplay);
        $xmlRoot->appendChild($textElement);


        //led tag
        $ledValue = ($xmlAttributes["color"] === "none") ? "Off" : "On";
        $ledElement = $xml->createElement(self::LED, $ledValue);

        $ledNumber = $xml->createAttribute(self::NUMBER);
        $ledNumber->value = $xmlAttributes["ledNo"];
        $ledElement->appendChild($ledNumber);

        $ledColor = $xml->createAttribute(self::COLOR);
        $ledColor->value = $xmlAttributes["color"];
        $ledElement->appendChild($ledColor);

        $xmlRoot->appendChild($ledElement);

        //fetch tag
        $fetchElement = $xml->createElement(self::FETCH, 'snom://mb_exit');

        $fetchTimeout = $xml->createAttribute(self::MIL);
        $fetchTimeout->value = $timeout;
        $fetchElement->appendChild($fetchTimeout);

        $xmlRoot->appendChild($fetchElement);

        $xml->format_output = TRUE;

        return $xml->saveXML();
    }

    public static function executeXml(string $xml): void
    {
        header("Content-Type: text/xml");
        echo $xml;
    }
}