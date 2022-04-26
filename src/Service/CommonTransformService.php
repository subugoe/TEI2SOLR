<?php

namespace Subugoe\TEI2SOLRBundle\Service;

use Subugoe\TEI2SOLRBundle\Import\HTMLDocument;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Service for TEI to HTML transformations that are handled equally in transcription and edited text.
 */
class CommonTransformService
{
    /** @var array
     * A list of node names which should be ignored when attempting to transform their children.
     * Usually such node's children are transformed within the "handleXYZ" itself,
     * where we manipulate the content, for example the <add> TEI tag should result in this:
     * ‹XXX <span class="italic">Erg.</span>›
     * and so we don't need to transform the children again.
     */
    protected array $ignoreChildrenList = [];

    /** @var array|string[]
     * A map of rendition styles.
     * The key represents occurrence in TEI and the value the assigned HTML class name.
     * Services that inherit from this class can override this map and style the renditions in their own way.
     */
    protected array $renditions = [
        'centre' => 'centre',
        'doubleunderline' => 'doubleunderline',
        'italic' => 'italic',
        'letterspace' => 'letterspace',
        'right' => 'right',
        'subscript' => 'subscript',
        'smallcaps' => 'smallcaps',
        'superscript' => 'superscript',
        'underline' => 'underline',
        'wavyunderline' => 'underline',
    ];
    private array $graphics = [];

    public function setGraphics(array $graphics): void
    {
        $this->graphics = $graphics;
    }

    public function transformAllChildren(DOMNode $teiEl, DOMNode $htmlEl, HTMLDocument $doc): DOMNode
    {
        $addSpace = false;

        if ('name' === $teiEl->nodeName && 'forename' === $teiEl->getAttribute('type')) {
            $addSpace = true;
        }

        foreach ($teiEl->childNodes as $child) {
            $transformed = $this->transformElement($child, $doc, $addSpace);
            if ($transformed) {
                $htmlEl->appendChild($transformed);
            }
        }

        return $htmlEl;
    }

    public function transformElement(DOMNode $teiEl, HTMLDocument $doc, bool $addSpace = false): ?DOMNode
    {
        $methodName = 'handle'.trim(ucfirst($teiEl->nodeName), '#');

        if (method_exists($this, $methodName)) {
            if ($addSpace && 'handletext' === $methodName) {
                $htmlEl = $this->{$methodName}($teiEl, $doc, $addSpace);
            } else {
                $htmlEl = $this->{$methodName}($teiEl, $doc);
            }


        } else {
            $htmlEl = $doc->span();
        }

        // After handling the $teiEl above we can end up in following scenarios:
        // - $htmlEl is a well transformed another HTMl element which can contain transformable children
        // - $htmlEl is null, so the $teiEl and all content should not be displayed
        // - $htmlEl is text, so we can stop here
        if (
            null !== $htmlEl &&
            !is_a($htmlEl, 'DOMText') &&
            $teiEl->hasChildNodes() &&
            $this->shouldTransformChildren($teiEl, $this->ignoreChildrenList)
        ) {
            $this->transformAllChildren($teiEl, $htmlEl, $doc);
        }

        return $htmlEl;
    }

    public function transformPage(DOMDocument $page): HTMLDocument
    {
        $doc = new HTMLDocument();

        /** @var DOMElement $element */
        foreach ($page->childNodes as $element) {
            $transformed = $this->transformElement($element, $doc);
            if ($transformed) {
                $doc->appendChild($transformed);
            }
        }

        // Later this should be packed in an object
        return $doc;
    }

    protected function handleAddress(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        return $doc->div('address');
    }

    protected function handleAddrLine(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        return $doc->div();
    }

    protected function handleCloser(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        return $doc->div();
    }

    protected function handleDateline(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        return $doc->div();
    }

    protected function handleDiv(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        $classes = '';
        if ($teiEl->hasAttribute('type')) {
            $classes = $teiEl->getAttribute('type');
        }

        return $doc->section($classes);
    }

    protected function handleHandShift(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        return null;
    }

    protected function handleHead(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        return $doc->div();
    }

    protected function handleLabel(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        // This is temporarily implemented till styling requirements
        // for lable are specified.
        if ('item' === $teiEl->parentNode->nodeName) {
            return $doc->text($teiEl->textContent);
        }

        $classes = '';
        if (
            'div' === $teiEl->parentNode->nodeName &&
            $teiEl->parentNode->hasAttribute('type') &&
            'lecture' === $teiEl->parentNode->getAttribute('type')
        ) {
            $classes = 'lecture-label';
        }

        return $doc->div($classes);
    }

