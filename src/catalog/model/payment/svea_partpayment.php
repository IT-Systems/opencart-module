<?php
class ModelPaymentsveapartpayment extends Model {
    
    const SWP_TUPASAPI_URL = "http://www4.it-systems.fi/svea/tupasapi/"; // Static URL, change to production...
    
    public function getMethod($address,$total) {
        $this->load->language('payment/svea_partpayment');
        $countryCode = $address['iso_code_2'];

        if ($this->config->get('svea_partpayment_status')) {
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('svea_partpayment_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

            if ($this->config->get("svea_partpayment_min_amount_$countryCode") > $total) {
                $status = FALSE;
            } elseif (!$this->config->get('svea_partpayment_geo_zone_id')) {
                $status = TRUE;
            } elseif ($query->num_rows) {
                $status = TRUE;
            } else {
                $status = FALSE;
            }

        } else {
            $status = FALSE;
        }

        $method_data = array();

        if ($status) {
                $method_data = array(
                                    'id'         => 'svea_partpayment',
                                    'code'       => 'svea_partpayment',
                                    'title'      => $this->language->get('text_title'),
                                    'sort_order' => $this->config->get('svea_partpayment_sort_order')
                                    );
    	}

    return $method_data;
    }

    public function getProductPriceMode(){
        return $this->config->get('svea_partpayment_product_price');
    }
    
    public function getAuthenticationParams() {
        $shop_token = $this->config->get('svea_partpay_tupas_shop_token');
        $cart_id = $this->session->data['token']; // Session token, no cart id in opencart.
        $params = array(
            'shop_token' => $shop_token, 
            'cart_id' => $cart_id,
            'return_url' => HTTP_SERVER . 'index.php?route=checkout/checkout',
            'hash' => strtoupper(hash('sha256', $shop_token . '|' . $cart_id . '|' . $this->getApiToken()))
            );
        return $params;
    }
    
    function getApiToken() {
        $sql = "SELECT api_token FROM `" . DB_PREFIX . "svea_tupas` WHERE payment_module = 'PARTPAY'";
        $result = $this->db->query($sql);
        return $result->row['api_token'];
    }    
    
    public function checkTapiReturn($vars) {
        if ($vars['stoken'] == $this->config->get('svea_partpay_tupas_shop_token')) {
            if ($vars['succ'] == '1') {
                $mac_base = $this->config->get('svea_partpay_tupas_shop_token') . '|' .
                            '1' . '|' .
                            $vars['cart_id'] . '|' .
                            $vars['ssn'] . '|' .
                            urldecode($vars['name']) . '|' . // urldecode ( might have öäå:s encoded )
                            $this->getApiToken();
                $calculated_hash = strtoupper(hash('sha256', $mac_base));
               
                if ($calculated_hash == $vars['tapihash']) { // OK
                    return array('ok' => true, 'ssn' => $vars['ssn'], 'name' => urldecode($vars['name']), 'tapihash' => $vars['tapihash'], 'cartid' => $vars['cart_id']);
                } else {
                    return array('ok' => false);
                }
            } else {
                return array('ok' => true, 'ssn' => null);
            }
        } else {
            return false;
        }
    }
    
    public function getSsn() {
        $ssn = '';
        //var_dump($this->session->data['tupas_iv_ssn']);
        if ($this->session->data['tupas_pp_ssn']) {
            $mac_base = $this->config->get('svea_partpay_tupas_shop_token') . '|' .
                        '1' . '|' .
                        $this->session->data['tupas_pp_cartid'] . '|' .
                        $this->session->data['tupas_pp_ssn'] . '|' .
                        $this->session->data['tupas_pp_name'] . '|' .
                        $this->getApiToken();
            $calculated_hash = strtoupper(hash('sha256', $mac_base));
            if ($this->session->data['tupas_pp_hash'] == $calculated_hash) {
                $ssn = $this->session->data['tupas_pp_ssn'];
            }
        }
        return $ssn;
    }
    
    public function getAuthenticationUrl() {
        return self::SWP_TUPASAPI_URL . 'authentications/enter';
    }
}
?>