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
			$output = $this->doScan(
				Tools::getValue('set_price'),
				Tools::getValue('price_min'),
				Tools::getValue('price_max'),
				Tools::getValue('lorem_paragraphs')
			);
		}

		return '<output>'.$output.'</output>'.$this->displayForm();
	}

	private function doScan($set_price, $price_min, $price_max, $lorem_paragraphs)
	{
		$output = 'Starting scanning...<br/>';

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
				$price = 0;
				if (!$set_price)
					$price = 1; // don't search
				foreach ($product_multi as $id_lang => $product)
				{
					if (!$description && $product['description'])
						$description = $product['description'];
					if (!$description_short && $product['description_short'])
						$description_short = $product['description_short'];
					if ($price == 0 && $product['price'] != 0)
						$price = $product['price'];
					if ($description && $description_short && $price != 0)
						break;
				}
				// TODO: if description is one-line, optionally replace or move it to desc_short
				if (!$description)
					$description = $this->getLipsum($lorem_paragraphs); // todo: params
				if (!$description_short)
				{
					$description_short = explode("\n", $description)[0];
					if (strlen($description_short) > 780)
						$description_short = mb_substr($description_short, 0, 780).'</p>'; //FIXME: cut by dot
				}
				if ($price == 0)
					$price = $this->getPrice($price_min, $price_max); // todo: params
				// now set description
				foreach ($product_multi as $id_lang => $product)
					if (!$product['description'] || !$product['description_short'] || $product['price'] == 0)
					{
						$prd = new Product($product['id_product'], false, $id_lang);
						$upd = array();
						if (!$prd->description)
						{
							$prd->description = $description;
							$upd .= 'description';
						}
						if (!$prd->description_short)
						{
							$prd->description_short = $description_short;
							$upd .= 'description_short';
						}
						if ($set_price && $product->price == 0)
						{
							$prd->price = $price;
							$upd .= 'price';
						}
						$output .= 'Product «'.$prd->name.'», language '.$id_lang.': updating '.implode(',', $upd).'...<br/>';
						$prd->update();
					}
			}

			$start += $step;
		}
		$output .= 'Done.';
		return $output;
	}

	private function getLipsum($paragraphs)
	{
		// FIXME: delay?
		$json = file_get_contents("http://lipsum.com/feed/json?amount=$paragraphs&what=paras&start=no");
		$obj = json_decode($json);
		$lipsum = $obj->feed->lipsum;
		return '<p>'.implode("</p>\n<p>", explode("\n", $lipsum)).'</p>';
	}
	private function getPrice($min, $max)
	{
		return rand($min*100, $max*100) / 100;
	}

	private function displayForm()
	{
		$fields[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('Options'),
			),
			'input' => array(
				array(
					'type' => 'switch',
					'label' => $this->l('If price is 0, set to random'),
					'name' => 'set_price',
					'values' => array(
						array('id'=>'active_on', 'value'=>1, 'label'=>$this->l('Yes')),
						array('id'=>'active_off', 'value'=>0, 'label'=>$this->l('No')),
					),
				),
				array(
					'type' => 'text',
					'label' => $this->l('Minimum price'),
					'name' => 'price_min',
				),
				array(
					'type' => 'text',
					'label' => $this->l('Maximum price'),
					'name' => 'price_max',
				),

				array(
					'type' => 'text',
					'label' => $this->l('Number of paragraphs for item description'),
					'name' => 'lorem_paragraphs',
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
			'set_price' => true,
			'price_min' => 0.01,
			'price_max' => 99999,
			'lorem_paragraphs' => 5,
		);

		return $helper->generateForm($fields);
	}
}
