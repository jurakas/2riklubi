# Regenerates all brand assets. Usage: .\brand\render.ps1 [-Preview <dir>]
param([string]$Preview)
$ErrorActionPreference = 'Stop'
Push-Location $PSScriptRoot
try {
    if (-not (Test-Path node_modules)) { npm install --no-fund --no-audit }
    if ($Preview) { node render.mjs --preview $Preview } else { node render.mjs }
    if ($LASTEXITCODE -ne 0) { throw "render.mjs failed ($LASTEXITCODE)" }
} finally {
    Pop-Location
}
