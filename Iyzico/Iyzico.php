<?php

class Iyzico extends PaymentPlugin
{

	private $plugin_options;
	private $options;

    /**
     * @author Mirarus
    */
    public function __construct($plugin_options)
    {
    	self::load(__CLASS__);
    	self::library('IyzipayBootstrap');
    	IyzipayBootstrap::init();

    	$this->plugin_options = $plugin_options;

    	$this->options = new \Iyzipay\Options();
    	$this->options->setApiKey($this->plugin_options['api_key']);
    	$this->options->setSecretKey($this->plugin_options['secret_key']);
    	if ($this->plugin_options['sandbox'] == true)
    		$this->options->setBaseUrl("https://sandbox-api.iyzipay.com/");
    	else
    		$this->options->setBaseUrl("https://api.iyzipay.com/");
    }

	/**
	 * @author Mirarus
	*/
	public function Meta()
	{
		return [
			'name' => __CLASS__,
			'version' => '1.0'
		];
	}

	public function link($params)
	{
		?>
		<button class="btn btn-default btn-block badge badge-boxed badge-soft-success waves-effect waves-light" data-toggle="modal" data-target="#modal-pay_invoice"><?php __('customer', 'pay_invoice'); ?></button>
		<div id="modal-pay_invoice" class="modal bg-dark fade" data-backdrop="false">
			<div class="modal-dialog animate" data-class="fade-down">
				<div class="modal-content box-shadow bg-info">
					<div class="modal-header">
						<div class="modal-title text-md"><?php __('customer', 'bill_pay'); ?></div>
						<button class="close" data-dismiss="modal">&times;</button>
					</div>
					<div class="modal-body" style="padding: 0rem;line-height: 0;">
						<div class="text-center">
							<center><?php $this->pay_form($params); ?></center>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function pay_form($params)
	{
		$request = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();
		$request->setCallbackUrl(url('customer/payment/callback/' . __CLASS__));
		$request->setPaymentSource("TRICEPS");
		if (get_lang() == "tr") 
			$request->setLocale(\Iyzipay\Model\Locale::TR);
		else 
			$request->setLocale(\Iyzipay\Model\Locale::EN);
		$request->setConversationId($this->plugin_options['conversation_id']);
		$request->setPrice($params['amount']);
		$request->setPaidPrice($params['invoice_amount']);
		$request->setCurrency($params['customer']['currency_code']);
		$request->setBasketId($params['invoice_id']);
		$request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::SUBSCRIPTION);

		$buyer = new \Iyzipay\Model\Buyer();
		$buyer->setId(customer_data('id'));
		$buyer->setName($params['customer']['first_name']);
		$buyer->setSurname($params['customer']['last_name']);
		$buyer->setEmail($params['customer']['mail']);
		$buyer->setIdentityNumber("74300864791");
		$buyer->setRegistrationAddress(ip_info("address"));
		$buyer->setIp(GetIP());
		$buyer->setCity(ip_info("city"));
		$buyer->setCountry(ip_info("country"));
		$buyer->setGsmNumber("+905350000000");
		$request->setBuyer($buyer);

		$billingAddress = new \Iyzipay\Model\Address();
		$billingAddress->setContactName($params['customer']['full_name']);
		$billingAddress->setCity(ip_info("city"));
		$billingAddress->setCountry(ip_info("country"));
		$billingAddress->setAddress(ip_info("address"));
		$request->setBillingAddress($billingAddress);

		$basketItems = array();
		foreach (Model::include('customer', 'payment')->invoice_items($params['invoice_id']) as $invoiceItem) {
			if ($invoiceItem['invoice_item_amount'] < 0) {
				$promo = array_pop($basketItems);
				$promoPrice = $promo->getPrice();
				$promoPrice = $promoPrice + $invoice_item['invoice_item_amount'];
				if ($promoPrice == 0) {
					continue;
				} else {
					$promo->setPrice($promoPrice);
					array_push($basketItems, $promo);
				}
				continue;
			}
			$basketItem = new \Iyzipay\Model\BasketItem();
			$basketItem->setId($invoiceItem['invoice_item_id']);
			$basketItem->setName($invoiceItem['invoice_item_description']);
			if (NULL == $invoiceItem['invoice_item_type'])
				$basketItem->setCategory1("Misc");
			else
				$basketItem->setCategory1($invoiceItem['invoice_item_type']);
			$basketItem->setItemType(\Iyzipay\Model\BasketItemType::VIRTUAL);
			$basketItem->setPrice($invoiceItem['invoice_item_amount']);
			if ($invoiceItem['invoice_item_amount'] != 0)
				array_push($basketItems, $basketItem);
		}
		$request->setBasketItems($basketItems);

		$response = \Iyzipay\Model\CheckoutFormInitialize::create($request, $this->options);
		echo '<div class="popup" id="iyzipay-checkout-form"></div>' . $response->getCheckoutFormContent();
	}

