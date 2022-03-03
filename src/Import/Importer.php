<?php

declare(strict_types=1);

namespace Subugoe\TEI2SOLRBundle\Import;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class Importer implements ImporterInterface
{
    private ?string $teiDir = null;
    private ?string $teiSampleDir = null;
    private ?string $gitlabProcessedTeiRepoUrl;
    private ?string $gitlabRepoToken;
    private ?string $gitlabRepoTreeUrl;
    private ?string $invalidTeiListFile;
    private ?string $sampleTeiDocumentUrl;
    private ?string $litDir;
    private ?string $gitlabLitRepoUrl;
    private ?string $gitlabProcessedLitRepoUrl;
    private ?string $gitlabTeiBranchName;

    public function setConfigs(string $teiDir, string $teiSampleDir, string $litDir, string $gitlabRepoToken, string $gitlabRepoTreeUrl, string $gitlabProcessedTeiRepoUrl, string $invalidTeiListFile, string $sampleTeiDocumentUrl, string $gitlabLitRepoUrl, string $gitlabProcessedLitRepoUrl, string $gitlabTeiBranchName): void
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
                            } catch (FileException $exception) {
                                echo $file['name'].' could not be imported.';
                            }
                        } else {
                            // TODO retry to download the file again
                            echo $file['name'].' could not be imported.';
                        }
                    }
                }
        } catch (FileException $exception) {
            echo 'Literature files list could not be imported from gitlab';
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
                        if ([] !== $invalidTeiList && !in_array(trim($file['name']), $invalidTeiList)) {
                            if ('.gitkeep' !== $file['name']) {
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
                                    } catch (FileException $exception) {
                                        echo $file['name'].' could not be imported.';
                                    }
                                } else {
                                    // TODO retry to download the file again
                                    echo $file['name'].' could not be imported.';
                                }
                            }
                        }
                    }
                }
            } catch (FileException $exception) {
                echo 'Files list could not be imported from gitlab';
            }
        }
    }

    private function getInvalidTeiList(): array
    {
        $invalidTeiList = [];
        $file_headers = @get_headers($this->invalidTeiListFile);
        if($file_headers[0] === 'HTTP/1.1 200 OK') {
            $invalidTeiList = json_decode(file_get_contents($this->invalidTeiListFile), true);
        }

        return $invalidTeiList;
    }

    public function importSampleTeiDocument(): void
    {
        $sampleTeiDocument = $this->getSampleTeiDocument();
        $filesystem = new Filesystem();
        if (!empty($sampleTeiDocument)) {
            $filesystem->dumpFile($this->teiSampleDir.'sample.xml', $sampleTeiDocument);
        }
    }

    private function getSampleTeiDocument(): ?string
    {
        $sampleTeiDocument = '';
        $sampleTeiDocumentUrl = $this->sampleTeiDocumentUrl.'&access_token='.$this->gitlabRepoToken;
        $file_headers = @get_headers($sampleTeiDocumentUrl);
        if($file_headers[0] === 'HTTP/1.1 200 OK') {
            $sampleDocumentData = json_decode(file_get_contents($sampleTeiDocumentUrl), true);
            $sampleTeiDocument = base64_decode($sampleDocumentData['content']);
        }

        return $sampleTeiDocument;
    }
}
