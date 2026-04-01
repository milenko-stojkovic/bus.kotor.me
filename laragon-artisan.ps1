# Run Laravel artisan with Laragon PHP when `php` is not on PATH (Windows).
$projectRoot = $PSScriptRoot
$artisanPath = Join-Path $projectRoot 'artisan'
& (Join-Path $projectRoot 'laragon-php.ps1') $artisanPath @args
exit $LASTEXITCODE
