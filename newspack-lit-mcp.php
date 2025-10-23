<?php
/**
 * Plugin Name: Lit MCP
 * Description: MCP server for Newspack migration operations using WordPress Abilities API and MCP Adapter.
 * Version: 0.1
 * Author: Newspack
 * Requires Plugins: abilities-api, mcp-adapter
 * Requires PHP: 8.1
 * Text Domain: lit-mcp
 *
 * @package Newspack_Lit_MCP
 */

namespace Newspack\Lit_MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/vendor/autoload.php';

// Check dependencies
if ( ! function_exists( 'wp_register_ability' ) ) {
	add_action(
		'admin_notices',
		function() {
			wp_admin_notice(
				'Lit MCP requires the Abilities API plugin to be installed and activated.',
				'lit-mcp',
				[ 'type' => 'error' ]
			);
		}
	);
	return;
}

if ( ! class_exists( 'WP\MCP\Plugin' ) ) {
	// Try to load the MCP Adapter plugin manually
	if ( file_exists( WP_PLUGIN_DIR . '/mcp-adapter/mcp-adapter.php' ) ) {
		require_once WP_PLUGIN_DIR . '/mcp-adapter/mcp-adapter.php';
	}

	if ( ! class_exists( 'WP\MCP\Plugin' ) ) {
		add_action(
			'admin_notices',
			function() {
				wp_admin_notice(
					'Lit MCP requires the MCP Adapter plugin to be installed and activated.',
					'lit-mcp',
					[ 'type' => 'error' ]
				);
			}
		);
		return;
	}
}

// Initialize the plugin
new Lit_MCP();
