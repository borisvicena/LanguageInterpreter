<?php
/**
 * IPP - Student project
 * @author Boris Vícena <xvicen10>
 */

namespace IPP\Student;

use IPP\Core\Exception\NotImplementedException;
use IPP\Core\Interface\InputReader;
use IPP\Core\Interface\OutputWriter;
use IPP\Student\FrameAccessError;
use IPP\Student\InvalidStructure;
use IPP\Student\OperandStructure;
use IPP\Student\OperandValue;
use IPP\Student\SemanticError;
use IPP\Student\StringOperationError;
use IPP\Student\ValueError;
use IPP\Student\VariableAccessError;


/**
 * Class that is used for interpreting the instructions
 */
class Instructions {
    public mixed $dataStack;
    public mixed $callStack = [];
    private int $instructionPointer;
    private InputReader $input;
    private OutputWriter $stdout;
    private OutputWriter $stderr;
    private Frames $frames;

    // Constructor
    public function __construct(InputReader $input, OutputWriter $stdout, OutputWriter $stderr, int &$instructionPointer) {
        $this->input = $input;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->instructionPointer = &$instructionPointer;
        $this->frames = new Frames();
        $this->dataStack = [];
    }

    // Function to run current instruction
    public function run(mixed $instruction, mixed $instructions): void {
        // Get the opcode and arguments from the instruction
        $opcode = $this->getOpcode($instruction);
        $args = $this->getArgs($instruction);

        switch(strtoupper($opcode)) {
            // Práca s pamäťovými rámcami, volanie funkcií
            case "MOVE";
                $this->opcodeMOVE($args);
                break;
            case "CREATEFRAME":
                $this->opcodeCREATEFRAME($args);
                break;
            case "PUSHFRAME":
                $this->opcodePUSHFRAME($args);
                break;
            case "POPFRAME":
                $this->opcodePOPFRAME($args);
                break;
            case "DEFVAR":
                $this->opcodeDEFVAR($args);
                break;
            case "CALL":
                $this->opcodeCALL($args, $instructions);
                break;
            case "RETURN":
                $this->opcodeRETURN($args);
                break;
            // Práca s datovým zásobníkom
            case "PUSHS":
                $this->opcodePUSHS($args);
                break;
            case "POPS":
                $this->opcodePOPS($args);
                break;
            // Aritmetické, relačné, boolovské a konverzné inštrukcie
            case "ADD":
                $this->opcodeADD($args);
                break;
            case "SUB":
                $this->opcodeSUB($args);
                break;
            case "MUL":
                $this->opcodeMUL($args);
                break;
            case "IDIV":
                $this->opcodeIDIV($args);
                break;
            case "LT":
                $this->opcodeLT($args);
                break;
            case "GT":
                $this->opcodeGT($args);
                break;
            case "EQ":
                $this->opcodeEQ($args);
                break;
            case "AND":
                $this->opcodeAND($args);
                break;
            case "OR":
                $this->opcodeOR($args);
                break;
            case "NOT":
                $this->opcodeNOT($args);
                break;
            case "INT2CHAR":
                $this->opcodeINT2CHAR($args);
                break;
            case "STRI2INT":
                $this->opcodeSTRI2INT($args);
                break;
            // Vstupno-výstupné inštrukcie
            case "READ":
                $this->opcodeREAD($args);
                break;
            case "WRITE":
                $this->opcodeWRITE($args);
                break;
            // Práca s reťazcami
            case "CONCAT":
                $this->opcodeCONCAT($args);
                break;
            case "STRLEN":
                $this->opcodeSTRLEN($args);
                break;
            case "GETCHAR":
                $this->opcodeGETCHAR($args);
                break;
            case "SETCHAR":
                $this->opcodeSETCHAR($args);
                break;
            // Práca s typmi
            case "TYPE":
                $this->opcodeTYPE($args);
                break;
            // Inštrukcie pre riadenie toku programu
            case "LABEL":
                $this->opcodeLABEL($args);
                break;
            case "JUMP":
                $this->opcodeJUMP($args, $instructions);
                break;
            case "JUMPIFEQ":
                $this->opcodeJUMPIFEQ($args, $instructions);
                break;
            case "JUMPIFNEQ":
                $this->opcodeJUMPIFNEQ($args, $instructions);
                break;
            case "EXIT":
                $this->opcodeEXIT($args);
                break;
            // Ladiace inštrukcie
            case "DPRINT":
                $this->opcodeDPRINT($args);
                break;
            case "BREAK":
                $this->opcodeBREAK($args);
                break;
            default:
                throw new InvalidStructure("Invalid opcode '{$opcode}'");
        }
    }

