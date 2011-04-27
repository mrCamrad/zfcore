<?php
/**
 * Register session form
 * 
 * @category Application
 * @package Crontab
 * @subpackage Form
 *
 * @author Anna Pavlova <pavlova.anna@nixsolutions.com>
 *
 * @version  $Id$
 */
class Debug_Model_Crontab_Form_Edit extends Debug_Model_Crontab_Form_Create
{

    private $_selectOptionsMonth = array(
                                      '1',
                                      '2',
                                      '3',
                                      '4',
                                      '5',
                                      '6',
                                      '7',
                                      '8',
                                      '9',
                                      '10',
                                      '11',
                                      '12',
                                 );

    private $_selectOptionsDayOfWeek = array(
                                          '7',
                                          '1',
                                          '2',
                                          '3',
                                          '4',
                                          '5',
                                          '6',
                                       );

    /**
     * Form initialization
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        
        $this->setName('sessionEditForm')
             ->addElement(new Zend_Form_Element_Hidden('id'));

        return $this;
    }

    /**
     * Set values for EditForm
     *
     *
     * @return void
     */
    public function setValues($values)
    {
        $this->getElement('minute')
             ->setValue($values['minute']);

        $this->getElement('hour')
             ->setValue($values['hour']);

        $this->getElement('dayOfMonth')
             ->setValue($values['dayOfMonth']);

        $this->getElement('command')
             ->setValue($values['command']);

        if (in_array($values['month'], $this->_selectOptionsMonth)) {
            $this->getElement('month')
                 ->setValue($values['month']);
        } else {
            $this->getElement('month')
                 ->addMultiOption($values['month'], $values['month'])
                 ->setValue($values['month']);
        }

        if (in_array($values['dayOfWeek'], $this->_selectOptionsDayOfWeek)) {
            $this->getElement('dayOfWeek')
                 ->setValue($values['dayOfWeek']);
        } else {
            $this->getElement('dayOfWeek')
                 ->addMultiOption($values['dayOfWeek'], $values['dayOfWeek'])
                 ->setValue($values['dayOfWeek']);
        }

    }
    
    /**
     * Modify parent element
     *
     * 
     * @return object Zend_Dojo_Form_Element_ValidationTextBox
     */
    protected function _submit()
    {
        return parent::_submit()->setLabel('Save');
    }
}