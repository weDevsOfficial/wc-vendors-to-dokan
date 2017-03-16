<?php

/**
 * Ajax Class
 */
class Dokan_WCV_Ajax_Handler {
    /**
     * Class constructor.
     */
    public function __construct() {
        add_action( 'wp_ajax_dokan_wcv_migrate', array( $this, 'migrate' ) );
    }

    /**
     * Migrate data.
     *
     * @return void
     */
    public function migrate() {
        if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'dokan-wcv-migrator' ) ) {
            wp_send_json_error( __( 'Error: Nonce verification failed', 'dokan-wcv' ) );
        }

        $type        = sanitize_text_field( $_POST['type'] );
        $limit       = intval( $_POST['limit'] );
        $done        = intval( $_POST['done'] );
        $total_items = intval( $_POST['total_items'] );

        $migrator = new Dokan_WCV_Migrator_Kit( $type, $limit, 0 );

        $total_items = $total_items ? $total_items : $migrator->get_total();
        
        if ( 0 == $total_items ) {
            wp_send_json_success( array(
                'done'    => 'All',
                'message' => __( 'No item was found to migrate.', 'dokan-wcv' ),
            ) );
        }
        $processed = $migrator->migrate();

        $done = $done + $processed;

        if ( $done != $total_items ) {
            wp_send_json_success( array(
                'total_items' => $total_items,
                'done'        => $done,
                'message'     => sprintf( __( '%d items has been migrated out of %d', 'dokan-wcv' ), $done, $total_items ),
            ) );
        } else {
            wp_send_json_success( array(
                'done'    => 'All',
                'message' => __( 'All items has been migrated.', 'dokan-wcv' ),
            ) );
        }

    }
}
