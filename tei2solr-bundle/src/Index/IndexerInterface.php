<?php

namespace Subugoe\TEI2SOLRBundle\Index;

use Subugoe\TEI2SOLRBundle\Model\SolrDocument;

interface IndexerInterface
{
    public function deleteSolrIndex(): void;

    public function getTextVersions(string $filePath, array $graphics = []): SolrDocument;

    public function teiTosolr(bool $importSampleTei): void;
}
