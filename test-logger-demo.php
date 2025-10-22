#!/usr/bin/env php
<?php

/*
 * Demo script showing PsySH logging functionality.
 *
 * This script demonstrates how to configure PsySH with a logger
 * to capture input, commands, and executed code.
 */

require __DIR__.'/vendor/autoload.php';

use Psy\Configuration;
use Psy\Shell;

echo "PsySH Logger Demo\n";
echo "=================\n\n";

echo "Configuring PsySH with a simple callback logger...\n\n";

// Create a configuration with logging enabled
$config = new Configuration([
    'updateCheck' => 'never',
]);

// Simple callback - just pass a closure
$config->setLogging(function ($kind, $data) {
    echo "[{$kind}] {$data}\n";
});

echo "Logger configured with defaults:\n";
echo "  - input:   info\n";
echo "  - command: info\n";
echo "  - execute: debug\n\n";

echo "You can also use a PSR-3 logger:\n";
echo "  \$config->setLogging(\$psrLogger);\n\n";

echo "Or configure granular log levels:\n";
echo "  \$config->setLogging([\n";
echo "      'logger' => \$logger,\n";
echo "      'inputLevel' => 'debug',\n";
echo "      'commandLevel' => 'info',\n";
echo "      'executeLevel' => false,  // disable execute logging\n";
echo "  ]);\n\n";

echo "Starting PsySH shell with logging enabled...\n";
echo "Try these commands to see logging in action:\n";
echo "  - \$x = 123\n";
echo "  - ls\n";
echo "  - doc array_map\n";
echo "  - exit\n\n";
echo "---\n\n";

// Create and run the shell
$shell = new Shell($config);
$shell->run();
