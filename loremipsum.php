<?php
/**
* This module fills empty goods descriptions with «Lorem Ipsum» text.
*
* @author  Semyon Maryasin <simeon@maryasin.name>
* @license GPL
*/

if (!defined('_PS_VERSION_'))
	exit;

class LoremIpsum extends Module
{
	public function __construct()
	{
		$this->name = 'loremipsum';
		$this->tab = 'content_management';
		$this->version = '1.0.0';
		$this->author = 'Semyon Maryasin';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('LoremIpsum');
		$this->description = $this->l('Fill empty product descriptions with generated «Lorem Ipsum» text');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
	}

	function install()
	{
		return parent::install() && $this->installTab();
	}
	function uninstall()
	{
		return $this->uninstallTab() && parent::uninstall();
	}

	function installTab()
	{
		$tab = new Tab();
		$tab->active = TRUE;
		$tab->class_name = 'AdminLoremIpsum';
		$tab->name = array();
		foreach (Language::getLanguages(true) as $lang)
			$tab->name[$lang['id_lang']] = 'LoremIpsum';
		$tab->id_parent = 99999;
		$tab->module = $this->name;
		return $tab->add();
	}
	function uninstallTab()
	{
		$id_tab = (int)Tab::getIdFromClassName('AdminLoremIpsum');
		if ($id_tab)
		{
			$tab = new Tab($id_tab);
			return $tab->delete();
		}
		return FALSE;
	}

	public function getContent()
	{
		$output = NULL;

		if (Tools::isSubmit('startScan'))
		{
			$output = $this->doScan();
		}

		return '<output>'.$output.'</output>'.$this->displayForm();
	}

	private function doScan()
	{
		$output = 'Starting scan...<br/>';

		$start = 0;
		$step = 100;
		while (TRUE)
		{
			// gather products for all languages
			$products = array();
			foreach (Language::getLanguages(true) as $lang)
			{
				$id_lang = $lang['id_lang'];
				$products_lang = Product::getProducts($id_lang, $start, $step, 'id_product', 'ASC');
				foreach ($products_lang as $product)
					$products[$product['id_product']][$id_lang] = $product;
			}
			if (count($products) == 0)
				break;
			// process each individual product
			foreach ($products as $product_multi)
			{
				// find description in any lang
				$description = NULL;
				$description_short = NULL;
				$price = NULL;
				foreach ($product_multi as $id_lang->$product)
				{
					if ($product['description'])
						$description = $product['description'];
					if ($product['description_short'])
						$description_short = $product['description_short'];
					if ($product['price'])
						$price = $product['price'];
					if ($description && $description_short && $price)
						break;
				}
				// TODO: if description is one-line, optionally replace or move it to desc_short
				if (!$description)
					$description = $this->getLipsum(); // todo: params
				if (!$description_short)
					$description_short = explode("\n", $description)[0];
				if (!$price)
					$price = $this->getPrice(); // todo: params
				// now set description
				foreach ($product_multi as $id_lang->$product)
					if (!$product['description'] || !$product['description_short'] || !$product['price'])
					{
						$prd = new Product($product['id_product'], false, $id_lang);
						$prd->description = $description;
						$prd->description_short = $description_short;
						$prd->price = $price;
						$prd->update();
						$output .= 'Updated product '.$prd->name.' in language '.$id_lang.'<br/>';
					}
			}
			d($products);

			$start += $step;
		}
	}

	private function getLipsum()
	{
		// TODO
		return 'Lorem ipsum dolor sit amet';
	}
	private function getPrice()
	{
		// TODO
		d('price gen');
	}

	private function displayForm()
	{
		$fields[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('Options'),
			),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('Xyz'),
					'name' => 'xyz',
				),
			),
			'submit' => array(
				'title' => $this->l('Start scanning'),
				'class' => 'button',
			),
		);
		$helper = new HelperForm();
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		$helper->title = $this->displayName;
		$helper->show_toolbar = FALSE;
		$helper->submit_action = 'startScan';
		$helper->fields_value = array(
			'xyz' => '',
		);

		return $helper->generateForm($fields);
	}
}
