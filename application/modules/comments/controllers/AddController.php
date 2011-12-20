<?php
/**
 * Comments_AddController for Comments module
 *
 * @category   Application
 * @package    Blog
 * @subpackage Controller
 *
 * @version  $Id: AddController.php 2011-11-21 11:59:34Z pavel.machekhin $
 */
class Comments_AddController extends Core_Controller_Action
{
    public function indexAction()
    {
        $aliasManager = new Comments_Model_CommentAlias_Manager();
        $commentsTable = new Comments_Model_Comment_Table();
        
        $alias = $aliasManager->getByAlias($this->getRequest()->getParam('alias'));
        $key = $this->getRequest()->getParam('key');
        
        if (!$alias) {
            throw new Zend_Controller_Action_Exception('Page not found');
        }
        
        if (Zend_Auth::getInstance()->hasIdentity()) {
            $form = new Comments_Model_Comment_Form_Create();
            
            if ($alias->isKeyRequired()) {
                $form->setKey($key);
            }
            
            if (!$alias->isTitleDisplayed()) {
                $form->removeTitleElement();
            }
            
            if ($this->getRequest()->isPost()
                && $form->isValid($this->getRequest()->getParams())
            ) {
                $row = $commentsTable->createRow($form->getValues());
                $row->aliasId = $alias->id;
                $row->status = Comments_Model_Comment::STATUS_ACTIVE;
                
                if ($alias->isPreModerationRequired()) {
                    $row->status = Comments_Model_Comment::STATUS_REVIEW;
                }
                
                $row->save();
                
                switch ($row->status) {
                    case Comments_Model_Comment::STATUS_REVIEW:
                        $this->_helper->flashMessenger->addMessage(
                            'Comment added successfully and awaiting pre-moderation.'
                        );
                        break;
                    default:
                        $this->_helper->flashMessenger->addMessage(
                            'Comment added successfully'
                        );
                        break;
                }
                
                $this->_redirect($form->getValue('returnUrl'));
            }
            $this->view->user = $this->view->user();
            $this->view->form = $form;
        }
    }
}