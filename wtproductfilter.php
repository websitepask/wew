<?php
/**
* 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
exit;
include_once(dirname(__FILE__).'/wtproductfilterclass.php');

class WTProductFilter extends Module
{
	private $html;
	private $product_types = array('featured_products' => 'Featured Products','special_products' => 'Special Products','topseller_products' => 'Top Seller Products','new_products' => 'New Products','choose_the_category' => 'Choose the Category...');
	
public function __construct()
{
	$this->name = 'wtproductfilter';
	$this->tab = 'others';
	$this->version = '1.0.0';
	$this->author = 'WaterThemes';
	$this->module_key = '';
	$this->secure_key = Tools::encrypt($this->name);
	$this->bootstrap = true;
	parent::__construct();
	$this->displayName = $this->l('WT Products Filter');
	$this->description = $this->l('Add Filter Products Tab on the homepage');
	$this->confirmUninstall = $this->l('Are you sure that you want to delete your WT Products Tab Filter?');
}

public function add_sample_data()
{
		$languages = Language::getLanguages(false);
		for ($i = 1; $i <= 3; ++$i)
		{
			$tab = new WTProductFilterClass();
			$tab->active = 1;
			$tab->position = $i;
			if ($i == 1)
			$tab->product_type = 'choose_the_category_3';
			if ($i == 2)
			$tab->product_type = 'choose_the_category_8';
			if ($i == 3)
			$tab->product_type = 'choose_the_category_4';
			foreach ($languages as $language)
			{
				if ($i == 1)
				$tab->title[$language['id_lang']] = 'Women';
				if ($i == 2)
				$tab->title[$language['id_lang']] = 'Dress';
				if ($i == 3)
				$tab->title[$language['id_lang']] = 'Summary';
			}
			$tab->add();
		}
}
protected function createTables()
{
		/* Menus */
		$res = (bool)Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'wtproductfilter` (
				`id_tab` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`id_shop` int(10) unsigned NOT NULL,
				PRIMARY KEY (`id_tab`, `id_shop`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=UTF8;
		');

		/* Menus configuration */
		$res &= Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'wtproductfilter_tabs` (
			`id_tab` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`product_type` varchar(255),
			`position` int(10) unsigned NOT NULL DEFAULT \'0\',
			`active` tinyint(1) unsigned NOT NULL DEFAULT \'0\',			 
			PRIMARY KEY (`id_tab`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=UTF8;
		');

		/* Menus lang configuration */
		$res &= Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'wtproductfilter_tabs_lang` (
			  `id_tab` int(10) unsigned NOT NULL,
			  `id_lang` int(10) unsigned NOT NULL,
			  `title` varchar(255) NOT NULL,
			  PRIMARY KEY (`id_tab`,`id_lang`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=UTF8;
		');

		return $res;
}	
public function install()
{
		Configuration::updateValue('NUM_PRO_DISPLAY', 10);
	/* Adds Module */
		if (parent::install() && $this->registerHook('displayHeader') && $this->registerHook('displayHome') && $this->registerHook('displayProductListThumbnails') && $this->registerHook('actionShopDataDuplication'))
		{
			/* Creates tables */
			$res = $this->createTables();

			/* Adds samples */
			if ($res)
				$this->add_sample_data();

			// Disable on mobiles and tablets
			//$this->disableDevice(Context::DEVICE_MOBILE);

			return (bool)$res;
		}
		return false;
}
protected function deleteTables()
{
		$tabs = $this->getTabs();
		foreach ($tabs as $tab)
		{
			$to_del = new WTProductFilterClass($tab['id_tab']);
			$to_del->delete();
		}

		return Db::getInstance()->execute('
			DROP TABLE `'._DB_PREFIX_.'wtproductfilter`, `'._DB_PREFIX_.'wtproductfilter_tabs`, `'._DB_PREFIX_.'wtproductfilter_tabs_lang`;
		');
}
public function uninstall()
{
		Configuration::deleteByName('NUM_PRO_DISPLAY');
		/* Deletes Module */
		if (parent::uninstall())
		{
			/* Deletes tables */
			$res = $this->deleteTables();
			/* Unsets configuration */
			return (bool)$res;
		}

		return false;
}

public function getContent()
{
		$this->html = '<h2><img src="'.$this->_path.'logo.png" alt="" /> '.$this->displayName.'</h2>';
		
		$this->html .= $this->headerHTML();
		/* Validate & process */
		if (Tools::isSubmit('submitTab') || Tools::isSubmit('delete_id_tab') || Tools::isSubmit('submitOption') || Tools::isSubmit('changeStatus'))
		{
			if ($this->_postValidation())
			{
				$this->postProcess();
				$this->html .= $this->renderList();
				$this->html .= $this->displayFormOption().$this->_displayHelp().$this->_displayAdvertising();			
			}
			else
				$this->html .= $this->renderAddForm();
		}
		elseif (Tools::isSubmit('addTab') || (Tools::isSubmit('id_tab') && $this->slideExists((int)Tools::getValue('id_tab'))))
			$this->html .= $this->renderAddForm();
		else
		{
			$this->html .= $this->renderList();
			$this->html .= $this->displayFormOption().$this->_displayHelp().$this->_displayAdvertising();
		}

		return $this->html;
		
		
}

	
private function _displayHelp()
{
		$html .= '
		<br/>
	 	<fieldset>
			<legend><img src="'.$this->_path.'views/img/help.png" alt="" title="" /> '.$this->l('Help').'</legend>		
			For customizations or assistance, please contact: <strong><a   target="_blank" href="http://waterthemes.com/contact-us">http://waterthemes.com/contact-us</a></strong>
			<br>
			<a href="http://waterthemes.com/" alt="waterthemes" title="waterthemes" target="_blank">http://waterthemes.com/</a>
		</fieldset>';
		return $html;
}
public function _displayAdvertising()
{
		$html .= '
		<br/>
		<fieldset>
			<legend><img src="'.$this->_path.'views/img/more.png" alt="" title="" /> '.$this->l('More Themes & Modules').'</legend>	
			<iframe src="http://waterthemes.com/advertising/prestashop_advertising.html" width="100%" height="420px;" border="0" style="border:none;"></iframe>
			</fieldset>';
		return $html;
}
public function slideExists($id_tab)
{
		$req = 'SELECT t.`id_tab` as id_tab
				FROM `'._DB_PREFIX_.'wtproductfilter` t
				WHERE t.`id_tab` = '.(int)$id_tab;
		$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($req);

		return ($row);
}

	private function _postValidation()
	{
		$errors = array();

		/* Validation for Menus configuration */
		if (Tools::isSubmit('submitOption'))
		{


		} /* Validation for status */
		elseif (Tools::isSubmit('changeStatus'))
		{
			if (!Validate::isInt(Tools::getValue('id_tab')))
				$errors[] = $this->l('Invalid tab');
		}
		/* Validation for Menu */
		elseif (Tools::isSubmit('submitTab'))
		{
			/* Checks state (active) */
			if (!Validate::isInt(Tools::getValue('active_tab')) || (Tools::getValue('active_tab') != 0 && Tools::getValue('active_tab') != 1))
				$errors[] = $this->l('Invalid tab state.');
			/* Checks position */
			if (!Validate::isInt(Tools::getValue('position')) || (Tools::getValue('position') < 0))
				$errors[] = $this->l('Invalid tab position.');
			
			/* If edit : checks id_menu */
			if (Tools::isSubmit('id_tab'))
			{

				//d(var_dump(Tools::getValue('id_tab')));
				if (!Validate::isInt(Tools::getValue('id_tab')) && !$this->slideExists(Tools::getValue('id_tab')))
					$errors[] = $this->l('Invalid id_tab');
			}
			/* Checks title/url/legend/description/image */
			$languages = Language::getLanguages(false);
			foreach ($languages as $language)
			{
				if (Tools::strlen(Tools::getValue('title_'.$language['id_lang'])) > 255)
					$errors[] = $this->l('The title is too long.');
			}

			/* Checks title/url/legend/description for default lang */
			$id_lang_default = (int)Configuration::get('PS_LANG_DEFAULT');
			if (Tools::strlen(Tools::getValue('title_'.$id_lang_default)) == 0)
				$errors[] = $this->l('The title is not set.');
				
		} /* Validation for deletion */
		elseif (Tools::isSubmit('delete_id_tab') && (!Validate::isInt(Tools::getValue('delete_id_tab')) || !$this->slideExists((int)Tools::getValue('delete_id_tab'))))
			$errors[] = $this->l('Invalid id_tab');

		/* Display errors if needed */
		if (count($errors))
		{
			$this->html .= $this->displayError(implode('<br />', $errors));

			return false;
		}

		/* Returns if validation is ok */

		return true;
	}

private function postProcess()
{
		$errors = array();

		/* Processes Menus */
		if (Tools::isSubmit('changeStatus') && Tools::isSubmit('id_tab'))
		{
			$tab = new WTProductFilterClass((int)Tools::getValue('id_tab'));
			if ($tab->active == 0)
				$tab->active = 1;
			else
				$tab->active = 0;
			$res = $tab->update();
			$this->html .= ($res ? $this->displayConfirmation($this->l('Configuration updated')) : $this->displayError($this->l('The configuration could not be updated.')));
		}
		/* Processes Menu */
		elseif (Tools::isSubmit('submitTab'))
		{		
			/* Sets ID if needed */
			if (Tools::getValue('id_tab'))
			{
				$tab = new WTProductFilterClass((int)Tools::getValue('id_tab'));
				if (!Validate::isLoadedObject($tab))
				{
					$this->html .= $this->displayError($this->l('Invalid id_tab'));
					return false;
				}
			}
			else
				$tab = new WTProductFilterClass();
				
				$tab->product_type_menu = Tools::getValue('categories-tree');
				$tab->product_type = Tools::getValue('product_type');
				if ($tab->product_type == 'choose_the_category')
				$tab->product_type .= '_'.$tab->product_type_menu;
			/* Sets position */
			$tab->position = (int)Tools::getValue('position');
			/* Sets active */
			$tab->active = (int)Tools::getValue('active_tab');
			
			/* Sets each langue fields */
			$languages = Language::getLanguages(false);
			foreach ($languages as $language)
				$tab->title[$language['id_lang']] = Tools::getValue('title_'.$language['id_lang']);
			

			/* Processes if no errors  */
			if (!$errors)
			{
				/* Adds */
				if (!Tools::getValue('id_tab'))
				{
					if (!$tab->add())
						$errors[] = $this->displayError($this->l('The tab could not be added.'));
				}
				/* Update */
				elseif (!$tab->update())
					$errors[] = $this->displayError($this->l('The tab could not be updated.'));
			}
		} /* Deletes */
		elseif (Tools::isSubmit('delete_id_tab'))
		{
			$tab = new WTProductFilterClass((int)Tools::getValue('delete_id_tab'));
			$res = $tab->delete();
			if (!$res)
				$this->html .= $this->displayError('Could not delete.');
			else
				Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true).'&conf=1&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name);
		}
		elseif (Tools::isSubmit('submitOption'))
		{
		
			$num_pro_display = Tools::getValue('num_pro_display');
			Configuration::updateValue('NUM_PRO_DISPLAY', $num_pro_display);
			
			$this->html .= $this->displayConfirmation($this->l('Configuration updated'));		
		}
		/* Display errors if needed */
		if (count($errors))
			$this->html .= $this->displayError(implode('<br />', $errors));
		elseif (Tools::isSubmit('submitTab') && Tools::getValue('id_tab'))
			Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true).'&conf=4&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name);
		elseif (Tools::isSubmit('submitTab'))
			Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true).'&conf=3&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name);	
	
}

