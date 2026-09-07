<?php
/**
 * Site Passport's MCP protocol adapter — the exact code that runs live at
 * https://sitepassport.org/.well-known/mcp.json.
 *
 * This file is the JSON-RPC/MCP handling layer only. It calls
 * `sp_normalize_target()` and `sp_run_checks()`, which live in Site
 * Passport's private scoring engine and are not included in this repo —
 * see README.md for why, and METHODOLOGY.md for exactly what those
 * functions check and how each check is weighted. The live endpoint above
 * is fully public and callable right now with no setup; this file exists
 * so you can see precisely how it speaks MCP.
 *
 * GET returns a small human/crawler-readable manifest. POST speaks
 * JSON-RPC 2.0 (initialize / tools/list / tools/call) over a single
 * stateless request-response — no session, no auth.
 */

declare(strict_types=1);

// require __DIR__ . '/lib/scan-core.php'; // private — provides
// sp_normalize_target() and sp_run_checks(), see README.md

header('Access-Control-Allow-Origin: *');

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

function sp_jsonrpc_result(mixed $id, array $result): never {
    header('Content-Type: application/json');
    echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    exit;
}

function sp_jsonrpc_error(mixed $id, int $code, string $message): never {
    header('Content-Type: application/json');
    echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    echo json_encode([
        'name'        => 'sitepassport',
        'description' => 'Site Passport — an independent, live-verified index of AI-agent readiness for WordPress and other sites.',
        'website'     => 'https://sitepassport.org',
        'protocol'    => 'mcp',
        'transport'   => 'streamable-http',
        'endpoint'    => 'https://sitepassport.org/.well-known/mcp.json',
        'note'        => 'POST JSON-RPC 2.0 to this same URL: initialize, tools/list, tools/call.',
        'tools'       => [sp_mcp_tool_schema()],
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($body)) {
    sp_jsonrpc_error(null, -32700, 'Parse error');
}

$id = $body['id'] ?? null;
$method = $body['method'] ?? '';

switch ($method) {
    case 'initialize':
        sp_jsonrpc_result($id, [
            'protocolVersion' => '2025-06-18',
            'serverInfo'      => ['name' => 'sitepassport', 'version' => '1.0.0'],
            'capabilities'    => ['tools' => new stdClass()],
        ]);

    case 'tools/list':
        sp_jsonrpc_result($id, ['tools' => [sp_mcp_tool_schema()]]);

    case 'tools/call':
        $params = $body['params'] ?? [];
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        if ($name !== SP_MCP_TOOL_NAME) {
            sp_jsonrpc_error($id, -32602, "Unknown tool: {$name}");
        }

        // sp_normalize_target() and sp_run_checks() are the private scoring
        // engine — see README.md. This is exactly what the live endpoint
        // does at this point; only the two function bodies are omitted here.
        $target = sp_normalize_target((string) ($args['url'] ?? ''));
        if ($target === null) {
            sp_jsonrpc_result($id, [
                'isError' => true,
                'content' => [['type' => 'text', 'text' => 'Please provide a real, public site URL.']],
            ]);
        }

        $result = sp_run_checks($target);
        if (!$result['reachable']) {
            sp_jsonrpc_result($id, [
                'isError' => true,
                'content' => [['type' => 'text', 'text' => "Couldn't reach {$target} to check it."]],
            ]);
        }

        sp_jsonrpc_result($id, [
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'url'           => $result['url'],
                    'partial_score' => $result['partial_score'],
                    'grade'         => $result['grade'],
                    'categories'    => $result['categories'],
                    'note'          => 'Score covers Discoverability, Bot Access, and Content only. Capability Exposure and Safety & Trust require Agent Builder installed on the target site.',
                ]),
            ]],
        ]);

    default:
        sp_jsonrpc_error($id, -32601, "Method not found: {$method}");
}
