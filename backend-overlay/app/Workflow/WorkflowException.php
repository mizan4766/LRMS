<?php

namespace App\Workflow;

/** A rule of the workflow (role, required field, guard, state) was violated. */
class WorkflowException extends \RuntimeException
{
    public function __construct(string $message, public array $details = [])
    {
        parent::__construct($message);
    }
}
