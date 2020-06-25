<?php

class Balance extends PaymentPlugin
{

	private $plugin_options;

    /**
     * @author Mirarus
    */
    public function __construct($plugin_options)
    {
    	self::load(__CLASS__);
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

	public function pay_form($params)
	{
		if (customer_data('balance') >= $params['amount']) {
			$new_balance = floatval(floatval(customer_data('balance')) - floatval($params['amount']));
			$update_customer_control = Model::DB()->update('customers')->where('customer_id', customer_data('id'))->set(['customer_balance' => $new_balance]);
			add_Transaction(customer_data('id'), $params['invoice_id'], __CLASS__, rand(0, 99999999999), $params['amount'], "Add Balance");
			if ($update_customer_control) {
				Redir(url('customer/payment/invoice/' . $params['invoice_id'] . '/callback'));
			} else{
				Redir(invoice_status($params['invoice_id'], 'failed'));
			}
		} else{
			Redir(invoice_status($params['invoice_id'], 'failed'));
		}
	}
}