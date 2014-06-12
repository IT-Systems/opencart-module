<?php
class ModelPaymentSveaPartpayment extends Model {
    
    const SWP_TUPASAPI_URL = "http://www4.it-systems.fi/svea/tupasapi/shops"; // Static URL, change to production...
    
	public function install() {
        $token = uniqid();
        $response = $this->createShopInstance($token);
        if (!$response) {
            die("Could not create a shop instance to Tupas API."); // @todo :: Improve error handling (but how?)
        }
        $this->model_setting_setting->editSetting('svea_partpayment', array('svea_partpay_use_tupas' => 1));    
        $this->model_setting_setting->editSetting('svea_partpayment', array('svea_partpay_tupas_mode' => 0));
        $this->model_setting_setting->editSetting('svea_partpayment', array('svea_partpay_tupas_shop_token' => $token));

        $sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "svea_tupas` (
                    id INT NOT NULL AUTO_INCREMENT, 
                    shop_id INT NOT NULL, 
                    api_token VARCHAR(45) NOT NULL, 
                    payment_module VARCHAR(45) NOT NULL,
                    previous_mode VARCHAR(10) NOT NULL, 
                    previous_shop_token VARCHAR(45) NOT NULL, 
                    PRIMARY KEY (`id`, `payment_module`), 
                    UNIQUE INDEX `pm_uniq` (`payment_module` ASC) 
                )";
        $this->db->query($sql);
        
        $sql = "INSERT INTO `" . DB_PREFIX . "svea_tupas` (shop_id, api_token, payment_module, previous_mode, previous_shop_token) 
                VALUES 
                    ('{$response->id}', '{$response->api_token}', 'PARTPAY', 'test', '{$token}') 
                ON DUPLICATE KEY UPDATE 
                    shop_id = '{$response->id}', api_token = '{$response->api_token}', previous_mode = 'test', previous_shop_token = '{$token}'";
        $this->db->query($sql);
    }
    
    
    public function uninstall() {
        if (!$this->removeShopInstance()) {
            die("Could not delete a shop instance from Tupas API."); // @todo :: Improve error handling (but how?)
        }
    }
    
    
    function createShopInstance($shop_token){ 
        $shop_name = $this->config->get("config_name");
        $shop_url = HTTP_CATALOG;
        
        $shop_info = array(
            'name' => $shop_name,
            'shop_token' => $shop_token,
            'mode' => 'test',
            'url' => $shop_url
            );
        $data = array('json' => json_encode($shop_info));
        // We can't be sure that cUrl is installed so use php's native methods
        $params = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data)));

        $context = stream_context_create($params);
        $fp = @fopen(self::SWP_TUPASAPI_URL, 'rb', false, $context);
        if (!$fp) {
            return false;
        }
        $response = json_decode(stream_get_contents($fp));
        if ($response->status->code !== 200) {
            return false;
        }
        return $response;
    }
    
    
    function editShopInstance() {
        $previous_shop_token = $this->get_previous('shop_token');
        $shop_id = $this->getShopId();
        
        // Perform a request
        $shop_info = array(
             'shop_token' => $this->request->post['svea_partpay_tupas_shop_token'],
             'mode' => $this->request->post['svea_partpay_tupas_mode'],
             'hash' => hash('sha256', $previous_shop_token . $this->getApiToken()));
        $data = array('json' => json_encode($shop_info));
        $params = array(
             'http' => array(
                 'method' => 'POST',
                 'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                 'content' => http_build_query($data)));
        $context = stream_context_create($params);
        $fp = fopen(self::SWP_TUPASAPI_URL."/".$shop_id, 'rb', false, $context);
        $response = json_decode(stream_get_contents($fp));
        
        // And update previous values to current ones saved in tupas table
        if ($response->status->code === 200) {
            $sql = "UPDATE `" . DB_PREFIX . "svea_tupas` 
                        SET previous_shop_token = '{$this->request->post['svea_partpay_tupas_shop_token']}', previous_mode = '{$this->request->post['svea_partpay_tupas_mode']}' 
                    WHERE payment_module = 'PARTPAY'";
            $this->db->query($sql);
        }        
    }
    
    
    function removeShopInstance() { 
        $shop_id = $this->getShopId();
        $shop_token = $this->config->get('svea_partpay_tupas_shop_token');
        $api_token = $this->getApiToken();
        
        $data = array(
            'json' => json_encode(array(
                'shop_token' => $shop_token, 
                'hash' => hash('sha256', $shop_token.$api_token)
                ))
            );
        $params = array(
            'http' => array(
                'method' => 'DELETE',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data)));
        $context = stream_context_create($params);
        $fp = @fopen(self::SWP_TUPASAPI_URL."/".$shop_id, 'rb', false, $context);
        if (!$fp) {
            return false;
        }
        $response = json_decode(stream_get_contents($fp));
        if ($response->status->code !== 200) {
            return false;
        }
        return $response;
    }
    
    
    function tupasSettingsChanged() {
        $sql = "SELECT previous_mode, previous_shop_token FROM `" . DB_PREFIX . "svea_tupas` WHERE payment_module = 'PARTPAY'";
        $result = $this->db->query($sql);
        // Check values from post data, since config isn't updated at this point.
        return ($result->row['previous_shop_token'] != $this->request->post['svea_partpay_tupas_shop_token'] || 
                $result->row['previous_mode'] != $this->request->post['svea_partpay_tupas_mode'])
        ? true : false;
    }
    
    
    function get_previous($col='shop_token'){
        $sql = "SELECT previous_{$col} AS pval FROM `" . DB_PREFIX . "svea_tupas` WHERE payment_module = 'PARTPAY'";
        $result = $this->db->query($sql);
        return $result->row['pval'];
    }
    
    
    function getShopId() {
        $sql = "SELECT shop_id FROM `" . DB_PREFIX . "svea_tupas` WHERE payment_module = 'PARTPAY'";
        $result = $this->db->query($sql);
        return $result->row['shop_id'];
    }
    
    
    function getApiToken() {
        $sql = "SELECT api_token FROM `" . DB_PREFIX . "svea_tupas` WHERE payment_module = 'PARTPAY'";
        $result = $this->db->query($sql);
        return $result->row['api_token'];
    }
    
}
?>