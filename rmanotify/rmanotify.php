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
			if (empty($email) || Validate::isInt($email))
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
		$output='
		<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post">
			<fieldset>
				<legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->l('Settings').'</legend>
				
				<label>'.$this->l('Service to send email').'</label>
				<select name="RMA_NOTIFY_EMAIL">';
				
				foreach (Contact::getContacts($this->context->language->id) as $contact)
					$arr[] = array('email_message' => $contact['id_contact'], 'name' => $contact['name']);
				/** syncrea unifico mail da diversi negozi **/
				$temp = array();	
				foreach ($arr as $key=>$a) {
					if (in_array($a,$temp)) {
						unset($arr[$key]);
					} else {
						$temp[] = $a;
					}
				}
				
				foreach ($arr as $a) {
					$output .= '<option value="'.$a['email_message'].'" '.(Tools::getValue('rma_notify_email', Configuration::get('RMA_NOTIFY_EMAIL')) == $a['email_message'] ? 'selected="selected"' : '' ).' >'.$a['name'].'</option>';
				}
				
				
		$output.='</select>

				<center><input type="submit" name="submitRMA_NOTIFY_EMAIL" value="'.$this->l('Save').'" class="button" /></center>
			</fieldset>
		</form>';
		return $output;
	}

	public function hookActionOrderReturn($params)
	{
	$customer = $this->context->customer;
		$from = $customer->email;
		
		$id_contact = (int)Configuration::get('RMA_NOTIFY_EMAIL'); 
		
		$id_lang = (int)$this->context->language->id;
		
		$contact = new Contact($id_contact, $id_lang );
		
		$id_order = (int)Tools::getValue('id_order');
		
		$message = strval(Tools::getValue('returnText'));
		
		$template = 'order_customer_comment';
		
		// PARTIALLY FROM CONTACT CONTROLLER LINE 49
		
		if (!($id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($from, $id_order)))		
		{
			$fields = Db::getInstance()->executeS('
			SELECT cm.id_customer_thread, cm.id_contact, cm.id_customer, cm.id_order, cm.id_product, cm.email
			FROM '._DB_PREFIX_.'customer_thread cm
			WHERE email = \''.pSQL($from).'\' AND cm.id_shop = '.(int)$this->context->shop->id.' AND ('.
				($customer->id ? 'id_customer = '.(int)($customer->id).' OR ' : '').'
				id_order = '.(int)(Tools::getValue('id_order')).')');
			$score = 0;
			foreach ($fields as $key => $row)
			{
				$tmp = 0;
				if ((int)$row['id_customer'] && $row['id_customer'] != $customer->id && $row['email'] != $from)
					continue;
				if ($row['id_order'] != 0 && Tools::getValue('id_order') != $row['id_order'])
					continue;
				if ($row['email'] == $from)
					$tmp += 4;
				if ($row['id_contact'] == $id_contact)
					$tmp++;
				/*if (Tools::getValue('id_product') != 0 && $row['id_product'] == Tools::getValue('id_product'))
					$tmp += 2;*/
				if ($tmp >= 5 && $tmp >= $score)
				{
					$score = $tmp;
					$id_customer_thread = $row['id_customer_thread'];
				}
				}
		}

		if ($contact->customer_service)
		{
			if ((int)$id_customer_thread)
			{
				$ct = new CustomerThread($id_customer_thread);
				$ct->status = 'open';
				$ct->id_lang = $id_lang ;
				$ct->id_contact = (int)($id_contact);
				if ($id_order = (int)Tools::getValue('id_order'))
					$ct->id_order = $id_order;
				$ct->update();
			}
			else
			{
				$ct = new CustomerThread();
				if (isset($customer->id))
					$ct->id_customer = (int)($customer->id);
				$ct->id_shop = (int)$this->context->shop->id;
				if ($id_order = (int)Tools::getValue('id_order'))
					$ct->id_order = $id_order;
				$ct->id_contact = (int)($id_contact);
				$ct->id_lang = $id_lang;
				$ct->email = $from;
				$ct->status = 'open';
				$ct->token = Tools::passwdGen(12);
				$ct->add();
			}

			if ($ct->id)
			{
				$cm = new CustomerMessage();
				$cm->id_customer_thread = $ct->id;
				$cm->message = Tools::htmlentitiesUTF8($message);
				$cm->ip_address = ip2long($_SERVER['REMOTE_ADDR']);
				$cm->user_agent = $_SERVER['HTTP_USER_AGENT'];
				if (!$cm->add())
					$this->errors[] = Tools::displayError('An error occurred while sending the message.');
			}
			else
				$this->errors[] = Tools::displayError('An error occurred while sending the message.');
		}
		
		if (!count($this->errors))
		{
			$var_list = array(
				'{email}' =>  $from,
				'{lastname}' => $customer->lastname,
				'{firstname}' => $customer->firstname,
				'{email}' => $customer->email,
				'{message}' => strval(Tools::getValue('returnText'))
			);

			
			if (isset($ct) && Validate::isLoadedObject($ct) && $ct->id_order)
				$id_order = $ct->id_order;

			if ($id_order)
			{
				$order = new Order((int)$id_order);
				$var_list['{order_name}'] = $order->getUniqReference();
				$var_list['{id_order}'] = $id_order;
			}
			
			$subject = Mail::l('Return Merchandise Authorization request', $id_lang);
		
			if (!Mail::Send($id_lang ,  $template, $subject , $var_list, $contact->email, $contact->name, $from, ($customer->id ? $customer->firstname.' '.$customer->lastname : '')) ){
				$this->errors[] = Tools::displayError('An error occurred while sending the message.');
			}

		}


		return 0;
	}
}
