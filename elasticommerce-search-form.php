<?php
/**
 * Plugin Name: Elasticommerce Search Form
 * Version: 0.1
 * Description: Search Form using Elasticsearch
 * Author: horike
 * Author URI: https://amimoto-ami.com/
 * Plugin URI: https://amimoto-ami.com/
 * Text Domain: elasticommerce-search-form
 * Domain Path: /languages
 * @package Elasticommerce-search-form
 */

require_once dirname( __FILE__ ) . '/vendor/autoload.php';
MegumiTeam\WooCommerceElasticsearch\Loader::get_instance()->init();

//use Elastica\Client;
use Elastica\Query;
use Elastica\Query\QueryString;

class Elasticommerce_Search_Form {

	private static $instance;
	private function __construct() {}

	/**
	 * Return a singleton instance of the current class
	 *
	 * @since 0.1
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
	 * @since 0.1
	 */
	public function init() {
		//add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_filter( 'wpels_search', array( $this, 'search' ) );
		add_filter( 'posts_search', array( $this, 'posts_search' ), 10, 2);
	}
	
	public function posts_search( $search, $wp_query ) {

		if ( $wp_query->is_search ) {
			$search_query = get_search_query();
			$post_ids = apply_filters( 'wpels_search', $search_query );
			
			if ( !empty( $post_ids ) && is_array( $post_ids ) ) {
				$search = 'AND wp_posts.ID IN (';
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
	 * @since 0.1
	 */
	public function search( $search_query ) {
		try {
			
			$client = MegumiTeam\WooCommerceElasticsearch\Loader::get_instance()->get_client();

			if ( ! $client ) {
				throw new Exception( 'Couldn\'t make Elasticsearch Client. Parameter is not enough.' );
			}
			$url = parse_url(home_url());
			$type = $client->getIndex( $url['host'] )->getType( 'product' );
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
