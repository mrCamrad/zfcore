<?php

require_once 'Core/Migration/Manager.php';
require_once 'Core/Tool/Project/Provider/Abstract.php';
require_once 'Zend/Tool/Framework/Provider/Pretendable.php';

/**
 * Migration Provider
 * 
 * <code>
 * # create new migration 
 * ~$ ./zfc.sh create migration
 * 
 * # create new migration in module <module> 
 * ~$ ./zfc.sh create migration <module>
 * 
 * # migrate to last revision
 * ~$ ./zfc.sh up migration
 * 
 * # migrate to last revision in module <module>
 * ~$ ./zfc.sh up migration <module>
 * 
 * # degrade current revision
 * ~$ ./zfc.sh down migration
 * 
 * # degrade current revision in module <module>
 * ~$ ./zfc.sh down migration <module>
 * 
 * # migrate to 20081225-121256-04 revision
 * ~$ ./zfc.sh up migration 20081225_121256_04
 * 
 * # migrate to 20081225-121256-04 revision in module <module>
 * ~$ ./zfc.sh up migration <module> 20081225_121256_04
 * 
 * # migrate down to 20081225-121256-04 revision
 * ~$ ./zfc.sh down migration 20081225_121256_04
 * 
 * # migrate down to 20081225-121256-04 revision in module <module>
 * ~$ ./zfc.sh down migration <module> 20081225_121256_04
 * </code>
 * 
 * @category Core
 * @package  Core_Migration
 * 
 * @author   Anton Shevchuk <AntonShevchuk@gmail.com>
 * @link     http://anton.shevchuk.name
 */
