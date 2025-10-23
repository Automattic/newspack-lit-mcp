<?php
/**
 * Main Lit MCP plugin class.
 *
 * @package Newspack\Lit_MCP
 */

namespace Newspack\Lit_MCP;

/**
 * Main Lit MCP plugin class.
 */
class Lit_MCP {

	/**
	 * Data directory for JSON files.
	 *
	 * @var string The path to the data directory.
	 */
	private string $data_dir;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$uploads        = wp_upload_dir();
		$this->data_dir = trailingslashit( $uploads['basedir'] ) . 'mcp_data/';
		
		add_action( 'abilities_api_categories_init', [ $this, 'register_categories' ] );
		add_action( 'abilities_api_init', [ $this, 'register_abilities' ] );
		add_action( 'mcp_adapter_init', [ $this, 'register_mcp_server' ] );
	}

	/**
	 * Register categories for abilities.
	 */
	public function register_categories(): void {
		wp_register_ability_category(
			'lit-mcp-data',
			[
				'label'       => __( 'Lit MCP Data', 'lit-mcp' ),
				'description' => __( 'Lit MCP data operations', 'lit-mcp' ),
			] 
		);
	}

	/**
	 * Register abilities for JSON file operations.
	 */
	public function register_abilities(): void {
		// Register read_json_file ability
		wp_register_ability(
			'lit-mcp/read-json-file',
			[
				'label'               => __( 'Read JSON File', 'lit-mcp' ),
				'description'         => __( 'Reads the content of a JSON file from the local mcp_data directory.', 'lit-mcp' ),
				'category'            => 'lit-mcp-data',
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'filename' => [
							'type'        => 'string',
							'description' => __( 'The filename without extension.', 'lit-mcp' ),
							'minLength'   => 1,
						],
					],
					'required'             => [ 'filename' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'        => 'object',
					'description' => __( 'The JSON content as an object.', 'lit-mcp' ),
				],
				'execute_callback'    => [ $this, 'read_json_file' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'meta'                => [
					'annotations' => [
						'readonly'    => true,
						'destructive' => false,
					],
				],
			] 
		);

		// Register write_json_file ability
		wp_register_ability(
			'lit-mcp/write-json-file',
			[
				'label'               => __( 'Write JSON File', 'lit-mcp' ),
				'description'         => __( 'Writes content to a JSON file in the local mcp_data directory. Overwrites if exists.', 'lit-mcp' ),
				'category'            => 'lit-mcp-data',
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'filename' => [
							'type'        => 'string',
							'description' => __( 'The filename without extension.', 'lit-mcp' ),
							'minLength'   => 1,
						],
						'content'  => [
							'type'        => 'object',
							'description' => __( 'The JSON content to write.', 'lit-mcp' ),
						],
					],
					'required'             => [ 'filename', 'content' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'message'   => [
							'type'        => 'string',
							'description' => __( 'Success message.', 'lit-mcp' ),
						],
						'filename'  => [
							'type'        => 'string',
							'description' => __( 'The filename that was written.', 'lit-mcp' ),
						],
						'file_path' => [
							'type'        => 'string',
							'description' => __( 'The full path to the written file.', 'lit-mcp' ),
						],
					],
					'required'   => [ 'message' ],
				],
				'execute_callback'    => [ $this, 'write_json_file' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => false,
					],
				],
			] 
		);
	}

	/**
	 * Register MCP server.
	 *
	 * @param \WP\MCP\Core\McpAdapter $adapter The MCP adapter instance.
	 */
	public function register_mcp_server( \WP\MCP\Core\McpAdapter $adapter ): void {
		$adapter->create_server(
			'lit-mcp-server',                                    // Server ID
			'lit-mcp',                                          // Namespace
			'jsonrpc',                                          // Route
			'Lit MCP Server',                                   // Server name
			'MCP server for JSON file operations',             // Description
			'1.0.0',                                           // Version
			[ \WP\MCP\Transport\Http\StreamableTransport::class ],   // Transport
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class, // Error handler
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class, // Observability
			[ 'lit-mcp/read-json-file', 'lit-mcp/write-json-file' ], // Tools
			[],                                                 // Resources
			[],                                                 // Prompts
			[ $this, 'check_transport_permission' ]             // Transport permission callback
		);
	}

	/**
	 * Read JSON file ability callback.
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error The JSON content or error.
	 */
	public function read_json_file( array $input ): array|\WP_Error {
		$filename = basename( sanitize_file_name( $input['filename'] ) );
		if ( empty( $filename ) ) {
			return new \WP_Error( 'invalid_filename', __( 'Invalid filename.', 'lit-mcp' ) );
		}

		$file_path = $this->data_dir . $filename;
		
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'File not found.', 'lit-mcp' ) );
		}

		$content = file_get_contents( $file_path ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		if ( false === $content ) {
			return new \WP_Error( 'read_failed', __( 'Failed to read file.', 'lit-mcp' ) );
		}

		$json_data = json_decode( $content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'invalid_json', __( 'Invalid JSON content.', 'lit-mcp' ) );
		}

		return $json_data;
	}

	/**
	 * Write JSON file ability callback.
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error Success message or error.
	 */
	public function write_json_file( array $input ): array|\WP_Error {
		$filename = basename( sanitize_file_name( $input['filename'] ) );
		if ( empty( $filename ) ) {
			return new \WP_Error( 'invalid_filename', __( 'Invalid filename.', 'lit-mcp' ) );
		}

		if ( ! isset( $input['content'] ) || ! is_array( $input['content'] ) ) {
			return new \WP_Error( 'invalid_content', __( 'Invalid content.', 'lit-mcp' ) );
		}

		$file_path = $this->data_dir . $filename;
		
		// Create directory if it doesn't exist
		if ( ! file_exists( dirname( $this->data_dir ) ) ) {
			wp_mkdir_p( dirname( $this->data_dir ) );
		}

		$json_content = wp_json_encode( $input['content'], JSON_PRETTY_PRINT );
		if ( false === $json_content ) {
			return new \WP_Error( 'json_encode_failed', __( 'Failed to encode JSON.', 'lit-mcp' ) );
		}

		$result = file_put_contents( $file_path, $json_content ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		if ( false === $result ) {
			return new \WP_Error( 'write_failed', __( 'Failed to write file.', 'lit-mcp' ) );
		}

		return [
			'message'   => __( 'File written successfully.', 'lit-mcp' ),
			'filename'  => $filename,
			'file_path' => $file_path,
		];
	}

	/**
	 * Check permission for abilities.
	 *
	 * @param array $input The input parameters.
	 * @return bool|\WP_Error True if permission granted, false or error otherwise.
	 */
	public function check_permission( array $input ): bool|\WP_Error {
		// Check if user is logged in and has edit_posts capability
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		return new \WP_Error( 'permission_denied', __( 'Insufficient permissions.', 'lit-mcp' ) );
	}

	/**
	 * Check transport permission for MCP server.
	 *
	 * @return bool|\WP_Error True if permission granted, false or error otherwise.
	 */
	public function check_transport_permission(): bool|\WP_Error {
		// Check for basic HTTP authentication
		$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $auth_header ) || ! str_starts_with( $auth_header, 'Basic ' ) ) {
			return new \WP_Error( 'permission_denied', __( 'Authentication required.', 'lit-mcp' ) );
		}

		$credentials = $this->parse_basic_auth( $auth_header );
		if ( false === $credentials ) {
			return new \WP_Error( 'invalid_auth', __( 'Invalid authentication.', 'lit-mcp' ) );
		}

		$user = wp_authenticate( $credentials['username'], $credentials['password'] );
		if ( is_wp_error( $user ) ) {
			return new \WP_Error( 'auth_failed', __( 'Authentication failed.', 'lit-mcp' ) );
		}

		if ( user_can( $user, 'edit_posts' ) ) {
			wp_set_current_user( $user->ID );
			return true;
		}

		return new \WP_Error( 'permission_denied', __( 'Insufficient permissions.', 'lit-mcp' ) );
	}

	/**
	 * Parse and sanitize basic authentication header.
	 *
	 * @param string $auth_header The authorization header.
	 * @return array|false Array with keys 'username' and 'password'. False on failure.
	 */
	private function parse_basic_auth( string $auth_header ): array|false {
		$auth_header = trim( $auth_header );
		$parts       = explode( ' ', $auth_header );

		if ( 2 !== count( $parts ) || 'Basic' !== $parts[0] ) {
			return false;
		}

		$credentials = base64_decode( $parts[1], true );
		if ( false === $credentials ) {
			return false;
		}

		$parts = explode( ':', $credentials );
		if ( 2 !== count( $parts ) ) {
			return false;
		}

		return [
			'username' => sanitize_user( $parts[0] ),
			'password' => $parts[1], // Don't sanitize password
		];
	}
}
