<?php
/**
 * Stdio MCP entrypoint used only by this repo's Dockerfile, so automated
 * registry checks (e.g. Glama) can start the server in a container and get
 * a real `initialize` / `tools/list` response without any network access.
 *
 * It speaks the exact same tool schema as the live HTTP endpoint at
 * https://sitepassport.org/.well-known/mcp.json (see server.php). It does
 * NOT implement `tools/call` for real — that requires Site Passport's
 * private scoring engine (`sp_normalize_target()` / `sp_run_checks()`,
 * see README.md), which is deliberately not in this public repo. A
 * `tools/call` here returns a pointer to the live endpoint instead of a
 * fabricated result.
 */

declare(strict_types=1);

const SP_MCP_TOOL_NAME = 'check_wordpress_agent_readiness';
const SP_MCP_TOOL_DESCRIPTION = 'Checks whether a WordPress site (or any website) is ready to be safely operated by AI agents: llms.txt, AI-crawler directives in robots.txt, schema.org markup, and a WebMCP capability manifest. Live-checked against the site itself, never self-reported.';

function sp_mcp_tool_schema(): array {
    return [
        'name'        => SP_MCP_TOOL_NAME,
        'description' => SP_MCP_TOOL_DESCRIPTION,
        'inputSchema' => [
            'type'       => 'object',
            'properties' => [
                'url' => [
                    'type'        => 'string',
                    'description' => 'The site URL to check, e.g. https://example.com',
                ],
            ],
            'required' => ['url'],
        ],
    ];
}

function sp_respond(array $message): void {
    fwrite(STDOUT, json_encode($message) . "\n");
    fflush(STDOUT);
}

$stdin = fopen('php://stdin', 'r');
if ($stdin === false) {
    exit(1);
}

while (($line = fgets($stdin)) !== false) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }

    $body = json_decode($line, true);
    if (!is_array($body)) {
        sp_respond(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']]);
        continue;
    }

    $id = $body['id'] ?? null;
    $method = $body['method'] ?? '';

    switch ($method) {
        case 'initialize':
            sp_respond([
                'jsonrpc' => '2.0',
                'id'      => $id,
                'result'  => [
                    'protocolVersion' => '2025-06-18',
                    'serverInfo'      => ['name' => 'sitepassport', 'version' => '1.0.0'],
                    'capabilities'    => ['tools' => new stdClass()],
                ],
            ]);
            break;

        case 'tools/list':
            sp_respond(['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => [sp_mcp_tool_schema()]]]);
            break;

        case 'tools/call':
            sp_respond([
                'jsonrpc' => '2.0',
                'id'      => $id,
                'result'  => [
                    'isError' => true,
                    'content' => [[
                        'type' => 'text',
                        'text' => 'This container image only answers MCP introspection (initialize/tools/list) for registry checks. For a real scan, call the live endpoint: https://sitepassport.org/.well-known/mcp.json',
                    ]],
                ],
            ]);
            break;

        default:
            sp_respond(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32601, 'message' => "Method not found: {$method}"]]);
    }
}
