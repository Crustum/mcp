# MCP UI Apps Reference (CakePHP / crustum/mcp)

Prefer package `docs/index.md` (Apps) if APIs diverge.
UI shell: `Response::view('Mcp/...', $data, [], 'Crustum/Mcp.mcp_app')`.
## Quick Start

`bake mcp_app_resource DashboardApp` generates two files — a PHP registration stub and a CakePHP template. The entire app lives in the CakePHP template.

**PHP class** — renders the CakePHP template. The view name is auto-inferred from the class name (`Mcp/<snake_case>` template + `Crustum/Mcp.mcp_app` layout), so the generated stub needs no changes unless you're passing additional server-side data:

```php
class DashboardApp extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::view('Mcp/dashboard_app', [
            'title' => $this->title(),
        ], [], 'Crustum/Mcp.mcp_app');
    }
}
```

**CakePHP template** — HTML structure + inline JS, everything in one file:

```html
<!-- Hosted in Crustum/Mcp.mcp_app layout; body content: -->
    <?php $this->start('mcpHead'); ?>
        <script type="module">
        createMcpApp(async (app) => {
            document.getElementById('run-btn').addEventListener('click', async () => {
                const result = await app.callServerTool({ name: 'tool-name', arguments: {} });
                document.getElementById('output').textContent = result.content[0]?.text ?? '';
            });
        });
        </script>
    <?php $this->end(); ?>

    <div id="app">
        <h1>Dashboard App</h1>
        <button id="run-btn">Run</button>
        <p id="output"></p>
    </div>
<!-- /mcp app body -->
```

`createMcpApp` is a global pre-bundled by the package — no npm install, no imports, no Vite required. It handles connection, error handling, and host theming automatically.

---

## Core Concept: Tool + Resource

Every MCP App is built from two parts linked together:

- **Tool** — called by the LLM or host. Returns a text/data response and tells the host which UI resource to render via `_meta.ui.resourceUri`.
- **AppResource** — serves the self-contained HTML app. The host fetches it after the tool is called and renders it in a sandboxed iframe.

```
LLM calls Tool
    └─► Tool response includes _meta.ui.resourceUri → "ui://dashboard-app"
            └─► Host fetches AppResource at that URI
                    └─► Host renders HTML in sandboxed iframe
                            └─► createMcpApp() connects the iframe back to the server
                                    └─► UI calls app-only tools to load/refresh data
```

The link is declared once with `#[RendersApp]` on the tool:

```php
#[RendersApp(resource: DashboardApp::class)]
class ShowDashboard extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::text('Dashboard loaded.');
    }
}
```

After that, the host handles fetching and rendering the resource automatically — you never reference the URI by hand.

---

## Architecture Overview

MCP Apps add interactive UI to the Model Context Protocol. The server returns self-contained HTML with all JS/CSS inlined. The host renders it in a sandboxed iframe. Apps communicate back via `createMcpApp()` — a pre-bundled global implementing the MCP UI PostMessage protocol.

```
┌─────────────────────────────────────────────┐
│  Host (Claude, ChatGPT, VS Code)            │
│  ┌───────────────────────────────────────┐  │
│  │  Sandboxed iframe                     │  │
│  │  ┌─────────────────────────────────┐  │  │
│  │  │  Your MCP App (HTML/JS/CSS)     │  │  │
│  │  │  - Rendered by AppResource       │  │  │
│  │  │  - Single self-contained HTML   │  │  │
│  │  │  - Themed via host CSS vars     │  │  │
│  │  └─────────────────────────────────┘  │  │
│  └───────────────────────────────────────┘  │
└──────────────────┬──────────────────────────┘
                   │ MCP Protocol (JSON-RPC)
┌──────────────────▼──────────────────────────┐
│  CakePHP MCP Server                         │
│  - AppResource → self-contained HTML         │
│  - Tool #[RendersApp] → triggers UI display   │
│  - resources/read → serves HTML + _meta.ui  │
└─────────────────────────────────────────────┘
```

The server automatically advertises `io.modelcontextprotocol/ui` capability when any `AppResource` is registered. The client declares support in `capabilities.extensions["io.modelcontextprotocol/ui"]` during the initialize handshake.

---

## Server-Side

Minimal case — `handle()` renders the CakePHP template, entire app lives there:

