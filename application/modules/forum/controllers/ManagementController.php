<?php
/**
 * UsersController for admin module
 *
 * @category   Application
 * @package    Users
 * @subpackage Controller
 *
 * @version  $Id: ManagementController.php 48 2010-02-12 13:23:39Z AntonShevchuk $
 */
class Forum_ManagementController extends Core_Controller_Action_Crud
{
    /**
     * init invironment
     *
     * @return void
     */
    public function init()
    {
        /* Initialize */
        parent::init();

        $this->_beforeGridFilter(array(
             '_addCheckBoxColumn',
             '_addAllTableColumns',
             '_prepareGrid',
             '_addEditColumn',
             '_addDeleteColumn',
             '_addCreateButton',
             '_addDeleteButton',
             '_showFilter'
        ));
    }

    public function postDispatch()
    {
        parent::postDispatch();

        if ('create' == $this->_getParam('action') || 'edit' == $this->_getParam('action')) {
            $this->_setDefaultScriptPath();
        }
    }

    /**
     * _getCreateForm
     *
     * return create form for scaffolding
     *
     * @return  Zend_Dojo_Form
     */
    protected function _getCreateForm()
    {
        return new Forum_Model_Post_Form_Admin_Create();
    }

    /**
     * _getEditForm
     *
     * return edit form for scaffolding
     *
     * @return  Zend_Dojo_Form
     */
    protected function _getEditForm()
    {
        $form = new Forum_Model_Post_Form_Admin_Create();
        $form->addElement(new Zend_Form_Element_Hidden('id'));
        return $form;
    }

    /**
     * _getTable
     *
     * return manager for scaffolding
     *
     * @return  Core_Model_Abstract
     */
    protected function _getTable()
    {
        return new Forum_Model_Post_Table();
    }

    /**
     * change grid before render
     *
     * @return void
     */
    protected function _prepareGrid()
    {
        $this->grid
             ->removeColumn('categoryId')
             ->removeColumn('userId')
             ->removeColumn('views')
             ->removeColumn('comments')
             ->setColumn('body', array(
                'formatter' => array($this, array('trimFormatter'))
             ));
    }



}

