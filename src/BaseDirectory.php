<?php

namespace Psy;

class BaseDirectory
{
    const S_IF_DIRECTORY = 040000; // Directory
    const S_IR_RWX_OTHER = 00007;  // RWX Other
    const S_IR_RWX_GROUP = 00056;  // RWX Group
    const XDG_RUNTIME_FB = 'php-xdg-runtime-dir-fallback-'; // Fallback Runtime Directory

    /**
     * @return string
     */
    public function getHomeDirectory()
    {
        return getenv('HOME') ?: (getenv('HOMEDRIVE').DIRECTORY_SEPARATOR.getenv('HOMEPATH'));
    }

    /**
     * @return string
     */
    public function getConfigDirectory()
    {
        if ($path = getenv('XDG_CONFIG_HOME')) {
            return $path;
        }
        $homeDir = $this->getHomeDirectory();
        return $path = DIRECTORY_SEPARATOR === $homeDir ? $homeDir.'.config' : $homeDir.DIRECTORY_SEPARATOR.'.config';
    }

    /**
     * @return string
     */
    public function getHomeDataDirectory()
    {
        return getenv('XDG_DATA_HOME') ?: $this->getHomeDirectory().DIRECTORY_SEPARATOR.'.local'.DIRECTORY_SEPARATOR.'share';
    }

    /**
     * @return array
     */
    public function getConfigDirectories()
    {
        $configDirs = getenv('XDG_CONFIG_DIRS') ? explode(':', getenv('XDG_CONFIG_DIRS')) : array('/etc/xdg');

        return array_merge(array($this->GetConfigDirectory()), $configDirs);
    }

    /**
     * @return array
     */
    public function getDataDirectories()
    {
        $dataDirs = getenv('XDG_DATA_DIRS') ? explode(':', getenv('XDG_DATA_DIRS')) : array('/usr/local/share', '/usr/share');

        return array_merge(array($this->getHomeDataDirectory()), $dataDirs);
    }

    /**
     * @return string
     */
    public function getHomeCacheDirectory()
    {
        return getenv('XDG_CACHE_HOME') ?: $this->getHomeDirectory().DIRECTORY_SEPARATOR.'.cache';
    }

    /**
     * @param bool $strict
     * @return array|false|string
     */
    public function getRuntimeDirectory($strict=true)
    {
        if ($runtimeDir = getenv('XDG_RUNTIME_DIR')) {
            return $runtimeDir;
        }

        if ($strict) {
            throw new \RuntimeException('XDG_RUNTIME_DIR was not set');
        }

        $fallback = sys_get_temp_dir().DIRECTORY_SEPARATOR.self::XDG_RUNTIME_FB.getenv('USER');

        $create = false;

        if (!is_dir($fallback)) {
            mkdir($fallback, 0700, true);
        }

        $st = lstat($fallback);

        if (!$st['mode'] & self::S_IF_DIRECTORY) {
            rmdir($fallback);
            $create = true;
        } elseif ($st['uid'] != $this->getUUID() ||
            $st['mode'] & (self::S_IR_RWX_GROUP | self::S_IR_RWX_OTHER)
        ) {
            rmdir($fallback);
            $create = true;
        }

        if ($create) {
            mkdir($fallback, 0700, true);
        }

        return $fallback;
    }

    /**
     * @return int
     */
    private function getUUID()
    {
        if (function_exists('posix_getuid')) {
            return posix_getuid();
        }

        return getmyuid();
    }
}
