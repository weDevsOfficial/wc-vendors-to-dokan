<?php
/**
Plugin Name: Dokan WC Vendors Migrator
Plugin URI: http://wedevs.com/
Description: Migrate WC Vendors Data to Dokan
Version: 1.0.0
Author: weDevs
Author URI: http://wedevs.com/
License: GPL2
*/

/**
 * Copyright (c) 2016 weDevs (email: info@wedevs.com). All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * **********************************************************************
 */

// don't call the file directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Dokan_WC_Vendors_Migrator class
 */
class Dokan_WCV_Migrator {

    /**
     * Class constructor.
     */
    public function __construct() {
        // load the addon
        add_action( 'dokan_loaded', array( $this, 'plugin_init' ) );
    }

    /**
     * Initialize the class.
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Init the plugin.
     *
     * @return void
     */
    public function plugin_init() {
        include dirname( __FILE__ ) . '/includes/class-wcv-migrator-kit.php';
        include dirname( __FILE__ ) . '/includes/class-ajax.php';

        // Instantiate classes
        $this->init_classes();

        // Initialize the action hooks
        $this->init_actions();
    }

    /**
     * Init the plugin classes.
     *
     * @return void
     */
    private function init_classes() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            new Dokan_WCV_Ajax_Handler();
        }
    }

    /**
     * Init the plugin actions.
     *
     * @return void
     */
    private function init_actions() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
    }

    /**
     * Register the admin menu.
     *
     * @return void
     */
    public function admin_menu() {
        add_submenu_page( 'tools.php', __( 'WC Vendors 2 Dokan Migrator', 'dokan-wcv' ), __( 'WC Vendors 2 Dokan Migrator', 'dokan-wcv' ), 'manage_options', 'dokan-wcv-migrator', array( $this, 'plugin_page' ) );
    }

    /**
     * Display the plugin page.
     *
     * @return void
     */
    public function plugin_page() {
        ?>
        <style type="text/css">
            .wcv-migrator-response span {
                color: #8a6d3b;
                background-color: #fcf8e3;
                border-color: #faebcc;
                padding: 15px;
                margin: 10px 0;
                border: 1px solid transparent;
                border-radius: 4px;
                display: block;
            }
            .wcv-migrator-loader {
                background: url('<?php echo admin_url('images/spinner-2x.gif') ?>') no-repeat;
                width: 20px;
                height: 20px;
                display: inline-block;
                background-size: cover;
            }
            #progressbar {
                background-color: #EEE;
                border-radius: 13px;
                padding: 3px;
                margin-bottom : 20px;
            }
            #wcv-migrator-progress {
                background-color: #00A0D2;
                width: 0%;
                height: 20px;
                border-radius: 10px;
                text-align: center;
                color:#FFF;
            }
        </style>

        <script type="text/javascript">
            jQuery( function ($) {
                var total_items = 0;

                $('form#dokan-wcv-migrator-form').on('submit', function(e) {
                    e.preventDefault();
                    var form = $(this),
                        submit = form.find('input[type=submit]'),
                        loader = form.find('.wcv-migrator-loader');
                        responseDiv = $('.wcv-migrator-response');

                    submit.attr('disabled', 'disabled');
                    loader.show();

                    var data = {
                        'action': 'dokan_wcv_migrate',
                        'type': form.find( "select[name=type]" ).val(),
                        'limit': form.find( "input[name=limit]" ).val(),
                        'total_items': form.find( "input[name=total_items]" ).val(),
                        'done': form.find( "input[name=done]" ).val(),
                        '_wpnonce': '<?php echo wp_create_nonce( "dokan-wcv-migrator" ); ?>'
                    };

                    $.post( ajaxurl, data, function( resp ) {
                        if ( resp.success ) {
                            total_items = resp.data.total_items;
                            completed = (resp.data.done*100) / total_items;

                            completed = Math.round(completed);

                            if (!$.isNumeric(completed)) {
                                $('#wcv-migrator-progress').width('100%');
                                $('#wcv-migrator-progress').html('Finished');
                            } else {
                                $('#wcv-migrator-progress').width(completed+'%');
                                $('#wcv-migrator-progress').html(completed+'%');
                            }

                            $('#progressbar').show();

                            responseDiv.html( '<span>' + resp.data.message + '</span>' );

                            if ( resp.data.done != 'All' ) {
                                form.find('input[name="total_items"]').val( resp.data.total_items );
                                form.find('input[name="done"]').val( resp.data.done );
                                form.submit();

                                return;
                            } else {
                                form.find('input[name="total_items"]').val( 0 );
                                form.find('input[name="done"]').val( 0 );
                                submit.removeAttr('disabled');
                                loader.hide();
                            }
                        }
                    });
                });

            });
        </script>

        <div class="wrap">
            <h2><?php _e( "WC Vendors 2 Dokan Data Migrator", "dokan-wcv" ); ?></h2>
            <p><?php _e( "This tool will migrate your <strong>WC Vendors</strong> data to <strong>Dokan</strong>.", "dokan-wcv" ); ?></p>

            <form action="" method="post" id="dokan-wcv-migrator-form">
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row"><?php _e( "Type", "dokan-wcv" ); ?></th>
                            <td>
                                <select name="type">
                                    <option value="vendor"><?php _e( "Vendor", "dokan-wcv" ); ?></option>
                                    <option value="order"><?php _e( "Order", "dokan-wcv" ); ?></option>
                                </select>
                                <p class="description"><?php _e( "Select a specific type to migrate.", "dokan-wcv" ); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e( "Limit", "dokan-wcv" ); ?></th>
                            <td>
                                <input type="number" name="limit" value="50">
                                <p class="description"><?php _e( "Amount of items to migrate per request.", "dokan-wcv" ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="wcv-migrator-response"></div>
                <div id="progressbar" style="display: none;">
                    <div id="wcv-migrator-progress">0</div>
                </div>

                <input type="hidden" name="total_items" value="0">
                <input type="hidden" name="done" value="0">
                <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Migrate', 'dokan-wcv' ); ?>" >
                <span class="wcv-migrator-loader" style="display:none"></span>
            </form>
        </div>
        <?php
    }

}

Dokan_WCV_Migrator::init();
