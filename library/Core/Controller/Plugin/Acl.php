<?php
/**
 * Front Controller Plugin
 *
 * @uses       Zend_Controller_Plugin_Abstract
 *
 * @category   Core
 * @package    Core_Controller
 * @subpackage Plugins
 *
 * @version  $Id: Acl.php 223 2011-01-19 15:14:14Z AntonShevchuk $
 */
class Core_Controller_Plugin_Acl extends Zend_Controller_Plugin_Abstract
{
    /**
     * Plugin configuration settings array
     *
     * @var array
     */
    protected $_options = array();

    /**
     * Denied page settings
     *
     * @var array
     */
    protected $_deniedPage = array(
        'module'     => null,
        'controller' => 'error',
        'action'     => 'denied'
    );

    /**
     * Error page settings
     *
     * @var array
     */
    protected $_errorPage = array(
        'module'     => 'error',
        'controller' => 'error',
        'action'     => 'notfound'
    );

    /**
     * Denied page settings
     *
     * @var array
     */
    protected $_loginPage = array(
        'module'     => 'users',
        'controller' => 'login',
        'action'     => 'index'
    );

    /**
     * default role name
     *
     * @var string
     */
    protected $_roleName = 'guest';

    /**
     * ACL object
     *
     * @var Zend_Acl
     */
    protected $_acl;

    /**
     * configuration file with acl settings
     *
     * @var string
     */
    protected $_config = 'acl';

    /**
     * cache using
     *
     * @var boolean
     */
    protected $_cache = true;

    /**
     * Allow All
     *  or
     * Deny All
     *
     * @var bool
     */
    protected $_allowAll = false;

    /**
     * Config array
     *
     * @var array
     */
    protected $_configArray;

    /**
     * Constructor
     *
     * Options may include:
     * - module
     * - controller
     * - action
     *
     * @param  Array $options
     * @return void
     */
    public function __construct(Array $options = array())
    {
        if (isset($options['error'])) {
            $this->_errorPage = array_merge($this->_errorPage, $options['error']);
        }

        if (isset($options['denied'])) {
            $this->_deniedPage = array_merge($this->_deniedPage, $options['denied']);
        }

        if (isset($options['login'])) {
            $this->_loginPage = array_merge($this->_loginPage, $options['login']);
        }

        if (isset($options['role'])) {
            $this->_roleName = $options['role'];
        }

        if (isset($options['config'])) {
            $this->_config = $options['config'];
        }

        if (isset($options['cache'])) {
            $this->_cache = $options['cache'];
        }

        if (isset($options['unlogined'])) {
            $this->_unLogined = $options['unlogined'];
        }

        $this->_options = $options;

        $this->getAcl();
    }



    /**
     * Gets config array from file
     *
     * @return array
     */
    private function _getConfig()
    {
        if (!$this->_configArray) {
            $this->_configArray = Core_Module_Config::getConfig(
                $this->_config,
                null,
                Core_Module_Config::MAIN_ORDER_FIRST,
                $this->_cache
            );
        }
        return $this->_configArray;
    }

    /**
     * Sets the ACL object
     *
     * @param  Zend_Acl $acl
     * @return void
     **/
    public function setAcl(Zend_Acl $acl)
    {
        $this->_acl = $acl;
    }

    /**
     * Returns the ACL object
     *
     * @return Zend_Acl
     **/
    public function getAcl()
    {
        if (null == $this->_acl) {
            $config = $this->_getConfig();

            $this->setAcl(new Core_Acl($config));

            Zend_Registry::set('Acl', $this->_acl);
        }
        return $this->_acl;
    }

    /**
     * Sets the ACL role to use
     *
     * @param string $roleName
     * @return void
     */
    public function setRoleName($roleName)
    {
        $this->_roleName = $roleName;
    }

    /**
     * Returns the ACL role used
     *
     * @return string
     */
    public function getRoleName()
    {
        $identity = Zend_Auth::getInstance()->getIdentity();

        if ($identity) {
            $this->_roleName = $identity->role;
        }

        return $this->_roleName;
    }

    /**
     * Predispatch
     * Checks if the current user identified by roleName has rights to the requested url (module/controller/action)
     * If not, it will call denyAccess to be redirected to errorPage
     *
     * @return void
     */
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        $resourceName  = 'mvc:';
        $resourceName .= $request->getModuleName() . '/';
        $resourceName .= $request->getControllerName();

        /** Check resource */
        if (!$this->getAcl()->has($resourceName)) {
            if ($this->_allowAll) {
                $this->allowAccess();
                return;
            } elseif (Zend_Controller_Front::getInstance()->getParam('env') == 'development') {
                $this->getResponse()
                     ->appendBody("<h2>Resource \"$resourceName\" not found in ACL rules</h2>");
                $this->allowAccess();
                return;
            } else {
                $this->denyAccess();
                return;
            }
        }

        /** Check if the controller/action can be accessed by the current user */
        if ($this->getAcl()->isAllowed(
            $this->getRoleName(),
            $resourceName,
            $request->getActionName()
        )) {
            $this->allowAccess();
        } else {
            /** Redirect to access denied page */
            $this->denyAccess();
        }
    }

    /**
     * Allow Access Function
     *
     * @return void
     */
    public function allowAccess()
    {
        // TODO: ?
    }

    /**
     * Deny Access Function
     * Redirects to denied/error/login page,
     * this can be called from an action using the action helper
     *
     * @todo Create logic for separate error page (notfound or some else)
     * @return void
     */
    public function denyAccess()
    {
        $session = new Zend_Session_Namespace('Zend_Request');
        $session->params = $this->_request->getParams();


        if (Zend_Auth::getInstance()->hasIdentity()) {
            // user logined, but don't have access
            $this->_request->setModuleName($this->_deniedPage['module']);
            $this->_request->setControllerName($this->_deniedPage['controller']);
            $this->_request->setActionName($this->_deniedPage['action']);
            $this->_request->setDispatched(false);
        } else {
            // is guest - go to login page
            $this->_request->setModuleName($this->_loginPage['module']);
            $this->_request->setControllerName($this->_loginPage['controller']);
            $this->_request->setActionName($this->_loginPage['action']);
            $this->_request->setDispatched(false);
        }
    }

}