class Core_Tool_Project_Provider_MigrationProvider
    extends Core_Tool_Project_Provider_Abstract
    implements Zend_Tool_Framework_Provider_Pretendable
{
    /**
     * Manager for migrations   
     *
     * @var Core_Migration_Manager
     */
    protected $_manager = null;
    
    protected $_colorOptions = array(
        // blacks
        'black'     => '30m',
        'hiBlack'   => '1;30m',
        'bgBlack'   => '40m',
        // reds
        'red'       => '31m',
        'hiRed'     => '1;31m',
        'bgRed'     => '41m',
        // greens
        'green'     => '32m',
        'hiGreen'   => '1;32m',
        'bgGreen'   => '42m',
        // yellows
        'yellow'    => '33m',
        'hiYellow'  => '1;33m',
        'bgYellow'  => '43m',
        // blues
        'blue'      => '34m',
        'hiBlue'    => '1;34m',
        'bgBlue'    => '44m',
        // magentas
        'magenta'   => '35m',
        'hiMagenta' => '1;35m',
        'bgMagenta' => '45m',
        // cyans
        'cyan'      => '36m',
        'hiCyan'    => '1;36m',
        'bgCyan'    => '46m',
        // whites
        'white'     => '37m',
        'hiWhite'   => '1;37m',
        'bgWhite'   => '47m'
        );
    
    /**
     * getName()
     *
     * @return string
     */
    public function getName()
    {
        return 'Migration';
    }
    
    /**
     * getManager()
     *
     * @return Core_Migration_Manager
     */
    protected function getManager()
    {
        if (null == $this->_manager) {
            $profile = $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION);

            $options = array(
                'projectDirectoryPath'
                    => self::_getProjectDirectoryPath($profile),
                'modulesDirectoryPath'
                    => self::_getModulesDirectoryPath($profile),
                'migrationsDirectoryName'
                    => 'migrations',
            );

            $this->_manager = new Core_Migration_Manager($options);
        }
        
        return $this->_manager;
    }
    
    /**
     * list of all avaliable migrations
     */
    public function listing($module = null)
    {
        require_once 'bootstrap.php';
        
        $this->message('----------------------------------');
        $this->message(chr(254).' - Already loaded', 'green');
        $this->message(chr(254).' - Ready for load', 'yellow');
        $this->message(chr(254).' - Loaded, not exists', 'red');
        $this->message(chr(254).' - Conflict, not load', 'bgRed');
        $this->message('----------------------------------');
        
        $lastMigration = $this->getManager()->getLastMigration($module);

        $exists = $this->getManager()->getExistsMigrations($module);
        $loaded = $this->getManager()->getLoadedMigrations($module);
        
        $migrations = array_merge($exists, $loaded);
        $migrations = array_unique($migrations);
        
        sort($migrations);
        
        foreach ($migrations as $migration) {
            $v = 0;
            if (in_array($migration, $exists)) { 
                $v = $v + 1;
            }
            if (in_array($migration, $loaded)) { 
                $v = $v + 2;
            }
            
            switch ($v) {
                case 1: 
                    $color = ($migration < $lastMigration) ? 'bgRed' : 'yellow';
                    break;
                case 2: 
                    $color = 'red';
                    break;
                case 3: 
                    $color = 'green';
                    break;
            }
            
            $this->message($migration, $color);
        }
    }
    
    /**
     * current migration
     */
    public function current($module = null)
    {
        require_once 'bootstrap.php';
        $revision = $this->getManager()->getLastMigration($module);
        if ('0' == $revision) {
            $this->message("None", 'hiWhite');
        } else {
            $this->message("Current migration is '$revision'", 'hiWhite');
        }
    }

    /**
     * _getMigrationsDirectoryResource()
     *
     * @param Zend_Tool_Project_Profile $profile
     * @param string $moduleName
     * @return Zend_Tool_Project_Profile_Resource
     */    
    protected static function _getMigrationsDirectoryResource(
        Zend_Tool_Project_Profile $profile, $moduleName = null)
    {
        $profileSearchParams = array();

        if ($moduleName != null && is_string($moduleName)) {
            $profileSearchParams = array(
                'modulesDirectory', 
                'moduleDirectory' => array('moduleName' => $moduleName)
            );
        } 

        $profileSearchParams[] = 'migrationsDirectory';
        
        return $profile->search($profileSearchParams);
    }    
    
    /**
     * Method returns path to project directory
     * 
     * @param  Zend_Tool_Project_Profile $profile
     * @return string
     */
    protected static function _getProjectDirectoryPath(
        Zend_Tool_Project_Profile $profile)
    {        
        $projectDirectory = $profile->search(array('projectDirectory'));

        if (!($projectDirectory instanceof Zend_Tool_Project_Profile_Resource)) {
            throw new Zend_Tool_Project_Provider_Exception("
                Project resource undefined.
            ");
        }            
      
        return $projectDirectory->getPath();
    }  
    
    /**
     * Method returns path to modules directory
     * 
     * @param  Zend_Tool_Project_Profile $profile
     * @return string
     */
    protected static function _getModulesDirectoryPath(
        Zend_Tool_Project_Profile $profile)
    {        
        $modulesDirectory = $profile->search(array('modulesDirectory'));

        if (!($modulesDirectory instanceof Zend_Tool_Project_Profile_Resource)) {
            throw new Zend_Tool_Project_Provider_Exception("
                Modules resource undefined.
            ");
        }            
      
        return $modulesDirectory->getPath();
    }
    
    /**
     * create new migration
     * 
     * @param string $name custom migration name
     */
    public function create($module = null)
    {
        require_once 'bootstrap.php';
        
        $migrationName = $this->getManager()->create($module);
        
        $message = "Migration '" . $migrationName . "' created"
            . ($module ? (" in module '" . $module."'") : "");
        
        $this->message($message, 'hiWhite');
        $this->message("Note: Don't forget run 'zf up migration'", 'yellow');
    }
    
    /**
     * up
     *
     * @param string $to revision uid
     */
    public function up($module = null, $to = null) 
    {
        if ((null === $to) && Core_Migration_Manager::isMigration($module)) {
            list($to, $module) = array($module, null);
        }
        
        require_once 'bootstrap.php';
        
        $this->getManager()->up($module, $to);
        
        foreach ($this->getManager()->getMessages() as $message) {
            $this->message($message, 'hiGreen');
        }
    }
    
    /**
     * down
     *
     * @param string $to revision uid
     */
    public function down($module = null, $to = null) 
    {        
        if ((null === $to) && Core_Migration_Manager::isMigration($module)) {
            list($to, $module) = array($module, null);
        }
        
        require_once 'bootstrap.php';

        $this->getManager()->down($module, $to);
        
        foreach ($this->getManager()->getMessages() as $message) {
            $this->message($message, 'hiRed');
        }
    }
    
    /**
     *
     * @param string $module
     * @param int    $step 
     */
    public function rollback($module = null, $step = null)
    {
        if (is_numeric($module) && (0 < (int)$module)) {
            list($step, $module) = array($module, null);
        }
        
        if (null === $step) { 
            $step = 1;
        }
        
        require_once 'bootstrap.php';

        $this->getManager()->rollback($module, $step);
        
        foreach ($this->getManager()->getMessages() as $message) {
            $this->message($message, 'hiRed');
        }
    }

    public function fake($module = null, $to = null)
    {
        if ((null === $to) && Core_Migration_Manager::isMigration($module)) {
            list($to, $module) = array($module, null);
        }

        require_once 'bootstrap.php';

        $this->getManager()->fake($module, $to);

        foreach ($this->getManager()->getMessages() as $message) {
            $this->message($message, 'hiGreen');
        }
    }
    
    /**
     * message
     *
     * print input message
     *
     * @param   string     $text
     */
    protected function message($text, $color = 'white') 
    {
        if (function_exists('posix_isatty')) {
            if (array_key_exists($color, $this->_colorOptions)) {
                echo "\033[" . $this->_colorOptions[$color].$text . "\033[m\n";
            }
        } else {
            echo $text . "\n";
        }
    }
}