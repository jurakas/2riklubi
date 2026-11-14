<#
.SYNOPSIS
  Starts the local Docker stack and provisions the site (idempotent).
.DESCRIPTION
  1. Copies .env.example to .env if .env does not exist.
  2. docker compose up -d (db, wordpress, mailpit).
  3. Waits until the db and wordpress containers report healthy.
  4. Runs scripts/setup.sh in the wpcli container.
  5. Prints the URLs and the admin credentials.
.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts\local-up.ps1
#>
[CmdletBinding()]
param(
    [int]$TimeoutSeconds = 240
)

$ErrorActionPreference = 'Stop'
$RepoDir = Split-Path -Parent $PSScriptRoot
Set-Location $RepoDir

function Invoke-Checked {
    param([string]$Description, [scriptblock]$Command)
    # Docker writes progress to stderr; Windows PowerShell 5.1 would treat that as an error under 'Stop'.
    $ErrorActionPreference = 'Continue'
    & $Command
    if ($LASTEXITCODE -ne 0) { throw "$Description failed (exit code $LASTEXITCODE)." }
}

function Get-DotEnv {
    param([string]$Path)
    $values = @{}
    foreach ($line in Get-Content -LiteralPath $Path) {
        if ($line -match '^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$') {
            $values[$Matches[1]] = $Matches[2].Trim().Trim('"').Trim("'")
        }
    }
    return $values
}

function Wait-Healthy {
    param([string[]]$Services, [int]$Timeout)
    $ErrorActionPreference = 'Continue'
    $deadline = (Get-Date).AddSeconds($Timeout)
    foreach ($service in $Services) {
        Write-Host "Waiting for '$service' to become healthy..." -NoNewline
        while ($true) {
            $id = (docker compose ps -q $service 2>$null | Select-Object -First 1)
            $status = ''
            if ($id) { $status = (docker inspect -f '{{.State.Health.Status}}' $id 2>$null) }
            if ($status -eq 'healthy') { Write-Host ' ok'; break }
            if ((Get-Date) -gt $deadline) {
                Write-Host ''
                throw "'$service' is not healthy after $Timeout s (status: '$status'). See: docker compose logs $service"
            }
            Write-Host '.' -NoNewline
            Start-Sleep -Seconds 3
        }
    }
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'docker was not found. Install Docker Desktop (WSL2 backend) and make sure it is running.'
}

if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
    Write-Host 'Created .env from .env.example'
}
$envValues = Get-DotEnv '.env'

Invoke-Checked 'docker compose up' { docker compose up -d }
Wait-Healthy -Services @('db', 'wordpress') -Timeout $TimeoutSeconds

Write-Host 'Running scripts/setup.sh in the wpcli container...'
Invoke-Checked 'setup.sh' { docker compose run --rm wpcli bash /scripts/setup.sh }

$siteUrl = if ($envValues['SITE_URL']) { $envValues['SITE_URL'] } else { 'http://localhost:8080' }
$mailPort = if ($envValues['MAILPIT_PORT']) { $envValues['MAILPIT_PORT'] } else { '8025' }
Write-Host ''
Write-Host "Site:     $siteUrl/"
Write-Host "English:  $siteUrl/en/"
Write-Host "Admin:    $siteUrl/wp-admin/"
Write-Host "User:     $($envValues['ADMIN_USER'])"
Write-Host "Password: $($envValues['ADMIN_PASSWORD'])"
Write-Host "Mail:     http://localhost:$mailPort/ (Mailpit)"
