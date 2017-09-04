<?php
/**
 * Created by PhpStorm.
 * User: tim
 * Date: 9/4/17
 * Time: 10:36 AM
 */

namespace Psy\TabCompletion\Matcher;


abstract class AbstractDefaultParametersMatcher extends AbstractContextAwareMatcher
{
    /**
     * @param \ReflectionParameter[] $reflectionParameters
     * @return array
     */
    public function getDefaultParameterCompletion(array $reflectionParameters)
    {
        $parametersProcessed = array();

        foreach ($reflectionParameters as $parameter) {
            if (!$parameter->isDefaultValueAvailable()) {
                return array();
            }

            $defaultValue = var_export($parameter->getDefaultValue(), true);

            $parametersProcessed[] = "\${$parameter->getName()} = $defaultValue";
        }

        if (empty($parametersProcessed)) {
            return array();
        }

        return array(implode(',', $parametersProcessed) . ')');
    }
}