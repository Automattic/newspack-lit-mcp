# Newspack Lit MCP

Newspack Launch and Infrastructure MCP server plugin is a WordPress MCP server for migration operations built using the Abilities API and MCP Adapter. This plugin demonstrates how to create an MCP server that exposes WordPress abilities as MCP tools.

## Key Features

- **Two MCP Tools Available**:
  - `lit-mcp-read-json-file` - Reads JSON files from `wp-content/uploads/mcp_data/`
  - `lit-mcp-write-json-file` - Writes JSON content to files in the `wp-content/uploads/mcp_data/` directory
- **Modern WordPress Architecture**:
  - Uses Abilities API for ability registration and management
  - Uses MCP Adapter for MCP server functionality
  - REST transport with proper JSON-RPC handling
  - Error handling and observability
- **Security**:
  - Basic HTTP authentication support
  - WordPress user capability checking (`edit_posts` required)
  - Proper permission validation
- **Data Management**:
  - Files stored in `wp-content/uploads/mcp_data/` directory
  - Automatic directory creation
  - JSON validation and pretty-printing

## Requirements

- WordPress 6.8+
- PHP 7.4+
- [Abilities API](https://github.com/WordPress/abilities-api) plugin (v0.3.0+)
- [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin

## Installation

1. **Install Dependencies** (via WP-CLI):
   ```bash
   wp plugin install https://github.com/WordPress/abilities-api/releases/latest/download/abilities-api.zip --activate
   wp plugin install https://github.com/WordPress/mcp-adapter/releases/latest/download/mcp-adapter.zip --activate
   ```

2. **Install Lit MCP**:
   ```bash
   # Clone or copy the plugin to your plugins directory
   wp plugin activate lit-mcp
   ```

3. **Install Composer Dependencies**:
   ```bash
   cd /path/to/wordpress/wp-content/plugins/lit-mcp
   composer install
   ```

## Usage

There are two ways to use this MCP server:

### Option 1: Command-Line MCP Server (Recommended for MCP Clients)

The `mcp-command.php` script provides a stdio-based MCP server that works with Claude Desktop and other MCP clients.

**Test the command-line server:**

```bash
# Test initialization
echo '{"jsonrpc":"2.0","method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"test","version":"1.0.0"}},"id":1}' | \
php /usr/local/var/www/newspack-ai.test/public/wp-content/plugins/newspack-lit-mcp/mcp-command.php | jq .

# Test tools list
echo '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":2}' | \
php /usr/local/var/www/newspack-ai.test/public/wp-content/plugins/newspack-lit-mcp/mcp-command.php | jq .

# Test writing a file
echo '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"lit-mcp-write-json-file","arguments":{"filename":"test.json","content":{"hello":"world"}}},"id":3}' | \
php /usr/local/var/www/newspack-ai.test/public/wp-content/plugins/newspack-lit-mcp/mcp-command.php | jq .

# Test reading a file
echo '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"lit-mcp-read-json-file","arguments":{"filename":"test.json"}},"id":4}' | \
php /usr/local/var/www/newspack-ai.test/public/wp-content/plugins/newspack-lit-mcp/mcp-command.php | jq .
```

**Multi-request test:**

```bash
cat > /tmp/test_mcp.txt << 'EOF'
{"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"test","version":"1.0.0"}},"jsonrpc":"2.0","id":0}
{"method":"notifications/initialized","jsonrpc":"2.0"}
{"method":"tools/list","params":{},"jsonrpc":"2.0","id":1}
{"method":"prompts/list","params":{},"jsonrpc":"2.0","id":2}
{"method":"resources/list","params":{},"jsonrpc":"2.0","id":3}
EOF

php /usr/local/var/www/newspack-ai.test/public/wp-content/plugins/newspack-lit-mcp/mcp-command.php < /tmp/test_mcp.txt | jq .
```

### Option 2: Direct HTTP REST API

You can also interact with the MCP server directly via HTTP requests to the WordPress REST API endpoint.
This bypasses the command-line server and goes directly to the WordPress REST API endpoint.

**Initialize:**
```bash
curl -k -u admin:pass \
-H "Content-Type: application/json" \
-H "Accept: application/json, text/event-stream" \
-d '{"jsonrpc":"2.0","method":"initialize","params":{},"id":1}' \
https://newspack-ai.test/wp-json/lit-mcp/jsonrpc/streamable | \
jq .
```

**List Tools:**
```bash
curl -k -u admin:pass \
-H "Content-Type: application/json" \
-H "Accept: application/json, text/event-stream" \
-d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":2}' \
https://newspack-ai.test/wp-json/lit-mcp/jsonrpc/streamable | \
jq .
```

**Write JSON File:**
```bash
curl -k -u admin:pass \
-H "Content-Type: application/json" \
-H "Accept: application/json, text/event-stream" \
-d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"lit-mcp-write-json-file","arguments":{"filename":"test.json","content":{"message":"Hello World"}}},"id":3}' \
https://newspack-ai.test/wp-json/lit-mcp/jsonrpc/streamable | \
jq .
```

**Read JSON File:**
```bash
curl -k -u admin:pass \
-H "Content-Type: application/json" \
-H "Accept: application/json, text/event-stream" \
-d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"lit-mcp-read-json-file","arguments":{"filename":"test.json"}},"id":4}' \
https://newspack-ai.test/wp-json/lit-mcp/jsonrpc/streamable | \
jq .
```

## MCP Configuration

Add this configuration to your Claude Desktop MCP settings file (`~/Library/Application Support/Claude/claude_desktop_config.json` on macOS):

**Note:** Update the path to `mcp-command.php` to match your installation directory.

### For Cursor IDE

Add this configuration to your `.cursor/mcp.json` file:


```json
{
  "mcpServers": {
    "newspack-lit-mcp": {
      "command": "php",
      "args": [
        "/usr/local/var/www/newspack-ai.test/public/wp-content/plugins/newspack-lit-mcp/mcp-command.php"
      ]
    }
  }
}
```

## Troubleshooting

- **Connection timeouts**:
   - Verify WordPress site is running and accessible
   - Check the URL in `mcp-command.php` matches your site
   - Ensure credentials (username/password) are correct in `mcp-command.php`

- **"HTTP request failed" errors**:
   - Check if WordPress is returning non-200 status codes
   - Verify Basic Auth credentials in `mcp-command.php`
   - Test the REST API endpoint directly with curl

- **401 Unauthorized Error**:
   - Ensure the WordPress user has `edit_posts` capability
   - Check that Basic Auth credentials are correct
   - Verify the user exists and password is correct

- **404 Not Found Error**:
   - Ensure the plugin is activated
   - Check that Abilities API and MCP Adapter plugins are active
   - Verify the URL path is correct (`/wp-json/lit-mcp/jsonrpc/streamable`)

- **500 Internal Server Error**:
   - Check WordPress debug logs for specific error messages
   - Ensure the `wp-content/uploads/mcp_data/` directory is writable
   - Verify all dependencies are properly installed

### Debug Information

- Check WordPress debug logs: `/wp-content/debug.log`
- Verify plugin activation: `wp plugin list`
- Test abilities registration: Check if abilities appear in WordPress admin
- Test command-line server manually: Use the test commands in the Usage section above
- Check Claude Desktop logs: `~/Library/Logs/Claude/mcp*.log` on macOS

## Technical Details

### Architecture

- **WordPress Plugin**: Registers abilities via Abilities API and exposes them through MCP Adapter
- **Command-Line Wrapper**: `mcp-command.php` is a minimal stdio wrapper that:
  - Reads JSON-RPC messages from stdin (one per line)
  - Forwards them to WordPress REST API via HTTP
  - Returns responses to stdout (one per line)
  - Filters out empty responses from notifications
- **Data Directory**: Files are stored in `wp-content/uploads/mcp_data/`
- **File Format**: JSON files with pretty-printing
- **Authentication**: Basic HTTP Auth with WordPress user validation
- **Permissions**: Requires `edit_posts` WordPress capability
- **Transport**: StreamableTransport (JSON-RPC 2.0 over HTTP with stdio wrapper for MCP clients)

### mcp-command.php Implementation

The command wrapper is intentionally minimal:
- **No WordPress bootstrap**: Avoids loading WordPress directly to prevent output pollution
- **Clean stdio protocol**: Only JSON responses go to stdout, nothing else
- **Error suppression**: Disables all PHP errors/warnings/notices to maintain clean output
- **HTTP proxy pattern**: Forwards all protocol logic to WordPress REST API
- **Notification handling**: Filters out empty responses from JSON-RPC notifications