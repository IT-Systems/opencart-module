<?php

class ControllerPaymentsveapartpayment extends Controller {

    /**
     * Returns the currency used for an invoice country.
     */
    protected function getPartpaymentCurrency( $countryCode ) {
        $country_currencies = array(
            'SE' => 'SEK',
            'NO' => 'NOK',
            'FI' => 'EUR',
            'DK' => 'DKK',
            'NL' => 'EUR',
            'DE' => 'EUR'
        );
        return $country_currencies[$countryCode];
    } 
    
    protected function index() {
        // populate data array for use in template
        $this->load->language('payment/svea_partpayment');
        $this->load->model('checkout/order');
        // Tupas API mod [BEGINS] ...
        // OpenCart does something to get parameters, so we have to catch those from server variables
        $sref = $this->request->server['HTTP_REFERER'];
        
        if (strstr($sref, 'ssn') !== false && strstr($sref, 'stoken') !== false && strstr($sref, 'tapihash') !== false) {
            $delim = (strstr($sref, "&amp;")) ? '&amp;' : '&';
            $tapivars = array();
            list($tapivars['ditch'], $tapivars['succ'], $tapivars['ssn'], $tapivars['name'], $tapivars['cart_id'], $tapivars['stoken'], $tapivars['tapihash']) = explode($delim, $sref);
            foreach ($tapivars as $key => $val) :
                list($tapivars['ditch'], $tapivars[$key]) = explode("=", $val);
            endforeach;
            unset($tapivars['ditch']);
            $return = $this->checkTapiReturn($tapivars);
            if ($return) {
                if ($return['ok'] === true && $return['ssn']) { // If everything is fine, store variables into session
                    $this->session->data['tupas_pp_cartid'] = $return['cartid'];
                    $this->session->data['tupas_pp_ssn'] = $return['ssn'];
                    $this->session->data['tupas_pp_name'] = $return['name'];
                    $this->session->data['tupas_pp_hash'] = $return['tapihash'];
                    // ... and reload page (by js) setting url first... this can't apparently be done without extensions... oh well leave the get params there
                    //$this->data['TupasRedirect'] = $this->url->link(('checkout/checkout'), '', 'SSL');
                } elseif ($return['ok'] === false) { // Tampered get params
                    die($this->language->get('tupas_error')); // @todo, better error reporting?
                }
            }
        }
        // ... [ENDS]
        $this->data['button_confirm'] = $this->language->get('button_confirm');
        $this->data['button_back'] = $this->language->get('button_back');

        $this->data['continue'] = 'index.php?route=checkout/success';

        if ($this->request->get['route'] != 'checkout/guest_step_3') {
            $this->data['back'] = 'index.php?route=checkout/payment';
        } else {
            $this->data['back'] = 'index.php?rout=checkout/guest_step_2';
        }

        $this->id = 'payment';
        
        //Get the country from the order
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $this->data['countryCode'] = $order_info['payment_iso_code_2'];
        
        // Tupas mod ... [BEGINS]
        $this->data['useTupas'] = ($this->data['countryCode'] == 'FI' && $this->config->get('svea_partpay_use_tupas')) ? true : false;
        if ($this->data['useTupas'] && ((isset($this->session->data['tupas_pp_ssn']) && $this->session->data['tupas_pp_ssn'] == null) || (!isset($this->session->data['tupas_pp_ssn'])))) {
            $this->session->data['tupas_pp_ssn'] = $this->session->data['tupas_pp_name'] = $this->session->data['tupas_pp_hash'] = null;
            $this->data['tupasParams'] = $this->getTupasParams();
            $this->data['tupas_button_text'] = $this->language->get('button_tupas');            
            $this->data['tupas_api_url'] = $this->model_payment_svea_partpayment->getAuthenticationUrl();
            $this->data['tupas_ssn'] = '';
        } elseif ($this->data['useTupas']) {
            $this->data['tupas_ssn'] = $this->getSsn();
        }
        // ... [ENDS]
        
        $this->data['logo'] = "<img src='admin/view/image/payment/" . $this->getLogo($order_info['payment_iso_code_2']) . "/svea_partpayment.png'>";

        // we show the available payment plans w/monthly amounts as radiobuttons under the logo
        $this->data['paymentOptions'] = $this->getPaymentOptions();



        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/svea_partpayment.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/payment/svea_partpayment.tpl';
        } else {
            $this->template = 'default/template/payment/svea_partpayment.tpl';
            $this->data['partpayment_fail'] = $this->language->get('text_partpayment_fail');
        }
        $this->render();
    }

    private function responseCodes($err, $msg = "") {
        $this->load->language('payment/svea_partpayment');

        $definition = $this->language->get("response_$err");

        if (preg_match("/^response/", $definition))
            $definition = $this->language->get("response_error") . " $msg";

        return $definition;
    }

    public function confirm() {
        $this->load->language('payment/svea_partpayment');
        //Load models
        $this->load->model('payment/svea_invoice');
        $this->load->model('checkout/order');
        $this->load->model('payment/svea_partpayment');
        $this->load->model('checkout/coupon');
        $this->load->model('account/address');

        floatval(VERSION) >= 1.5 ? $this->load->model('checkout/voucher') : $this->load->model('checkout/extension');

        //Load SVEA includes
        include(DIR_APPLICATION . '../svea/Includes.php');
        $response = array();
        //Get order information
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $countryCode = $order['payment_iso_code_2'];
        
        // TUPAS MOD [Begins] : make sure the ssn is the same that tupas api returned
        $this->data['useTupas'] = ($countryCode == 'FI' && $this->config->get('svea_partpay_use_tupas')) ? true : false;
        if ($this->data['useTupas']) {
            if ($this->getSsn() != $_GET['ssn'] || empty($this->getSsn())) {
                $response = array("error" => $this->responseCodes(60000, $this->language->get('response_60000')));
                echo json_encode($response);
                exit();
            }
        }
        // Ends
        
        //Testmode
        if ($this->config->get('svea_partpayment_testmode_' . $countryCode) !== NULL) {
            $conf = $this->config->get('svea_partpayment_testmode_' . $countryCode) == "1" ? new OpencartSveaConfigTest($this->config) : new OpencartSveaConfig($this->config);
        } else {
            $response = array("error" => $this->responseCodes(40001, "The country is not supported for this paymentmethod"));
            echo json_encode($response);
            exit();
        }

        $svea = WebPay::createOrder($conf);

        // Get the products in the cart
        $products = $this->cart->getProducts();

        // make sure we use the currency matching the clientno 
        $this->load->model('localisation/currency');
        $currency_info = $this->model_localisation_currency->getCurrencyByCode( $this->getPartpaymentCurrency($countryCode) );
        $currencyValue = $currency_info['value'];

        //products
        $svea = $this->formatOrderRows($svea, $products, $currencyValue);
        //get all addons
        $addons = $this->formatAddons();
        //extra charge addons like shipping and invoice fee
        foreach ($addons as $addon) {
            if ($addon['value'] >= 0) {
                $svea = $svea
                        ->addOrderRow(Item::orderRow()
                        ->setQuantity(1)
                        ->setAmountExVat(floatval($addon['value'] * $currencyValue))
                        ->setVatPercent(intval($addon['tax_rate']))
                        ->setName(isset($addon['title']) ? $addon['title'] : "")
                        ->setUnit($this->language->get('unit'))
                        ->setArticleNumber($addon['code'])
                        ->setDescription(isset($addon['text']) ? $addon['text'] : "")
                );
                //discounts
            } else {
                $taxRates = $this->getTaxRatesInOrder($svea);
                $discountRows = $this->splitMeanToTwoTaxRates(abs($addon['value']), $addon['tax_rate'], $addon['title'], $addon['text'], $taxRates);
                foreach ($discountRows as $row) {
                    $svea = $svea->addDiscount($row);
                }
            }
        }


         if($order["payment_iso_code_2"] == "DE" || $order["payment_iso_code_2"] == "NL") {
           $addressArr = Svea\Helper::splitStreetAddress( $order['payment_address_1'] );
        }  else {
            $addressArr[1] =  $order['payment_address_1'];
            $addressArr[2] =  "";
        }
        $ssn = (isset($_GET['ssn'])) ? $_GET['ssn'] : 0;

        $item = Item::individualCustomer();
        $item = $item->setNationalIdNumber($ssn)
                ->setEmail($order['email'])
                ->setName($order['payment_firstname'], $order['payment_lastname'])
                ->setStreetAddress($addressArr[1], $addressArr[2])
                ->setZipCode($order['payment_postcode'])
                ->setLocality($order['payment_city'])
                ->setIpAddress($order['ip'])
                ->setPhoneNumber($order['telephone']);

        if ($order["payment_iso_code_2"] == "DE" || $order["payment_iso_code_2"] == "NL") {

            $item = $item->setInitials($_GET['initials'])
                    ->setBirthDate($_GET['birthYear'], $_GET['birthMonth'], $_GET['birthDay']);
        }

        $svea = $svea->addCustomerDetails($item);
        try {
            $svea = $svea
                    ->setCountryCode($countryCode)
                    ->setCurrency($this->session->data['currency'])
                    ->setClientOrderNumber($this->session->data['order_id'])
                    ->setOrderDate(date('c'))
                    ->usePaymentPlanPayment($_GET["paySel"])
                    ->doRequest();
        } catch (Exception $e) {
            $this->log->write($e->getMessage());
            $response = array("error" => $this->responseCodes(0, $e->getMessage()));
            echo json_encode($response);
            exit();
        }


        //If response accepted redirect to thankyou page
        if ($svea->accepted == 1) {
            //update order billingaddress
            $countryId = $this->model_payment_svea_invoice->getCountryIdFromCountryCode(strtoupper($countryCode));
            $sveaAddresses = array();
            if (isset($svea->customerIdentity->firstName) && isset($svea->customerIdentity->lastName)) {
                $sveaAddresses["payment_firstname"] = $svea->customerIdentity->firstName;
                $sveaAddresses["payment_lastname"] = $svea->customerIdentity->lastName;
            } elseif (isset($svea->customerIdentity->firstName) == false || isset($svea->customerIdentity->lastName) == false && isset($svea->customerIdentity->fullName)) {
                $sveaAddresses["payment_firstname"] = $svea->customerIdentity->fullName;
                //$sveaAddresses["payment_lastname"] = ""; //will cause form validation in admin to scream
            }
            isset($svea->customerIdentity->firstName) ? $sveaAddresses["payment_firstname"] = $svea->customerIdentity->firstName : "";
            isset($svea->customerIdentity->lastName) ? $sveaAddresses["payment_lastname"] = $svea->customerIdentity->lastName : "";
            isset($svea->customerIdentity->street) ? $sveaAddresses["payment_address_1"] = $svea->customerIdentity->street : "";
            isset($svea->customerIdentity->street) ? $sveaAddresses["payment_address_1"] = $svea->customerIdentity->street : "";
            isset($svea->customerIdentity->coAddress) ? $sveaAddresses["payment_address_2"] = $svea->customerIdentity->coAddress : "";
            isset($svea->customerIdentity->locality) ? $sveaAddresses["payment_city"] = $svea->customerIdentity->locality : "";
            isset($svea->customerIdentity->zipCode) ? $sveaAddresses["payment_postcode"] = $svea->customerIdentity->zipCode : "";
            $sveaAddresses["payment_country_id"] = $countryId['country_id'];
            $sveaAddresses["payment_country"] = $countryId['country_name'];
            $sveaAddresses["payment_method"] = $this->language->get('text_title');
            $sveaAddresses["comment"] = $order['comment'] . "\n\nSvea order id: ".$svea->sveaOrderId;
                
            $this->model_payment_svea_invoice->updateAddressField($this->session->data['order_id'], $sveaAddresses);
            //If Auto deliver order is set, DeliverOrder
            if ($this->config->get('svea_partpayment_auto_deliver') == 1) {
                $deliverObj = WebPay::deliverOrder($conf);
                //Product rows
                try {
                    $deliverObj = $deliverObj
                            ->setCountryCode($countryCode)
                            ->setOrderId($svea->sveaOrderId)
                            ->deliverPaymentPlanOrder()
                            ->doRequest();
                } catch (Exception $e) {
                    $this->log->write($e->getMessage());
                    $response = array("error" => $this->responseCodes(0, $e->getMessage()));
                    echo json_encode($response);
                    exit();
                }

                //If DeliverOrder returns true, send true to veiw
                if ($deliverObj->accepted == 1) {
                    $response = array("success" => true);
                    //update order status for delivered
                    $this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('svea_partpayment_auto_deliver_status_id'));
                    //I not, send error codes
                } else {
                    $response = array("error" => $this->responseCodes($deliverObj->resultcode, $deliverObj->errormessage));
                }
                //if auto deliver not set, send true to view
            } else {
                $response = array("success" => true);
                //update order status for created
                $this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('svea_partpayment_order_status_id'));
            }

            //else send errors to view
        } else {
            $response = array("error" => $this->responseCodes($svea->resultcode, $svea->errormessage));
        }
        echo json_encode($response);
    }

    private function getAddress($ssn) {

        include(DIR_APPLICATION . '../svea/Includes.php');

        $this->load->model('payment/svea_partpayment');
        $this->load->model('checkout/order');

        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $countryCode = $order['payment_iso_code_2'];
        //Testmode
        $conf = $this->config->get('svea_partpayment_testmode_' . $countryCode) == "1" ? new OpencartSveaConfigTest($this->config) : new OpencartSveaConfig($this->config);

        $svea = WebPay::getAddresses($conf)
                ->setOrderTypePaymentPlan()
                ->setCountryCode($countryCode);

        $svea = $svea->setIndividual($ssn);
        $result = array();
        try {
            $svea = $svea->doRequest();
        } catch (Exception $e) {
            $this->log->write($e->getMessage());
            $result = array("error" => $this->responseCodes(0, $e->getMessage()));
        }

        if (isset($svea->errormessage)) {
            $result = array("error" => $svea->errormessage);
        } else {
            foreach ($svea->customerIdentity as $ci) {

                $name = ($ci->fullName) ? $ci->fullName : $ci->legalName;

                $result[] = array("fullName" => $name,
                    "street" => $ci->street,
                    "address_2" => $ci->coAddress,
                    "zipCode" => $ci->zipCode,
                    "locality" => $ci->locality);
            }
        }
        return $result;
        // echo json_encode($result);
    }


    /**
     * getPaymentOptions gets the available paymentmethods for this country and the order value and returns campaigns w/monthly cost
     *
     * @return array of array("campaignCode" => same, "description" => same , "price_per_month" => (string) price/month in selected currency)
     */

    private function getPaymentOptions() {
        include(DIR_APPLICATION . '../svea/Includes.php');
        $this->load->language('payment/svea_partpayment');
        $this->load->model('payment/svea_partpayment');
        $this->load->model('checkout/order');
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $countryCode = $order['payment_iso_code_2'];

        $result = array();
        if ($this->config->get('svea_partpayment_testmode_' . $countryCode) !== NULL) {
            $sveaConf = ($this->config->get('svea_partpayment_testmode_' . $countryCode) == "1") ? (new OpencartSveaConfigTest($this->config)) : new OpencartSveaConfig($this->config);
        } else {
            $result = array("error" => $this->responseCodes(40001, "The country is not supported for this paymentmethod"));
            return $result;
        }
        $svea = WebPay::getPaymentPlanParams($sveaConf);
        try {
            $svea = $svea->setCountryCode($countryCode)
                    ->doRequest();
        } catch (Exception $e) {
            $this->log->write($e->getMessage());
            $result[] = array("error" => $e->getMessage());
        }

        if (isset($svea->errormessage)) {
            $result = array("error" => $svea->errormessage);
        } else {
            $currency = floatval(VERSION) >= 1.5 ? $order['currency_code'] : $order['currency'];
            $this->load->model('localisation/currency');
            $currencies = $this->model_localisation_currency->getCurrencies();
            $decimals = "";
            foreach ($currencies as $key => $val) {
                if ($key == $currency) {
                    $decimals = intval($val['decimal_place']);
                }
            }
            $formattedPrice = round($this->currency->format(($order['total']), '', false, false), 2);
            $campaigns = WebPay::paymentPlanPricePerMonth($formattedPrice, $svea);
            foreach ($campaigns->values as $cc)
                $result[] = array("campaignCode" => $cc['campaignCode'],
                    "description" => $cc['description'],
                    "price_per_month" => (string) round($cc['pricePerMonth'], $decimals) . " " . $currency . "/" . $this->language->get('month'));
        }

        return $result;
    }

    public function getAddressAndPaymentOptions() {
        $this->load->model('checkout/order');
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $countryCode = $order['payment_iso_code_2'];
        $paymentOptions = $this->getPaymentOptions();

        if ($countryCode == "SE" || $countryCode == "DK") { //|| $countryCode == "NO") {    // getAddresses() turned off for Norway oct'13
            $addresses = $this->getAddress($_GET['ssn']);
        } elseif ($countryCode != "SE" && $countryCode != "NO" && $countryCode != "DK" && $countryCode != "FI" && $countryCode != "NL" && $countryCode != "DE") {
            $addresses = array("error" => $this->responseCodes(40001, "The country is not supported for this paymentmethod"));
        } else {
            $addresses = array();
        }
        $result = array("addresses" => $addresses, "paymentOptions" => $paymentOptions);

        echo json_encode($result);
    }

    private function ShowErrorMessage($response = null) {
        $message = ($response !== null && isset($response->ErrorMessage)) ? $response->ErrorMessage : "Could not get any partpayment alternatives.";
        echo '$("#svea_partpayment_div").hide();
              $("#svea_partpayment_alt").hide();
              $("#svea_partpayment_err").show();
              $("#svea_partpayment_err").append("' . $message . '");
              $("a#checkout").hide();';
    }

    private function formatOrderRows($svea, $products, $currencyValue) {
        $this->load->language('payment/svea_partpayment');
        //Product rows
        foreach ($products as $product) {
            $productPriceExVat = $product['price'] * $currencyValue;

            //Get the tax, difference in version 1.4.x
            if (floatval(VERSION) >= 1.5) {
                $productTax = $this->tax->getTax($product['price'], $product['tax_class_id']);
                $tax = $this->tax->getRates($product['price'], $product['tax_class_id']);
                $taxPercent = 0;
                foreach ($tax as $key => $value) {
                    $taxPercent = $value['rate'];
                }
            } else {
                $taxPercent = $this->tax->getRate($product['tax_class_id']);
            }
            $svea = $svea
                    ->addOrderRow(Item::orderRow()
                    ->setQuantity($product['quantity'])
                    ->setAmountExVat(floatval($productPriceExVat))
                    ->setVatPercent(intval($taxPercent))
                    ->setName($product['name'])
                    ->setUnit($this->language->get('unit'))
                    ->setArticleNumber($product['product_id'])
                    ->setDescription($product['model'])
            );
        }

        return $svea;
    }

    public function formatAddons() {
        //Get all addons
        $this->load->model('setting/extension');
        $total_data = array();
        $total = 0;
        $svea_tax = array();
        $cartTax = $this->cart->getTaxes();
        $results = $this->model_setting_extension->getExtensions('total');
        foreach ($results as $result) {
            //if this result is activated
            if ($this->config->get($result['code'] . '_status')) {
                $amount = 0;
                $taxes = array();
                foreach ($cartTax as $key => $value) {
                    $taxes[$key] = 0;
                }
                $this->load->model('total/' . $result['code']);

                $this->{'model_total_' . $result['code']}->getTotal($total_data, $total, $taxes);

                foreach ($taxes as $tax_id => $value) {
                    $amount += $value;
                }

                $svea_tax[$result['code']] = $amount;
            }
        }
        foreach ($total_data as $key => $value) {

            if (isset($svea_tax[$value['code']])) {
                if ($svea_tax[$value['code']]) {
                    $total_data[$key]['tax_rate'] = (int) round($svea_tax[$value['code']] / $value['value'] * 100); // round and cast, or may get i.e. 24.9999, which shows up as 25f in debugger & written to screen, but converts to 24i
                } else {
                    $total_data[$key]['tax_rate'] = 0;
                }
            } else {
                $total_data[$key]['tax_rate'] = '0';
            }
        }
        $ignoredTotals = 'sub_total, total, taxes';
        $ignoredOrderTotals = array_map('trim', explode(',', $ignoredTotals));
        foreach ($total_data as $key => $orderTotal) {
            if (in_array($orderTotal['code'], $ignoredOrderTotals)) {
                unset($total_data[$key]);
            }
        }
        return $total_data;
    }

    private function getLogo($countryCode) {

        switch ($countryCode) {
            case "SE": $country = "swedish";
                break;
            case "NO": $country = "norwegian";
                break;
            case "DK": $country = "danish";
                break;
            case "FI": $country = "finnish";
                break;
            case "NL": $country = "dutch";
                break;
            case "DE": $country = "german";
                break;
            default: $country = "english";
                break;
        }

        return $country;
    }

    /**

     * TODO replace these with the one in php integration package Helper class in next release
     *
     * Takes a total discount value ex. vat, a mean tax rate & an array of allowed tax rates.
     * returns an array of FixedDiscount objects representing the discount split
     * over the allowed Tax Rates, defined using AmountExVat & VatPercent.
     *
     * Note: only supports two allowed tax rates for now.
     */
    private function splitMeanToTwoTaxRates($discountAmountExVat, $discountMeanVat, $discountName, $discountDescription, $allowedTaxRates) {

        $fixedDiscounts = array();

        if (sizeof($allowedTaxRates) > 1) {

            // m = $discountMeanVat
            // r0 = allowedTaxRates[0]; r1 = allowedTaxRates[1]
            // m = a r0 + b r1 => m = a r0 + (1-a) r1 => m = (r0-r1) a + r1 => a = (m-r1)/(r0-r1)
            // d = $discountAmountExVat;
            // d = d (a+b) => 1 = a+b => b = 1-a

            $a = ($discountMeanVat - $allowedTaxRates[1]) / ( $allowedTaxRates[0] - $allowedTaxRates[1] );
            $b = 1 - $a;

            $discountA = WebPayItem::fixedDiscount()
                    ->setAmountExVat(Svea\Helper::bround(($discountAmountExVat * $a), 2))
                    ->setVatPercent($allowedTaxRates[0])
                    ->setName(isset($discountName) ? $discountName : "" )
                    ->setDescription((isset($discountDescription) ? $discountDescription : "") . ' (' . $allowedTaxRates[0] . '%)')
            ;

            $discountB = WebPayItem::fixedDiscount()
                    ->setAmountExVat(Svea\Helper::bround(($discountAmountExVat * $b), 2))
                    ->setVatPercent($allowedTaxRates[1])
                    ->setName(isset($discountName) ? $discountName : "" )
                    ->setDescription((isset($discountDescription) ? $discountDescription : "") . ' (' . $allowedTaxRates[1] . '%)')
            ;

            $fixedDiscounts[] = $discountA;
            $fixedDiscounts[] = $discountB;
        }
        // single tax rate, so use shop supplied mean as vat rate
        else {
            $discountA = WebPayItem::fixedDiscount()
                    ->setAmountExVat(Svea\Helper::bround(($discountAmountExVat), 2))
                    ->setVatPercent($allowedTaxRates[0])
                    ->setName(isset($discountName) ? $discountName : "" )
                    ->setDescription((isset($discountDescription) ? $discountDescription : ""))
            ;

            $fixedDiscounts[] = $discountA;
        }
        return $fixedDiscounts;
    }

    /**
     * TODO replace these with the one in php integration package Helper class in next release
     *
     * Takes a createOrderBuilder object, iterates over its orderRows, and
     * returns an array containing the distinct taxrates present in the order
     */
    private function getTaxRatesInOrder($order) {
        $taxRates = array();

        foreach ($order->orderRows as $orderRow) {

            if (isset($orderRow->vatPercent)) {
                $seenRate = $orderRow->vatPercent; //count
            } elseif (isset($orderRow->amountIncVat) && isset($orderRow->amountExVat)) {
                $seenRate = Svea\Helper::bround((($orderRow->amountIncVat - $orderRow->amountExVat) / $orderRow->amountExVat), 2) * 100;
            }

            if (isset($seenRate)) {
                isset($taxRates[$seenRate]) ? $taxRates[$seenRate] +=1 : $taxRates[$seenRate] = 1;   // increase count of seen rate
            }
        }
        return array_keys($taxRates);   //we want the keys
    }
    
    public function getTupasParams() {
		$this->load->model('payment/svea_partpayment');   
        return $this->model_payment_svea_partpayment->getAuthenticationParams(); 
    }
    
    public function checkTapiReturn($vars) {
        $this->load->model('payment/svea_partpayment');
        return $this->model_payment_svea_partpayment->checkTapiReturn($vars);
    }
    
    public function getSsn() {
        $this->load->model('payment/svea_partpayment');
        return $this->model_payment_svea_partpayment->getSsn();
    }
    

}

?>