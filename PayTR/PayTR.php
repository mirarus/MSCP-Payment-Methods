<?php

class PayTR extends PaymentPlugin
{

	private $plugin_options;

    /**
     * @author Mirarus
    */
    public function __construct($plugin_options)
    {
    	self::load(__CLASS__);
    	$this->plugin_options = $plugin_options;
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
		$user_basket = [];
		foreach (Model::include('customer', 'payment')->invoice_items($params['invoice_id']) as $invoiceItem) {
			$user_basket[] = [$invoiceItem["invoice_item_description"], round($invoiceItem["invoice_item_amount"], 2), 1];
		}
		$basket = base64_encode(json_encode($user_basket));

		$merchant_oid = uniqid() . 'MSCP' . $params['invoice_id'];

		$payment_amount = number_format($params['amount'], 2, '.', '');
		$payment_amount = $payment_amount * 100;

		$installment = (bool) $this->plugin_options['installment'];
		if ($installment) $no_installment = 0;
		else $no_installment = 1;

		$max_installment = (int) $this->plugin_options['max_installment'];

		$sandbox = (bool) $this->plugin_options['sandbox'];
		if ($sandbox) $sandbox = 1;
		else $sandbox = 0;

		$debug_on = (bool) $this->plugin_options['debug_on'];
		if ($debug_on) $debug_on = 1;
		else $debug_on = 0;

		$hash_str = $this->plugin_options['merchant_id'] . GetIP() . $merchant_oid . $params['customer']['mail'] . $payment_amount . $basket . $no_installment . $max_installment . $params['customer']['currency_code'] . $sandbox;
		$paytr_token = base64_encode(hash_hmac('sha256', $hash_str . $this->plugin_options['merchant_salt'], $this->plugin_options['merchant_key'], true));

		$post_vals = array(
			'merchant_id' => $this->plugin_options['merchant_id'],
			'user_ip' => GetIP(),
			'merchant_oid' => $merchant_oid,
			'email' => $params['customer']['mail'],
			'payment_amount' => $payment_amount,
			'paytr_token' => $paytr_token,
			'user_basket' => $basket,
			'debug_on' => $debug_on,
			'no_installment' => $no_installment,
			'max_installment' => $max_installment,
			'user_name' => $params['customer']['full_name'],
			'user_address' => ip_info("address"),
			'user_phone' => "+905000000000",
			'merchant_ok_url' => invoice_status($params['invoice_id'], 'success'),
			'merchant_fail_url' => invoice_status($params['invoice_id'], 'failed'),
			'timeout_limit' => 30,
			'currency' => $params['customer']['currency_code'],
			'test_mode' => $sandbox,
			'lang' => get_lang()
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://www.paytr.com/odeme/api/get-token");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1) ;
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vals);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		$result = @curl_exec($ch);

		if (curl_errno($ch)) {
			error_print('Payment Error!', 'PayTR Iframe connection error!', 'Error:' . curl_error($ch));
		} else{
			$result = json_decode($result, 1);
			if ($result['status'] != 'success') {
				error_print('Payment Error!', 'PayTR Iframe failed!', 'Reason:' . $result['reason']);
			} else{
				echo '<iframe src="https://www.paytr.com/odeme/guvenli/' . $result['token'] . '" frameborder="0" scrolling="no" width="100%" height="1210px"></iframe>';
			}
		}
		curl_close($ch);
	}

	public function callback()
	{
		$merchant_oid = @form_filter('merchant_oid');
		$status = @form_filter('status');
		$total_amount = @form_filter('total_amount');
		$Phash = @form_filter('hash');

		$invoice_id = explode('MSCP', $merchant_oid);

		$hash = base64_encode(hash_hmac('sha256', $merchant_oid . $this->plugin_options['merchant_salt'] . $status . $total_amount, $this->plugin_options['merchant_key'], true));

		if ($hash != $Phash) {
			die('PAYTR notification failed: bad hash');
		} else{
			if ($status != 'success') {
				echo "ERROR";
			} else{
				$invoice_data = Model::include('customer', 'payment')->invoice_data($invoice_id[1]);
				if ($invoice_data) {
					if (checkPaymentTransID($invoice_id[0])) {
						$log_status = "İsset BuyID";
						echo "İsset BuyID";
					} else{
						$log_data = '[hash] => ' . $Phash . ' [merchant_oid] => ' . $merchant_oid . ' [status] => ' . $status . ' [total_amount] => ' . $total_amount . ' [payment_type] => ' . @form_filter('payment_type') . ' [payment_amount] => ' . @form_filter('payment_amount') . ' [currency] => ' . @form_filter('currency') . ' [installment_count] => ' . @form_filter('installment_count') . ' [merchant_id] => ' . @form_filter('merchant_id') . ' [test_mode] => ' . @form_filter('test_mode');
						$callback = Controller::include('customer', 'payment')->invoice_pay_callback($invoice_data['invoice_id']);
						if ($callback['status'] == true) {
							add_Transaction($invoice_data['invoice_user_id'], $invoice_data['invoice_id'], __CLASS__, $invoice_id[0], $invoice_data['invoice_amount'], "Invoice Payment");
							$log_status = "OK";
							echo "OK";
						} else{
							$log_status = $callback['message'];
							echo $callback['message'];
						}
					}
				} else{
					$log_data = "Invoice ID Not Faund";
					echo "Invoice ID Not Faund";
				}
				log_Module_Call('Paymanet', __CLASS__, 'callback', $log_data, $log_status);
			}
		}
	}
}