<?php

/**
 * Classes source autoload
 *
 * @author Fabrizio Branca
 */
class Varien_Autoload
{
    const SCOPE_FILE_PREFIX = '__';
    const CACHE_KEY_PREFIX  = 'classPathCache';

    static protected $_instance;
    static protected $_scope = 'default';
    static protected $_cache = array();
    static protected $_numberOfFilesAddedToCache = 0;

    static public $useAPC = NULL;
    static public $useOPC = NULL;
    static protected $cacheKey = self::CACHE_KEY_PREFIX;

    /* Base Path */
    static protected $_BP = '';

    /**
     * Class constructor
     */
    public function __construct()
    {
        if (defined('BP')) {
            self::$_BP = BP;
        } elseif (strpos($_SERVER["SCRIPT_FILENAME"], 'get.php') !== FALSE) {
            global $bp; //get from get.php
            if (isset($bp) && !empty($bp)) {
                self::$_BP = $bp;
            }
        }

        // Allow APC to be disabled externally by explicitly setting Varien_Autoload::$useAPC = FALSE;
        if (self::$useAPC === NULL) {
            self::$useAPC = extension_loaded('apc') && ini_get('apc.enabled');
        }
        if (self::$useOPC === NULL) {
            self::$useOPC = extension_loaded('Zend OPcache') && ini_get('opcache.enable');
        }

        self::$cacheKey = self::CACHE_KEY_PREFIX . "_" . md5(self::$_BP);
        self::registerScope(self::$_scope);
        self::loadCacheContent();
    }

    /**
     * Singleton pattern implementation
     *
     * @return Varien_Autoload
     */
    static public function instance()
    {
        if (!self::$_instance) {
            self::$_instance = new Varien_Autoload();
        }
        return self::$_instance;
    }

    /**
     * Register SPL autoload function
     */
    static public function register()
    {
        spl_autoload_register(array(self::instance(), 'autoload'));
    }

    /**
     * Load class source code
     *
     * @param string $class
     *
     * @return bool
     */
    public function autoload($class)
    {
        // Prevent fatal errors when PHP has already started shutting down
        if (!isset(self::$_cache)) {
            $classFile = str_replace(' ', DIRECTORY_SEPARATOR, ucwords(str_replace('_', ' ', $class)));
            return include $classFile . '.php';
        }

        // Get file path (from cache if available)
        $realPath = self::getFullPath($class);
        if ($realPath !== FALSE) {
            return include self::$_BP . DIRECTORY_SEPARATOR . $realPath;
        }
        return FALSE;
    }

    /**
     * Get file name from class name
     *
     * @param string $className
     *
     * @return string
     */
    static function getFileFromClassName($className)
    {
        return str_replace(' ', DIRECTORY_SEPARATOR, ucwords(str_replace('_', ' ', $className))) . '.php';
    }

    /**
     * Register autoload scope
     * This process allow include scope file which can contain classes
     * definition which are used for this scope
     *
     * @param string $code scope code
     */
    static public function registerScope($code)
    {
        self::$_scope = $code;
    }

    /**
     * Get current autoload scope
     *
     * @return string
     */
    static public function getScope()
    {
        return self::$_scope;
    }

    /**
     * Get cache file path
     *
     * @return string
     */
    static public function getCacheFilePath()
    {
        return self::$_BP . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'classPathCache.php';
    }

    /**
     * Get revalidate flag file path
     *
     * @return string
     */
    static public function getRevalidateFlagPath()
    {
        return self::$_BP . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'classPathCache.flag';
    }

    /**
     * Setting cache content
     *
     * @param array $cache
     */
    static public function setCache(array $cache)
    {
        self::$_cache = $cache;
    }

