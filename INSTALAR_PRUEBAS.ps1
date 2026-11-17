param(
    [string]$BaseDeDatos = 'laboratorio_pruebas',
    [string]$Php = ''
)
$ErrorActionPreference = 'Stop'
try {
    if (-not $Php) {
        $phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
        if ($phpCommand) { $Php = $phpCommand.Source }
        elseif (Test-Path -LiteralPath 'C:\AppServ') {
            $phpFound = Get-ChildItem -LiteralPath 'C:\AppServ' -Directory -Filter 'php*' |
                Sort-Object Name -Descending |
                ForEach-Object { Join-Path $_.FullName 'php.exe' } |
                Where-Object { Test-Path -LiteralPath $_ -PathType Leaf } |
                Select-Object -First 1
            if ($phpFound) { $Php = $phpFound }
        }
    }
    if (-not $Php -or -not (Test-Path -LiteralPath $Php -PathType Leaf)) {
        throw 'No se encontro PHP. Ejecuta de nuevo con -Php C:\ruta\php.exe.'
    }
    if ($BaseDeDatos -notmatch '^laboratorio_[a-z0-9_]{1,52}$') { throw 'Nombre de base no valido. Usa laboratorio_ seguido de minusculas, numeros o guion bajo.' }
    $phpArgs = @()
    $extensionDirectory = Join-Path (Split-Path -Parent $Php) 'ext'
    if (Test-Path -LiteralPath $extensionDirectory -PathType Container) { $phpArgs += @('-d', "extension_dir=$extensionDirectory") }
    foreach ($module in @('pdo_mysql', 'curl')) {
        $loaded = & $Php @phpArgs '-r' "echo extension_loaded('$module') ? '1' : '0';"
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo iniciar PHP. Revisa su configuracion.' }
        if ($loaded -ne '1') {
            $dll = Join-Path $extensionDirectory "php_$module.dll"
            if (-not (Test-Path -LiteralPath $dll -PathType Leaf)) { throw "Falta la extension PHP $module. Activa la extension en php.ini." }
            $phpArgs += @('-d', "extension=php_$module.dll")
        }
    }
    Write-Host 'Instalacion de demostracion del laboratorio'
    Write-Host "Base destino: $BaseDeDatos. No reemplaza bases ajenas al instalador."
    $mysqlServer = Read-Host 'Servidor MySQL [127.0.0.1]'
    if (-not $mysqlServer) { $mysqlServer = '127.0.0.1' }
    $mysqlUser = Read-Host 'Usuario MySQL [root]'
    if (-not $mysqlUser) { $mysqlUser = 'root' }
    $mysqlSecret = Read-Host 'Contrasena MySQL (Enter si esta vacia)' -AsSecureString
    $savedEnvironment = @{}
    foreach ($key in @('LAB_DB_HOST','LAB_DB_USER','LAB_DB_PASSWORD','LAB_DB_NAME')) { $savedEnvironment[$key] = [Environment]::GetEnvironmentVariable($key,'Process') }
    $secretPointer = [IntPtr]::Zero
    try {
        $secretPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($mysqlSecret)
        $env:LAB_DB_HOST = $mysqlServer
        $env:LAB_DB_USER = $mysqlUser
        $encodedSecret = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes([Runtime.InteropServices.Marshal]::PtrToStringBSTR($secretPointer)))
        $env:LAB_DB_NAME = $BaseDeDatos
        $encodedSecret | & $Php @phpArgs (Join-Path $PSScriptRoot 'instalar_pruebas.php') '--password-stdin'
        if ($LASTEXITCODE -ne 0) { throw 'La instalacion no termino. Corrige el mensaje anterior y vuelve a ejecutarla.' }
    }
    finally {
        if ($secretPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($secretPointer) }
        foreach ($key in $savedEnvironment.Keys) { [Environment]::SetEnvironmentVariable($key,$savedEnvironment[$key],'Process') }
        $encodedSecret = $null
        $mysqlSecret.Dispose()
    }
    Write-Host 'Listo. La aplicacion ya apunta a la base de pruebas.'
    Write-Host 'Activa pdo_mysql y curl tambien en el PHP que usa Apache si no estan habilitados.'
    exit 0
}
catch {
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}
