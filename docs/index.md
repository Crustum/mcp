# CakePHP MCP Plugin

- [Introduction](#introduction)
- [Installation](#installation)
    - [Publishing Configuration](#publishing-configuration)
- [Creating Servers](#creating-servers)
    - [Server Registration](#server-registration)
    - [Web Servers](#web-servers)
    - [Local Servers](#local-servers)
- [Tools](#tools)
    - [Creating Tools](#creating-tools)
    - [Tool Input Schemas](#tool-input-schemas)
    - [Tool Output Schemas](#tool-output-schemas)
    - [Validating Tool Arguments](#validating-tool-arguments)
    - [Tool Dependency Injection](#tool-dependency-injection)
    - [Tool Annotations](#tool-annotations)
    - [Conditional Tool Registration](#conditional-tool-registration)
    - [Tool Responses](#tool-responses)
- [Prompts](#prompts)
    - [Creating Prompts](#creating-prompts)
    - [Prompt Arguments](#prompt-arguments)
    - [Validating Prompt Arguments](#validating-prompt-arguments)
    - [Prompt Dependency Injection](#prompt-dependency-injection)
    - [Conditional Prompt Registration](#conditional-prompt-registration)
    - [Prompt Responses](#prompt-responses)
- [Resources](#resources)
    - [Creating Resources](#creating-resources)
    - [Resource Templates](#resource-templates)
    - [Resource URI and MIME Type](#resource-uri-and-mime-type)
    - [Resource Request](#resource-request)
    - [Resource Dependency Injection](#resource-dependency-injection)
    - [Resource Annotations](#resource-annotations)
    - [Conditional Resource Registration](#conditional-resource-registration)
    - [Resource Responses](#resource-responses)
- [Apps](#apps)
    - [Creating App Resources](#creating-app-resources)
    - [Rendering Apps From Tools](#rendering-apps-from-tools)
    - [App Tool Visibility](#app-tool-visibility)
    - [App Configuration](#app-configuration)
- [Metadata](#metadata)
- [Icons](#icons)
- [Authentication](#authentication)
    - [OAuth 2.1](#oauth)
    - [Token Authentication](#token-authentication)
    - [Production Hardening](#production-hardening)
- [Authorization](#authorization)
- [MCP Client](#client)
    - [Connecting to Servers](#client-connecting)
    - [Named Clients](#named-clients)
    - [Client Authentication](#client-authentication)
    - [Tools](#client-tools)
    - [Prompts](#client-prompts)
    - [Resources](#client-resources)
- [Testing Servers](#testing-servers)
    - [MCP Inspector](#mcp-inspector)
    - [Unit Tests](#unit-tests)

<a name="introduction"></a>
## Introduction

The CakePHP MCP plugin (`crustum/mcp`) provides a simple and elegant way for AI clients to interact with your CakePHP application through the [Model Context Protocol](https://modelcontextprotocol.io/docs/getting-started/intro). It offers an expressive interface for defining servers, tools, resources, and prompts that enable AI-powered interactions with your application.

MCP servers expose capabilities that language models and agent hosts can discover and invoke. A server might offer tools that query your database, resources that return documentation or configuration, and prompts that encode reusable conversation templates. The plugin handles the protocol wiring — JSON-RPC transport, session headers, schema advertisement, and response formatting — so you can focus on the behavior of each primitive.

You may expose servers over HTTP for remote agents, or as local STDIO processes for IDE integrations and desktop clients. The same server class can be registered for both transports when that fits your deployment model.

<a name="installation"></a>
## Installation

To get started, install the MCP plugin into your project using the Composer package manager:

```bash
composer require crustum/mcp
```

> [!NOTE]
> This plugin should be registered in your `config/plugins.php` file, or loaded from your application bootstrap.

```bash
bin/cake plugin load Crustum/Mcp
```

Alternatively, you can load the plugin in your `Application.php`:

```php
<?php
declare(strict_types=1);

use Crustum\Mcp\McpPlugin;

// In src/Application.php
public function bootstrap(): void
{
    parent::bootstrap();

    $this->addPlugin(McpPlugin::class);
}
```

Loading the plugin registers container bindings, console commands, MCP middleware aliases, and plugin routes used for HTTP servers and OAuth discovery.

<a name="publishing-configuration"></a>
### Publishing Configuration

After installing the plugin, install the configuration with the manifest system:

```bash
bin/cake manifest install --plugin=Crustum/Mcp
```

This copies `config/mcp.php` into your application and appends loading of that file to `config/bootstrap.php`.

Alternatively, copy the plugin config manually and load it during bootstrap:

```php
<?php
declare(strict_types=1);

use Cake\Core\Configure;

Configure::load('mcp', 'default');
```

All of your application's MCP configuration is stored under the `Mcp` key:

```php
<?php
declare(strict_types=1);

use Cake\Http\Middleware\BodyParserMiddleware;
use Crustum\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Crustum\Mcp\Server\Middleware\ReorderJsonAccept;

return [
    'Mcp' => [
        'require_web_auth_middleware' => false,
        'Middleware' => [
            'bodyParser' => [
                'class' => BodyParserMiddleware::class,
            ],
            'reorderJsonAccept' => [
                'class' => ReorderJsonAccept::class,
            ],
            'wwwAuthenticate' => [
                'class' => AddWwwAuthenticateHeader::class,
            ],
        ],
        'Servers' => [
            // ['route' => 'mcp/weather', 'server' => \App\Mcp\Servers\WeatherServer::class, 'middleware' => []],
        ],
        'local' => [
            // 'weather' => \App\Mcp\Servers\WeatherServer::class,
        ],
        'base_url' => null,
        'redirect_domains' => [
            '*',
        ],
        'custom_schemes' => [
        ],
        'authorization_server' => null,
        'oauth' => [
            'enabled' => true,
            'prefix' => 'oauth',
            'authorization_endpoint' => null,
            'token_endpoint' => null,
            'routes' => [
            ],
        ],
    ],
];
```

> [!TIP]
> Register web servers in `Mcp.Servers` and local STDIO servers in `Mcp.local`. The registrar reads these entries during bootstrap so you do not need to wire every server by hand in a routes file. Set `require_web_auth_middleware` to `true` in production so web servers without a `middleware` list fail at registration.

<a name="creating-servers"></a>
## Creating Servers

You can create an MCP server using Bake. Servers act as the central communication point that exposes MCP capabilities like tools, resources, and prompts to AI clients:

```bash
bin/cake bake mcp_server WeatherServer
```

This command will create a new server class in the `src/Mcp/Servers` directory (or your configured bake namespace). The generated server class extends the plugin's base `Crustum\Mcp\Server` class and provides attributes and properties for configuring the server and registering tools, resources, and prompts:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Servers;

use Crustum\Mcp\Server;
use Crustum\Mcp\Server\Attributes\Instructions;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Attributes\Version;

#[Name('Weather Server')]
#[Version('1.0.0')]
#[Instructions('This server provides weather information and forecasts.')]
class WeatherServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Crustum\Mcp\Server\Tool>|\Crustum\Mcp\Server\Tool>
     */
    public array $tools = [
        // GetCurrentWeatherTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Crustum\Mcp\Server\Resource>|\Crustum\Mcp\Server\Resource>
     */
    public array $resources = [
        // WeatherGuidelinesResource::class,
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Crustum\Mcp\Server\Prompt>|\Crustum\Mcp\Server\Prompt>
     */
    public array $prompts = [
        // DescribeWeatherPrompt::class,
    ];
}
```

The `#[Name]`, `#[Version]`, and `#[Instructions]` attributes advertise server metadata during the MCP initialize handshake. Instructions are especially useful: they tell the model how your server is meant to be used, which tools to prefer, and any constraints that apply.

You may also register class instances instead of class strings when a primitive needs constructor arguments that the container does not resolve automatically:

```php
public array $tools = [
    new CurrentWeatherTool($weatherRepository),
];
```

<a name="server-registration"></a>
### Server Registration

Once you've created a server, you must register it so AI clients can reach it. The plugin provides two registration styles: **web** for HTTP-accessible servers and **local** for STDIO command-line servers.

Registration is handled by `Crustum\Mcp\Server\Registrar`. You may register servers from configuration (`Mcp.Servers` / `Mcp.local`) or explicitly in a routes file using the registrar API.

<a name="web-servers"></a>
### Web Servers

Web servers are the most common type of server and are accessible via HTTP requests, making them ideal for remote AI clients or web-based integrations. Register a web server using the `web` method and a CakePHP `RouteBuilder`:

```php
<?php
declare(strict_types=1);

use App\Mcp\Servers\WeatherServer;
use Cake\Routing\RouteBuilder;
use Crustum\Mcp\Server\Registrar;

/** @var \Cake\Routing\RouteBuilder $routes */
$registrar = Registrar::getInstance();

$registrar->web($routes, '/mcp/weather', WeatherServer::class);
```

Just like normal routes, you may apply middleware aliases to protect your web servers. Pass middleware as the fourth argument:

```php
$registrar->web($routes, '/mcp/weather', WeatherServer::class, [
    'authentication',
    'throttle',
]);
```

Middleware aliases must be registered with CakePHP's middleware queue (or via `Mcp.Middleware` for MCP-specific middleware). The plugin inserts its MCP request processor into the stack so POST JSON-RPC traffic is handled by the server class.

You may also declare web servers in configuration without writing route code:

```php
'Servers' => [
    [
        'route' => 'mcp/weather',
        'server' => \App\Mcp\Servers\WeatherServer::class,
        'middleware' => [],
    ],
],
```

> [!NOTE]
> HTTP MCP traffic is handled by `Crustum\Mcp\Controller\ServerController`. On CakeDC Auth hosts, merge the plugin `config/permissions.php` fragment (includes `Server` `bypassAuth`) so anonymous JSON-RPC is not redirected to login. Attach bearer/OAuth middleware on the MCP route itself when the server must be protected.

<a name="local-servers"></a>
### Local Servers

Local servers run as CakePHP console commands over STDIO. They are ideal for IDE agents, desktop MCP hosts, and local development tools. Register a local server using the `local` method:

```php
<?php
declare(strict_types=1);

use App\Mcp\Servers\WeatherServer;
use Crustum\Mcp\Server\Registrar;

Registrar::getInstance()->local('weather', WeatherServer::class);
```

Or via configuration:

```php
'local' => [
    'weather' => \App\Mcp\Servers\WeatherServer::class,
],
```

Once registered, you typically should not need to start the process manually while developing. Configure your MCP client (AI agent) to launch the server, or use the [MCP Inspector](#mcp-inspector). When you do need to start it yourself:

```bash
bin/cake mcp start weather
```

The `weather` argument is the local handle you registered. The command boots the server class and speaks MCP over standard input and output.

> [!TIP]
> The same `WeatherServer` class can be registered as both a web route and a local handle. That lets remote clients use HTTP while your IDE uses STDIO, without duplicating tools or resources.

<a name="tools"></a>
## Tools

Tools enable your server to expose functionality that AI clients can call. They allow language models to perform actions, run code, or interact with external systems:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Tools;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Tool;
use Illuminate\Contracts\JsonSchema\JsonSchema;

#[Description('Fetches the current weather forecast for a specified location.')]
class CurrentWeatherTool extends Tool
{
    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response
     */
    public function handle(Request $request): Response
    {
        $location = $request->get('location');

        return Response::text('The weather is sunny in ' . $location . '.');
    }

    /**
     * Get the tool's input schema.
     *
     * @param \Illuminate\Contracts\JsonSchema\JsonSchema $schema JSON schema builder
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'location' => $schema->string()
                ->description('The location to get the weather for.')
                ->required(),
        ];
    }
}
```

<a name="creating-tools"></a>
### Creating Tools

To create a tool, run the Bake command:

```bash
bin/cake bake mcp_tool CurrentWeatherTool
```

After creating a tool, register it in your server's `$tools` property:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\CurrentWeatherTool;
use Crustum\Mcp\Server;
use Crustum\Mcp\Server\Attributes\Instructions;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Attributes\Version;

#[Name('Weather Server')]
#[Version('1.0.0')]
#[Instructions('This server provides weather information and forecasts.')]
class WeatherServer extends Server
{
    /**
     * @var array<int, class-string<\Crustum\Mcp\Server\Tool>|\Crustum\Mcp\Server\Tool>
     */
    public array $tools = [
        CurrentWeatherTool::class,
    ];
}
```

<a name="tool-name-title-description"></a>
#### Tool Name, Title, and Description

By default, the tool's name and title are derived from the class name. For example, `CurrentWeatherTool` will have a name of `current-weather` and a title of `Current Weather Tool`. You may customize these values using the `Name` and `Title` attributes:

```php
<?php
declare(strict_types=1);

use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Attributes\Title;
use Crustum\Mcp\Server\Tool;

#[Name('get-optimistic-weather')]
#[Title('Get Optimistic Weather Forecast')]
class CurrentWeatherTool extends Tool
{
}
```

Tool descriptions are not automatically generated. You should always provide a meaningful description using the `Description` attribute:

```php
use Crustum\Mcp\Server\Attributes\Description;

#[Description('Fetches the current weather forecast for a specified location.')]
class CurrentWeatherTool extends Tool
{
}
```

> [!NOTE]
> The description is a critical part of the tool's metadata, as it helps AI models understand when and how to use the tool effectively.

<a name="tool-input-schemas"></a>
### Tool Input Schemas

Tools can define input schemas to specify what arguments they accept from AI clients. Use the JSON Schema builder (`JsonSchema`) from the `illuminate/json-schema` package (a plugin dependency used only for MCP/tool schemas) to define your tool's input requirements:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Tools;

use Crustum\Mcp\Server\Tool;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CurrentWeatherTool extends Tool
{
    /**
     * Get the tool's input schema.
     *
     * @param \Illuminate\Contracts\JsonSchema\JsonSchema $schema JSON schema builder
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'location' => $schema->string()
                ->description('The location to get the weather for.')
                ->required(),

            'units' => $schema->string()
                ->enum(['celsius', 'fahrenheit'])
                ->description('The temperature units to use.')
                ->default('celsius'),
        ];
    }
}
```

The schema is advertised to MCP clients during `tools/list`. Clear descriptions and enums help models choose valid arguments without trial and error.

<a name="tool-output-schemas"></a>
### Tool Output Schemas

Tools can define [output schemas](https://modelcontextprotocol.io/specification/2025-06-18/server/tools#output-schema) to specify the structure of their responses. This enables better integration with AI clients that need parseable tool results. Use the `outputSchema` method to define your tool's output structure:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Tools;

use Crustum\Mcp\Server\Tool;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CurrentWeatherTool extends Tool
{
    /**
     * Get the tool's output schema.
     *
     * @param \Illuminate\Contracts\JsonSchema\JsonSchema $schema JSON schema builder
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'temperature' => $schema->number()
                ->description('Temperature in Celsius')
                ->required(),

            'conditions' => $schema->string()
                ->description('Weather conditions')
                ->required(),

            'humidity' => $schema->integer()
                ->description('Humidity percentage')
                ->required(),
        ];
    }
}
```

When you return structured content from the tool, clients that understand output schemas can validate and consume the payload more reliably.

<a name="validating-tool-arguments"></a>
### Validating Tool Arguments

JSON Schema definitions provide a basic structure for tool arguments, but you may also want to enforce more complex validation rules.

The MCP request integrates with CakePHP's `Validator`. Validate incoming tool arguments within your tool's `handle` method using `validateWith`:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Tools;

use Cake\Validation\Validator;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;

class CurrentWeatherTool extends Tool
{
    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response
     */
    public function handle(Request $request): Response
    {
        $validator = new Validator();
        $validator
            ->requirePresence('location')
            ->notEmptyString('location')
            ->maxLength('location', 100)
            ->inList('units', ['celsius', 'fahrenheit']);

        $validated = $request->validateWith($validator);

        return Response::text('Forecast ready for ' . $validated['location']);
    }
}
```

On validation failure, AI clients will act based on the error messages you provide. As such, it is critical to provide clear and actionable error messages using custom validation messages:

```php
$validator = new Validator();
$validator
    ->requirePresence('location', true, 'You must specify a location to get the weather for. For example, "New York City" or "Tokyo".')
    ->notEmptyString('location', 'You must specify a location to get the weather for. For example, "New York City" or "Tokyo".')
    ->inList('units', ['celsius', 'fahrenheit'], 'You must specify either "celsius" or "fahrenheit" for the units.');
```

<a name="tool-dependency-injection"></a>
#### Tool Dependency Injection

The CakePHP dependency injection container is used to resolve tools when they are invoked. As a result, you are able to type-hint any dependencies your tool may need in its constructor. The declared dependencies will automatically be resolved and injected into the tool instance when the container can provide them:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Service\WeatherService;
use Crustum\Mcp\Server\Tool;

class CurrentWeatherTool extends Tool
{
    /**
     * @param \App\Service\WeatherService $weather Weather service
     */
    public function __construct(
        protected WeatherService $weather,
    ) {
    }
}
```

In addition to constructor injection, you may also type-hint dependencies in your tool's `handle()` method. The container invoker will automatically resolve and inject the dependencies when the method is called:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Service\WeatherService;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;

class CurrentWeatherTool extends Tool
{
    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @param \App\Service\WeatherService $weather Weather service
     * @return \Crustum\Mcp\Response
     */
    public function handle(Request $request, WeatherService $weather): Response
    {
        $location = $request->get('location');
        $forecast = $weather->getForecastFor($location);

        return Response::text($forecast);
    }
}
```

<a name="tool-annotations"></a>
### Tool Annotations

You may enhance your tools with [annotations](https://modelcontextprotocol.io/specification/2025-06-18/schema#toolannotations) to provide additional metadata to AI clients. These annotations help AI models understand the tool's behavior and capabilities. Annotations are added to tools via attributes:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Tools;

use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsIdempotent;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsIdempotent]
#[IsReadOnly]
class CurrentWeatherTool extends Tool
{
}
```

Available annotations include:

| Annotation | Type | Description |
| --- | --- | --- |
| `#[IsReadOnly]` | boolean | Indicates the tool does not modify its environment. |
| `#[IsDestructive]` | boolean | Indicates the tool may perform destructive updates (only meaningful when not read-only). |
| `#[IsIdempotent]` | boolean | Indicates repeated calls with same arguments have no additional effect (when not read-only). |
| `#[IsOpenWorld]` | boolean | Indicates the tool may interact with external entities. |

Annotation values can be explicitly set using boolean arguments:

```php
<?php
declare(strict_types=1);

use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsDestructive;
use Crustum\Mcp\Server\Tools\Annotations\IsIdempotent;
use Crustum\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[IsIdempotent(true)]
class CurrentWeatherTool extends Tool
{
}
```

<a name="conditional-tool-registration"></a>
### Conditional Tool Registration

You may conditionally register tools at runtime by implementing the `shouldRegister` method in your tool class. This method allows you to determine whether a tool should be available based on application state, configuration, or request parameters:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Tools;

use Crustum\Mcp\Request;
use Crustum\Mcp\Server\Tool;

class CurrentWeatherTool extends Tool
{
    /**
     * Determine if the tool should be registered.
     *
     * @param \Crustum\Mcp\Request|null $request MCP request when available
     * @return bool
     */
    public function shouldRegister(?Request $request = null): bool
    {
        $identity = $request?->getIdentity();

        return $identity !== null && method_exists($identity, 'isSubscribed') && $identity->isSubscribed();
    }
}
```

When a tool's `shouldRegister` method returns `false`, it will not appear in the list of available tools and cannot be invoked by AI clients.

<a name="tool-responses"></a>
### Tool Responses

Tools should return an instance of `Crustum\Mcp\Response` (or a `ResponseFactory`, an array of responses, a string, or a generator). The `Response` class provides several convenient methods for creating different types of responses.

For simple text responses, use the `text` method:

```php
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;

/**
 * Handle the tool request.
 *
 * @param \Crustum\Mcp\Request $request MCP request
 * @return \Crustum\Mcp\Response
 */
public function handle(Request $request): Response
{
    return Response::text('Weather Summary: Sunny, 72°F');
}
```

To indicate an error occurred during tool execution, use the `error` method:

```php
return Response::error('Unable to fetch weather data. Please try again.');
```

To return image or audio content, use the `image` and `audio` methods:

```php
return Response::image(
    (string)file_get_contents(WWW_ROOT . 'img' . DS . 'weather' . DS . 'radar.png'),
    'image/png',
);

return Response::audio(
    (string)file_get_contents(WWW_ROOT . 'files' . DS . 'weather' . DS . 'alert.mp3'),
    'audio/mpeg',
);
```

You may return the contents of an HTML file as text using `html`. Relative paths resolve from your application `ROOT`:

```php
return Response::html('templates/mcp/weather_summary.html');
```

For CakePHP templates, use `view`:

```php
return Response::view('Mcp/weather_summary', [
    'location' => $location,
    'forecast' => $forecast,
]);
```

<a name="multiple-content-responses"></a>
#### Multiple Content Responses

Tools can return multiple pieces of content by returning an array of `Response` instances:

```php
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;

/**
 * Handle the tool request.
 *
 * @param \Crustum\Mcp\Request $request MCP request
 * @return array<int, \Crustum\Mcp\Response>
 */
public function handle(Request $request): array
{
    return [
        Response::text('Weather Summary: Sunny, 72°F'),
        Response::text("**Detailed Forecast**\n- Morning: 65°F\n- Afternoon: 78°F\n- Evening: 70°F"),
    ];
}
```

<a name="structured-responses"></a>
#### Structured Responses

Tools can return [structured content](https://modelcontextprotocol.io/specification/2025-06-18/server/tools#structured-content) using the `structured` method. This provides parseable data for AI clients while maintaining backward compatibility with a JSON-encoded text representation:

```php
return Response::structured([
    'temperature' => 22.5,
    'conditions' => 'Partly cloudy',
    'humidity' => 65,
]);
```

If you need to provide custom text alongside structured content, use the `withStructuredContent` method on the response factory:

```php
return Response::make(
    Response::text('Weather is 22.5°C and sunny'),
)->withStructuredContent([
    'temperature' => 22.5,
    'conditions' => 'Sunny',
]);
```

<a name="streaming-responses"></a>
#### Streaming Responses

For long-running operations or real-time data streaming, tools can return a [generator](https://www.php.net/manual/en/language.generators.overview.php) from their `handle` method. This enables sending intermediate updates to the client before the final response:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Tools;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Generator;

class CurrentWeatherTool extends Tool
{
    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Generator<int, \Crustum\Mcp\Response>
     */
    public function handle(Request $request): Generator
    {
        $locations = (array)$request->get('locations', []);

        foreach ($locations as $index => $location) {
            yield Response::notification('processing/progress', [
                'current' => $index + 1,
                'total' => count($locations),
                'location' => $location,
            ]);

            yield Response::text($this->forecastFor((string)$location));
        }
    }

    /**
     * @param string $location Location name
     * @return string
     */
    protected function forecastFor(string $location): string
    {
        return 'Forecast for ' . $location;
    }
}
```

When using web-based servers, streaming responses automatically open an SSE (Server-Sent Events) stream, sending each yielded message as an event to the client.

<a name="prompts"></a>
## Prompts

[Prompts](https://modelcontextprotocol.io/specification/2025-06-18/server/prompts) enable your server to share reusable prompt templates that AI clients can use to interact with language models. They provide a standardized way to structure common queries and interactions.

<a name="creating-prompts"></a>
### Creating Prompts

To create a prompt, run the Bake command:

```bash
bin/cake bake mcp_prompt DescribeWeatherPrompt
```

After creating a prompt, register it in your server's `$prompts` property:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Prompts\DescribeWeatherPrompt;
use Crustum\Mcp\Server;

class WeatherServer extends Server
{
    /**
     * @var array<int, class-string<\Crustum\Mcp\Server\Prompt>|\Crustum\Mcp\Server\Prompt>
     */
    public array $prompts = [
        DescribeWeatherPrompt::class,
    ];
}
```

<a name="prompt-name-title-and-description"></a>
#### Prompt Name, Title, and Description

By default, the prompt's name and title are derived from the class name. For example, `DescribeWeatherPrompt` will have a name of `describe-weather` and a title of `Describe Weather Prompt`. You may customize these values using the `Name` and `Title` attributes:

```php
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Attributes\Title;
use Crustum\Mcp\Server\Prompt;

#[Name('weather-assistant')]
#[Title('Weather Assistant Prompt')]
class DescribeWeatherPrompt extends Prompt
{
}
```

Prompt descriptions are not automatically generated. You should always provide a meaningful description using the `Description` attribute:

```php
use Crustum\Mcp\Server\Attributes\Description;

#[Description('Generates a natural-language explanation of the weather for a given location.')]
class DescribeWeatherPrompt extends Prompt
{
}
```

> [!NOTE]
> The description is a critical part of the prompt's metadata, as it helps AI models understand when and how to get the best use out of the prompt.

<a name="prompt-arguments"></a>
### Prompt Arguments

Prompts can define arguments that allow AI clients to customize the prompt template with specific values. Use the `arguments` method to define what arguments your prompt accepts:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Prompts;

use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\Prompts\Argument;

class DescribeWeatherPrompt extends Prompt
{
    /**
     * Get the prompt's arguments.
     *
     * @return array<int, \Crustum\Mcp\Server\Prompts\Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'tone',
                description: 'The tone to use in the weather description (e.g., formal, casual, humorous).',
                required: true,
            ),
        ];
    }
}
```

<a name="validating-prompt-arguments"></a>
### Validating Prompt Arguments

Prompt arguments are automatically validated based on their definition, but you may also want to enforce more complex validation rules using CakePHP's `Validator`:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Prompts;

use Cake\Validation\Validator;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Prompt;

class DescribeWeatherPrompt extends Prompt
{
    /**
     * Handle the prompt request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response
     */
    public function handle(Request $request): Response
    {
        $validator = new Validator();
        $validator
            ->requirePresence('tone')
            ->notEmptyString('tone')
            ->maxLength('tone', 50);

        $validated = $request->validateWith($validator);
        $tone = $validated['tone'];

        return Response::text("Describe the weather in a {$tone} tone.");
    }
}
```

On validation failure, AI clients will act based on the error messages you provide. Prefer clear, actionable messages:

```php
$validator = new Validator();
$validator->requirePresence(
    'tone',
    true,
    'You must specify a tone for the weather description. Examples include "formal", "casual", or "humorous".',
);
```

<a name="prompt-dependency-injection"></a>
### Prompt Dependency Injection

The CakePHP container is used to resolve prompts. You may type-hint dependencies in the constructor or the `handle` method:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Prompts;

use App\Service\WeatherService;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Prompt;

class DescribeWeatherPrompt extends Prompt
{
    /**
     * @param \App\Service\WeatherService $weather Weather service
     */
    public function __construct(
        protected WeatherService $weather,
    ) {
    }

    /**
     * Handle the prompt request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @param \App\Service\WeatherService $weather Weather service
     * @return \Crustum\Mcp\Response
     */
    public function handle(Request $request, WeatherService $weather): Response
    {
        $available = $weather->isServiceAvailable();

        return Response::text($available ? 'Ask about the weather.' : 'Weather service is offline.');
    }
}
```

<a name="conditional-prompt-registration"></a>
### Conditional Prompt Registration

You may conditionally register prompts at runtime by implementing the `shouldRegister` method:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Prompts;

use Crustum\Mcp\Request;
use Crustum\Mcp\Server\Prompt;

class DescribeWeatherPrompt extends Prompt
{
    /**
     * @param \Crustum\Mcp\Request|null $request MCP request when available
     * @return bool
     */
    public function shouldRegister(?Request $request = null): bool
    {
        return $request?->getIdentity() !== null;
    }
}
```

When a prompt's `shouldRegister` method returns `false`, it will not appear in the list of available prompts and cannot be invoked by AI clients.

<a name="prompt-responses"></a>
### Prompt Responses

Prompts may return a single `Crustum\Mcp\Response` or an iterable of `Response` instances. These responses encapsulate the content that will be sent to the AI client:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Prompts;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Prompt;

class DescribeWeatherPrompt extends Prompt
{
    /**
     * Handle the prompt request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return array<int, \Crustum\Mcp\Response>
     */
    public function handle(Request $request): array
    {
        $tone = (string)$request->get('tone');

        $systemMessage = "You are a helpful weather assistant. Please provide a weather description in a {$tone} tone.";
        $userMessage = 'What is the current weather like in New York City?';

        return [
            Response::text($systemMessage)->asAssistant(),
            Response::text($userMessage),
        ];
    }
}
```

You can use the `asAssistant()` method to indicate that a response message should be treated as coming from the AI assistant, while regular messages are treated as user input.

<a name="resources"></a>
## Resources

[Resources](https://modelcontextprotocol.io/specification/2025-06-18/server/resources) enable your server to expose data and content that AI clients can read and use as context when interacting with language models. They provide a way to share static or dynamic information like documentation, configuration, or any data that helps inform AI responses.

<a name="creating-resources"></a>
### Creating Resources

To create a resource, run the Bake command:

```bash
bin/cake bake mcp_resource WeatherGuidelinesResource
```

After creating a resource, register it in your server's `$resources` property:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Resources\WeatherGuidelinesResource;
use Crustum\Mcp\Server;

class WeatherServer extends Server
{
    /**
     * @var array<int, class-string<\Crustum\Mcp\Server\Resource>|\Crustum\Mcp\Server\Resource>
     */
    public array $resources = [
        WeatherGuidelinesResource::class,
    ];
}
```

<a name="resource-name-title-and-description"></a>
#### Resource Name, Title, and Description

By default, the resource's name and title are derived from the class name. For example, `WeatherGuidelinesResource` will have a name of `weather-guidelines` and a title of `Weather Guidelines Resource`. You may customize these values using the `Name` and `Title` attributes:

```php
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Attributes\Title;
use Crustum\Mcp\Server\Resource;

#[Name('weather-api-docs')]
#[Title('Weather API Documentation')]
#[Description('Comprehensive guidelines for using the Weather API.')]
class WeatherGuidelinesResource extends Resource
{
}
```

> [!NOTE]
> The description is a critical part of the resource's metadata, as it helps AI models understand when and how to use the resource effectively.

<a name="resource-templates"></a>
### Resource Templates

[Resource templates](https://modelcontextprotocol.io/specification/2025-06-18/server/resources#resource-templates) enable your server to expose dynamic resources that match URI patterns with variables. Instead of defining a static URI for each resource, you can create a single resource that handles multiple URIs based on a template pattern.

<a name="creating-resource-templates"></a>
#### Creating Resource Templates

To create a resource template, implement the `HasUriTemplate` interface on your resource class and define a `uriTemplate` method that returns a `UriTemplate` instance:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Resources;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\MimeType;
use Crustum\Mcp\Server\Contracts\HasUriTemplate;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Support\UriTemplate;

#[Description('Access user files by ID')]
#[MimeType('text/plain')]
class UserFileResource extends Resource implements HasUriTemplate
{
    /**
     * Get the URI template for this resource.
     *
     * @return \Crustum\Mcp\Support\UriTemplate
     */
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('file://users/{userId}/files/{fileId}');
    }

    /**
     * Handle the resource request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response
     */
    public function handle(Request $request): Response
    {
        $userId = $request->get('userId');
        $fileId = $request->get('fileId');
        $content = "File {$fileId} for user {$userId}";

        return Response::text($content);
    }
}
```

When a resource implements the `HasUriTemplate` interface, it will be registered as a resource template rather than a static resource. AI clients can then request resources using URIs that match the template pattern, and the variables from the URI will be automatically extracted and made available in your resource's `handle` method.

<a name="uri-template-syntax"></a>
#### URI Template Syntax

URI templates use placeholders enclosed in curly braces to define variable segments in the URI:

```php
use Crustum\Mcp\Support\UriTemplate;

new UriTemplate('file://users/{userId}');
new UriTemplate('file://users/{userId}/files/{fileId}');
new UriTemplate('https://api.example.com/{version}/{resource}/{id}');
```

<a name="accessing-template-variables"></a>
#### Accessing Template Variables

When a URI matches your resource template, the extracted variables are automatically merged into the request and can be accessed using the `get` method:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Resources;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Contracts\HasUriTemplate;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Support\UriTemplate;

class UserProfileResource extends Resource implements HasUriTemplate
{
    /**
     * @return \Crustum\Mcp\Support\UriTemplate
     */
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('file://users/{userId}/profile');
    }

    /**
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response
     */
    public function handle(Request $request): Response
    {
        $userId = $request->get('userId');
        $uri = $request->uri();

        return Response::text("Profile for user {$userId} from {$uri}");
    }
}
```

The `Request` object provides both the extracted variables and the original URI that was requested, giving you full context for processing the resource request.

<a name="resource-uri-and-mime-type"></a>
### Resource URI and MIME Type

Each resource is identified by a unique URI and has an associated MIME type that helps AI clients understand the resource's format.

By default, the resource's URI is generated based on the resource's name. The default MIME type is `text/plain`. You may customize these values using the `Uri` and `MimeType` attributes:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Resources;

use Crustum\Mcp\Server\Attributes\MimeType;
use Crustum\Mcp\Server\Attributes\Uri;
use Crustum\Mcp\Server\Resource;

#[Uri('weather://resources/guidelines')]
#[MimeType('application/pdf')]
class WeatherGuidelinesResource extends Resource
{
}
```

The URI and MIME type help AI clients determine how to process and interpret the resource content appropriately.

<a name="resource-request"></a>
### Resource Request

Unlike tools and prompts, resources cannot define input schemas or arguments. However, you can still interact with the request object within your resource's `handle` method — for identity checks, URI template variables, and session metadata:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Resources;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Resource;

class WeatherGuidelinesResource extends Resource
{
    /**
     * Handle the resource request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response
     */
    public function handle(Request $request): Response
    {
        $identity = $request->getIdentity();

        return Response::text('Guidelines for authenticated readers.');
    }
}
```

<a name="resource-dependency-injection"></a>
### Resource Dependency Injection

The CakePHP container is used to resolve resources. Type-hint dependencies in the constructor or `handle` method:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Service\WeatherService;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Resource;

class WeatherGuidelinesResource extends Resource
{
    /**
     * Handle the resource request.
     *
     * @param \App\Service\WeatherService $weather Weather service
     * @return \Crustum\Mcp\Response
     */
    public function handle(WeatherService $weather): Response
    {
        return Response::text($weather->guidelines());
    }
}
```

<a name="resource-annotations"></a>
### Resource Annotations

You may enhance your resources with [annotations](https://modelcontextprotocol.io/specification/2025-06-18/schema#resourceannotations) to provide additional metadata to AI clients:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Resources;

use Crustum\Mcp\Enums\Role;
use Crustum\Mcp\Server\Annotations\Audience;
use Crustum\Mcp\Server\Annotations\LastModified;
use Crustum\Mcp\Server\Annotations\Priority;
use Crustum\Mcp\Server\Resource;

#[Audience(Role::User)]
#[LastModified('2025-01-12T15:00:58Z')]
#[Priority(0.9)]
class UserDashboardResource extends Resource
{
}
```

Available annotations include:

| Annotation | Type | Description |
| --- | --- | --- |
| `#[Audience]` | Role or array | Specifies the intended audience (`Role::User`, `Role::Assistant`, or both). |
| `#[Priority]` | float | A numerical score between 0.0 and 1.0 indicating resource importance. |
| `#[LastModified]` | string | An ISO 8601 timestamp showing when the resource was last updated. |

<a name="conditional-resource-registration"></a>
### Conditional Resource Registration

You may conditionally register resources at runtime by implementing the `shouldRegister` method:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Resources;

use Crustum\Mcp\Request;
use Crustum\Mcp\Server\Resource;

class WeatherGuidelinesResource extends Resource
{
    /**
     * @param \Crustum\Mcp\Request|null $request MCP request when available
     * @return bool
     */
    public function shouldRegister(?Request $request = null): bool
    {
        return $request?->getIdentity() !== null;
    }
}
```

When a resource's `shouldRegister` method returns `false`, it will not appear in the list of available resources and cannot be accessed by AI clients.

<a name="resource-responses"></a>
### Resource Responses

Resources should return an instance of `Crustum\Mcp\Response`. For simple text content, use the `text` method:

```php
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;

/**
 * @param \Crustum\Mcp\Request $request MCP request
 * @return \Crustum\Mcp\Response
 */
public function handle(Request $request): Response
{
    return Response::text($weatherData);
}
```

<a name="resource-link-responses"></a>
#### Resource Link Responses

To return a resource link, use the `resourceLink` method. Unlike an embedded resource, a resource link returns a URI pointer that the AI client fetches independently:

```php
return Response::resourceLink(
    uri: 'file:///data/report.json',
    name: 'monthly-report',
    mimeType: 'application/json',
);
```

You may also pass a registered resource class or instance, which will automatically inherit the resource's URI, name, title, description, and MIME type:

```php
return Response::resourceLink(WeatherForecastResource::class);
```

<a name="resource-blob-responses"></a>
#### Blob Responses

To return blob content, use the `blob` method:

```php
return Response::blob((string)file_get_contents(WWW_ROOT . 'img' . DS . 'weather' . DS . 'radar.png'));
```

When returning blob content, the MIME type will be determined by your resource's configured MIME type:

```php
use Crustum\Mcp\Server\Attributes\MimeType;
use Crustum\Mcp\Server\Resource;

#[MimeType('image/png')]
class WeatherRadarResource extends Resource
{
}
```

<a name="resource-error-responses"></a>
#### Error Responses

To indicate an error occurred while reading a resource, use the `error` method:

```php
return Response::error('Unable to fetch weather data for the specified location.');
```

<a name="apps"></a>
## Apps

The CakePHP MCP plugin supports [MCP Apps](https://modelcontextprotocol.io/extensions/apps/overview), an extension of the Model Context Protocol that allows tools to render interactive HTML applications within sandboxed iframes in supported hosts. This allows you to build dashboards, forms, visualizations, and other rich experiences that go beyond plain text responses.

An MCP app consists of two parts working together:

- An **app resource** that returns the self-contained HTML for your application.
- A **tool** that is linked to the app resource using the `#[RendersApp]` attribute. When the tool is called, the host fetches and renders the linked resource.

<a name="creating-app-resources"></a>
### Creating App Resources

You may create an app resource using Bake:

```bash
bin/cake bake mcp_app_resource WeatherDashboardApp
```

This command creates a PHP class under your MCP resources namespace and a CakePHP template for the app UI. The generated class extends `AppResource` and renders through the plugin layout:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Resources;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\AppResource;
use Crustum\Mcp\Server\Attributes\AppMeta;
use Crustum\Mcp\Server\Attributes\Description;

#[Description('An interactive weather dashboard.')]
#[AppMeta]
class WeatherDashboardApp extends AppResource
{
    /**
     * Handle the app resource request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response
     */
    public function handle(Request $request): Response
    {
        return Response::view('Mcp/weather_dashboard_app', [
            'title' => $this->title(),
        ], [], 'Crustum/Mcp.mcp_app');
    }
}
```

`AppResource` extends the base `Resource` class and automatically configures the `ui://` URI scheme and the `text/html;profile=mcp-app` MIME type required by the MCP Apps specification. Like any other resource, you must register it in your server's `$resources` array.

The `Crustum/Mcp.mcp_app` layout injects the client-side MCP SDK and any configured library scripts so the iframe can talk back to the server. Inside your template you can call server tools, open links, and react to host theming. For the full client-side API, refer to the [MCP Apps specification](https://modelcontextprotocol.io/extensions/apps/overview).

<a name="rendering-apps-from-tools"></a>
### Rendering Apps From Tools

To display an app resource, link a tool to it using the `#[RendersApp]` attribute. When the tool is called, the plugin includes the resource's URI in the tool metadata so the host can render the app in a sandboxed iframe:

```php
<?php
declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Resources\WeatherDashboardApp;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\RendersApp;
use Crustum\Mcp\Server\Tool;

#[RendersApp(resource: WeatherDashboardApp::class)]
class ShowWeatherDashboard extends Tool
{
    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response
     */
    public function handle(Request $request): Response
    {
        return Response::text('Weather dashboard loaded.');
    }
}
```

The plugin automatically advertises the `io.modelcontextprotocol/ui` capability whenever any `AppResource` is registered, so no additional server configuration is required.

<a name="app-tool-visibility"></a>
### App Tool Visibility

Each `#[RendersApp]` tool can limit who may invoke it via the `visibility` argument. This is useful for exposing private, app-only tools that the UI calls to load or refresh data without making those tools visible to the model:

```php
use Crustum\Mcp\Server\Attributes\RendersApp;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Ui\Enums\Visibility;

#[RendersApp(resource: WeatherDashboardApp::class, visibility: [Visibility::App])]
class GetWeatherData extends Tool
{
}
```

The `Visibility` enum has two cases, `Model` and `App`, and defaults to both. Use `[Visibility::App]` for backend actions the UI calls directly, or `[Visibility::Model]` to make a tool unavailable to the UI.

<a name="app-configuration"></a>
### App Configuration

The `#[AppMeta]` attribute on your app resource configures the iframe's Content Security Policy, browser permissions, and any library scripts that should be included in the view's `<head>`:

```php
use Crustum\Mcp\Server\AppResource;
use Crustum\Mcp\Server\Attributes\AppMeta;
use Crustum\Mcp\Server\Ui\Enums\Library;
use Crustum\Mcp\Server\Ui\Enums\Permission;

#[AppMeta(
    connectDomains: ['https://api.weather.com'],
    permissions: [Permission::Geolocation],
    libraries: [Library::Tailwind, Library::Alpine],
)]
class WeatherDashboardApp extends AppResource
{
}
```

The `Library` enum includes pre-configured CDN scripts for common front-end libraries, such as `Library::Tailwind` and `Library::Alpine`, and their CDN origins are automatically merged into the CSP. The `Permission` enum covers browser permissions such as `Camera`, `Microphone`, `Geolocation`, and `ClipboardWrite`.

For computed or dynamic configuration, override the `appMeta` method on your resource using the fluent `AppMeta`, `Csp`, and `Permissions` builders from the `Crustum\Mcp\Server\Ui` namespace.

<a name="metadata"></a>
## Metadata

The plugin also supports the `_meta` field as defined in the [MCP specification](https://modelcontextprotocol.io/specification/2025-06-18/basic#meta), which is required by certain MCP clients or integrations. Metadata can be applied to all MCP primitives, including tools, resources, and prompts, as well as their responses.

You can attach metadata to individual response content using the `withMeta` method:

```php
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;

/**
 * @param \Crustum\Mcp\Request $request MCP request
 * @return \Crustum\Mcp\Response
 */
public function handle(Request $request): Response
{
    return Response::text('The weather is sunny.')
        ->withMeta(['source' => 'weather-api', 'cached' => true]);
}
```

For result-level metadata that applies to the entire response envelope, wrap your responses with `Response::make` and call `withMeta` on the returned response factory instance:

```php
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;

/**
 * @param \Crustum\Mcp\Request $request MCP request
 * @return \Crustum\Mcp\ResponseFactory
 */
public function handle(Request $request): ResponseFactory
{
    return Response::make(
        Response::text('The weather is sunny.'),
    )->withMeta(['request_id' => '12345']);
}
```

To attach metadata to a tool, resource, or prompt itself, define a `$meta` property on the class:

```php
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Tool;

#[Description('Fetches the current weather forecast.')]
class CurrentWeatherTool extends Tool
{
    protected ?array $meta = [
        'version' => '2.0',
        'author' => 'Weather Team',
    ];
}
```

<a name="icons"></a>
## Icons

MCP clients can display icons for your server and its primitives. You may declare icons on a server, tool, resource, or prompt using the `Icon` attribute:

```php
use Crustum\Mcp\Enums\IconTheme;
use Crustum\Mcp\Server;
use Crustum\Mcp\Server\Attributes\Icon;

#[Icon('mcp/server.png', mimeType: 'image/png', sizes: ['48x48'])]
#[Icon('mcp/server-dark.svg', theme: IconTheme::Dark)]
class WeatherServer extends Server
{
}
```

The `Icon` attribute is repeatable, so you may declare multiple icons to provide different sizes or light and dark theme variants.

Alternatively, you may define icons programmatically by overriding the `icons` method, which is useful when an icon depends on runtime conditions:

```php
use Crustum\Mcp\Schema\Icon;
use Crustum\Mcp\Server\Tool;

class CurrentWeatherTool extends Tool
{
    /**
     * Get the tool's icons.
     *
     * @return list<\Crustum\Mcp\Schema\Icon>
     */
    public function icons(): array
    {
        return [
            Icon::from('mcp/tool.png', mimeType: 'image/png'),
        ];
    }
}
```

Icons defined via the attribute and the `icons` method are combined automatically. Icon paths are resolved as follows:

- Paths with a URI scheme, such as `https:` or `data:`, are used as-is.
- Relative paths are resolved using `Mcp.asset_base_url` when configured, otherwise your application base URL.

<a name="authentication"></a>
## Authentication

Just like routes, you can authenticate web MCP servers with middleware. Adding authentication to your MCP server will require a client to authenticate before using any capability of the server.

There are two common approaches: OAuth 2.1 via Tessera (`crustum/tessera`), or bearer-token middleware that inspects the `Authorization` header.

Web MCP endpoints are **unauthenticated until you attach middleware**. That matches the package’s role as a capability framework: local demos and STDIO servers stay easy to wire, while production hosts must opt into auth. CakeDC\Auth `bypassAuth` on `Server` only skips HTML login redirects — it is **not** MCP authorization. STDIO / `Mcp.local` servers trust the process boundary; HTTP is the public threat surface.

<a name="oauth"></a>
### OAuth 2.1

The most robust way to protect your web-based MCP servers is with OAuth using Tessera, which acts as the OAuth authorization server for the plugin.

When authenticating your MCP server via OAuth, invoke the `oauthRoutes` method to register the required OAuth2 discovery and dynamic client registration routes. Then apply your API authentication middleware to the web server registration:

```php
<?php
declare(strict_types=1);

use App\Mcp\Servers\WeatherServer;
use Cake\Routing\RouteBuilder;
use Crustum\Mcp\Server\Registrar;

/** @var \Cake\Routing\RouteBuilder $routes */
$registrar = Registrar::getInstance();

$registrar->oauthRoutes($routes);

$registrar->web($routes, '/mcp/weather', WeatherServer::class, [
    'authentication',
]);
```

`oauthRoutes()` registers `.well-known` metadata endpoints and the dynamic client registration route. It also ensures the `mcp:use` scope (`Registrar::OAUTH_SCOPE`) is registered with Tessera via `ensureMcpScope()`.

Protect an MCP HTTP server with Tessera bearer tokens by registering the plugin middleware alias (not applied globally by default) and listing it on that server:

```php
// config/mcp.php
'tesseraOAuth' => [
    'class' => \Crustum\Mcp\Server\Middleware\TesseraOAuthMiddleware::class,
    'apply' => false,
],

// config or bootstrap
Configure::write('Mcp.Servers', [
    [
        'route' => '/mcp/weather-oauth',
        'server' => WeatherServer::class,
        'middleware' => ['tesseraOAuth'],
    ],
]);
```

`TesseraOAuthMiddleware` validates the Bearer token, requires scope `mcp:use`, returns JSON 401/403 for `AddWwwAuthenticateHeader`, and sets `mcp.oauth.identity` (plus `identity`) when the token subject resolves via `Tessera.usersTable` (or `Mcp.oauth.usersTable`).

Cake Authentication may clear the request `identity` attribute on bearer API requests. Wire the shipped listener in bootstrap so tools still receive the OAuth principal via `$request->getIdentity()`:

```php
use Cake\Event\EventManager;
use Crustum\Mcp\Event\McpRequestBuildingEvent;
use Crustum\Mcp\Server\Middleware\TesseraOAuthMiddleware;

EventManager::instance()->on(
    McpRequestBuildingEvent::NAME,
    TesseraOAuthMiddleware::applyIdentity(...),
);
```

`applyIdentity` prefers `mcp.oauth.identity`, falling back to `identity` when the OAuth attribute is absent.

<a name="cakedc-public-permissions"></a>
#### CakeDC Auth public routes

MCP discovery and dynamic client registration are normal Cake controllers on the global middleware stack. With CakeDC Auth, merge the plugin config fragments so anonymous MCP clients are not redirected to HTML login:

```php
use Cake\Core\Plugin;

$permissions = array_merge(
    $permissions,
    require Plugin::path('Crustum/Tessera') . 'config' . DS . 'permissions.php',
    require Plugin::path('Crustum/Mcp') . 'config' . DS . 'permissions.php',
);
```

MCP’s fragment (`plugins/mcp/config/permissions.php`) covers `OAuthMetadata` and `OAuthRegister`. Tessera’s fragment covers authorize / token / consent. Rules already set `bypassAuth => true`.

Configure the authorization server issuer and optional endpoint overrides in `config/mcp.php`:

```php
'authorization_server' => env('APP_FULL_BASE_URL'),
'oauth' => [
    'enabled' => true,
    'prefix' => 'oauth',
    'authorization_endpoint' => null,
    'token_endpoint' => null,
],
```

Authorize and token endpoints typically live on Tessera (`/oauth/*`). MCP clients discover them through the well-known metadata served by the plugin.

> [!NOTE]
> In many MCP deployments, OAuth is primarily used as a translation layer to an authenticated principal. The plugin advertises and uses a single `mcp:use` scope for MCP access.

OAuth 2.1 is the documented authentication mechanism in the Model Context Protocol specification, and is the most widely supported among MCP clients. Prefer OAuth when your clients support it.

<a name="token-authentication"></a>
### Token Authentication

If you would like to protect your MCP server using bearer tokens without a full OAuth dance, attach middleware that validates the `Authorization: Bearer <token>` header on the MCP web route:

```php
$registrar->web($routes, '/mcp/demo', WeatherServer::class, [
    'mcpBearer',
]);
```

Your middleware is responsible for authenticating the token and, when useful, attaching an identity for tools to read. The plugin does not ship a default bearer middleware; host applications provide the integration that matches their token store.

<a name="custom-mcp-authentication"></a>
#### Custom MCP Authentication

If your application issues its own custom API tokens, assign any middleware you wish to your web MCP routes. Your custom middleware can inspect the `Authorization` header manually to authenticate the incoming MCP request.

To expose the authenticated principal inside tools, prompts, and resources, listen for the `Mcp.RequestBuilding` event and call `setIdentity()` on the mutable MCP request. Tools then read `$request->getIdentity()`.

For Tessera-protected MCP HTTP routes, prefer the shipped listener instead of hand-rolling the body:

```php
use Cake\Event\EventManager;
use Crustum\Mcp\Event\McpRequestBuildingEvent;
use Crustum\Mcp\Server\Middleware\TesseraOAuthMiddleware;

EventManager::instance()->on(
    McpRequestBuildingEvent::NAME,
    TesseraOAuthMiddleware::applyIdentity(...),
);
```

<a name="production-hardening"></a>
### Production Hardening

The published defaults favor local MCP clients (Inspector, Cursor, demos). For internet-facing deployments, tighten host config:

1. **Auth every public web server.** List `tesseraOAuth` (or your bearer middleware) on each `Mcp.Servers` entry / `Registrar::web(...)` call. Set `require_web_auth_middleware` to `true` so empty middleware lists fail at registration. When `debug` is enabled and the flag is off, the registrar logs a warning for unauthenticated web servers.

2. **Restrict DCR redirect URIs.** Default `redirect_domains` is `['*']` so dynamic client registration accepts any HTTPS redirect (convenient for local tooling). In production set an explicit allowlist of your client hosts, or disable public DCR and pre-register Tessera clients.

```php
'redirect_domains' => [
    'https://app.example.com',
    'http://localhost',
],
```

3. **Treat client OAuth `return_to` as untrusted.** The connect query parameter is stored in session and used after callback when no handler returns a response. Prefer a registered `oAuthRoutesFor` handler that redirects to a fixed app path, or only pass relative paths from trusted UI. Do not expose connect URLs that accept attacker-controlled absolute `return_to` values on public sites.

<a name="authorization"></a>
## Authorization

You may access the currently authenticated principal via the `$request->getIdentity()` method, allowing you to perform authorization checks within your MCP tools and resources:

```php
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;

/**
 * Handle the tool request.
 *
 * @param \Crustum\Mcp\Request $request MCP request
 * @return \Crustum\Mcp\Response
 */
public function handle(Request $request): Response
{
    $identity = $request->getIdentity();

    if ($identity === null || !method_exists($identity, 'can') || !$identity->can('read-weather')) {
        return Response::error('Permission denied.');
    }

    return Response::text('Authorized weather data.');
}
```

Throwing authentication or authorization exceptions from handlers is also supported: unauthenticated and forbidden failures are mapped to MCP error responses by the server.

<a name="client"></a>
## MCP Client

In addition to building servers, the CakePHP MCP plugin includes a client for connecting to other MCP servers, whether first-party or third-party. The client lets your application discover and call the tools, prompts, and resources exposed by an MCP server.

<a name="client-connecting"></a>
### Connecting to Servers

You may connect to an HTTP-accessible MCP server using the `Client::web` method, passing the server's URL:

```php
use Crustum\Mcp\Client;

$client = Client::web('https://mcp.example.com');
```

To connect to a local MCP server that runs as a command, use the `Client::local` method, providing the command and any arguments needed to start the server:

```php
use Crustum\Mcp\Client;

$client = Client::local('php', ['bin/cake.php', 'mcp', 'start', 'weather']);
```

The client connects lazily, automatically establishing the connection the first time you list or call tools. If you need to manage the connection manually, you may use the `connect`, `connected`, `ping`, and `disconnect` methods:

```php
$client->connect();

$client->ping();

if ($client->connected()) {
    // ...
}

$client->disconnect();
```

You may customize the request timeout using the `withTimeout` method:

```php
$client = Client::web('https://mcp.example.com')->withTimeout(30);
```

<a name="named-clients"></a>
### Named Clients

Instead of constructing a client each time you need it, you may register reusable, named clients with `ClientManager`. This is typically done during application bootstrap:

```php
use Crustum\Mcp\Client;
use Crustum\Mcp\Client\ClientManager;

ClientManager::getInstance()->registerClient(
    'github',
    fn() => Client::web('https://mcp.example.com'),
);
```

Once registered, you may resolve the client anywhere in your application by name:

```php
$client = ClientManager::getInstance()->client('github');
```

Named clients are resolved once and automatically disconnected at the end of the process lifecycle when the plugin's shutdown hook runs.

<a name="client-authentication"></a>
### Client Authentication

To connect to a web MCP server that is protected by a bearer token, use the `withToken` method. You may pass a token string or a closure that lazily resolves the token:

```php
use Crustum\Mcp\Client;

$client = Client::web('https://mcp.example.com')->withToken($token);

$client = Client::web('https://mcp.example.com')->withToken(
    fn(): string => (string)$identity->mcpToken(),
);
```

For servers protected by [OAuth 2.1](#oauth), configure the client using the `withOAuth` method:

```php
use Cake\Core\Configure;
use Crustum\Mcp\Client;
use Crustum\Mcp\Client\ClientManager;

ClientManager::getInstance()->registerClient(
    'github',
    fn() => Client::web('https://mcp.example.com')->withOAuth(
        clientId: Configure::read('Mcp.Clients.github.client_id'),
        clientSecret: Configure::read('Mcp.Clients.github.client_secret'),
    ),
);
```

> [!NOTE]
> The `clientId` and `clientSecret` arguments may be omitted when the MCP server supports [dynamic client registration](https://datatracker.ietf.org/doc/html/rfc7591), in which case the client registers itself automatically.

Next, register the OAuth routes for the named client using `Registrar::oAuthRoutesFor`. The closure you provide receives the client name and resulting `TokenSet` after the authorization code has been exchanged for an access token:

```php
use Cake\Http\Response;
use Crustum\Mcp\Client\OAuth\TokenSet;
use Crustum\Mcp\Server\Registrar;

Registrar::getInstance()->oAuthRoutesFor(
    $routes,
    'github',
    function (string $client, TokenSet $token): Response {
        // Persist $token->accessToken for the current user...

        return (new Response())->withLocation('/dashboard');
    },
);
```

This registers connect and callback routes for the named client. To begin the authorization flow, redirect the user to the connect route for that client.

The connect route accepts an optional `return_to` query parameter that is restored after the OAuth callback when your handler returns `null`. Prefer returning an explicit redirect from the handler (as in the example above) so production apps do not rely on an unvalidated `return_to` value. See [Production Hardening](#production-hardening).

<a name="client-tools"></a>
### Tools

You may retrieve the tools exposed by an MCP server using the `tools` method, which returns a collection of tools keyed by name:

```php
use Crustum\Mcp\Client\ClientManager;

$tools = ClientManager::getInstance()->client('github')->tools();

foreach ($tools as $tool) {
    $tool->name;
    $tool->title;
    $tool->description;
    $tool->inputSchema;
}
```

The client automatically paginates through all available tools. You may limit the number of tools returned using the `limit` argument:

```php
$tools = ClientManager::getInstance()->client('github')->tools(limit: 10);
```

To invoke a tool, use the `callTool` method, passing the tool name and an array of arguments. The returned `ToolResult` instance exposes the tool response:

```php
$result = ClientManager::getInstance()->client('github')->callTool('current-weather', [
    'location' => 'New York',
]);

$result->text();
(string)$result;
$result->isError;
$result->structuredContent;
```

Alternatively, you may call a tool directly from a listed tool instance:

```php
$tools = ClientManager::getInstance()->client('github')->tools();

$result = $tools['current-weather']->call([
    'location' => 'New York',
]);
```

<a name="client-prompts"></a>
### Prompts

You may retrieve the prompts exposed by an MCP server using the `prompts` method:

```php
$prompts = ClientManager::getInstance()->client('github')->prompts();

foreach ($prompts as $prompt) {
    $prompt->name;
    $prompt->title;
    $prompt->description;
    $prompt->arguments;
}
```

To retrieve a prompt, use the `getPrompt` method:

```php
$result = ClientManager::getInstance()->client('github')->getPrompt('describe-weather', [
    'location' => 'New York',
]);

$result->text();
$result->messages;
$result->description;
```

<a name="client-resources"></a>
### Resources

You may retrieve the resources exposed by an MCP server using the `resources` method, which returns a collection of resources keyed by URI:

```php
$resources = ClientManager::getInstance()->client('github')->resources();

foreach ($resources as $resource) {
    $resource->uri;
    $resource->name;
    $resource->title;
    $resource->description;
    $resource->mimeType;
    $resource->size;
}
```

To read a resource, use the `readResource` method:

```php
$result = ClientManager::getInstance()->client('github')->readResource('weather://guidelines');

$result->content();
(string)$result;
$result->mimeType();
$result->contents;
```

<a name="testing-servers"></a>
## Testing Servers

You may test your MCP servers using the built-in MCP Inspector or by writing unit tests.

<a name="mcp-inspector"></a>
### MCP Inspector

The [MCP Inspector](https://modelcontextprotocol.io/docs/tools/inspector) is an interactive tool for testing and debugging your MCP servers. Use it to connect to your server, verify authentication, and try out tools, resources, and prompts.

You may run the inspector for any registered server:

```bash
bin/cake mcp inspector mcp/weather

bin/cake mcp inspector weather
```

This command launches the MCP Inspector and provides the client settings that you may copy into your MCP client to ensure everything is configured correctly. If your web server is protected by authentication middleware, make sure to include the required headers, such as an `Authorization` bearer token, when connecting.

<a name="unit-tests"></a>
### Unit Tests

You may write unit tests for your MCP servers, tools, resources, and prompts.

To get started, invoke the desired primitive on the server that registers it. For example, to test a tool on the `WeatherServer`:

```php
<?php
declare(strict_types=1);

use App\Mcp\Servers\WeatherServer;
use App\Mcp\Tools\CurrentWeatherTool;

test('tool', function (): void {
    $response = WeatherServer::tool(CurrentWeatherTool::class, [
        'location' => 'New York City',
        'units' => 'fahrenheit',
    ]);

    $response
        ->assertOk()
        ->assertSee('The current weather in New York City is 72°F and sunny.');
});
```

Similarly, you may test prompts and resources:

```php
$response = WeatherServer::prompt(DescribeWeatherPrompt::class, [
    'tone' => 'casual',
]);

$response = WeatherServer::resource(WeatherGuidelinesResource::class);
```

You may also act as an authenticated principal by chaining the `actingAs` method before invoking the primitive:

```php
$response = WeatherServer::actingAs($user)->tool(CurrentWeatherTool::class, [
    'location' => 'Tokyo',
]);
```

Once you receive the response, you may use various assertion methods to verify the content and status of the response:

```php
$response->assertOk();
$response->assertSee('The current weather in New York City is 72°F and sunny.');
$response->assertHasErrors();
$response->assertHasErrors([
    'Something went wrong.',
]);
$response->assertHasNoErrors();
$response->assertName('current-weather');
$response->assertTitle('Current Weather Tool');
$response->assertDescription('Fetches the current weather forecast for a specified location.');
$response->assertStructuredContent([
    'temperature' => 72,
]);
$response->assertSentNotification('processing/progress', [
    'step' => 1,
    'total' => 5,
]);
$response->assertNotificationCount(5);
$response->assertAuthenticated();
$response->assertGuest();
$response->assertAuthenticatedAs($user);
```
