# Builds a production upload bundle for Hostinger shared hosting.
#
#   powershell -ExecutionPolicy Bypass -File scripts\build-hostinger.ps1
#
# Output: dist\pathwaytt-<version>.zip containing the app with production-only
# Composer dependencies and compiled Vite assets. Excludes .env, .git, tests,
# node_modules and any uploaded resumes. Your local vendor/ is left untouched.
$ErrorActionPreference = 'Stop'
$PSNativeCommandUseErrorActionPreference = $false
$root    = Split-Path -Parent $PSScriptRoot
$php     = if ($env:PHP_BIN) { $env:PHP_BIN } else { (Get-ChildItem "C:\laragon\bin\php\php-8*\php.exe" | Select-Object -First 1).FullName }
$composer = "C:\laragon\bin\composer\composer.phar"
$version = (Select-String -Path "$root\config\app.php" -Pattern "'version'\s*=>\s*'([^']+)'").Matches[0].Groups[1].Value
$stage   = Join-Path $env:TEMP "pathwaytt-stage"
$dist    = Join-Path $root "dist"
$zip     = Join-Path $dist "pathwaytt-$version.zip"

Write-Host "Building assets..."
Push-Location $root
npm run build | Out-Null
Pop-Location

Write-Host "Staging to $stage ..."
if (Test-Path $stage) { Remove-Item -Recurse -Force $stage }
New-Item -ItemType Directory -Force $stage | Out-Null
$include = 'app','bootstrap','config','database','docs','public','resources','routes','storage','artisan','composer.json','composer.lock','.env.example','README.md'
foreach ($item in $include) { Copy-Item -Recurse -Force (Join-Path $root $item) (Join-Path $stage $item) }
Copy-Item -Force (Join-Path $root "scripts\hostinger.htaccess") (Join-Path $stage ".htaccess")

# Never ship local runtime state.
Remove-Item -Recurse -Force (Join-Path $stage 'storage\app\private\*') -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force (Join-Path $stage 'storage\app\public\*') -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force (Join-Path $stage 'storage\logs\*') -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force (Join-Path $stage 'storage\framework\cache\data\*') -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force (Join-Path $stage 'storage\framework\sessions\*') -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force (Join-Path $stage 'storage\framework\views\*') -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force (Join-Path $stage 'bootstrap\cache\*.php') -ErrorAction SilentlyContinue
Remove-Item -Force (Join-Path $stage 'public\hot') -ErrorAction SilentlyContinue
Remove-Item -Force (Join-Path $stage 'public\storage') -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force (Join-Path $stage 'storage\framework\testing') -ErrorAction SilentlyContinue
foreach ($d in 'storage\app\private','storage\app\public','storage\logs','storage\framework\cache\data','storage\framework\sessions','storage\framework\views','bootstrap\cache') {
    New-Item -ItemType Directory -Force (Join-Path $stage $d) | Out-Null
    if (-not (Test-Path (Join-Path $stage "$d\.gitignore"))) { Set-Content -Path (Join-Path $stage "$d\.gitignore") -Value "*`n!.gitignore" -Encoding ascii }
}

Write-Host "Installing production Composer dependencies..."
Push-Location $stage
& $php $composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist --quiet
if ($LASTEXITCODE -ne 0) { throw "composer install failed" }
Pop-Location

Write-Host "Zipping to $zip ..."
New-Item -ItemType Directory -Force $dist | Out-Null
if (Test-Path $zip) { Remove-Item -Force $zip }
# Windows' bundled bsdtar writes zip entries with forward slashes (Compress-Archive
# uses backslashes, which Linux unzip treats as literal filename characters).
$entries = Get-ChildItem -Force $stage | ForEach-Object { $_.Name }
& "$env:WINDIR\System32\tar.exe" -a -cf $zip -C $stage @entries
if ($LASTEXITCODE -ne 0) { throw "zip failed" }
Remove-Item -Recurse -Force $stage

Write-Host "Done: $zip ($([math]::Round((Get-Item $zip).Length / 1MB, 1)) MB)"
