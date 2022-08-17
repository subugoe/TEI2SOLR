<?php

namespace Subugoe\TEI2SOLRBundle\Import;

interface ImporterInterface
{
    public function importSampleTeiDocument(): void;

    public function importTeiFiles(): void;
}
