<?php
class Elasticommerce_Search_Form_Dashboard {

	private $metric = array();
	private static $instance;
	private function __construct() {}

	/**
	 * Return a singleton instance of the current class
	 *
	 * @since 1.1
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
	 * @since 1.1
	 */
	public function init() {
		add_filter( 'admin_menu', array( $this, 'admin_menu' ) );
		add_filter( 'admin_print_scripts', array( $this, 'admin_print_scripts' ) );
		add_action( 'admin_footer', array( $this, 'admin_footer' ) );
	}
	
	public function admin_menu() {
		add_dashboard_page( __( 'Elasticommerce Searvice' ), __( 'Elasticommerce Searvice' ), 'manage_options', 'ecss-dash', array($this, 'dashboard') );
	}
	
	public function dashboard() {
?>
<div class="wrap">
<?php screen_icon(); ?>

<h2><?php _e( '検索ワードごとの売上' ); ?></h2>

<div id="elasticommerce" style="height: 400px;"></div>
</div>
<?php
	}
	
	public function admin_print_scripts() {
		wp_enqueue_style('morris_css', '//cdnjs.cloudflare.com/ajax/libs/morris.js/0.5.1/morris.css');
		wp_enqueue_script('raphael_js', '//cdnjs.cloudflare.com/ajax/libs/raphael/2.1.0/raphael-min.js', false, '1.0');
		wp_enqueue_script('morris_js', '//cdnjs.cloudflare.com/ajax/libs/morris.js/0.5.1/morris.min.js', false, '1.0');
	}
	
	private function _cmp($a, $b){
		if ($a['total_value'] == $b['total_value']) { return 0; }
		else return ($a['total_value'] < $b['total_value']) ? -1 : 1;
	}
	
	public function admin_footer() {
		$response = wp_remote_get('https://example.com');
		if( !is_wp_error( $response ) && $response["response"]["code"] === 200 ) {
    		$response_body = json_decode($response["body"]);
    		
    		$items = array();
    		foreach( $response_body->Items as $val ) {
    			$items[$val->search_word] = $val->total_value;
    		}
    		arsort($items);
		}
?>
<script>
new Morris.Bar({
  // ID of the element in which to draw the chart.
  element: 'elasticommerce',
  // Chart data records -- each entry in this array corresponds to a point on
  // the chart.
  data: [
  <?php foreach( $items as $key => $val ): ?>
    { post_id: '<?php echo $key; ?>', value: <?php echo esc_js(intval($val)); ?> },
  <?php endforeach; ?>
  ],
  // The name of the data record attribute that contains x-values.
  xkey: 'post_id',
  // A list of names of data record attributes that contain y-values.
  ykeys: ['value'],
  // Labels for the ykeys -- will be displayed when you hover over the
  // chart.
  labels: ['売上'],
});
</script>
<?php
	}
}
Elasticommerce_Search_Form_Dashboard::get_instance()->init();
