<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCSG_Admin {

	public static $option_prefix = 'woocommerce_subscriptions_gifting';

	/**
	 * Setup hooks & filters, when the class is initialised.
	 */
	public static function init() {

		add_action( 'admin_enqueue_scripts',  __CLASS__ . '::enqueue_scripts' );

		add_filter( 'woocommerce_subscription_list_table_column_content', __CLASS__ . '::display_recipient_name_in_subscription_title', 1, 3 );

		add_filter( 'woocommerce_order_items_meta_get_formatted', __CLASS__ . '::remove_recipient_order_item_meta', 1, 1 );

		add_filter( 'woocommerce_subscription_settings', __CLASS__ . '::add_settings', 10, 1 );

		add_filter( 'request',  __CLASS__ . '::request_query', 11 , 1 );

		add_action( 'woocommerce_admin_order_data_after_order_details', __CLASS__ . '::display_edit_subscription_recipient_field', 10, 1 );

		// Save recipient user after WC have saved all subscription order items (40)
		add_action( 'woocommerce_process_shop_order_meta', __CLASS__ . '::save_subscription_recipient_meta', 50, 2 );
	}

	/**
	 * Register/queue admin scripts.
	 */
	public static function enqueue_scripts() {
		global $post;

		$screen = get_current_screen();

		if ( 'shop_subscription' == $screen->id && WCS_Gifting::is_gifted_subscription( $post->ID ) ) {

			wp_register_script( 'wcs_gifting_admin', plugins_url( '/js/wcsg-admin.js', __FILE__ ), array( 'jquery', 'wc-admin-order-meta-boxes' ) );

			wp_localize_script( 'wcs_gifting_admin', 'wcs_gifting', array(
				'revoke_download_permission_nonce' => wp_create_nonce( 'revoke_download_permission' ),
				'ajax_url'                         => admin_url( 'admin-ajax.php' ),
				)
			);

			wp_enqueue_script( 'wcs_gifting_admin' );
		}
	}

	/**
	 * Formats the subscription title in the admin subscriptions table to include the recipient's name.
	 *
	 * @param string $column_content The column content HTML elements
	 * @param WC_Subscription $subscription
	 * @param string $column The column name being rendered
	 */
	public static function display_recipient_name_in_subscription_title( $column_content, $subscription, $column ) {

		if ( 'order_title' == $column && WCS_Gifting::is_gifted_subscription( $subscription ) ) {

			$recipient_id   = WCS_Gifting::get_recipient_user( $subscription );
			$recipient_user = get_userdata( $recipient_id );
			$recipient_name = '<a href="' . esc_url( get_edit_user_link( $recipient_id ) ) . '">';

			if ( ! empty( $recipient_user->first_name ) || ! empty( $recipient_user->last_name ) ) {
				$recipient_name .= ucfirst( $recipient_user->first_name ) . ( ( ! empty( $recipient_user->last_name ) ) ? ' ' . ucfirst( $recipient_user->last_name ) : '' );
			} else {
				$recipient_name .= ucfirst( $recipient_user->display_name );
			}
			$recipient_name .= '</a>';

			$purchaser_id   = $subscription->get_user_id();
			$purchaser_user = get_userdata( $purchaser_id );
			$purchaser_name = '<a href="' . esc_url( get_edit_user_link( $purchaser_id ) ) . '">';

			if ( ! empty( $purchaser_user->first_name ) || ! empty( $purchaser_user->last_name ) ) {
				$purchaser_name .= ucfirst( $purchaser_user->first_name ) . ( ( ! empty( $purchaser_user->last_name ) ) ? ' ' . ucfirst( $purchaser_user->last_name ) : '' );
			} else {
				$purchaser_name .= ucfirst( $purchaser_user->display_name );
			}
			$purchaser_name .= '</a>';

			// translators: $1: is subscription order number,$2: is recipient user's name, $3: is the purchaser user's name
			$column_content = sprintf( _x( '%1$s for %2$s purchased by %3$s', 'Subscription title on admin table. (e.g.: #211 for John Doe Purchased by: Jane Doe)', 'woocommerce-subscriptions-gifting' ), '<a href="' . esc_url( get_edit_post_link( wcsg_get_objects_id( $subscription ) ) ) . '">#<strong>' . esc_attr( $subscription->get_order_number() ) . '</strong></a>', $recipient_name, $purchaser_name );

			$column_content .= '</div>';
		}

		return $column_content;
	}

	/**
	 * Removes the recipient order item meta from the admin subscriptions table.
	 *
	 * @param array $formatted_meta formatted order item meta key, label and value
	 */
	public static function remove_recipient_order_item_meta( $formatted_meta ) {

		if ( is_admin() ) {
			$screen = get_current_screen();

			if ( isset( $screen->id ) && 'edit-shop_subscription' == $screen->id ) {
				foreach ( $formatted_meta as $meta_id => $meta ) {
					if ( 'wcsg_recipient' == $meta['key'] ) {
						unset( $formatted_meta[ $meta_id ] );
					}
				}
			}
		}

		return $formatted_meta;
	}

	/**
	 * Add Gifting specific settings to standard Subscriptions settings
	 *
	 * @param array $settings
	 * @return array $settings
	 */
	public static function add_settings( $settings ) {

		return array_merge( $settings, array(
			array(
				'name'     => __( 'Gifting Subscriptions', 'woocommerce-subscriptions-gifting' ),
				'type'     => 'title',
				'id'       => self::$option_prefix,
			),
			array(
				'name'     => __( 'Gifting Checkbox Text', 'woocommerce-subscriptions-gifting' ),
				'desc'     => __( 'Customise the text displayed on the front-end next to the checkbox to select the product/cart item as a gift.', 'woocommerce-subscriptions' ),
				'id'       => self::$option_prefix . '_gifting_checkbox_text',
				'default'  => __( 'This is a gift', 'woocommerce-subscriptions-gifting' ),
				'type'     => 'text',
				'desc_tip' => true,
			),
			array( 'type' => 'sectionend', 'id' => self::$option_prefix ),
		) );
	}

	/**
	 * Adds meta query to also include subscriptions the user is the recipient of when filtering subscriptions by customer.
	 *
	 * @param  array $vars
	 * @return array
	 */
	public static function request_query( $vars ) {
		global $typenow;

		if ( 'shop_subscription' === $typenow ) {

			// Add _recipient_user meta check when filtering by customer
			if ( isset( $_GET['_customer_user'] ) && $_GET['_customer_user'] > 0 ) {
				$vars['meta_query'][] = array(
					'key'   => '_recipient_user',
					'value' => (int) $_GET['_customer_user'],
					'compare' => '=',
				);
				$vars['meta_query']['relation'] = 'OR';
			}
		}

		return $vars;
	}

	/**
	 * Output a recipient user select field in the edit subscription data metabox.
	 *
	 * @param WP_Post $subscription
	 * @since 1.0.1
	 */
	public static function display_edit_subscription_recipient_field( $subscription ) {

		if ( ! wcs_is_subscription( $subscription ) ) {
			return;
		} ?>

		<p class="form-field form-field-wide wc-customer-user">
			<label for="recipient_user"><?php esc_html_e( 'Recipient:', 'woocommerce-subscriptions-gifting' ) ?></label><?php
			$user_string = '';
			$user_id     = '';
			if ( WCS_Gifting::is_gifted_subscription( $subscription ) ) {
				$user_id     = WCS_Gifting::get_recipient_user( $subscription );
				$user        = get_user_by( 'id', $user_id );
				$user_string = esc_html( $user->display_name ) . ' (#' . absint( $user->ID ) . ' &ndash; ' . esc_html( $user->user_email );
			}

			if ( is_callable( array( 'WCS_Select2', 'render' ) ) ) {
				WCS_Select2::render( array(
					'class'       => 'wc-customer-search',
					'name'        => 'recipient_user',
					'id'          => 'recipient_user',
					'placeholder' => esc_attr__( 'Search for a recipient&hellip;', 'woocommerce-subscriptions-gifting' ),
					'selected'    => $user_string,
					'value'       => $user_id,
					'allow_clear' => 'true',
				) );
			} else { ?>
				<input type="hidden" class="wc-customer-search" id="recipient_user" name="recipient_user" data-placeholder="<?php esc_attr_e( 'Search for a recipient&hellip;', 'woocommerce-subscriptions-gifting' ); ?>" data-selected="<?php echo esc_attr( $user_string ); ?>" value="<?php echo esc_attr( $user_id ); ?>" data-allow_clear="true"/><?php
			}?>
		</p><?php
	}

	/**
	 * Save subscription recipient user meta by updating or deleting _recipient_user post meta.
	 * Also updates the recipient id stored in subscription line item meta.
	 *
	 * @param int $post_id
	 * @param WP_Post $post
	 * @since 1.0.1
	 */
	public static function save_subscription_recipient_meta( $post_id, $post ) {

		if ( 'shop_subscription' != $post->post_type || ! isset( $_POST['recipient_user'] ) || empty( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( $_POST['woocommerce_meta_nonce'], 'woocommerce_save_data' ) ) {
			return;
		}

		$recipient_user         = empty( $_POST['recipient_user'] ) ? '' : absint( $_POST['recipient_user'] );
		$subscription           = wcs_get_subscription( $post_id );
		$customer_user          = $subscription->get_user_id();
		$is_gifted_subscription = WCS_Gifting::is_gifted_subscription( $subscription );

		if ( $recipient_user == $customer_user ) {
			// Remove the recipient
			$recipient_user = '';
			wcs_add_admin_notice( __( 'Error saving subscription recipient: customer and recipient cannot be the same. The recipient user has been removed.', 'woocommerce-subscriptions-gifting' ), 'error' );
		}

		if ( ( $is_gifted_subscription && WCS_Gifting::get_recipient_user( $subscription ) == $recipient_user ) || ( ! $is_gifted_subscription && empty( $recipient_user ) ) ) {
			// Recipient user remains unchanged - do nothing
			return;
		} elseif ( empty( $recipient_user ) ) {
			WCS_Gifting::delete_recipient_user( $subscription );

			// Delete recipient meta from subscription order items
			foreach ( $subscription->get_items() as $order_item_id => $order_item ) {
				wc_delete_order_item_meta( $order_item_id, 'wcsg_recipient' );
			}
		} else {
			WCS_Gifting::set_recipient_user( $subscription, $recipient_user );

			// Update all subscription order items
			foreach ( $subscription->get_items() as $order_item_id => $order_item ) {
				wc_update_order_item_meta( $order_item_id, 'wcsg_recipient', 'wcsg_recipient_id_' . $recipient_user );
			}
		}
	}
}
WCSG_Admin::init();
