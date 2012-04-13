<?php

namespace Psy\Util\Signature;

/**
 * An abstract representation of a function, class or property signature.
 */
abstract class Signature
{
    /**
     * @type \Reflector
     */
    protected $reflector;

    /**
     * Signature constructor.
     *
     * @param \Reflector $reflector Reflector for the desired signature.
     */
    public function __construct(\Reflector $reflector)
    {
        $this->reflector = $reflector;
    }

    /**
     * Print the signature in a format suitable for console output.
     *
     * @return string
     */
    abstract public function prettyPrint();

    /**
     * Print the signature name.
     *
     * @return string
     */
    public function printName()
    {
        return $this->reflector->getName();
    }

    /**
     * @see self::prettyPrint
     *
     * @return string
     */
    public function __toString()
    {
        return $this->prettyPrint();
    }

    /**
     * Print the method, property or class modifiers.
     *
     * Techinically this should be a trait. Can't wait for 5.4 :)
     */
    protected function printModifiers()
    {
        return implode(' ', array_map(function($modifier) {
            return sprintf('<comment>%s</comment>', $modifier);
        }, \Reflection::getModifierNames($this->reflector->getModifiers())));
    }
}
