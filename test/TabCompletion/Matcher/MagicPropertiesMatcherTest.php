<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\TabCompletion\Matcher;

use Psy\Context;
use Psy\TabCompletion\Matcher\MagicPropertiesMatcher;
use Psy\Test\Fixtures\Util\MagicChild;
use Psy\Test\Fixtures\Util\MagicClass;
use Psy\Test\Fixtures\Util\NoMagicClass;
use Psy\Test\TestCase;
use Psy\Util\Docblock;

class MagicPropertiesMatcherTest extends TestCase
{
    private MagicPropertiesMatcher $matcher;
    private Context $context;

    protected function setUp(): void
    {
        $this->matcher = new MagicPropertiesMatcher();
        $this->context = new Context();
        $this->matcher->setContext($this->context);
    }

    protected function tearDown(): void
    {
        Docblock::clearMagicCache();
    }

    public function testMatchesObjectOperator()
    {
        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_VARIABLE, '$foo', 1],
            [\T_OBJECT_OPERATOR, '->', 1],
        ];

        $this->assertTrue($this->matcher->hasMatched($tokens));
    }

    public function testMatchesObjectOperatorWithPartialProperty()
    {
        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_VARIABLE, '$foo', 1],
            [\T_OBJECT_OPERATOR, '->', 1],
            [\T_STRING, 'tit', 1],
        ];

        $this->assertTrue($this->matcher->hasMatched($tokens));
    }

    public function testMatchesDoubleColon()
    {
        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_STRING, 'SomeClass', 1],
            [\T_DOUBLE_COLON, '::', 1],
        ];

        $this->assertTrue($this->matcher->hasMatched($tokens));
    }

    public function testMatchesDoubleColonWithPartialProperty()
    {
        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_STRING, 'SomeClass', 1],
            [\T_DOUBLE_COLON, '::', 1],
            [\T_STRING, 'id', 1],
        ];

        $this->assertTrue($this->matcher->hasMatched($tokens));
    }

    public function testGetMatchesForInstanceProperties()
    {
        $obj = new MagicClass();
        $this->context->setAll(['obj' => $obj]);

        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_VARIABLE, '$obj', 1],
            [\T_OBJECT_OPERATOR, '->', 1],
        ];

        $matches = $this->matcher->getMatches($tokens);

        $this->assertContains('title', $matches);
        $this->assertContains('id', $matches);
        $this->assertContains('password', $matches);
    }

    public function testGetMatchesFiltersPrefix()
    {
        $obj = new MagicClass();
        $this->context->setAll(['obj' => $obj]);

        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_VARIABLE, '$obj', 1],
            [\T_OBJECT_OPERATOR, '->', 1],
            [\T_STRING, 'ti', 1],
        ];

        $matches = $this->matcher->getMatches($tokens);

        $this->assertContains('title', $matches);
        $this->assertNotContains('id', $matches);
        $this->assertNotContains('password', $matches);
    }

    public function testGetMatchesIncludesInheritedProperties()
    {
        $obj = new MagicChild();
        $this->context->setAll(['obj' => $obj]);

        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_VARIABLE, '$obj', 1],
            [\T_OBJECT_OPERATOR, '->', 1],
        ];

        $matches = $this->matcher->getMatches($tokens);

        $this->assertContains('childProperty', $matches);      // From MagicChild
        $this->assertContains('parentProperty', $matches);     // From MagicParent
        $this->assertContains('interfaceProperty', $matches);  // From MagicInterface
        $this->assertContains('traitProperty', $matches);      // From MagicTrait
    }

    public function testGetMatchesReturnsEmptyForNoMagicProperties()
    {
        $obj = new NoMagicClass();
        $this->context->setAll(['obj' => $obj]);

        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_VARIABLE, '$obj', 1],
            [\T_OBJECT_OPERATOR, '->', 1],
        ];

        $matches = $this->matcher->getMatches($tokens);

        $this->assertEmpty($matches);
    }

    public function testGetMatchesReturnsEmptyForUndefinedVariable()
    {
        // Empty context - no variables defined
        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_VARIABLE, '$undefined', 1],
            [\T_OBJECT_OPERATOR, '->', 1],
        ];

        $matches = $this->matcher->getMatches($tokens);

        $this->assertEmpty($matches);
    }

    public function testGetMatchesReturnsEmptyForNonObject()
    {
        $this->context->setAll(['str' => 'not an object']);

        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_VARIABLE, '$str', 1],
            [\T_OBJECT_OPERATOR, '->', 1],
        ];

        $matches = $this->matcher->getMatches($tokens);

        $this->assertEmpty($matches);
    }
}
