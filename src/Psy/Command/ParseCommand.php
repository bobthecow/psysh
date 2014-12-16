<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use PhpParser\Lexer;
use PhpParser\Parser;
use Psy\Presenter\PHPParserPresenter;
use Psy\Presenter\PresenterManager;
use Psy\Presenter\PresenterManagerAware;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Parse PHP code and show the abstract syntax tree.
 */
class ParseCommand extends Command implements PresenterManagerAware
{
    private $presenterManager;
    private $parser;

    /**
     * PresenterManagerAware interface.
     *
     * @param PresenterManager $manager
     */
    public function setPresenterManager(PresenterManager $manager)
    {
        $this->presenterManager = new PresenterManager();

        foreach ($manager as $presenter) {
            $this->presenterManager->addPresenter($presenter);
        }

        $this->presenterManager->addPresenter(new PHPParserPresenter());
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('parse')
            ->setDefinition(array(
                new InputArgument('code', InputArgument::REQUIRED, 'PHP code to parse.'),
                new InputOption('depth', '', InputOption::VALUE_REQUIRED, 'Depth to parse', 10),
            ))
            ->setDescription('Parse PHP code and show the abstract syntax tree.')
            ->setHelp(
                <<<HELP
Parse PHP code and show the abstract syntax tree.

This command is used in the development of PsySH. Given a string of PHP code,
it pretty-prints the PHP Parser parse tree.

See https://github.com/nikic/PHP-Parser

It prolly won't be super useful for most of you, but it's here if you want to play.
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $code = $input->getArgument('code');
        if (strpos('<?', $code) === false) {
            $code = '<?php ' . $code;
        }

        $depth = $input->getOption('depth');
        $nodes = $this->parse($code);
        $output->page($this->presenterManager->present($nodes, $depth));
    }

    /**
     * Lex and parse a string of code into statements.
     *
     * @param string $code
     *
     * @return array Statements
     */
    private function parse($code)
    {
        $parser = $this->getParser();

        try {
            return $parser->parse($code);
        } catch (\PhpParser\Error $e) {
            if (strpos($e->getMessage(), 'unexpected EOF') === false) {
                throw $e;
            }

            // If we got an unexpected EOF, let's try it again with a semicolon.
            return $parser->parse($code . ';');
        }
    }

    /**
     * Get (or create) the Parser instance.
     *
     * @return Parser
     */
    private function getParser()
    {
        if (!isset($this->parser)) {
            $this->parser = new Parser(new Lexer());
        }

        return $this->parser;
    }
}
