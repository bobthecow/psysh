<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\TabCompletion\Matcher;

use Psy\TabCompletion\Matcher\ClassNamesMatcher;
use Psy\Test\TestCase;

class ClassNamesMatcherTest extends TestCase
{
    public function testMatchesDeclaredClasses()
    {
        $matcher = new ClassNamesMatcher();

        $tokens = [[\T_OPEN_TAG, '<?php ', 1], [\T_STRING, 'stdCla', 1]];

        $this->assertTrue($matcher->hasMatched($tokens));

        $matches = $matcher->getMatches($tokens);
        $this->assertContains('stdClass', $matches);
    }

    public function testMatchesNamespacedClasses()
    {
        $matcher = new ClassNamesMatcher();

        $tokens = [[\T_OPEN_TAG, '<?php ', 1], [\T_STRING, 'Psy\\', 1]];

        $this->assertTrue($matcher->hasMatched($tokens));

        $matches = $matcher->getMatches($tokens);

        // Should include Psy classes (which are declared)
        $hasPsyClass = false;
        foreach ($matches as $match) {
            if (
                \strpos($match, 'Configuration') !== false ||
                \strpos($match, 'Shell') !== false
            ) {
                $hasPsyClass = true;
                break;
            }
        }

        $this->assertTrue($hasPsyClass, 'Should include declared PsySH classes');
    }

    public function testIncludesTraitsAndInterfaces()
    {
        // Load a trait fixture
        require_once __DIR__.'/../../fixtures/TraitFixture.php';

        $matcher = new ClassNamesMatcher();

        $tokens = [[\T_OPEN_TAG, '<?php ', 1], [\T_STRING, '', 1]];
        $matches = $matcher->getMatches($tokens);

        // Verify some known symbols are present
        $this->assertContains('stdClass', $matches); // Class
        $this->assertContains('Traversable', $matches); // Interface
        $this->assertContains('Countable', $matches); // Interface
        $this->assertContains('ArrayAccess', $matches); // Interface
        $this->assertContains('JsonSerializable', $matches); // Interface
        $this->assertContains('Psy\\Test\\Fixtures\\TestTrait', $matches); // Trait
    }

    public function testMatchesLeadingBackslash()
    {
        $matcher = new ClassNamesMatcher();

        $tokens = [
            [\T_OPEN_TAG, '<?php ', 1],
            [\T_NS_SEPARATOR, '\\', 1],
            [\T_STRING, 'stdCla', 1],
        ];

        $this->assertTrue($matcher->hasMatched($tokens));

        $matches = $matcher->getMatches($tokens);
        $this->assertContains('stdClass', $matches);
    }

    public function testMatchesVariables()
    {
        // Note: The existing hasMatched logic actually matches T_VARIABLE
        // This allows completion after typing a variable name
        $matcher = new ClassNamesMatcher();

        $tokens = [[\T_VARIABLE, '$test', 1]];

        // This is the actual behavior (matches T_VARIABLE)
        $this->assertTrue($matcher->hasMatched($tokens));
    }

    public function testMatchesAfterNew()
    {
        $matcher = new ClassNamesMatcher();

        $tokens = [[\T_NEW, 'new', 1], [\T_STRING, 'stdCla', 1]];

        // With T_STRING as token and T_NEW as prevToken, this should match
        $this->assertTrue($matcher->hasMatched($tokens));
    }

    public function testDoesNotMatchAfterInclude()
    {
        $matcher = new ClassNamesMatcher();

        $tokens = [
            [\T_INCLUDE, 'include', 1],
            [\T_WHITESPACE, ' ', 1],
            [\T_STRING, 'file', 1],
        ];

        $this->assertFalse($matcher->hasMatched($tokens));
    }

    public function testMatchesWithClassesLoadedByWarmer()
    {
        // This test verifies that if a warmer has loaded classes at Shell startup,
        // they appear in tab completion

        $matcher = new ClassNamesMatcher();

        // Get current declared classes
        $declaredClasses = \get_declared_classes();

        $tokens = [[\T_OPEN_TAG, '<?php ', 1], [\T_STRING, '', 1]];

        $matches = $matcher->getMatches($tokens);

        // All declared classes should be in matches
        foreach ($declaredClasses as $class) {
            $this->assertContains($class, $matches);
        }
    }
}
