<?php
require_once __DIR__ . '/big-sky-evaluator.php';
require_lib( 'openai' );

class WPCOM_Big_Sky_Evaluator extends Big_Sky_Evaluator {
	private $openai;
	private $run_id;
	private $version;

	public function __construct() {
		$this->openai = new OpenAI( 'big-sky-evals' );
		$this->run_id = gmdate( 'Y-m-d H:i:s' );
		// This gets the version from the plugin header. We should start updating it.
		$plugin_data   = get_plugin_data( __DIR__ . '/../big-sky.php' );
		$this->version = $plugin_data['Version'];
	}

	public function print( $message, $result = true ) {
		$this->print_result_and_return( $message, $result );
	}

	public function request( $payload ): mixed {
		$response = $this->openai->api_request( 'https://api.openai.com/v1/chat/completions', $payload );
		$data     = json_decode( $response['body'], true );
		return $data;
	}

	public function print_result_and_return( $message, $result = true ) {
		$logstash_payload = [
			'es_retention'    => '1w', // TODO: Change to 6m later once we are done testing.
			'feature'         => 'big-sky-eval',
			'plugin'          => 'big-sky-evals',
			'message'         => $message,
			'properties'      => $this->current_test,
			// Reusing existing fields from log2logstash.php
			'tests'           => $this->run_id,
			'file'            => $this->current_dataset,
			'method'          => $this->current_suite,
			'jetpack_version' => $this->version,
			'tags'            => [],
		];

		if ( defined( 'WPCOM_SANDBOXED' ) && WPCOM_SANDBOXED ) {
			$logstash_payload['tags'][] = 'sandbox';
		} else {
			$logstash_payload['tags'][] = 'production';
		}

		if ( is_wp_error( $result ) ) {
			$logstash_payload['error_code'] = $result->get_error_message();
			$logstash_payload['success']    = 'ERROR';
			$logstash_payload['extra']      = wp_json_encode( $result->get_error_data(), JSON_PRETTY_PRINT );
		} else {
			$logstash_payload['extra']   = wp_json_encode( $result, JSON_PRETTY_PRINT );
			$logstash_payload['success'] = 'OK';
		}

		log2logstash( $logstash_payload );
		return $result;
	}

	public function run_all_tests_in_jobs() {
		$mocks_dir = WP_CONTENT_DIR . '/plugins/big-sky-plugin/mocks';
		$this->load_all_available_payloads( $mocks_dir );
		// Hardcoded tests
		$this->evaluate();
		// Evaluate all available CSV files
		$this->evaluate_csv( $mocks_dir . '/site-design-evals.csv', 'default' );
	}
}
