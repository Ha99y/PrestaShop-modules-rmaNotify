<?php
/*
* 2013 Ha!*!*y
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* It is available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
*
* DISCLAIMER
* This code is provided as is without any warranty.
* No promise of safety or security.
*
*  @author          Ha!*!*y <ha99ys@gmail.com>
*  @copyright       2013 Ha!*!*y
*  @license         http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

if (!defined('_PS_VERSION_'))
  exit;

class rmaNotify extends Module
{
	public function __construct()
	{
		$this->name = 'rmanotify';
		$this->tab = 'emailing';
		$this->author = 'Ha!*!*y';
		$this->version = '1.0';

		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.6');

		parent::__construct();

		$this->displayName = $this->l('RMA Notify');
		$this->description = $this->l('This modules sends eMail Notification, when customer requests a RMA (Return Merchandise Authorization).');

	}

	public function install()
	{
		Configuration::updateValue('RMA_NOTIFY_EMAIL', '');

		if (parent::install() == false || $this->registerHook('actionOrderReturn') == false)
				return false;
		return true;
	}

	public function uninstall()
	{
		Configuration::deleteByName('RMA_NOTIFY_EMAIL');

		//TODO: see if you have to delete the Hook from install function
		if (!parent::uninstall())
			return false;
		return true;
	}

	public function getContent()
	{
		$output = '<h2>'.$this->displayName.'</h2>';
		if (Tools::isSubmit('submitRMA_NOTIFY_EMAIL'))
		{
			$email = Tools::getValue('RMA_NOTIFY_EMAIL');
			if (empty($email) || Validate::isEmail($email))
			{
				Configuration::updateValue('RMA_NOTIFY_EMAIL', $email);
				$output .= '<div class="conf confirm">'.$this->l('Settings updated').'</div>';
			}
			else
			{
				$output .= '<div class="conf error">'.$this->l('Invalid e-mail:').' '.Tools::safeOutput($email).'</div>';
			}
		}
		return $output.$this->displayForm();
	}

	public function displayForm()
	{
		return '
		<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post">
			<fieldset>
				<legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->l('Settings').'</legend>

				<label>'.$this->l('E-mail').'</label>
				<div class="margin-form">
					<input type="text" name="RMA_NOTIFY_EMAIL" value="' .Tools::getValue('rma_notify_email', Configuration::get('RMA_NOTIFY_EMAIL')). '" size="64" />
					<p class="clear">'.$this->l('If email address is empty the shop email is used.').'</p>
				</div>

				<center><input type="submit" name="submitRMA_NOTIFY_EMAIL" value="'.$this->l('Save').'" class="button" /></center>
			</fieldset>
		</form>';
	}

	public function hookActionOrderReturn($params)
	{
		Mail::Send((int)$this->context->language->id, 'order_customer_comment', Mail::l('Return Merchandise Authorization request', (int)$this->context->language->id),
			array(
				'{lastname}' => $this->context->customer->lastname,
				'{firstname}' => $this->context->customer->firstname,
				'{email}' => $this->context->customer->email,
				'{id_order}' => (int)Tools::getValue('id_order'),
				'{message}' => strval(Tools::getValue('returnText'))
			),
			strval(Configuration::get('PS_SHOP_EMAIL')),
			strval(Configuration::get('PS_SHOP_NAME')),
			$this->context->customer->email,
			$this->context->customer->firstname.' '.$this->context->customer->lastname
		);

		return 0;
	}
}