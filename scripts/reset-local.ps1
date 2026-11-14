<#
.SYNOPSIS
  Deletes the local site (containers AND volumes: database, WordPress files, uploads) and rebuilds it.
.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts\reset-local.ps1 -Force
#>
[CmdletBinding()]
param(
    [switch]$Force
)

$ErrorActionPreference = 'Stop'
$RepoDir = Split-Path -Parent $PSScriptRoot
Set-Location $RepoDir

if (-not $Force) {
    $answer = Read-Host 'This deletes the local database, WordPress files and uploads. Type "yes" to continue'
    if ($answer -ne 'yes') { Write-Host 'Aborted.'; exit 1 }
}

$ErrorActionPreference = 'Continue'
docker compose --profile tools down -v --remove-orphans
$ErrorActionPreference = 'Stop'
if ($LASTEXITCODE -ne 0) { throw "docker compose down failed (exit code $LASTEXITCODE)." }

& (Join-Path $PSScriptRoot 'local-up.ps1')
