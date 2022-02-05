<?php

declare(strict_types=1);

namespace Subugoe\TEI2SOLRBundle\Command;

use Subugoe\TEI2SOLRBundle\Import\ImporterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TeiToS3Storage extends Command
{
    protected static $defaultName = 'app:tei_to_s3';
    private ImporterInterface $importer;

    public function __construct(ImporterInterface $importer)
    {
        parent::__construct();
        $this->importer = $importer;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setDescription('Import TEI files from gitlab to S3 storage.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Start importing TEI files into S3 storage.');

        $this->importer->importTeiToS3Storage();

        $time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        $time /= 60;
        $output->writeln('Indexing process completed in '.$time.' minutes.');

        return 1;
    }
}
