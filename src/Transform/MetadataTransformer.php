<?php

declare(strict_types=1);

namespace Subugoe\TEI2SOLRBundle\Transform;

use DOMXPath;
use Symfony\Component\Routing\RouterInterface;

class MetadataTransformer implements MetadataTransformerInterface
{
    private RouterInterface $router;
    private ?string $mainDomain;
    private ?array $documentLanguages;
    private ?int $handleAuthorName;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function setConfigs(string $mainDomain, array $documentLanguages, int $handleAuthorName): void
    {
        $this->mainDomain = $mainDomain;
        $this->documentLanguages = $documentLanguages;
        $this->handleAuthorName = $handleAuthorName;

    }

    public function getAuthor(DOMXPath $xpath): string|array
    {
        $authorNode = $xpath->query('//tei:name[@type="person" and @subtype="aut"]');

        $author = '';

        if ($authorNode->item(0)) {
            $author = $authorNode->item(0)->nodeValue;
            $author = trim(preg_replace('/\s+/', ' ', $author));
        }

        if (empty($author)) {
            $authorNodes = $xpath->query('//tei:titleStmt//tei:author//tei:name[@type="person"]');

            $author = [];
            foreach ($authorNodes as $authorNode) {
                $authorFullname = '';

                foreach ($authorNode->childNodes as $key => $authorChildNode) {
                    if ('name' === $authorChildNode->nodeName) {
                        $authorFullname .= $authorChildNode->nodeValue.',';
                    }
                }

                if ($this->handleAuthorName) {
                    $authorFullname = implode(', ', array_reverse(explode(',', trim($authorFullname, ','))));
                }

                $author[] = $authorFullname;
            }
        }

            return $author;
    }

    public function getEditor(DOMXPath $xpath): string|array
    {
        $editorNodes = $xpath->query('//tei:titleStmt//tei:editor//tei:name[@type="person"]');

        $editor = [];

        foreach ($editorNodes as $editorNode) {
            $editor[] = trim(preg_replace('/\s+/', ' ', $editorNode->nodeValue));
        }

        return $editor;
    }

    public function getDocumentPublisher(DOMXPath $xpath): string
    {
        $publisherNode = $xpath->query('//tei:publicationStmt//tei:publisher//tei:name[@type="org"]');

        $publisher = '';

        if (!empty($publisherNode->item(0)->nodeValue)) {
            $publisher = trim(preg_replace('/\s+/', ' ', $publisherNode->item(0)->nodeValue));
        }

        return $publisher;
    }

    public function getPublicationPlace(DOMXPath $xpath): string
    {
        $publicationPlaceNode = $xpath->query('//tei:publicationStmt//tei:pubPlace//tei:name[@type="place"]');

        $publicationPlace = '';

        if (!empty($publicationPlaceNode->item(0)->nodeValue)) {
            $publicationPlace = trim(preg_replace('/\s+/', ' ', $publicationPlaceNode->item(0)->nodeValue));
        }

        return $publicationPlace;
    }

    public function getVolumePart(DOMXPath $xpath): string
    {
        $volumePartNode = $xpath->query('//tei:title[@level = "m"]/text()');

        $volumePart = '';

        if (!empty($volumePartNode->item(0))) {
            $volumePart = $volumePartNode->item(0)->data;
            $volumePart = trim(preg_replace('/\s+/', ' ', $volumePart));
        }

        return $volumePart;
    }

    public function getProject(DOMXPath $xpath): string
    {
        $projectNode = $xpath->query('//tei:title[@level = "s"]/text()');

        $project = '';

        if (!empty($projectNode->item(0))) {
            $project = $projectNode->item(0)->data;
            $project = trim(preg_replace('/\s+/', ' ', $project));
        }

        return $project;
    }

    public function getMarker(DOMXPath $xpath): string
    {
        $markerNodes = $xpath->query('//tei:respStmt//tei:name[@type="person"]');

        $marker = '';

        foreach ($markerNodes as $markerNode) {
            $marker = trim(preg_replace('/\s+/', ' ', $markerNode->nodeValue));
        }

        return $marker;
    }

