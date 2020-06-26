<?php

class Shipy extends PaymentPlugin
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
		$post_vals = array(
			"user_ip" => GetIP(),
			"user_name" => $params['customer']['full_name'],
			"user_address" => ip_info("address"),
			"user_phone" => '5000000000',
			"email" => $params['customer']['mail'],
			"amount" => $params['amount'],
			"return_id" => uniqid() . 'MSCP' . $params['invoice_id'],
			"apiKey" => $this->plugin_options['api_key']
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.shipy.net/pay/credit_card");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1) ;
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vals);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		$result = @curl_exec($ch);
		if (curl_errno($ch)) {
			error_print('Payment Error!', 'Shipy connection error!', 'Error:' . curl_error($ch));
		} else{
			$result = json_decode($result, 1);
			if ($result['status'] != 'success') {
				error_print('Payment Error!', 'Shipy failed!', 'Reason:' . $result['message']);
			} else{
				pr ($post_vals );
				echo '<iframe src="' . $result['link'] . '" frameborder="0" scrolling="no" width="100%" height="800px"></iframe>';
			}
		}
		curl_close($ch);
		Session::set([md5('Payment') => [md5('invoice_id') => $params['invoice_id']]]);
	}

	public function callback()
	{
		$return_id = @form_filter('return_id');
		$payment_amount = @form_filter('payment_amount');
		$invoice_id = explode('MSCP', $return_id);
		if (GetIP() != "185.120.5.2") exit();
		$invoice_data = Model::include('customer', 'payment')->invoice_data($invoice_id[1]);
		if ($invoice_data) {
		//	if (checkPaymentTransID($invoice_id[0])) {
			//	$log_status = "İsset BuyID";
		//	} else{
				$log_data = '[return_id] => ' . $return_id . ' [payment_amount] => ' . $payment_amount;
				$callback = Controller::include('customer', 'payment')->invoice_pay_callback($invoice_data['invoice_id']);
				if ($callback['status'] == true) {
					add_Transaction($invoice_data['invoice_user_id'], $invoice_data['invoice_id'], __CLASS__, $invoice_id[0], $invoice_data['invoice_amount'], "Invoice Payment");
					echo "OK";
					$log_status = "OK";
				} else{
					$log_status = $callback['message'];
				}
		//	}
		} else{
			$log_status = "Invoice ID Not Faund";
		}
		log_Module_Call('Paymanet', __CLASS__, 'callback', $log_data, $log_status);
	}
}