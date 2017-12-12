<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCSG_Recipient_Details {

	/**
	 * Setup hooks & filters, when the class is initialised.
	 */
	public static function init() {
		add_filter( 'template_redirect', __CLASS__ . '::update_recipient_details', 1 );
		add_action( 'template_redirect',  __CLASS__ . '::my_account_template_redirect' );
		add_filter( 'wc_get_template', __CLASS__ . '::add_new_customer_template', 10, 5 );
	}

	/**
	 * locates the new recipient details page template if the user is flagged for requiring further details.
	 * @param $located
	 * @param $template_name
	 * @param $args
	 * @param $template_path
	 * @param $default_path
	 */
	public static function add_new_customer_template( $located, $template_name, $args, $template_path, $default_path ) {
		global $wp;
		$current_user_id = get_current_user_id();
		if ( 'true' === get_user_meta( $current_user_id, 'wcsg_update_account', true ) ) {
			if ( 'myaccount/my-account.php' == $template_name && isset( $wp->query_vars['new-recipient-account'] ) ) {
				$located = wc_locate_template( 'new-recipient-account.php', $template_path, plugin_dir_path( WCS_Gifting::$plugin_file ) . 'templates/' );
			}
		}
		return $located;
	}

	/**
	 * redirects the user to the relevant page if they are trying to access my account or recipient account details page.
	 */
	public static function my_account_template_redirect() {
		global $wp;
		$current_user_id = get_current_user_id();
		if ( is_account_page() && ! isset( $wp->query_vars['customer-logout'] ) ) {
			if ( 'true' === get_user_meta( $current_user_id, 'wcsg_update_account', true )  && ! isset( $wp->query_vars['new-recipient-account'] ) ) {
				wp_redirect( wc_get_endpoint_url( 'new-recipient-account', '', wc_get_page_permalink( 'myaccount' ) ) );
				exit();
			} else if ( 'true' !== get_user_meta( $current_user_id, 'wcsg_update_account', true ) && isset( $wp->query_vars['new-recipient-account'] ) ) {
				wp_redirect( wc_get_page_permalink( 'myaccount' ) );
				exit();
			}
		}
	}

	/**
	 * Validates the new recipient account details page updating user data and removing the 'required account update' user flag
	 * if there are no errors in validation.
	 */
	public static function update_recipient_details() {
		if ( isset( $_POST['wcsg_new_recipient_customer'] ) && ! empty( $_POST['_wcsgnonce'] ) && wp_verify_nonce( $_POST['_wcsgnonce'], 'wcsg_new_recipient_data' ) ) {

			$country          = ( ! empty( $_POST['shipping_country'] ) ) ? wc_clean( $_POST['shipping_country'] ) : '';
			$form_fields      = self::get_new_recipient_account_form_fields( $country );
			$password_fields  = array();
			$password_missing = false;

			foreach ( $form_fields as $key => $field ) {

				if ( isset( $field['type'] ) && 'password' == $field['type'] ) {
					$password_fields[ $key ] = $field;
				}

				// If the field is a required field and missing from posted data
				if ( isset( $field['required'] ) && true == $field['required'] && empty( $_POST[ $key ] ) ) {

					if ( isset( $password_fields[ $key ] ) ) {
						if ( ! $password_missing ) {
							wc_add_notice( __( 'Please enter both password fields.', 'woocommerce-subscriptions-gifting' ), 'error' );
							$password_missing = true;
						}
					} else {
						wc_add_notice( $field['label'] . ' ' . __( 'is a required field.', 'woocommerce-subscriptions-gifting' ), 'error' );
					}
				}
			}

			// Now match the passwords but only if we haven't displayed the password missing error
			if ( ! $password_missing && ! empty( $password_fields ) ) {
				$passwords = array_intersect_key( $_POST, $password_fields );

				if ( count( array_unique( $passwords ) ) !== 1 ) {
					wc_add_notice( __( 'The passwords you have entered do not match.', 'woocommerce-subscriptions-gifting' ), 'error' );
				}
			}

			// Validate the postcode field
			if ( $_POST['shipping_postcode'] && ! WC_Validation::is_postcode( $_POST['shipping_postcode'], $_POST['shipping_country'] ) ) {
				wc_add_notice( __( 'Please enter a valid postcode/ZIP.', 'woocommerce-subscriptions-gifting' ), 'error' );
			}

			if ( 0 == wc_notice_count( 'error' ) ) {
				$user    = wp_get_current_user();
				$address = array();
				$non_user_meta_keys = array( 'set_billing', 'new_password', 'repeat_password' );

				foreach ( $form_fields as $key => $field ) {

					if ( ! in_array( $key, $non_user_meta_keys ) ) {

						$value = isset( $_POST[ $key ] ) ? wc_clean( $_POST[ $key ] ) : '';

						if ( false !== strpos( $key, 'shipping_' ) ) {

							$address_field = str_replace( 'shipping_', '', $key ); // Get the key minus the leading 'shipping_'

							// If the field is a shipping first or last name and there isn't a posted value, fallback to our custom name field (if it exists)
							if ( in_array( $key, array( 'shipping_first_name', 'shipping_last_name' ) ) && empty( $_POST[ $key ] ) && ! empty( $_POST[ $address_field ] ) ) {
								$value = wc_clean( $_POST[ $address_field ] );
							}

							if ( isset( $_POST['set_billing'] ) ) {
								update_user_meta( $user->ID, str_replace( 'shipping', 'billing', $key ), $value );
							}

							$address[ $address_field ] = $value;
						}

						update_user_meta( $user->ID, $key, $value );
					}
				}

				if ( ! empty( $_POST['new_password'] ) ) {
					$user->user_pass = wc_clean( $_POST['new_password'] );
				}

				if ( ! empty( $_POST['first_name'] ) ) {
					$user_first_name = wc_clean( $_POST['first_name'] );

					update_user_meta( $user->ID, 'nickname', $user_first_name );
					$user->display_name = $user_first_name;
				}

				wp_update_user( $user );

				if ( ! empty( $address ) ) {
					$recipient_subscriptions = WCSG_Recipient_Management::get_recipient_subscriptions( $user->ID );

					foreach ( $recipient_subscriptions as $subscription_id ) {
						$subscription = wcs_get_subscription( $subscription_id );
						$subscription->set_address( $address, 'shipping' );
					}
				}

				delete_user_meta( $user->ID, 'wcsg_update_account', 'true' );

				wc_add_notice( __( 'Your account has been updated.', 'woocommerce-subscriptions-gifting' ), 'notice' );

				wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
				exit;
			}
		} else if ( isset( $_POST['wcsg_new_recipient_customer'] ) ) {
			wc_add_notice( __( 'There was an error with your request to update your account. Please try again..', 'woocommerce-subscriptions-gifting' ), 'error' );
		}
	}

	/**
	 * Creates an array of form fields for the new recipient user details form
	 * @return array Form elements for recipient details page
	 */
	public static function get_new_recipient_account_form_fields( $country ) {

		$shipping_fields = array();

		if ( wc_shipping_enabled() ) {
			$shipping_fields = WC()->countries->get_address_fields( $country, 'shipping_', true );

			// We have our own name fields, so hide and make the shipping name fields not required
			foreach ( array( 'shipping_first_name', 'shipping_last_name' ) as $field_key ) {
				$shipping_fields[ $field_key ]['type'] = 'hidden';
				$shipping_fields[ $field_key ]['required'] = false;
			}

			// Add the option for users to also set their billing address
			$shipping_fields['set_billing'] = array(
				'type'     => 'checkbox',
				'label'    => esc_html__( 'Set my billing address to the same as above.', 'woocommerce-subscriptions-gifting' ),
				'class'    => array( 'form-row' ),
				'required' => false,
				'default'  => 1,
			);
		}

		$personal_fields = array();

		$personal_fields['first_name'] = array(
			'label'        => esc_html__( 'First Name', 'woocommerce-subscriptions-gifting' ),
			'required'     => true,
			'class'        => array( 'form-row-first' ),
			'autocomplete' => 'given-name',
		);

		$personal_fields['last_name'] = array(
			'label'        => esc_html__( 'Last Name', 'woocommerce-subscriptions-gifting' ),
			'required'     => true,
			'class'        => array( 'form-row-last' ),
			'clear'        => true,
			'autocomplete' => 'family-name',
		);

		$personal_fields['new_password'] = array(
			'type'     => 'password',
			'label'    => esc_html__( 'New Password', 'woocommerce-subscriptions-gifting' ),
			'required' => true,
			'password' => true,
			'class'    => array( 'form-row-first' ),
		);
		$personal_fields['repeat_password'] = array(
			'type'     => 'password',
			'label'    => esc_html__( 'Confirm New Password', 'woocommerce-subscriptions-gifting' ),
			'required' => true,
			'password' => true,
			'class'    => array( 'form-row-last' ),
		);

		return apply_filters( 'wcsg_new_recipient_account_details_fields', array_merge( $personal_fields, $shipping_fields ) );
	}
}
WCSG_Recipient_Details::init();
