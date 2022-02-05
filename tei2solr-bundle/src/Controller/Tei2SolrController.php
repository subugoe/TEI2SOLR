<?php

namespace Subugoe\TEI2SOLRBundle\Controller;

use Subugoe\TEI2SOLRBundle\Import\ImporterInterface;
use Subugoe\TEI2SOLRBundle\Index\IndexerInterface;
use Subugoe\TEI2SOLRBundle\Model\SolrDocument;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class Tei2SolrController extends AbstractController
{
    private ImporterInterface $importer;
    private IndexerInterface $indexer;

    public function __construct(ImporterInterface $importer, IndexerInterface $indexer)
    {
        $this->importer = $importer;
        $this->indexer = $indexer;
    }

    /**
     * @Route("gitlab")
     */
    public function fetchTeis(): void
    {
        $this->importer->import();
    }

    /**
     * @Route("tido/getTextVersions")
     */
    public function getTextVersions(string $filePath = './../data/gitlab/Z_1822-02-20_k.xml', array $graphics = []): SolrDocument
    {
        return $this->indexer->getTextVersions($filePath, $graphics);
    }

    /**
     * @Route("tei2solr")
     */
    public function tei2solr(): void
    {
        $this->indexer->tei2solr();
    }
}