    /**************************************************************************
     * Práca s pamäťovými rámcami, volanie funkcií
     */

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeMOVE($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 2) {
            throw new InvalidStructure("Invalid number of arguments for MOVE");
        }

        // Get the variable name and symbol from the instruction arguments
        $var = $args[0]['value'];
        $symb = $this->resolveValue($args[1]);
        $symbType = $this->getType($args[1]);

        // Construct the result value
        $result = array("value" => $symb, "type" => $symbType);
        
        // Set the result value into the variable
        $this->frames->set($var, $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeCREATEFRAME($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 0) {
            throw new InvalidStructure("Invalid number of arguments for CREATEFRAME");
        }

        // Check if the temporary frame is not empty
        if ($this->frames->tempFrame !== null) {
            $this->frames->tempFrame = null;
        }

        // Create a new temporary frame
        $this->frames->tempFrame = [];

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodePUSHFRAME($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 0) {
            throw new InvalidStructure("Invalid number of arguments for PUSHFRAME");
        }

        // Push the temporary frame onto the stack of frames
        $this->frames->pushFrame();

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodePOPFRAME($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 0) {
            throw new InvalidStructure("Invalid number of arguments for POPFRAME");
        }

        // Pop the frame from the stack of frames
        $this->frames->popFrame();

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeDEFVAR($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 1) {
            throw new InvalidStructure("Invalid number of arguments for DEFVAR");
        }

        // Create a new variable in the current frame
        $this->frames->add($args[0]['value']);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     * @param mixed $instructions
     */
    private function opcodeCALL($args, $instructions): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 1) {
            throw new InvalidStructure("Invalid number of arguments for CALL");
        }

        // Retrieve the label name from the instruction arguments
        $labelName = $args[0]['value'];

        // Get the current instruction pointer value and push it onto the call stack
        $this->callStack[] = $this->instructionPointer + 1;

        // Find the index of the label in the array of instructions
        $labelIndex = Labels::findLabelIndex($labelName, $instructions);
        if ($labelIndex === false) {
            throw new SemanticError("Label '{$labelName}' does not exist");
        }

        // Update the instruction pointer to the index of the label
        $this->instructionPointer = $labelIndex;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeRETURN($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 0) {
            throw new InvalidStructure("Invalid number of arguments for RETURN");
        }

        // Check if the call stack is empty
        if (empty($this->callStack)) {
            throw new ValueError("Call stack is empty");
        }

        // Pop the return address from the call stack
        $returnAddress = array_pop($this->callStack);
        if ($returnAddress === null) {
            throw new OperandValue("Cannot pop from empty stack");
        }

