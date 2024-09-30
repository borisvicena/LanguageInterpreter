<?php
/**
 * IPP - Student project
 * @author Boris Vícena <xvicen10>
 */

namespace IPP\Student;
use IPP\Student\SemanticError;

/**
 * Class that is used for storing labels and jumping to them
 */
class Labels {    
    /** @var mixed[] */
    private static array $labels = [];

    // Function to add a label to the labels array
    public static function add(string $name, mixed $instruction): void {
        // Check if the label already exists
        if (array_key_exists($name, self::$labels)) {
            throw new SemanticError("Label '{$name}' already exists");
        }

        // Add the label to the labels array
        self::$labels[$name] = $instruction;
    }

    // Function to print all labels
    public static function printLabels(): void {
        echo "Labels:\n";
        foreach (array_keys(self::$labels) as $label) {
            echo "- $label\n";
        }
    }

    // Function to find the index of a label in the instruction array
    public static function findLabelIndex(string $labelName, mixed $instructions): int|false {
        // Loop through the instructions
        foreach ($instructions as $index => $inst) {
            // Check if the instruction is a label
            if ($inst->getAttribute('opcode') === "LABEL") {
                // Get the name of the label
                $arg1 = $inst->getElementsByTagName('arg1')->item(0)->nodeValue;
                if ($arg1 === $labelName) {
                    // Return the index of the label
                    return $index;
                }
            }
        }

        // Return false if the label was not found
        return false;
    }

    // Function to resolve the labels
    public static function resolveLabels(mixed $dom): mixed {
        // Get all instructions
        $instructions = $dom->getElementsByTagName('instruction');
        
        // Loop through the instructions
        $labels = [];
        foreach ($instructions as $instruction) {
            $opcode = $instruction->getAttribute('opcode');
            if ($opcode === "LABEL") {
                $labelName = $instruction->getElementsByTagName('arg1')->item(0)->nodeValue;
                // Check if the label already exists
                if (array_key_exists($labelName, $labels)) {
                    throw new SemanticError("Duplicate label '{$labelName}'");
                }
                // Add the label to the labels array
                $labels[$labelName] = $instruction;
            }
        }

        // Return the labels
        return $labels;
    }
}