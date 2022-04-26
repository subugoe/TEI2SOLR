<?php

declare(strict_types=1);

namespace Subugoe\TEI2SOLRBundle\Command;

use Subugoe\TEI2SOLRBundle\Import\ImporterInterface;
use Subugoe\TEI2SOLRBundle\Index\IndexerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SolrIndexing extends Command
{
    protected static $defaultName = 'app:tei2solr';
    protected static string $description = 'Process TEI data to solr data for importing into solr.';
    private ImporterInterface $importer;
    private ?bool $importSampleTei;
    private ?bool $importTeiFiles;
    private IndexerInterface $indexer;
    private ?bool $literatureToSolr;
    private ?bool $teiToSolr;
    private ?bool $multipelFolderImport;

    public function __construct(ImporterInterface $importer, IndexerInterface $indexer)
    {
        parent::__construct();
        $this->importer = $importer;
        $this->indexer = $indexer;
    }

    public function setConfigs(bool $importSampleTei, bool $importTeiFiles, bool $literatureToSolr, bool $teiToSolr, bool $multipelFolderImport): void
    {
        $this->importSampleTei = $importSampleTei;
        $this->importTeiFiles = $importTeiFiles;
        $this->literatureToSolr = $literatureToSolr;
        $this->teiToSolr = $teiToSolr;
        $this->multipelFolderImport = $multipelFolderImport;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription(self::$description);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Start solr indexing.');

        if ($this->importTeiFiles) {
            if ($this->multipelFolderImport) {
                $this->importer->importMultipelFolderTeiFiles();
            } else {
                $this->importer->importTeiFiles();
            }
        }

        if ($this->importSampleTei) {
            $this->importer->importSampleTeiDocument();
        }

        if ($this->teiToSolr) {
            $this->indexer->deleteSolrIndex();
            $this->indexer->teiToSolr($this->importSampleTei);
        }

        if ($this->literatureToSolr) {
            $this->indexer->literatureToSolr();
        }

        $time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        $time /= 60;
        $output->writeln('Indexing process completed in '.$time.' minutes.');

        return 1;
    }
}
