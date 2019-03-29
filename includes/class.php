<?php
class ISAAC_PM_Gateway extends WC_Payment_Gateway_CC 
{
    private static $log;
	public function __construct()
	{
		$this->id = 'pnm_payment';
		$this ->icon = PNM_URL . 'cards.png';
		$this->has_fields  = false;
        $this->method_title     = __( 'PrismPay Payment' , 'woocommerce' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->supports = array(
            'products',
            'default_credit_card_form',
        );

		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];
		$this->username = $this->settings['username'];
		$this->password = $this->settings['password'];
        
        add_action( 'admin_notices', array( $this,	'ssl_check' ) );
		if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options' ) );
		} else {
			add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		}
	}



	function init_form_fields(){
		$blog_title= get_bloginfo('name');
		$this->form_fields = array(
				'enabled' => array(
						'title' => __('Enable/Disable', 'woocommerce'),
						'type' => 'checkbox',
						'label' => __('Enable PrismPay Payment Plugin.', 'woocommerce'),
						'default' => 'no'),
				'title' => array(
						'title' => __('Title:', 'woocommerce'),
						'type'=> 'text',
						'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
						'default' => __('PrismPay', 'woocommerce')),
				'description' => array(
						'title' => __('Description:', 'woocommerce'),
						'type' => 'textarea',
						'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
						'default' => __('Pay securely using your PrismPay gateway', 'woocommerce')),
				'username' => array(
						'title' => __('Username', 'woocommerce'),
						'type' => 'text',
						'default' => 'username'),
				'password' => array(
                        'title'    => __( 'Password', 'woocommerce' ),
                        'type'     => 'password',
                        'default'  => 'XXXXXXXXXXXXXXXXXXXX'),
        );
	}

	public function admin_options(){
		echo '<h3>'.__('PrismPay Payment Gateway', 'woocommerce').'</h3>';
		echo '<table class="form-table">';
		// Generate the HTML For the settings form.
		$this -> generate_settings_html();
		echo '</table>';

	}

    public function ssl_check() {
        if( $this->enabled == "yes" ) {
            if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
                echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are forcing the checkout pages to be secured." ), $this->method_title ) ."</p></div>";
            }
        }
    } // ssl_check

    public function process_payment( $order_id ) {
        
        $order = new WC_Order( $order_id );

        $credit_card = preg_replace( '/(?<=\d)\s+(?=\d)/', '', trim( $_POST['pnm_payment-card-number'] ) );
        $ccexp_expiry = $_POST['pnm_payment-card-expiry'];
        $month = substr( $ccexp_expiry , 0, 2 );
        $year = substr( $ccexp_expiry , 5, 7 );
        //$cardtype = $this->getCardType($credit_card);

        //
        $exp_date_array = explode( '/', $_POST['pnm_payment-card-expiry'] );

        $exp_month = trim( $exp_date_array[0] );
        $exp_year = trim( $exp_date_array[1] );
        $card = $_POST['pnm_payment-card-number'];
        $url = 'https://prismpay.transactiongateway.com/api/transact.php?';
        $AmountInput = number_format($order->order_total, 2, '.', '');

        $nmipay_args['type'] = 'sale';
        $nmipay_args['username'] = $this->username;
        $nmipay_args['password'] = $this->password;
        $nmipay_args['payment'] = 'creditcard';
        $nmipay_args['ccnumber'] = $credit_card;
        $nmipay_args['cvv'] = $_POST["pnm_payment-card-cvc"];
        $nmipay_args['ccexp'] = $month.'/'.$year;
        $nmipay_args['ipaddress'] = $_SERVER['REMOTE_ADDR'];
        $nmipay_args['orderid'] = $order_id.'-'.time();

        $nmipay_args['first_name'] = $order->billing_first_name;
        $nmipay_args['last_name'] = $order->billing_last_name;
        $nmipay_args['company'] = $order->billing_company;
        $nmipay_args['address1'] = $order->billing_address_1;
        $nmipay_args['address2'] = $order->billing_address_2;
        $nmipay_args['city'] = $order->billing_city;
        $nmipay_args['state'] = $order->billing_state;
        $nmipay_args['zip'] = $order->billing_postcode;
        $nmipay_args['country'] = $order->billing_country;
        $nmipay_args['email'] = $order->billing_email;
        $nmipay_args['amount'] = $AmountInput;

        $name_value_pairs = array();
        foreach ($nmipay_args as $key => $value) {
            $name_value_pairs[] = $key . '=' . urlencode($value);
        }

        $gateway_values =   implode('&', $name_value_pairs);

        $response = wp_remote_post( $url.$gateway_values, array( 'sslverify' => false ) );
        //$this->log($url.$gateway_values);
        $this->log(print_r($response,1));
        //die($result['response']);
        // Make sure its not a WP_ERROR
        if( !is_wp_error($response) ) {
            parse_str($response['body'], $response);
            if( $response['response'] == 1 ) {
                // Add the Success Message
                $order->add_order_note(  __( 'PrismPay Payment completed. Transaction ID: '. $response['transactionid'] .'.', 'woocommerce' ) );

                // Mark as Payment Complete
                $order->payment_complete( $response['transactionid'] );

                // Reduce Stock
                $order->reduce_order_stock();

                // Empty the cart
                WC()->cart->empty_cart();

                // Return to the Success Page
                return array(
                    'result'    => 'success',
                    'redirect'  => $this->get_return_url( $order ),
                );
            } elseif( $response['response'] == 2) {
                // Show error on the cart
                wc_add_notice( $response['responsetext'], 'error' );
                // Save note to Order
                $order->add_order_note( $response['responsetext'] );
            }elseif( $response['response'] == 3) {
                // Show error on the cart
                wc_add_notice( $response['responsetext'], 'error' );
                // Save note to Order
                $order->add_order_note( $response['responsetext'] );
            } else {
                wc_add_notice( 'Unknown error. Please select another method of payment', 'error' );
            }
        } else {
            wc_add_notice( 'There was an error contacting the Payment Gateway.', 'error' );
        }
    } 

    public static function log( $message ) {

        if ( empty( self::$log ) ) {

            self::$log = new WC_Logger();
        }

        self::$log->add( 'woocommerce-gateway-prismpay', $message );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

            error_log( $message );

        }
    }
} 