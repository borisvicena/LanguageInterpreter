<?php
/**
 * IPP - Student project
 * @author Boris Vícena <xvicen10>
 */

namespace IPP\Student;

use IPP\Core\Exception\IPPException;
use IPP\Core\ReturnCode;
use Throwable;

class InvalidStructure extends IPPException
{
    public function __construct(string $message = "Invalid source XML format", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::INVALID_SOURCE_STRUCTURE, $previous, false);
    }
}