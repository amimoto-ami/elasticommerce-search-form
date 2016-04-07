<?php
/**
 * Plugin Name: Elasticommerce Search Form
 * Version: 1.1
 * Description: Search Form using Elasticsearch
 * Author: horike
 * Author URI: https://amimoto-ami.com/
 * Plugin URI: https://amimoto-ami.com/
 * Text Domain: elasticommerce-search-form
 * Domain Path: /languages
 * @package Elasticommerce-search-form
 */

if ( ! escs_is_activate_woocommerce() ) {
	$ESCS_Err = new ESCS_Error();
	$msg = array(
		__( 'Elasticommerce Need "WooCommerce" Plugin.' , 'elasticommerce-search-form' ),
		__( 'Please Activate it.' , 'elasticommerce-search-form' ),
	);
	$e = new WP_Error( 'Elasticommerce Activation Error', $msg );
	$ESCS_Err->show_error_message( $e );
	add_action( 'admin_notices', array( $ESCS_Err, 'admin_notices' ) );
	return;
}
require_once dirname( __FILE__ ) . '/vendor/autoload.php';
require_once dirname( __FILE__ ) . '/modules/dashboard.php';
MegumiTeam\WooCommerceElasticsearch\Loader::get_instance()->init();

use Elastica\Query;
use Elastica\Query\QueryString;

class Elasticommerce_Search_Form {

	private static $instance;
	private function __construct() {}

	/**
	 * Return a singleton instance of the current class
	 *
	 * @since 1.0
	 * @return object
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			$c = __CLASS__;
			self::$instance = new $c();
		}
		return self::$instance;
	}

	/**
	 * Initialize.
	 *
	 * @since 1.0
	 */
	public function init() {
		add_filter( 'wpels_search', array( $this, 'search' ) );
		add_filter( 'posts_search', array( $this, 'posts_search' ), 10, 2);
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		add_action( 'wp_head', array( $this, 'set_cookie_search_query' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'woocommerce_thankyou' ) );
		add_action( 'wpels_inner_setting_form', array( $this, 'wpels_inner_setting_form' ) );
	}
	
	public function wpels_inner_setting_form() {
		$options = get_option( 'wpels_settings' );
		
		?>
		<table class="form-table">
		<tr>
		<th scope="row">Visual Service Endpoint</th>
		<td><input type='text' name='wpels_settings[visual_service_endpoint]' value='<?php echo $options['visual_service_endpoint']; ?>'></td>
		</tr>
		</table>
		<?php
	}

	/**
	 * woocommerce_thankyou action hook. Set product code.
	 *
	 * @since 1.1
	 * @return void
	 */
	public function woocommerce_thankyou( $order_id ) {
		$options = get_option( 'wpels_settings' );
		
		if ( !isset( $options['visual_service_endpoint'] ) || empty($options['visual_service_endpoint']) ) {
			return;
		}

		$order = wc_get_order($order_id);
		$productinfo = array();
		foreach( $order->get_items() as $item_id => $item ) {
			$productinfo[] = array(
								'product_id' => intval($item['product_id']),
								'count'      => intval(wc_get_order_item_meta($item_id, '_qty'))
								);
		}
		
?>
<script type="text/javascript">
	jQuery(document).ready(function($){
		var wc_esf_total_value = '<?php echo $order->calculate_totals(); ?>';

		if ( $.cookie('elasticommerce_search_query') && wc_esf_total_value != '' ) {
		    var data = { 
		              searchword:$.cookie('elasticommerce_search_query'),
		              productinfo:<?php echo json_encode($productinfo); ?>,
		              totalvalue:wc_esf_total_value
		              }
			$.ajax( {
			    url:'<?php echo trim(esc_url($options['visual_service_endpoint'])); ?>',
			    method: "POST",
			    crossDomain: true,
			    data: JSON.stringify(data),
			    success: function(response) {
			      $.removeCookie('elasticommerce_search_query');
			    }
			  });
			
		}	
	});
</script>
<?php
	}

