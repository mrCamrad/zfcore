<?php
/**
 * Class Core_Migration_Manager
 *
 * Migration manager
 *
 * @category Core
 * @package  Core_Migration
 *
 * @author   Anton Shevchuk <AntonShevchuk@gmail.com>
 * @link     http://anton.shevchuk.name
 * 
 * @version  $Id: Manager.php 219 2010-12-07 10:49:15Z dmitriy.britan $
 */
class Core_Migration_Manager
{
    /**
     * Variable contents options
     *
     * @var array
     */
    protected $_options = array(
        // Migrations schema table name
        'migrationsSchemaTable'   => 'migrations',
        // Path to project directory
        'projectDirectoryPath'    => null,
        // Path to modules directory
        'modulesDirectoryPath'    => null,
        // Migrations directory name
        'migrationsDirectoryName' => 'migrations',  
    );
    
    /**
     * Message stack
     * 
     * @var array 
     */
    protected $_messages   = array();
    
    /**
     * Migration exists and ready to load
     */
    const MIGRATION_STATUS_READY     = 1;
    
    /**
     * Migration loaded but not exists (conflict situation need to be resolved,
     * down command blocked)
     */
    const MIGRATION_STATUS_NOTEXISTS = 2;
    
    /**
     * Migration exists and loaded
     */
    const MIGRATION_STATUS_USED      = 3;
    
    /**
     * Migration exists but less than current and not loaded
     * (conflict situation need to be resolved, up command blocked)
     */
    const MIGRATION_STATUS_NOTLOADED = 9;
    
    /**
     * Constructor of Core_Migration_Manager
     *
     * @access  public
     * @param   array $options
     */
    public function __construct($options = array()) 
    {
        if ($options) {
            $this->_options = array_merge($this->_options, $options);
        }
        
        $this->_init();
    }
    
