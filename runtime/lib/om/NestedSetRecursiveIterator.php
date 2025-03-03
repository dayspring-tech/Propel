<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * Pre-order node iterator for Node objects.
 *
 * @author     Heltem <heltem@o2php.com>
 * @version    $Revision$
 * @package    propel.runtime.om
 */
class NestedSetRecursiveIterator implements RecursiveIterator
{
    protected $topNode = null;

    protected $curNode = null;

    public function __construct($node)
    {
        $this->topNode = $node;
        $this->curNode = $node;
    }

    public function rewind(): void
    {
        $this->curNode = $this->topNode;
    }

    public function valid(): bool
    {
        return ($this->curNode !== null);
    }

    public function current(): mixed
    {
        return $this->curNode;
    }

    public function key(): mixed
    {
        $method = method_exists($this->curNode, 'getPath') ? 'getPath' : 'getAncestors';
        $key = array();
        foreach ($this->curNode->$method() as $node) {
            $key[] = $node->getPrimaryKey();
        }

        return implode('.', $key);
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        $nextNode = null;
        $method = method_exists($this->curNode, 'retrieveNextSibling') ? 'retrieveNextSibling' : 'getNextSibling';
        if ($this->valid()) {
            while (null === $nextNode) {
                if (null === $this->curNode) {
                    break;
                }

                if ($this->curNode->hasNextSibling()) {
                    $nextNode = $this->curNode->$method();
                } else {
                    break;
                }
            }
            $this->curNode = $nextNode;
        }

        return $this->curNode;
    }

    public function hasChildren(): bool
    {
        return $this->curNode->hasChildren();
    }

    public function getChildren(): ?RecursiveIterator
    {
        $method = method_exists($this->curNode, 'retrieveFirstChild') ? 'retrieveFirstChild' : 'getFirstChild';

        return new NestedSetRecursiveIterator($this->curNode->$method());
    }
}