    protected function handleOpener(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        return $doc->div();
    }

    protected function handlePb(DOMElement $el, HTMLDocument $doc): DOMNode
    {
        $graphics = $this->graphics;
        $htmlEl = $doc->text();
        foreach ($el->attributes as $attribute) {
            if ('facs' === $attribute->name) {
                if (isset($graphics[trim($attribute->value, '#')])) {
                    $graphic = $graphics[trim($attribute->value, '#')];

                    if (str_ends_with($graphic, '.jpg')) {
                        $graphic = substr($graphic, 0, -4);
                    }
                }
            } elseif ('n' === $attribute->name) {
                $pageNumber = $attribute->value;
            }

            if (!empty($pageNumber) && !empty($graphic)) {
                $htmlEl = $doc->div();
                $a = $doc->a('/'.$graphic);
                $text = $doc->text($pageNumber);
                $htmlEl->appendChild($a)->appendChild($text);
            } elseif (!empty($pageNumber) && empty($graphic)) {
                $htmlEl = $doc->div();
                $htmlEl->appendChild($doc->text($pageNumber));
            }
        }

        return $htmlEl;
    }

    protected function handlePostscript(DOMElement $teiEl, HTMLDocument $doc): DOMNode
    {
        return $doc->div();
    }

    protected function handleRenditions(string $value, DOMNode $htmlEl): DOMNode
    {
        $valueArr = explode(':', $value);
        $style = $valueArr[1] ?? '';

        if (!empty($style) && isset($this->renditions[$style])) {
            $className = $this->renditions[$style];
            if ($className) {
                $htmlEl->setAttribute('class', $className);
            }
        }

        return $htmlEl;
    }

    protected function handleSalute(DOMElement $el, HTMLDocument $doc): DOMNode
    {
        return $doc->p('salute');
    }

    protected function handleSpace(DOMElement $teiEl, HTMLDocument $doc): ?DOMNode
    {
        $htmlEl = null;
        if ($teiEl->hasAttributes()) {
            $dimension = $teiEl->getAttribute('dimension');

            if (empty($dimension)) {
                $dimension = $teiEl->getAttribute('dim');
            }

            if (empty($dimension)) {
                if ('vertical' === $dimension) {
                    $htmlEl = $doc->div('space vertical');
                } elseif ('horizontal' === $dimension) {
                    $htmlEl = $doc->span('space horizontal');
                    $quantity = $teiEl->getAttribute('quantity');

                    if (isset($quantity)) {
                        $quantityArr = explode('.', $quantity);

                        if (1 === count($quantityArr)) {
                            // Quantity can equal to "0.a" or "a" where a = number of spaces
                            // We need to omit values like "0.a" and consider only "a"
                            $htmlEl->appendChild(
                                $doc->text(
                                    implode('', array_fill(0, $quantityArr[0], '&ensp; '))
                                )
                            );
                        }
                    }
                }
            }
        }

        return $htmlEl;
    }

    protected function handleText(DOMNode $el, HTMLDocument $doc, bool $addSpace = false): DOMNode
    {
        $textContent = (!$addSpace) ? $el->textContent : $el->textContent.' ';

        return $doc->text($textContent);
    }

    protected function handleTextCriticalAttributes(DOMNode $teiEl, string $comment, HTMLDocument $doc)
    {
        $italic = $doc->span('italic');

        if (empty($teiEl->attributes)) {
            $italic->appendChild($doc->text(' '.ucfirst($comment).'.'));
        } else {
            foreach ($teiEl->attributes as $attribute) {
                if ('hand' === $attribute->nodeName) {
                    if (false !== strpos($attribute->nodeValue, 'scrb')) {
                        $valueArr = explode('scrb', $attribute->nodeValue);
                        if (isset($valueArr[1])) {
                            $valueArr = explode('_', ltrim($valueArr[1], '_'));
                            if (2 === count($valueArr)) {
                                $italic->appendChild($doc->text(strtolower($comment).' Schrhd.'.$valueArr[0].' '.$valueArr[1]));
                            }
                        }
                    } else {
                        $italic->appendChild($doc->text(strtolower($comment).' '.str_replace('_', ' ', trim($attribute->nodeValue, '#'))));
                    }
                }
            }
        }

        return $italic;
    }

    private function shouldTransformChildren(DOMNode $htmlEl, array $ignoreList): bool
    {
        $shouldTransform = true;

        foreach ($ignoreList as $ignoreNodeName) {
            $shouldTransform = $htmlEl->nodeName !== $ignoreNodeName;
            if (!$shouldTransform) {
                break;
            }
        }

        return $shouldTransform;
    }
}
