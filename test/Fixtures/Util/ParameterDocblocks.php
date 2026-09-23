<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Fixtures\Util;

// These comments are test input. This directory is excluded from StyleCI.
class ParameterDocblocks
{
    public static function withTaggedParameter(): \Closure
    {
        return function (
            string $name /**
             * The name to look up.
             *
             * @deprecated Use an ID instead.
             */
        ) {};
    }

    public static function withParameterOverrides(): \Closure
    {
        return
            /**
             * Look up names.
             *
             * @param list<string> &$names Outdated description.
             * @param string $legacy Keep this description.
             * @param positive-int ...$ids Old ID description.
             */
            function (
                /** An optional filter. */
                ?string $filter,
                /** The names to search. */
                array &$names,
                /** */
                string $legacy,
                /**
                 * The IDs to match.
                 * Include all matching names.
                 */
                int ...$ids
            ) {};
    }

    public static function withMarkup(): \Closure
    {
        return function (
            /**
             * The name to look up.
             * May contain <info>markup</info>.
             */
            string $name,
            int $limit /** The maximum results. */
        ) {};
    }

    public function search(/** The query. */ string $query, $options /** Search options. */, $undocumented)
    {
    }
}
