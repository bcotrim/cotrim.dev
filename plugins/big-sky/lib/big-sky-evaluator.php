<?php

/**
 * Big Sky Evaluator
 *
 * This file can run in many context: on the local site or in an automated pipeline on WPCOM.
 * This class holds "tests" to run as evals and is synced to WPCOM.
 *
 * @package Big_Sky
 */

abstract class Big_Sky_Evaluator {

	public $payloads        = [];
	public $print           = true;
	public $errors          = [];
	public $warnings        = [];
	public $current_dataset = '';
	public $current_suite   = '';

	// For the tools that have changed names
	private $tool_name_map = [
		'router' => [
			'show_color_picker'      => 'change_assistant:big-sky-color',
			'show_font_picker'       => 'change_assistant:big-sky-font',
			'change_agent:big-sky-font' => 'change_assistant:big-sky-font',
			'change_agent:big-sky-color' => 'change_assistant:big-sky-color',
			'navigate_to_page'          => 'change_assistant:big-sky-page',
			'ask_a_wordpress_expert'    => 'change_assistant:big-sky-help',
			'help'                      => 'change_assistant:big-sky-help',
			'add_page'                  => 'change_assistant:big-sky-page',
			'add_pattern'               => 'change_assistant:big-sky-pattern',
		],
		'color'  => [],
	];

	/**
	 * Print the result of the test.
	 *
	 * @param string $message The message to print.
	 * @param mixed|bool|WP_Error $result The result of the test. WP_Error is used to indicate a failure.
	 */
	abstract public function print( $message, $result );

	/**
	 * Make a request to the OpenAI API.
	 *
	 * @param array $payload The payload to send to the OpenAI API.
	 * @return mixed The response from the OpenAI API.
	 */
	abstract public function request( $payload );

	/**
	 * Print the result of the test and return the result.
	 *
	 * @param string $message The message to print.
	 * @param mixed|bool|WP_Error $result The result of the test. WP_Error is used to indicate a failure.
	 * @return mixed The result of the request for further test cases.
	 */
	public function print_result_and_return( $message, $result = true ) {
		if ( is_wp_error( $result ) ) {
			$this->errors[] = $result;
		}

		if ( $result === E_USER_WARNING ) {
			$this->warnings[] = $message;
		}

		if ( $this->print ) {
			if ( $this->current_suite ) {
				$message = "[$this->current_suite] $message";
			}
			$this->print( $message, $result );
		}

		return $result;
	}

	/**
	 * Set the "system" payload from an encoded string
	 *
	 * @param string $payload The payload to set.
	 * @param string $assistant_id The identifier for the assistant ('router', 'color', etc.).
	 * @return array The payload.
	 */
	public function set_payload_from_string( $payload, $assistant_id = 'router' ) {
		// Sometimes its json-encoded multiple times.
		while ( is_string( $payload ) ) {
			try {
				$payload = json_decode( $payload, true );
			} catch ( Exception $e ) {
				break;
			}
		}

		// Clean up the fields used to store the payload in the evals.
		unset( $payload['store'] );
		unset( $payload['metadata'] );
		unset( $payload['feature'] );
		unset( $payload['session_id'] );
		unset( $payload['sessionId'] );
		unset( $payload['response_format'] ); // Our format is not in the same format as OpenAI. OpenAI demands object and we pass text.
		// Remove the user message from the payload so we an inject it in evals.
		$payload['messages'] = array_values(
			array_filter(
				$payload['messages'] ?? [],
				function ( $message ) {
					return $message['role'] !== 'user';
				}
			)
		);

		$this->payloads[ $assistant_id ] = $payload;
		return $payload;
	}

	/**
	 * Get a payload for a specific assistant
	 *
	 * @param string $assistant_id The identifier for the assistant.
	 * @return array|null The payload or null if not found.
	 */
	protected function get_payload( $assistant_id ) {
		return isset( $this->payloads[ $assistant_id ] ) ? $this->payloads[ $assistant_id ] : null;
	}

	private function replace_named_tags_with_context( $tag, &$input, $context ) {
		if ( ! is_string( $input ) ) {
			return false;
		}
		// Use non-greedy quantifier .*? to match content between tags
		$updated = preg_replace( '/(<' . $tag . '>).*?(<\/' . $tag . '>)/s', '$1' . $context . '$2', $input );
		if ( $updated !== null ) {
			$input = $updated;
		}
	}

