<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\StrictTypesPass;

class StrictTypesPassTest extends CodeCleanerTestCase
{
    /**
     * @before
     */
    public function getReady()
    {
        $this->setPass(new StrictTypesPass());
    }

    public function testProcess()
    {
        $this->assertProcessesAs('declare(strict_types=1)', 'declare (strict_types=1);');
        $this->assertProcessesAs('null', "declare (strict_types=1);\nnull;");
        $this->assertProcessesAs('declare(strict_types=0)', 'declare (strict_types=0);');
        $this->assertProcessesAs('null', 'null;');
    }

    /**
     * @dataProvider invalidDeclarations
     */
    public function testInvalidDeclarations($code)
    {
        $this->expectException(\Psy\Exception\FatalErrorException::class);
        $this->parseAndTraverse($code);

        $this->fail();
    }

    public function invalidDeclarations()
    {
        return [
            ['declare(strict_types=-1)'],
            ['declare(strict_types=2)'],
            ['declare(strict_types="foo")'],
        ];
    }
}
