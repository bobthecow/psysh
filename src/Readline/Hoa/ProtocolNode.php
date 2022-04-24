<?php

/**
 * Hoa
 *
 *
 * @license
 *
 * New BSD License
 *
 * Copyright © 2007-2017, Hoa community. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace Hoa\Protocol\Node;

use Hoa\Consistency;
use Hoa\Protocol;

/**
 * Class \Hoa\Protocol\Node\Node.
 *
 * Abstract class for all `hoa://`'s nodes.
 *
 * @copyright  Copyright © 2007-2017 Hoa community
 * @license    New BSD License
 */
class Node implements \ArrayAccess, \IteratorAggregate
{
    /**
     * Node's name.
     *
     * @var string
     */
    protected $_name       = null;

    /**
     * Path for the `reach` method.
     *
     * @var string
     */
    protected $_reach      = null;

    /**
     * Children of the node.
     *
     * @var array
     */
    private $_children     = [];



    /**
     * Construct a protocol's node.
     * If it is not a data object (i.e. if it does not extend this class to
     * overload the `$_name` attribute), we can set the `$_name` attribute
     * dynamically. This is useful to create a node on-the-fly.
     *
     * @param   string  $name        Node's name.
     * @param   string  $reach       Path for the `reach` method.
     * @param   array   $children    Node's children.
     */
    public function __construct($name = null, $reach = null, array $children = [])
    {
        if (null !== $name) {
            $this->_name = $name;
        }

        if (null !== $reach) {
            $this->_reach = $reach;
        }

        foreach ($children as $child) {
            $this[] = $child;
        }

        return;
    }

    /**
     * Add a node.
     *
     * @param   string                  $name    Node's name. If null, will be
     *                                           set to name of `$node`.
     * @param   \Hoa\Protocol\Protocol  $node    Node to add.
     * @return  \Hoa\Protocol\Protocol
     * @throws  \Hoa\Protocol\Exception
     */
    public function offsetSet($name, $node)
    {
        if (!($node instanceof self)) {
            throw new Protocol\Exception(
                'Protocol node must extend %s.',
                0,
                __CLASS__
            );
        }

        if (empty($name)) {
            $name = $node->getName();
        }

        if (empty($name)) {
            throw new Protocol\Exception(
                'Cannot add a node to the `hoa://` protocol without a name.',
                1
            );
        }

        $this->_children[$name] = $node;

        return;
    }

    /**
     * Get a specific node.
     *
     * @param   string  $name    Node's name.
     * @return  \Hoa\Protocol\Protocol
     * @throws  \Hoa\Protocol\Exception
     */
    public function offsetGet($name)
    {
        if (!isset($this[$name])) {
            throw new Protocol\Exception(
                'Node %s does not exist.',
                2,
                $name
            );
        }

        return $this->_children[$name];
    }

    /**
     * Check if a node exists.
     *
     * @param   string  $name    Node's name.
     * @return  bool
     */
    public function offsetExists($name)
    {
        return true === array_key_exists($name, $this->_children);
    }

    /**
     * Remove a node.
     *
     * @param   string  $name    Node's name to remove.
     * @return  void
     */
    public function offsetUnset($name)
    {
        unset($this->_children[$name]);

        return;
    }

    /**
     * Resolve a path, i.e. iterate the nodes tree and reach the queue of
     * the path.
     *
     * @param   string  $path            Path to resolve.
     * @param   array   &$accumulator    Combination of all possibles paths.
     * @param   string  $id              ID.
     * @return  mixed
     */
    protected function _resolve($path, &$accumulator, $id = null)
    {
        if (substr($path, 0, 6) == 'hoa://') {
            $path = substr($path, 6);
        }

        if (empty($path)) {
            return null;
        }

        if (null === $accumulator) {
            $accumulator = [];
            $posId       = strpos($path, '#');

            if (false !== $posId) {
                $id   = substr($path, $posId + 1);
                $path = substr($path, 0, $posId);
            } else {
                $id   = null;
            }
        }

        $path = trim($path, '/');
        $pos  = strpos($path, '/');

        if (false !== $pos) {
            $next = substr($path, 0, $pos);
        } else {
            $next = $path;
        }

        if (isset($this[$next])) {
            if (false === $pos) {
                if (null === $id) {
                    $this->_resolveChoice($this[$next]->reach(), $accumulator);

                    return true;
                }

                $accumulator = null;

                return $this[$next]->reachId($id);
            }

            $tnext = $this[$next];
            $this->_resolveChoice($tnext->reach(), $accumulator);

            return $tnext->_resolve(substr($path, $pos + 1), $accumulator, $id);
        }

        $this->_resolveChoice($this->reach($path), $accumulator);

        return true;
    }

    /**
     * Resolve choices, i.e. a reach value has a “;”.
     *
     * @param   string  $reach           Reach value.
     * @param   array   &$accumulator    Combination of all possibles paths.
     * @return  void
     */
    protected function _resolveChoice($reach, array &$accumulator)
    {
        if (empty($accumulator)) {
            $accumulator = explode(RS, $reach);

            return;
        }

        if (false === strpos($reach, RS)) {
            if (false !== $pos = strrpos($reach, "\r")) {
                $reach = substr($reach, $pos + 1);

                foreach ($accumulator as &$entry) {
                    $entry = null;
                }
            }

            foreach ($accumulator as &$entry) {
                $entry .= $reach;
            }

            return;
        }

        $choices     = explode(RS, $reach);
        $ref         = $accumulator;
        $accumulator = [];

        foreach ($choices as $choice) {
            if (false !== $pos = strrpos($choice, "\r")) {
                $choice = substr($choice, $pos + 1);

                foreach ($ref as $entry) {
                    $accumulator[] = $choice;
                }
            } else {
                foreach ($ref as $entry) {
                    $accumulator[] = $entry . $choice;
                }
            }
        }

        unset($ref);

        return;
    }

    /**
     * Queue of the node.
     * Generic one. Must be overrided in children classes.
     *
     * @param   string  $queue    Queue of the node (generally a filename,
     *                            with probably a query).
     * @return  mixed
     */
    public function reach($queue = null)
    {
        return empty($queue) ? $this->_reach : $queue;
    }

    /**
     * ID of the component.
     * Generic one. Should be overrided in children classes.
     *
     * @param   string  $id    ID of the component.
     * @return  mixed
     * @throws  \Hoa\Protocol\Exception
     */
    public function reachId($id)
    {
        throw new Protocol\Exception(
            'The node %s has no ID support (tried to reach #%s).',
            4,
            [$this->getName(), $id]
        );
    }

    /**
     * Set a new reach value.
     *
     * @param   string  $reach    Reach value.
     * @return  string
     */
    public function setReach($reach)
    {
        $old          = $this->_reach;
        $this->_reach = $reach;

        return $old;
    }

    /**
     * Get node's name.
     *
     * @return  string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Get reach's root.
     *
     * @return  string
     */
    protected function getReach()
    {
        return $this->_reach;
    }

    /**
     * Get an iterator.
     *
     * @return  \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->_children);
    }

    /**
     * Get root the protocol.
     *
     * @return  \Hoa\Protocol\Protocol
     */
    public static function getRoot()
    {
        return Protocol::getInstance();
    }

    /**
     * Print a tree of component.
     *
     * @return  string
     */
    public function __toString()
    {
        static $i = 0;

        $out = str_repeat('  ', $i) . $this->getName() . "\n";

        foreach ($this as $node) {
            ++$i;
            $out .= $node;
            --$i;
        }

        return $out;
    }
}

/**
 * Flex entity.
 */
Consistency::flexEntity('Hoa\Protocol\Node\Node');
