<?php

namespace app\api\controller;
use think\Db;

class Pay extends Base {
    public function __construct(){
        // 设置所有方法的默认请求方式
        $this->method = 'POST';

        parent::__construct();
    }

    public function exec(){
        $user_id = 1;

        vendor('eCommerce.mpgClass');

        /**************************** Request Variables *******************************/

        $store_id='store5';
        $api_token='yesguy';

        /************************* Transactional Variables ****************************/

        $type='purchase';
        $cust_id=$user_id;
        $order_id='ord-'.date("dmy-G:i:s");
        $amount='0.01';
        $pan='4242424242424242';
        $expiry_date='1111';
        $crypt='7';
        $dynamic_descriptor='123';
        $status_check = 'false';

        /*********************** Transactional Associative Array **********************/

        $txnArray=array(
            'type'=>$type,
            'order_id'=>$order_id,
            'cust_id'=>$cust_id,
            'amount'=>$amount,
            'pan'=>$pan,
            'expdate'=>$expiry_date,
            'crypt_type'=>$crypt,
            'dynamic_descriptor'=>$dynamic_descriptor
        );

        /**************************** Transaction Object *****************************/

        $mpgTxn = new \mpgTransaction($txnArray);

        /****************************** Request Object *******************************/

        $mpgRequest = new \mpgRequest($mpgTxn);
        $mpgRequest->setProcCountryCode("CA"); //"US" for sending transaction to US environment
        $mpgRequest->setTestMode(true); //false or comment out this line for production transactions

        /***************************** HTTPS Post Object *****************************/

        /* Status Check Example
        $mpgHttpPost  =new mpgHttpsPostStatus($store_id,$api_token,$status_check,$mpgRequest);
        */

        $mpgHttpPost  =new \mpgHttpsPost($store_id,$api_token,$mpgRequest);

        /******************************* Response ************************************/

        $mpgResponse=$mpgHttpPost->getMpgResponse();

        print("\nCardType = " . $mpgResponse->getCardType());
        print("\nTransAmount = " . $mpgResponse->getTransAmount());
        print("\nTxnNumber = " . $mpgResponse->getTxnNumber());
        print("\nReceiptId = " . $mpgResponse->getReceiptId());
        print("\nTransType = " . $mpgResponse->getTransType());
        print("\nReferenceNum = " . $mpgResponse->getReferenceNum());
        print("\nResponseCode = " . $mpgResponse->getResponseCode());
        print("\nISO = " . $mpgResponse->getISO());
        print("\nMessage = " . $mpgResponse->getMessage());
        print("\nIsVisaDebit = " . $mpgResponse->getIsVisaDebit());
        print("\nAuthCode = " . $mpgResponse->getAuthCode());
        print("\nComplete = " . $mpgResponse->getComplete());
        print("\nTransDate = " . $mpgResponse->getTransDate());
        print("\nTransTime = " . $mpgResponse->getTransTime());
        print("\nTicket = " . $mpgResponse->getTicket());
        print("\nTimedOut = " . $mpgResponse->getTimedOut());
        print("\nStatusCode = " . $mpgResponse->getStatusCode());
        print("\nStatusMessage = " . $mpgResponse->getStatusMessage());
    }
}