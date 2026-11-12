param([string]$Path = '.env')
$ErrorActionPreference = 'Stop'
function Env($n, $d) { $v = [Environment]::GetEnvironmentVariable($n); if ([string]::IsNullOrEmpty($v)) { $d } else { $v } }
$vals = [ordered]@{
  APP_NAME = 'LARMS'; APP_ENV = 'local'; APP_DEBUG = 'true'; APP_URL = 'http://127.0.0.1:8000'; APP_LOCALE = 'bn'; APP_FALLBACK_LOCALE = 'en'
  LOG_LEVEL = 'debug'; DB_CONNECTION = 'mysql'; DB_HOST = (Env 'DB_HOST' '127.0.0.1'); DB_PORT = (Env 'DB_PORT' '3306'); DB_DATABASE = (Env 'DB_NAME' 'larms')
  DB_USERNAME = (Env 'DB_USER' 'root'); DB_PASSWORD = ([Environment]::GetEnvironmentVariable('DB_PASS'))
  SESSION_DRIVER = 'file'; CACHE_STORE = 'file'; QUEUE_CONNECTION = 'sync'
  LARMS_ADMIN_PASSWORD = (Env 'LARMS_ADMIN_PASSWORD' 'ChangeMe@123')
}
$text = [IO.File]::ReadAllText($Path)
foreach ($k in $vals.Keys) {
    $v = $vals[$k]; if ($null -eq $v) { $v = '' }
    $line = "$k=$v"
    if ($v -match '[\s#"]') { $line = "$k=`"$v`"" }
    $rx = "(?m)^\s*#?\s*$k=.*$"
    if ([regex]::IsMatch($text, $rx)) { $text = [regex]::Replace($text, $rx, [System.Text.RegularExpressions.MatchEvaluator]{ param($m) $line }) }
    else { $text = $text.TrimEnd() + "`r`n$line`r`n" }
}
[IO.File]::WriteAllText($Path, $text)
Write-Host "      .env updated."
