<?php

namespace Pingiun\FixConflicts;

use Throwable;

class ProcessFailedException extends \RuntimeException {
    public function __construct(public string $command, public string $output, string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function __toString(): string
    {
        return parent::__toString(). PHP_EOL.'Process output:'.PHP_EOL.$this->output;
    }

    public function getNiceMessage(): string
    {
        return "Command <fg=blue>{$this->command}</> failed with output:".PHP_EOL.$this->output;
    }
}
