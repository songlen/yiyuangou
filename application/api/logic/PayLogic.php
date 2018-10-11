<?php
/**
 * 订单处理类
 */

namespace app\api\logic;
use think\Db;

// 开奖
class PayLogic {

    public function doPay($user_id, $order_sn, $param, &$error){
        // 加载类
        vendor('eCommerce.mpgClass');
        /************************ Request Variables ***************************/

        $store_id='gwca009685';
        $api_token='68YZOsyDH90DKGSxdasc';

        /********************* Transactional Variables ************************/

        $type='purchase';
        $order_id = $order_sn;
        $cust_id = $user_id;
        $amount = '0.01';
        $pan=$param['card_number'];
        $expiry_date = $param['expiry_date'];        //December 2008
        $crypt = '7';

        /************************** AVS Variables *****************************/

        $avs_street_number = $param['street_number'];
        $avs_street_name = $param['street_name'];
        $avs_zipcode = $param['zipcode'];
        $avs_email = $param['email'];
        $avs_hostname = '35.182.2.214';
        $avs_browser = 'Mozilla';
        $avs_shiptocountry = 'Canada';
        $avs_merchprodsku = '123456';
        $avs_custip = $param['ip'];
        $avs_custphone = $param['custphone'];//'5556667777';

        /************************** CVD Variables *****************************/

        $cvd_indicator = '1';
        $cvd_value = $param['cvd_value'];

        /********************** AVS Associative Array *************************/

        $avsTemplate = array(
                             'avs_street_number'=>$avs_street_number,
                             'avs_street_name' =>$avs_street_name,
                             'avs_zipcode' => $avs_zipcode,
                             'avs_hostname'=>$avs_hostname,
                             'avs_browser' =>$avs_browser,
                             'avs_shiptocountry' => $avs_shiptocountry,
                             'avs_merchprodsku' => $avs_merchprodsku,
                             'avs_custip'=>$avs_custip,
                             'avs_custphone' => $avs_custphone
                            );

        /********************** CVD Associative Array *************************/

        $cvdTemplate = array(
                             'cvd_indicator' => $cvd_indicator,
                             'cvd_value' => $cvd_value
                            );

        /************************** AVS Object ********************************/

        $mpgAvsInfo = new \mpgAvsInfo ($avsTemplate);

        /************************** CVD Object ********************************/

        $mpgCvdInfo = new \mpgCvdInfo ($cvdTemplate);

        /***************** Transactional Associative Array ********************/

        $txnArray = array(
                        'type'=>$type,
                        'order_id'=>$order_id,
                        'cust_id'=>$cust_id,
                        'amount'=>$amount,
                        'pan'=>$pan,
                        'expdate'=>$expiry_date,
                        'crypt_type'=>$crypt
                        );

        /********************** Transaction Object ****************************/

        $mpgTxn = new \mpgTransaction($txnArray);

        /************************ Set AVS and CVD *****************************/

        $mpgTxn->setAvsInfo($mpgAvsInfo);
        $mpgTxn->setCvdInfo($mpgCvdInfo);

        /************************ Request Object ******************************/

        $mpgRequest = new \mpgRequest($mpgTxn);
        $mpgRequest->setProcCountryCode("CA"); //"US" for sending transaction to US environment
        $mpgRequest->setTestMode(true); //false or comment out this line for production transactions

        /*********************** HTTPS Post Object ****************************/

        $mpgHttpPost  =new \mpgHttpsPost($store_id,$api_token,$mpgRequest);

        /*************************** Response *********************************/

        $mpgResponse=$mpgHttpPost->getMpgResponse();
        $complete = $mpgResponse->getComplete();

        var_dump($complete);
        p(echo $mpgResponse->getMessage());
        die();
        if($complete == true){
            return true;
        } else {
            $error = $mpgResponse->getMessage();
            return false;
        }

        // print("\nCardType = " . $mpgResponse->getCardType());
        // print("\nTransAmount = " . $mpgResponse->getTransAmount());
        // print("\nTxnNumber = " . $mpgResponse->getTxnNumber());
        // print("\nReceiptId = " . $mpgResponse->getReceiptId());
        // print("\nTransType = " . $mpgResponse->getTransType());
        // print("\nReferenceNum = " . $mpgResponse->getReferenceNum());
        // print("\nResponseCode = " . $mpgResponse->getResponseCode());
        // print("\nISO = " . $mpgResponse->getISO());
        // print("\nMessage = " . $mpgResponse->getMessage());
        // print("\nIsVisaDebit = " . $mpgResponse->getIsVisaDebit());
        // print("\nAuthCode = " . $mpgResponse->getAuthCode());
        // print("\nComplete = " . $mpgResponse->getComplete());
        // print("\nTransDate = " . $mpgResponse->getTransDate());
        // print("\nTransTime = " . $mpgResponse->getTransTime());
        // print("\nTicket = " . $mpgResponse->getTicket());
        // print("\nTimedOut = " . $mpgResponse->getTimedOut());
        // print("\nAVSResponse = " . $mpgResponse->getAvsResultCode());
        // print("\nCVDResponse = " . $mpgResponse->getCvdResultCode());
        // print("\nITDResponse = " . $mpgResponse->getITDResponse());

    }
}