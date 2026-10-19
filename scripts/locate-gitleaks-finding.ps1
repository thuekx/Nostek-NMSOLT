# Nostek-NMSOLT: reveal only path + rule + line, never secret contents.
$ErrorActionPreference = 'Stop'
$gitRoot = (git rev-parse --show-toplevel)
if ($LASTEXITCODE -ne 0) { throw 'Open PowerShell in Git repository first.' }
Set-Location $gitRoot
if (-not (Get-Command gitleaks -ErrorAction SilentlyContinue)) { throw 'Install Gitleaks first.' }
$report = Join-Path $env:TEMP ('nmsolt-gitleaks-' + [guid]::NewGuid().ToString('N') + '.json')
try {
    & gitleaks dir . --redact --report-format json --report-path $report
    $scanCode = $LASTEXITCODE
    Write-Host ('GITLEAKS_EXIT_CODE=' + $scanCode)
    if ($scanCode -eq 1 -and (Test-Path -LiteralPath $report)) {
        $items = @(Get-Content -LiteralPath $report -Raw | ConvertFrom-Json)
        foreach ($item in $items) {
            # Do not print Match, Secret, entropy or surrounding line contents.
            Write-Host ('FINDING FILE=' + $item.File + ' LINE=' + $item.StartLine + ' RULE=' + $item.RuleID)
        }
        Write-Host 'STOP: Do not commit/push. Review the affected line locally and redact any actual credentials.'
    } elseif ($scanCode -eq 0) {
        Write-Host 'SCAN_PASS (heuristic only; does not substitute manual review)'
    } else {
        Write-Host 'SCAN_ERROR (not a PASS)'
    }
} finally {
    if (Test-Path -LiteralPath $report) { Remove-Item -LiteralPath $report -Force }
}
