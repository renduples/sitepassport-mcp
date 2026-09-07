# Methodology

*Version 1 · Published 2026-09-04 · kept in sync with [sitepassport.org/methodology](https://sitepassport.org/methodology), which is the canonical, human-readable source. If they ever disagree, the website is correct and this file is stale — open an issue.*

A score you can't audit isn't a score, it's a claim. This is the single source of truth for every check Site Passport runs.

| Check | Category | Verifies | Weight | Requires |
|---|---|---|---|---|
| `mcp_server_reachable` | Capability exposure | Does the site expose an MCP server that responds to a real request? | High | Agent Builder (or equivalent) |
| `webmcp_tools_registered` | Capability exposure | How many tools does the site successfully register for browser agents, confirmed live? | High | Agent Builder (or equivalent) |
| `approval_gate_configured` | Safety & trust | Does every exposed tool have a declared risk tier, with a real human-approval gate reachable? | High | Agent Builder (or equivalent) |
| `llms_txt_present` | Discoverability | Does `/llms.txt` exist and is it well-formed? | Medium | Nothing — checked directly |
| `robots_ai_directives` | Bot access control | Does `robots.txt` explicitly address AI crawlers (GPTBot, ClaudeBot, PerplexityBot, Google-Extended)? Silence scores lower than an explicit allow. | Medium | Nothing — checked directly |
| `schema_org_present` | Content | Is Organization/WebSite (and Product, where relevant) JSON-LD present? | Medium | Nothing — checked directly |
| `well_known_manifest` | Discoverability | Does `/.well-known/webmcp.json` exist? An emerging, non-canonical discovery convention. | Low | Nothing — checked directly |

## Why two tiers of checks?

Discoverability, Bot Access, and Content are externally observable — anyone can check them for any site, which is exactly what `check_wordpress_agent_readiness` (this repo) and the free scan on [sitepassport.org](https://sitepassport.org) do. Capability Exposure and Safety & Trust can't be faked from outside: they require the site itself to actually expose tools and gate them behind approval, which today means running Agent Builder. That's not a marketing claim, it's a structural fact about what's checkable from the outside.

## Rankings are earned, never bought

A featured or promoted listing — when that exists — will always be visibly labeled as such and will never change the underlying score. The moment score and payment blur, this file stops meaning anything.

## Verified, not self-submitted

A listed site's score is pulled directly from that site's own public manifest on a recurring schedule — not typed into a form once and trusted forever. Stop passing checks, and a listing goes stale, then delists.

## This file changes with notice

If the check list or weights change, the version number and date at the top change with them, on the website first. Nothing here is silently retuned. Watch this repo or the website's changelog for updates.

## What's not in this repo

The *implementation* of each check — the actual scanning logic, SSRF guards, and rate-limiting — is Site Passport's private engine, not published here. See the [README](./README.md) for why, and for the parallel to how other hosted-API MCP servers (Stripe, Perplexity, Sentry) structure their own public repos.
