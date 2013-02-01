<?php

namespace Psy;

interface Readline
{
    public static function isSupported();
    public function addHistory($line);
    public function clearHistory();
    public function listHistory();
    public function readHistory();
    public function readline($prompt = null);
    public function redisplay();
    public function writeHistory();
}
