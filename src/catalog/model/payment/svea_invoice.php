<?php
class ModelPaymentsveainvoice extends Model {
    
    const SWP_TUPASAPI_URL = "http://www4.it-systems.fi/svea/tupasapi/"; // Static URL, change to production...
    
  	public function getMethod($address,$total) {
            $this->load->language('payment/svea_invoice');

            if ($this->config->get('svea_invoice_status')) {
                $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('svea_invoice_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

                if (!$this->config->get('svea_invoice_geo_zone_id')) {
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
                                'id'         => 'svea_invoice',
                                'code'         => 'svea_invoice',
                                'title'      => $this->language->get('text_title'),
                                'sort_order' => $this->config->get('svea_invoice_sort_order')
                                );
        }


            return $method_data;
    }
        /**
         * Update shops address so billing address is the same as address recieved from Svea UC
         * @param type $address_id
         * @param type $data
         */
        public function updateAddressField($order_id,$data){
            $query = "UPDATE `" . DB_PREFIX . "order` SET ";    //added ` around order as it is a reserved word when no prefix is used
            $row = "";
            $counter = 0;
            foreach ($data as $key => $value){
                $counter == 0 ? $row = "" : $row .= ",";
                $row .= $this->db->escape($key)." = '".$this->db->escape($value)."'";
                $counter ++;
            }
            $query .= $row;
            $query .=  " WHERE order_id  = '" . (int)$order_id . "'";

            $this->db->query($query);

          }

          public function getCountryIdFromCountryCode($countryCode){
                $query = $this->db->query("SELECT country_id, name FROM " . DB_PREFIX . "country WHERE status = '1' AND iso_code_2 = '$countryCode' ORDER BY name ASC");
                $country = $query->rows;
                return array("country_id" => $country[0]['country_id'], "country_name" => $country[0]['name']);
          }

        public function getProductPriceMode(){
             return $this->config->get('svea_invoice_product_price');
        }
        public function getProductPriceModeMin(){
             return $this->config->get('svea_invoice_product_price_min');
        }
        
    public function getAuthenticationParams() {
        $shop_token = $this->config->get('svea_invoice_tupas_shop_token');
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
        $sql = "SELECT api_token FROM `" . DB_PREFIX . "svea_tupas` WHERE payment_module = 'INVOICE'";
        $result = $this->db->query($sql);
        return $result->row['api_token'];
    }    
    
    public function checkTapiReturn($vars) {
        if ($vars['stoken'] == $this->config->get('svea_invoice_tupas_shop_token')) {
            if ($vars['succ'] == '1') {
                $mac_base = $this->config->get('svea_invoice_tupas_shop_token') . '|' .
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
        if ($this->session->data['tupas_iv_ssn']) {
            $mac_base = $this->config->get('svea_invoice_tupas_shop_token') . '|' .
                        '1' . '|' .
                        $this->session->data['tupas_iv_cartid'] . '|' .
                        $this->session->data['tupas_iv_ssn'] . '|' .
                        $this->session->data['tupas_iv_name'] . '|' .
                        $this->getApiToken();
            $calculated_hash = strtoupper(hash('sha256', $mac_base));
            if ($this->session->data['tupas_iv_hash'] == $calculated_hash) {
                $ssn = $this->session->data['tupas_iv_ssn'];
            }
        }
        return $ssn;
    }
    
    public function getAuthenticationUrl() {
        return self::SWP_TUPASAPI_URL . 'authentications/enter';
    }
}
?>