    /**
     * Method initialize migration schema table
     */
    protected function _init()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `".$this->getMigrationsSchemaTable()."`(
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `module` varchar(128) NOT NULL,
                `migration` varchar(64) NOT NULL,
                `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `UNIQUE_MIGRATION` (`module`,`migration`)
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
        ";
        Zend_Db_Table::getDefaultAdapter()->query($sql);
    }
    
    /**
     * Method return application directory path
     * 
     * @return string
     */
    public function getProjectDirectoryPath()
    {
        if (null == $this->_options['projectDirectoryPath']) {
            throw new Zend_Exception('Project directory path undefined.');
        }
        
        return $this->_options['projectDirectoryPath'];
    }
    
    /**
     * Method set application directory path
     * 
     * @param  string $value
     * @return Core_Migration_Manager 
     */
    public function setProjectDirectoryPath($value)
    {
        $this->_options['projectDirectoryPath'] = $value;
        return $this;
    }
    
    /**
     * Method return application directory path
     * 
     * @return string
     */
    public function getModulesDirectoryPath()
    {
        if (null == $this->_options['modulesDirectoryPath']) {
            throw new Zend_Exception('Modules directory path undefined.');
        }
        
        return $this->_options['modulesDirectoryPath'];
    }
    
    /**
     * Method set application directory path
     * 
     * @param string $value
     * @return Core_Migration_Manager 
     */
    public function setModulesDirectoryPath($value)
    {
        $this->_options['modulesDirectoryPath'] = $value;
        return $this;
    }
    
    /**
     * Method return application directory path
     * 
     * @return string
     */
    public function getMigrationsDirectoryName()
    {
        if (null == $this->_options['migrationsDirectoryName']) {
            throw new Zend_Exception('Migrations directory name undefined.');
        }
        
        return $this->_options['migrationsDirectoryName'];
    }
    
    /**
     * Method returns path to migrations directory
     * 
     * @param string $module Module name
     * @return string 
     */
    public function getMigrationsDirectoryPath($module = null)
    {
        if (null == $module) {
            $path = $this->getProjectDirectoryPath();
            $path .= '/' . $this->getMigrationsDirectoryName();
        } else {
            $modulePath = $this->getModulesDirectoryPath() . '/' . $module;
            
            if (!file_exists($modulePath))
                throw new Zend_Exception('Module `'.$module.'` not exists.');
            
            $path = $modulePath . '/' . $this->getMigrationsDirectoryName();
        }
        
        $this->_preparePath($path);
        
        return $path;
    }
    
    /**
     * Method prepare path (create not existing dirs)
     * 
     * @param string $path 
     */
    protected function _preparePath($path)
    {
        if (!is_dir($path)) {
            $this->_preparePath(dirname($path));
            mkdir($path, 0777);
        }
    }
    
    /**
     * Method return migrations schema table
     *  
     * @return string
     */
    public function getMigrationsSchemaTable()
    {
        return $this->_options['migrationsSchemaTable'];
    }
    
    /**
     * Method returns array of exists in filesystem migrations
     *
     * @param string $module Module name
     * @return array
     */
    public function getExistsMigrations($module = null) 
    {
        $filesDirty = scandir($this->getMigrationsDirectoryPath($module));

        $migrations = array();

        // foreach loop for $filesDirty array
        foreach ($filesDirty as $file) {
            if (preg_match('/\d{8}_\d{6}_\d{2}\.php/', $file)) {
                array_push($migrations, substr($file, 0, -4));
            }
        }

        sort($migrations);

        return $migrations;
    }
    
    /**
     * Method return array of loaded migrations
     * 
     * @param string $module Module name
     * @return array 
     */
    public function getLoadedMigrations($module = null) 
    {
        $select = Zend_Db_Table::getDefaultAdapter()->select()
            ->from($this->getMigrationsSchemaTable())
            ->where("module = ?", (null === $module) ? '' : $module)
            ->order('migration ASC');

        $items = Zend_Db_Table::getDefaultAdapter()->fetchAll($select);

        $migrations = array();
        foreach ($items as $item) {
            $migrations[] = $item['migration'];
        }

        return $migrations;
    }    
    
    /**
     * Method returns stack of messages
     *
     * @return array
     */
    public function getMessages() 
    {
        return $this->_messages;
    }
    
    /**
     * Method retirns last migration for selected module
     *
     * @return string
     */
    public function getLastMigration($module = null) 
    {
        $lastMigration = null;
        
        try {
            $select = Zend_Db_Table::getDefaultAdapter()->select()
                ->from($this->getMigrationsSchemaTable(), array('migration'))
                ->where("module = ?", (null === $module) ? '' : $module)
                ->order('migration DESC')
                ->limit(1);

            $lastMigration
                = Zend_Db_Table::getDefaultAdapter()->fetchOne($select);

            if (empty($this->_lastMigration)) {
                throw new Zend_Exception("
                    Not found migration version in database
                ");
            }
        } catch (Exception $e) {
            // maybe table is not exist; this is first revision
            $this->_lastMigration = '0';
        }
        
        return $lastMigration;
    }
    
    /**
     * Method create's new migration file
     * 
     * @param  string $module Module name
     * @return string Migration name
     */
    public function create($module = null)
    {
        $path = $this->getMigrationsDirectoryPath($module);

        list($sec, $msec) = explode(".", microtime(true));
        $_migrationName = date('Ymd_His_') . sprintf("%02d", $msec);
        
        // Configuring after instantiation
        $methodUp = new Zend_CodeGenerator_Php_Method();
        $methodUp->setName('up')
                 ->setBody('// upgrade');
                 
        // Configuring after instantiation
        $methodDown = new Zend_CodeGenerator_Php_Method();
        $methodDown->setName('down')
                   ->setBody('// degrade');
                   
        $class = new Zend_CodeGenerator_Php_Class();
        $className = ((null !== $module) ? ucfirst($module).'_' : '') 
            . 'Migration_'
            . $_migrationName;
        
        $class->setName($className)
              ->setExtendedClass('Core_Migration_Abstract')
              ->setMethod($methodUp)
              ->setMethod($methodDown);

        $file = new Zend_CodeGenerator_Php_File();
        $file->setClass($class)
             ->setFilename($path . '/' . $_migrationName . '.php')
             ->write();
             
        return $_migrationName;
    }
    
    /**
     * Method upgrade all migration or migrations to selected
     *
     * @param string $module Module name
     * @param string $to     Migration name
     */
    public function up($module = null, $to = null) 
    {
        $lastMigration = $this->getLastMigration($module);
        
        if ($to) {
            if (!self::isMigration($to)) {
                throw new Zend_Exception("Migration name '$to' is not valid");
            } elseif ($lastMigration == $to) {
                throw new Zend_Exception("Migration `'$to'` is current");
            } elseif ($lastMigration > $to) {
                throw new Zend_Exception("
                    Migration `".$to."` is older than current "
                    . "migration `".$lastMigration."`
                ");
            }
        }
        
        $exists = $this->getExistsMigrations($module);
        $loaded = $this->getLoadedMigrations($module);
        
        $ready = array_diff($exists, $loaded);
        
        if (sizeof($ready) == 0) {
            array_push($this->_messages, 'No migrations to upgrade.');
            return;
        }
        
        sort($ready);
        
        if (($to) && (!in_array($to, $exists))) {
            throw new Zend_Exception('Migration `'.$to.'` not exists');
        }
        
        foreach ($ready as $migration) {
            if ($migration < $lastMigration) { 
                throw new Zend_Exception("
                    Migration `".$migration."` is conflicted
                ");
            }
            
            try {
                $includePath = $this->getMigrationsDirectoryPath($module)
                    . '/' . $migration . '.php';

                include_once $includePath;

                $moduleAddon = ((null !== $module) ? ucfirst($module).'_' : '');

                $migrationClass  = $moduleAddon . 'Migration_'.$migration;
                $migrationObject = new $migrationClass;               
                
                $migrationObject->getDbAdapter()->beginTransaction();
                try {
                    $migrationObject->up();
                    $migrationObject->getDbAdapter()->commit();
                } catch (Exception $e) {
                    $migrationObject->getDbAdapter()->rollBack();
                    throw new Zend_Exception($e->getMessage());
                }
                
                array_push(
                    $this->_messages,
                    "Upgrade to revision '$migration'"
                );

                $this->_pushMigration($module, $migration);
            } catch (Exception $e) {
                throw new Zend_Exception(
                    "Migration '$migration' return exception:\n"
                    . $e->getMessage()
                );
            }
            
            if (($to) && ($migration == $to)) { 
                break;
            }
        }
    }

    public function fake($module, $to)
    {
        $lastMigration = $this->getLastMigration($module);

        if ($to) {
            if (!self::isMigration($to)) {
                throw new Zend_Exception("Migration name '$to' is not valid");
            } elseif ($lastMigration == $to) {
                throw new Zend_Exception("Migration `'$to'` is current");
            }

            $exists = $this->getExistsMigrations($module);

            if (($to) && (!in_array($to, $exists))) {
                array_push($this->_messages, 'Migration `'.$to.'` not exists');
                return;
            }

            $loaded = $this->getLoadedMigrations($module);
            
            if (($to) && (in_array($to, $loaded))) {
                array_push(
                    $this->_messages,
                    'Migration `'.$to.'` already executed'
                );
                return;
            }

            $this->_pushMigration($module, $to);
            array_push(
                $this->_messages,
                "Fake upgrade to revision '$migration'"
            );

        } else {
            array_push(
                $this->_messages,
                'Need migration name for fake upgrade.'
            );
            return;
        }
    }
    
    /**
     * Method downgrade all migration or migrations to selected
     *
     * @param string $module Module name
     * @param int    $to     Migration name
     */
    public function down($module, $to = null)
    {
        $lastMigration = $this->getLastMigration($module);
        
        if (null !== $to) {
            if (!self::isMigration($to)) {
                throw new Zend_Exception("Migration name '$to' is not valid");
            } elseif ($lastMigration == $to) {
                throw new Zend_Exception("Migration `'$to'` is current");
            } elseif ($lastMigration < $to) {
                throw new Zend_Exception("
                    Migration `".$to."` is younger than current "
                    . "migration `".$lastMigration."`
                ");
            }
        }

        $exists = $this->getExistsMigrations($module);
        $loaded = $this->getLoadedMigrations($module);
        
        if (sizeof($loaded) == 0) {
            array_push($this->_messages, 'No migrations to degrade.');
            return;            
        }
        
        rsort($loaded);

        if (($to) && (!in_array($to, $loaded))) {
            throw new Zend_Exception('Migration `'.$to.'` not loaded');
        }

        foreach ($loaded as $migration) {
            
            if (($to) && ($migration == $to)) { 
                break;
            }

            if (!in_array($migration, $exists)) {
                throw new Zend_Exception("
                    Migration `".$migration."` not exists
                ");
            }
            
            try {
                $includePath = $this->getMigrationsDirectoryPath($module)
                    . '/' . $migration . '.php';

                include_once $includePath;

                $moduleAddon = ((null !== $module) ? ucfirst($module).'_' : '');

                $migrationClass  = $moduleAddon . 'Migration_'.$migration;
                $migrationObject = new $migrationClass;               
                
                $migrationObject->getDbAdapter()->beginTransaction();
                try {
                    $migrationObject->down();
                    $migrationObject->getDbAdapter()->commit();
                } catch (Exception $e) {
                    $migrationObject->getDbAdapter()->rollBack();
                    throw new Zend_Exception($e->getMessage());
                }
                
                array_push($this->_messages, "Degrade migration '$migration'");

                $this->_pullMigration($module, $migration);
            } catch (Exception $e) {
                throw new Zend_Exception(
                    "Migration '$migration' return exception:\n"
                    . $e->getMessage()
                );
            }
            
            //if (!$to) { break; }
        }        
    }
    
    /**
     * Method rollback last migration or few last migrations
     * 
     * @param string $module Module name
     * @param int    $step   Steps to rollback
     */
    public function rollback($module, $step)
    {
        $lastMigration = $this->getLastMigration($module);
        
        if (!is_numeric($step) || ($step <= 0)) {
            throw new Zend_Exception("Step count '$step' is invalid");
        }

        $exists = $this->getExistsMigrations($module);
        $loaded = $this->getLoadedMigrations($module);
        
        if (sizeof($loaded) == 0) {
            array_push($this->_messages, 'No migrations to rollback.');
            return;            
        }
        
        rsort($loaded);

        foreach ($loaded as $migration) {
            
            if (!in_array($migration, $exists)) {
                throw new Zend_Exception("
                    Migration `".$migration."` not exists
                ");
            }
            
            try {
                $includePath = $this->getMigrationsDirectoryPath($module)
                    . '/' . $migration . '.php';

                include_once $includePath;

                $moduleAddon = ((null !== $module) ? ucfirst($module).'_' : '');

                $migrationClass  = $moduleAddon . 'Migration_'.$migration;
                $migrationObject = new $migrationClass;               
                
                $migrationObject->getDbAdapter()->beginTransaction();
                try {
                    $migrationObject->down();
                    $migrationObject->getDbAdapter()->commit();
                } catch (Exception $e) {
                    $migrationObject->getDbAdapter()->rollBack();
                    throw new Zend_Exception($e->getMessage());
                }
                
                array_push($this->_messages, "Degrade migration '$migration'");

                $this->_pullMigration($module, $migration);
            } catch (Exception $e) {
                throw new Zend_Exception(
                    "Migration '$migration' return exception:\n"
                    . $e->getMessage()
                );
            }
            
            $step--;
            if ($step <= 0) {
                break;
            }
        }  
    }
    
    /**
     * Method add migration to schema table
     * 
     * @param string $module    Module name
     * @param string $migration Migration name
     * @return Core_Migration_Manager 
     */
    protected function _pushMigration($module, $migration)
    {
        if (null === $module) { 
            $module = '';
        }
        
        try {
            $sql = "
                INSERT INTO `".$this->getMigrationsSchemaTable()."`
                SET module = ?, migration = ?
            ";
            Zend_Db_Table::getDefaultAdapter()
                ->query($sql, array($module, $migration));
        } catch (Exception $e) {
            // table is not exist
        }
        
        return $this;
    }
    
    /**
     * Methos remove migration from schema table
     *  
     * @param string $module    Module name
     * @param string $migration Migration name
     * @return Core_Migration_Manager 
     */
    protected function _pullMigration($module, $migration)
    {
        if (null === $module) { 
            $module = '';
        }
        
        try {
            $sql = "
                DELETE FROM `".$this->getMigrationsSchemaTable()."`
                WHERE module = ? AND migration = ?
            ";

            Zend_Db_Table::getDefaultAdapter()
                ->query($sql, array($module, $migration));
        } catch (Exception $e) {
            // table is not exist
        }
        
        return $this;
    }
    
    /**
     * Method check string, if string valid migration name returns true
     * 
     * @param string $value String to check
     * @return boolean 
     */
    public static function isMigration($value)
    {
        return ('0' == $value) || preg_match('/^\d{8}_\d{6}_\d{2}$/', $value);
    }
}