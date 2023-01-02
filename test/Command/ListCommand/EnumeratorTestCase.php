<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command\ListCommand;

use Psy\Command\ListCommand;
use Psy\VarDumper\Presenter;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\StringInput;

abstract class EnumeratorTestCase extends \Psy\Test\TestCase
{
    protected function getPresenter()
    {
        return new Presenter(new OutputFormatter(false));
    }

    protected function getInput($inputStr)
    {
        $cmd = new ListCommand();
        $input = new StringInput($inputStr);
        $input->bind($cmd->getDefinition());

        return $input;
    }

    /**
     * Helper to abstract wrapping number output for testing.
     *
     * Depending on the PHP and VarDumper versions, integers and floats may be
     * presented differently. Since most tests don't test *that* bit, we can use
     * a helper to abstract that away :)
     */
    protected function presentNumber($number): string
    {
        return $this->getPresenter()->present($number);
    }
}