```php
class DashboardApp extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::view('Mcp/dashboard_app', [
            'title' => $this->title(),
        ], [], 'Crustum/Mcp.mcp_app');
    }
}
```

Bake generates `templates/Mcp/dashboard_app.php` rendered through `Crustum/Mcp.mcp_app`; `$title` via `$this->title()`.

Override `handle()` only when passing additional server-side data:

```php
class AnalyticsDashboard extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::view('Mcp/analytics_dashboard', [
            'title' => $this->title(),
            'metrics' => \Cake\ORM\TableRegistry::getTableLocator()->get('Metrics')->find()->orderByDesc('id')->limit(10)->all(),
            'totalUsers' => \Cake\ORM\TableRegistry::getTableLocator()->get('Users')->find()->count(),
        ], [], 'Crustum/Mcp.mcp_app');
    }
}
```

`Response::view($template, $data = [], $mergeData = [], $layout = false)` renders a CakePHP template (pass `'Crustum/Mcp.mcp_app'` as layout for apps) and returns it as text.

`Response::html($path)` reads an HTML file from disk and returns its content. Relative paths resolve via `project templates / webroot`:

```php
class StaticApp extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::html('mcp/static-app.html');
    }
}
```

### AppMeta Configuration

The simplest way to configure UI metadata is via the `#[AppMeta]` attribute directly on your resource class:

```php
use Crustum\Mcp\Server\Attributes\AppMeta;
use Crustum\Mcp\Server\Ui\Enums\Library;
use Crustum\Mcp\Server\Ui\Enums\Permission;

#[AppMeta(
    connectDomains: ['https://api.stripe.com'],
    permissions: [Permission::Camera, Permission::ClipboardWrite],
    prefersBorder: true,
    libraries: [Library::Tailwind, Library::Alpine],
)]
class PaymentsResource extends AppResource
{
    // ...
}
```

For dynamic or computed configuration, override `appMeta()` instead:

```php
use Crustum\Mcp\Server\Ui\AppMeta;

public function appMeta(): AppMeta
{
    return AppMeta::make()
        ->csp(Csp::make()->connectDomains(config('services.api.domains')))
        ->permissions(Permissions::make()->allow(Permission::Camera))
        ->libraries(Library::Tailwind)
        ->domain('sandbox.example.com');
}
```

#### Permission Enum

Use the `Permission` enum for type-safe permission configuration:

```php
use Crustum\Mcp\Server\Ui\Enums\Permission;

Permission::Camera        // 'camera'
Permission::Microphone    // 'microphone'
Permission::Geolocation   // 'geolocation'
Permission::ClipboardWrite // 'clipboardWrite'
```

#### Csp

Controls what external domains the iframe can access:

```php
Csp::make()
    ->connectDomains(['https://api.example.com'])    // fetch, XHR, WebSocket origins
    ->resourceDomains(['https://cdn.example.com'])   // images, scripts, fonts, media
    ->frameDomains(['https://embed.example.com'])    // nested iframe origins
    ->baseUriDomains(['https://base.example.com']);  // base URI origins
```

#### Permissions

```php
Permissions::make()->allow(Permission::Camera, Permission::ClipboardWrite);

Permissions::make()
    ->camera()
    ->microphone()
    ->geolocation()
    ->clipboardWrite();
```

Each enabled permission serializes as `"camera": {}` per the MCP spec.

#### AppMeta

```php
AppMeta::make()
    ->csp(Csp::make()->connectDomains([...]))
    ->permissions(Permissions::make()->allow(Permission::Camera))
    ->libraries(Library::Tailwind, Library::Alpine)
    ->domain('sandbox.example.com')  // dedicated sandbox origin (OAuth/CORS)
    ->prefersBorder(false);
```

`prefersBorder` defaults to `true`. `toArray()` omits null fields and empty nested objects. Library CDN domains are automatically merged into `csp.resourceDomains`.

#### domain

The `domain` field provides a stable origin that external APIs can allowlist for CORS. It is automatically resolved from `Configure::read('App.fullBaseUrl')` (`App.fullBaseUrl` / `APP_URL`) via `resolvedAppMeta()`, so most apps need no configuration. Override only when a resource needs a different origin:

```php
#[AppMeta(domain: 'custom.example.com')]
class PaymentsResource extends AppResource
{
    // ...
}
```