	/**
	 * Test if the answer successfully called an indicated tool.
	 *
	 * @param string $query The query to test.
	 * @param string $tool_name The tool name it should call
	 * @param string $assistant_id The identifier for the assistant to use
	 * @return mixed|WP_Error The result of the test. WP_Error is used to indicate a failure.
	 */
	public function answer_should_call_tool( $query, $tool_name, $assistant_id = 'router', $additional_data = [] ) {
		// Some tools have changed names
		if ( isset( $this->tool_name_map[ $assistant_id ][ $tool_name ] ) ) {
			$tool_name = $this->tool_name_map[ $assistant_id ][ $tool_name ];
		}

		$this->current_test = [
			'query'        => $query,
			'tool_name'    => $tool_name,
			'assistant_id' => $assistant_id,
		];

		$previous_grade = $additional_data['previous_grade'] ?? 'OK';
		$message        = "'{$query}' should call '{$tool_name}'";

		// To allow the filter to be used to skip tests.

		if ( ! empty( $this->filter ) && 0 ) {
			// Check if filter is a tool filter
			if ( strpos( $this->filter, 'tool:' ) === 0 ) {
				$filtered_tool = substr( $this->filter, 5 );
				if ( $filtered_tool !== $tool_name ) {
					return false;
				}
			} elseif ( ! stristr( $message, $this->filter ) ) {
				return false;
			}
		}

		if ( empty( $query ) || empty( $tool_name ) ) {
			return false;
		}

		$payload = $this->get_payload( $assistant_id );
		if ( empty( $payload ) ) {
			return $this->print_result_and_return( $message, new WP_Error( 'evaluator_no_payload', "No payload found for assistant: {$assistant_id}" ) );
		}

		// override the context if it is provided
		if ( isset( $additional_data['context'] ) ) {
			// replace any named tags with the context overrides
			// example: anything within <selected_block_context>context</selected_block_context> will be replaced with the context overrides
			// build the context like so:
			// - key: value
			// - key: value
			// - etc.
			if ( is_array( $additional_data['context'] ) ) {
				foreach ( $additional_data['context'] as $tag => $values ) {
					$context = "\n";
					if ( is_array( $values ) ) {
						foreach ( $values as $key => $value ) {
							$context .= "- {$key}: {$value}\n";
						}
					} else {
						$context = $values;
					}
					$this->replace_named_tags_with_context( $tag, $payload['messages'][0]['content'], $context );
				}
			}
		}

		$payload['messages'][] = [
			'role'    => 'user',
			'content' => $query,
		];

		$data = $this->request( $payload );
		if ( is_wp_error( $data ) ) {
			return $this->print_result_and_return( 'Error calling OpenAI', $data );
		}

		if ( ! empty( $data['id'] ) ) {
			$this->current_test['openai_id'] = $data['id'];
		}

		if (
			! isset( $data['choices'][0]['message']['tool_calls'][0] ) &&
			$previous_grade === 'REPLY'
		) {
			$this->print_result_and_return( 'Expected Error:' . $message . ", got 'Reply:{$data['choices'][0]['message']['content']}'", E_USER_WARNING );
			return $data['choices'][0]['message'];
		} elseif ( ! isset( $data['choices'][0]['message']['tool_calls'][0] ) ) {
			return $this->print_result_and_return( $message, new WP_Error( 'evaluator_tool_not_called', 'Tool not called', $data['choices'][0]['message'] ) );
		}

		$tool_call        = $data['choices'][0]['message']['tool_calls'][0];
		$actual_tool_name = $tool_call['function']['name'];

		// Special case for change_assistant
		if ( $actual_tool_name === 'change_assistant' ) {
			$agent            = json_decode( $tool_call['function']['arguments'], true );
			$actual_tool_name = $actual_tool_name . ':' . ( $agent['toAssistantId'] ?? '' );
		}

		if ( $actual_tool_name !== $tool_name && $previous_grade === 'ERROR' ) {
			// Expected error.
			$this->print_result_and_return( 'Expected Error:' . $message . ", got '{$actual_tool_name}'", E_USER_WARNING );
			return $tool_call;
		} elseif ( $actual_tool_name !== $tool_name && $previous_grade === 'OK' ) {
			// Regression !
			return $this->print_result_and_return( $message . ", got '{$actual_tool_name}'", new WP_Error( 'evaluator_wrong_tool', 'Called wrong tool', $actual_tool_name, $tool_call ) );
		}

		return $this->print_result_and_return( ( $previous_grade === 'ERROR' ? 'IMPROVED: ' : '' ) . $message, $tool_call );
	}

