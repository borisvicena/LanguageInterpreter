<?php
/**
 * IPP - Student project
 * @author Boris Vícena <xvicen10>
 */

namespace IPP\Student;

use IPP\Core\AbstractInterpreter;
use IPP\Core\Settings;
use IPP\Core\ReturnCode;

class Interpreter extends AbstractInterpreter
{
    public function execute(): int
    {
        try {
            // Process arguments
            $settings = new Settings();
            $settings->processArgs();
    
            // Get source, input, stdout and stderr
            $source = $settings->getSourceReader();
            $input = $settings->getInputReader();
            $stdout = $settings->getStdOutWriter();
            $stderr = $settings->getStdErrWriter();
    
            // Check if help is requested
            if ($settings->isHelp()) {
                $stdout->writeString($settings->getHelpString());
                return ReturnCode::OK;
            }
    
            // Get DOMDocument from source
            $dom = $source->getDOMDocument();
    
            // Validate XML structure
            $xmlStructure = new XMLValidator($dom);
            $xmlStructure->validate();
    
            // Create instance of Runner
            $runner = new Runner($dom, $input, $stdout, $stderr);
            
            // Load sorted instructions
            $instructions = $runner->load();

            // Prerun for labels
            $runner->prerun();

            // Run instructions
            $runner->run($instructions);
        }
        catch (InvalidStructure $e) {
            throw new InvalidStructure($e->getMessage());
        }
        catch (ValueError $e) {
            throw new ValueError($e->getMessage());
        }
        catch (VariableAccessError $e){
            throw new VariableAccessError($e->getMessage());
        }
        catch (FrameAccessError $e){
            throw new FrameAccessError($e->getMessage());
        }
        catch (StringOperationError $e){
            throw new StringOperationError($e->getMessage());
        }
        catch (SemanticError $e){
            throw new SemanticError($e->getMessage());
        }
        catch (OperandStructure $e) {
            throw new OperandStructure($e->getMessage());
        }
        catch (OperandValue $e) {
            throw new OperandValue($e->getMessage());
        }
    
        // Return success code
        return ReturnCode::OK;
    }
}