<?php

namespace Subugoe\TEI2SOLRBundle\Service;

use DateTime;
use DOMElement;
use DOMNode;
use Subugoe\TEI2SOLRBundle\Import\HTMLDocument;

/**
 * Specialized service to transform TEI to HTML tags to display an edited text.
 * It will also extract additional data like notes, links and entities.
 */
class EditedTextService extends CommonTransformService
{
    protected array $renditions = [
        'centre' => 'left',
        'doubleunderline' => 'letterspace',
        'italic' => 'normal',
        'letterspace' => 'letterspace',
        'right' => 'left',
        'subscript' => 'subscript',
        'smallcaps' => 'letterspace',
        'superscript' => 'superscript',
        'underline' => 'letterspace',
        'wavyunderline' => 'letterspace',
        'bold' => 'bold',
    ];

    private array $allAnnotationIds = [];

    private array $dates = [];

    private array $gndsUuids = [];

    private ?string $lastSegUuid = null;

    private array $notes = [];

    private array $works = [];

    public function clear()
    {
        $this->gndsUuids = [];
        $this->notes = [];
        $this->dates = [];
        $this->works = [];
        $this->allAnnotationIds = [];
    }

    public function createUuid(): ?string
    {
        $uuid = uuid_create(UUID_TYPE_RANDOM);
        $this->allAnnotationIds[] = $uuid;

        return $uuid;
    }

    public function getAllAnnotationIds(): array
    {
        return $this->allAnnotationIds;
    }

    public function getDates(): array
    {
        return $this->dates;
    }

    public function getGndsUuids(): array
    {
        return $this->gndsUuids;
    }

    public function getNotes(): array
    {
        return $this->notes;
    }

    public function getWorks(): array
    {
        return $this->works;
    }

    protected function handleAbbr(DOMNode $teiEl, HTMLDocument $doc): ?DOMNode
    {
        return null;
    }

    protected function handleAdd(DOMNode $teiEl, HTMLDocument $doc): ?DOMNode
    {
        $type = $teiEl->getAttribute('type');

        if ('courseBus' === $type) {
            return null;
        }

        return $doc->span();
    }

    protected function handleBibl(DOMNode $teiEl, HTMLDocument $doc): ?DOMNode
    {
        if ($teiEl->hasChildNodes() && 'ref' === $teiEl->childNodes[0]->nodeName) {
            /** @var DOMElement $ref */
            $ref = $teiEl->childNodes[0];
            $targetValue = $ref->getAttribute('target');

            if ('' !== $targetValue && '0' !== $targetValue) {
                $targetArr = explode('#', $targetValue);
                if (isset($targetArr[1]) && !empty($targetArr[1])) {
                    $text = str_replace('_', ' ', $targetArr[1]);
                    $url = './../literatur/'.$text;

                    // Create annotation
                    $this->works[$this->createUuid()] = '<a href="'.$url.'" target="_blank">'.$text.'</a>';
                }
            }

            return $this->handleRef($ref, $doc);
        }

        return null;
    }

    protected function handleCorr(DOMNode $teiEl, HTMLDocument $doc): ?DOMNode
    {
        return $doc->span('italic');
    }

    protected function handleDate(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        $when = null;
        $htmlEl = $doc->span();
        $monthsMap = [
            'January' => 'Januar',
            'February' => 'Februar',
            'March' => 'März',
            'April' => 'April',
            'May' => 'Mai',
            'June' => 'Juni',
            'July' => 'Juli',
            'August' => 'August',
            'September' => 'September',
            'October' => 'Oktober',
            'November' => 'November',
            'December' => 'Dezember',
        ];

        foreach ($teiEl->attributes as $attribute) {
            if ('when' === $attribute->nodeName && (property_exists($attribute, 'value') && null !== $attribute->value)) {
                $when = $attribute;
            }
        }

        if (isset($when)) {
            $uuid = $this->createUuid();
            $date = new DateTime($when->value);

            $this->dates[$uuid] = strftime('%e. '.$monthsMap[$date->format('F')].' %G', $date->getTimestamp());
            $htmlEl->setAttribute('id', $uuid);
        }

        return $htmlEl;
    }

    protected function handleDel(DOMNode $teiEl, HTMLDocument $doc): ?DOMNode
    {
        return null;
    }

    protected function handleHi(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        $htmlEl = $doc->span();
        if ($teiEl->hasAttributes()) {
            $renditionValue = $teiEl->getAttribute('rendition');
            if ('' !== $renditionValue && '0' !== $renditionValue) {
                $htmlEl = $this->handleRenditions($renditionValue, $htmlEl);
            }

            $handValue = $teiEl->getAttribute('hand');
            if ('' !== $handValue && '0' !== $handValue) {
                $noteDoc = new HTMLDocument();
                foreach ($teiEl->childNodes as $child) {
                    $transformed = $this->transformElement($child, $noteDoc);
                    if (null !== $transformed) {
                        $noteDoc->appendChild($transformed);
                    }
                }

                $noteText = trim($noteDoc->saveHTML());

                $uuid = $this->createUuid();
                $htmlEl->setAttribute('id', $uuid);

                $italicDoc = new HTMLDocument();
                $italicDoc->appendChild($this->handleTextCriticalAttributes($teiEl, 'unterstr.', $italicDoc));

                $this->notes[$uuid] = $this->lemmatize($noteText).'] '.$italicDoc->saveHTML();
            }
        }

        return $htmlEl;
    }