#### Library Scripts

The `libraries` parameter adds pre-configured CDN scripts to the `<head>` of your app. Available libraries:

```php
use Crustum\Mcp\Server\Ui\Enums\Library;

Library::Tailwind  // Tailwind CSS CDN + dark mode config
Library::Alpine    // Alpine.js CDN + x-cloak style
```

When libraries are specified, the package automatically:

1. Injects the CDN `<script>` tags into the CakePHP template's `<head>` (after the MCP SDK, before content assigned to the `mcpHead` block)
2. Merges each library's CDN domains into `csp.resourceDomains` so the host allows loading them

Via attribute:

```php
#[AppMeta(libraries: [Library::Tailwind])]
class StyledApp extends AppResource
{
    // Tailwind is available in the CakePHP template — no extra setup
}
```

Via fluent builder:

```php
public function appMeta(): AppMeta
{
    return AppMeta::make()
        ->libraries(Library::Tailwind, Library::Alpine);
}
```

---

## View Layer

### CakePHP `Crustum/Mcp.mcp_app` layout

AppResource `Response::view(..., [], 'Crustum/Mcp.mcp_app')` renders a complete HTML document with the MCP SDK inlined via the plugin layout. `createMcpApp` is available globally.

```html
<!-- Hosted in Crustum/Mcp.mcp_app layout; body content: -->
    <?php $this->start('mcpHead'); ?>
        <script type="module">
        createMcpApp(async (app) => {
            document.getElementById('run-btn').addEventListener('click', async () => {
                const result = await app.callServerTool({ name: 'tool-name', arguments: {} });
                document.getElementById('output').textContent = result.content[0]?.text ?? '';
            });
        });
        </script>
    <?php $this->end(); ?>

    <div id="app">
        <button id="run-btn">Run</button>
        <p id="output"></p>
    </div>
<!-- /mcp app body -->
```

**Layout variables and blocks:**

| Name          | Type          | Description                                          |
| ------------- | ------------- | ---------------------------------------------------- |
| `title`       | Prop          | Sets `<title>`. Optional.                            |
| `mcpHead`     | View block    | Injected into `<head>` after the inlined SDK script (`$this->start('mcpHead')`). |
| `content`     | Layout body   | Template body via `$this->fetch('content')`.         |
| (body attrs)  | n/a           | Customize `<body>` in a forked layout if needed.      |

The SDK is resolved from the MCP container binding `mcp.sdk` (see `McpContainerBindings::SDK` / `McpSdk`) and inlined in a `<script>` tag by the `Crustum/Mcp.mcp_app` layout. Library scripts from `#[AppMeta]` are injected after the SDK; put extra head content in the `mcpHead` view block.

Use the plugin layout `Crustum/Mcp.mcp_app` (see package docs).

To pass server-side data to JS, embed it as `data-*` attributes:

```html
<div id="app" data-users="<?= h(json_encode($users)) ?>">
    ...
</div>
```

```js
const users = JSON.parse(document.getElementById("app").dataset.users);
```

## Client-Side

This package provides a simple MCP client library to easily work with client interactions.

### createMcpApp

Pre-bundled and inlined automatically — no npm install or imports required.

```js
createMcpApp(async (app) => {
    // app is ready — connection established, theming applied
});
```

### Tools

#### app.callServerTool()

Accepts an object or positional arguments:

```js
// Object form
const result = await app.callServerTool({ name: 'get-analytics', arguments: { dateRange: '7d' } });

// Positional form
const result = await app.callServerTool('get-analytics', { dateRange: '7d' });

// result structure depends on the server's tool response
const text = result.content[0]?.text ?? "";
```

All tool results share a standard structure:

| Property  | Type      | Description                                                               |
| --------- | --------- | ------------------------------------------------------------------------- |
| `content` | `Array`   | Content items returned by the tool (each has `type` and `text` or `data`) |
| `isError` | `boolean` | `true` when the tool returned an error response                           |

