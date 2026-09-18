# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0]

### Added

- MCP protocol revision `2026-07-28` support. `ProtocolVersion::LATEST` is
  `2026-07-28`; `ProtocolVersion::handshake()`, `serverSupported()`,
  `clientSupported()` (now including `2026-07-28`), `initializeSupported()`, and
  `preferredFrom()` describe the modern and legacy eras. New `Enums\ProtocolHandshake`
  (`Initialize` / `Discovery`), `Enums\MetaKey`, and `Enums\RequestHeader`.
- The `server/discover` method (`Server\Methods\Discover`, `Client\Methods\Discover`,
  `Client\Schema\DiscoverResult`), returning supported protocol versions,
  capabilities, and instructions.
- Legacy `initialize` handshake serving alongside the modern protocol: the new
  `Server\Methods\Initialize` handler answers with `2025-11-25` or `2025-06-18`,
  whichever the client requested (`2025-03-26` and `2024-11-05` clients are offered
  `2025-11-25`). Requests without the modern `_meta` protocol keys are treated as
  legacy and skip protocol metadata and MCP header validation.
- `resultType: complete` and the `io.modelcontextprotocol/serverInfo` metadata on
  every result.
- Caching hints on cacheable results (`server/discover`, `tools/list`, `prompts/list`,
  `resources/list`, `resources/templates/list`, `resources/read`): `Enums\CacheScope`,
  `Server\Attributes\Cacheable`, `Resource::cacheable()`, and per-method `cacheHints()`.
- Client-side response caching honoring server hints: `Client::withCache()` /
  `withoutCache()` with `Client\ResponseCache` respecting `ttlMs` and `cacheScope`.
- MCP Apps advertised through the `capabilities.extensions` member via
  `Enums\Extension`; the UI extension is declared automatically when an
  `AppResource` is registered.
- Mirrored tool parameters: an `x-mcp-header` annotation on a `tools/call` argument
  produces a `Mcp-Param-*` request header. New `Support\SchemaPath`,
  `Support\MirroredParameter`, `Support\MirroredParameterType`,
  `Support\MirroredParameters`, and `Exception\MirroredParameterException`.
- The MCP 2026-07-28 base64 `=?base64?...?=` sentinel for mirrored header values via
  `Transport\HeaderValue`.
- `Enums\ErrorCode` aligned with MCP 2026-07-28.
- `subscriptions/listen` acknowledgement: new `Server\Methods\Listen` answers with
  `notifications/subscriptions/acknowledged` carrying
  `io.modelcontextprotocol/subscriptionId`, then closes with an empty result.
- Searchable tool catalogs: a `ToolSearch::class => [...]` tool group expands into
  `search_tools` and `execute_tools` meta-tools (`Server\Tools\ToolSearch`,
  `Server\Tools\SearchTools`, `Server\Tools\ExecuteTools`); `Server\ToolInvoker`
  extracts tool invocation from `CallTool`. Limits via `Mcp.tool_search`
  (`max_tool_calls`, `max_output_bytes`).
- Client ID Metadata Documents preferred over dynamic registration: `OAuthClient`
  uses an HTTPS client metadata document URL as the `client_id` when the
  authorization server advertises `client_id_metadata_document_supported`; PKCE
  (`S256`) is now required. A public client metadata document route
  (`mcp.oauth.{client}.client-metadata`) is registered by
  `OAuthRouteRegistrar::register()` and served by `OAuthController::clientMetadata()`.
- OAuth DCR metadata: optional `logo_uri` / `client_uri` (HTTP(S) URLs) accepted by
  the registration validator; `persistClientMetadata()` persists RFC 7591 metadata
  when the Tessera clients table supports the columns (schema-aware, `[]` otherwise).
- OAuth challenges on authenticated MCP routes: the `AddWwwAuthenticateHeader`
  middleware adds `WWW-Authenticate` with the protected-resource metadata URL on 401
  responses.
- An opt-in Tessera consent template (`templates/Authorization/authorize.php`;
  enable via `Tessera::authorizationView('Mcp.Authorization/authorize')`).
- JSON-RPC notification params validation: `JsonRpcNotification::from()` rejects
  scalar or list params with `-32602` (mirrors the `JsonRpcRequest` guard).
- Server details on the client: `Client::discoverResult()`, `capabilities()`,
  `serverInfo()`, `instructions()`, `protocolVersion()`, and `withProtocolVersion()`
  for pinning a protocol era (the pinned version survives serialization). New
  `Client\Contracts\UsesProtocol`, `Client\Contracts\MirrorsParameters`,
  `Client\NegotiatedConnection`, and
  `Client\Exception\TransportException` / `TimeoutException`.
- `TestListResponse` with `assertRegistered()` / `assertNotRegistered()` for
  asserting server primitives; `PendingTestResponse::tools()`, `resources()`, and
  `prompts()` return paginated `TestListResponse` instances.
- A `Registrar::web('demo', ...)` registration snippet in the ignis MCP skill.
- The MCP conformance test suite: `ConformanceServer` for running the official
  `@modelcontextprotocol/conformance` runner against the CakePHP MCP server.
- Dot-path and matcher support in `Request::data()`: paths resolve via
  `Hash::get()`, `{n}` / `{s}` / `{*}` matchers via `Hash::extract()` (empty matches
  fall back to the default).
- An injectable output stream in `Server\Transport\StdioTransport` (STDOUT remains
  the default).
- The 1.0.0 upgrade guide (`docs/upgrade-1.0.md`).

### Changed

- The server speaks `2026-07-28` by default: modern requests carry the protocol
  version and client capabilities in their own `params._meta`. Clients that still
  open with `initialize` keep working (see Added); `ping` is served again alongside
  `server/discover`.
- The client negotiates eras: `server/discover` is probed first with fallback to the
  legacy `initialize` handshake when refused, including discovery rejections with a
  server-defined error code in the `-32000..-32099` band or a null `id`.
- Mirrored MCP request headers (`MCP-Protocol-Version`, `Mcp-Method`, `Mcp-Name`) are
  validated over HTTP by the new `ValidateMcpHeaders` middleware; mismatches answer
  with HTTP 400 and the `-32020` code, and protocol errors map to their HTTP statuses
  (404 / 500 / 400).
- Unresolvable `resources/read` URIs return `-32602` instead of the retired `-32002`.
- `Client::callTool()` accepts a `Tool` instance and re-lists tools once on a
  `-32020` header mismatch. `Transport::send()` accepts extra request headers, while
  HTTP `send()` refuses custom `Mcp-*` overrides.
- `Schema\Implementation::from()` returns `null` on malformed payloads instead of
  throwing.
- Each HTTP POST and stdio line is processed on its own; no server session is
  established (see Removed).
- `Registrar::oauthRoutes()` registers well-known and DCR routes idempotently by
  checking named routes, so repeated calls are safe.
- `McpPlugin::bootstrap()` loads host `config/mcp.php` with plugin config fallback
  and wires the container through the `Application.buildContainer` event;
  `config/bootstrap.php` no longer loads config directly.
- The `mcp_server` Bake template generates `protected` `$tools` / `$resources` /
  `$prompts` with `@var` docblocks.
- The MCP Inspector launches with a modern-era config (`protocolEra: modern`) and
  prompts for route parameter values.
- MCP app iframe auto-resize measurement in the bundled SDK (temporary
  `max-content` height probe, `window.innerWidth` width).
- `config/mcp.php` documents the `redirect_domains`, `custom_schemes`,
  `authorization_server`, `tool_search`, and `oauth` options.
