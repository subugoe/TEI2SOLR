<?php

namespace Subugoe\TEI2SOLRBundle\Transform;

use DOMXPath;

interface MetadataTransformerInterface
{
    public function getAuthor(DOMXPath $xpath): string;
    public function getCountry(DOMXPath $xpath): string;
    public function getDestinationPlace(DOMXPath $xpath): string;
    public function getFreeKeywords(DOMXPath $xpath): array;
    public function getGndKeywords(DOMXPath $xpath): array;
    public function getNumberOfPages(DOMXPath $xpath): ?int;
    public function getImageIds(DOMXPath $xpath): array;
    public function getImageUrls(DOMXPath $xpath): array;
    public function getInstitution(DOMXPath $xpath): string;
    public function getLanguage(DOMXPath $xpath): string;
    public function getLicense(DOMXPath $xpath): string;
    public function getOriginDate(DOMXPath $xpath): string;
    public function getOriginPlace(DOMXPath $xpath): string;
    public function getPublicationDate(DOMXPath $xpath): string;
    public function getRecipient(DOMXPath $xpath): string;
    public function getReference(DOMXPath $xpath): string;
    public function getRelatedItems(DOMXPath $xpath): array;
    public function getRepository(DOMXPath $xpath): string;
    public function getResponse(DOMXPath $xpath): string;
    public function getScriptSource(DOMXPath $xpath): string;
    public function getSettlement(DOMXPath $xpath): string;
    public function getShelfmark(DOMXPath $xpath): string;
    public function getShortTitle(DOMXPath $xpath): string;
    public function getSourceDescription(DOMXPath $xpath): string;
    public function getTitle(DOMXPath $xpath): string;
    public function getWriters(DOMXPath $xpath): array;
}
