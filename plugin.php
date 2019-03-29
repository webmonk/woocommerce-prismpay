<?php
/**
 * Plugin Name: Woocommerce PrismPay Payment Plugin 
 * Plugin URI: http://www.codemypain.com
 * Description: PrismPay Payment plugin for WooCommerce
 * Version: 1.0.1
 * Author: Isaac Oyelowo
 * Author URI: http://www.codemypain.com
 * Requires at least: 3.0
 * Tested up to: 4.1
 */

/*
#begin plugin
*/

// define plugin directory
define( 'PNM_URL', plugin_dir_url( __FILE__ ) );
//ini_set("display_errors", 1);



//Network Merchants Gateway
function pm_woocommerce() 
{
	require_once dirname(__FILE__) . '/includes/class.php';
	if( !is_admin() )
	{
		new ISAAC_PM_Gateway();
	}
}

//Load network merchants gateway
function add_pm_gateway( $methods )
{
	$methods[] = 'ISAAC_PM_Gateway';
	return $methods;
}


add_filter( 'woocommerce_payment_gateways', 'add_pm_gateway' );

add_action( 'plugins_loaded', 'pm_woocommerce', 0 );


?>