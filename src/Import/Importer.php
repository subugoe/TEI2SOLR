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
    private ?string $teiFoldersToBeImported;
    private ?string $teiFolderWithFiles;
    private ?int $gitlabRepoTreeOffset;

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

    private function getTeisToBeimported(string $teiBaseFolderUrl, array &$activeTeiFolders): array
    {
        $teiFoldersToBeImported = explode(',', $this->teiFoldersToBeImported);
        $teiFolders = json_decode(file_get_contents($teiBaseFolderUrl), true);

        foreach ($teiFolders as $teiFolder) {
            $path = $teiFolder['path'];

            if ('tree' === $teiFolder['type'] && in_array($teiFolder['name'], $teiFoldersToBeImported)) {
                $url = $this->gitlabRepoTreeUrl.'?path='.$path.'&access_token='.$this->gitlabRepoToken;
                $this->getTeisToBeimported($url, $activeTeiFolders);
            }
            elseif ('tree' === $teiFolder['type'] && $teiFolder['name'] == $this->teiFolderWithFiles) {
                $activeTeiFolders[] = $teiFolder['path'];
            }
        }

        return $activeTeiFolders;
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
                            } catch (FileException $exception) {
                                echo $teiFile['name'].' could not be imported.';
                            }
                        } else {
                            // TODO retry to download the file again
                            echo $teiFile['name'].' could not be imported.';
                        }
                    }
                }
            }
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
                        if (!in_array(trim($file['name']), $invalidTeiList)) {
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