    public function getCountry(DOMXPath $xpath): string
    {
        $countryNode = $xpath->query('//tei:country');

        $country = '';

        if ($countryNode->item(0)) {
            $country = $countryNode->item(0)->nodeValue;
        }

        return $country;
    }

    public function getDestinationPlace(DOMXPath $xpath): string
    {
        $destinationPlaceNode = $xpath->query('//tei:name[@type="place" and @subtype="dtn"]');

        $destinationPlace = '';

        if ($destinationPlaceNode->item(0)) {
            $destinationPlace = $destinationPlaceNode->item(0)->nodeValue;
        }

        return $destinationPlace;
    }

    public function getDoctypeNotes(DOMXPath $xpath, string $id): array
    {
        $notesNodes = $xpath->query('//tei:text[@xml:lang="ger"]//tei:div//tei:div//tei:note');

        $doctypeNotes = [];

        if (is_iterable($notesNodes) && !empty($notesNodes)) {
            $notes = [];
            foreach ($notesNodes as $key => $notesNode) {
                if (!empty($notesNodes->item(0)->nodeValue) && !empty($id)) {
                    $note = trim(preg_replace('/\s+/', ' ', $notesNode->nodeValue));
                    $notes[] = $note;
                    $doctypeNotes[$id][] = ['id' => $id.'_note_'.++$key, 'article_id' => $id, 'doctype' => 'note', 'note' => $note];
                }
            }
        }

        return $doctypeNotes;
    }

    public function getEntities(DOMXPath $xpath): array
    {
        $documentGndsNodes = $xpath->query('//tei:text[@xml:lang="ger"]//tei:name');

        $entities = [];
        $documentEntities = [];

        foreach ($documentGndsNodes as $documentGndsNode) {
            if (is_iterable($documentGndsNode->childNodes)) {
                $entityName = '';
                foreach ($documentGndsNode->childNodes as $childNode) {
                    if ('#text' === $childNode->nodeName && !empty($childNode->data) && ',' !== $childNode->data) {
                        $entityName .= ' '.trim(preg_replace('/\s+/', ' ', $childNode->data));
                    } else {
                        foreach ($childNode->childNodes as $item) {
                            if (!empty($item->data)) {
                                $entityName .= ' '.trim(preg_replace('/\s+/', ' ', $item->data));
                            }
                        }
                    }
                }
            }

            foreach ($documentGndsNode->attributes as $attribute) {
                if (false !== strpos($attribute->nodeValue, 'gnd')) {
                    $gnd = str_replace('gnd:', '', $attribute->nodeValue);
                    $documentEntities[] = $gnd;
                } else {
                    $type = $attribute->nodeValue;
                }
            }

            if ((isset($entityName) && !empty($entityName)) && (isset($type) && !empty($type)) && (isset($gnd) && !empty($gnd))) {
                $entities[] = ['doctype' => 'entity', 'name' => trim($entityName), 'entity_type' => $type, 'gnd' => $gnd];
                unset($gnd);
                unset($type);
                unset($entityName);
            }
        }

        return $entities;
    }

    public function getFreeKeywords(DOMXPath $xpath): array
    {
        $freeKeyWordNodes = $xpath->query('//tei:keywords[@scheme = "frei"]/tei:term');

        $freeKeywords = [];

        if (is_iterable($freeKeyWordNodes)) {
            foreach ($freeKeyWordNodes as $freeKeyWordNode) {
                if (!empty($freeKeyWordNode->nodeValue)) {
                    $freeKeyword = trim(preg_replace('/\s+/', ' ', $freeKeyWordNode->nodeValue));
                    $freeKeywords[] = $freeKeyword;
                }
            }
        }

        $freeKeyWordNodes = $xpath->query('//tei:keywords[@scheme = "free"]/tei:term');

        if (is_iterable($freeKeyWordNodes)) {
            foreach ($freeKeyWordNodes as $freeKeyWordNode) {
                if (!empty($freeKeyWordNode->nodeValue)) {
                    $freeKeyword = trim(preg_replace('/\s+/', ' ', $freeKeyWordNode->nodeValue));
                    $freeKeywords[] = $freeKeyword;
                }
            }
        }

        return $freeKeywords;
    }

