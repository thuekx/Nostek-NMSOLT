$ErrorActionPreference = "Stop"

Write-Host "===== NOSTEK-NMSOLT PREPUSH ====="

$root = (git rev-parse --show-toplevel)
if ($LASTEXITCODE -ne 0) { throw "Not a Git repository" }

$remote = (git remote get-url origin)
if ($remote -ne "https://github.com/thuekx/Nostek-NMSOLT.git") {
    throw "Unexpected Git remote: $remote"
}

$name = git config --local user.name
$email = git config --local user.email

if ($name -ne "thuekx" -or $email -ne "thuekx@kolu.web.id") {
    throw "Git author does not match expected identity"
}

Write-Host "Git identity: PASS"

$files = @(git ls-files --cached --others --exclude-standard |
    Where-Object { $_ })

if ($LASTEXITCODE -ne 0) { throw "Git file listing failed" }

$bad = @($files | Where-Object {
    $p = $_.Replace('\', '/')
    ($p -match '(^|/)\.env($|\.(?!example$|docker\.example$))') -or
    ($p -match '(^|/)(backups|node_modules|vendor|\.git|\.claude)(/|$)') -or
    ($p -match '(^|/)(CLAUDE\.md|skills-lock\.json)$') -or
    ($p -match '\.(pem|p12|pfx|key|dump|sqlite|sqlite3|sql|zip|7z|rar|gz)$')
})

if ($bad.Count -gt 0) {
    $bad | ForEach-Object { Write-Host "UNSAFE PATH: $_" }
    throw "Unsafe files found"
}

Write-Host "Unsafe file paths: PASS"

if (-not (Test-Path "LICENSE")) {
    throw "LICENSE missing"
}
if (-not (Select-String -Path "LICENSE" -Pattern "MIT License" -Quiet)) {
    throw "MIT license text missing"
}

Write-Host "License presence: PASS"

$pattern = ('Kusuma' + 'Vision') + '|' + ('Clau' + 'de')
$extensions = @(".php",".js",".ts",".json",".vue",".md",
                ".yaml",".yml",".go",".sh",".kt")

$matches = @($files | Where-Object {
    $extensions -contains [IO.Path]::GetExtension($_)
} | ForEach-Object {
    Select-String -LiteralPath $_ -Pattern $pattern -ErrorAction SilentlyContinue
})

if ($matches.Count -gt 0) {
    $matches | Select-Object -First 20 |
        ForEach-Object { Write-Host "BRAND: $($_.Path):$($_.LineNumber)" }
    throw "Legacy brand references require review"
}

Write-Host "Brand scan: PASS"
Write-Host "===== PREPUSH STATIC CHECK PASS ====="