Always check `result.isError` before consuming `content`. See [Error Handling](#error-handling) for a full example.

### Resources

#### app.listResources()

```js
const resources = await app.listResources();
// or with cursor for pagination
const resources = await app.listResources("cursor-value");
// or object form
const resources = await app.listResources({ cursor: "cursor-value" });
```

#### app.readResource()

```js
const resource = await app.readResource("ui://my-resource");
// or object form
const resource = await app.readResource({ uri: "ui://my-resource" });
```

### Messaging

#### app.sendMessage()

Send a message to the model (creates a conversation turn):

```js
// Object form with structured content
await app.sendMessage({
    role: "user",
    content: [{ type: "text", text: "User submitted the form." }],
});

// Shorthand — plain string content with optional role (defaults to 'user')
await app.sendMessage("User submitted the form.");
await app.sendMessage("System event occurred.", "user");
```

### Host Context

#### app.getHostContext()

Returns the current host context, including theme and style variables:

```js
const ctx = app.getHostContext();
ctx?.theme; // 'light' | 'dark'
ctx?.styles?.variables; // CSS variable map from host
ctx?.styles?.css?.fonts; // font CSS from host
```

#### app.getHostInfo()

```js
const info = app.getHostInfo();
```

#### app.getHostCapabilities()

```js
const caps = app.getHostCapabilities();
```

### Navigation & Files

#### app.openLink()

```js
await app.openLink("https://example.com");
// or object form
await app.openLink({ url: "https://example.com" });
```

#### app.downloadFile()

```js
await app.downloadFile("file contents here");
// or object form
await app.downloadFile({ contents: "file contents here" });
```

### Display

#### app.requestDisplayMode()

```js
await app.requestDisplayMode("fullscreen");
// or object form
await app.requestDisplayMode({ mode: "fullscreen" });
```

#### app.resize() / app.autoResize()

`resize()` sends a one-time size notification. `autoResize()` uses `ResizeObserver` to continuously notify the host of size changes. It returns a cleanup function that disconnects the observer — useful if you need to stop observing before teardown. The observer is also automatically disconnected on teardown.

```js
const stopObserving = app.autoResize();

// Later, if needed:
stopObserving();
```

### Model Context

#### app.updateModelContext()

```js
await app.updateModelContext({ key: "value" });
```

### Lifecycle

#### app.requestTeardown()

Sends a teardown notification to the host.

```js
app.requestTeardown();
```

### Logging

#### app.sendLog()

```js
// Positional form
await app.sendLog("info", "Processing started", "my-logger");

// Object form
await app.sendLog({
    level: "info",
    data: "Processing started",
    logger: "my-logger",
});
```

### Event Handlers

Register callbacks for host-side events. Tool input/result/cancelled events are queued until a handler is registered, then flushed.

```js
createMcpApp(async (app) => {
    app.onToolInput((params) => {
        /* tool input received */
    });
    app.onToolInputPartial((params) => {
        /* partial tool input */
    });
    app.onToolResult((params) => {
        /* tool result received */
    });
    app.onToolCancelled((params) => {
        /* tool was cancelled */
    });
    app.onHostContextChanged((ctx) => {
        /* theme/styles changed */
    });
    app.onTeardown(async () => {
        /* cleanup before teardown */
    });
    app.onCallTool(async (params) => {
        /* host requests tool call */
    });
    app.onListTools(async (params) => {
        /* host requests tool list */
    });
});
```

---

## Host Theming

`createMcpApp` automatically applies host theming on connect and on context change:

- Sets `data-theme` attribute and `color-scheme` on `<html>`
- Applies CSS variables from `hostContext.styles.variables` to `:root`
- Injects font CSS from `hostContext.styles.css.fonts` into a `<style>` tag

The specific CSS variables available depend on the host. Always provide fallback values — use `light-dark()` for theme-aware defaults:

```css
:root {
    --color-background-primary: light-dark(#ffffff, #171717);
    --color-text-primary: light-dark(#171717, #fafafa);
    --color-text-secondary: light-dark(#525252, #a3a3a3);
    --color-border-primary: light-dark(#e5e5e5, #404040);
    --font-sans: system-ui, -apple-system, sans-serif;
    --border-radius-md: 8px;
}

body {
    font-family: var(--font-sans);
    background: var(--color-background-primary);
    color: var(--color-text-primary);
    margin: 0;
}

.card {
    background: var(--color-background-secondary);
    border: 1px solid var(--color-border-primary);
    border-radius: var(--border-radius-md);
    padding: 1rem;
}
```

---

## Tool-to-UI Linking

### #[RendersApp] Attribute

Associates a Tool with a UI Resource. When the tool is called, the host fetches and renders the linked resource.

```php
use Crustum\Mcp\Server\Attributes\RendersApp;
use Crustum\Mcp\Server\Ui\Enums\Visibility;

// Both model and app can call this tool (default)
#[RendersApp(resource: DashboardApp::class)]
class ShowDashboard extends Tool { ... }

// Only the app can call this tool (private to the UI)
#[RendersApp(resource: DashboardApp::class, visibility: [Visibility::App])]
class RefreshDashboardData extends Tool { ... }
```

**Visibility:**

The `Visibility` enum (`Crustum\Mcp\Server\Ui\Enums\Visibility`) has two cases: `Model` and `App`. The default is `[Visibility::Model, Visibility::App]`.

| Visibility                             | Model | App | Use case                                               |
| -------------------------------------- | ----- | --- | ------------------------------------------------------ |
| `[Visibility::Model, Visibility::App]` | Yes   | Yes | Primary tools that trigger UI display                  |
| `[Visibility::App]`                    | No    | Yes | Backend actions the UI calls (refresh, save, paginate) |
| `[Visibility::Model]`                  | Yes   | No  | Model-only tools linked to a UI                        |

### Primary + Private Pattern

```php
#[RendersApp(resource: DashboardApp::class)]
class ShowDashboard extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::text('Dashboard loaded.');
    }
}

#[RendersApp(resource: DashboardApp::class, visibility: [Visibility::App])]
class GetDashboardMetrics extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::json(\Cake\ORM\TableRegistry::getTableLocator()->get('Metrics')->find()->orderByDesc('id')->limit(50)->all());
    }
}
```

---

## Testing

```php
it('returns html content', function () {
    MyServer::readResource(DashboardApp::class)
        ->assertSee('<div id="app">');
});

it('has correct mime type and uri scheme', function () {
    $resource = new DashboardApp;
    $data = $resource->toArray();

    expect($data['mimeType'])->toBe('text/html;profile=mcp-app')
        ->and($data['_meta']['ui'])->toBeArray()
        ->and($resource->uri())->toStartWith('ui://');
});

it('configures ui meta correctly', function () {
    $meta = (new DashboardApp)->resolvedAppMeta();

    expect($meta['csp']['connectDomains'])->toContain('https://api.example.com')
        ->and($meta['permissions'])->toHaveKey('clipboardWrite');
});

it('includes ui metadata in tool listing', function () {
    MyServer::listTools()->assertSee('show-dashboard');
});
```

---

## Patterns

### Real-time Polling

Use app-only tools to fetch fresh data at regular intervals from the UI:

```php
#[RendersApp(resource: MonitorApp::class, visibility: [Visibility::App])]
class GetMonitorData extends Tool
{
    protected string $description = 'Fetch latest monitor metrics';

    public function handle(Request $request): Response
    {
        return Response::json([
            'cpu' => sys_getloadavg()[0],
            'memory' => memory_get_usage(true),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
```

```js
createMcpApp(async (app) => {
    async function poll() {
        const result = await app.callServerTool('get-monitor-data');
        const data = JSON.parse(result.content[0]?.text ?? '{}');
        document.getElementById('cpu').textContent = data.cpu;
    }

    setInterval(poll, 2000);
    poll();
});
```

### Chunked Data Loading

For large datasets, implement pagination via app-only tools:

```php
#[RendersApp(resource: LogViewerApp::class, visibility: [Visibility::App])]
class GetLogChunk extends Tool
{
    protected string $description = 'Fetch a chunk of log entries';

    public function schema(JsonSchema $schema): array
    {
        return [
            'offset' => $schema->integer()->description('Byte offset to start from')->required(),
            'limit' => $schema->integer()->description('Max bytes to return'),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate(['offset' => 'required|integer', 'limit' => 'integer']);

        $offset = $request->get('offset');
        $limit = $request->get('limit', 500_000);
        $content = Storage::get('logs/app.log');
        $chunk = substr($content, $offset, $limit);

        return Response::json([
            'data' => $chunk,
            'offset' => $offset,
            'totalBytes' => strlen($content),
            'hasMore' => ($offset + $limit) < strlen($content),
        ]);
    }
}
```

### Binary Resource Serving

Deliver images and binary content through MCP resources using `Response::blob()`:

```php
#[RendersApp(resource: GalleryApp::class, visibility: [Visibility::App])]
class GetImage extends Tool
{
    protected string $description = 'Fetch an image by ID';

    public function handle(Request $request): Response
    {
        $request->validate(['id' => 'required|integer']);

        $image = Image::findOrFail($request->get('id'));
        $data = base64_encode(Storage::get($image->path));

        return Response::blob($data);
    }
}
```

In the client, convert the base64 blob to a data URI for rendering:

```js
const result = await app.callServerTool('get-image', { id: 42 });
const blob = result.content[0];
img.src = `data:${blob.mimeType};base64,${blob.data}`;
```

### Streaming Argument Previews

Use `onToolInputPartial` to show previews as the model streams tool arguments:

```js
createMcpApp(async (app) => {
    app.onToolInputPartial((params) => {
        try {
            const partial = JSON.parse(params.arguments);
            if (partial.query) {
                document.getElementById("preview").textContent = partial.query;
            }
        } catch {
            // partial JSON — ignore until parseable
        }
    });

    app.onToolResult((params) => {
        const data = JSON.parse(params.result.content[0]?.text ?? "{}");
        renderResults(data);
    });
});
```

### View State Persistence

Use `localStorage` to preserve UI state across re-renders. For important state, persist server-side via an app-only tool:

```js
createMcpApp(async (app) => {
    const STATE_KEY = "dashboard-view-state";

    // Restore from localStorage
    const saved = JSON.parse(localStorage.getItem(STATE_KEY) || "{}");
    if (saved.activeTab) selectTab(saved.activeTab);

    // Save on interaction
    function saveState(state) {
        localStorage.setItem(STATE_KEY, JSON.stringify(state));
    }

    // For durable state, persist server-side
    async function saveServerState(state) {
        await app.callServerTool('save-dashboard-state', { state: JSON.stringify(state) });
    }
});
```

### Fullscreen Toggling

Switch between inline and fullscreen display modes and react to mode changes:

```js
createMcpApp(async (app) => {
    document.getElementById("expand-btn").addEventListener("click", () => {
        app.requestDisplayMode("fullscreen");
    });

    app.onHostContextChanged((ctx) => {
        document.body.classList.toggle(
            "fullscreen",
            ctx.displayMode === "fullscreen",
        );
    });
});
```

### Model Context Updates

Keep the model informed about what the user is viewing so it can provide relevant assistance:

```js
createMcpApp(async (app) => {
    async function notifyContext(view, detail) {
        await app.updateModelContext({
            currentView: view,
            detail: detail,
        });
    }

    // Notify on tab change
    document.querySelectorAll(".tab").forEach((tab) => {
        tab.addEventListener("click", () => {
            notifyContext(tab.dataset.view, { filters: getActiveFilters() });
        });
    });

    // For large payloads, follow up with sendMessage
    await app.updateModelContext({ currentView: "report", rows: 5000 });
    await app.sendMessage("The user is viewing a report with 5000 rows.");
});
```

### Pause Offscreen Views

Conserve resources by pausing animations and polling when the view is not visible:

```js
createMcpApp(async (app) => {
    let pollInterval = null;

    function startPolling() {
        if (!pollInterval) {
            pollInterval = setInterval(fetchData, 2000);
        }
    }

    function stopPolling() {
        clearInterval(pollInterval);
        pollInterval = null;
    }

    const observer = new IntersectionObserver(([entry]) => {
        entry.isIntersecting ? startPolling() : stopPolling();
    });

    observer.observe(document.documentElement);
    startPolling();
});
```

### Error Handling

Return `Response::error()` from tools and use `updateModelContext()` to signal degraded state:

```php
class ProcessData extends Tool
{
    public function handle(Request $request): Response
    {
        $request->validate(['input' => 'required|string']);

        if (strlen($request->get('input')) > 10_000) {
            return Response::error('Input exceeds 10KB limit.');
        }

        return Response::json(process($request->get('input')));
    }
}
```

```js
createMcpApp(async (app) => {
    const result = await app.callServerTool('process-data', { input: value });

    if (result.isError) {
        document.getElementById("error").textContent =
            result.content[0]?.text ?? "Unknown error";
        await app.updateModelContext({
            state: "error",
            message: result.content[0]?.text,
        });
        return;
    }

    renderOutput(JSON.parse(result.content[0]?.text ?? "{}"));
});
```
