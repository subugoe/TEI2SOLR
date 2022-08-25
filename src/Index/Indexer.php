<?php

declare(strict_types=1);

namespace Subugoe\TEI2SOLRBundle\Index;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Psr\Log\LoggerInterface;
use Solarium\Client;
use Subugoe\TEI2SOLRBundle\Model\SolrDocument;
use Subugoe\TEI2SOLRBundle\Service\EditedTextService;
use Subugoe\TEI2SOLRBundle\Service\PreProcessingService;
use Subugoe\TEI2SOLRBundle\Service\TranscriptionService;
use Subugoe\TEI2SOLRBundle\Transform\MetadataTransformerInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Indexer implements IndexerInterface
{
    /**
     * @var string
     */
    private const ABSTRACT_XPATH = '//tei:abstract';

    /**
     * @var string
     */
    private const ARTICLE_DOC_TYPE = 'article';

    /**
     * @var string
     */
    private const DOCUMENT_ID_XPATH = '//tei:text/@xml:id';

    /**
     * @var string
     */
    private const DOCUMENT_ID_XPATH_ALT = '//tei:TEI/@xml:id';

    /**
     * @var string
     */
    private const ENTITY_DOC_TYPE = 'entity';

    /**
     * @var string
     */
    private const FULLTEXT_XPATH = '//tei:body//tei:div/descendant::text()';

    /**
     * @var string
     */
    private const GNDS_XPATH = '//tei:text//tei:name[@type="place"]';

    /**
     * @var string
     */
    private const LITERATURE_DOC_TYPE = 'literature';

    /**
     * @var string
     */
    private const LITERATURE_XPATH = '//tei:text//tei:body//tei:listBibl//tei:bibl';

    /**
     * @var string
     */
    private const NAMESPACE_PREFIX = 'tei';

    /**
     * @var string
     */
    private const NOTE_DOC_TYPE = 'note';

    /**
     * @var string
     */
    private const NOTES_XPATH = '//tei:text[@xml:lang="ger"]//tei:div//tei:div//tei:note';

    /**
     * @var string
     */
    private const ORIGIN_PLACE_XPATH = '//tei:name[@type="place" and @subtype="orn"]/@ref';

    /**
     * @var string
     */
    private const PAGE_DOC_TYPE = 'page';

    /**
     * @var string
     */
    private const PAGES_XPATH = '//tei:body';

    /**
     * @var string
     */
    private const TEI_NAMESPACE = 'http://www.tei-c.org/ns/1.0';

    private ?bool $addArticlesTOGnd;

    private ?bool $editedTextRemoveHyphen;

    private ?string $geonameFilesDir;

    private ?string $gndApi;

    private ?string $gndFilesDir;

    private ?bool $indexDoctypeNotes;

    private ?bool $indexEntities;

    private ?bool $indexPages;

    private ?bool $indexWikidata;

    private ?string $litDir;

    private ?array $literaturDataElements;

    private ?string $teiDir = null;

    private ?string $teiImportLogFile;

    private ?string $teiSampleDir = null;

    private ?string $transformationFields;

    private ?string $wikidataFilesDir;

    private ?string $wikipediaFilesDir;

    public function __construct(
        private Client $client, private PreProcessingService $preProcessingService, private TranscriptionService $transcriptionService, private EditedTextService $editedTextService, private MetadataTransformerInterface $metadataTransformer, private LoggerInterface $logger)
    {
    }

    public function deleteSolrIndex(): void
    {
        $update = $this->client->createUpdate();
        $update->addDeleteQuery('*:*');
        $update->addCommit();

        $this->client->execute($update);
    }

    public function getTextVersions(string $filePath, array $graphics = []): SolrDocument
    {
        $doc = new DOMDocument();
        $doc->load($filePath, LIBXML_NOBLANKS);

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace(self::NAMESPACE_PREFIX, self::TEI_NAMESPACE);

        $pagesNodes = $xpath->query(self::PAGES_XPATH);
        /** @var DOMElement $body */
        $body = $pagesNodes[0];
        $this->transcriptionService->setGraphics($graphics);
        $this->editedTextService->setGraphics($graphics);
        $pbCount = $this->metadataTransformer->getNumberOfPages($xpath);

        if (0 === $pbCount) {
            $pages = $this->preProcessingService->getSinglePage($body);
        } else {
            $pages = $this->preProcessingService->getMultiplePages($body);
            array_shift($pages);
        }

        $pageLevelEditedText = [];
        $pageLevelTranscriptedText = [];
        $pagesGndsUuids = [];
        $pagesNotes = [];
        $pagesDates = [];
        $pagesWorks = [];
        $pagesAllAnnotationIds = [];

        if (is_iterable($pages)) {
            foreach ($pages as $key => $page) {
                $pageIndex = $key;
                $transcriptedDoc = $this->transcriptionService->transformPage($page);
                $pageLevelTranscriptedText[] = htmlspecialchars_decode($transcriptedDoc->saveHTML());
                $editedDoc = $this->editedTextService->transformPage($page);
                $pagesGndsUuids[$pageIndex] = $this->editedTextService->getGndsUuids();
                $pagesNotes[$pageIndex] = $this->editedTextService->getNotes();
                $pagesDates[$pageIndex] = $this->editedTextService->getDates();
                $pagesWorks[$pageIndex] = $this->editedTextService->getWorks();
                $pagesAllAnnotationIds[$pageIndex] = $this->editedTextService->getAllAnnotationIds();
                $pageLevelEditedText[] = htmlspecialchars_decode($editedDoc->saveHTML());
                $this->editedTextService->clear();
            }
        }

        $this->preProcessingService->clear();
        $gndsUuids = [...$pagesGndsUuids];
        $documentLevelTranscriptedText = '';

        foreach ($pageLevelTranscriptedText as $singlePageTranscriptedText) {
            $documentLevelTranscriptedText .= $singlePageTranscriptedText;
        }

        $documentLevelEditedText = '';

        foreach ($pageLevelEditedText as $singlePageEditedText) {
            $documentLevelEditedText .= $singlePageEditedText;
        }

        $solrDocument = new SolrDocument();
        $solrDocument->setTranscriptedText($documentLevelTranscriptedText);
        $solrDocument->setPageLevelTranscriptedText($pageLevelTranscriptedText);
        $solrDocument->setEditedText($documentLevelEditedText);
        $solrDocument->setPageLevelEditedText($pageLevelEditedText);
        $solrDocument->setGndsUuids($gndsUuids);
        $solrDocument->setPagesGndsUuids($pagesGndsUuids);
        $solrDocument->setPagesNotes($pagesNotes);
        $solrDocument->setPagesDates($pagesDates);
        $solrDocument->setPagesWorks($pagesWorks);
        $solrDocument->setAllAnnotationIds($pagesAllAnnotationIds);

        return $solrDocument;
    }

    public function literatureToSolr(): void
    {
        $this->client->getEndpoint()->setOptions(['timeout' => 60, 'index_timeout' => 60]);
        $finder = new Finder();
        $finder->files()->in($this->litDir);
        foreach ($finder as $file) {
            libxml_use_internal_errors(true);
            $doc = new \DOMDocument();
            $doc->load($file->getRealPath());
            if (!libxml_get_errors()) {
                $xpath = new \DOMXPath($doc);
                $xpath->registerNamespace(self::NAMESPACE_PREFIX, self::TEI_NAMESPACE);
                $literature = $xpath->query(self::LITERATURE_XPATH);

                foreach ($literature as $literatureItem) {
                    $update = $this->client->createUpdate();
                    $litdoc = $update->createDocument();
                    $uri = [];
                    $author = [];
                    $publisher = [];
                    $pubPlace = [];
                    $edition = [];

                    foreach ($literatureItem->childNodes as $childNode) {
                        $id = str_replace('_', ' ', $literatureItem->attributes->item(0)->textContent);
                        $litdoc->id = $id;
                        $litdoc->doctype = self::LITERATURE_DOC_TYPE;

                        if ('#text' !== $childNode->nodeName) {
                            $text = trim(preg_replace('#\s+#', ' ', $childNode->nodeValue));

                            if ('relatedItem' === $childNode->nodeName) {
                                foreach ($childNode->childNodes as $childChildNode) {
                                    if ('ref' === $childChildNode->nodeName) {
                                        $ref = $childChildNode->attributes->item(0)->nodeValue;

                                        if ('_' !== $ref) {
                                            $uri[] = $ref;
                                        }
                                    }
                                }
                            } elseif ('title' === $childNode->nodeName) {
                                $name = 'title_';

                                if (!empty($childNode->attributes->item(0)->nodeValue)) {
                                    $name .= $childNode->attributes->item(0)->nodeValue;
                                }

                                if (!empty($childNode->attributes->item(1)->nodeValue)) {
                                    $name .= '_'.$childNode->attributes->item(1)->nodeValue;
                                }

                                if (isset($this->literaturDataElements[$name])) {
                                    $solrFieldName = $this->literaturDataElements[$name];
                                }

                                if (!empty($solrFieldName) && !empty($text)) {
                                    $litdoc->$solrFieldName = $text;
                                }
                            } elseif ('author' === $childNode->nodeName) {
                                foreach ($childNode->childNodes as $item) {
                                    if (!empty($item->nodeValue)) {
                                        $authorElement = trim(preg_replace('#\s+#', ' ', $item->nodeValue));

                                        if (!empty($authorElement)) {
                                            $author[] = $authorElement;
                                        }
                                    }
                                }
                            } elseif ('publisher' === $childNode->nodeName) {
                                foreach ($childNode->childNodes as $item) {
                                    if (!empty($item->nodeValue)) {
                                        $publisherElement = trim(preg_replace('#\s+#', ' ', $item->nodeValue));

                                        if (!empty($publisherElement)) {
                                            $publisher[] = $publisherElement;
                                        }
                                    }
                                }
                            } elseif ('pubPlace' === $childNode->nodeName) {
                                foreach ($childNode->childNodes as $item) {
                                    if (!empty($item->nodeValue)) {
                                        $pubPlaceElement = trim(preg_replace('#\s+#', ' ', $item->nodeValue));

                                        if (!empty($pubPlaceElement)) {
                                            $pubPlace[] = $pubPlaceElement;
                                        }
                                    }
                                }
                            } elseif ('edition' === $childNode->nodeName) {
                                foreach ($childNode->childNodes as $item) {
                                    $editionElement = trim(preg_replace('#\s+#', ' ', $item->nodeValue));

                                    if (!empty($editionElement)) {
                                        $edition[] = $editionElement;
                                    }
                                }
                            } elseif ('idno' === $childNode->nodeName) {
                                $name = 'idno_';

                                if (!empty($childNode->attributes->item(0)->nodeValue)) {
                                    $name .= strtolower($childNode->attributes->item(0)->nodeValue);
                                }

                                if (isset($this->literaturDataElements[$name])) {
                                    $solrFieldName = $this->literaturDataElements[$name];
                                }

                                if (!empty($solrFieldName) && !empty($text)) {
                                    $litdoc->$solrFieldName = $text;
                                }
                            } elseif ('biblScope' === $childNode->nodeName) {
                                $name = 'biblScope_'.$childNode->attributes->item(0)->nodeValue;

                                if (property_exists($childNode->attributes->item(1), 'nodeName') && null !== $childNode->attributes->item(1)->nodeName && 'n' === $childNode->attributes->item(1)->nodeName && !empty($childNode->attributes->item(1)->nodeValue)) {
                                    $name .= '_'.$childNode->attributes->item(1)->nodeValue;
                                }

                                if (isset($this->literaturDataElements[$name])) {
                                    $solrFieldName = $this->literaturDataElements[$name];
                                }

                                if (!empty($solrFieldName) && !empty($text)) {
                                    $litdoc->$solrFieldName = $text;
                                }
                            } else {
                                if (property_exists($childNode, 'nodeName') && null !== $childNode->nodeName) {
                                    $name = strval($childNode->nodeName);
                                }

                                if (isset($this->literaturDataElements[$name])) {
                                    $solrFieldName = $this->literaturDataElements[$name];
                                }

                                if (!empty($solrFieldName) && !empty($text)) {
                                    $litdoc->$solrFieldName = $text;
                                }
                            }
                        }

                        unset($text);
                        unset($name);
                        unset($solrFieldName);
                    }

                    if ([] !== $uri) {
                        $litdoc->uri = $uri;
                    }

                    if ([] !== $author) {
                        $litdoc->literature_author = $author;
                    }

                    if ([] !== $publisher) {
                        $litdoc->publisher = $publisher;
                    }

                    if ([] !== $pubPlace) {
                        $litdoc->pub_place = $pubPlace;
                    }

                    if ([] !== $edition) {
                        $litdoc->edition = $edition;
                    }

                    $update->addDocument($litdoc);
                    $update->addCommit();
                    $this->client->execute($update);
                }
            }
        }

        try {
            $filesystem = new Filesystem();
            $filesystem->remove($this->litDir);
        } catch (IOExceptionInterface $ioException) {
            $this->logger->error(sprintf('Error deleting directory at %s', $ioException->getPath()));
        }
    }

    public function setConfigs(string $teiDir, string $teiSampleDir, string $litDir, string $gndFilesDir, string $geonameFilesDir, string $wikidataFilesDir, string $wikipediaFilesDir, string $teiImportLogFile, array $literaturDataElements, bool $indexPages, bool $indexEntities, bool $indexDoctypeNotes, string $gndApi,
        string $transformationFields, bool $indexWikidata, bool $addArticlesTOGnd, bool $editedTextRemoveHyphen): void
    {
        $this->teiDir = $teiDir;
        $this->teiSampleDir = $teiSampleDir;
        $this->litDir = $litDir;
        $this->gndFilesDir = $gndFilesDir;
        $this->geonameFilesDir = $geonameFilesDir;
        $this->wikidataFilesDir = $wikidataFilesDir;
        $this->wikipediaFilesDir = $wikipediaFilesDir;
        $this->teiImportLogFile = $teiImportLogFile;
        $this->literaturDataElements = $literaturDataElements;
        $this->indexPages = $indexPages;
        $this->indexEntities = $indexEntities;
        $this->indexDoctypeNotes = $indexDoctypeNotes;
        $this->gndApi = $gndApi;
        $this->transformationFields = $transformationFields;
        $this->indexWikidata = $indexWikidata;
        $this->addArticlesTOGnd = $addArticlesTOGnd;
        $this->editedTextRemoveHyphen = $editedTextRemoveHyphen;
    }

    public function teiTosolr(bool $importSampleTei): void
    {
        $this->client->getEndpoint()->setOptions(['timeout' => 60, 'index_timeout' => 60]);
        $finder = new Finder();
        $finder->files()->in($this->teiDir);

        if ($importSampleTei) {
            $finderSample = $this->addSampleTeiFileToProjectTeiFiles();
            $finder->append($finderSample);
        }

        foreach ($finder as $file) {
            libxml_use_internal_errors(true);
            $doc = new \DOMDocument();
            $doc->load($file->getRealPath());

            if (!libxml_get_errors()) {
                $xpath = new \DOMXPath($doc);
                $xpath->registerNamespace(self::NAMESPACE_PREFIX, self::TEI_NAMESPACE);
                $id = $this->getId($xpath);
                $this->indexDocument($doc, $file->getRealPath());

                if ($this->indexDoctypeNotes) {
                    $doctypeNotes = $this->getDoctypeNotes($xpath, $id);

                    if (isset($doctypeNotes) && is_iterable($doctypeNotes)) {
                        $this->indexNotes($doctypeNotes);
                    }
                }

                if ($this->indexEntities) {
                    $entities = $this->getEntities($xpath);

                    if (isset($entities) && is_iterable($entities)) {
                        $this->indexEntities($entities);
                    }
                }
            } else {
                $filesystem = new Filesystem();

                if (!$filesystem->exists($this->teiImportLogFile)) {
                    $filesystem->mkdir($this->teiImportLogFile);
                    $filesystem->touch($this->teiImportLogFile.'teiImportLogs.txt');
                }

                $errors = [];
                foreach (libxml_get_errors() as $key => $error) {
                    if (0 === $key) {
                        $errors[] = explode('/', $error->file)[4].PHP_EOL;
                        $errors[] = '--------------------'.PHP_EOL;
                    }

                    $errors[] = $error->message;
                }

                $filesystem->appendToFile($this->teiImportLogFile.'teiImportLogs.txt', implode('', $errors));

                libxml_clear_errors();
            }
        }

        if ($this->addArticlesTOGnd) {
            $this->addArticlesToGnd();
        }

        try {
            $filesystem = new Filesystem();
            $filesystem->remove($this->teiDir);
        } catch (IOExceptionInterface $ioException) {
            $this->logger->error(sprintf('Error deleting directory at %s', $ioException->getPath()));
        }

        try {
            $filesystem = new Filesystem();
            $filesystem->remove($this->teiSampleDir);
        } catch (IOExceptionInterface $ioException) {
            $this->logger->error(sprintf('Error deleting directory at %s', $ioException->getPath()));
        }
    }

    private function addArticlesToGnd(): void
    {
        $gndQuery = sprintf('doctype:%s', 'entity');
        $gndSelect = $this->client->createSelect()
            ->setQuery($gndQuery)
            ->setRows(1000);
        $gndDocuments = $this->client->select($gndSelect)->getDocuments();

        if (!empty($gndDocuments) && is_iterable($gndDocuments)) {
            foreach ($gndDocuments as $key => $gndDocument) {
                $articleQuery = sprintf('doctype:%s AND gnds:%s', 'article', $gndDocument['id']);
                $articleSelect = $this->client->createSelect()
                    ->setQuery($articleQuery)
                    ->setFields(['id'])
                    ->setRows(1000);
                $articleDocuments = $this->client->select($articleSelect)->getDocuments();

                if (!empty($articleDocuments) && is_iterable($articleDocuments)) {
                    $articles = [];

                    foreach ($articleDocuments as $key => $articleDocument) {
                        $articles[] = $articleDocument['id'];
                    }

                    $update = $this->client->createUpdate();
                    $gndDoc = $update->createDocument();
                    $gndDoc->id = $gndDocument['id'];
                    $gndDoc->doctype = $gndDocument['doctype'];
                    $gndDoc->entity_name = $gndDocument['entity_name'];
                    $gndDoc->entitytype = $gndDocument['entitytype'];
                    $gndDoc->mostly_used_name = $gndDocument['mostly_used_name'];
                    $gndDoc->alternatively_name = $gndDocument['alternatively_name'];
                    $gndDoc->latitude = $gndDocument['latitude'];
                    $gndDoc->longitude = $gndDocument['longitude'];
                    $gndDoc->geoname_id = $gndDocument['geoname_id'];
                    $gndDoc->wikidata_id = $gndDocument['wikidata_id'];
                    $gndDoc->wikidata_id = $gndDocument['wikidata_id'];
                    $gndDoc->wikidata_url = $gndDocument['wikidata_url'];
                    $gndDoc->de_wikipedia_url = $gndDocument['de_wikipedia_url'];
                    $gndDoc->articles = $articles;
                    $update->addDocument($gndDoc);
                    $update->addCommit();
                    $this->client->execute($update);
                }
            }
        }
    }

    private function addSampleTeiFileToProjectTeiFiles()
    {
        $finderSample = new Finder();

        return $finderSample->files()->in($this->teiSampleDir);
    }

    private function convertSoftHyphenToHyphen(string $text): string
    {
        return preg_replace('#\x{00AD}#u', '-', $text);
    }

    private function getAbstracts(DOMXPath $xpath): array
    {
        $abstractNodes = $xpath->query(self::ABSTRACT_XPATH);

        $abstracts = [];

        foreach ($abstractNodes as $abstractNode) {
            $abstracts[] = $abstractNode->nodeValue;
        }

        return $abstracts;
    }

    private function getDoctypeNotes(DOMXPath $xpath, string $id): array
    {
        $notesNodes = $xpath->query(self::NOTES_XPATH);

        $doctypeNotes = [];

        if (is_iterable($notesNodes) && !empty($notesNodes)) {
            foreach ($notesNodes as $key => $notesNode) {
                if (!empty($notesNodes->item(0)->nodeValue) && !empty($id)) {
                    $note = trim(preg_replace('#\s+#', ' ', $notesNode->nodeValue));
                    $doctypeNotes[$id][] = ['id' => $id.'_note_'.++$key, 'article_id' => $id, 'doctype' => self::NOTE_DOC_TYPE, 'note' => $note];
                }
            }
        }

        return $doctypeNotes;
    }

    private function getDocumentGnds(DOMXPath $xpath): array
    {
        $documentGndsNodes = $xpath->query(self::GNDS_XPATH);

        $gnds = [];

        foreach ($documentGndsNodes as $documentGndsNode) {
            foreach ($documentGndsNode->attributes as $attribute) {
                if (false !== stripos($attribute->nodeValue, 'gnd') && !empty($attribute->nodeValue)) {
                    $gndRemoveablePart = explode(':', $attribute->nodeValue)[0];
                    $gnd = str_replace($gndRemoveablePart.':', '', $attribute->nodeValue);
                    $gnds[] = $gnd;
                }
            }
        }

        return array_unique($gnds);
    }

    private function getEntities(DOMXPath $xpath): array
    {
        $entities = [];

        $documentGndsNodes = $xpath->query(self::GNDS_XPATH);

        foreach ($documentGndsNodes as $documentGndsNode) {
            if (is_iterable($documentGndsNode->childNodes)) {
                $entityName = '';
                foreach ($documentGndsNode->childNodes as $childNode) {
                    if ('#text' === $childNode->nodeName && !empty($childNode->data) && ',' !== $childNode->data) {
                        $entityName .= ' '.trim(preg_replace('#\s+#', ' ', $childNode->data));
                    } else {
                        foreach ($childNode->childNodes as $item) {
                            if (!empty($item->data)) {
                                $entityName .= ' '.trim(preg_replace('#\s+#', ' ', $item->data));
                            }
                        }
                    }
                }
            }

            foreach ($documentGndsNode->attributes as $attribute) {
                if (false !== stripos($attribute->nodeValue, 'gnd')) {
                    if (!empty($attribute->nodeValue)) {
                        $gndRemoveablePart = explode(':', $attribute->nodeValue)[0];
                        $gnd = str_replace($gndRemoveablePart.':', '', $attribute->nodeValue);
                    }
                } else {
                    $type = $attribute->nodeValue;
                }
            }

            if ((isset($entityName) && !empty($entityName)) && (isset($type) && !empty($type)) && (isset($gnd) && !empty($gnd))) {
                $entities[] = ['doctype' => self::ENTITY_DOC_TYPE, 'name' => trim($entityName), 'entity_type' => $type, 'gnd' => $gnd];
                unset($gnd);
                unset($type);
                unset($entityName);
            }
        }

        return array_unique($entities, SORT_REGULAR);
    }

    private function getFulltext(DOMXPath $xpath): string
    {
        $fulltextNodeList = $xpath->query(self::FULLTEXT_XPATH);

        $fulltext = '';

        foreach ($fulltextNodeList as $fulltextNode) {
            $fulltext .= $fulltextNode->data;
        }

        if (!empty($fulltext)) {
            $fulltext = trim(preg_replace('#\s+#', ' ', $fulltext));
        }

        return $fulltext;
    }

    private function getGraphics(array $imageIds, array $imageUrls): array
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

    private function getId(DOMXPath $xpath): string
    {
        $idNode = $xpath->query(self::DOCUMENT_ID_XPATH);

        if (!empty($idNode->item(0)->nodeValue)) {
            $id = $idNode->item(0)->nodeValue;
        } else {
            $idNode = $xpath->query(self::DOCUMENT_ID_XPATH_ALT);
            if (!empty($idNode->item(0)->nodeValue)) {
                $id = $idNode->item(0)->nodeValue;
            }
        }

        return $id;
    }

    private function getUuid(): string
    {
        return uuid_create(UUID_TYPE_RANDOM);
    }

    private function indexDocument(DOMDocument $doc, string $file): void
    {
        $transformationFields = [];

        if (!empty($this->transformationFields)) {
            $transformationFields = explode(',', $this->transformationFields);
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace(self::NAMESPACE_PREFIX, self::TEI_NAMESPACE);

        $id = $this->getId($xpath);
        $abstracts = $this->getAbstracts($xpath);
        $docType = self::ARTICLE_DOC_TYPE;

        $imageIds = $this->metadataTransformer->getImageIds($xpath);
        $imageUrls = $this->metadataTransformer->getImageUrls($xpath);
        $graphics = $this->getGraphics($imageIds, $imageUrls);
        $solrDocument = $this->getTextVersions($file, $graphics);
        $pagesTranscription = $solrDocument->getPageLevelTranscriptedText();
        $pagesEdited = $solrDocument->getPageLevelEditedText();
        $pagesGndsUuids = $solrDocument->getPagesGndsUuids();
        $pagesNotes = $solrDocument->getPagesNotes();
        $pagesDates = $solrDocument->getPagesDates();
        $pagesWorks = $solrDocument->getPagesWorks();
        $pagesAllAnnotationIds = $solrDocument->getAllAnnotationIds();

        $update = $this->client->createUpdate();
        $doc = $update->createDocument();

        if (!empty($id)) {
            $doc->id = $id;

            if (in_array('doctype', $transformationFields)) {
                $doc->doctype = $docType;
            }

            if (in_array('location', $transformationFields)) {
                $location = $this->metadataTransformer->getLocation($xpath);

                if (!empty($location)) {
                    $doc->location = $location;
                }
            }

            if (in_array('short_title', $transformationFields)) {
                $shortTitle = $this->metadataTransformer->getShortTitle($xpath);

                if (!empty($shortTitle)) {
                    $doc->short_title = $shortTitle;
                }
            }

            if (in_array('title', $transformationFields)) {
                $title = $this->metadataTransformer->getTitle($xpath);

                if (!empty($title)) {
                    $doc->title = $title;
                }
            }

            if (in_array('origin_place', $transformationFields)) {
                $originPlace = $this->metadataTransformer->getOriginPlace($xpath);

                if (!empty($originPlace)) {
                    $doc->origin_place = $originPlace;
                }
            }

            if (in_array('author', $transformationFields)) {
                $author = $this->metadataTransformer->getAuthor($xpath);

                if (!empty($author)) {
                    $doc->author = $author;
                }
            }

            if (in_array('editor', $transformationFields)) {
                $editor = $this->metadataTransformer->getEditor($xpath);

                if (!empty($editor)) {
                    $doc->editor = $editor;
                }
            }

            if (in_array('publisher', $transformationFields)) {
                $publisher = $this->metadataTransformer->getDocumentPublisher($xpath);

                if (!empty($publisher)) {
                    $doc->publisher = $publisher;
                }
            }

            if (in_array('recipient', $transformationFields)) {
                $recipient = $this->metadataTransformer->getRecipient($xpath);

                if (!empty($recipient)) {
                    $doc->recipient = $recipient;
                }
            }

            if (in_array('origin_date', $transformationFields)) {
                $originDate = $this->metadataTransformer->getOriginDate($xpath);

                if (empty($originDate)) {
                    $doc->origin_date = $originDate;
                }
            }

            if (in_array('destination_place', $transformationFields)) {
                $destinationPlace = $this->metadataTransformer->getDestinationPlace($xpath);

                if (!empty($destinationPlace)) {
                    $doc->destination_place = $destinationPlace;
                }
            }

            if (in_array('license', $transformationFields)) {
                $license = $this->metadataTransformer->getLicense($xpath);

                if (!empty($license)) {
                    $license = trim(preg_replace('#\s+#', ' ', $license));
                    $doc->license = $license;
                }
            }

            if (in_array('license_link', $transformationFields)) {
                $licenseLink = $this->metadataTransformer->getLicenseLink($xpath);

                if (!empty($licenseLink)) {
                    $licenseLink = trim(preg_replace('/\s+/', ' ', $licenseLink));
                    $doc->license_link = $licenseLink;
                }
            }

            if (in_array('language', $transformationFields)) {
                $language = $this->metadataTransformer->getLanguage($xpath);

                if (!empty($language)) {
                    $doc->language = $language;
                }
            }

            if (in_array('reference', $transformationFields)) {
                $reference = $this->metadataTransformer->getReference($xpath);

                if (!empty($reference)) {
                    $doc->reference = $reference;
                }
            }

            if (in_array('response', $transformationFields)) {
                $response = $this->metadataTransformer->getResponse($xpath);

                if (!empty($response)) {
                    $doc->response = $response;
                }
            }

            if (in_array('related_items', $transformationFields)) {
                $relatedItems = $this->metadataTransformer->getRelatedItems($xpath);

                if (!empty($relatedItems)) {
                    $doc->related_items = $relatedItems;
                }
            }

            if (in_array('institution', $transformationFields)) {
                $repository = $this->metadataTransformer->getRepository($xpath);
                $institution = $this->metadataTransformer->getInstitution($xpath);
                $settlement = $this->metadataTransformer->getSettlement($xpath);
                $country = $this->metadataTransformer->getCountry($xpath);

                $institutionDetail = '';
                if (!empty($repository)) {
                    $institutionDetail .= $repository;
                }

                if (!empty($institution)) {
                    $institutionDetail .= ', '.$institution;
                }

                if (!empty($settlement)) {
                    $institutionDetail .= ', '.$settlement;
                }

                if (!empty($country)) {
                    $institutionDetail .= ' ('.$country.')';
                }

                if (!empty($institutionDetail)) {
                    $institution = trim(preg_replace('#\s+#', ' ', $institutionDetail));
                }

                if (!empty($institution)) {
                    $doc->institution = $institution;
                }
            }

            if (in_array('source_description', $transformationFields)) {
                $sourceDescription = $this->metadataTransformer->getSourceDescription($xpath);

                if (!empty($sourceDescription)) {
                    $doc->source_description = $sourceDescription;
                }
            }

            if (in_array('publication_date', $transformationFields)) {
                $publicationDate = $this->metadataTransformer->getPublicationDate($xpath);

                if (!empty($publicationDate)) {
                    $doc->publication_date = $publicationDate;
                }
            }

            if (in_array('publication_place', $transformationFields)) {
                $publicationPlace = $this->metadataTransformer->getPublicationPlace($xpath);

                if (!empty($publicationPlace)) {
                    $doc->publication_place = $publicationPlace;
                }
            }

            if (in_array('volume_part', $transformationFields)) {
                $volumePart = $this->metadataTransformer->getVolumePart($xpath);

                if (!empty($volumePart)) {
                    $doc->volume_part = $volumePart;
                }
            }

            if (in_array('marker', $transformationFields)) {
                $marker = $this->metadataTransformer->getMarker($xpath);

                if (!empty($marker)) {
                    $doc->marker = $marker;
                }
            }

            if (in_array('fulltext', $transformationFields, true)) {
                $fulltext = $this->getFulltext($xpath);

                if (!empty($fulltext)) {
                    $doc->fulltext = $fulltext;
                }
            }

            if (in_array('number_of_pages', $transformationFields, true)) {
                $numberOfPages = $this->metadataTransformer->getNumberOfPages($xpath);

                if (!empty($numberOfPages)) {
                    $doc->number_of_pages = $numberOfPages;
                }
            }

            if (in_array('gnd_keywords', $transformationFields)) {
                $gndKeywords = $this->metadataTransformer->getGndKeywords($xpath);

                if (!empty($gndKeywords)) {
                    $doc->gnd_keyword = $gndKeywords;
                }
            }

            if (in_array('free_keywords', $transformationFields)) {
                $freeKeywords = $this->metadataTransformer->getFreeKeywords($xpath);

                if (!empty($freeKeywords)) {
                    $doc->free_keyword = $freeKeywords;
                }
            }

            if (in_array('shelfmark', $transformationFields)) {
                $shelfmark = $this->metadataTransformer->getShelfmark($xpath);

                if (!empty($shelfmark)) {
                    $doc->shelfmark = $shelfmark;
                }
            }

            if (in_array('script_source', $transformationFields)) {
                $scriptSource = $this->metadataTransformer->getScriptSource($xpath);

                if (!empty($scriptSource)) {
                    $doc->script_source = $scriptSource;
                }
            }

            if (in_array('writers', $transformationFields)) {
                $writers = $this->metadataTransformer->getWriters($xpath);

                if (!empty($writers)) {
                    $doc->writer = $writers;
                }
            }

            if (in_array('image_ids', $transformationFields) && !empty($imageIds)) {
                $doc->image_ids = $imageIds;
            }

            if (in_array('image_urls', $transformationFields) && !empty($imageUrls)) {
                $doc->image_urls = $imageUrls;
            }

            if (in_array('transcripted_text', $transformationFields)) {
                $transcription = $solrDocument->getTranscriptedText();

                if (!empty($transcription)) {
                    $doc->transcripted_text = $transcription;
                }
            }

            if (in_array('edited_text', $transformationFields)) {
                $editedText = $solrDocument->getEditedText();

                if (!empty($editedText)) {
                    if (str_contains($editedText, '-') && $this->editedTextRemoveHyphen) {
                        $editedText = $this->removeHyphen($editedText);
                    }

                    $doc->edited_text = $editedText;
                }
            }

            if (in_array('document_gnds', $transformationFields)) {
                $documentGnds = $this->getDocumentGnds($xpath);

                if (!empty($documentGnds)) {
                    $doc->gnds = $documentGnds;
                }
            }

            if (in_array('project', $transformationFields)) {
                $project = $this->metadataTransformer->getProject($xpath);

                if (!empty($project)) {
                    $doc->project = $project;
                }
            }

            if (in_array('document_own_gnd', $transformationFields)) {
                $documentOwnGnd = $this->metadataTransformer->getDocumentOwnGNDs($xpath);

                if (!empty($documentOwnGnd)) {
                    $doc->article_own_gnds = $documentOwnGnd;
                }
            }

            if (in_array('pages_count', $transformationFields)) {
                $pageFrom = $this->metadataTransformer->getPageFrom($xpath);
                $pageTo = $this->metadataTransformer->getPageTo($xpath);

                if (!empty($pageFrom)) {
                    $doc->page_from = $pageFrom;
                }

                if (!empty($pageTo)) {
                    $doc->page_to = $pageTo;
                }
            }

            if (in_array('existence_period', $transformationFields)) {
                $existenceDate = $this->metadataTransformer->getExistencePeriod($xpath);
                if (!empty($existenceDate)) {
                    $doc->existence_period = $existenceDate;
                }
            }

            if (isset($notes) && !empty($notes)) {
                $doc->notes = $notes;
            }

            if (!empty($numberOfPages) && $numberOfPages && $this->indexPages) {
                for ($i = 0; $i < $numberOfPages; ++$i) {
                    $pageNumber = $i + 1;
                    $update1 = $this->client->createUpdate();
                    $childDoc = $update1->createDocument();
                    $childDoc->id = $id.'_page'.$pageNumber;
                    $childDoc->article_id = $id;
                    $childDoc->article_title = $title;
                    $childDoc->doctype = self::PAGE_DOC_TYPE;
                    $childDoc->page_number = $pageNumber;
                    $childDoc->language = $language;

                    if (isset($abstracts) && !empty($abstracts)) {
                        $abstractUuid = $this->getUuid();
                        $childDoc->page_notes_abstracts = $abstracts;
                        $childDoc->page_notes_abstracts_ids = [$abstractUuid];
                        if (isset($pagesAllAnnotationIds[$i])) {
                            $pagesAllAnnotationIds[$i] = [$abstractUuid, ...$pagesAllAnnotationIds[$i]];
                        }
                    }

                    if (isset($imageUrls[$i]) && !empty($imageUrls[$i])) {
                        $childDoc->image_url = $imageUrls[$i];
                    }

                    if (isset($pagesTranscription[$i]) && !empty($pagesTranscription[$i])) {
                        $childDoc->transcripted_text = $pagesTranscription[$i];
                    }

                    if (isset($pagesEdited[$i]) && !empty($pagesEdited[$i])) {
                        if (str_contains($pagesEdited[$i], '-')) {
                            $pagesEdited[$i] = $this->removeHyphen($pagesEdited[$i]);
                        }

                        $childDoc->edited_text = $pagesEdited[$i];
                    }

                    if (isset($pagesGndsUuids[$i]) && !empty($pagesGndsUuids[$i])) {
                        $childDoc->entities = array_values($pagesGndsUuids[$i]);
                        $childDoc->annotation_ids = array_keys($pagesGndsUuids[$i]);
                    }

                    if (isset($pagesNotes[$i]) && !empty($pagesNotes[$i])) {
                        $childDoc->page_notes = array_values($pagesNotes[$i]);
                        $childDoc->page_notes_ids = array_keys($pagesNotes[$i]);
                    }

                    if (isset($pagesDates[$i]) && !empty($pagesDates[$i])) {
                        $childDoc->page_dates = array_values($pagesDates[$i]);
                        $childDoc->page_dates_ids = array_keys($pagesDates[$i]);
                    }

                    if (isset($pagesWorks[$i]) && !empty($pagesWorks[$i])) {
                        $childDoc->page_works = array_values($pagesWorks[$i]);
                        $childDoc->page_works_ids = array_keys($pagesWorks[$i]);
                    }

                    if (isset($pagesAllAnnotationIds[$i]) && !empty($pagesAllAnnotationIds[$i])) {
                        $childDoc->page_all_annotation_ids = $pagesAllAnnotationIds[$i];
                    }

                    $update->addDocument($childDoc);
                }
            }

            $update->addDocument($doc);
            $update->addCommit();
            $this->client->execute($update);
        }
    }

    private function indexEntities(array $entities): void
    {
        if (isset($entities) && is_iterable($entities)) {
            foreach ($entities as $entity) {
                $latitudes = [];
                $longitudes = [];

                if (!empty($entity['gnd'])) {
                    $localFilePath = $this->gndFilesDir.$entity['gnd'].'.json';

                    if (!file_exists($localFilePath)) {
                        $remoteFilePath = $this->gndApi.$entity['gnd'].'.json';
                        try {
                            $fileContent = @file_get_contents($remoteFilePath, true);

                            if (false === $fileContent) {
                                throw new \Exception($localFilePath.PHP_EOL);
                            }
                        } catch (\Exception $exception) {
                            $this->logger->error($exception->getMessage(), $exception->getTrace());
                        }

                        // TODO: better exception handling to not go here when remote file path failed
                        $gndArr = [];

                        if ($fileContent) {
                            $filesystem = new Filesystem();
                            $filesystem->dumpFile($localFilePath, $fileContent);

                            $gndArr = json_decode($fileContent);
                        }
                    } else {
                        $gndArr = json_decode(@file_get_contents($localFilePath));
                    }

                    if (property_exists($gndArr, 'preferredName') && null !== $gndArr->preferredName && !empty($gndArr->preferredName)) {
                        $preferredName = $gndArr->preferredName;
                    }

                    if (property_exists($gndArr, 'variantName') && null !== $gndArr->variantName && !empty($gndArr->variantName)) {
                        $variantNames = $gndArr->variantName;
                    }

                    $update = $this->client->createUpdate();
                    $doc = $update->createDocument();
                    $doc->id = $entity['gnd'];
                    $doc->entity_name = $entity['name'];
                    $doc->doctype = $entity['doctype'];
                    $doc->entitytype = $entity['entity_type'];

                    if (isset($preferredName) && !empty($preferredName)) {
                        $doc->mostly_used_name = $preferredName;
                    }

                    if (isset($variantNames) && !empty($variantNames)) {
                        $doc->alternatively_name = $variantNames;
                    }

                    if (property_exists($gndArr, 'hasGeometry') && null !== $gndArr->hasGeometry) {
                        foreach ($gndArr->hasGeometry as $gitem) {
                            foreach ($gitem->asWKT as $asWKTitem) {
                                preg_match('#\((.*?)\)#', $asWKTitem, $match);
                                $coordinates = explode(' ', trim($match[1]));
                                $latitudes[] = ltrim($coordinates[1], '+');
                                $longitudes[] = ltrim($coordinates[0], '+');
                            }
                        }
                    }

                    if (isset($latitudes) && !empty($latitudes) && isset($longitudes) && !empty($longitudes)) {
                        $doc->latitude = $latitudes;
                        $doc->longitude = $longitudes;
                    }

                    if ($this->indexWikidata && (property_exists($gndArr, 'sameAs') && null !== $gndArr->sameAs) && !empty($gndArr->sameAs)) {
                        foreach ($gndArr->sameAs as $k => $item) {
                            if (property_exists($item->collection, 'name') && null !== $item->collection->name && 'Wikidata' === $item->collection->name) {
                                $wikidataId = array_reverse(explode('/', $item->id))[0];

                                if (!empty($wikidataId)) {
                                    $doc->wikidata_id = $wikidataId;
                                }
                            } elseif (str_contains($item->id, 'sws.geonames.org')) {
                                $geonameId = array_reverse(explode('/', $item->id))[0];

                                if (!empty($geonameId)) {
                                    $doc->geoname_id = $geonameId;
                                }
                            } elseif (property_exists($item->collection, 'abbr') && null !== $item->collection->abbr && 'dewiki' === $item->collection->abbr) {
                                $wikipedia = $item->id;
                            }
                        }
                    }

                    if (isset($wikipedia) && !empty($wikipedia)) {
                        $doc->de_wikipedia_url = $wikipedia;
                    } elseif (property_exists($gndArr, 'wikipedia') && null !== $gndArr->wikipedia && !empty($gndArr->wikipedia)) {
                        foreach ($gndArr->wikipedia as $k => $item) {
                            if (str_contains($item->id, 'de.wikipedia.org')) {
                                $doc->wikidata_id = $item->id;
                            }
                        }
                    }

                    $update->addDocument($doc);
                    $update->addCommit();
                    $this->client->execute($update);
                }
            }
        }

        try {
            $filesystem = new Filesystem();
            $filesystem->remove($this->gndFilesDir);
        } catch (IOExceptionInterface $ioException) {
            $this->logger->error(sprintf('Error deleting directory at %s', $ioException->getPath()));
        }
    }

    private function indexNotes(array $doctypeNotes): void
    {
        if (isset($doctypeNotes) && is_iterable($doctypeNotes)) {
            foreach ($doctypeNotes as $doctypeNoteArr) {
                foreach ($doctypeNoteArr as $doctypeNote) {
                    if (!empty($doctypeNote['id'])) {
                        $update = $this->client->createUpdate();
                        $doc = $update->createDocument();
                        $doc->id = $doctypeNote['id'];
                        $doc->article_id = $doctypeNote['article_id'];
                        $doc->doctype = $doctypeNote['doctype'];
                        $doc->note = $doctypeNote['note'];
                        $update->addDocument($doc);
                        $update->addCommit();
                        $this->client->execute($update);
                    }
                }
            }
        }
    }

    private function removeHyphen(string $text): string
    {
        $pattern = '/(\w+)-\s(\w)/i';

        return preg_replace_callback(
            $pattern,
            static fn ($match) => $match[1].$match[2],
            $text
        );
    }
}