	/**
	 * wp head action hook. Set cookie search word
	 *
	 * @since 1.1
	 * @return void
	 */
	public function set_cookie_search_query() {
		if ( !is_search() ) {
			return;
		}
?>
<script type="text/javascript">
	jQuery(document).ready(function($){
		var COOKIE_NAME = 'elasticommerce_search_query';
		var COOKIE_PATH = '/';
		var search_word = '<?php the_search_query(); ?>';

		var date = new Date();
		date.setTime(date.getTime() + ( 1000 * 60 * 60 ));
		$.cookie(COOKIE_NAME, search_word, { path: COOKIE_PATH, expires: date });
	});
</script>
<?php
	}

	/**
	 * wp_enqueue_scripts action hook. Set jQuery cookie
	 *
	 * @since 1.1
	 * @return void
	 */
	public function wp_enqueue_scripts() {
		wp_enqueue_script('jquery');
		wp_register_script('jquery-cookie', plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ) .'/js/jquery.cookie.js', array('jquery'), '1.0');
		wp_enqueue_script('jquery-cookie');
	}

	/**
	 * posts_search filter hook.
	 *
	 * @param $search, $wp_query
	 * @return String
	 * @since 1.0
	 */
	public function posts_search( $search, $wp_query ) {
		global $wpdb;

		$options = get_option( 'wpels_settings' );
		if ( !is_admin() && $wp_query->is_search && $options !== false ) {
			$search_query = get_search_query();
			$post_ids = apply_filters( 'wpels_search', $search_query );

			if ( !empty( $post_ids ) && is_array( $post_ids ) ) {
				$search = 'AND '.$wpdb->posts.'.ID IN (';
				$search .= implode(',',$post_ids);
				$search .= ')';
			}
		}

		return $search;
	}

	/**
	 * search query to Elasticsearch.
	 *
	 * @param $search_query
	 * @return true or WP_Error object
	 * @since 1.0
	 */
	public function search( $search_query ) {
		try {
			
			$es = MegumiTeam\WooCommerceElasticsearch\Loader::get_instance();
			$type = $es->client->getIndex( $es->index )->getType( $es->type );
			$qs = new QueryString();
			$qs->setQuery( $search_query );
			$query_es = Query::create( $qs );
			$resultSet = $type->search( $query_es );
			$post_ids = array();
			foreach ( $resultSet as $r ) {
				$post_ids[] = $r->getID();
			}

			return $post_ids;
		} catch (Exception $e) {
			$err = new WP_Error( 'Elasticsearch Search Error', $e->getMessage() );
			return $err;
		}
	}
}
Elasticommerce_Search_Form::get_instance()->init();


class ESCS_Error {
	public function admin_notices() {
		if ( $messageList = get_transient( 'escs-admin-errors' ) ) {
			$this->show_notice_html( $messageList );
		}
	}

	public function show_error_message( $msg ) {
		if ( ! is_wp_error( $msg ) ) {
			$e = new WP_Error();
			$e->add( 'error' , $msg , 'escs-admin-errors' );
		} else {
			$e = $msg;
		}
		set_transient( 'escs-admin-errors' , $e->get_error_messages(), 10 );
	}

	public function show_notice_html( $messageList ) {
		foreach ( $messageList as $key => $messages ) {
			$html  = "<div class='error'><ul>";
			foreach ( (array)$messages as $key => $message ) {
				$html .= "<li>{$message}</li>";
			}
			$html .= '</ul></div>';
		}
		echo $html;
	}
}
function escs_is_activate_woocommerce() {
	$activePlugins = get_option('active_plugins');
	$plugin = 'woocommerce/woocommerce.php';
	if ( ! array_search( $plugin, $activePlugins ) && file_exists( WP_PLUGIN_DIR. '/'. $plugin ) ) {
		return false;
	} else {
		return true;
	}
}