    public function getFulltext(DOMXPath $xpath): string
    {
        $fulltextNodeList = $xpath->query('//tei:body//tei:div/descendant::text()');

        $fulltext = '';

        foreach ($fulltextNodeList as $fulltextNode) {
            $fulltext .= $fulltextNode->data;
        }

        if (!empty($fulltext)) {
            $fulltext = trim(preg_replace('/\s+/', ' ', $fulltext));
        }

        return $fulltext;
    }

    public function getGndKeywords(DOMXPath $xpath): array
    {
        $gndKeyWordNodes = $xpath->query('//tei:keywords[@scheme = "#gnd"]/tei:term');

        $gndKeywords = [];

        if (is_iterable($gndKeyWordNodes)) {
            foreach ($gndKeyWordNodes as $gndKeyWordNode) {
                if (!empty($gndKeyWordNode->nodeValue)) {
                    $gndKeyword = trim(preg_replace('/\s+/', ' ', $gndKeyWordNode->nodeValue));
                    $gndKeywords[] = $gndKeyword;
                }
            }
        }

        return $gndKeywords;
    }

    public function getGraphics(array $imageIds, array $imageUrls): array
    {
        $graphics = [];

        if (!empty($imageIds) && !empty($imageUrls)) {
            foreach ($imageIds as $key => $imageId) {
                if (!empty($imageId) && !empty($imageUrls[$key])) {
                    $graphics[$imageId] = $imageUrls[$key];
                }
            }
        }

        return $graphics;
    }

    public function getId(DOMXPath $xpath): string
    {
        $idNode = $xpath->query('//tei:text/@xml:id');

        $id = '';

        $id = $idNode->item(0)->nodeValue;

        return $id;
    }

    public function getImageIds(DOMXPath $xpath): array
    {
        $imageIdsNodes = $xpath->query('//tei:graphic/@xml:id');

        $imageIds = [];

        if (is_iterable($imageIdsNodes)) {
            foreach ($imageIdsNodes as $imageIdsNode) {
                if (!empty($imageIdsNode->nodeValue)) {
                    $imageId = trim(preg_replace('/\s+/', ' ', $imageIdsNode->nodeValue));
                    $imageIds[] = $imageId;
                }
            }
        }

        return $imageIds;
    }

    public function getImageUrls(DOMXPath $xpath): array
    {
        $imageUrlssNodes = $xpath->query('//tei:graphic/@url');

        $imageUrls = [];

        if (is_iterable($imageUrlssNodes)) {
            foreach ($imageUrlssNodes as $imageUrlssNode) {
                if (!empty($imageUrlssNode->nodeValue)) {
                    $imageUrl = trim(preg_replace('/\s+/', ' ', $imageUrlssNode->nodeValue));
                    $imageUrls[] = $imageUrl;
                }
            }
        }

        return $imageUrls;
    }

    public function getInstitution(DOMXPath $xpath): string
    {
        $institutionNode = $xpath->query('//tei:institution');

        $institution = '';

        if ($institutionNode->item(0)) {
            $institution = $institutionNode->item(0)->nodeValue;
        }

        return $institution;
    }

    public function getLanguage(DOMXPath $xpath): string
    {
        $languageNode = $xpath->query('//tei:text//@xml:lang');

        $language = '';

        if ($languageNode->item(0)) {
            $language = $languageNode->item(0)->nodeValue;
            $language = $this->documentLanguages[$language];
        }

        return $language;
    }

    public function getLicense(DOMXPath $xpath): string
    {
        $licenseNode = $xpath->query('//tei:licence');

        $license = '';

        if ($licenseNode->item(0)) {
            $license = $licenseNode->item(0)->nodeValue;
        }

        return $license;
    }

    public function getNodeChilds($pagesNode, &$ele): array
    {
        if (isset($pagesNode->childNodes)) {
            foreach ($pagesNode->childNodes as $childNode) {
                $ele[] = $childNode;
                if ($childNode->childNodes) {
                    foreach ($childNode->childNodes as $childChildNode) {
                        $ele[] = $childChildNode;
                        $this->getNodeChilds($childChildNode, $ele);
                    }
                }
            }
        }

        return $ele;
    }

