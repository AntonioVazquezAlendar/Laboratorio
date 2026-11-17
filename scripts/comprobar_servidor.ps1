$ErrorActionPreference = 'Stop'
$baseUrl = 'http://localhost:8080/Laboratorio/'
$failed = $false
foreach ($relative in @('app/Migrations/001_acceso.sql','storage/logs/test-server.log','.gitignore','app/Config/database.local.php')) {
    try { $response = Invoke-WebRequest -Uri ($baseUrl + $relative) -UseBasicParsing; $status = [int]$response.StatusCode }
    catch { if ($_.Exception.Response) { $status = [int]$_.Exception.Response.StatusCode } else { throw } }
    if ($status -ne 403) { $failed = $true }
    Write-Output "$relative : HTTP $status (esperado: 403)"
}
$response = Invoke-WebRequest -Uri ($baseUrl + 'public/index.php?api=sesion') -UseBasicParsing
$state = $response.Content | ConvertFrom-Json
if (-not $state.success) { $failed = $true }
if ($failed) { throw 'Falta aplicar la proteccion. Reinicia Apache como administrador y vuelve a comprobar.' }
Write-Output 'Acceso publico disponible y rutas internas protegidas.'
