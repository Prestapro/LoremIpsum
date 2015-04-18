<?php
/**
* This module fills empty goods descriptions with «Lorem Ipsum» text.
*
* @author  Semyon Maryasin <simeon@maryasin.name>
* @license GPL
*/

if (!defined('_PS_VERSION_'))
	exit;

class LoremIpsum extends Models
{
	public function __construct()
	{
		$this->name = 'loremipsum';
		$this->tab = 'content_management';
		$this->version = '1.0.0';
		$this->author = 'Semyon Maryasin';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
		$this->bootstram = true;

		parent::__construct();

		$this->displayName = $this->l('LoremIpsum');
		$this->description = $this->l('Fill empty product descriptions with generated «Lorem Ipsum» text');

		//
	}
}
