<#
.SYNOPSIS
  No-Docker local runner for Windows. Downloads portable PHP 8.3 + MariaDB 11.4
  into .runtime/, provisions WordPress with scripts/setup.sh and serves it on
  http://localhost:8080 using PHP's built-in web server.

.PARAMETER Reset
  Stop servers and delete .runtime/www and the database before starting.

.PARAMETER NoServe
  Provision only; don't start the web server in the foreground.
#>
param(
    [switch]$Reset,
    [switch]$NoServe,
    [int]$Port = 8080
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$Repo    = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$Runtime = Join-Path $Repo '.runtime'
$PhpDir  = Join-Path $Runtime 'php'
$DbDir   = Join-Path $Runtime 'mariadb'
$DataDir = Join-Path $Runtime 'mariadb-data'
$WwwDir  = Join-Path $Runtime 'www'
$WpCli   = Join-Path $Runtime 'wp-cli.phar'
$DbPort  = 3307

function Info($msg) { Write-Host "==> $msg" -ForegroundColor Cyan }

# Windows PowerShell 5.1 turns any native stderr line into a terminating error under 'Stop'.
function Invoke-Native {
    $Exe = $args[0]
    $Rest = @($args | Select-Object -Skip 1)
    $prev = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { & $Exe @Rest 2>&1 | ForEach-Object { "$_" } }
    finally { $ErrorActionPreference = $prev }
}

function Get-EnvFile {
    $envFile = Join-Path $Repo '.env'
    if (-not (Test-Path $envFile)) {
        $example = Join-Path $Repo '.env.example'
        if (Test-Path $example) { Copy-Item $example $envFile }
    }
    $vars = @{}
    if (Test-Path $envFile) {
        foreach ($line in Get-Content $envFile) {
            if ($line -match '^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)\s*$') {
                $vars[$Matches[1]] = $Matches[2].Trim('"').Trim("'")
            }
        }
    }
    return $vars
}

function Stop-Servers {
    Get-Process php, mariadbd, mysqld -ErrorAction SilentlyContinue |
        Where-Object { $_.Path -and $_.Path.StartsWith($Runtime, [StringComparison]::OrdinalIgnoreCase) } |
        Stop-Process -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 1
}

function Install-Php {
    if (Test-Path (Join-Path $PhpDir 'php.exe')) { return }
    Info 'Downloading PHP 8.3 (NTS x64)'
    $base = 'https://windows.php.net/downloads/releases/'
    $links = (Invoke-WebRequest -UseBasicParsing $base).Links.href
    $zip = $links | Where-Object { $_ -match '^(?:.*/)?php-8\.3\.[\d-]+-nts-Win32-vs16-x64\.zip$' } | Select-Object -First 1
    if (-not $zip) { throw 'Could not find a PHP 8.3 NTS x64 build on windows.php.net' }
    $zipName = Split-Path $zip -Leaf
    $tmp = Join-Path $Runtime $zipName
    Invoke-WebRequest -UseBasicParsing ($base + $zipName) -OutFile $tmp
    Expand-Archive $tmp -DestinationPath $PhpDir -Force
    Remove-Item $tmp

    Invoke-WebRequest -UseBasicParsing 'https://curl.se/ca/cacert.pem' -OutFile (Join-Path $PhpDir 'cacert.pem')
    $ini = Get-Content (Join-Path $PhpDir 'php.ini-development') -Raw
    $ini += @"

; --- ariklubi local overrides ---
extension_dir = "$($PhpDir -replace '\\','/')/ext"
extension=curl
extension=exif
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=mysqli
extension=openssl
extension=pdo_mysql
extension=sodium
extension=zip
zend_extension=opcache
opcache.enable=1
opcache.enable_cli=0
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
date.timezone = Europe/Tallinn
curl.cainfo = "$($PhpDir -replace '\\','/')/cacert.pem"
openssl.cafile = "$($PhpDir -replace '\\','/')/cacert.pem"
display_errors = Off
log_errors = On
error_log = "$($Runtime -replace '\\','/')/php-error.log"
"@
    Set-Content -Path (Join-Path $PhpDir 'php.ini') -Value $ini -Encoding ascii
}

function Install-MariaDb {
    if (Test-Path (Join-Path $DbDir 'bin\mariadbd.exe')) { return }
    Info 'Downloading MariaDB 11.4 (x64 zip)'
    $rel = Invoke-RestMethod 'https://downloads.mariadb.org/rest-api/mariadb/11.4/'
    $ver = ($rel.releases.PSObject.Properties.Name | Sort-Object { [version]$_ } -Descending)[0]
    $zipName = "mariadb-$ver-winx64.zip"
    $tmp = Join-Path $Runtime $zipName
    Invoke-WebRequest -UseBasicParsing "https://downloads.mariadb.org/rest-api/mariadb/$ver/$zipName" -OutFile $tmp
    $extract = Join-Path $Runtime 'mariadb-extract'
    Expand-Archive $tmp -DestinationPath $extract -Force
    Move-Item (Get-ChildItem $extract -Directory | Select-Object -First 1).FullName $DbDir
    Remove-Item $extract -Recurse -Force
    Remove-Item $tmp
}

function Start-MariaDb {
    if (-not (Test-Path $DataDir)) {
        Info 'Initialising MariaDB data directory'
        & (Join-Path $DbDir 'bin\mariadb-install-db.exe') "--datadir=$DataDir" "--port=$DbPort" | Out-Host
    }
    $running = Get-Process mariadbd -ErrorAction SilentlyContinue |
        Where-Object { $_.Path -and $_.Path.StartsWith($Runtime, [StringComparison]::OrdinalIgnoreCase) }
    if (-not $running) {
        Info "Starting MariaDB on port $DbPort"
        Start-Process -FilePath (Join-Path $DbDir 'bin\mariadbd.exe') `
            -ArgumentList "--datadir=`"$DataDir`"", "--port=$DbPort", '--bind-address=127.0.0.1', '--character-set-server=utf8mb4', '--collation-server=utf8mb4_unicode_ci' `
            -WindowStyle Hidden
    }
    $client = Join-Path $DbDir 'bin\mariadb.exe'
    for ($i = 0; $i -lt 60; $i++) {
        Invoke-Native $client --user=root --host=127.0.0.1 "--port=$DbPort" --skip-ssl -e 'SELECT 1' | Out-Null
        if ($LASTEXITCODE -eq 0) { return }
        Start-Sleep -Seconds 1
    }
    throw 'MariaDB did not start within 60 seconds'
}

function Invoke-Sql($sql) {
    Invoke-Native (Join-Path $DbDir 'bin\mariadb.exe') --user=root --host=127.0.0.1 "--port=$DbPort" --skip-ssl -e $sql | Out-Host
    if ($LASTEXITCODE -ne 0) { throw "SQL failed: $sql" }
}

function Link-Dir($link, $target) {
    if (Test-Path $link) {
        $item = Get-Item $link -Force
        if ($item.LinkType -eq 'Junction') { return }
        Remove-Item $link -Recurse -Force
    }
    New-Item -ItemType Junction -Path $link -Target $target | Out-Null
}

# ---------------------------------------------------------------------------

New-Item -ItemType Directory -Force -Path $Runtime | Out-Null
$cfg = Get-EnvFile
$AdminUser  = if ($cfg.ADMIN_USER)     { $cfg.ADMIN_USER }     else { 'admin' }
$AdminEmail = if ($cfg.ADMIN_EMAIL)    { $cfg.ADMIN_EMAIL }    else { 'admin@example.test' }
$AdminPass  = if ($cfg.ADMIN_PASSWORD) { $cfg.ADMIN_PASSWORD } else { 'admin' }
$DbName = 'ariklubi'; $DbUser = 'ariklubi'; $DbPass = 'ariklubi'

if ($Reset) {
    Info 'Resetting native runtime (site files + database)'
    Stop-Servers
    if (Test-Path $WwwDir)  { cmd /c rmdir /s /q "$WwwDir" }
    if (Test-Path $DataDir) { Remove-Item $DataDir -Recurse -Force }
}

Install-Php
Install-MariaDb
if (-not (Test-Path $WpCli)) {
    Info 'Downloading WP-CLI'
    Invoke-WebRequest -UseBasicParsing 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar' -OutFile $WpCli
}
Start-MariaDb
Invoke-Sql "CREATE DATABASE IF NOT EXISTS $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS '$DbUser'@'127.0.0.1' IDENTIFIED BY '$DbPass'; CREATE USER IF NOT EXISTS '$DbUser'@'localhost' IDENTIFIED BY '$DbPass'; GRANT ALL ON $DbName.* TO '$DbUser'@'127.0.0.1'; GRANT ALL ON $DbName.* TO '$DbUser'@'localhost'; FLUSH PRIVILEGES;"

$php = Join-Path $PhpDir 'php.exe'
$env:PATH = "$PhpDir;$env:PATH"
$wpCliCmd = "$($php -replace '\\','/') $($WpCli -replace '\\','/')"

New-Item -ItemType Directory -Force -Path $WwwDir | Out-Null
if (-not (Test-Path (Join-Path $WwwDir 'wp-includes'))) {
    Info 'Downloading WordPress core (et)'
    Invoke-Native $php $WpCli core download --locale=et "--path=$WwwDir" --skip-content | Out-Host
    if ($LASTEXITCODE -ne 0) { throw 'wp core download failed' }
}
New-Item -ItemType Directory -Force -Path (Join-Path $WwwDir 'wp-content\themes'), (Join-Path $WwwDir 'wp-content\plugins'), (Join-Path $WwwDir 'wp-content\uploads') | Out-Null
Link-Dir (Join-Path $WwwDir 'wp-content\themes\ariklubi') (Join-Path $Repo 'wp-content\themes\ariklubi')
Link-Dir (Join-Path $WwwDir 'wp-content\mu-plugins') (Join-Path $Repo 'wp-content\mu-plugins')

if (-not (Test-Path (Join-Path $WwwDir 'wp-config.php'))) {
    Info 'Creating wp-config.php'
    Invoke-Native $php $WpCli config create "--path=$WwwDir" --dbname=$DbName --dbuser=$DbUser --dbpass=$DbPass "--dbhost=127.0.0.1:$DbPort" --locale=et --skip-check | Out-Host
    Invoke-Native $php $WpCli config set WP_ENVIRONMENT_TYPE local "--path=$WwwDir" | Out-Host
    Invoke-Native $php $WpCli config set DISALLOW_FILE_EDIT true --raw "--path=$WwwDir" | Out-Host
    Invoke-Native $php $WpCli config set WP_AUTO_UPDATE_CORE true --raw "--path=$WwwDir" | Out-Host
}

$router = Join-Path $Runtime 'router.php'
Set-Content -Path $router -Encoding ascii -Value @'
<?php
// Router for PHP's built-in server: serve real files, send the rest to WordPress.
$root = $_SERVER['DOCUMENT_ROOT'];
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($path !== '/' && file_exists($root . $path)) {
    if (is_dir($root . $path) && file_exists($root . rtrim($path, '/') . '/index.php')) {
        chdir($root . rtrim($path, '/'));
        require $root . rtrim($path, '/') . '/index.php';
        return true;
    }
    if (substr($path, -4) === '.php') {
        chdir(dirname($root . $path));
        require $root . $path;
        return true;
    }
    return false;
}
chdir($root);
require $root . '/index.php';
'@

Info 'Running scripts/setup.sh'
$bash = 'C:\Program Files\Git\bin\bash.exe'
if (-not (Test-Path $bash)) { throw 'Git Bash not found (install Git for Windows)' }
$env:WP_CLI         = $wpCliCmd
$env:WP_PATH        = ($WwwDir -replace '\\','/')
$env:SITE_URL       = "http://localhost:$Port"
$env:ADMIN_USER     = $AdminUser
$env:ADMIN_EMAIL    = $AdminEmail
$env:ADMIN_PASSWORD = $AdminPass
$env:WP_ENVIRONMENT_TYPE = 'local'
$env:CONTENT_DIR    = (Join-Path $Repo 'content') -replace '\\','/'
$env:BRAND_DIR      = (Join-Path $Repo 'brand') -replace '\\','/'
Invoke-Native $bash (Join-Path $Repo 'scripts/setup.sh') | Out-Host
if ($LASTEXITCODE -ne 0) { throw "setup.sh failed with exit code $LASTEXITCODE" }

Write-Host ''
Write-Host "Site:   http://localhost:$Port/" -ForegroundColor Green
Write-Host "Admin:  http://localhost:$Port/wp-admin/  ($AdminUser / $AdminPass)" -ForegroundColor Green
Write-Host ''

if ($NoServe) { return }
Info "Serving on http://localhost:$Port (Ctrl+C to stop; MariaDB keeps running, stop it with -Reset or Task Manager)"
& $php -S "localhost:$Port" -t $WwwDir $router
