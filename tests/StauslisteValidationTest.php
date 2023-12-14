<?php

declare(strict_types=1);

namespace tests;

use TestCaseSymconValidation;

include_once __DIR__ . '/stubs/Validator.php';

class StauslisteValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateModule_Statusliste(): void
    {
        $this->validateModule(__DIR__ . '/../Statusliste');
    }
}