	/**
	 * Take an array of queries and expected tool calls and run the tests.
	 *
	 * @param array $expected_tool_calls The expected tool calls.
	 * @param string $assistant_id The identifier for the assistant to use.
	 * @return void
	 */

	public function expect_tool_call( $expected_tool_calls, $assistant_id = 'router' ) {
		foreach ( $expected_tool_calls as $query => $tool_name ) {
			$this->answer_should_call_tool( $query, $tool_name, $assistant_id );
		}
	}

	/**
	 * Load test cases from a JSON file
	 *
	 * @param string $test_suite The name of the test suite (e.g. 'help-tool', 'start-over-tool')
	 * @return array|WP_Error The test cases array or WP_Error if file not found
	 */
	protected function load_test_cases( $test_suite ) {
		$file_path = __DIR__ . '/../test/evals/test-cases/' . $test_suite . '.json';
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'evaluator_no_test_cases', "No test cases found for suite: {$test_suite}" );
		}

		$json_content = file_get_contents( $file_path );
		if ( false === $json_content ) {
			return new WP_Error( 'evaluator_file_read_error', "Could not read test cases file: {$test_suite}" );
		}

		$test_cases = json_decode( $json_content, true );
		if ( null === $test_cases || ! isset( $test_cases['test_cases'] ) ) {
			return new WP_Error( 'evaluator_invalid_json', "Invalid test cases JSON for suite: {$test_suite}" );
		}

		return $test_cases['test_cases'];
	}

	protected function value_check( $args, $arg_name, $expected_value, $operator, $case_sensitive ) {
		if ( ! in_array( $operator, [ 'is_null' ] ) && ! isset( $args[ $arg_name ] ) ) {
			$this->print_result_and_return(
				"should include {$arg_name} in the arguments with value {$expected_value}",
				new WP_Error( 'evaluator_missing_value', 'Missing value', $arg_name )
			);
		}

		$passed = false;
		switch ( $operator ) {
			case 'equal':
				if ( $case_sensitive ) {
					$passed = $args[ $arg_name ] === $expected_value;
				} else {
					$passed = strtolower( $args[ $arg_name ] ) === strtolower( $expected_value );
				}
				break;
			case 'include':
				if ( is_array( $args[ $arg_name ] ) ) {
					$values         = $case_sensitive ? $args[ $arg_name ] : array_map( 'strtolower', $args[ $arg_name ] );
					$expected_value = $case_sensitive ? $expected_value : strtolower( $expected_value );
					$passed         = in_array( $expected_value, $values );
				} else {
					$value          = $case_sensitive ? $args[ $arg_name ] : strtolower( $args[ $arg_name ] );
					$expected_value = $case_sensitive ? $expected_value : strtolower( $expected_value );
					$passed         = $value === $expected_value;
				}
				break;
			case 'is_null':
				$passed = $args[ $arg_name ] === null;
				break;
		}

		if ( $passed ) {
			$this->print_result_and_return( "{$arg_name} is {$operator} {$expected_value}" );
		} else {
			$this->print_result_and_return(
				"{$arg_name} is {$operator} {$expected_value}",
				new WP_Error( 'evaluator_wrong_value', 'Wrong value', $arg_name )
			);
		}
	}

	private function normalize_args( &$args, $assistant_name ) {
		if ( $assistant_name === 'color' && isset( $args['custom_colors'] ) ) {
			$args['custom_colors'] = array_values(
				array_map(
					function ( $color ) {
						return $color['color'];
					},
					$args['custom_colors']
				)
			);
		}

		if ( $assistant_name === 'image' && isset( $args['description'] ) ) {
			// Normalize descriptions for comparison (lowercase, remove extra spaces)
			$args['description'] = strtolower( trim( preg_replace( '/\s+/', ' ', $args['description'] ) ) );
		}
	}

	private function run_assistant_tests( $assistant_name ) {
		$this->current_suite = "{$assistant_name}_assistant";
		$test_cases          = $this->load_test_cases( "{$assistant_name}-assistant" );
		if ( is_wp_error( $test_cases ) ) {
			return $this->print_result_and_return( "Failed to load {$assistant_name} test cases", $test_cases );
		}

		foreach ( $test_cases as $test_case ) {
			$tool_result = $this->answer_should_call_tool(
				$test_case['query'],
				$test_case['expected_tool'],
				$test_case['assistant_id'] ?? $assistant_name,
				[
					'previous_grade' => $test_case['previous_grade'] ?? 'OK',
					'context'        => $test_case['context'] ?? [],
				]
			);

			// Skip further validation if the tool call failed
			if ( is_wp_error( $tool_result ) || $tool_result === false ) {
				continue;
			}

			// Handle special validations
			if ( isset( $test_case['validation'] ) ) {
				$args = json_decode( $tool_result['function']['arguments'], true );

				// normalize args based on the assistant name
				$this->normalize_args( $args, $assistant_name );

				// loop through each validation and run the check
				foreach ( $test_case['validation'] as $validation ) {
					$this->value_check( $args, $validation['arg'], $validation['value'] ?? null, $validation['operator'] ?? 'equal', $validation['case_sensitive'] ?? false );
				}
			}
		}
	}

	/**
	 * Run all the tests.
	 *
	 * @param string|null $assistant_id Optional. The specific assistant to run tests for. If null, runs tests for all available assistants.
	 * @return void|WP_Error Returns WP_Error if no payloads are available.
	 */
	public function evaluate( $assistant_id = null ) {
		if ( empty( $this->payloads ) ) {
			return new WP_Error( 'evaluator_no_payload', 'No payloads found' );
		}

		// If no specific assistant is specified, run tests for all available assistants
		if ( $assistant_id === null ) {
			foreach ( array_keys( $this->payloads ) as $current_assistant ) {
				$this->evaluate( $current_assistant );
			}
			return;
		}

		$this->current_dataset = 'Big_Sky_Evaluator';

		// Skip if the requested assistant's payload is not available
		if ( empty( $this->get_payload( $assistant_id ) ) ) {
			$this->print_result_and_return(
				"[{$assistant_id}] Skipping tests - no payload found",
				new WP_Error( 'evaluator_no_payload', "No payload found for assistant: {$assistant_id}" )
			);
			return;
		}

		// Run the appropriate tests based on the assistant type
		switch ( $assistant_id ) {
			case 'router':
				$this->run_assistant_tests( 'router' );
				break;

			case 'color':
				$this->run_assistant_tests( 'color' );
				break;

			case 'font':
				$this->run_assistant_tests( 'font' );
				break;

			case 'page':
				$this->run_assistant_tests( 'page' );
				break;

			case 'pattern':
				$this->run_assistant_tests( 'pattern' );
				break;

			case 'image':
				$this->run_assistant_tests( 'image' );
				break;

			case 'layout':
				$this->run_assistant_tests( 'layout' );
				break;

			case 'help':
				$this->run_assistant_tests( 'help' );
				break;

			default:
				$this->print_result_and_return(
					"[{$assistant_id}] No specific tests defined for this assistant",
					new WP_Error( 'evaluator_no_tests', "No tests defined for assistant: {$assistant_id}" )
				);
				break;
		}
	}

	public function evaluate_csv( string $file_path, $assistant_id = 'router' ) {
		if ( 'default' === $file_path ) {
			$file_path = __DIR__ . '/../test/evals/mocks/site-design-evals.csv';
		}
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'evaluator_no_payload', 'No payload found' );
		}

		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			return new \WP_Error( 'evaluator_no_payload', 'No payload found' );
		}

		// Skip header row
		fgetcsv( $handle );
		$this->current_dataset = basename( $file_path );
		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			$tool_name           = $data[2];
			$this->current_suite = $this->current_dataset . ':' . $tool_name;
			$this->answer_should_call_tool(
				$data[1],
				$tool_name,
				$assistant_id,
				[
					//'previous_grade' => $data[3], // I am unsure on how to treat this.
					'evaluation_id' => $data[0],
				]
			);
		}

		fclose( $handle );
	}


	/**
	 * Load all available payload files from the mocks directory.
	 */
	public function load_all_available_payloads( $mocks_dir ): void {

		// Always load the default site-design payload first
		$default_payload = file_get_contents( $mocks_dir . '/site-design-payload.json' );
		if ( ! empty( $default_payload ) ) {
			$this->set_payload_from_string( $default_payload, 'default' );
		}

		// Then load any additional assistant payloads
		$files = glob( $mocks_dir . '/*-assistant-payload.json' );
		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				$assistant_id = basename( $file, '-assistant-payload.json' );
				$payload      = file_get_contents( $file );
				if ( ! empty( $payload ) ) {
					$this->set_payload_from_string( $payload, $assistant_id );
				}
			}
		}
	}
}
