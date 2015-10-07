<?php
/**
 * Recipient customer new account email
 *
 * @author James Allan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
echo '= ' . $email_heading . " =\n\n";
echo sprintf( __( 'Hi there,', 'woocommerce-subscriptions-gifting' ) ) . "\n";
// translators: 1$: Purchaser's name and email, 2$ The name of the site.
echo sprintf( __( '%1$s just purchased ' .  _n( 'a subscription', 'subscriptions', count( $subscriptions ), 'woocommerce-subscriptions-gifting' ) . ' for you at %2$s.', 'woocommerce-subscriptions-gifting' ), esc_html( $subscription_purchaser ), esc_html( $blogname ) );
echo sprintf( __( ' The order has been received and is being processed. Details of the ' . _n( 'subscription', 'subscriptions', count( $subscriptions ), 'woocommerce-subscriptions-gifting' ) . ' are shown below.', 'woocommerce-subscriptions-gifting' ) ) . "\n\n";

$new_recipient = get_user_meta( $recipient_user->ID, 'wcsg_update_account', true );

if ( 'true' == $new_recipient ) {
	echo esc_html__( 'We noticed you didn\'t have an account so we created one for you. Your account login details will have been sent to you in a separate email.' )  . "\n\n";
} else {
	echo sprintf( __( 'You may access your account area to view your new ' . _n( 'subscription', 'subscriptions', count( $subscriptions ), 'woocommerce-subscriptions-gifting' ) . ' here: %s.', 'woocommerce-subscriptions-gifting' ), esc_url( wc_get_page_permalink( 'myaccount' ) ) ) . "\n\n";
}

foreach ( $subscriptions as $subscription_id ) {
	$subscription = wcs_get_subscription( $subscription_id );
	$items        = $subscription->get_items();
	$total        = $subscription->get_formatted_order_total();
	echo sprintf( __( 'Subscription #%s', 'woocommerce-subscriptions-gifting' ), esc_attr( $subscription_id ) ) . "\n\n";
	foreach ( $items as $item ) {
		$_product  = $subscription->get_product_from_item( $item );
		$item_meta = wcs_get_order_item_meta( $item, $_product );
		echo esc_attr( $item['name'] ) . "\n";
		echo $item_meta->display( true, true, '_', "\n" ) . "\n";
		echo __( 'Quantity: ', 'woocommerce-subscriptions-gifting' ) . esc_attr( $item['item_meta']['_qty'][0] ) . "\n";
		echo __( 'Cost: ', 'woocommerce-subscriptions-gifting' ). wp_kses_post( WC_Subscriptions_Product::get_price_string( $item['product_id'], array( 'price' => wc_price( $item['line_subtotal'] ) ) ) ) . "\n\n";
	}
	echo '==================' . "\n";
	echo __( 'Total: ', 'woocommerce-subscriptions-gifting' ) . wp_kses_post( $total ) . "\n";
	echo '==================' . "\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );