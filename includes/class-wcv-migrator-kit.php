<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Dokan_WCV_Migrator_Kit' ) ):

class Dokan_WCV_Migrator_Kit {
    protected $type;

    protected $total_items;

    protected $item_obj;

    /**
     * Class constructor
     *
     * @param  string  $type
     * @param  integer $limit
     * @param  integer $offset
     *
     * @return void
     */
    public function __construct( $type = 'vendor', $limit = 50, $offset = 0 ) {
        $this->type   = $type;
        $this->limit  = $limit;
        $this->offset = $offset;

        switch ( $type ) {
            case 'vendor':
                $this->item_obj = new WP_User_Query( array( 'role__in' => array( 'vendor', 'pending_vendor' ), 'number' => $limit, 'offset' => $offset ) );
                break;
        }
    }

    /**
     * Get the total number of items
     *
     * @return int
     */
    public function get_total() {
        global $wpdb;

        switch ( $this->type ) {
            case 'vendor':
                $this->total_items = (int) $this->item_obj->get_total();
                break;

            case 'order':
                $this->total_items = (int) $wpdb->get_var(
                    "
                    SELECT count(p2.ID)
                        FROM {$wpdb->prefix}posts p1
                        LEFT JOIN {$wpdb->prefix}posts p2 ON p2.ID = p1.post_parent
                        WHERE p1.post_type = 'shop_order_vendor'
                    "
                );
                break;
        }

        return $this->total_items;
    }

    /**
     * Process the migration
     *
     * @return void
     */
    public function migrate() {
        global $wpdb;

        $items = array();

        switch ( $this->type ) {
            case 'vendor':
                $items = $this->item_obj->get_results();

                foreach ( $items as $item ) {
                    $seller_id = $item->ID;

                    if ( in_array( 'vendor', $item->roles ) ) {
                        $item->remove_role( 'vendor' );
                        $item->update_meta( 'dokan_enable_selling', 'yes' );
                    } else {
                        $item->remove_role( 'pending_vendor' );
                        $item->update_meta( 'dokan_enable_selling', 'no' );
                    }

                    $item->add_role( 'seller' );

                    $seller_settings = array( 'store_name' => $item->user_login );

                    update_user_meta( $seller_id, 'dokan_profile_settings', $seller_settings );
                    update_user_meta( $seller_id, 'dokan_store_name', $item->user_login );
                }

                break;

            case 'order':

                $items = $wpdb->get_results(
                    "
                    SELECT p2.ID AS order_id, p2.post_status as post_status, GROUP_CONCAT(p1.ID) AS sub_order_ids
                        FROM {$wpdb->prefix}posts p1
                        LEFT JOIN {$wpdb->prefix}posts p2 ON p2.ID = p1.post_parent
                        WHERE p1.post_type = 'shop_order_vendor'
                        LIMIT {$this->offset}, {$this->limit}
                    "
                );

                if ( ! empty( $items ) && isset( $items[0]->order_id ) ) {

                    foreach ( $items as $order ) {
                        $sub_order_ids = $order->sub_order_ids;

                        $product_vendors = $wpdb->get_results(
                            "
                            SELECT oi.order_id as order_id, p.post_author as post_author
                            FROM {$wpdb->prefix}posts p
                            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oim.meta_value = p.ID
                            LEFT JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_item_id = oim.order_item_id
                            WHERE oim.meta_key = '_product_id' AND oi.order_id IN ({$sub_order_ids}) GROUP BY p.post_author
                            "
                        );

                        if ( count( $product_vendors ) > 1 ) {
                            foreach ( $product_vendors as $product_vendor ) {
                                $wpdb->update( "{$wpdb->prefix}posts", array( 'post_author' => $product_vendor->post_author, 'post_type' => 'shop_order', 'post_status' => $order->post_status ), array( 'ID' => $product_vendor->order_id ) );

                                $this->sync_dokan_order_meta( $product_vendor->order_id, $product_vendor->post_author );
                            }
                        } else {
                            $wpdb->query( "DELETE FROM {$wpdb->prefix}posts WHERE ID IN ({$sub_order_ids})" );

                            $wpdb->update( "{$wpdb->prefix}posts", array( 'post_author' => $product_vendors[0]->post_author ), array( 'ID' => $order->order_id ) );

                            $this->sync_dokan_order_meta( $order->order_id, $product_vendors[0]->post_author );
                        }

                    }

                }

                break;
        }

        return count( $items );
    }

    /**
     * Sync the dokan order meta
     *
     * @param  int $order_id
     * @param  int $seller_id
     *
     * @return void
     */
    public function sync_dokan_order_meta( $order_id, $seller_id ) {
        global $wpdb;

        $order       = new WC_Order( $order_id );
        $order_total = $order->get_total();

        if ( $order->get_total_refunded() ) {
            $order_total = $order_total - $order->get_total_refunded();
        }

        $order_status = $order->post_status;
        $net_amount   = $order_total;

        $wpdb->insert( $wpdb->prefix . 'dokan_orders',
            array(
                'order_id'     => $order_id,
                'seller_id'    => $seller_id,
                'order_total'  => $order_total,
                'net_amount'   => $net_amount,
                'order_status' => $order_status,
            ),
            array(
                '%d',
                '%d',
                '%f',
                '%f',
                '%s',
            )
        );
    }

}

endif;
