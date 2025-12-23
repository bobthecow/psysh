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
use Psy\TabCompletion\Matcher\MagicMethodsMatcher;
use Psy\Test\TestCase;
use Psy\Test\Util\Fixtures\MagicChild;
use Psy\Test\Util\Fixtures\MagicClass;
use Psy\Test\Util\Fixtures\NoMagicClass;
use Psy\Util\Docblock;

class MagicMethodsMatcherTest extends TestCase
{
    private MagicMethodsMatcher $matcher;
    private Context $context;

    protected function setUp(): void
    {
        $this->matcher = new MagicMethodsMatcher();
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

    public function testMatchesObjectOperatorWithPartialMethod()
    {
        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_VARIABLE, '$foo', 1],
            [\T_OBJECT_OPERATOR, '->', 1],
            [\T_STRING, 'get', 1],
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

    public function testMatchesDoubleColonWithPartialMethod()
    {
        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_STRING, 'SomeClass', 1],
            [\T_DOUBLE_COLON, '::', 1],
            [\T_STRING, 'find', 1],
        ];

        $this->assertTrue($this->matcher->hasMatched($tokens));
    }

    public function testGetMatchesForInstanceMethods()
    {
        $obj = new MagicClass();
        $this->context->setAll(['obj' => $obj]);

        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_VARIABLE, '$obj', 1],
            [\T_OBJECT_OPERATOR, '->', 1],
        ];

        $matches = $this->matcher->getMatches($tokens);

        $this->assertContains('getName', $matches);
        $this->assertContains('setName', $matches);
        $this->assertContains('where', $matches);
    }

    public function testGetMatchesFiltersPrefix()
    {
        $obj = new MagicClass();
        $this->context->setAll(['obj' => $obj]);

        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_VARIABLE, '$obj', 1],
            [\T_OBJECT_OPERATOR, '->', 1],
            [\T_STRING, 'get', 1],
        ];

        $matches = $this->matcher->getMatches($tokens);

        $this->assertContains('getName', $matches);
        $this->assertNotContains('setName', $matches);
    }

    public function testGetMatchesIncludesInheritedMethods()
    {
        $obj = new MagicChild();
        $this->context->setAll(['obj' => $obj]);

        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_VARIABLE, '$obj', 1],
            [\T_OBJECT_OPERATOR, '->', 1],
        ];

        $matches = $this->matcher->getMatches($tokens);

        $this->assertContains('getChildMethod', $matches);      // From MagicChild
        $this->assertContains('getParentMethod', $matches);     // From MagicParent
        $this->assertContains('getInterfaceMethod', $matches);  // From MagicInterface
        $this->assertContains('getTraitMethod', $matches);      // From MagicTrait
    }

    public function testGetMatchesReturnsEmptyForNoMagicMethods()
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
