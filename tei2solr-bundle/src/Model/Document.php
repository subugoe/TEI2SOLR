<?php

namespace Subugoe\TEI2SOLRBundle\Model;

class Document
{
    private ?string $author = null;

    private ?string $destinationPlace = null;

    private ?string $fulltext = null;

    private ?string $fulltextHtml = null;
    private ?string $id = null;

    private ?string $originDate = null;

    private ?string $originPlace = null;

    private ?string $recipient = null;

    private ?string $shortTitle = null;

    /**
     * @var array
     */
    private $sourceDescription;

    private ?string $title = null;

    /**
     * @return string
     */
    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function getDestinationPlace(): ?string
    {
        return $this->destinationPlace;
    }

    public function getFulltext(): string
    {
        return $this->fulltext;
    }

    public function getFulltextHtml(): string
    {
        return $this->fulltextHtml;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOriginDate(): ?string
    {
        return $this->originDate;
    }

    public function getOriginPlace(): ?string
    {
        return $this->originPlace;
    }

    public function getRecipient(): ?string
    {
        return $this->recipient;
    }

    public function getShortTitle(): string
    {
        return $this->shortTitle;
    }

    public function getSourceDescription(): array
    {
        return $this->sourceDescription;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $author
     */
    public function setAuthor(?string $author): Document
    {
        $this->author = $author;

        return $this;
    }

    public function setDestinationPlace(?string $destinationPlace): Document
    {
        $this->destinationPlace = $destinationPlace;

        return $this;
    }

    public function setFulltext(string $fulltext): Document
    {
        $this->fulltext = $fulltext;

        return $this;
    }

    public function setFulltextHtml(string $fulltextHtml): Document
    {
        $this->fulltextHtml = $fulltextHtml;

        return $this;
    }

    public function setId(string $id): Document
    {
        $this->id = $id;

        return $this;
    }

    public function setOriginDate(?string $originDate): Document
    {
        $this->originDate = $originDate;

        return $this;
    }

    public function setOriginPlace(?string $originPlace): Document
    {
        $this->originPlace = $originPlace;

        return $this;
    }

    public function setRecipient(?string $recipient): Document
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function setShortTitle(string $shortTitle): Document
    {
        $this->shortTitle = $shortTitle;

        return $this;
    }

    public function setSourceDescription(array $sourceDescription): Document
    {
        $this->sourceDescription = $sourceDescription;

        return $this;
    }

    public function setTitle(string $title): Document
    {
        $this->title = $title;

        return $this;
    }
}
