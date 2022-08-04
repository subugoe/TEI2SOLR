<?php

declare(strict_types=1);

namespace Subugoe\TEI2SOLRBundle\Import;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class Importer implements ImporterInterface
{
    private ?string $gitlabLitRepoUrl;

    private ?string $gitlabProcessedLitRepoUrl;

    private ?string $gitlabProcessedTeiRepoUrl;

    private ?string $gitlabRepoToken;

    private ?int $gitlabRepoTreeOffset;

    private ?string $gitlabRepoTreeUrl;

    private ?string $gitlabTeiBranchName;

    private ?string $invalidTeiListFile;

    private ?string $litDir;

    private ?string $sampleTeiDocumentUrl;

    private ?string $teiDir = null;

    private ?string $teiFoldersToBeImported;

    private ?string $teiFolderWithFiles;

    private ?string $teiSampleDir = null;

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function importMultipelFolderTeiFiles(): void
    {
        $activeTeiFolders = [];
        $teiBaseFolderUrl = $this->gitlabRepoTreeUrl.'?path=Texte&access_token='.$this->gitlabRepoToken;
        $activeTeiFolders = $this->getTeisToBeimported($teiBaseFolderUrl, $activeTeiFolders);

        $filesystem = new Filesystem();
        if (!$filesystem->exists($this->teiDir)) {
            $filesystem->mkdir($this->teiDir);
        }

        foreach ($activeTeiFolders as $activeTeiFolder) {
            for ($i = 1; $i <= $this->gitlabRepoTreeOffset; ++$i) {
                $activeTeiFolderUrl = $this->gitlabRepoTreeUrl.'?per_page=100&path='.$activeTeiFolder.'&access_token='.$this->gitlabRepoToken.'&page='.$i;
                $teiFiles = json_decode(file_get_contents($activeTeiFolderUrl), true);

                foreach ($teiFiles as $teiFile) {
                    if ('.gitkeep' !== $teiFile['name']) {
                        $fileName = urlencode($teiFile['name']);
                        $teiFileUrl = $this->gitlabProcessedTeiRepoUrl.str_replace('/', '%2F', $activeTeiFolder).'%2F'.$fileName.'?access_token='.$this->gitlabRepoToken.'&ref='.$this->gitlabTeiBranchName;
                        $fileData = @file_get_contents($teiFileUrl);

                        if (is_string($fileData)) {
                            $fileData = json_decode($fileData, true);

                            try {
                                $filesystem->dumpFile(
                                    $this->teiDir.$teiFile['name'],
                                    base64_decode($fileData['content'])
                                );
                            } catch (FileException $fileException) {
                                $this->logger->error(sprintf('%s could not be imported.', $teiFile['name']), $fileException->getTrace());
                            }
                        } else {
                            // TODO retry to download the file again
                            $this->logger->error(sprintf('%s could not be imported.', $teiFile['name']));
                        }
                    }
                }
            }
        }
    }

    public function importSampleTeiDocument(): void
    {
        $sampleTeiDocument = $this->getSampleTeiDocument();
        $filesystem = new Filesystem();

        if (!empty($sampleTeiDocument)) {
            $filesystem->dumpFile($this->teiSampleDir.'sample.xml', $sampleTeiDocument);
        }
    }

    public function importTeiFiles(): void
    {
        $this->importLiterature();

        $filesystem = new Filesystem();
        if (!$filesystem->exists($this->teiDir)) {
            $filesystem->mkdir($this->teiDir);
        }

        $invalidTeiList = $this->getInvalidTeiList();

        for ($i = 1; $i <= 100; ++$i) {
            try {
                $files = file_get_contents($this->gitlabRepoTreeUrl.'&access_token='.$this->gitlabRepoToken.'&page='.$i);

                if (is_string($files)) {
                    $files = json_decode($files, true);

                    foreach ($files as $file) {
                        if (!in_array(trim($file['name']), $invalidTeiList) && '.gitkeep' !== $file['name']) {
                            $fileName = urlencode($file['name']);
                            $teiFileUrl = $this->gitlabProcessedTeiRepoUrl.$fileName.'?access_token='.$this->gitlabRepoToken.'&ref='.$this->gitlabTeiBranchName;
                            $fileData = @file_get_contents($teiFileUrl);
                            if (is_string($fileData)) {
                                $fileData = json_decode($fileData, true);

                                try {
                                    $filesystem->dumpFile(
                                        $this->teiDir.$file['name'],
                                        base64_decode($fileData['content'])
                                    );
                                } catch (FileException $fileException) {
                                    $this->logger->error(sprintf('%s could not be imported.', $file['name']), $fileException->getTrace());
                                }
                            } else {
                                // TODO retry to download the file again
                                $this->logger->error(sprintf('%s could not be imported.', $file['name']));
                            }
                        }
                    }
                }
            } catch (FileException $fileException) {
                $this->logger->error('Files list could not be imported from gitlab.', $fileException->getTrace());
            }
        }
    }

