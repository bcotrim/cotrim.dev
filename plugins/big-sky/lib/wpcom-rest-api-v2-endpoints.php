<?php
declare( strict_types = 1 );

require_once __DIR__ . '/wpcom-big-sky-logger.php';

/**
 * These endpoints also get registered on WordPress.com simple.
 */
class WPCOM_REST_API_V2_Endpoint_Big_Sky_Plugin extends WP_REST_Controller {

	private $logger;
	private $anthropic_api_key;

	public function __construct( $logger = null ) {
		$this->wpcom_is_wpcom_only_endpoint = true;
		$this->logger                       = $logger ?? new WPCOM_Big_Sky_Logger();
		$this->anthropic_api_key            = $this->load_anthropic_api_key();
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	private function load_anthropic_api_key() {
		$env_file = dirname( __DIR__ ) . '/.env-anthropic';
		if ( file_exists( $env_file ) ) {
			$key = trim( file_get_contents( $env_file ) );
			if ( ! empty( $key ) ) {
				return $key;
			}
		}
		return null;
	}

	public function register_routes() {
		// register POST big-sky/v1/log/session
		register_rest_route(
			'wpcom/v2',
			'big-sky/v1/session/(?P<session_uuid>[^\/]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'show_in_index'       => false,
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
				'callback'            => [ $this, 'log_session' ],
				'args'                => [
					'name'    => [
						'required' => true,
					],
					'date'    => [
						'required' => false,
					],
					'is_test' => [
						'default' => false,
					],
				],
			)
		);

		// register POST big-sky/v1/sessions/(?P<session_uuid>[^\/]+)/metadata
		register_rest_route(
			'wpcom/v2',
			'big-sky/v1/session/(?P<session_uuid>[^\/]+)/metadata',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'show_in_index'       => false,
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
				'callback'            => [ $this, 'log_metadata' ],
			)
		);