public function getTabs($active = null)
{
		$this->context = Context::getContext();
		$id_shop = $this->context->shop->id;
		$id_lang = $this->context->language->id;

		return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT t.`id_tab` as id_tab, tts.`position`, tts.`active`, tts.`product_type`, ttl.`title`
			FROM '._DB_PREFIX_.'wtproductfilter t
			LEFT JOIN '._DB_PREFIX_.'wtproductfilter_tabs tts ON (t.id_tab = tts.id_tab)
			LEFT JOIN '._DB_PREFIX_.'wtproductfilter_tabs_lang ttl ON (tts.id_tab = ttl.id_tab)
			WHERE id_shop = '.(int)$id_shop.'
			AND ttl.id_lang = '.(int)$id_lang.
			($active ? ' AND tts.`active` = 1' : ' ').'
			ORDER BY tts.position'
		);
}

public function renderList()
{
		$tabs = $this->getTabs();
		foreach ($tabs as $key => $tab)
			$tabs[$key]['status'] = $this->displayStatus($tab['id_tab'], $tab['active']);

		$this->context->smarty->assign(
			array(
				'link' => $this->context->link,
				'tabs' => $tabs,
				'path'	=> $this->_path
			)
		);
		return $this->display(__FILE__, 'list.tpl');
}