        // Update the instruction pointer to the return address
        $this->instructionPointer = $returnAddress;
    }

    /**************************************************************************
     * Práca s datovým zásobníkom
     */

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodePUSHS($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 1) {
            throw new InvalidStructure("Invalid number of arguments for PUSHS");
        }

        // Get the symbol from the instruction arguments
        $symb = $args[0];
    
        // Get the value of the symbol
        $value = $this->resolveValue($symb);
        $type = '';

        // Determine the type of the value
        if (is_numeric($value)) {
            $type = 'int';
        } elseif (is_string($value)) {
            if (($value == "true") || ($value == "false")) {
                $type = 'bool';
            } else {
                $type = 'string';
            }
        } elseif (is_bool($value)) {
            $type = 'bool';
        } elseif ($value === null) {
            $type = 'nil';
        }

        // Push the value onto the data stack
        $this->dataStack[$symb['value']] = array("value" => $value, "type" => $type);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string, type: string}> $args
     */
    private function opcodePOPS($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 1) {
            throw new InvalidStructure("Invalid number of arguments for POPS");
        }
    
        // Get the variable from the instruction arguments
        $var = $args[0]['value'];
    
        // Check if the data stack is empty
        if (empty($this->dataStack)) {
            throw new ValueError("Cannot pop from empty stack");
        }

        // Pop the value from the data stack
        $popped = array_pop($this->dataStack);

        // Construct the result value
        $result = array("value" => $popped["value"], "type" => $popped["type"]);
    
        // Store the popped value into the variable
        $this->frames->set($var, $result, true);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**************************************************************************
     * Aritmetické, relačné, boolovské a konverzné inštrukcie
     */

    /**
     * @param array<array{value: string}> $args
     */
     private function opcodeADD($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for ADD");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[1]);
        $arg2Type = $this->getType($args[2]);

        // Check if the types of the arguments are integers
        if ($arg1Type !== "int" || $arg2Type !== "int") {
            throw new OperandStructure("Invalid operand type '{$arg1Type}' or '{$arg2Type}'");
        }

        // Get the values of the arguments
        $arg1Value = $this->getValue($args[1]);
        $arg2Value = $this->getValue($args[2]);

        // Check if the values are integers
        if (!is_int($arg1Value) && !is_int($arg2Value)) {
            throw new OperandStructure("Invalid operand value '{$arg1Value}' or '{$arg2Value}'");
        }

        // Perform the addition operation
        $result = $arg1Value + $arg2Value;

        // Construct the result value
        $result = array("value" => $result, "type" => "int");

        // Set the result value into the variable
        $this->frames->set($args[0]['value'], $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeSUB($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for SUB");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[1]);
        $arg2Type = $this->getType($args[2]);

        // Check if the types of the arguments are integers
        if ($arg1Type !== "int" || $arg2Type !== "int") {
            throw new OperandStructure("Invalid operand type '{$arg1Type}' or '{$arg2Type}'");
        }

        // Get the values of the arguments
        $arg1Value = $this->getValue($args[1]);
        $arg2Value = $this->getValue($args[2]);

        // Check if the values are integers
        if (!is_int($arg1Value) && !is_int($arg2Value)) {
            throw new OperandStructure("Invalid operand value '{$arg1Value}' or '{$arg2Value}'");
        }

        // Perform the subtraction operation
        $result = $arg1Value - $arg2Value;

        // Construct the result value
        $result = array("value" => $result, "type" => "int");

        // Set the result value into the variable
        $this->frames->set($args[0]['value'], $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeMUL($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for MUL");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[1]);
        $arg2Type = $this->getType($args[2]);

        // Check if the types of the arguments are integers
        if ($arg1Type !== "int" || $arg2Type !== "int") {
            throw new OperandStructure("Invalid operand type '{$arg1Type}' or '{$arg2Type}'");
        }

        // Get the values of the arguments
        $arg1Value = $this->getValue($args[1]);
        $arg2Value = $this->getValue($args[2]);

        // Check if the values are integers
        if (!is_int($arg1Value) && !is_int($arg2Value)) {
            throw new OperandStructure("Invalid operand value '{$arg1Value}' or '{$arg2Value}'");
        }

        // Perform the multiplication operation
        $result = $arg1Value * $arg2Value;

        // Construct the result value
        $result = array("value" => $result, "type" => "int");

        // Set the result value into the variable
        $this->frames->set($args[0]['value'], $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeIDIV($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for IDIV");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[1]);
        $arg2Type = $this->getType($args[2]);

        // Check if the types of the arguments are integers
        if ($arg1Type !== "int" || $arg2Type !== "int") {
            throw new OperandStructure("Invalid operand type '{$arg1Type}' or '{$arg2Type}'");
        }

        // Get the values of the arguments
        $arg1Value = $this->getValue($args[1]);
        $arg2Value = $this->getValue($args[2]);

        // Check if the values are integers
        if (!is_int($arg1Value) && !is_int($arg2Value)) {
            throw new OperandStructure("Invalid operand value '{$arg1Value}' or '{$arg2Value}'");
        }

        // Check if the second argument is zero
        if ($arg2Value === 0) {
            throw new OperandValue("Division by zero");
        }

        // Perform the division operation
        $result = $arg1Value / $arg2Value;

        // Construct the result value
        $result = array("value" => $result, "type" => "int");

        // Set the result value into the variable
        $this->frames->set($args[0]['value'], $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeLT($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for LT");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[1]);
        $arg2Type = $this->getType($args[2]);

        // Check if the types of the symbols are compatible for comparison
        if ($arg1Type !== $arg2Type || !in_array($arg1Type, ['int', 'bool', 'string'])) {
            throw new OperandStructure("Invalid operand types '{$arg1Type}' and '{$arg2Type}' for LT");
        }

        // Get the values of the arguments
        $arg1Value = $this->getValue($args[1]);
        $arg2Value = $this->getValue($args[2]);

        // Perform the comparison operation
        $result = null;
        switch ($arg1Type) {
            case 'int':
                $result = intval($arg1Value) < intval($arg2Value);
                break;
            case 'bool':
                $result = boolval($arg1Value) < boolval($arg2Value);
                break;
            case 'string':
                $result = strcmp($arg1Value, $arg2Value) < 0;
                break;
        }

        // Construct the result value
        $resultValue = array("value" => $result ? "true" : "false", "type" => "bool");

        // Set the result value into the variable
        $this->frames->set($args[0]['value'], $resultValue, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeGT($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for GT");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[1]);
        $arg2Type = $this->getType($args[2]);

        // Check if the types of the symbols are compatible for comparison
        if ($arg1Type !== $arg2Type || !in_array($arg1Type, ['int', 'bool', 'string'])) {
            throw new OperandStructure("Invalid operand types '{$arg1Type}' and '{$arg2Type}' for GT");
        }

        // Get the values of the arguments
        $arg1Value = $this->getValue($args[1]);
        $arg2Value = $this->getValue($args[2]);

        // Perform the comparison operation
        $result = null;
        switch ($arg1Type) {
            case 'int':
                $result = intval($arg1Value) > intval($arg2Value);
                break;
            case 'bool':
                $result = boolval($arg1Value) > boolval($arg2Value);
                break;
            case 'string':
                $result = strcmp($arg1Value, $arg2Value) > 0;
                break;
        }

        // Construct the result value
        $resultValue = array("value" => $result ? "true" : "false", "type" => "bool");

        // Set the result value into the variable
        $this->frames->set($args[0]['value'], $resultValue, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeEQ($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for MUL");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[1]);
        $arg2Type = $this->getType($args[2]);

        // Check if one or both operands are nil or if their types are not compatible
        if (($arg1Type == 'nil' || $arg2Type == 'nil') || ($arg1Type !== $arg2Type || !in_array($arg1Type, ['int', 'bool', 'string']))) {
            // If one operand is nil, ensure that the other is also nil
            if ($arg1Type != 'nil' && $arg2Type != 'nil') {
                throw new OperandStructure("Invalid operand types '{$arg1Type}' and '{$arg2Type}' for EQ");
            }
        }

        // Get the values of the arguments
        $arg1Value = $this->getValue($args[1]);
        $arg2Value = $this->getValue($args[2]);

        // Perform the comparison operation
        $result = ($arg1Value === $arg2Value);

        // Construct the result value
        $resultValue = array("value" => $result ? "true" : "false", "type" => "bool");

        // Set the result value into the variable
        $this->frames->set($args[0]['value'], $resultValue, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeAND($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for AND");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[1]);
        $arg2Type = $this->getType($args[2]);

        // Check if the types of the arguments are integers
        if ($arg1Type !== "bool" || $arg2Type !== "bool") {
            throw new OperandStructure("Invalid operand type '{$arg1Type}' or '{$arg2Type}'");
        }

        // Get the values of the arguments
        $arg1Value = $this->getValue($args[1]);
        $arg2Value = $this->getValue($args[2]);

        // Perform the multiplication operation
        $result = $arg1Value && $arg2Value;

        // Construct the result value
        $result = array("value" => $result, "type" => "bool");

        // Set the result value into the variable
        $this->frames->set($args[0]['value'], $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeOR($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for OR");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[1]);
        $arg2Type = $this->getType($args[2]);

        // Check if the types of the arguments are integers
        if ($arg1Type !== "bool" || $arg2Type !== "bool") {
            throw new OperandStructure("Invalid operand type '{$arg1Type}' or '{$arg2Type}'");
        }

        // Get the values of the arguments
        $arg1Value = $this->getValue($args[1]);
        $arg2Value = $this->getValue($args[2]);

        // Perform the multiplication operation
        $result = $arg1Value || $arg2Value;

        // Construct the result value
        $result = array("value" => $result, "type" => "bool");

        // Set the result value into the variable
        $this->frames->set($args[0]['value'], $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeNOT($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 2) {
            throw new InvalidStructure("Invalid number of arguments for NOT");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[1]);

        // Check if the types of the arguments are integers
        if ($arg1Type !== "bool") {
            throw new OperandStructure("Invalid operand type '{$arg1Type}'");
        }

        // Get the values of the arguments
        $arg1Value = $this->getValue($args[1]);

        // Perform the multiplication operation
        $result = !$arg1Value;

        // Construct the result value
        $result = array("value" => $result, "type" => "bool");

        // Set the result value into the variable
        $this->frames->set($args[0]['value'], $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeINT2CHAR($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 2) {
            throw new InvalidStructure("Invalid number of arguments for INT2CHAR");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[1]);

        // Check if the types of the arguments are integers
        if ($arg1Type !== "int") {
            throw new OperandStructure("Invalid operand type '{$arg1Type}'");
        }

        // Get the values of the arguments
        $arg1Value = $this->getValue($args[1]);

        // Check if the value is a valid ASCII code
        if ($arg1Value < 0 || $arg1Value > 1114111) {
            throw new StringOperationError("Invalid ASCII code '{$arg1Value}'");
        }

        // Perform the operation
        $result = chr($arg1Value);

        // Construct the result value
        $result = array("value" => $result, "type" => "string");

        // Store the result value into the variable
        $this->frames->set($args[0]['value'], $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeSTRI2INT($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for STRI2INT");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[1]);
        $arg2Type = $this->getType($args[2]);

        // Check if the types of the arguments are string and integer
        if ($arg1Type !== "string" || $arg2Type !== "int") {
            throw new OperandStructure("Invalid operand type '{$arg1Type}' or '{$arg2Type}'");
        }

        // Get the values of the arguments
        $arg1Value = $this->resolveValue($args[1]);
        $arg2Value = $this->resolveValue($args[2]);

        // Check if the second argument is a valid index
        if ($arg2Value < 0 || $arg2Value >= strlen($arg1Value)) {
            throw new StringOperationError("Invalid index '{$arg2Value}'");
        }

        // Perform the operation
        $result = ord($arg1Value[$arg2Value]);

        // Construct the result value
        $result = array("value" => $result, "type" => "int");

        // Store the result value into the variable
        $this->frames->set($args[0]['value'], $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }


    /**************************************************************************
     * Vstupno-výstupné inštrukcie
     */

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeREAD($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 2) {
            throw new InvalidStructure("Invalid number of arguments for READ");
        }

        // Get the variable name and type from the instruction arguments
        $varName = $args[0]['value'];
        $type = $args[1]['value'];

        // Check if the type is valid
        if ($type !== "int" && $type !== "string" && $type !== "bool") {
            throw new InvalidStructure("Invalid operand type '{$type}'");
        }
        
        // Read input based on the specified type
        $value = "nil@nil";
        switch ($type) {
            case "int":
                // Read an integer value
                $value = $this->input->readInt();
                break;
            case "string":
                // Read a string value
                $value = $this->input->readString();
                break;
            case "bool":
                // Read a boolean value
                $value = $this->input->readBool();
                break;
            default:
                // Invalid type specified, set value to nil@nil
                $value = "nil@nil";
                break;
        }

        // Construct the result value
        $result = array("value" => $value, "type" => $type);

        // Store the read value into the variable
        $this->frames->set($varName, $result, false);
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    public function opcodeWRITE($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 1) {
            throw new InvalidStructure("Invalid number of arguments for WRITE");
        }

        // Get the value to write
        $value = $this->resolveValue($args[0]);

        // Check if the value is null
        if ($value === null) {
            throw new ValueError("Invalid value to write");
        }

        // Write the value to the output
        $this->stdout->writeString($value);

        // Move to the next instruction
        $this->instructionPointer++;
    }


    /**************************************************************************
     * Práca s reťazcami
     */

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeCONCAT($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for CONCAT");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[1]);
        $arg2Type = $this->getType($args[2]);

        // Check if both symbols are of string type
        if ($arg1Type !== "string" || $arg2Type !== "string") {
            throw new OperandStructure("CONCAT requires string operands");
        }

        // Get the values of the arguments
        $arg1Value = $this->resolveValue($args[1]);
        $arg2Value = $this->resolveValue($args[2]);

        // Perform the concatenation operation
        $result = $arg1Value . $arg2Value;

        // Construct the result value
        $result = array("value" => $result, "type" => "string");

        // Set the result value into the variable
        $this->frames->set($args[0]['value'], $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeSTRLEN($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 2) {
            throw new InvalidStructure("Invalid number of arguments for STRLEN");
        }

        // Get the type of the symbol
        $type = $this->getType($args[1]);

        // Check if the type of the symbol is string
        if ($type !== "string") {
            throw new OperandStructure("STRLEN requires a string operand");
        }

        // Get the value of the symbol
        $value = $this->resolveValue($args[1]);

        // Perform the operation
        $result = strlen($value);

        // Construct the result value
        $result = array("value" => $result, "type" => "int");

        // Store the result value into the variable
        $this->frames->set($args[0]['value'], $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeGETCHAR($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for GETCHAR");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[1]);
        $arg2Type = $this->getType($args[2]);

        // Check if the types of the arguments are correct
        if ($arg1Type !== "string" || $arg2Type !== "int") {
            throw new OperandStructure("GETCHAR requires a string and an integer operand");
        }

        // Get the values of the arguments
        $arg1Value = $this->resolveValue($args[1]);
        $arg2Value = $this->resolveValue($args[2]);

        // Check if the second argument is a valid index
        if ($arg2Value < 0 || $arg2Value >= strlen($arg1Value)) {
            throw new StringOperationError("Invalid index '{$arg2Value}' for string '{$arg1Value}'");
        }

        // Perform the operation
        $result = $arg1Value[$arg2Value];

        // Construct the result value
        $result = array("value" => $result, "type" => "string");

        // Store the result value into the variable
        $this->frames->set($args[0]['value'], $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeSETCHAR($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for SETCHAR");
        }

        // Get the types of the arguments
        $arg1Type = $this->getType($args[0]);
        $arg2Type = $this->getType($args[1]);
        $arg3Type = $this->getType($args[2]);

        // Check if the types of the arguments are correct
        if ($arg1Type !== "string" || $arg2Type !== "int") {
            throw new OperandStructure("SETCHAR requires a string and an integer operand");
        }

        // Check if the type of the third argument is string
        if ($arg3Type !== "string") {
            throw new OperandStructure("SETCHAR requires a string operand");
        }

        // Get the values of the arguments
        $arg1Value = $this->resolveValue($args[0]);
        $arg2Value = $this->resolveValue($args[1]);
        $arg3Value = $this->resolveValue($args[2]);

        // Check if the second argument is a valid index
        if ($arg2Value < 0 || $arg2Value >= strlen($arg1Value)) {
            throw new StringOperationError("Invalid index '{$arg2Value}' for string '{$arg1Value}'");
        }
        
        // Check if the value of the third argument is a single character
        if (mb_strlen($arg3Value) !== 1) {
            throw new StringOperationError("Invalid character '{$arg3Value}'");
        }

        // Perform the operation
        $result = substr_replace($arg1Value, $arg3Value, $arg2Value, 1);

        // Construct the result value
        $result = array("value" => $result, "type" => "string");

        // Store the result value into the variable
        $this->frames->set($args[0]['value'], $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }


    /**************************************************************************
     * Práca s typmi
     */

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeTYPE($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 2) {
            throw new InvalidStructure("Invalid number of arguments for TYPE");
        }

        // Get the values of the arguments
        $var = $args[0]['value'];
        $symb = $args[1];

        // Get the type of the symbol
        $type = $this->getType($symb);

        // Construct the result value
        $result = array("value" => $type, "type" => "string");
    
        // Store the type of the symbol into the variable
        $this->frames->set($var, $result, false);

        // Move to the next instruction
        $this->instructionPointer++;
    }


    /**************************************************************************
     * Inštrukcie pre riadenie toku programu
     */

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeLABEL($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 1) {
            throw new InvalidStructure("Invalid number of arguments for LABEL");
        }

        // Labels were already processed in the prerun phase
        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     * @param mixed $instructions
     */
    private function opcodeJUMP($args, $instructions): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 1) {
            throw new InvalidStructure("Invalid number of arguments for JUMP");
        }

        // Get the name of the label
        $labelName = $args[0]['value'];

        // Find the index of the label in the instructions array
        $jumpIndex = Labels::findLabelIndex($labelName, $instructions);

        // Check if the label exists
        if ($jumpIndex === false) {
            throw new SemanticError("Label '{$labelName}' does not exist");
        }

        // Jump to the specified instruction
        $this->instructionPointer = $jumpIndex;
    }

    /**
     * @param array<array{value: string}> $args
     * @param mixed $instructions
     */
    private function opcodeJUMPIFEQ($args, $instructions): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for JUMPIFEQ");
        }

        // Get the type and value of the arguments
        $arg1Type = $this->getType($args[1]);
        $arg2Type = $this->getType($args[2]);

        // Get the values of the arguments
        $arg1Value = $this->resolveValue($args[1]);
        $arg2Value = $this->resolveValue($args[2]);

        // Check if the types of the arguments are the same
        if ($arg1Type !== $arg2Type) {
            throw new OperandStructure("Invalid operand types '{$arg1Type}' and '{$arg2Type}'");
        }

        // Convert the values to integers if both operands are integers
        if ($arg1Type === "int" && $arg2Type === "int") {
            $arg1Value = intval($arg1Value);
            $arg2Value = intval($arg2Value);
        }

        // Check if both operands are of the same type or one of them is nil
        if (($arg1Type === $arg2Type || $arg1Type === "nil" || $arg2Type === "nil") && $arg1Value === $arg2Value) {
            // Find the label in the instructions array
            $label = $args[0]['value'];
            $jumpIndex = Labels::findLabelIndex($label, $instructions);
            if ($jumpIndex !== false) {
                // Jump to the specified instruction
                $this->instructionPointer = $jumpIndex;
            } else {
                throw new SemanticError("Label '{$label}' not found");
            }
        } else {
            // Move to the next instruction
            $this->instructionPointer++;
        }
    }

    /**
     * @param array<array{value: string}> $args
     * @param mixed $instructions
     */
    private function opcodeJUMPIFNEQ($args, $instructions): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 3) {
            throw new InvalidStructure("Invalid number of arguments for JUMPIFEQ");
        }

        // Get the type and value of the arguments
        $arg1Type = $this->getType($args[1]);
        $arg2Type = $this->getType($args[2]);

        // Get the values of the arguments
        $arg1Value = $this->resolveValue($args[1]);
        $arg2Value = $this->resolveValue($args[2]);

        if ($arg1Type !== $arg2Type) {
            throw new OperandStructure("Invalid operand types '{$arg1Type}' and '{$arg2Type}'");
        }

        // Convert the values to integers if both operands are integers
        if ($arg1Type === "int" && $arg2Type === "int") {
            $arg1Value = intval($arg1Value);
            $arg2Value = intval($arg2Value);
        }

        // Check if both operands are of the same type or one of them is nil
        if (($arg1Type === $arg2Type || $arg1Type === "nil" || $arg2Type === "nil") && $arg1Value !== $arg2Value) {
            // Find the label in the instructions array
            $label = $args[0]['value'];
            $jumpIndex = Labels::findLabelIndex($label, $instructions);
            if ($jumpIndex !== false) {
                // Jump to the specified instruction
                $this->instructionPointer = $jumpIndex;
            } else {
                throw new SemanticError("Label '{$label}' not found");
            }
        } else {
            // Move to the next instruction
            $this->instructionPointer++;
        }
    }

    /**
     * @param array<array{value: string, type: string}> $args
     */
    private function opcodeEXIT($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 1) {
            throw new InvalidStructure("Invalid number of arguments for MOVE");
        }

        // Get the type and value of the argument
        $argType = $args[0]['type'];
        $argValue = $args[0]['value'];

        // Check if the type of the argument is integer
        if ($argType !== "int") {
            throw new OperandStructure("Invalid operand type '{$argType}'");
        }

        // Check if the value of the argument is in the range 0-49
        if ($argValue < 0 || $argValue > 49) {
            throw new OperandValue("Invalid exit code '{$argValue}'");
        }

        // Exit the program with the specified exit code
        exit(intval($argValue));
    }


    /**************************************************************************
     * Ladiace inštrukcie
     */

    /**
     * @param array<array{value: string}> $args
     */
    public function opcodeDPRINT($args): void {
        // Check if the number of arguments is correct
        $argsLength = count($args);
        if ($argsLength !== 1) {
            throw new InvalidStructure("Invalid number of arguments for DPRINT");
        }

        // Get the value of the argument
        $value = $this->resolveValue($args[0]);

        // Write the value to the standard error output
        $this->stderr->writeString($value);

        // Move to the next instruction
        $this->instructionPointer++;
    }

    /**
     * @param array<array{value: string}> $args
     */
    private function opcodeBREAK($args): void {
        // BREAK instruction is not fully implemented !!!
        $this->stdout->writeString("Instruction pointer: {$this->instructionPointer}\n");
        $this->stdout->writeString("Frames: \n");
        $this->frames->printFrames();

        // Move to the next instruction
        $this->instructionPointer++;
    }


    /**************************************************************************
     * Pomocné funkcie
     */
        
    /**
     * @param array<string> $symb
     */
    public function getType($symb): string {
        // Get the type of the symbol
        $value = $symb['type'];

        // Get the type of the symbol
        $type = '';
        if ($value == "var"){
            $type = $this->frames->get($symb['value'], "type");
        } else {
            $type = $value;
        }
    
        // Return the type of the symbol
        switch ($type) {
            case 'int':
                return 'int';
            case 'bool':
                return 'bool';
            case 'string':
                return 'string';
            case 'nil':
                return 'nil';
            default:
                return '';
        }
    }

    // Function to get the opcode of the instruction
    public function getOpcode(mixed $instruction): string {
        // Return the opcode of the instruction
        return $instruction->getAttribute('opcode');
    }

    /**
     * @return array<array{count: int, type: string, value: string}> $instruction
     */
    public function getArgs(mixed $instruction): array {
        // Get the arguments of the instruction
        $args = [];
        for ($i = 1; $i <= $instruction->childElementCount; $i++) {
            $argTagName = "arg$i";
            $argElement = $instruction->getElementsByTagName($argTagName)->item(0);
            // Check if the argument is missing
            if ($argElement === null) {
                throw new InvalidStructure("Missing argument $i in instruction '{$instruction->getAttribute('opcode')}'");
            } else {
                // Get the type and value of the argument
                $argType = $argElement->getAttribute('type');
                $argValue = $argElement->nodeValue;
                $args[] = array('count' => $i, 'type' => $argType, 'value' => $argValue);
            }
        }

        // Return the arguments of the instruction
        return $args;
    }

    // Function to get the value of the argument
    private function getValue(mixed $arg): mixed {
        // Get the value of the argument
        if ($arg['type'] === "int") {
            if (filter_var($arg['value'], FILTER_VALIDATE_INT) === false) {
                throw new InvalidStructure("Invalid integer value '{$arg['value']}'");
            }
            return intval($arg['value']);
        } else if ($arg['type'] === "var") {
            if ($this->frames->get($arg['value'], "type") === "bool")
                return $this->frames->get($arg['value'], "value") === "true" ? 1 : 0;
            else
                return $this->frames->get($arg['value'], "value");
        } else if ($arg['type'] === "bool") {
            if ($arg['value'] === "true") {
                return 1;
            } else if ($arg['value'] === "false") {
                return 0;
            } else {
                throw new InvalidStructure("Invalid boolean value '{$arg['value']}'");
            }
        } else if ($arg['type'] === "string") {
            return $arg['value'];
        } else if ($arg['type'] === "nil") {
            return "";
        }
        else {
            throw new OperandStructure("Invalid operand type '{$arg['type']}'");
        }
    }

    // Function to resolve the value of the argument
    private function resolveValue(mixed $arg): mixed {
        // Resolve the value of the argument
        switch ($arg['type']) {
            case "var":
                $frame = &$this->frames->identifyFrame($arg['value']);
                if ($frame === null) {
                    throw new FrameAccessError("Frame is not defined");
                }
                if (!isset($frame[$arg['value']])) {
                    throw new VariableAccessError("Variable '{$arg['value']}' does not exist");
                }
                return $frame[$arg['value']]["value"];
            case "string":
                return $this->frames->parseStringValue($arg['value']);
            case "bool":
            case "int":
            case "label":
                return $arg['value'];
            case "nil":
                return "";
            default:
                throw new OperandStructure("Invalid operand type '{$arg['type']}'");
        }
    }
}