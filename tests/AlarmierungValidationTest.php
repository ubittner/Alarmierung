<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class AlarmierungValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateModule_Alarmierung(): void
    {
        $this->validateModule(__DIR__ . '/../Alarmierung');
    }
}