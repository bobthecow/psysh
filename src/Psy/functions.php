<?php

namespace Psy;

if (!function_exists('Psy\sh')) {
    /**
     * Command to return the eval-able code to startup PsySH.
     *
     *     eval(\Psy\sh());
     *
     * @return string
     */
    function sh()
    {
        return 'extract(\Psy\Shell::debug(get_defined_vars(), isset($this) ? $this : null));';
    }
}

if (!function_exists('Psy\info')) {
    function info()
    {
        $config = new Configuration();

        $core = array(
            'PsySH version'      => Shell::VERSION,
            'PHP version'        => PHP_VERSION,
            // 'config dir'         => $config->getConfigDir(),
            // 'data dir'           => $config->getDataDir(),
            // 'runtime dir'        => $config->getRuntimeDir(),
            'default includes'   => $config->getDefaultIncludes(),
            'require semicolons' => $config->requireSemicolons(),
            'config file'        => array(
                'default config file' => $config->getConfigFile(),
                'PSYSH_CONFIG env'    => getenv('PSYSH_CONFIG'),
            ),
        );

        if ($config->hasReadline()) {
            $info = readline_info();

            $readline = array(
                'readline available' => true,
                'readline enabled'   => $config->useReadline(),
                'readline service'   => get_class($config->getReadline()),
                'readline library'   => $info['library_version'],
            );

            if ($info['readline_name'] !== '') {
                $readline['readline name'] = $info['readline_name'];
            }
        } else {
            $readline = array(
                'readline available' => false,
            );
        }

        $pcntl = array(
            'pcntl available' => function_exists('pcntl_signal'),
            'posix available' => function_exists('posix_getpid'),
        );

        $history = array(
            'history file'     => $config->getHistoryFile(),
            'history size'     => $config->getHistorySize(),
            'erase duplicates' => $config->getEraseDuplicates(),
        );

        $docs = array(
            'manual db file'   => $config->getManualDbFile(),
            'sqlite available' => true,
        );

        try {
            if ($db = $config->getManualDb()) {
                if ($q = $db->query('SELECT * FROM meta;')) {
                    $q->setFetchMode(\PDO::FETCH_KEY_PAIR);
                    $meta = $q->fetchAll();

                    foreach ($meta as $key => $val) {
                        switch ($key) {
                            case 'built_at':
                                $d = new \DateTime('@' . $val);
                                $val = $d->format(\DateTime::RFC2822);
                                break;
                        }
                        $key = 'db ' . str_replace('_', ' ', $key);
                        $docs[$key] = $val;
                    }
                } else {
                    $docs['db schema'] = '0.1.0';
                }
            }
        } catch (Exception\RuntimeException $e) {
            if ($e->getMessage() === 'SQLite PDO driver not found') {
                $docs['sqlite available'] = false;
            } else {
                throw $e;
            }
        }

        $autocomplete = array(
            'tab completion enabled' => $config->getTabCompletion(),
            'custom matchers'        => array_map('get_class', $config->getTabCompletionMatchers()),
        );

        return array_merge($core, compact('pcntl', 'readline', 'history', 'docs', 'autocomplete'));
    }
}
