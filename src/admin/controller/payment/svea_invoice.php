<?php
class ControllerPaymentsveainvoice extends Controller {
	private $error = array();
	 //
	public function index() {
		$this->load->language('payment/svea_invoice');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {
			$this->model_setting_setting->editSetting('svea_invoice', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->redirect(HTTPS_SERVER . 'index.php?route=extension/payment&token=' . $this->session->data['token']);
		}

		$this->data['heading_title'] = $this->language->get('heading_title');

		$this->data['text_enabled']       = $this->language->get('text_enabled');
		$this->data['text_disabled']      = $this->language->get('text_disabled');
		$this->data['text_all_zones']     = $this->language->get('text_all_zones');
		$this->data['entry_order_status'] = $this->language->get('entry_order_status');
		$this->data['entry_geo_zone']     = $this->language->get('entry_geo_zone');
		$this->data['entry_status']       = $this->language->get('entry_status');
		$this->data['entry_sort_order']   = $this->language->get('entry_sort_order');

		$this->data['button_save']        = $this->language->get('button_save');
		$this->data['button_cancel']      = $this->language->get('button_cancel');

		$this->data['tab_general']        = $this->language->get('tab_general');

        //Credentials
        $this->data['entry_username']      = $this->language->get('entry_username');
        $this->data['entry_password']      = $this->language->get('entry_password');
        $this->data['entry_clientno']      = $this->language->get('entry_clientno');

        $this->data['entry_sweden']        = $this->language->get('entry_sweden');
        $this->data['entry_finland']       = $this->language->get('entry_finland');
        $this->data['entry_denmark']       = $this->language->get('entry_denmark');
        $this->data['entry_norway']        = $this->language->get('entry_norway');
        $this->data['entry_germany']       = $this->language->get('entry_germany');
        $this->data['entry_netherlands']   = $this->language->get('entry_netherlands');

        $this->data['entry_testmode']      = $this->language->get('entry_testmode');
        $this->data['entry_auto_deliver']  = $this->language->get('entry_auto_deliver');
        $this->data['entry_auto_deliver_text'] = $this->language->get('entry_auto_deliver_text');
        $this->data['entry_yes']           = $this->language->get('entry_yes');
        $this->data['entry_no']            = $this->language->get('entry_no');

        $this->data['version']  = floatval(VERSION);

        $cred = array();
        $cred[] = array("lang" => "SE","value_username" => $this->config->get('svea_invoice_username_SE'),"name_username" => 'svea_invoice_username_SE',"value_password" => $this->config->get('svea_invoice_password_SE'),"name_password" => 'svea_invoice_password_SE',"value_clientno" => $this->config->get('svea_invoice_clientno_SE'),"name_clientno" => 'svea_invoice_clientno_SE');
        $cred[] = array("lang" => "NO","value_username" => $this->config->get('svea_invoice_username_NO'),"name_username" => 'svea_invoice_username_NO',"value_password" => $this->config->get('svea_invoice_password_NO'),"name_password" => 'svea_invoice_password_NO',"value_clientno" => $this->config->get('svea_invoice_clientno_NO'),"name_clientno" => 'svea_invoice_clientno_NO');
        $cred[] = array("lang" => "FI","value_username" => $this->config->get('svea_invoice_username_FI'),"name_username" => 'svea_invoice_username_FI',"value_password" => $this->config->get('svea_invoice_password_FI'),"name_password" => 'svea_invoice_password_FI',"value_clientno" => $this->config->get('svea_invoice_clientno_FI'),"name_clientno" => 'svea_invoice_clientno_FI');
        $cred[] = array("lang" => "DK","value_username" => $this->config->get('svea_invoice_username_DK'),"name_username" => 'svea_invoice_username_DK',"value_password" => $this->config->get('svea_invoice_password_DK'),"name_password" => 'svea_invoice_password_DK',"value_clientno" => $this->config->get('svea_invoice_clientno_DK'),"name_clientno" => 'svea_invoice_clientno_DK');
        $cred[] = array("lang" => "NL","value_username" => $this->config->get('svea_invoice_username_NL'),"name_username" => 'svea_invoice_username_NL',"value_password" => $this->config->get('svea_invoice_password_NL'),"name_password" => 'svea_invoice_password_NL',"value_clientno" => $this->config->get('svea_invoice_clientno_NL'),"name_clientno" => 'svea_invoice_clientno_NL');
        $cred[] = array("lang" => "DE","value_username" => $this->config->get('svea_invoice_username_DE'),"name_username" => 'svea_invoice_username_DE',"value_password" => $this->config->get('svea_invoice_password_DE'),"name_password" => 'svea_invoice_password_DE',"value_clientno" => $this->config->get('svea_invoice_clientno_DE'),"name_clientno" => 'svea_invoice_clientno_DE');

        $this->data['credentials'] = $cred;


        $this->data['svea_invoice_sort_order']    = $this->config->get('svea_invoice_sort_order');
        $this->data['svea_invoice_testmode']      = $this->config->get('svea_invoice_testmode');
        $this->data['svea_invoice_auto_deliver']          = $this->config->get('svea_invoice_auto_deliver');


 		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}

  		$this->document->breadcrumbs = array();

   		$this->document->breadcrumbs[] = array(
       		'href'      => HTTPS_SERVER . 'index.php?route=common/home&token=' . $this->session->data['token'],
       		'text'      => $this->language->get('text_home'),
      		'separator' => FALSE
   		);

   		$this->document->breadcrumbs[] = array(
       		'href'      => HTTPS_SERVER . 'index.php?route=extension/payment&token=' . $this->session->data['token'],
       		'text'      => $this->language->get('text_payment'),
      		'separator' => ' :: '
   		);

   		$this->document->breadcrumbs[] = array(
       		'href'      => HTTPS_SERVER . 'index.php?route=payment/svea_invoice&token=' . $this->session->data['token'],
       		'text'      => $this->language->get('heading_title'),
      		'separator' => ' :: '
   		);


		$this->data['action'] = HTTPS_SERVER . 'index.php?route=payment/svea_invoice&token=' . $this->session->data['token'];
		$this->data['cancel'] = HTTPS_SERVER . 'index.php?route=extension/payment&token=' . $this->session->data['token'];


		if (isset($this->request->post['svea_invoice_order_status_id'])) {
			$this->data['svea_invoice_order_status_id'] = $this->request->post['svea_invoice_order_status_id'];
		} else {
			$this->data['svea_invoice_order_status_id'] = $this->config->get('svea_invoice_order_status_id');
		}

		$this->load->model('localisation/order_status');

		$this->data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['svea_invoice_geo_zone_id'])) {
			$this->data['svea_invoice_geo_zone_id'] = $this->request->post['svea_invoice_geo_zone_id'];
		} else {
			$this->data['svea_invoice_geo_zone_id'] = $this->config->get('svea_invoice_geo_zone_id');
		}


		$this->load->model('localisation/geo_zone');

		$this->data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();


		if (isset($this->request->post['svea_invoice_status'])) {
			$this->data['svea_invoice_status'] = $this->request->post['svea_invoice_status'];
		} else {
			$this->data['svea_invoice_status'] = $this->config->get('svea_invoice_status');
		}

		if (isset($this->request->post['svea_invoice_sort_order'])) {
			$this->data['svea_invoice_sort_order'] = $this->request->post['svea_invoice_sort_order'];
		} else {
			$this->data['svea_invoice_sort_order'] = $this->config->get('svea_invoice_sort_order');
		}

                if (isset($this->request->post['svea_invoice_testmode'])) {
			$this->data['svea_invoice_testmode'] = $this->request->post['svea_invoice_testmode'];
		} else {
			$this->data['svea_invoice_testmode'] = $this->config->get('svea_invoice_testmode');
		}
                if (isset($this->request->post['svea_invoice_auto_deliver'])) {
			$this->data['svea_invoice_auto_deliver'] = $this->request->post['svea_invoice_auto_deliver'];
		} else {
			$this->data['svea_invoice_auto_deliver'] = $this->config->get('svea_invoice_auto_deliver');
		}

		$this->template = 'payment/svea_invoice.tpl';
		$this->children = array(
			'common/header',
			'common/footer'
		);

		$this->response->setOutput($this->render(TRUE), $this->config->get('config_compression'));
	}


	private function validate() {
		if (!$this->user->hasPermission('modify', 'payment/svea_invoice')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->error) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

}
?>