		// register POST big-sky/v1/log/action
		register_rest_route(
			'wpcom/v2',
			'big-sky/v1/session/(?P<session_uuid>[^\/]+)/action',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'show_in_index'       => false,
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
				'callback'            => [ $this, 'log_action' ],
				'args'                => [
					'message_type'      => [
						'required' => true,
					],
					'message_id'        => [
						'required' => true,
					],
					'parent_message_id' => [
						'default' => null,
					],
					'content'           => [
						'required' => true,
					],
					'date'              => [
						'required' => false,
					],
					'is_test'           => [
						'required' => false,
						'default'  => false,
					],
				],
			)
		);

		// register GET big-sky/v1/geo-coordinates
		register_rest_route(
			'wpcom/v2',
			'big-sky/v1/geo-coordinates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'show_in_index'       => false,
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
				'callback'            => [ $this, 'get_mapbox_geo_coordinates' ],
			)
		);

		// register POST big-sky/v1/dummy-posts
		register_rest_route(
			'wpcom/v2',
			'big-sky/v1/dummy-posts',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'show_in_index'       => false,
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
				'callback'            => [ $this, 'create_dummy_posts' ],
				'args'                => [
					'posts' => [
						'required' => true,
					],
				],
			)
		);
		// register DELETE big-sky/v1/dummy-posts
		register_rest_route(
			'wpcom/v2',
			'big-sky/v1/dummy-posts',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'show_in_index'       => false,
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
				'callback'            => [ $this, 'delete_dummy_posts' ],
			)
		);

		// register GET big-sky/v1/variation-templates/colors
		register_rest_route(
			'wpcom/v2',
			'big-sky/v1/variation-templates/colors',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'show_in_index'       => false,
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
				'callback'            => [ $this, 'get_variation_templates_colors' ],
			)
		);

		// register POST big-sky/v1/anthropic/messages
		register_rest_route(
			'wpcom/v2',
			'big-sky/v1/anthropic/messages',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'show_in_index'       => false,
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
				'callback'            => [ $this, 'proxy_anthropic_request' ],
			)
		);
	}

	public function create_dummy_posts( WP_REST_Request $request ) {
		$posts = $request->get_param( 'posts' );

		foreach ( $posts as $post ) {
			// check if the post category exists
			$category = get_term_by( 'name', $post['category'], 'category' );
			if ( $category && ! is_wp_error( $category ) ) {
					$category_id = $category->term_id;
			} else {
					// create the category
					$term        = wp_insert_term( $post['category'], 'category' );
					$category_id = $term['term_id'];
			}

			$post_id = wp_insert_post(
				array(
					'post_title'    => $post['title'],
					'post_content'  => $post['content'],
					'post_status'   => 'private',
					'post_author'   => get_current_user_id(),
					'post_category' => [ $category_id ],
					'tags_input'    => [ 'dummy-post' ],
				)
			);

			// download image and create attachment
			$image_url = $post['featured_image'];
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attachment_id = media_sideload_image( $image_url, $post_id, '', 'id' );
			if ( ! is_wp_error( $attachment_id ) ) {
				// set as featured image
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		return true;
	}

	public function delete_dummy_posts() {
		$posts = get_posts(
			array(
				'tag'         => 'dummy-post',
				'post_status' => 'private', // only delete private dummy posts
				'numberposts' => -1,
			)
		);

		// get all unique categories from the posts
		$categories = [];
		foreach ( $posts as $post ) {
			$categories = array_merge( $categories, wp_get_post_categories( $post->ID ) );
		}
		$categories = array_unique( $categories );
		// delete all categories
		foreach ( $categories as $category_id ) {
			wp_delete_term( $category_id, 'category' );
		}

		// delete all posts
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}

		return true;
	}

	public function log_session( WP_REST_Request $request ) {
		$session_uuid = $request->get_param( 'session_uuid' );
		$name         = $request->get_param( 'name' );
		$date         = $request->get_param( 'date' );
		$is_test      = $request->get_param( 'is_test' );

		if ( ! $date ) {
			$date = current_time( 'mysql', true );
		}

		return $this->logger->log_session( $session_uuid, $name, $date, $is_test );
	}

	public function log_action( WP_REST_Request $request ) {
		$session_uuid      = $request->get_param( 'session_uuid' );
		$message_role      = $request->get_param( 'message_type' );
		$message_id        = $request->get_param( 'message_id' );
		$content           = $request->get_param( 'content' );
		$date              = $request->get_param( 'date' );
		$parent_message_id = $request->get_param( 'parent_message_id' );
		$is_test           = $request->get_param( 'is_test' );

		// default date to current GMT datetime
		if ( ! $date ) {
			$date = current_time( 'mysql', true );
		}

		return $this->logger->log_action( $session_uuid, $message_role, $message_id, $content, $date, $parent_message_id, $is_test );
	}

	public function log_metadata( WP_REST_Request $request ) {
		$session_uuid = $request->get_param( 'session_uuid' );
		$metadata     = $request->get_json_params();

		return $this->logger->log_metadata( $session_uuid, $metadata );
	}

	public function get_mapbox_geo_coordinates( WP_REST_Request $request ) {
		$address = $request->get_param( 'address' );

		if ( ! $address ) {
			return new WP_Error( 'missing_address', 'Address is required', array( 'status' => 400 ) );
		}
		$mapbox_token = Jetpack_Mapbox_Helper::get_access_token();
		$api_key      = $mapbox_token['key'];
		if ( ! $api_key ) {
			return new WP_Error( 'missing_api_key', 'Mapbox API key is required', array( 'status' => 400 ) );
		}
		$api_response = wp_remote_get(
			'https://api.mapbox.com/search/geocode/v6/forward?access_token=' . $api_key . '&q=' . urlencode( $address )
		);

		return new WP_REST_Response( json_decode( wp_remote_retrieve_body( $api_response ), true ) );
	}

	public function get_variation_templates_colors() {
		$partials = WP_Theme_JSON_Resolver_Gutenberg::get_style_variations( 'block' );
		gutenberg_register_block_style_variations_from_theme_json_partials( $partials );
		$variations = WP_Theme_JSON_Resolver_Gutenberg::get_style_variations_from_directory( plugin_dir_path( __FILE__ ) . '/theme-variation-templates' );
		return new WP_REST_Response( $variations );
	}

	public function proxy_anthropic_request( WP_REST_Request $request ) {
		if ( ! $this->anthropic_api_key ) {
			return new WP_Error(
				'missing_api_key',
				'Anthropic API key not configured',
				array( 'status' => 500 )
			);
		}

		$body   = $request->get_json_params();
		$stream = isset( $body['stream'] ) && $body['stream'];

		if ( $stream ) {
			// Set headers for streaming response
			header( 'Content-Type: text/event-stream' );
			header( 'Cache-Control: no-cache' );
			header( 'Connection: keep-alive' );
			header( 'X-Accel-Buffering: no' ); // Disable nginx buffering

			// Make streaming request to Anthropic
			$curl = curl_init();
			curl_setopt_array(
				$curl,
				array(
					CURLOPT_URL            => 'https://api.anthropic.com/v1/messages',
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_WRITEFUNCTION  => function ( $curl, $data ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE data must be passed through unmodified
						echo $data;
						flush();
						ob_flush();
						return strlen( $data );
					},
					CURLOPT_HTTPHEADER     => array(
						'Content-Type: application/json',
						'Accept: text/event-stream',
						'x-api-key: ' . $this->anthropic_api_key,
						'anthropic-version: 2023-06-01',
					),
					CURLOPT_POST           => true,
					CURLOPT_POSTFIELDS     => wp_json_encode( array_merge( $body, array( 'stream' => true ) ) ),
					CURLOPT_TIMEOUT        => 0, // No timeout for streaming
				)
			);

			curl_exec( $curl );
			$error = curl_error( $curl );
			curl_close( $curl );

			if ( $error ) {
				error_log( 'Anthropic streaming error: ' . $error );
			}
			exit;
		}

		// Non-streaming request
		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'headers' => array(
					'content-type'      => 'application/json',
					'x-api-key'         => $this->anthropic_api_key,
					'anthropic-version' => '2023-06-01',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'anthropic_error',
				'Error calling Anthropic API: ' . $response->get_error_message(),
				array( 'status' => 500 )
			);
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		return new WP_REST_Response( $response_body );
	}
}
