# Run PHP from Laragon when `php` is not on PATH (Windows). Use for -l, -v, etc.
# Example: .\laragon-php.ps1 -l app\Http\Controllers\Foo.php
$phpExe = 'php'
$laragonBase = 'C:\laragon\bin\php'
if (Test-Path $laragonBase) {
    $dir = Get-ChildItem $laragonBase -Directory -ErrorAction SilentlyContinue | Sort-Object Name -Descending | Select-Object -First 1
    if ($dir) {
        $candidate = Join-Path $dir.FullName 'php.exe'
        if (Test-Path $candidate) {
            $phpExe = $candidate
        }
    }
}
& $phpExe @args
exit $LASTEXITCODE
