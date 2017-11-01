<?php
/**
 *
 * @see WCG_Unit_Test_Case::setUp()
 * @since 2.0
 */
class WCSG_Test_Download_Permission_Functions extends WC_Unit_Test_Case {

	/**
	 * Tests for WCSG_Download_Handler::grant_recipient_download_permissions
	 * Basic tests for granting recipients download permissions
	 */
	public function test_grant_recipient_download_permissions() {

		$recipient_user        = wp_create_user( 'recipient', 'password', 'email@example.com' );
		$purchaser_user        = wp_create_user( 'purchaser', 'password', 'purchaser_user@example.com' );
		$gifted_subscription   = WCS_Helper_Subscription::create_subscription( array( 'customer_id' => $purchaser_user ), array( 'recipient_user' => $recipient_user ) );
		$subscription          = WCS_Helper_Subscription::create_subscription( array( 'customer_id' => $purchaser_user ) );
		$subscription_product  = WCS_Helper_Product::create_simple_subscription_product( array( 'downloadable' => 'yes', 'virtual' => 'yes' ) );

		//don't grant purchaser access.
		update_option( 'woocommerce_subscriptions_gifting_downloadable_products', 'no' );

		//Default Case - Gifted subscription should grant recipient download permission.
		$data                = array( 'order_id' => wcsg_get_objects_id( $gifted_subscription ) );
		$result              = WCSG_Download_Handler::grant_recipient_download_permissions( $data );
		$purchaser_downloads = self::get_download_permissions( $purchaser_user );

		$this->assertEquals( $result['user_id'], $recipient_user );
		$this->assertTrue( empty( $purchaser_downloads ) );

		//Not gifted - Not gifted subscription shouldn't change the user granted download permission
		$data                = array( 'order_id' => wcsg_get_objects_id( $subscription ) );
		$result              = WCSG_Download_Handler::grant_recipient_download_permissions( $data );
		$this->assertTrue( empty( $result['user_id'] ) );

		//test granting purchasers access.
		$updated = update_option( 'woocommerce_subscriptions_gifting_downloadable_products', 'yes' );

		$data                = array( 'order_id' => wcsg_get_objects_id( $gifted_subscription ), 'download_id' => 1, 'product_id' => wcsg_get_objects_id( $subscription_product ) );
		$result              = WCSG_Download_Handler::grant_recipient_download_permissions( $data );
		$purchaser_downloads = self::get_download_permissions( $purchaser_user );

		//both recipient and purchaser should have permissions
		$this->assertEquals( $result['user_id'], $recipient_user );
		$this->assertTrue( ! empty( $purchaser_downloads ) );

		foreach( $purchaser_downloads as $download ) {
			$this->assertEquals( $download->order_id, wcsg_get_objects_id( $gifted_subscription ) );
			$this->assertEquals( $download->product_id, wcsg_get_objects_id( $subscription_product ) );
		}

		//Clean-up
		remove_action( 'before_delete_post', 'WC_Subscriptions_Manager::maybe_cancel_subscription', 10, 1 );
		wp_delete_post( wcsg_get_objects_id( $gifted_subscription ) );
		wp_delete_post( wcsg_get_objects_id( $subscription ) );
		add_action( 'before_delete_post', 'WC_Subscriptions_Manager::maybe_cancel_subscription', 10, 1 );

		wp_delete_user( $purchaser_user );
		wp_delete_user( $recipient_user );
	}

	/**
	 * Returns an array of user download permissions.
	 *
	 * @param int $user_id The id of the user to return download permissions for.
	 * @return array of download permissions for the user.
	 */
	public static function get_download_permissions( $user_id ){
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( "
			SELECT *
			FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions
			WHERE user_id = %d;", $user_id ) );
	}
}
