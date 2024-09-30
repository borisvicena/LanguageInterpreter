<?php
/**
 * IPP - Student project
 * @author Boris Vícena <xvicen10>
 */

namespace IPP\Student;

use DOMDocument;
use IPP\Core\Interface\InputReader;
use IPP\Core\Interface\OutputWriter;
use IPP\Student\InvalidStructure;

/**
 * Class that is used for running the instructions
 */
class Runner {    
    private Instructions $instructions;
    private InputReader $input;
    private OutputWriter $stdout;
    private OutputWriter $stderr;
    private DOMDocument $dom;
    private int $instructionPointer = 0;

    // Constructor
    public function __construct(DOMDocument $dom, InputReader $input, OutputWriter $stdout, OutputWriter $stderr) {
        $this->dom = $dom;
        $this->input = $input;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    // Prepare the instructions
    public function prepare(): void {
        $this->instructions = new Instructions($this->input, $this->stdout, $this->stderr, $this->instructionPointer);
    }

    // Load and sort instructions from the XML
    public function load(): mixed {
        // Get root element
        $rootElement = $this->dom->documentElement;

        // Get instructions
        if ($rootElement === null) {
            throw new InvalidStructure("Root element not found");
        }
        $instructions = $rootElement->getElementsByTagName('instruction');

        // Sort instructions by order attribute
        $orderedInstructions = [];
        foreach ($instructions as $instruction) {
            if ($instruction->nodeType === XML_ELEMENT_NODE && $instruction->nodeName === 'instruction') {
                $order = intval($instruction->getAttribute('order'));
                $orderedInstructions[$order] = $instruction;
            }
        }
        ksort($orderedInstructions);

        // Return ordered instructions
        return array_values($orderedInstructions);
    }

    // Pre-run pass for label processing
    public function prerun(): void {
        $labels = Labels::resolveLabels($this->dom);

        foreach($labels as $labelName => $instruction) {
            Labels::add($labelName, $instruction);
        }
    }

    // Run the instructions
    public function run(mixed $instructions): void {
        // Prepare instructions
        $this->prepare();
        
        // Run instructions
        while ($this->instructionPointer != count($instructions)) {
            // Get and run instruction
            $instruction = $instructions[$this->instructionPointer];
            $this->instructions->run($instruction, $instructions);
        }
    }
}