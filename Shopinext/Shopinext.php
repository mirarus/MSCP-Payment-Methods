<?php

class Shopinext extends PaymentPlugin
{

	private $plugin_options;
	private $options;

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
					<div class="modal-body bg-white" style="padding: 0rem;line-height: 0;background: white;">
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
		$token_data = array(
			'ACTION' => 'SESSIONTOKEN',
			'APIKEY' => $this->plugin_options['api_key'],
			'SNID' => $this->plugin_options['sn_id'],
			'SNPASS' => $this->plugin_options['sn_password'],
			'PRICE' => $params['amount'],
			'RETURNURL' => url('customer/payment/callback/' . __CLASS__),
			'CUSTOMERNAME' => $params['customer']['full_name'],
			'CUSTOMEREMAIL' => $params['customer']['mail'],
			'CUSTOMERIP' => GetIP(),
			'CUSTOMERUSERAGENT' => get_user_agent(),
			'CUSTOMERPHONE' => '50000000',
			'BILLTOADDRESSLINE' => ip_info("address"),
			'BILLTOCITY' => ip_info("city"),
			'BILLTOCOUNTRY' => ip_info("country"),
			'BILLTOPOSTALCODE' => '00000',
			'BILLTOPHONE' => '50000000',
			'SHIPTOADDRESSLINE' => ip_info("address"),
			'SHIPTOCITY' => ip_info("city"),
			'SHIPTOCOUNTRY' => ip_info("country"),
			'SHIPTOPOSTALCODE' => '00000',
			'SHIPTOPHONE' => '50000000'
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://www.shopinext.com/api/v1");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1) ;
		curl_setopt($ch, CURLOPT_POSTFIELDS, $token_data);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		$result = json_decode(curl_exec($ch), true);
		if (curl_errno($ch)) die("Bağlantı hatası. err:" . curl_error($ch));
		curl_close($ch);
		if (!isset($result['sessionToken'])) echo $result['errorMsg'];
		Session::set([md5(__CLASS__) => [md5('invoice_id') => $params['invoice_id']]]);
		?>
		<form class="pl-5 pr-5 pt-4 pb-4" action="https://www.shopinext.com/sale3d/<?php echo $result['sessionToken']; ?>" method="post" novalidate="novalidate">
			<div class="form-group">
				<label class="text-muted pb-2">Kart Üzerindeki Ad Soyad</label>
				<input type="text" class="form-control text-center" name="name" autocomplete="cc-name" maxlength="32" required>
			</div>
			<div class="form-group">
				<label class="text-muted pb-2">Kart Numarası</label>
				<input type="tel" class="form-control text-center" name="number" placeholder="•••• •••• •••• ••••" inputmode="numeric" pattern="[0-9\s]{13,19}" autocomplete="cc-number" minlength="19" maxlength="19" required>
			</div>
			<div class="form-row">
				<div class="form-group col-md-4 col-4">
					<label class="text-muted pb-2">Ay</label>
					<input type="number" class="form-control text-center" name="month" placeholder="••" autocomplete="cc-exp-month" minlength="2" maxlength="2" required>
				</div>
				<div class="form-group col-md-4 col-4">
					<label class="text-muted pb-2">Yıl</label>
					<input type="number" class="form-control text-center" name="year" placeholder="••••" autocomplete="cc-exp-year" minlength="4" maxlength="4" required>

				</div>
				<div class="form-group col-md-4 col-4">
					<label class="text-muted pb-2">CVC</label>
					<input type="number" class="form-control text-center" name="cvv" placeholder="•••" autocomplete="cc-csc" minlength="3" maxlength="4" required>
				</div>
			</div>
			<button type="submit" class="btn btn-info btn-block">Öde</button>
		</form>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
		<script src="https://www.shopinext.com/assets/js/jquery.mask.js"></script>
		<script>
			$('input[name="number"]').mask("0000 0000 0000 0000");
			$('input[name="month"]').mask("00");
			$('input[name="year"]').mask("0000");
			$('input[name="cvv"]').mask("0000");
		</script>
		<?php
	}

	public function callback()
	{
		$errorCode = @form_filter('errorCode');
		$responseCode = @form_filter('responseCode');
		$sessionToken = @form_filter('sessionToken');
		$errorMsg = @form_filter('errorMsg');
		$responseMsg = @form_filter('responseMsg');
		$orderID = @form_filter('orderID');
		$invoice_id = Session::get(md5(__CLASS__), md5('invoice_id'));

		if (isset($responseCode)) {
			if ($responseCode == 00) {
				$token_data = array(
					'ACTION' => 'ISDONE',
					'SESID' => $sessionToken
				); 
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, "https://www.shopinext.com/api/v1");
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POST, 1) ;
				curl_setopt($ch, CURLOPT_POSTFIELDS, $token_data);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
				curl_setopt($ch, CURLOPT_TIMEOUT, 20);
				$result = @curl_exec($ch);
				if (curl_errno($ch)) {
					error_print('Payment Error!', 'Shopinext connection error!', 'Error:' . curl_error($ch));
				} else{
					$result = json_decode($result, true);
				}
				curl_close($ch);
				if ($result['responseCode'] == 00) {
					$invoice_data = Model::include('customer', 'payment')->invoice($invoice_id);
					if (checkPaymentTransID($orderID)) {
						error_print('Payment Error!', 'Iyzipay Error!', 'Message: İsset BuyID');
						$transactionStatus = 'İsset BuyID';
					} else{
						$log_data = '[responseCode] => ' . $responseCode . ' [sessionToken] => ' . $sessionToken . ' [orderID] => ' . $orderID . ' [responseMsg] => ' . $responseMsg;
						log_Module_Call('Paymanet', __CLASS__, 'callback', $log_data, @$transactionStatus);
						add_Transaction(customer_data('id'), $invoice_data['invoice_id'], __CLASS__, $orderID, $invoice_data['invoice_amount'], "Invoice Payment");
						$cbSuccess = true;
					}
					if ($cbSuccess) Redir(url('customer/payment/invoice/' . $invoice_data['invoice_id'] . '/callback'));
					else Redir(invoice_status($invoice_data['invoice_id'], 'failed'));
				} else{
					Redir(invoice_status($invoice_id, 'failed'));		
				}
			} elseif ($responseCode == 99) {
				Redir(invoice_status($invoice_id, 'failed'));
			}
		}
	}
}