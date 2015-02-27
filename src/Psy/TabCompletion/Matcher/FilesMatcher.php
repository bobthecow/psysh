<?php

namespace Psy\TabCompletion\Matcher;

class FilesMatcher extends AbstractMatcher
{
    /**
     * Provide tab completion matches for readline input.
     *
     * @param array $tokens information substracted with get_token_all
     * @param array $info   readline_info object
     *
     * @return array The matches resulting from the query
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $input = $this->getInput($tokens);

        $quote = '"';
        if (strlen($input) > 1) {
            if (strlen($input) > 1 && $input[0] === "'" || $input[0] === '"') {
                $quote = substr($input, 0, 1);
                $input = substr($input, 1, strlen($input));
            }
        }

        $pathFolders = explode(PATH_SEPARATOR, get_include_path());
        $relativePaths = array_map(function ($path) {
            $paths = glob($path . DIRECTORY_SEPARATOR . '*.*');

            return array_map(function ($filePath) use ($path) {
                return str_replace($path . DIRECTORY_SEPARATOR, '', $filePath);
            }, $paths);
        }, $pathFolders);

        // flatten the array
        $relativePaths = call_user_func_array('array_merge', $relativePaths);

        return array_filter(
            $relativePaths,
            function ($file) use ($input, $quote) {
                return AbstractMatcher::startsWith($input, $file);
            }
        );
    }

    /**
     * {@inheritDoc}
     */
    public function hasMatched(array $tokens)
    {
        $token = array_pop($tokens);
        $prevToken = array_pop($tokens);
        $priorToken = array_pop($tokens);

        $fileManipulatorTokens = array(
            self::T_INCLUDE, self::T_INCLUDE_ONCE, self::T_REQUIRE, self::T_REQUIRE_ONCE,
        );

        switch (true) {
            case is_string($token) && $token === '"' && self::hasToken($fileManipulatorTokens, $prevToken):
            case is_string($prevToken) && $prevToken === '"' && self::hasToken($fileManipulatorTokens, $priorToken):
            case self::tokenIs($token, self::T_ENCAPSED_AND_WHITESPACE) &&
                self::hasToken($fileManipulatorTokens, $prevToken):
            case self::hasToken($fileManipulatorTokens, $token):
                return true;
        }

        return false;
    }
}
