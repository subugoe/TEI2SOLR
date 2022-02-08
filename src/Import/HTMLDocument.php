<?php

namespace Subugoe\TEI2SOLRBundle\Import;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * A wrapper for DOMDocument to handle creation of basic HTML elements.
 */
class HTMLDocument extends DOMDocument
{
    public function __construct()
    {
        parent::__construct();
    }

    public function a(string $href = '', string $target = '_blank', string $classes = ''): DOMElement
    {
        $a = $this->createCustomElement('a', $classes);

        if (!empty($href)) {
            $a->setAttribute('href', $href);
        }

        $a->setAttribute('target', $target);

        return $a;
    }

    public function br(): DOMElement
    {
        return $this->createElement('br');
    }

    public function createCustomElement(string $name, string $classes = ''): DOMElement
    {
        $element = $this->createElement($name);
        if ('' !== $classes) {
            $element->setAttribute('class', $classes);
        }

        return $element;
    }

    public function ul(string $classes = ''): DOMElement
    {
        return $this->createCustomElement('ul', $classes);
    }

    public function li(string $classes = ''): DOMElement
    {
        return $this->createCustomElement('li', $classes);
    }

    public function div(string $classes = ''): DOMElement
    {
        return $this->createCustomElement('div', $classes);
    }

    public function p(string $classes = ''): DOMElement
    {
        return $this->createCustomElement('p', $classes);
    }

    public function section(string $classes = ''): DOMElement
    {
        return $this->createCustomElement('section', $classes);
    }

    public function span(string $classes = ''): DOMElement
    {
        return $this->createCustomElement('span', $classes);
    }

    public function text(string $data = ''): DOMNode
    {
        return $this->createTextNode($data);
    }
}
