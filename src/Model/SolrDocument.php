<?php

namespace Subugoe\TEI2SOLRBundle\Model;

class SolrDocument
{
    private array $allAnnotationIds;

    private string $editedText;

    private array $gndsUuids;

    private array $pageLevelEditedText;

    private array $pageLevelTranscriptedText;

    private array $pagesDates;

    private array $pagesGndsUuids;

    private array $pagesNotes;

    private array $pagesWorks;

    private string $transcriptedText;

    public function getAllAnnotationIds(): array
    {
        return $this->allAnnotationIds;
    }

    public function getEditedText(): string
    {
        return $this->editedText;
    }

    public function getGndsUuids(): array
    {
        return $this->gndsUuids;
    }

    public function getPageLevelEditedText(): array
    {
        return $this->pageLevelEditedText;
    }

    public function getPageLevelTranscriptedText(): array
    {
        return $this->pageLevelTranscriptedText;
    }

    public function getPagesDates(): ?array
    {
        return $this->pagesDates;
    }

    public function getPagesGndsUuids(): ?array
    {
        return $this->pagesGndsUuids;
    }

    public function getPagesNotes(): ?array
    {
        return $this->pagesNotes;
    }

    public function getPagesWorks(): array
    {
        return $this->pagesWorks;
    }

    public function getTranscriptedText(): string
    {
        return $this->transcriptedText;
    }

    public function setAllAnnotationIds(array $allAnnotationIds): SolrDocument
    {
        $this->allAnnotationIds = $allAnnotationIds;

        return $this;
    }

    public function setEditedText(string $editedText): SolrDocument
    {
        $this->editedText = $editedText;

        return $this;
    }

    public function setGndsUuids(array $gndsUuids): SolrDocument
    {
        $this->gndsUuids = $gndsUuids;

        return $this;
    }

    public function setPageLevelEditedText(array $pageLevelEditedText): SolrDocument
    {
        $this->pageLevelEditedText = $pageLevelEditedText;

        return $this;
    }

    public function setPageLevelTranscriptedText(array $pageLevelTranscriptedText): SolrDocument
    {
        $this->pageLevelTranscriptedText = $pageLevelTranscriptedText;

        return $this;
    }

    public function setPagesDates(?array $pagesDates): SolrDocument
    {
        $this->pagesDates = $pagesDates;

        return $this;
    }

    public function setPagesGndsUuids(?array $pagesGndsUuids): SolrDocument
    {
        $this->pagesGndsUuids = $pagesGndsUuids;

        return $this;
    }

    public function setPagesNotes(?array $pagesNotes): SolrDocument
    {
        $this->pagesNotes = $pagesNotes;

        return $this;
    }

    public function setPagesWorks(array $pagesWorks): SolrDocument
    {
        $this->pagesWorks = $pagesWorks;

        return $this;
    }

    public function setTranscriptedText(string $transcriptedText): SolrDocument
    {
        $this->transcriptedText = $transcriptedText;

        return $this;
    }
}
