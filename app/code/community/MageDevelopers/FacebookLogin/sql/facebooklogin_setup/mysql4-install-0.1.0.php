<?php
$installer = $this;

$installer->startSetup();

$installer->addAttribute('customer', 'facebook_uid', array(
			'type'			=> 'varchar',
			'label'			=> 'Facebook Uid',
			'visible'		=> false,
			'required'	=> false
));

$installer->endSetup();