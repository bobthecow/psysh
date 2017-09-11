<?php

namespace Psy\TabCompletion\Matcher;

abstract class AbstractDefaultParametersMatcher extends AbstractContextAwareMatcher
{
    /**
     * @param \ReflectionParameter[] $reflectionParameters
     *
     * @return array
     */
    public function getDefaultParameterCompletion(array $reflectionParameters)
    {
        $parametersProcessed = array();

        foreach ($reflectionParameters as $parameter) {
            if (!$parameter->isDefaultValueAvailable()) {
                return array();
            }

            $defaultValue = $this->valueToShortString($parameter->getDefaultValue());

            $parametersProcessed[] = "\${$parameter->getName()} = $defaultValue";
        }

        if (empty($parametersProcessed)) {
            return array();
        }

        return array(implode(',', $parametersProcessed) . ')');
    }

    /**
     * Takes in the default value of a parameter and turns it into a
     *  string representation that fits inline.
     * This is not 100% true to the original (newlines are inlined, for example).
     *
     * @param mixed $value
     *
     * @return string
     */
    private function valueToShortString($value)
    {
        if (!is_array($value)) {
            return json_encode($value);
        }

        $chunks = '';

        foreach ($value as $key => $item) {
            $keyString = $this->valueToShortString($key);
            $itemString = $this->valueToShortString($item);

            $chunks .= "{$keyString} => {$itemString}, ";
        }

        return '[ ' . $chunks . ' ]';
    }
}
