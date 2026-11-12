$p = 'config\app.php'
if (Test-Path $p) {
  $t = [IO.File]::ReadAllText($p)
  $t = $t.Replace("'timezone' => 'UTC'", "'timezone' => 'Asia/Dhaka'")
  [IO.File]::WriteAllText($p, $t)
}
