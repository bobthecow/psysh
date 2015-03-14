<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command\ListCommand;

use Psy\Formatter\SignatureFormatter;
use Psy\Presenter\PresenterManager;
use Psy\Util\Mirror;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Abstract Enumerator class.
 */
abstract class Enumerator
{
    // Output styles
    const IS_PUBLIC    = 'public';
    const IS_PROTECTED = 'protected';
    const IS_PRIVATE   = 'private';
    const IS_GLOBAL    = 'global';
    const IS_CONSTANT  = 'const';
    const IS_CLASS     = 'class';
    const IS_FUNCTION  = 'function';

    private $presenterManager;

    private $filter       = false;
    private $invertFilter = false;
    private $pattern;

    /**
     * Enumerator constructor.
     *
     * @param PresenterManager $presenterManager
     */
    public function __construct(PresenterManager $presenterManager)
    {
        $this->presenterManager = $presenterManager;
    }

    /**
     * Return a list of categorized things with the given input options and target.
     *
     * @param InputInterface $input
     * @param Reflector      $reflector
     * @param mixed          $target
     *
     * @return array
     */
    public function enumerate(InputInterface $input, \Reflector $reflector = null, $target = null)
    {
        $this->setFilter($input);

        return $this->listItems($input, $reflector, $target);
    }

    /**
     * Enumerate specific items with the given input options and target.
     *
     * Implementing classes should return an array of arrays:
     *
     *     [
     *         'Constants' => [
     *             'FOO' => [
     *                 'name'  => 'FOO',
     *                 'style' => 'public',
     *                 'value' => '123',
     *             ],
     *         ],
     *     ]
     *
     * @param InputInterface $input
     * @param Reflector      $reflector
     * @param mixed          $target
     *
     * @return array
     */
    abstract protected function listItems(InputInterface $input, \Reflector $reflector = null, $target = null);

    protected function presentRef($value)
    {
        return $this->presenterManager->presentRef($value);
    }

    protected function showItem($name)
    {
        return $this->filter === false || (preg_match($this->pattern, $name) xor $this->invertFilter);
    }

    private function setFilter(InputInterface $input)
    {
        if ($pattern = $input->getOption('grep')) {
            if (substr($pattern, 0, 1) !== '/' || substr($pattern, -1) !== '/' || strlen($pattern) < 3) {
                $pattern = '/' . preg_quote($pattern, '/') . '/';
            }

            if ($input->getOption('insensitive')) {
                $pattern .= 'i';
            }

            $this->validateRegex($pattern);

            $this->filter       = true;
            $this->pattern      = $pattern;
            $this->invertFilter = $input->getOption('invert');
        } else {
            $this->filter = false;
        }
    }

    /**
     * Validate that $pattern is a valid regular expression.
     *
     * @param string $pattern
     *
     * @return boolean
     */
    private function validateRegex($pattern)
    {
        set_error_handler(array('Psy\Exception\ErrorException', 'throwException'));
        try {
            preg_match($pattern, '');
        } catch (ErrorException $e) {
            throw new RuntimeException(str_replace('preg_match(): ', 'Invalid regular expression: ', $e->getRawMessage()));
        }
        restore_error_handler();
    }

    protected function presentSignature($target)
    {
        // This might get weird if the signature is actually for a reflector. Hrm.
        if (!$target instanceof \Reflector) {
            $target = Mirror::get($target);
        }

        return SignatureFormatter::format($target);
    }
}
