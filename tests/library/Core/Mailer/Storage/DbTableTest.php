<?php

/**
 * ACL Resource
 * 
 * @category Tests
 * @package  Core
 */
class Core_Mailer_Storage_DbTableTest extends ControllerTestCase
{
    /**
     * @access public
     */
    public function setUp()
    {
        parent::setUp();
        
        $this->_adapter = array(
            'storage' => array('type'    => 'DbTable',
                               'options' => array(
                                    'table'  => 'Model_Mail_Table'
                               )),
            'transport' => array('type'    => 'ZendMail',
                                 'options' => array(
                                    'transport' => array(
                                            'class'=>'Zend_Mail_Transport_Sendmail'
                                        )
                                 )),
           );

        $this->_fixture = array( 'fromEmail' => 'test'.time().'@nixsolutions.com',
                                 'fromName'  => 'Test '.time(),
                                 'subject'   => 'Test '.date('Y-m-d H:i:s'),
                                 'body'      => 'Test Mailer<br/><br/>With subject: %subject%',
                                 'alias'     => 'test',
                                 'id'        => 23,
                                 'altBody'   => "Test Mailer\n\nWith subject: %subject%"
                                );  

                                $mail = new Model_Mail_Table();
        $this->_mail = $mail->create($this->_fixture);
        $this->_mail->save();

        Core_Mailer::init($this->_adapter);
    }
    
    /**
     * Test getTemplate method
     *
     */
    public function testGetTemplate() 
    {
        $template = Core_Mailer::getTemplate($this->_fixture['alias']);
        $this->assertEquals($this->_fixture['toEmail'], $template->toEmail);
        $this->assertEquals($this->_fixture['toName'], $template->toName);
        $this->assertEquals($this->_fixture['subject'], $template->subject);
        $this->assertEquals($this->_fixture['body'], $template->body);
    }
    
    /**
     * @access public
     */
    public function tearDown()
    {
        if ($this->_mail) {
            $this->_mail->delete();
        }
        parent::tearDown();
    }
}