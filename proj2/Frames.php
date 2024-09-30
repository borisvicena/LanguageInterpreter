<?php
/**
 * IPP - Student project
 * @author Boris Vícena <xvicen10>
 */

namespace IPP\Student;

use IPP\Student\FrameAccessError;
use IPP\Student\SemanticError;
use IPP\Student\VariableAccessError;
use IPP\Student\StringOperationError;

/**
 * Class that is used for storing variables in frames
 */
class Frames {

    /** @var array<string, array{value: mixed, type: ?string}> */
    public array $globalFrame = [];

    /** @var array<string, array{value: mixed, type: ?string}>|null */
    public ?array $localFrame = null;

    /** @var array<string, array{value: mixed, type: ?string}>|null */
    public ?array $tempFrame = null;

    /** @var array<mixed> */
    public array $stack = [];


    // Function to add a variable to the frame
    public function add(string $argument): void {
        // Identify the frame
        $frame = &$this->identifyFrame($argument);
        
        // Check if the frame is initialized
        if ($frame === null) {
            throw new FrameAccessError("Frame is uninitialized");
        }
        
        // Check if the variable already exists in the frame
        if (array_key_exists($argument, $frame)) {
            throw new SemanticError("Variable '{$argument}' already exists in the frame");
        }
    
        // Add the variable to the frame with a null value
        $frame[$argument] = array("value" => null, "type" => null);
    }

    /**
     * @param string $var
     * @param array<mixed, mixed> $value
     * @param bool $fromDataStack
     */
    // Function to set the value of a variable in the frame
    public function set($var, $value, $fromDataStack): void {
        // Identify the frame
        $frame = &$this->identifyFrame($var);

        // Check if the frame is initialized
        if ($frame === null) {
            throw new FrameAccessError("Frame is uninitialized");
        }

        // Check if the variable exists in the frame
        if (!array_key_exists($var, $frame) && !$fromDataStack) {
            throw new VariableAccessError("Variable '{$var}' does not exist");
        }

        // Get the type and value of the variable
        $setType = $value['type'];
        $setValue = $value['value'];

        // Set the value of the variable based on its type
        switch ($setType) {
            case "int":
                if (is_numeric($setValue)) {
                    $setValue = intval($setValue);
                } else {
                    $setValue = '';
                }
                break;
            case "bool":
                if (is_bool($setValue)) {
                    $setValue = $setValue ? "true" : "false";
                } else {
                    $setValue = ($setValue === "true") ? "true" : "false";
                }
                break;
            case "string":
                $setValue = strval($this->parseStringValue($setValue));
                break;
            case "nil":
                $setValue = "";
                break;
        }

        // Set the value of the variable in the frame
        $frame[$var] = array("value" => $setValue, "type" => $setType);
    }

    // Function to get the value or type of a variable in the frame
    public function get(string $name, string $switcher): mixed {
        // Identify the frame
        $frame = &$this->identifyFrame($name);

        // Check if the frame is initialized
        if ($frame === null) {
            throw new FrameAccessError("Frame is uninitialized");
        }

        // Check if the variable exists in the frame
        if (!array_key_exists($name, $frame)) {
            throw new VariableAccessError("Variable '{$name}' does not exist");
        }

        // Get the value variable from the frame
        $result = $frame[$name];

        // Return the value or type of the variable based on the switcher
        if ($switcher === "value") {
            return $result["value"];
        }
        return $result["type"];
    }

    // Function to push a new frame onto the stack
    public function pushFrame(): void {
        // Check if the temporary frame is initialized
        if ($this->tempFrame === null){
            throw new FrameAccessError("Temporary frame is uninitialized");
        }

        // Scope the local frame
        $scopedFrame = [];
        foreach ($this->tempFrame as $name => $value) {
            $scopedName = str_replace("TF@", "LF@", $name);
            $scopedFrame[$scopedName] = $value;
        }
        
        // Push the local frame onto the stack
        $this->localFrame = $scopedFrame;
        array_push($this->stack, $this->localFrame);
        $this->tempFrame = null;
    }

    // Function to pop a frame from the stack
    public function popFrame(): void {
        // Check if the stack is empty
        if (empty($this->stack)) {
            throw new FrameAccessError("Cannot pop from empty stack");
        }

        // Pop the local frame from the stack
        $scopedFrame = array_pop($this->stack);
        foreach ($scopedFrame as $name => $value) {
            $newName = str_replace("LF@", "TF@", $name);
            $this->tempFrame[$newName] = $value;
        }

        // Set the local frame to the top of the stack
        if (!empty($this->stack)) {
            $this->localFrame = &$this->stack[count($this->stack) - 1];
        } else {
            $scopedFrame = [];
            foreach ($this->localFrame as $name => $value) {
                $scopedName = str_replace("LF@", "TF@", $name);
                $scopedFrame[$scopedName] = $value;
            }
            $this->tempFrame = $scopedFrame;
        }
    }

    /**
     * @return array<string, array{value: mixed, type: string|null}>
     */
    // Function to identify the frame based on the variable name
    public function &identifyFrame(string $name): array|null {
        if (strpos($name, "GF@") === 0) {
            return $this->globalFrame;
        } elseif (strpos($name, "LF@") === 0) {
            return $this->localFrame;
        } elseif (strpos($name, "TF@") === 0) {
            return $this->tempFrame;
        } else {
            throw new StringOperationError("Invalid frame prefix");
        }
    }

    /**
     * @param string $value
     */
    // Function to parse escape sequences in a string
    public function parseStringValue($value): string|null {
        // Use regular expression to find escape sequences and replace them with corresponding characters
        $pattern = '/\\\\([0-9]{3})/';
        return preg_replace_callback($pattern, function($matches) {
            // Convert matched escape sequence to character
            return chr(intval($matches[1]));
        }, $value);
    }

    // Function to print the frames
    public function printFrames(): void {
        echo "Global Frame:\n";
        $this->printFrame($this->globalFrame);
        echo "\nLocal Frame:\n";
        $this->printFrame($this->localFrame);
        echo "\nTemporary Frame:\n";
        $this->printFrame($this->tempFrame);
        echo "--------------------------\n";
    }

    /**
     * @param array<string, array{value: mixed, type: ?string}>|null $frame
     */
    // Function to print a frame
    private function printFrame($frame): void {
        // Check if the frame is initialized
        if ($frame === null) {
            echo "Frame is uninitialized\n";
            return;
        }

        // Print the name, value, and type of each variable in the frame
        foreach ($frame as $name => $valueWithType) {
            $value = $valueWithType['value'];
            $type = $valueWithType['type'];
    
            echo " - Name: $name, Value: ";
            echo $value === null ? "nil" : $value;
            echo ", Type: ";
            echo $type === null ? "nil" : $type;
            echo "\n";
        }
    }
}