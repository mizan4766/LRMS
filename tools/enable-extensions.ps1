# Enables the PHP extensions LARMS needs in the php.ini that the command line PHP uses.
$ErrorActionPreference = 'Stop'
$line = (& php --ini | Select-String 'Loaded Configuration File:\s*(.+)$')
$ini = if ($line) { $line.Matches[0].Groups[1].Value.Trim() } else { '' }
if (-not $ini -or $ini -eq '(none)') {
    $phpDir = Split-Path (Get-Command php).Source
    foreach ($src in 'php.ini-development', 'php.ini-production') {
        if (Test-Path "$phpDir\$src") { Copy-Item "$phpDir\$src" "$phpDir\php.ini"; $ini = "$phpDir\php.ini"; break }
    }
}
if (-not $ini -or -not (Test-Path $ini)) { Write-Host "      php.ini not found - enable the extensions manually."; exit 0 }
Write-Host "      php.ini: $ini"
try {
    $text = [IO.File]::ReadAllText($ini)
    $orig = $text
    foreach ($e in 'gd','zip','mbstring','pdo_mysql','openssl','fileinfo','curl','intl') {
        $text = [regex]::Replace($text, "(?m)^\s*;\s*(extension\s*=\s*(?:php_)?$e(?:\.dll)?)\s*$", '$1')
    }
    if ($text -ne $orig) {
        Copy-Item $ini "$ini.larms-backup" -Force
        [IO.File]::WriteAllText($ini, $text)
        Write-Host "      Extensions enabled (backup: $ini.larms-backup)"
    } else { Write-Host "      Nothing to change." }
} catch { Write-Host "      Could not edit php.ini ($($_.Exception.Message)). Run install.bat as Administrator or edit it by hand." }
