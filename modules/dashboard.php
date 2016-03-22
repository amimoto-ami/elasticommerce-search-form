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
		add_action( 'admin_print_scripts-dashboard_page_ecss-dash', array( $this, 'admin_print_scripts' ) );
		add_action( 'admin_footer', array( $this, 'admin_footer' ) );
		add_action( 'admin_head-dashboard_page_ecss-dash', array( $this, 'admin_head' ) );
	}

	public function admin_head() {
?>
<style>
    text {
        font: 12px sans-serif;
    }
    svg {
        display: block;
    }
    html, body, #chart1, svg {
        margin: 0px;
        padding: 0px;
        height: 530px;
        width: 100%;
    }
</style>
<?php
	}
	
	public function admin_menu() {
		add_dashboard_page( __( 'Elasticommerce Searvice' ), __( 'Elasticommerce Searvice' ), 'manage_options', 'ecss-dash', array($this, 'dashboard') );
	}
	
	public function dashboard() {
		$options = get_option( 'wpels_settings' );
		
		if ( !isset( $options['visual_service_endpoint'] ) || empty($options['visual_service_endpoint']) ) {
			return;
		}

		if ( isset( $_POST['start_date'] ) ) {
			$start_date = (int) str_replace( '/', '', wp_unslash( $_POST['start_date'] ) );
		} else {
			$start_date = (int) date_i18n( 'Ymd', strtotime("-1 month")  );
		}

		if ( isset( $_POST['end_date'] ) ) {
			$end_date = (int) str_replace( '/', '', wp_unslash( $_POST['end_date'] ) );
		} else {
			$end_date = (int) date_i18n( 'Ymd');
		}

		$response = wp_remote_get($options['visual_service_endpoint'] . '?start_date='.$start_date.'000000&end_date='.$end_date.'000000');
		if( !is_wp_error( $response ) && $response["response"]["code"] === 200 ) {
    		$response_body = json_decode($response["body"]);
    		$items = array();
    		foreach( $response_body->Items as $val ) {
    			$items[$val->SearchWord] += $val->TotalValue;
    		}
    		arsort($items);
    		
    		$json_chart = array();
    		foreach ( $items as $key => $item ) {
    			$json_chart[] = array(
    								'label' => $key,
    								'value' => $item
    								);
    		} 
		}
		
		
?>
<div class="wrap">
<?php screen_icon(); ?>

<h2><?php _e( 'Elasticommerce Search Service' ); ?></h2>
<h3>検索ワードごとの売上高</h3>
<form action="<?php echo self_link(); ?>" method="POST">
<table class="form-table">
<tr><th><?php _e( 'Display Period' ); ?></th><td><input type="text" name="start_date" value="<?php echo esc_attr($start_date); ?>"> - <input type="text" name="end_date" value="<?php echo esc_attr($end_date); ?>"> <input type="submit" value="Apply" class="button" id="submit" name="submit"></td></tr>
</table>
</form>
<div id="chart1">
    <svg></svg>
</div>

<script>
    var results = <?php echo json_encode($json_chart); ?>;
    
    
console.log(results);
    historicalBarChart = [
        {
            key: "Cumulative Return",
            values: results
        }
    ];
    nv.addGraph(function() {
        var chart = nv.models.discreteBarChart()
            .x(function(d) { return d.label })
            .y(function(d) { return d.value })
            .staggerLabels(true)
            //.staggerLabels(historicalBarChart[0].values.length > 8)
            .showValues(true)
            .duration(250)
            ;
        chart.xAxis
            .axisLabel("検索ワード");

        chart.yAxis
            .axisLabel('売上 (円)')
            .tickFormat(d3.format(','));

        //chart.showXAxis(true);
        d3.select('#chart1 svg')
            .datum(historicalBarChart)
            .call(chart);
        nv.utils.windowResize(chart.update);
        return chart;
    });
</script>
</div>
<?php
	}
	
	public function admin_print_scripts() {
		wp_enqueue_script( 'd3', '//d3js.org/d3.v3.min.js', array(), '1.0', false );
		wp_enqueue_script( 'nv.d3.js', plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ) .'/../js/nv.d3.min.js', array('d3'), '1.0', false );
		wp_enqueue_style( 'nv.d3.css', plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ) .'/../css/nv.d3.min.css', array(), '1.0', false );
		wp_enqueue_script( 'my-datepicker', plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ) .'/../js/datepicker.js', array('jquery-ui-datepicker'), '1.0', false );
		wp_enqueue_style( 'jquery-ui-datepicker-theme', plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ) .'/../css/jquery-ui.css', array(), '1.0' );
	}

	public function admin_footer() {
	}
}
Elasticommerce_Search_Form_Dashboard::get_instance()->init();