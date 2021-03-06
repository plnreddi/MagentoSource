<?php
/**
* @author Amasty Team
* @copyright Copyright (c) 2008-2012 Amasty (http://www.amasty.com)
* @package Amasty_Feed
*/
class Amasty_Feed_Block_Adminhtml_Profile_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();
                 
        $this->_objectId = 'id'; 
        $this->_blockGroup = 'amfeed';
        $this->_controller = 'adminhtml_profile';
        
        $this->_removeButton('reset'); 
        
        if (Mage::registry('amfeed_profile')) {
            $feed = Mage::registry('amfeed_profile');
            $id = $feed->getId();
            if ($id) {
                
                $this->_addButton('generate', array(
                    'label'     => Mage::helper('amfeed')->__('Generate'),
                    'onclick'   => 'am_feed_object.request('. $id .')',
                    'class'     => 'save',
                ), -100);
        
                if (file_exists($feed->getMainPath())) {
                    $downloadUrl = Mage::helper('adminhtml')->getUrl('amfeed/adminhtml_profile/download', array('file' => $feed->getFilename() . $feed->getFileExt()));
                    $this->_addButton('download', array(
                        'label'     => Mage::helper('amfeed')->__('Download'),
                        'onclick'   => 'setLocation(\'' . $downloadUrl . '\')',
                        'class'     => 'save',
                    ), -200);
                }
            }
        } else {
            $model = Mage::getModel('amfeed/profile');
            Mage::register('amfeed_profile', $model);
        }
    }

    public function getHeaderText()
    {
        $model = Mage::registry('amfeed_profile');
        if ($model->getId()) {
            return Mage::helper('amfeed')->__("Edit Feed `%s`", $this->htmlEscape($model->getTitle()));
        }
        else {
            return Mage::helper('amfeed')->__('New Feed');
        }
    }     
    
}