    public function getNumberOfPages(DOMXPath $xpath): ?int
    {
        $numberOfPagesNode = $xpath->query('//tei:pb');

        $numberOfPages = null;

        if ($numberOfPagesNode->count()) {
            $numberOfPages = $numberOfPagesNode->count();
        }

        return $numberOfPages;
    }

    public function getOriginDate(DOMXPath $xpath): string
    {
        $originDateNode = $xpath->query('//tei:date/text()');

        $originDate = '';

        if ($originDateNode->item(0)) {
            $originDate = $originDateNode->item(0)->nodeValue;
        }

        return $originDate;
    }

    public function getOriginPlace(DOMXPath $xpath): string
    {
        $originPlaceNode = $xpath->query('//tei:name[@type="place" and @subtype="orn"]');

        $originPlace = '';

        if ($originPlaceNode->item(0)) {
            $originPlace = $originPlaceNode->item(0)->nodeValue;
        }

        return $originPlace;
    }

    public function getPublicationDate(DOMXPath $xpath): string
    {
        $publicationDateNode = $xpath->query('//date[@type = "orn"]');

        $publicationDate = '';

        if ($publicationDateNode->item(0)) {
            $publicationDate = $publicationDateNode->item(0)->nodeValue;
        }

        if (empty($publicationDate)) {
            $publicationDateNode = $xpath->query('//tei:date/text()');

            if ($publicationDateNode->item(0)) {
                $publicationDate = $publicationDateNode->item(0)->nodeValue;
            }
        }

        return $publicationDate;
    }

    public function getRecipient(DOMXPath $xpath): string
    {
        $recipientNode = $xpath->query('//tei:name[@type="person" and @subtype="rcp"]');

        $recipient = '';

        if ($recipientNode->item(0)) {
            $recipient = $recipientNode->item(0)->nodeValue;
            $recipient = trim(preg_replace('/\s+/', ' ', $recipient));
        }

        return $recipient;
    }

    public function getReference(DOMXPath $xpath): string
    {
        $referenceNode = $xpath->query('//tei:relatedItem[@type = "letter" and @subtype = "related"]/tei:ref');

        $reference = '';

        if ($referenceNode->item(0)) {
            $referenceText = $referenceNode->item(0)->nodeValue;
            $referenceText = trim(preg_replace('/\s+/', ' ', $referenceText));
            $refLink = $referenceNode->item(0)->attributes->item(0)->textContent;
            $documentId = array_reverse(explode('/', $refLink))[0];

            if (str_contains($documentId, '.')) {
                $documentId = explode('.', $documentId)[0];
                $documentUrl = $this->mainDomain.$this->router->generate('_detail', ['id' => $documentId]);
            }

            if (!empty($referenceText) && !empty($documentUrl)) {
                $reference = $referenceText.' ('.$documentUrl.')';
            } elseif (!empty($referenceText)) {
                $reference = $referenceText;
            }
        }

        return $reference;
    }

    public function getRelatedItems(DOMXPath $xpath): array
    {
        $relatedItemNodes = $xpath->query('//tei:relatedItem[@type="letter" and not(@subtype)]//tei:ref');

        $relatedItems = [];

        if (is_iterable($relatedItemNodes)) {
            foreach ($relatedItemNodes as $relatedItemNode) {
                if (!empty($relatedItemNode->nodeValue)) {
                    $relatedItemText = trim(preg_replace('/\s+/', ' ', $relatedItemNode->nodeValue));
                    $refLink = $relatedItemNode->attributes->item(0)->textContent;
                    $documentId = array_reverse(explode('/', $refLink))[0];

                    if (str_contains($documentId, '.')) {
                        $documentId = explode('.', $documentId)[0];
                        $documentUrl = $this->mainDomain.$this->router->generate('_detail', ['id' => $documentId]);
                    }

                    if (!empty($relatedItemText) && !empty($documentUrl)) {
                        $relatedItem = $relatedItemText.' ('.$documentUrl.')';
                    } elseif (!empty($relatedItemText)) {
                        $relatedItem = $relatedItemText;
                    }

                    $relatedItems[] = $relatedItem;
                }
            }
        }

        return $relatedItems;
    }

