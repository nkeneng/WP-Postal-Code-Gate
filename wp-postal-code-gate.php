<?php
/**
 * Plugin Name:       WP Postal Code Gate
 * Plugin URI:        https://stevennkeneng.com
 * Description:       Vérifie le code postal du visiteur avant de lui donner accès au shop WooCommerce.
 * Version:           1.0.0
 * Author:            Steven Nkeneng
 * Author URI:        https://stevennkeneng.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-postal-code-gate
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Requires PHP:      7.0
 * WC requires at least: 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="error"><p>' . __( 'Le plugin "WP Postal Code Gate" nécessite l\'activation de WooCommerce.', 'wp-postal-code-gate' ) . '</p></div>';
    });
    return;
}

class WP_Postal_Code_Gate {

    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_footer', [ $this, 'output_popup_html' ] );
        add_action( 'wp_ajax_nopriv_pcg_check_postcode', [ $this, 'ajax_check_postcode' ] );
        add_action( 'wp_ajax_pcg_check_postcode', [ $this, 'ajax_check_postcode' ] );
    }

    public function enqueue_scripts() {
        if ( is_admin() || wp_doing_ajax() || isset($_COOKIE['pcg_postcode_validated']) ) {
            return;
        }

        wp_enqueue_style( 'pcg-style', plugin_dir_url( __FILE__ ) . 'pcg-style.css', [], '1.0.0' );
        wp_enqueue_script( 'pcg-script', plugin_dir_url( __FILE__ ) . 'pcg-script.js', [ 'jquery' ], '1.0.0', true );

        wp_localize_script( 'pcg-script', 'pcg_vars', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pcg_check_postcode_nonce' ),
            'error_message_invalid' => __( 'Désolé, nous ne livrons pas dans cette zone.', 'wp-postal-code-gate' ),
            'error_message_empty' => __( 'Veuillez entrer un code postal.', 'wp-postal-code-gate' ),
        ]);
    }

    public function output_popup_html() {
        if ( is_admin() || wp_doing_ajax() || isset($_COOKIE['pcg_postcode_validated']) ) {
            return;
        }

        ?>
        <div id="pcg-popup-overlay" style="display: none;">
            <div id="pcg-popup-content">
                <h2><?php _e( 'Vérifier la livraison dans votre zone', 'wp-postal-code-gate' ); ?></h2>
                <p><?php _e( 'Entrez votre code postal pour continuer :', 'wp-postal-code-gate' ); ?></p>
                <form id="pcg-form">
                    <input type="text" id="pcg-postcode" name="pcg_postcode" placeholder="<?php _e( 'Code Postal', 'wp-postal-code-gate' ); ?>" required>
                    <button type="submit" id="pcg-submit"><?php _e( 'Vérifier', 'wp-postal-code-gate' ); ?></button>
                </form>
                <div id="pcg-message" style="display: none; color: red; margin-top: 10px;"></div>
                <div id="pcg-loading" style="display: none; margin-top: 10px;"><?php _e( 'Vérification...', 'wp-postal-code-gate' ); ?></div>
            </div>
        </div>
        <?php
    }

    public function ajax_check_postcode() {
        check_ajax_referer( 'pcg_check_postcode_nonce', 'nonce' );

        if ( ! isset( $_POST['postcode'] ) || empty( trim( $_POST['postcode'] ) ) ) {
            wp_send_json_error( [ 'message' => __( 'Code postal manquant.', 'wp-postal-code-gate' ) ] );
            return;
        }

        $postcode = sanitize_text_field( wp_unslash( $_POST['postcode'] ) );
        $postcode_formatted = wc_format_postcode( $postcode, 'DE');

        $is_deliverable = $this->is_postcode_in_shipping_zones( $postcode_formatted );

        if ( $is_deliverable ) {
            setcookie( 'pcg_postcode_validated', 'yes', time() + 3600, COOKIEPATH, COOKIE_DOMAIN );
            wp_send_json_success();
        } else {
            wp_send_json_error( [ 
                'message' => __( 'Non livrable.', 'wp-postal-code-gate' )
            ]);
        }
    }

    private function get_all_postcode_locations() {
        if ( ! class_exists('WC_Shipping_Zone') || ! class_exists('WC_Data_Store') || ! function_exists('WC') ) {
            return ['error' => 'WooCommerce components missing'];
        }

        $all_postcode_locations = [];
        $data_store = WC_Data_Store::load( 'shipping-zone' );
        $zone_ids = $data_store->get_zones();
        $zone_ids[] = 0;

        foreach ( $zone_ids as $zone_id ) {
            try {
                $shipping_zone = new WC_Shipping_Zone( $zone_id );
                $zone_name = $shipping_zone->get_zone_name();
                $locations = $shipping_zone->get_zone_locations();

                if ( ! empty( $locations ) ) {
                    foreach ( $locations as $location ) {
                        if ( isset($location->type) && 'postcode' === $location->type && isset($location->code) ) {
                            $all_postcode_locations[] = [
                                'zone_id' => $zone_id,
                                'zone_name' => $zone_name,
                                'rule' => (string) $location->code,
                            ];
                        }
                    }
                }
            } catch (Exception $e) {
                $all_postcode_locations[] = [
                    'error' => 'Error loading zone ' . (is_scalar($zone_id) ? $zone_id : 'unknown') . ': ' . $e->getMessage()
                ];
                continue;
            }
        }

        return $all_postcode_locations;
    }

    private function is_postcode_in_shipping_zones( $postcode_to_check ) {
        if ( empty( $postcode_to_check ) ) {
            return false;
        }

        if ( ! class_exists('WC_Shipping_Zone') || ! class_exists('WC_Data_Store') || ! function_exists('wc_postcode_location_matcher') || ! function_exists('WC') || ! WC()->countries ) {
             error_log('WP Postal Code Gate Error: Core WC components missing for postcode check.');
             return false;
        }

        $all_postcode_locations = [];
        $data_store             = WC_Data_Store::load( 'shipping-zone' );
        $zone_ids               = $data_store->get_zones();
        $zone_ids[]             = 0;

        foreach ( $zone_ids as $zone_id ) {
             try {
                $shipping_zone = new WC_Shipping_Zone( $zone_id );
                $locations     = $shipping_zone->get_zone_locations();

                if ( ! empty( $locations ) ) {
                    foreach ( $locations as $location ) {
                        if ( isset($location->type) && 'postcode' === $location->type && isset($location->code) ) {
                             $location_object = [
                                'zone_id' => $zone_id,
                                'rule'    => (string) $location->code,
                            ];
                            $all_postcode_locations[] = $location_object;
                        }
                    }
                }
             } catch (Exception $e) {
                 error_log('WP Postal Code Gate Error loading shipping zone ID ' . (is_scalar($zone_id) ? $zone_id : 'unknown') . ' for postcode check: ' . $e->getMessage());
                 continue;
             }
        }

        if ( empty( $all_postcode_locations ) ) {
            return false;
        }

        $country = WC()->countries->get_base_country();

        $matches = wc_postcode_location_matcher(
            $postcode_to_check,
            $all_postcode_locations,
            'zone_id',
            'rule',
            $country
        );

        error_log('WP Postal Code Gate: Checking postcode "' . $postcode_to_check . '"');
        
        if (empty($matches)) {
            error_log('WP Postal Code Gate: No matches from wc_postcode_location_matcher, trying direct comparison');
            
            // Fallback: perform direct string comparison if WooCommerce matcher failed
            foreach ($all_postcode_locations as $location) {
                if (isset($location['rule']) && trim($location['rule']) === trim($postcode_to_check)) {
                    error_log('WP Postal Code Gate: Direct match found for ' . $postcode_to_check);
                    return true; // Exact match found
                }
            }
            
            error_log('WP Postal Code Gate: No direct matches found either');
            return false; // No matches found in any comparison method
        }
        
        error_log('WP Postal Code Gate: Match found by wc_postcode_location_matcher');
        return true; // WooCommerce matcher found a valid location
    }
}

// Initialize the plugin singleton
WP_Postal_Code_Gate::get_instance();