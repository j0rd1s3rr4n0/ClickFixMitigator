param(
  [string]$OutputRoot = "dist"
)

$ErrorActionPreference = "Stop"

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$sourceRoot = Resolve-Path $scriptRoot
$manifestTemplatePath = Join-Path $sourceRoot "manifest.firefox.json"

if (-not (Test-Path $manifestTemplatePath)) {
  throw "Missing Firefox manifest template: $manifestTemplatePath"
}

$manifest = Get-Content $manifestTemplatePath -Raw | ConvertFrom-Json
$version = $manifest.version
if (-not $version) {
  throw "Firefox manifest template does not define a version."
}

$runtimeItems = @(
  "_locales",
  "background.js",
  "block-all-inject.js",
  "content-script.js",
  "icons",
  "ui"
)

$outputRootPath = Join-Path $sourceRoot $OutputRoot
$stageRoot = Join-Path $outputRootPath "firefox"
$packageName = "clickfix-mitigator-firefox-$version.xpi"
$packagePath = Join-Path $outputRootPath $packageName

if (Test-Path $stageRoot) {
  Remove-Item $stageRoot -Recurse -Force
}
New-Item -ItemType Directory -Path $stageRoot | Out-Null

foreach ($item in $runtimeItems) {
  $sourcePath = Join-Path $sourceRoot $item
  if (-not (Test-Path $sourcePath)) {
    throw "Missing runtime item: $item"
  }
  Copy-Item $sourcePath -Destination $stageRoot -Recurse -Force
}

Copy-Item $manifestTemplatePath -Destination (Join-Path $stageRoot "manifest.json") -Force

if (Test-Path $packagePath) {
  Remove-Item $packagePath -Force
}

$zipPath = [System.IO.Path]::ChangeExtension($packagePath, ".zip")
if (Test-Path $zipPath) {
  Remove-Item $zipPath -Force
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zipFileStream = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::CreateNew)
try {
  $zipArchive = New-Object System.IO.Compression.ZipArchive($zipFileStream, [System.IO.Compression.ZipArchiveMode]::Create, $false)
  try {
    $files = Get-ChildItem -Path $stageRoot -Recurse -File
    foreach ($file in $files) {
      $relativePath = $file.FullName.Substring($stageRoot.Length).TrimStart('\', '/')
      $entryName = $relativePath -replace '\\', '/'
      $entry = $zipArchive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
      $entryStream = $entry.Open()
      try {
        $inputStream = [System.IO.File]::OpenRead($file.FullName)
        try {
          $inputStream.CopyTo($entryStream)
        } finally {
          $inputStream.Dispose()
        }
      } finally {
        $entryStream.Dispose()
      }
    }
  } finally {
    $zipArchive.Dispose()
  }
} finally {
  $zipFileStream.Dispose()
}

Move-Item $zipPath $packagePath -Force

Write-Host "Firefox stage:" $stageRoot
Write-Host "Firefox package:" $packagePath