    protected function handleItem(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        return $doc->li();
    }

    protected function handleLb(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        return $doc->text(' ');
    }

    protected function handleList(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        return $doc->ul();
    }

    protected function handleName(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        $htmlEl = $doc->span();

        $refValue = $teiEl->getAttribute('ref');
        $typeValue = $teiEl->getAttribute('type');

        if ($refValue && !empty($teiEl->attributes[1]->value) && str_contains($teiEl->attributes[1]->value, 'gnd:')) {
            $uuid = $this->createUuid();
            $this->gndsUuids[$uuid] = str_replace('gnd:', '', $refValue);
            $htmlEl->setAttribute('id', $uuid);
            if ('' !== $typeValue && '0' !== $typeValue) {
                $htmlEl->setAttribute('class', $typeValue);
            }
        }

        return $htmlEl;
    }

    protected function handleNote(DOMNode $teiEl, HTMLDocument $doc): void
    {
        $noteDoc = new HTMLDocument();
        $this->transformAllChildren($teiEl, $noteDoc, $noteDoc);
        $noteText = $noteDoc->saveHTML();

        if ('' !== $noteText) {
            if (null !== $this->lastSegUuid) {
                // If there is an uuid set which belongs to the <seg> element that was previously transformed,
                // we add to it's value the corresponding note text.
                // So the note will look like this in total: "LEMMATIZED_TEXT] NOTE_TEXT"
                $this->notes[$this->lastSegUuid] .= '] '.trim($noteText);
            } else {
                // This applies to all other notes.
                $this->notes[$this->createUuid()] = trim($noteText);
            }
        }
    }

    protected function handleOpener(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        return $doc->div();
    }

    protected function handleOrig(DOMNode $teiEl, HTMLDocument $doc): ?DOMNode
    {
        return null;
    }

    protected function handleP(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        return $doc->p();
    }

    protected function handleRef(DOMElement $el, HTMLDocument $doc): ?DOMNode
    {
        $target = null;

        foreach ($el->attributes as $attribute) {
            if ('target' === $attribute->nodeName) {
                $target = $attribute->value;
                break;
            }
        }

        if ('note' === $el->parentNode->nodeName) {
            $target = explode('.', array_reverse(explode('/', $target))[0])[0];
        }

        if ($target) {
            $a = $doc->a($target);

            // There can be self closing <ref/> tags, so we just add the target as content for the <a> tag.
            if (!$el->hasChildNodes()) {
                $a->appendChild($doc->text($target));
            }

            return $a;
        }

        return null;
    }

    protected function handleRs(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        return $this->handleName($teiEl, $doc);
    }

    protected function handleSeg(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        // We need to extract and save the text which the <seg> tag encloses
        // This text will be shown in the annotation panel.
        $segDoc = new HTMLDocument();
        $this->transformAllChildren($teiEl, $segDoc, $segDoc);

        // Now we extract the concatenated text
        $segText = $segDoc->textContent;

        if ('' !== $segText) {
            $uuid = $this->createUuid();
            $this->notes[$uuid] = $this->lemmatize($segText);

            // We save this uuid to reuse it when we reach <note> which should be the next sibling
            $this->lastSegUuid = $uuid;

            $htmlEl = $doc->span();

            return $this->setUuid($htmlEl, $uuid);
        }

        return null;
    }

    protected function handleSic(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        $sicDoc = new HTMLDocument();
        $this->transformAllChildren($teiEl, $sicDoc, $sicDoc);

        $sicText = $sicDoc->textContent;
        if ('' !== $sicText) {
            $uuid = $this->createUuid();
            $this->notes[$uuid] = $this->lemmatize($sicText).'] <span class="italic">sic!</span>';

            $htmlEl = $doc->span();
            $htmlEl->setAttribute('id', $uuid);

            return $htmlEl;
        }

        return null;
    }

    protected function handleSigned(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        return $doc->div('signed');
    }

    protected function handleSupplied(DOMNode $teiEl, HTMLDocument $doc): ?DOMNode
    {
        return $doc->span('italic');
    }

    private function lemmatize(string $text): string
    {
        $wordsCountInPageSeg = explode(' ', $text);

        if (!empty($wordsCountInPageSeg) && 2 < count($wordsCountInPageSeg)) {
            $firstWord = $wordsCountInPageSeg[0];
            $lastWord = array_reverse($wordsCountInPageSeg)[0];
            $text = $firstWord.' ... '.$lastWord;
        }

        return $text;
    }

    private function setUuid(DOMNode $el, ?string $uuid = null): DOMNode
    {
        if (null === $uuid) {
            $uuid = $this->createUuid();
        }

        $el->setAttribute('id', $uuid);

        return $el;
    }
}
