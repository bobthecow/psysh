<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\TabCompletion;

use Psy\TabCompletion\Matcher\AbstractMatcher;

/**
 * A readline tab completion service.
 *
 * @author Marc Garcia <markcial@gmail.com>
 */
class AutoCompleter
{
    /** @var Matcher\AbstractMatcher[] */
    protected $matchers;

    /**
     * The set of characters which separate completeable 'words', and
     * therefore determines the precise $input word for which completion
     * candidates should be generated (noting that each candidate must
     * begin with the original $input text).
     *
     * PHP's readline support does not provide any control over the
     * characters which constitute a word break for completion purposes,
     * which means that we are restricted to the default -- being the
     * value of GNU Readline's rl_basic_word_break_characters variable:
     *
     *   The basic list of characters that signal a break between words
     *   for the completer routine. The default value of this variable
     *   is the characters which break words for completion in Bash:
     *   " \t\n\"\\’‘@$><=;|&{(".
     *
     * This limitation has several ramifications for PHP completion:
     *
     *  1. The namespace separator '\' introduces a word break, and so
     *     class name completion is on a per-namespace-component basis.
     *     When completing a namespaced class, the (incomplete) $input
     *     parameter (and hence the completion candidates we return) will
     *     not include the separator or preceding namespace components.
     *
     *  2. The double-colon (nekudotayim) operator '::' does NOT introduce
     *     a word break (as ':' is not a word break character), and so the
     *     $input parameter will include the preceding 'ClassName::' text
     *     (typically back to, but not including, a space character or
     *     namespace-separator '\').  Completion candidates for class
     *     attributes and methods must therefore include this same prefix.
     *
     *  3. The object operator '->' introduces a word break (as '>' is a
     *     word break character), so when completing an object attribute
     *     or method, $input will contain only the text following the
     *     operator, and therefore (unlike '::') the completion candidates
     *     we return must NOT include the preceding object and operator.
     *
     *  4. '$' is a word break character, and so completion for variable
     *     names does not include the leading '$'.  The $input parameter
     *     contains only the text following the '$' and therefore the
     *     candidates we return must do likewise...
     *
     *  5. ...Except when we are returning ALL variables (amongst other
     *     things) as candidates for completing the empty string '', in
     *     which case we DO need to include the '$' character in each of
     *     our candidates, because it was not already present in the text.
     *     (Note that $input will be '' when we are completing either ''
     *     or '$', so we need to distinguish between those two cases.)
     *
     *  6. Only a sub-set of other PHP operators constitute (or end with)
     *     word breaks, and so inconsistent behaviour can be expected if
     *     operators are used without surrounding whitespace to ensure a
     *     word break has occurred.
     *
     *     Operators which DO break words include: '>' '<' '<<' '>>' '<>'
     *     '=' '==' '===' '!=' '!==' '>=' '<=' '<=>' '->' '|' '||' '&' '&&'
     *     '+=' '-=' '*=' '/=' '.=' '%=' '&=' '|=' '^=' '>>=' '<<=' '??='
     *
     *     Operators which do NOT break words include: '!' '+' '-' '*' '/'
     *     '++' '--' '**' '%' '.' '~' '^' '??' '? :' '::'
     *
     *     E.g.: although "foo()+bar()" is valid PHP, we would be unable
     *     to use completion to obtain the function name "bar" in that
     *     situation, as the $input string would actually begin with ")+"
     *     and the Matcher in question would not be returning candidates
     *     with that prefix.
     *
     * @see self::processCallback()
     * @see \Psy\Shell::getTabCompletions()
     */
    public const WORD_BREAK_CHARS = " \t\n\"\\’‘@$><=;|&{(";

    /**
     * A regular expression based on WORD_BREAK_CHARS which will match the
     * completable word at the end of the string.
     */
    public const WORD_REGEXP = "/[^ \t\n\"\\\\’‘@$><=;|&{(]*$/";

    /**
     * Register a tab completion Matcher.
     *
     * @param AbstractMatcher $matcher
     */
    public function addMatcher(AbstractMatcher $matcher)
    {
        $this->matchers[] = $matcher;
    }

    /**
     * Activate readline tab completion.
     */
    public function activate()
    {
        \readline_completion_function([&$this, 'callback']);
    }

    /**
     * Handle readline completion for the $input parameter (word).
     *
     * @see WORD_BREAK_CHARS
     *
     * @TODO: Post-process the completion candidates returned by each
     * Matcher to ensure that they use the common prefix established by
     * the $input parameter.
     *
     * @param string $input Readline current word
     * @param int    $index Current word index
     * @param array  $info  readline_info() data
     *
     * @return array
     */
    public function processCallback($input, $index, $info = [])
    {
        // Some (Windows?) systems provide incomplete `readline_info`, so let's
        // try to work around it.
        $line = $info['line_buffer'];
        if (isset($info['end'])) {
            $line = \substr($line, 0, $info['end']);
        }
        if ($line === '' && $input !== '') {
            $line = $input;
        }

        $tokens = \token_get_all('<?php '.$line);

        // Remove whitespace tokens, excluding the current token.
        $token = \array_pop($tokens);
        $tokens = \array_filter($tokens, function ($token) {
            return !AbstractMatcher::tokenIs($token, AbstractMatcher::T_WHITESPACE);
        });

        // If we're effectively completing an empty string, append a
        // token to represent this.  An empty string isn't actually a
        // valid token, so we use the value that is simplest to test.
        switch (true) {
            case AbstractMatcher::tokenIs($token, AbstractMatcher::T_WHITESPACE):
                $tokens[] = '';
                break;
            case AbstractMatcher::tokenIs($token, AbstractMatcher::T_VARIABLE):
                $tokens[] = $token;
                break;
            case !AbstractMatcher::tokenIsValidIdentifier($token):
                // This also covers/includes the cases !is_array($token)
                // (which is a super-set of AbstractMatcher::isOperator()),
                // and also tokenIs($token, AbstractMatcher::T_OPEN_TAG).
                // Therefore it will never be the case that one of those
                // things is the final token in the array; they can only
                // ever be the *previous* token.  Moreover, the final token
                // is always a valid completion prefix or else the empty
                // string.  This simplifies the set of cases that Matchers
                // need to cater for.
                $tokens[] = $token;
                $tokens[] = '';
                break;
            default:
                // We're completing a valid identifier.
                $tokens[] = $token;
                break;
        }

        $matches = [];
        foreach ($this->matchers as $matcher) {
            if ($matcher->hasMatched($tokens)) {
                $matches = \array_merge($matcher->getMatches($tokens), $matches);
            }
        }

        $matches = \array_unique($matches);

        return !empty($matches) ? $matches : [''];
    }

    /**
     * The readline_completion_function callback handler.
     *
     * @see processCallback
     *
     * @param string $input
     * @param int    $index
     *
     * @return array
     */
    public function callback($input, $index)
    {
        return $this->processCallback($input, $index, \readline_info());
    }

    /**
     * Remove readline callback handler on destruct.
     */
    public function __destruct()
    {
        // PHP didn't implement the whole readline API when they first switched
        // to libedit. And they still haven't.
        if (\function_exists('readline_callback_handler_remove')) {
            \readline_callback_handler_remove();
        }
    }
}
