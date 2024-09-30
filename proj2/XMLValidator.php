<?php
/**
 * IPP - Student project
 * @author Boris Vícena <xvicen10>
 */

namespace IPP\Student;

use DOMDocument;
use IPP\Student\InvalidStructure;

/**
 * Class that is used for storing labels and jumping to them
 */
class XMLValidator {
    private DOMDocument $dom;

    // Constructor
    public function __construct(DOMDocument $dom) {
        $this->dom = $dom;
    }

    // Function to validate the XML
    public function validate(): void {
        $rootElement = $this->dom->documentElement;
        if ($rootElement === null) {
            throw new InvalidStructure("Empty document: no root element found");
        }
        if ($rootElement->tagName !== 'program') {
            throw new InvalidStructure("Root element is not 'program'");
        }
        $instructions = $rootElement->childNodes;

        foreach ($instructions as $instruction) {
            if ($instruction->nodeType === XML_ELEMENT_NODE) {
                if ($instruction->nodeName !== 'instruction') {
                    throw new InvalidStructure("Invalid element '{$instruction->nodeName}' in program");
                }
            }
        }

        $instructions = $rootElement->getElementsByTagName('instruction');
    
        $orderedInstructions = [];
        foreach ($instructions as $instruction) {
            if ($instruction->nodeType === XML_ELEMENT_NODE) {
                if ($instruction->nodeName !== 'instruction') {
                    throw new InvalidStructure("Invalid element '{$instruction->nodeName}' in program");
                }
                $order = intval($instruction->getAttribute('order'));
                if ($order === 0) {
                    throw new InvalidStructure("Missing order attribute in instruction");
                }
                if ($order < 1) {
                    throw new InvalidStructure("Invalid order attribute in instruction");
                }
                if (isset($orderedInstructions[$order])) {
                    throw new InvalidStructure("Duplicate order attribute in instruction");
                }
                $orderedInstructions[$order] = $instruction;
            }
        }
    }    
}