    public function getRepository(DOMXPath $xpath): string
    {
        $repositoryNode = $xpath->query('//tei:repository');

        $repository = '';

        if ($repositoryNode->item(0)) {
            $repository = $repositoryNode->item(0)->nodeValue;
        }

        return $repository;
    }

    public function getResponse(DOMXPath $xpath): string
    {
        $responseNode = $xpath->query('//tei:relatedItem[@type = "letter" and @subtype = "response"]/tei:ref');

        $response = '';

        if ($responseNode->item(0)) {
            $responseText = $responseNode->item(0)->nodeValue;
            $responseText = trim(preg_replace('/\s+/', ' ', $responseText));
            $refLink = $responseNode->item(0)->attributes->item(0)->textContent;
            $documentId = array_reverse(explode('/', $refLink))[0];

            if (str_contains($documentId, '.')) {
                $documentId = explode('.', $documentId)[0];
                $documentUrl = $this->mainDomain.$this->router->generate('_detail', ['id' => $documentId]);
            }

            if (!empty($responseText) && !empty($documentUrl)) {
                $response = $responseText.' ('.$documentUrl.')';
            } elseif (!empty($responseText)) {
                $response = $responseText;
            }
        }

        return $response;
    }

    public function getScriptSource(DOMXPath $xpath): string
    {
        $supportDescNode = $xpath->query('//tei:supportDesc/tei:extent/text()');

        $scriptSource = '';

        if (!empty($supportDescNode->item(0)->data)) {
            $scriptSource .= trim(preg_replace('/\s+/', ' ', $supportDescNode->item(0)->data)).' ';
        }

        $heightNode = $xpath->query('//tei:supportDesc/tei:extent/tei:dimensions/tei:height');
        if (!empty($heightNode->item(0)->nodeValue)) {
            $height = $heightNode->item(0)->nodeValue;
        }

        $widthNode = $xpath->query('//tei:supportDesc/tei:extent/tei:dimensions/tei:width');
        if (!empty($widthNode->item(0)->nodeValue)) {
            $width = $widthNode->item(0)->nodeValue;
        }

        $unitNode = $xpath->query('//tei:supportDesc/tei:extent/tei:dimensions/@unit');
        if (!empty($unitNode->item(0)->nodeValue)) {
            $unit = $unitNode->item(0)->nodeValue;
        }

        if ((isset($height) && !empty($height)) &&
            (isset($width) && !empty($width)) &&
            (isset($unit) && !empty($unit))) {
            $scriptSource .= $height.' x '.$width.' '.$unit.'.';
        }

        $bindingDescNode = $xpath->query('//tei:bindingDesc/tei:p/text()');
        if (!empty($bindingDescNode->item(0)->data)) {
            $scriptSource .= ' '.trim(preg_replace('/\s+/', ' ', $bindingDescNode->item(0)->data));
        }

        return $scriptSource;
    }

    public function getSettlement(DOMXPath $xpath): string
    {
        $settlementNode = $xpath->query('//tei:settlement');

        $settlement = '';

        if ($settlementNode->item(0)) {
            $settlement = $settlementNode->item(0)->nodeValue;
        }

        return $settlement;
    }

    public function getShelfmark(DOMXPath $xpath): string
    {
        $shelfmarkNode = $xpath->query('//tei:idno');

        $shelfmark = '';

        if ($shelfmarkNode->item(0)) {
            $shelfmark = trim(preg_replace('/\s+/', ' ', $shelfmarkNode->item(0)->nodeValue));
        }

        return $shelfmark;
    }

    public function getShortTitle(DOMXPath $xpath): string
    {
        $shortTitleNodeList = $xpath->query('//tei:title[@level = "a"]//tei:name/text()');

        $shortTitle = '';

        if (null !== $shortTitleNodeList->item(0)) {
            $shortTitle = $shortTitleNodeList->item(0)->data;
            $shortTitle = trim(preg_replace('/\s+/', ' ', $shortTitle));
        }

        if (!isset($shortTitle)) {
            $shortTitleNodeList = $xpath->query('//tei:title[@type = "short"]/descendant::text()');
            $shortTitle = '';
            foreach ($shortTitleNodeList as $k => $shortTitleNode) {
                $shortTitle .= $shortTitleNode->data;
            }

            $shortTitle = trim(preg_replace('/\s+/', ' ', $shortTitle));
        }

        return $shortTitle;
    }

