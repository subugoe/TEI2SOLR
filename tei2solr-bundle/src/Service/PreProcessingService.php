<?php

namespace Subugoe\TEI2SOLRBundle\Service;

use DOMDocument;
use DOMElement;
use DOMNode;

class PreProcessingService
{
    private DOMNode $lastParent;
    private array $pages = [];

    public function clear()
    {
        $this->pages = [];
    }

    public function getPages(): array
    {
        return $this->pages;
    }

    public function splitByPages(DOMELement $body)
    {
        $this->pages[] = new DOMDocument();

        // To start out we set our empty page as last parent to append other elements to
        $this->lastParent = $this->getLastPage();

        // Start recursion
        $this->checkPb($body);

        return $this->pages;
    }

    private function checkPb(DOMNode $el): void
    {
        if ('#comment' === $el->nodeName) {
            return;
        }

        $lastPage = $this->getLastPage();

        if ('#text' === $el->nodeName) {
            $clone = $lastPage->createTextNode($el->textContent);
        } else {
            $clone = $lastPage->createElement($el->nodeName);
            $clone = $this->cloneAttributes($el->attributes, $clone);
        }

        // Always append the current element clone to last parent
        $this->lastParent->appendChild($clone);

        if ($el->hasChildNodes()) {
            // If there are children then we want to append their clones to the current element
            // so we have to move lastParent
            $this->lastParent = $clone;

            /** @var DOMElement $child */
            foreach ($el->childNodes as $child) {
                if ('pb' === $child->nodeName) {
                    $this->pages[] = $this->createNewPage($child);
                } else {
                    $this->checkPb($child);
                }
            }

            // After we finished iterating (recursively) over all children
            // we are done here and want move on with our next sibling
            // so we have to set lastParent to it's parent
            if ($this->lastParent->parentNode) {
                $this->lastParent = $this->lastParent->parentNode;
            }
        }
    }

    private function cloneAttributes($attributes, $clone)
    {
        foreach ($attributes as $attr) {
            $clone->setAttribute($attr->name, $attr->value);
        }

        return $clone;
    }

    private function createNewPage(DOMElement $pbEl): DOMDocument
    {
        // Creates a new DOMDocument and replicates every parent node of pb element
        // up to the <body>
        $newPage = new DOMDocument();
        $parent = $pbEl->parentNode;

        $parents = [];
        while ($parent->parentNode) {
            // Collect all parents until <body>

            $parents[] = $parent;

            if ('body' === $parent->nodeName) {
                break;
            }
            $parent = $parent->parentNode;
        }

        // Reverse them to append them from document root
        $parentsReversed = array_reverse($parents);

        $lastNode = $newPage;
        foreach ($parentsReversed as $parent) {
            $node = $newPage->createElement($parent->nodeName);
            $node = $this->cloneAttributes($parent->attributes, $node);
            $node = $lastNode->appendChild($node);

            // Result will be the deepest parent node so we can continue cloning the TEI
            // in further checkPb calls
            $this->lastParent = $node;
            $lastNode = $node;
        }

        // Lastly we insert the <pb> element itself as the first element
        // to the new page to maintain it for further processing
        $pbClone = $newPage->createElement('pb');
        $pbClone = $this->cloneAttributes($pbEl->attributes, $pbClone);
        $newPage->insertBefore($pbClone, $newPage->firstChild);

        return $newPage;
    }

    private function getLastPage(): ?DOMDocument
    {
        return (!empty($this->pages)) ? $this->pages[count($this->pages) - 1] : null;
    }
}
