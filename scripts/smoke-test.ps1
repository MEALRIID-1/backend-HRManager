param(
  [string]$BaseUrl = "http://127.0.0.1:8000",
  [string]$User = "manager@hrmanager.com",
  [string]$Pass = "password123"
)

Write-Output "Smoke test starting against $BaseUrl"

try {
  $login = Invoke-RestMethod -Method Post -Uri "$BaseUrl/api/login" -Body @{email=$User;password=$Pass} -UseBasicParsing
} catch {
  Write-Error "Login failed: $_"
  exit 2
}

if ($login.data -and $login.data.token) { $token = $login.data.token } elseif ($login.token) { $token = $login.token } else { $token = $null }
if (-not $token) { Write-Error "No token in login response"; exit 3 }
Write-Output "Logged in, token length: $($token.length)"

$headers = @{ Authorization = "Bearer $token" }

# /api/me
try {
  $me = Invoke-RestMethod -Uri "$BaseUrl/api/me" -Headers $headers -UseBasicParsing
  Write-Output "/api/me OK: $($me.data.email)"
} catch {
  Write-Error "/api/me failed: $_"; exit 4
}

# reports leaves
$debut = (Get-Date).AddMonths(-1).ToString('yyyy-MM-dd')
$fin = (Get-Date).ToString('yyyy-MM-dd')
try {
  $leaves = Invoke-RestMethod -Uri "$BaseUrl/api/reports/leaves?debut=$debut&fin=$fin" -Headers $headers -UseBasicParsing
  Write-Output "/api/reports/leaves OK: totalDemandes=$($leaves.data.totalDemandes)"
} catch {
  Write-Error "/api/reports/leaves failed: $_"; exit 5
}

# export
$out = Join-Path $PWD "report-smoke.csv"
try {
  Invoke-WebRequest -Uri "$BaseUrl/api/reports/leaves/export?type=leaves&format=csv&debut=$debut&fin=$fin" -Headers $headers -OutFile $out -UseBasicParsing
  Write-Output "Export saved: $out ($(Get-Item $out).Length) bytes"
} catch {
  Write-Error "Export failed: $_"; exit 6
}

$pdfOut = Join-Path $PWD "report-smoke.pdf"
try {
  Invoke-WebRequest -Uri "$BaseUrl/api/reports/leaves/export?type=leaves&format=pdf&debut=$debut&fin=$fin" -Headers $headers -OutFile $pdfOut -UseBasicParsing
  $bytes = [System.IO.File]::ReadAllBytes($pdfOut)
  if ($bytes.Length -lt 4 -or -not ([System.Text.Encoding]::ASCII.GetString($bytes,0,4) -eq '%PDF')) {
    throw "Invalid PDF header"
  }
  Write-Output "PDF export saved: $pdfOut ($($bytes.Length) bytes)"
} catch {
  Write-Error "PDF export failed: $_"; exit 7
}

Write-Output "Smoke test completed successfully."