    /**
     * Load cache content from file
     *
     * @return array
     */
    static public function loadCacheContent()
    {

        if (self::isApcUsed()) {
            $value = apc_fetch(self::getCacheKey());
            if ($value !== FALSE) {
                self::setCache($value);
            }
        } elseif (TRUE === file_exists(self::getCacheFilePath())) {
            self::setCache(include_once(self::getCacheFilePath()));
        }

        if (TRUE === file_exists(self::getRevalidateFlagPath()) && unlink(self::getRevalidateFlagPath())) {
            // When this is called there might not be an autoloader in place. So we need to manually load all the needed classes:
            require_once implode(DIRECTORY_SEPARATOR, array(self::$_BP, 'app', 'code', 'core', 'Mage', 'Core', 'Helper', 'Abstract.php'));
            require_once implode(DIRECTORY_SEPARATOR, array(self::$_BP, 'app', 'code', 'local', 'Aoe', 'ClassPathCache', 'Helper', 'Data.php'));
            $helper = new Aoe_ClassPathCache_Helper_Data;
            $helper->revalidateCache();
        }
    }

    /**
     * Get full path
     *
     * @param $className
     *
     * @return mixed
     */
    static public function getFullPath($className)
    {
        if (!isset(self::$_cache[$className])) {
            self::$_cache[$className] = self::searchFullPath(self::getFileFromClassName($className));
            // removing the basepath
            self::$_cache[$className] = str_replace(self::$_BP . DIRECTORY_SEPARATOR, '', self::$_cache[$className]);
            self::$_numberOfFilesAddedToCache++;
        }
        return self::$_cache[$className];
    }

    /**
     * Checks if a file exists in the include path and returns the full path if the file exists
     *
     * @param $filename
     *
     * @return bool|string
     */
    static public function searchFullPath($filename)
    {
        // return stream_resolve_include_path($filename);
        $paths = explode(PATH_SEPARATOR, get_include_path());
        foreach ($paths as $path) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $filename;
            if (TRUE === @file_exists($fullPath)) {
                return $fullPath;
            }
        }
        return FALSE;
    }

    /**
     * Check if apc is used
     *
     * @return bool
     */
    public static function isApcUsed()
    {
        return self::$useAPC;
    }

    /**
     * Check if opcache is used
     *
     * @return bool
     */
    public static function isOpCacheUsed()
    {
        return self::$useOPC;
    }

    /**
     * Get cache key (for apc)
     *
     * @return string
     */
    public static function getCacheKey()
    {
        return self::$cacheKey;
    }

    /**
     * Get cache
     *
     * @return array
     */
    public static function getCache()
    {
        return self::$_cache;
    }

    /**
     * first invalidate it, then compile it. if compile functions does not exists then the next request will compile it.
     * A simple opcache_compile_file is not enough to refresh it in the opcache
     *
     * @param null|string $fileName
     */
    public static function opCachePrime($fileName = NULL)
    {
        $fileName = NULL === $fileName
            ? self::getCacheFilePath()
            : $fileName;

        if (TRUE === function_exists('opcache_invalidate')) {
            opcache_invalidate($fileName, TRUE);
        }
        if (TRUE === function_exists('opcache_compile_file')) {
            opcache_compile_file($fileName);
        }
    }

    /**
     * Class destructor
     */
    public function __destruct()
    {
        if (self::$_numberOfFilesAddedToCache > 0) {
            if (self::isApcUsed()) {
                if (PHP_SAPI != 'cli') {
                    apc_store(self::getCacheKey(), self::$_cache, 0);
                }
            } else {
                $fileContent = '<?php return ' . var_export(self::$_cache, 1) . ';'; // enable opcache
                $tmpFile     = tempnam(sys_get_temp_dir(), 'aoe_classpathcache');
                if (FALSE !== file_put_contents($tmpFile, $fileContent)) {
                    if (rename($tmpFile, self::getCacheFilePath())) {
                        @chmod(self::getCacheFilePath(), 0664);
                    } else {
                        @unlink($tmpFile);
                    }
                }

                if (TRUE === static::isOpCacheUsed()) {
                    static::opCachePrime();
                }
            }
        }
    }
}
