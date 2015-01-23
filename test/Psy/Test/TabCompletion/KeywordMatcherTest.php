<?php

namespace Psy\Test\TabCompletion;

use Psy\Context;
use Psy\TabCompletion\KeywordMatcher;

class KeywordMatcherTest extends \PHPUnit_Framework_TestCase
{
    public function testGetMatches()
    {
        $matcher = new KeywordMatcher(new Context());
        $keywords = $matcher->getKeywords();
        foreach ($keywords as $keyword) {
            $truncatedKeyword = substr($keyword, 0, strlen($keyword) - 2);
            $result = $matcher->getMatches($truncatedKeyword, strlen($truncatedKeyword));
            $this->assertContains($keyword, $result);
        }
    }
}