public function displayStatus($id_tab, $active)
{
		$title = ((int)$active == 0 ? $this->l('Disabled') : $this->l('Enabled'));
		$icon = ((int)$active == 0 ? 'icon-remove' : 'icon-check');
		$class = ((int)$active == 0 ? 'btn-danger' : 'btn-success');
		$html = '<a class="btn '.$class.'" href="'.AdminController::$currentIndex.
			'&configure='.$this->name.'
				&token='.Tools::getAdminTokenLite('AdminModules').'
				&changeStatus&id_tab='.(int)$id_tab.'" title="'.$title.'"><i class="'.$icon.'"></i> '.$title.'</a>';

		return $html;
}


public function headerHTML()
{
		if (Tools::getValue('controller') != 'AdminModules' && Tools::getValue('configure') != $this->name)
			return;

		$this->context->controller->addJqueryUI('ui.sortable');
		/* Style & js for fieldset 'slides configuration' */
		$html = '<script type="text/javascript">
			$(function() {
				var $mySlides = $("#tabs");
				$mySlides.sortable({
					opacity: 0.6,
					cursor: "move",
					update: function() {
						var order = $(this).sortable("serialize") + "&action=updateTabsPosition";
						$.post("'.$this->context->shop->physical_uri.$this->context->shop->virtual_uri.'modules/'.$this->name.'/ajax_'.$this->name.'.php?secure_key='.$this->secure_key.'", order);
						}
					});
				$mySlides.hover(function() {
					$(this).css("cursor","move");
					},
					function() {
					$(this).css("cursor","auto");
				});
			});
		</script>';

		return $html;
}


