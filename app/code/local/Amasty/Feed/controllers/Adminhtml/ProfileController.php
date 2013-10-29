<?php
/**
* @author Amasty Team
* @copyright Copyright (c) 2008-2012 Amasty (http://www.amasty.com)
* @package Amasty_Feed
*/     
class Amasty_Feed_Adminhtml_ProfileController extends Amasty_Feed_Controller_Abstract
{
    public function preDispatch()
    {
        parent::preDispatch();
        $this->_tabs      =  true;
        $this->_modelName = 'profile';
        $this->_title     = 'Feed';
        $this->_dynamic   = array('cond_advanced', 'csv');
    } 
    
    public function generateAction()
    {
        // set memory limit (Mb)
        //ini_set('memory_limit', Mage::getStoreConfig('amfeed/system/max_memory') . 'M');
        
        $total = 0;
        $cnt = 0;
        $message     = '';
        $isCompleted = false;
        $file        = '';
        $fileName    = '';
        
        $id = $this->getRequest()->getParam('profileId'); // feed_id
        
        $feed = Mage::getModel('amfeed/profile')->load($id);
        if ($feed->getId()) {
            try {
                $hasGenerated = $feed->generate();
                $cnt  = $feed->getInfoCnt();
                $total = $feed->getInfoTotal();
                
                if (!$total) {
                    $message = $this->__('There are no products to export. Pleas check the filters and try again.');
                    $isCompleted = true;   
                } elseif (!$cnt){
                    $message = $this->__('The feed generating has been started. %d products will be exported.', $total);
                } elseif ($hasGenerated) {
                    $message = $this->__('The feed has been generated');
                    if (($feed->getDeliveryType() == Amasty_Feed_Model_Profile::DELIVERY_TYPE_FTP) && $feed->getDelivered()) {
                        $message .= $this->__(' and uploaded to FTP server');
                    }
                    $message .= '.';
                    $isCompleted = true;
                    $file = Mage::helper('adminhtml')->getUrl('amfeed/adminhtml_profile/download', array('file' => $feed->getFilename() . $feed->getFileExt()));
                    $fileName = $feed->getFilename();
                } else {
                    //@todo
                    // 1) get max exec time in seconds
                    // 2) check how many seconds it takes to generate N products
                    // 3) offer to increase the batch size
                    $message = $this->__('Feed generating is in progress. %d of %d products have been exported.', $cnt, $total);    
                }
            } catch (Exception $e) {
                $message = $this->__('Please check the following error and try again:<br>%s', $e->getMessage());
                $isCompleted = true;   
                $feed->setStatus(Amasty_Feed_Model_Profile::STATE_ERROR);
                $feed->save();
            }
        } else {
            $total = 0;
            $cnt = 0;
            $file = '';
            $fileName = '';
            $isCompleted = true;
            $message = $this->__('Please provide a valid feed ID.');                
        }
        
        $progress = 0;
        if ($total) {
            $progress = 100 * $cnt / $total;
        }
        
        $result = array(
            'total'       => $total,
            'progress'    => $progress,
            'log'         => $message,
            'isCompleted' => $isCompleted,
            'filepath'    => $file,
            'filename'    => $fileName,
        );
        
        $json = Zend_Json::encode($result);
        $this->getResponse()->setBody($json);
    }
    
    public function stopAction()
    {
        $id = $this->getRequest()->getParam('profileId');
        $error = $this->getRequest()->getParam('error');
        $feed = Mage::getModel('amfeed/profile')->load($id);
        if ('true' === $error) {
            $feed->setStatus(Amasty_Feed_Model_Profile::STATE_ERROR);
        } else {
            $feed->setStatus(Amasty_Feed_Model_Profile::STATE_READY);
        }
        $feed->save();
    }
    
    protected function prepareForSave($model)
    {
        if (($model->getType() == Amasty_Feed_Model_Profile::TYPE_CSV) || $model->getType() == Amasty_Feed_Model_Profile::TYPE_TXT) {
            $csv = $model->getCsv();
            if (!$csv || !is_array($csv) || count($csv['name']) < 2){
                throw new Exception($this->__('Please specify fields'));
            }
            
            // the last is alwaus empty
            unset($csv['name'][count($csv['name'])-1]);
            unset($csv['attr'][count($csv['attr'])-1]);
            unset($csv['type'][count($csv['type'])-1]);
            
            // name is required
            foreach($csv['name'] as $i => $name){
                if (!$name){
                    throw new Exception($this->__('Please provide name for the field #%d', $i+1));
                }
            }
            
            $model->setCsv($csv);
        }
        else {
            $model->setCsv(array()); 
        }
        
        $cond = $model->getCondAdvanced();
        if ($cond) {
            foreach ($cond['attr'] as $i => $value){
                if (!$value){
                    unset($cond['attr'][$i]);
                }
            }
            $model->setCondAdvanced($cond);             
        }
        
        if ($model->getOnDays()) {
            $data = implode(',', $model->getOnDays());
            $model->setOnDays($data);
        }
        
        if ($model->getCondType()) {
            $data = implode(',', $model->getCondType());
            $model->setCondType($data);
        }
        
        if ($model->getDeleteImage()) {
            $path = Mage::helper('amfeed')->getDownloadPath('images', $this->getId() . '.jpg');
            Mage::helper('amfeed')->deleteFile($path);
            $model->setDefaultImage(0);
        }
        
        return parent::prepareForSave($model);
    }
    
    public function downloadAction()
    {
        $fileName = $this->getRequest()->getParam('file');
        $download = Mage::helper('amfeed')->getDownloadPath('feeds', $fileName);
        if (file_exists($download)) {
            header('Content-Disposition: attachment; filename="' . $fileName . '"');               
            if(function_exists('mime_content_type')) {
                header('Content-Type: ' . mime_content_type($download));                    
            }
            else if(class_exists('finfo')) {
                 $finfo = new finfo(FILEINFO_MIME);
                 $mimetype = $finfo->file($download);
                 header('Content-Type: ' . $mimetype);
            }                
            readfile($download); 
        }
        exit;
    }
}
