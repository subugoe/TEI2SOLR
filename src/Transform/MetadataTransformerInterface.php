<?php

namespace Subugoe\TEI2SOLRBundle\Transform;

use DOMXPath;

interface MetadataTransformerInterface
{
    public function getAuthor(DOMXPath $xpath): string|array;

    public function getCountry(DOMXPath $xpath): string;

    public function getDestinationPlace(DOMXPath $xpath): string;

    public function getDoctypeNotes(DOMXPath $xpath, string $id): array;

    public function getDocumentOwnGNDs(DOMXPath $xpath): ?array;

    public function getDocumentPublisher(DOMXPath $xpath): string;

    public function getEditor(DOMXPath $xpath): string|array;

    public function getEntities(DOMXPath $xpath): array;

    public function getFreeKeywords(DOMXPath $xpath): array;

    public function getFulltext(DOMXPath $xpath): string;

    public function getGndKeywords(DOMXPath $xpath): array;

    public function getGraphics(array $imageIds, array $imageUrls): array;

    public function getId(DOMXPath $xpath): string;

    public function getImageIds(DOMXPath $xpath): array;

    public function getImageUrls(DOMXPath $xpath): array;

    public function getInstitution(DOMXPath $xpath): string;

    public function getLanguage(DOMXPath $xpath): string;

    public function getLicense(DOMXPath $xpath): string;

    public function getLocation(DOMXPath $xpath): string;

    public function getMarker(DOMXPath $xpath): string;

    public function getNodeChilds($pagesNode, &$ele): array;

    public function getNumberOfPages(DOMXPath $xpath): ?int;

    public function getOriginDate(DOMXPath $xpath): string;

    public function getOriginPlace(DOMXPath $xpath): string;

    public function getPageFrom(DOMXPath $xpath): int;

    public function getPageTo(DOMXPath $xpath): int;

    public function getPublicationDate(DOMXPath $xpath): string;

    public function getPublicationPlace(DOMXPath $xpath): string;

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

    public function getVolumePart(DOMXPath $xpath): string;

    public function getWriters(DOMXPath $xpath): array;
}
