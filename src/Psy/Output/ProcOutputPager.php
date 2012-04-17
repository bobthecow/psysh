<?php

namespace Psy\Output;

use Psy\Output\OutputPager;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class ProcOutputPager extends StreamOutput implements OutputPager
{
    private $proc;
    private $pipe;
    private $stream;
    private $cmd;

    public function __construct(StreamOutput $output, $cmd = 'less -R -S -F -X')
    {
        $this->stream = $output->getStream();
        $this->cmd    = $cmd;
    }

    /**
     * Writes a message to the output.
     *
     * @param string  $message A message to write to the output
     * @param Boolean $newline Whether to add a newline or not
     *
     * @throws \RuntimeException When unable to write output (should never happen)
     */
    public function doWrite($message, $newline)
    {
        $pipe = $this->getPipe();
        if (false === @fwrite($pipe, $message.($newline ? PHP_EOL : ''))) {
            // @codeCoverageIgnoreStart
            // should never happen
            throw new \RuntimeException('Unable to write output.');
            // @codeCoverageIgnoreEnd
        }

        fflush($pipe);
    }

    public function close()
    {
        if (isset($this->pipe)) {
            fclose($this->pipe);
        }

        if (isset($this->proc)) {
            $exit = proc_close($this->proc);
            if ($exit !== 0) {
                throw new \RuntimeException('Error closing output stream');
            }
        }

        unset($this->pipe, $this->proc);
    }

    private function getPipe()
    {
        if (!isset($this->pipe) || !isset($this->proc)) {
            $desc = array(array('pipe', 'r'), $this->stream, STDERR);
            $this->proc = proc_open($this->cmd, $desc, $pipes);

            if (!is_resource($this->proc)) {
                throw new \RuntimeException('Error opening output stream');
            }

            $this->pipe = $pipes[0];
        }

        return $this->pipe;
    }
}