	public function callback()
	{
		$token = @form_filter('token');
		$status = @form_filter('status');
		$paymentId = @form_filter('paymentId');
		$conversationData = @form_filter('conversationData');
		if (isset($token)) {
			$request = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
			if (get_lang() == "tr") 
				$request->setLocale(\Iyzipay\Model\Locale::TR);
			else 
				$request->setLocale(\Iyzipay\Model\Locale::EN);
			$request->setConversationId($this->plugin_options['conversation_id']);
			$request->setToken($token);
			$checkoutForm = \Iyzipay\Model\CheckoutForm::retrieve($request, $this->options);
			if (NULL == $checkoutForm) {
				$cbSuccess = false;
				if ($cbSuccess)
					Redir(url('customer/payment/invoice/' . $invoice_id . '/callback'));
				else
					Redir(invoice_status($invoice_id, 'failed'));
			}
			$cbSuccess = false;
			if ($checkoutForm->getConversationId() != $this->plugin_options['conversation_id']) {
				$cbSuccess = false;
				error_print('Payment Error!', 'Iyzipay Error!', 'Message: Request cannot be verified');
				$transactionStatus = 'Request cannot be verified';
			}
			if ("success" == $checkoutForm->getStatus() && 1 == $checkoutForm->getFraudStatus()) {
				$invoice_id = $checkoutForm->getBasketId();
				$transaction_id = $checkoutForm->getpaymentId();
				$amount = $checkoutForm->getPaidPrice();
				$invoice_data = Model::include('customer', 'payment')->invoice($invoice_id);
				if (checkPaymentTransID($transaction_id)) {
					error_print('Payment Error!', 'Iyzipay Error!', 'Message: İsset BuyID');
					$transactionStatus = 'İsset BuyID';
				} else{
					$log_data = '[token] => ' . $token;
					log_Module_Call('Paymanet', __CLASS__, 'callback', $log_data, @$transactionStatus);
					add_Transaction(customer_data('id'), $invoice_data['invoice_id'], __CLASS__, $transaction_id, $amount, "Invoice Payment");
					$cbSuccess = true;
				}
			} elseif ("failure" == $checkoutForm->getStatus()) {
				error_print('Payment Error!', 'Iyzipay Error!', 'Message: ' . $auth->getErrorMessage());
				$transactionStatus = $auth->getErrorMessage();
				$cbSuccess = false;
			}
		} elseif($status) {
			$initStatus = $status;
			$initTransactionId = $paymentId;
			$initConversationId = $conversationId;
			$initConversationData = $conversationData;
			$cbSuccess = false;
			if ($initConversationId != $this->plugin_options['conversation_id']) {
				$cbSuccess = false;
				error_print('Payment Error!', 'Iyzipay Error!', 'Message: Request cannot be verified');
				$transactionStatus = 'Request cannot be verified';
			}
			if ("success" != $initStatus) {
				$cbSuccess = false;
				error_print('Payment Error!', 'Iyzipay Error!', 'Message: 3D Secure payment failed');
				$transactionStatus = '3D Secure payment failed';
			}
			if ("success" == $initStatus) {
				$request = new \Iyzipay\Request\CreateThreedsPaymentRequest();
				if (get_lang() == "tr") 
					$request->setLocale(\Iyzipay\Model\Locale::TR);
				else 
					$request->setLocale(\Iyzipay\Model\Locale::EN);
				$request->setConversationId($this->plugin_options['conversation_id']);
				$request->setPaymentId($initTransactionId);
				$request->setConversationData($initConversationData);
				$auth = \Iyzipay\Model\ThreedsPayment::create($request, $options);
			}
			if (NULL == $auth) {
				$cbSuccess = false;
				if ($cbSuccess)
					Redir(url('customer/payment/invoice/' . $invoice_id . '/callback'));
				else
					Redir(invoice_status($invoice_id, 'failed'));
			}
			if ("success" == $auth->getStatus() && 1 == $auth->getFraudStatus()) {
				$invoice_id = $auth->getBasketId();
				$transaction_id = $auth->getpaymentId();
				$amount = $auth->getPaidPrice();
				$invoice_data = Model::include('customer', 'payment')->invoice($invoice_id);
				if (checkPaymentTransID($transaction_id)) {
					error_print('Payment Error!', 'Iyzipay Error!', 'Message: İsset BuyID');
					$transactionStatus = 'İsset BuyID';
				} else{
					$log_data = '[token] => ' . $token;
					log_Module_Call('Paymanet', __CLASS__, 'callback', $log_data, @$transactionStatus);
					add_Transaction(customer_data('id'), $invoice_data['invoice_id'], __CLASS__, $transaction_id, $amount, "Invoice Payment");
					$cbSuccess = true;
				}
			} elseif ("failure" == $auth->getStatus()) {
				error_print('Payment Error!', 'Iyzipay Error!', 'Message: ' . $auth->getErrorMessage());
				$cbSuccess = false;
			}
		} else{
			$cbSuccess = false;
			error_print('Payment Error!', 'Iyzipay Error!', 'Message: Request cannot be verified');
		}
		if ($cbSuccess) Redir(url('customer/payment/invoice/' . $invoice_id . '/callback'));
		else Redir(invoice_status($invoice_id, 'failed'));
	}
}