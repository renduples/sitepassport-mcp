# Container for automated MCP registry checks (e.g. Glama) only — the real
# server is the remote HTTP endpoint at https://sitepassport.org/.well-known/mcp.json.
# This image starts a stdio MCP server that answers `initialize` and
# `tools/list` with the real tool schema; see bin/mcp-stdio.php for why
# `tools/call` isn't implemented here.
FROM php:8.4-cli-alpine

WORKDIR /app
COPY bin/mcp-stdio.php ./bin/mcp-stdio.php

ENTRYPOINT ["php", "bin/mcp-stdio.php"]
