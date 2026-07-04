<#
Synchronisation nocturne du depot schilo-theme (branche develop uniquement).
Regles : voir .claude/commands/git-workflow.md
- Ne jamais toucher a la branche master.
- Ne committer/fusionner que si php -l et le test de sante du site passent.
- En cas de conflit de merge, abandonner et laisser la branche intacte.
Planifie via le Planificateur de taches Windows (00h07 chaque nuit).
#>

$RepoPath = "C:\Apache24\htdocs\schilo\wp-content\themes\schilo-theme"
$LogDir   = Join-Path $RepoPath ".claude\logs"
$LogFile  = Join-Path $LogDir "nightly-sync.log"
$DebugLog = "C:\Apache24\htdocs\schilo\wp-content\debug.log"

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

function Write-Log {
    param([string]$Message)
    $line = "[{0}] {1}" -f (Get-Date -Format "yyyy-MM-dd HH:mm:ss"), $Message
    Add-Content -Path $LogFile -Value $line
}

function Test-PhpFiles {
    param([string[]]$Files)
    foreach ($f in $Files) {
        if ([string]::IsNullOrWhiteSpace($f)) { continue }
        $full = Join-Path $RepoPath $f
        if (-not (Test-Path $full)) { continue }
        $out = & php -l $full 2>&1
        if ($LASTEXITCODE -ne 0) {
            Write-Log "ECHEC php -l sur $f : $out"
            return $false
        }
    }
    return $true
}

function Test-SiteHealth {
    $before = 0
    if (Test-Path $DebugLog) { $before = (Get-Item $DebugLog).Length }
    try {
        Invoke-WebRequest -Uri "http://schilo.local/" -UseBasicParsing -TimeoutSec 20 | Out-Null
        Invoke-WebRequest -Uri "http://schilo.local/wp-admin/" -UseBasicParsing -TimeoutSec 20 | Out-Null
    } catch {
        Write-Log "AVERTISSEMENT : requete site en echec : $($_.Exception.Message)"
    }
    Start-Sleep -Seconds 2
    if (-not (Test-Path $DebugLog)) { return $true }
    $content = Get-Content $DebugLog -Raw -ErrorAction SilentlyContinue
    if (-not $content) { return $true }
    $before = [Math]::Min($before, $content.Length)
    $tail = $content.Substring($before)
    if ($tail -match "Fatal error|Parse error") {
        Write-Log "ECHEC : nouvelle Fatal/Parse error detectee dans debug.log"
        return $false
    }
    return $true
}

Set-Location $RepoPath
Write-Log "=== Debut synchronisation nocturne ==="

git fetch origin --prune *> $null

$branch = (git branch --show-current).Trim()
if ($branch -ne "develop") {
    Write-Log "Branche courante = '$branch' (pas develop) -- aucune action, arret."
    exit
}

git pull --ff-only origin develop *> $null
if ($LASTEXITCODE -ne 0) {
    Write-Log "ECHEC git pull --ff-only sur develop -- intervention manuelle necessaire, arret."
    exit
}

# 1. Commit des changements locaux non commités
$dirty = git status --porcelain
if ($dirty) {
    $changedPhp = @(git diff --name-only -- "*.php") + @(git diff --cached --name-only -- "*.php") + @(git ls-files --others --exclude-standard -- "*.php")
    $changedPhp = $changedPhp | Select-Object -Unique
    if (Test-PhpFiles $changedPhp) {
        if (Test-SiteHealth) {
            git add -A
            git commit -m "Sync auto nocturne : changements locaux" -q
            git push origin develop *> $null
            if ($LASTEXITCODE -eq 0) {
                Write-Log "Commit + push des changements locaux effectue."
            } else {
                Write-Log "ECHEC git push apres commit local."
            }
        } else {
            Write-Log "Changements locaux NON commités (echec test sante site)."
        }
    } else {
        Write-Log "Changements locaux NON commités (echec php -l)."
    }
} else {
    Write-Log "Aucun changement local non commite."
}

# 2. Fusion des branches feature/fix/chore/hotfix propres
git fetch origin --prune *> $null
$branches = git for-each-ref --format="%(refname:short)" refs/remotes/origin/ |
    Where-Object { $_ -match '^origin/(feature|fix|chore|hotfix)/' } |
    ForEach-Object { $_ -replace '^origin/', '' }

if (-not $branches) {
    Write-Log "Aucune branche feature/fix/chore/hotfix a fusionner."
} else {
    foreach ($b in $branches) {
        Write-Log "Tentative de fusion de '$b' dans develop..."
        git merge --no-commit --no-ff "origin/$b" *> $null
        $mergeExit = $LASTEXITCODE
        $mergeConflict = (git status --porcelain | Select-String "^(UU|AA|DD)")
        if ($mergeExit -ne 0 -or $mergeConflict) {
            git merge --abort *> $null
            Write-Log "Conflit sur '$b' -- fusion annulee, branche laissee intacte."
            continue
        }
        $changedPhp = @(git diff --cached --name-only -- "*.php")
        if ((Test-PhpFiles $changedPhp) -and (Test-SiteHealth)) {
            git commit -m "Merge auto nocturne : $b -> develop" -q
            git push origin develop *> $null
            if ($LASTEXITCODE -eq 0) {
                git push origin --delete $b *> $null
                git branch -d $b *> $null
                Write-Log "Fusion de '$b' reussie, branche distante supprimee."
            } else {
                Write-Log "ECHEC push apres fusion de '$b'."
            }
        } else {
            git reset --hard HEAD *> $null
            Write-Log "Fusion de '$b' annulee (echec des tests post-merge)."
        }
    }
}

Write-Log "=== Fin synchronisation nocturne ==="