    public function getSourceDescription(DOMXPath $xpath): string
    {
        $sourceDescriptionNodeList = $xpath->query('//tei:sourceDesc/descendant::text()');

        $sourceDescription = '';

        foreach ($sourceDescriptionNodeList as $sourceDescriptionNode) {
            $sourceDescription .= $sourceDescriptionNode->data;
        }

        if (!empty($sourceDescription)) {
            $sourceDescription = trim(preg_replace('/\s+/', ' ', $sourceDescription));
        }

        return $sourceDescription;
    }

    public function getTitle(DOMXPath $xpath): string
    {
        $titleNodeList = $xpath->query('//tei:title[@level = "a"]//tei:name/text()');

        $title = '';

        if (null !== $titleNodeList->item(0)) {
            $title = $titleNodeList->item(0)->data;
            $title = trim(preg_replace('/\s+/', ' ', $title));
        }

        if (!isset($title)) {
            $titleNodeList = $xpath->query('//tei:title[@type = "desc"]/descendant::text()');

            $title = '';

            foreach ($titleNodeList as $k => $titleNode) {
                $title .= $titleNode->data;
            }

            $title = trim(preg_replace('/\s+/', ' ', $title));
        }

        if (empty($title)) {
            $titleNodeList = $xpath->query('//tei:title/text()');

            if (null !== $titleNodeList->item(0)) {
                $title = $titleNodeList->item(0)->data;
                $title = trim(preg_replace('/\s+/', ' ', $title));
            }
        }
        return $title;
    }

    public function getWriters(DOMXPath $xpath): array
    {
        $writerNodes = $xpath->query('//tei:handNote');

        $writers = [];

        if (is_iterable($writerNodes)) {
            foreach ($writerNodes as $writerNode) {
                foreach ($writerNode->attributes as $attribute) {
                    if ('major' === $attribute->nodeValue) {
                        $textAddition = '– (Grundschicht)';
                    }
                }

                if (!empty($writerNode->textContent)) {
                    $writer = trim(preg_replace('/\s+/', ' ', $writerNode->textContent));
                    if (isset($textAddition)) {
                        $writer .= ' '.$textAddition;
                    }
                }

                $writers[] = $writer;
                unset($textAddition);
            }
        }

        return $writers;
    }

    public function getLocation(DOMXPath $xpath): string
    {
        $locationNode = $xpath->query('//tei:title[@level = "a"]//tei:name/text()');

        $location = '';

        if (null !== $locationNode->item(0)) {
            $location = $locationNode->item(0)->data;
            $location = trim(preg_replace('/\s+/', ' ', $location));
        }

        return $location;
    }

    public function getDocumentOwnGNDs(DOMXPath $xpath): ?array
    {
        $documentOwnGNDsNodes = $xpath->query('//tei:titleStmt//tei:title[@level = "a"]//tei:name');

        $documentOwnGNDs = [];

        foreach ($documentOwnGNDsNodes as $documentOwnGNDsNode) {
            foreach ($documentOwnGNDsNode->attributes as $attribute)
                if ('ref' === $attribute->nodeName) {
                    $gnd = explode(':', $attribute->nodeValue)[1];
                    $documentOwnGNDs[] = $gnd;
                    unset($gnd);
                }
        }

        return $documentOwnGNDs;
    }

    public function getPageFrom(DOMXPath $xpath): int
    {
        $pageFromNode = $xpath->query('//tei:biblScope/@from');

        $pageFrom = '';

        if ($pageFromNode->item(0)) {
            $pageFrom = intval($pageFromNode->item(0)->nodeValue);
        }

        return $pageFrom;
    }

    public function getPageTo(DOMXPath $xpath): int
    {
        $pageToNode = $xpath->query('//tei:biblScope/@to');

        $pageTo = '';

        if ($pageToNode->item(0)) {
            $pageTo = intval($pageToNode->item(0)->nodeValue);
        }

        return $pageTo;
    }
}
