<?php

namespace Subugoe\TEI2SOLRBundle\Import;

interface ImporterInterface
{
    public function importTeiFiles(): void;

    public function importSampleTeiDocument(): void;
}
