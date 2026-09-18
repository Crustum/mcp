<#
.SYNOPSIS
    Run MCP conformance suite against the CakePHP MCP plugin.

.PARAMETER Suite
    Which suite to run: "server", "client", or "all" (default: all).

.PARAMETER Port
    PHP built-in server port (default: 8001).

.PARAMETER Revision
    MCP protocol revision to test against (default: 2026-07-28).

.EXAMPLE
    .\run.ps1                    # run both suites
    .\run.ps1 -Suite server      # server only
    .\run.ps1 -Suite client      # client only
    .\run.ps1 -Port 9001         # custom port
#>
[CmdletBinding()]
param(
    [ValidateSet('server', 'client', 'all')]
    [string]$Suite = 'all',

    [int]$Port = 8001,

    [string]$Revision = '2026-07-28',

    [string]$ConformancePackage = '@modelcontextprotocol/conformance@alpha'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$Dir  = Split-Path -Parent $MyInvocation.MyCommand.Path
$Root = Split-Path -Parent (Split-Path -Parent $Dir)

# ── helpers ──────────────────────────────────────────────────────

function Wait-ServerReady {
    param([int]$MaxAttempts = 30)

    $body = @{
        jsonrpc = '2.0'
        id      = 1
        method  = 'server/discover'
        params  = @{
            _meta = @{
                'io.modelcontextprotocol/protocolVersion'       = $Revision
                'io.modelcontextprotocol/clientCapabilities'    = @{}
            }
        }
    } | ConvertTo-Json -Depth 5 -Compress

    for ($i = 0; $i -lt $MaxAttempts; $i++) {
        try {
            $null = curl.exe -sf -o NUL `
                "http://127.0.0.1:${Port}/conformance" `
                -X POST `
                -H 'Content-Type: application/json' `
                -H "MCP-Protocol-Version: $Revision" `
                -H 'MCP-Method: server/discover' `
                -d $body
            Write-Host "Server ready after $($i + 1) attempt(s)." -ForegroundColor Green
            return
        } catch {
            Start-Sleep -Seconds 1
        }
    }

    throw "Server did not become ready after $MaxAttempts attempts."
}

# ── clean ────────────────────────────────────────────────────────

if (Test-Path "$Dir\results") { Remove-Item -Recurse -Force "$Dir\results" }
if (Test-Path "$Dir\logs")    { Remove-Item -Recurse -Force "$Dir\logs" }
New-Item -ItemType Directory -Path "$Dir\logs" -Force | Out-Null

# ── suite functions ──────────────────────────────────────────────

function Run-Server {
    Write-Host "`n=== SERVER SUITE ===" -ForegroundColor Cyan

    # start PHP built-in server in background
    $serverProcess = Start-Process -FilePath 'php' `
        -ArgumentList "-S", "127.0.0.1:${Port}", "$Dir\router.php" `
        -RedirectStandardOutput "$Dir\logs\serve.log" `
        -RedirectStandardError "$Dir\logs\serve_err.log" `
        -PassThru `
        -NoNewWindow

    try {
        Write-Host "Waiting for server on port $Port..."
        Wait-ServerReady

        Write-Host "Running conformance runner (server)..."
        & npx --yes $ConformancePackage server `
            --url "http://127.0.0.1:${Port}/conformance" `
            --requirements $Revision `
            --expected-failures "$Dir\conformance-baseline.yml" `
            --output-dir "$Dir\results\server"

        if ($LASTEXITCODE -ne 0) {
            Write-Warning "Conformance runner exited with code $LASTEXITCODE"
        }
    } finally {
        Write-Host "Stopping server (PID $($serverProcess.Id))..."
        Stop-Process -Id $serverProcess.Id -Force -ErrorAction SilentlyContinue
        $serverProcess.WaitForExit(5000) | Out-Null
    }

    Write-Host "`nScoring server results..."
    & php "$Dir\score.php" server
}

function Run-Client {
    Write-Host "`n=== CLIENT SUITE ===" -ForegroundColor Cyan

    Write-Host "Running conformance runner (client)..."
    & npx --yes $ConformancePackage client `
        --command "php $Dir\client.php" `
        --requirements $Revision `
        --expected-failures "$Dir\conformance-baseline.yml" `
        --output-dir "$Dir\results\client"

    if ($LASTEXITCODE -ne 0) {
        Write-Warning "Conformance runner exited with code $LASTEXITCODE"
    }

    Write-Host "`nScoring client results..."
    & php "$Dir\score.php" client
}

# ── main ─────────────────────────────────────────────────────────

$exitCode = 0

try {
    switch ($Suite) {
        'server' { Run-Server }
        'client' { Run-Client }
        'all'    { Run-Server; Run-Client }
    }
} catch {
    Write-Error $_.Exception.Message
    $exitCode = 1
}

exit $exitCode