    public function setConfigs(string $teiDir, string $teiSampleDir, string $litDir, string $gitlabRepoToken, string $gitlabRepoTreeUrl, string $gitlabProcessedTeiRepoUrl, string $invalidTeiListFile, string $sampleTeiDocumentUrl, string $gitlabLitRepoUrl, string $gitlabProcessedLitRepoUrl, string $gitlabTeiBranchName, string $teiFoldersToBeImported, string $teiFolderWithFiles, int $gitlabRepoTreeOffset): void
    {
        $this->teiDir = $teiDir;
        $this->teiSampleDir = $teiSampleDir;
        $this->litDir = $litDir;
        $this->gitlabRepoToken = $gitlabRepoToken;
        $this->gitlabRepoTreeUrl = $gitlabRepoTreeUrl;
        $this->gitlabProcessedTeiRepoUrl = $gitlabProcessedTeiRepoUrl;
        $this->invalidTeiListFile = $invalidTeiListFile;
        $this->sampleTeiDocumentUrl = $sampleTeiDocumentUrl;
        $this->gitlabLitRepoUrl = $gitlabLitRepoUrl;
        $this->gitlabProcessedLitRepoUrl = $gitlabProcessedLitRepoUrl;
        $this->gitlabTeiBranchName = $gitlabTeiBranchName;
        $this->teiFoldersToBeImported = $teiFoldersToBeImported;
        $this->teiFolderWithFiles = $teiFolderWithFiles;
        $this->gitlabRepoTreeOffset = $gitlabRepoTreeOffset;
    }

    private function getInvalidTeiList(): array
    {
        $invalidTeiList = [];
        $file_headers = @get_headers($this->invalidTeiListFile);

        if ('HTTP/1.1 200 OK' === $file_headers[0]) {
            $invalidTeiList = json_decode(file_get_contents($this->invalidTeiListFile), true);
        }

        return $invalidTeiList;
    }

    private function getSampleTeiDocument(): ?string
    {
        $sampleTeiDocument = '';
        $sampleTeiDocumentUrl = $this->sampleTeiDocumentUrl.'&access_token='.$this->gitlabRepoToken;
        $file_headers = @get_headers($sampleTeiDocumentUrl);

        if ('HTTP/1.1 200 OK' === $file_headers[0]) {
            $sampleDocumentData = json_decode(file_get_contents($sampleTeiDocumentUrl), true);
            $sampleTeiDocument = base64_decode($sampleDocumentData['content']);
        }

        return $sampleTeiDocument;
    }

    private function getTeisToBeimported(string $teiBaseFolderUrl, array &$activeTeiFolders): array
    {
        $teiFoldersToBeImported = explode(',', $this->teiFoldersToBeImported);
        $teiFolders = json_decode(file_get_contents($teiBaseFolderUrl), true);

        foreach ($teiFolders as $teiFolder) {
            $path = $teiFolder['path'];

            if ('tree' === $teiFolder['type'] && in_array($teiFolder['name'], $teiFoldersToBeImported)) {
                $url = $this->gitlabRepoTreeUrl.'?path='.$path.'&access_token='.$this->gitlabRepoToken;
                $this->getTeisToBeimported($url, $activeTeiFolders);
            } elseif ('tree' === $teiFolder['type'] && $teiFolder['name'] == $this->teiFolderWithFiles) {
                $activeTeiFolders[] = $teiFolder['path'];
            }
        }

        return $activeTeiFolders;
    }

    private function importLiterature(): void
    {
        $filesystem = new Filesystem();

        if (!$filesystem->exists($this->litDir)) {
            $filesystem->mkdir($this->litDir);
        }

        try {
            $files = file_get_contents($this->gitlabLitRepoUrl.'&page=1&access_token='.$this->gitlabRepoToken);

            if (is_string($files)) {
                $files = json_decode($files, true);

                foreach ($files as $file) {
                    $teiFileUrl = $this->gitlabProcessedLitRepoUrl.$file['name'].'?access_token='.$this->gitlabRepoToken.'&ref=master';
                    $fileData = file_get_contents($teiFileUrl);

                    if (is_string($fileData)) {
                        $fileData = json_decode($fileData, true);

                        try {
                            $filesystem->dumpFile(
                                $this->litDir.$file['name'],
                                base64_decode($fileData['content'])
                            );
                        } catch (FileException $fileException) {
                            $this->logger->error(sprintf('%s could not be imported.', $file['name']), $fileException->getTrace());
                        }
                    } else {
                        // TODO retry to download the file again
                        $this->logger->error(sprintf('%s could not be imported.', $file['name']));
                    }
                }
            }
        } catch (FileException $fileException) {
            $this->logger->error('Literature files list could not be imported from gitlab', $fileException->getTrace());
        }
    }
}