public function displayFormOption()
{
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Option'),
					'icon' => 'icon-cogs'
				),
				'input' => array
				(
					array(
						'type' => 'text',
						'label' => $this->l('Products Displayed:'),
						'desc' => $this->l('Number of products to be displayed.'),
						'lang' => false,
						'name' => 'num_pro_display',
						'cols' => 10,
						'rows' => 10,
						'class' => 'fixed-width-xs'
					),						
				),
				'submit' => array(
					'title' => $this->l('Save'),
				)
			),
		);
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$this->fields_form = array();
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitOption';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'
		&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValuesOption()
		);
		$this->html .= '
			<legend><img src="'.$this->_path.'views/img/setting.png" alt="" title="" /> '.$this->l('Options').'</legend>';
		$this->html .= $helper->generateForm(array($fields_form));
		
}

public function getConfigFieldsValuesOption()
{
		return array(
			'num_pro_display' => Tools::getValue('num_pro_display', Configuration::get('NUM_PRO_DISPLAY'))
		);
}

public function getProductType()
{
		$productType = array();
		$i = 0;
		foreach ($this->product_types as $key => $name)
		{
			$productType[$i]['key'] = $key;
			$productType[$i]['name'] = $name;
			$i++;
		}
		return $productType;
}

public function renderAddForm()
{
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
		$ProductTypes = $this->getProductType();
		$id_tab = Tools::getValue('id_tab');
		$product_type = array('featured_products','special_products','topseller_products','new_products');
		if ($id_tab)
			$tab = new WTProductFilterClass((int)$id_tab);
		else
			$tab = new WTProductFilterClass();
		if (!in_array($tab->product_type, $product_type))
		{
			$tab->product_type = 'choose_the_category';
			$selected_categories = array($tab->product_type_menu); 
		}
		else
			$selected_categories = array();	
		$root_category = Context::getContext()->shop->getCategory();
		
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Menu informations'),
					'icon' => 'icon-cogs'
				),
				'input' => array(
				array(
						'type' => 'text',
						'label' => $this->l('Title'),
						'name' => 'title',
						'lang' => true,
					),
					array(
					'type' => 'select',
					'label' => $this->l('Get product form'),
					'name' => 'product_type',
					'options' => array(
						'query' => $ProductTypes,
						'id' => 'key',
						'name' => 'name'
					)
					
				),
				array(
					'type'  => 'categories',
					'label' => $this->l(' '),
					'name'  => 'categories-tree',
					'show' => $tab->product_type,
					'tree'  => array(
						'id'      => 'categories-tree-aaa',
						'selected_categories' => $selected_categories,
						'disabled_categories' => null,
						'root_category' => $root_category
					)
				),
				array(
						'type' => 'switch',
						'label' => $this->l('Displayed'),
						'name' => 'active_tab',
						'values' => array(
							array(
								'id' => 'display_on',
								'value' => 1,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'display_off',
								'value' => 0,
								'label' => $this->l('Disabled')
							)
						),
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
				),
				'back'=> array(
				'title' => $this->l('Back to list'),
				),
			),
		);

		if (Tools::isSubmit('id_tab') && $this->slideExists((int)Tools::getValue('id_tab')))
		{
			$tab = new WTProductFilterClass((int)Tools::getValue('id_tab'));
			$fields_form['form']['input'][] = array('type' => 'hidden', 'name' => 'id_tab');
		}

		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();
		$helper->module = $this;
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitTab';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$language = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->tpl_vars = array(
			'base_url' => $this->context->shop->getBaseURL(),
			'language' => array(
				'id_lang' => $language->id,
				'iso_code' => $language->iso_code
			),
			'fields_value' => $this->getAddFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id,
			'image_baseurl' => $this->_path.'images/'
		);

		$helper->override_folder = '/';

		return $helper->generateForm(array($fields_form));
}

