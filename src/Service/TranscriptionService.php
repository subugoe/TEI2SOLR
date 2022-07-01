<?php

namespace Subugoe\TEI2SOLRBundle\Service;

use Subugoe\TEI2SOLRBundle\Import\HTMLDocument;
use DOMElement;
use DOMNode;

/**
 * Specialized service to transform TEI to HTML tags to display a transcription.
 */
class TranscriptionService extends CommonTransformService
{
    private const ANGLE_BRACKET_CLOSE = '&rang;';
    private const ANGLE_BRACKET_OPEN = '&lang;';
    private const SQUARE_BRACKET_CLOSE = '&rsqb;';
    private const SQUARE_BRACKET_OPEN = '&lsqb;';

    public array $ignoreChildrenList = [
        'add',
        'del',
        'hi',
        'signed',
    ];

    protected array $renditions = [
        'centre' => 'left',
        'doubleunderline' => 'doubleunderline',
        'italic' => 'italic',
        'letterspace' => 'letterspace',
        'right' => 'left',
        'subscript' => 'subscript',
        'smallcaps' => 'smallcaps',
        'superscript' => 'superscript',
        'underline' => 'underline',
        'wavyunderline' => 'underline',
        'bold' => 'bold',
    ];

    protected function handleItem(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        return $doc->li();
    }

    protected function handleList(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        return $doc->ul();
    }

    protected function handleAdd(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        $type = $teiEl->getAttribute('type');

        if ('courseBus' === $type) {
            return null;
        }

        $htmlEl = $doc->span();
        $prefix = '';

        if (isset($teiEl->previousSibling) && 'del' !== $teiEl->previousSibling->nodeName) {
            $prefix = ' ';
        }

        $prefix .= self::ANGLE_BRACKET_OPEN;
        $suffix = self::ANGLE_BRACKET_CLOSE.' ';

        $htmlEl->appendChild($doc->text($prefix));
        $this->transformAllChildren($teiEl, $htmlEl, $doc);
        $htmlEl->appendChild($doc->text(' '));
        $htmlEl->appendChild($this->handleTextCriticalAttributes($teiEl, 'erg.', $doc));
        $htmlEl->appendChild($doc->text($suffix));

        return $htmlEl;
    }

    protected function handleCorr(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        return null;
    }

    protected function handleDel(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        $htmlEl = $doc->span();
        $prefix = ' '.self::SQUARE_BRACKET_OPEN;
        $htmlEl->appendChild($doc->text($prefix));
        $this->transformAllChildren($teiEl, $htmlEl, $doc);
        $htmlEl->appendChild($doc->text(' '));
        $htmlEl->appendChild($this->handleTextCriticalAttributes($teiEl, 'str.', $doc));
        $suffix = self::SQUARE_BRACKET_CLOSE;

        if (isset($teiEl->nextSibling) && 'add' !== $teiEl->nextSibling->nodeName) {
            $suffix .= ' ';
        }
        $htmlEl->appendChild($doc->text($suffix));

        return $htmlEl;
    }

    protected function handleExpan(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        return null;
    }

    protected function handleHi(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        $htmlEl = $doc->span();
        if ($teiEl->hasAttributes()) {
            $renditionValue = $teiEl->getAttribute('rendition');

            if ($renditionValue) {
                $htmlEl = $this->handleRenditions($renditionValue, $htmlEl);
            }

            $this->transformAllChildren($teiEl, $htmlEl, $doc);
            $handValue = $teiEl->getAttribute('hand');

            if ($handValue) {
                $prefix = ' '.self::ANGLE_BRACKET_OPEN;
                $suffix = self::ANGLE_BRACKET_CLOSE.' ';
                $htmlEl->appendChild($doc->text($prefix));
                $htmlEl->appendChild($this->handleTextCriticalAttributes($teiEl, 'unterstr.', $doc));
                $htmlEl->appendChild($doc->text($suffix));
            }
        } else {
            $this->transformAllChildren($teiEl, $htmlEl, $doc);
        }

        return $htmlEl;
    }

    protected function handleLb(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        return $doc->br();
    }

    protected function handleNote(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        return null;
    }

    protected function handleP(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        return $doc->p();
    }

    protected function handleSigned(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        $handShift = '';
        /** @var DOMElement $childNode */
        foreach ($teiEl->childNodes as $childNode) {
            if ('handShift' === $childNode->nodeName) {
                $handShift = $childNode->getAttribute('scribeRef');
            }
        }

        $htmlEl = $doc->div();

        $signedDoc = new HTMLDocument();
        $signedDocRootEl = $signedDoc->span();
        $signedDoc->appendChild($signedDocRootEl);

        $this->transformAllChildren($teiEl, $signedDocRootEl, $signedDoc);

        if (isset($handShift)) {
            $prefix = $signedDoc->text(self::ANGLE_BRACKET_OPEN);
            $signedDocRootEl->insertBefore($prefix, $signedDocRootEl->firstChild);

            $handShift = str_replace('_', ' ', trim($handShift, '#'));

            $sigle = $signedDoc->text(' sign. '.$handShift);
            $italic = $signedDoc->span('italic');
            $italic->appendChild($sigle);

            $suffix = $signedDoc->text(self::ANGLE_BRACKET_CLOSE);

            $signedDocRootEl->appendChild($italic);
            $signedDocRootEl->appendChild($suffix);
        }

        $importNode = $doc->importNode($signedDocRootEl, true);

        $htmlEl->appendChild($importNode);

        return $htmlEl;
    }

    protected function handleSpan(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        return $doc->span();
    }
}
