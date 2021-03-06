<?php
/**
* This module fills empty goods descriptions with «Lorem Ipsum» text.
*
* @author    Semyon Maryasin <simeon@maryasin.name>
* @copyright 2015 Semyon Maryasin <simeon@maryasin.name>
* @license   GPL
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

	public function install()
	{
		return parent::install() && $this->installTab() && $this->initDefaults();
	}
	private function initDefaults()
	{
		Configuration::updateValue('LOREM_IPSUM_set_price', true);
		Configuration::updateValue('LOREM_IPSUM_price_min', 0.01);
		Configuration::updateValue('LOREM_IPSUM_price_max', 99999);
		Configuration::updateValue('LOREM_IPSUM_lorem_paragraphs', 5);
		Configuration::updateValue('LOREM_IPSUM_lorem_short_sentences', '1-3');
		return true;
	}
	public function uninstall()
	{
		return $this->uninstallTab() && parent::uninstall();
	}

	private function installTab()
	{
		$tab = new Tab();
		$tab->active = true;
		$tab->class_name = 'AdminLoremIpsum';
		$tab->name = array();
		foreach (Language::getLanguages(true) as $lang)
			$tab->name[$lang['id_lang']] = 'LoremIpsum';
		$tab->id_parent = 99999;
		$tab->module = $this->name;
		return $tab->add();
	}
	private function uninstallTab()
	{
		$id_tab = (int)Tab::getIdFromClassName('AdminLoremIpsum');
		if ($id_tab)
		{
			$tab = new Tab($id_tab);
			return $tab->delete();
		}
		return false;
	}

	public function getContent()
	{
		$output = null;

		if (Tools::isSubmit('startScan'))
		{
			if (Tools::getValue('set_price') && (
					!Tools::getValue('price_min') ||
					!Tools::getValue('price_max')) ||
				!Tools::getValue('lorem_paragraphs') ||
				!Tools::getValue('lorem_short_sentences'))
				$output = 'Invalid values passed!';
			else
				$output = $this->doScan(
					Tools::getValue('set_price'),
					Tools::getValue('price_min'),
					Tools::getValue('price_max'),
					Tools::getValue('lorem_paragraphs'),
					Tools::getValue('lorem_short_sentences')
				);
		}

		return '<output>'.$output.'</output>'.$this->displayForm();
	}

	private function doScan($set_price, $price_min, $price_max, $lorem_paragraphs, $lorem_short_sentences)
	{
		$output = 'Starting scanning...<br/>';

		$start = 0;
		$step = 100;
		while (true)
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
				$description = null;
				$description_short = null;
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
				{
					$paras = $lorem_paragraphs;
					if (strpos($paras, '-') !== false) // range
					{
						$range = explode('-', $paras);
						$paras = rand($range[0], $range[1]);
					}
					$description = $this->getLipsum($paras); // todo: params
				}
				if (!$description_short)
				{
					$description_short = explode("\n", strip_tags($description))[0];
					$shorts = $lorem_short_sentences;
					if (strpos($shorts, '-') !== false) // range
					{
						$range = explode('-', $shorts);
						$shorts = rand($range[0], $range[1]);
					}
					$sentences = explode('.', $description_short);
					$sentences = array_slice($sentences, 0, $shorts);
					$description_short = implode('.', $sentences);
				}
				if ($price == 0)
					$price = $this->getPrice($price_min, $price_max); // todo: params
				// now set description
				$product = $product_multi[Configuration::get('PS_LANG_DEFAULT')];
				$prd = new Product($product['id_product']);
				$changed = false;
				foreach ($product_multi as $id_lang => $product)
				{
					if (!$product['description'] || !$product['description_short'] || $product['price'] == 0)
					{
						$upd = array();
						if (!$prd->description || !$prd->description[$id_lang])
						{
							$prd->description[$id_lang] = $description;
							$upd[] = 'description';
							$changed = true;
						}
						if (!$prd->description_short || !$prd->description_short[$id_lang])
						{
							$prd->description_short[$id_lang] = $description_short;
							$upd[] = 'description_short';
							$changed = true;
						}
						if ($set_price && $prd->price == 0)
						{
							$prd->price = $price;
							$upd[] = 'price';
							$changed = true;
						}
						$output .= 'Product «'.$product['name'].'», language '.$id_lang.': updating '.implode(',', $upd).'...<br/>';
					}
				}
				if ($changed)
					$prd->update();
			}

			$start += $step;
		}
		$output .= 'Done.';
		return $output;
	}

	private function getLipsum($paragraphs)
	{
		// FIXME: delay?
		$json = Tools::file_get_contents("http://lipsum.com/feed/json?amount=$paragraphs&what=paras&start=no");
		if (!$json)
			throw new Exception('Connection problem, cannot fetch Lorem Ipsum');
		$obj = Tools::jsonDecode($json);
		$lipsum = $obj->feed->lipsum;
		return '<p>'.implode("</p>\n<p>", explode("\n", $lipsum)).'</p>';
	}
	private function getPrice($min, $max)
	{
		return rand($min * 100, $max * 100) / 100;
	}

	private function displayForm()
	{
		$fields = array(); // for validation
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
					'label' => $this->l('Number of paragraphs for full item description'),
					'name' => 'lorem_paragraphs',
				),
				array(
					'type' => 'text',
					'label' => $this->l('Number of sentences for short item description'),
					'name' => 'lorem_short_sentences',
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
		$helper->show_toolbar = false;
		$helper->submit_action = 'startScan';
		$fieldnames = array('set_price', 'price_min', 'price_max', 'lorem_paragraphs', 'lorem_short_sentences');
		$helper->fields_value = array();
		foreach ($fieldnames as $fname)
		{
			$val = Tools::getValue($fname, Configuration::get('LOREM_IPSUM_'.$fname));
			$helper->fields_value[$fname] = $val;
		}

		return $helper->generateForm($fields);
	}
}