public function getAddFieldsValues()
{
		$fields = array();

		if (Tools::isSubmit('id_tab') && $this->slideExists((int)Tools::getValue('id_tab')))
		{
			$tab = new WTProductFilterClass((int)Tools::getValue('id_tab'));
			$fields['id_tab'] = (int)Tools::getValue('id_tab', $tab->id);
		}
		else
			$tab = new WTProductFilterClass();
			
		$product_type = array('featured_products','special_products','topseller_products','new_products');
		if (!in_array($tab->product_type, $product_type))
			{
				$tab->product_type = 'choose_the_category';
				$selected_categories = array($tab->product_type_menu); 	
			}
			else
				$selected_categories = array();	
		
		$fields['active_tab'] = Tools::getValue('active_tab', $tab->active);
			
		$fields['product_type'] = Tools::getValue('product_type', $tab->product_type);
		
		$languages = Language::getLanguages(false);

		foreach ($languages as $lang)
			$fields['title'][$lang['id_lang']] = Tools::getValue('title_'.(int)$lang['id_lang'], $tab->title[$lang['id_lang']]);
			
		return $fields;
}

private function getTabsDisplayFront($nb = 10, $id_shop)
{
		$tabs = array();
		$results = Db::getInstance()->ExecuteS(
					'SELECT t.`id_tab` FROM `'._DB_PREFIX_.'wtproductfilter` t
					LEFT JOIN `'._DB_PREFIX_.'wtproductfilter_tabs` tts ON (tts.id_tab = t.id_tab)
					WHERE (t.id_shop = '.(int)$id_shop.')
					AND tts.`active` = 1 ORDER BY tts.`position` ASC
				');	
		foreach ($results as $key => $row)
		{
			$temp = new WTProductFilterClass($row['id_tab']);
			$temp->getProductList($nb);
			$tabs[] = $temp;
		}
		return $tabs;
}

public function hookHeader($params)
{	
		$this->context = Context::getContext();
		$id_shop = $this->context->shop->id;
		$this->context->controller->addJs($this->_path.'views/js/jquery.carouFredSel-6.1.0.js');
		
		if ($this->context->smarty->tpl_vars['page_name']->value == 'index')
		{
			$this->context->controller->addCss($this->_path.'views/css/wtproductfilter.css');
			$this->context->controller->addJs($this->_path.'views/js/jquery-ui-tabs.min.js');
			$this->context->controller->addJs($this->_path.'views/js/getwidthbrowser.js');
			
			$this->context->controller->addCss($this->_path.'views/css/jquery.carouFredSel-6.1.0-packed.css');
			
		}
}

public function hookDisplayHome()
{
		$isMobile = 0;
		$isIpad = 0;
		require_once(_PS_TOOL_DIR_.'mobile_Detect/Mobile_Detect.php');
		$detect = new Mobile_Detect();
		if ($detect->isMobile() && ! $detect->isTablet())
			$isMobile = 1;
		else
			$isMobile = 0;
			
		if ($detect->isTablet())
		$isIpad = 1;
		
		$this->context = Context::getContext();
		$id_shop = $this->context->shop->id;
	
		if ($isMobile || $isIpad)
		{
		if (!$this->isCached('wtproductfilter_mobile.tpl', $this->getCacheId('wtproductfilter_mobile')))
		{
			$tabs = $this->getTabsDisplayFront(6, $id_shop);
			$this->context->smarty->assign(array('tabs' => $tabs));
		}
				return $this->display(__FILE__, 'views/templates/hook/wtproductfilter_mobile.tpl', $this->getCacheId('wtproductfilter_mobile'));
		}
		else
		{
			if (!$this->isCached('wtproductfilter.tpl', $this->getCacheId('wtproductfilter')))
				{
					$tabs = $this->getTabsDisplayFront(Configuration::get('NUM_PRO_DISPLAY'), $id_shop);
					$this->context->smarty->assign(array(
						'tabs' => $tabs,
						'isIpad' =>$isIpad,					
					));
				}
			return $this->display(__FILE__, 'views/templates/hook/wtproductfilter.tpl', $this->getCacheId('wtproductfilter'));
		}
}

public function getImages($id_product, $id_lang, Context $context = null)
{
		if (!$context)
		$context = Context::getContext();
		$sql = 'SELECT image_shop.`cover`, i.`id_image`, il.`legend`, i.`position`
                FROM `'._DB_PREFIX_.'image` i
                '.Shop::addSqlAssociation('image', 'i').'
                LEFT JOIN `'._DB_PREFIX_.'image_lang` il ON (i.`id_image` = il.`id_image` AND il.`id_lang` = '.(int)$id_lang.')
                WHERE i.`id_product` = '.(int)$id_product.'
                ORDER BY `position`';
		return Db::getInstance()->executeS($sql);
}

public function hookDisplayProductListThumbnails($params)
{
	$id_product = (int)$params['product']['id_product'];
	if (!$this->isCached('thumbnail.tpl', $this->getCacheId($id_product)))
	{
		$images = $this->getImages($id_product, (int)$this->context->language->id);
		$ptsimages = $images;
		$this->context->smarty->assign(array(
		'wt_thumbnails' => $ptsimages,
		'product' => $params['product'],
		'wt_thumbnails_key' => rand(100, 999),
		'link' => $this->context->link));
	}
	return $this->display(__FILE__, 'thumbnail.tpl', $this->getCacheId($id_product));
		// return $this->display(__FILE__, 'thumbnail.tpl');
}

public function hookActionShopDataDuplication($params)
{
		Db::getInstance()->execute('
			INSERT IGNORE INTO '._DB_PREFIX_.'wtproductfilter (id_tab, id_shop)
			SELECT id_tab, '.(int)$params['new_id_shop'].'
			FROM '._DB_PREFIX_.'wtproductfilter
			WHERE id_shop = '.(int)$params['old_id_shop']
		);
		$this->clearCache();
}	
protected function getCacheId($name = null)
{
		parent::getCacheId($name);

		if (isset($this->context->currency->id))
			$id_currency = $this->context->currency->id;
		else
			$id_currency = Configuration::get('PS_CURRENCY_DEFAULT');
		
		$groups = implode(', ', Customer::getGroupsStatic((int)$this->context->customer->id));
		$id_lang = (int)$this->context->language->id;
		
		return $name.'|'.(int)Tools::usingSecureMode().'|'.$this->context->shop->id.'|'.$groups.'|'.$id_lang.'|'.$id_currency;
}
	
public function hookActionObjectProductAddAfter($params)
{
		$this->clearCacheWTProductFilter();
}
	
public function hookActionObjectProductUpdateAfter($params)
{
		$this->clearCacheWTProductFilter();
}
	
public function hookActionObjectProductDeleteAfter($params)
{
		$this->clearCacheWTProductFilter();
}
public function hookActionUpdateQuantity($params)
{
		$this->clearCacheWTProductFilter();
}
public function clearCacheWTProductFilter()
{
		$this->_clearCache('wtproductfilter.tpl');
		$this->_clearCache('wtproductfilter_mobile.tpl');
		$this->_clearCache('thumbnail.tpl');
}	
}
?>
