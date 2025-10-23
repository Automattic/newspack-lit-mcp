#!/usr/bin/env php
<?php
/**
 * Lit MCP Command-line MCP Server - Minimal stdio wrapper
 *
 * This script forwards JSON-RPC requests from stdin to the WordPress REST API
 * and returns responses to stdout.
 */

// Suppress all output except our JSON responses.
error_reporting( 0 );
ini_set( 'display_errors', '0' );
ini_set( 'log_errors', '0' );

// Disable output buffering and ensure clean output.
while ( ob_get_level() ) {
	ob_end_clean();
}
ob_implicit_flush( true );

// TODO -- promote to environment variables.
$url      = 'http://newspack-ai.test/wp-json/lit-mcp/jsonrpc/streamable';
$username = 'admin';
$password = 'pass';

// Redirect stderr to /dev/null to prevent any error output mixing with JSON
if ( function_exists( 'stream_set_blocking' ) ) {
	fclose( STDERR );
	$STDERR = fopen( 'php://stderr', 'w' );
}

// Main stdio loop.
while ( $line = fgets( STDIN ) ) {
	$line = trim( $line );
	if ( empty( $line ) ) {
		continue;
	}

	// Forward request to WordPress REST API.
	$ch = curl_init( $url );
	curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $line );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_USERPWD, $username . ':' . $password );
	curl_setopt(
		$ch,
		CURLOPT_HTTPHEADER,
		[
			'Content-Type: application/json',
			'Accept: application/json, text/event-stream',
			'Content-Length: ' . strlen( $line ),
		]
	);

	$response  = curl_exec( $ch );
	$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );

	if ( $response === false || $http_code !== 200 ) {
		$error = [
			'jsonrpc' => '2.0',
			'id'      => json_decode( $line, true )['id'] ?? null,
			'error'   => [
				'code'    => -32603,
				'message' => 'HTTP request failed',
			],
		];
		fwrite( STDOUT, json_encode( $error ) . "\n" );
	} else {
		// Only output non-empty responses (notifications return empty).
		if ( ! empty( $response ) && trim( $response ) !== '' ) {
			fwrite( STDOUT, $response . "\n" );
		}
	}
	fflush( STDOUT );
}
