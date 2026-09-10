# Site Passport — MCP server

The protocol layer behind [sitepassport.org](https://sitepassport.org)'s live, callable MCP server — an independent, live-verified index of AI-agent readiness for WordPress and other sites.

**Live endpoint:** `https://sitepassport.org/.well-known/mcp.json`
Fully public, no auth, no API key. `GET` returns a human/crawler-readable manifest; `POST` speaks JSON-RPC 2.0 (`initialize`, `tools/list`, `tools/call`) over a single stateless request — no session required.

## Connect it

No API key, no signup, nothing to configure — that's the whole pitch.

**Claude Code:**

```bash
claude mcp add --transport http sitepassport https://sitepassport.org/.well-known/mcp.json
```

**Cursor** (`~/.cursor/mcp.json` or a project's `.cursor/mcp.json`):

```json
{
  "mcpServers": {
    "sitepassport": {
      "url": "https://sitepassport.org/.well-known/mcp.json"
    }
  }
}
```

**VS Code** (`.vscode/mcp.json`):

```json
{
  "servers": {
    "sitepassport": {
      "type": "http",
      "url": "https://sitepassport.org/.well-known/mcp.json"
    }
  }
}
```

**Any other client** that supports a remote Streamable HTTP MCP server: point it at the URL above — no headers, no credentials.

## What's in this repo, and what isn't

This repo contains the **protocol adapter** — JSON-RPC request/response handling and the tool schema — the same shape used by other hosted-API MCP servers you may already be pointing agents at (Stripe's [`agent-toolkit`](https://github.com/stripe/agent-toolkit), Perplexity's [`modelcontextprotocol`](https://github.com/ppl-ai/modelcontextprotocol), Sentry's [`sentry-mcp`](https://github.com/getsentry/sentry-mcp)): the public repo is the adapter, not the proprietary engine behind it.

`server.php` in this repo calls `sp_normalize_target()` and `sp_run_checks()` — Site Passport's actual scoring engine, which runs against our own private codebase and isn't included here. That's deliberate, for the same reason none of the examples above publish their core business logic: the live endpoint is fully public and callable right now with zero setup, and the exact scoring implementation staying private keeps `robots.txt`/schema/manifest checks meaningful rather than something a site can trivially game once the precise logic is known.

What you get from this repo:
- The exact MCP protocol shape Site Passport speaks — useful if you're building your own MCP client or debugging a call against the live endpoint.
- The tool schema for `check_wordpress_agent_readiness`, verbatim.
- [`METHODOLOGY.md`](./METHODOLOGY.md) — every check Site Passport runs, its category, and its weight, kept in sync with [sitepassport.org/methodology](https://sitepassport.org/methodology). This is the actual audit surface: what's checked and why, versioned and public, even though the check *implementation* isn't.

## Docker

This repo also ships a `Dockerfile` for registry checkers (e.g. Glama) that need to start the server in a container and confirm it answers MCP introspection — it is not how you'd actually use Site Passport day to day; use the live HTTP endpoint above for that.

```bash
docker build -t sitepassport-mcp .
printf '%s\n%s\n' \
  '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}' \
  '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}' \
  | docker run -i --rm sitepassport-mcp
```

It speaks stdio, newline-delimited JSON-RPC, and answers `initialize` / `tools/list` with the real tool schema. `tools/call` isn't implemented in the container — it needs the private scoring engine described above — and returns a pointer back to the live endpoint instead.

## The one tool

**`check_wordpress_agent_readiness`** — checks whether a WordPress site (or any website) is ready to be safely operated by AI agents: `llms.txt`, AI-crawler directives in `robots.txt`, schema.org markup, and a WebMCP capability manifest. Live-checked against the site itself, never self-reported.

```json
{
  "name": "check_wordpress_agent_readiness",
  "description": "Checks whether a WordPress site (or any website) is ready to be safely operated by AI agents: llms.txt, AI-crawler directives in robots.txt, schema.org markup, and a WebMCP capability manifest. Live-checked against the site itself, never self-reported.",
  "inputSchema": {
    "type": "object",
    "properties": {
      "url": { "type": "string", "description": "The site URL to check, e.g. https://example.com" }
    },
    "required": ["url"]
  }
}
```

Two of the seven categories in the full methodology — Capability Exposure and Safety & Trust — require the target site to actually expose agent tools behind an approval gate (today, that means running [Agent Builder](https://agentic-plugin.com) or an equivalent). Those two can't be checked externally, so `check_wordpress_agent_readiness` covers the five that can: Discoverability, Bot Access, and Content.

## Try it

```bash
curl -s https://sitepassport.org/.well-known/mcp.json \
  -X POST -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"check_wordpress_agent_readiness","arguments":{"url":"https://wordpress.org"}}}'
```

## Rankings are earned, never bought

A listed site's score is pulled directly from that site's own public manifest, on a recurring schedule — not typed into a form once and trusted forever. See [`METHODOLOGY.md`](./METHODOLOGY.md) for the full rationale and [sitepassport.org](https://sitepassport.org) for the live directory.

## Support

Questions, issues with the live endpoint, or methodology feedback: [hello@agentic-tech.us](mailto:hello@agentic-tech.us).

## License

MIT — see [`LICENSE`](./LICENSE).
