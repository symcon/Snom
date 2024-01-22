<?php

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

/**
 * Builds a Snom minibrowser page with the requested parameters and the given main tag
 * as root element https://service.snom.com/display/wiki/Main+Tags.
 * Root element defaults to main tag SnomIPPhoneText.
 */
class SnomXmlMinibrowser
{
    protected string $mainTag;
    protected DOMDocument $xmlDocument;
    protected array $parameters;
    public string $minibrowser;
    public int $timeout;

    function __construct(array $xmlParameters, $mainTag = SNOM_IP_PHONE_TEXT)
    {
        $this->mainTag = $mainTag;
        $this->parameters = $xmlParameters;
        $this->timeout = 1;
        $this->xmlDocument = $this->getXmlDocument();
        $this->minibrowser = $this->getMinibrowserPage();
    }

    public function getXmlDocument(): DOMDocument
    {
        $xmlDocument = new DOMDocument('1.0', 'UTF-8');
        $xmlDocument->formatOutput = true;

        return $xmlDocument;
    }

    /**
     * Returns a Snom minibrowser page
     */
    public function getMinibrowserPage(): string
    {
        $xmlRoot = $this->getRootElement();
        $this->appendMinibrowserTag($xmlRoot, TEXT);
        $this->appendMinibrowserTag($xmlRoot, LED);
        $this->appendMinibrowserTag($xmlRoot, FETCH);

        return $this->xmlDocument->saveXML();
    }

    public function getRootElement(): DOMNode
    {
        $xmlElement = $this->xmlDocument->createElement($this->mainTag);
        return $this->xmlDocument->appendChild($xmlElement);
    }

    /**
     * Appends an Snom XML minibrowser tag to the given XML Root node https://service.snom.com/display/wiki/Main+Subtags
     */
    public function appendMinibrowserTag(DOMNode $xmlRoot, $tag): void
    {
        $minibrowserTag = new DOMNode;

        switch ($tag) {
            case TEXT:
                $textToDisplay = $this->parameters["variableId"] . " = " . $this->parameters["value"];
                $minibrowserTag = $this->xmlDocument->createElement($tag, $textToDisplay);
                break;
            case LED:
                $ledValue = ($this->parameters["color"] === "none") ? "Off" : "On";
                $minibrowserTag = $this->xmlDocument->createElement($tag, $ledValue);
                $this->appendMinibrowserTagAttribute($xmlRoot, $minibrowserTag, NUMBER);
                $this->appendMinibrowserTagAttribute($xmlRoot, $minibrowserTag, COLOR);
                break;
            case FETCH:
                $minibrowserTag = $this->xmlDocument->createElement($tag, 'snom://mb_exit');
                $this->appendMinibrowserTagAttribute($xmlRoot, $minibrowserTag, MIL);
                break;
            default:
                echo "Not valid Snom tag $tag";
        }

        $xmlRoot->appendChild($minibrowserTag);
    }

    public function appendMinibrowserTagAttribute(DOMNode $xmlRoot, DOMNode $minibrowserTag, string $attribute): void
    {
        $tagAttribute = new DOMAttr($attribute);

        switch ($attribute) {
            case NUMBER:
                $tagAttribute = $this->xmlDocument->createAttribute($attribute);
                $tagAttribute->value = $this->parameters["ledNo"];
                break;
            case COLOR:
                $tagAttribute = $this->xmlDocument->createAttribute($attribute);
                $tagAttribute->value = $this->parameters["color"];
                break;
            case MIL:
                $tagAttribute = $this->xmlDocument->createAttribute($attribute);
                $tagAttribute->value = $this->timeout;
                break;
            default:
                echo "Not valid Snom attribute $attribute";
        }

        $minibrowserTag->appendChild($tagAttribute);
    }

    public function executeMinibrowser(): void
    {
        header("Content-Type: text/xml");
        echo $this->minibrowser;
    }
}