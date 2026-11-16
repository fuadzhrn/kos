<#
.SYNOPSIS
    Git Backup otomatis untuk project Kos Aron.

.DESCRIPTION
    Dijalankan oleh Windows Task Scheduler (setiap 2 jam) atau secara manual.
    Langkah:
      1. Memastikan hanya satu backup yang berjalan (mutex).
      2. Memeriksa kondisi repository (branch, upstream, merge/rebase/konflik).
      3. Mendata perubahan dan MENYARING file sensitif
         (.env, dump database, kunci, data penghuni, token di dalam isi file).
      4. Jika ada perubahan aman: git add -> git commit
         "Auto Backup Kos Aron - YYYY-MM-DD HH:mm".
      5. git fetch, lalu push HANYA jika remote tidak punya commit baru.

    Script ini TIDAK PERNAH menjalankan force push, reset, rebase, stash,
    atau menghapus file. Semua kegagalan dicatat di logs\git-backup.log.

.PARAMETER RepoPath
    Folder root repository. Default: dua tingkat di atas folder script.

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\auto-backup\git-auto-backup.ps1

.NOTES
    Kode keluar: 0 = berhasil / tidak ada perubahan
                 1 = gagal (push gagal, konflik, remote berubah, dll.)
                 2 = berhasil sebagian (ada file sensitif yang dilewati)
#>
[CmdletBinding()]
param(
    [string]$RepoPath = ''
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

# Di PowerShell 5.1, $PSScriptRoot belum tersedia di blok param().
if (-not $RepoPath) { $RepoPath = Join-Path $PSScriptRoot '..\..' }

# ------------------------------------------------------------------
# Pengaturan
# ------------------------------------------------------------------
$CommitPrefix     = 'Auto Backup Kos Aron'
$MaxFileSizeMB    = 10        # file lebih besar dari ini tidak di-backup
$MaxScanSizeMB    = 2         # batas ukuran file yang isinya dipindai
$GitTimeoutSec    = 120       # batas waktu satu perintah git (fetch/push)
$MaxLogSizeKB     = 1024      # log dirotasi jika melebihi ukuran ini
$MutexName        = 'Global\KosAronGitBackup'

# Pola NAMA file yang tidak boleh masuk repository (regex, path memakai "/").
$PolaNamaSensitif = @(
    @{ Pola = '(^|/)\.env$';                          Alasan = 'file .env' }
    @{ Pola = '(^|/)\.env\.(?!example$)[^/]+$';       Alasan = 'file .env.*' }
    @{ Pola = '\.env$';                               Alasan = 'file .env' }
    @{ Pola = '\.(sql|dump|sqlite|sqlite3|db|mdb)$';  Alasan = 'database / dump' }
    @{ Pola = '\.sql\.(gz|zip|bz2|7z)$';              Alasan = 'dump database terkompresi' }
    @{ Pola = '\.(pem|key|p12|pfx|ppk|kdbx|jks)$';    Alasan = 'kunci / sertifikat / password store' }
    @{ Pola = '(^|/)id_(rsa|dsa|ecdsa|ed25519)';      Alasan = 'kunci SSH' }
    @{ Pola = '\.local\.php$';                        Alasan = 'konfigurasi lokal (kredensial)' }
    @{ Pola = '(^|/)(credentials[^/]*|auth|secrets?)\.json$'; Alasan = 'file kredensial' }
    @{ Pola = '\.(csv|xlsx|xls|ods)$';                Alasan = 'kemungkinan ekspor data penghuni' }
    @{ Pola = '(^|/)uploads/(?!\.gitkeep$)';          Alasan = 'file unggahan pengguna' }
    @{ Pola = '(^|/)(backup|backups|dump|dumps)/';    Alasan = 'folder backup/dump' }
    @{ Pola = '\.(bak|backup|old|orig)$';             Alasan = 'file backup lokal' }
    @{ Pola = '(^|/)logs/';                           Alasan = 'file log' }
    @{ Pola = '\.log$';                               Alasan = 'file log' }
)
# Pengecualian: template yang memang aman di-commit.
$PolaNamaAman = @(
    '(^|/)\.env\.example$'
    '\.local\.example\.php$'
    '(^|/)schema\.sql$'
)

# Pola ISI file yang menandakan rahasia (hanya file teks yang dipindai).
$PolaIsiSensitif = @(
    @{ Pola = 'gh[pousr]_[A-Za-z0-9]{36}';                    Alasan = 'token GitHub' }
    @{ Pola = 'github_pat_[A-Za-z0-9_]{22,}';                 Alasan = 'token GitHub (fine-grained)' }
    @{ Pola = '-----BEGIN [A-Z ]*PRIVATE KEY-----';           Alasan = 'private key' }
    @{ Pola = 'AKIA[0-9A-Z]{16}';                             Alasan = 'AWS access key' }
    @{ Pola = 'AIza[0-9A-Za-z_\-]{35}';                       Alasan = 'Google API key' }
    @{ Pola = 'sk-[A-Za-z0-9_\-]{20,}';                       Alasan = 'API secret key' }
    @{ Pola = 'xox[baprs]-[A-Za-z0-9\-]{10,}';                Alasan = 'token Slack' }
    @{ Pola = '(?i)\b(db_?pass(word)?|password|passwd|pwd|secret|api_?key|access_?token|auth_?token|client_?secret)\b[''"]?\s*(=>|=|:)\s*[''"][^''"\s]{4,}[''"]'; Alasan = 'password/secret tertulis di kode' }
    @{ Pola = '\b(?:11|12|13|14|15|16|17|18|19|21|31|32|33|34|35|36|51|52|53|61|62|63|64|65|71|72|73|74|75|76|81|82|91|92|94)\d{14}\b'; Alasan = 'kemungkinan NIK (data penghuni)' }
)
$EkstensiTeks = @('.php', '.phtml', '.html', '.htm', '.css', '.js', '.mjs', '.json', '.md', '.txt',
                  '.ini', '.yml', '.yaml', '.xml', '.svg', '.ps1', '.psm1', '.sh', '.bat', '.cmd',
                  '.conf', '.config', '.htaccess', '.env', '.sql', '.csv', '')

# ------------------------------------------------------------------
# Persiapan path & log
# ------------------------------------------------------------------
$RepoPath = [IO.Path]::GetFullPath($RepoPath)
$LogDir   = Join-Path $RepoPath 'logs'
$LogFile  = Join-Path $LogDir 'git-backup.log'
if (-not (Test-Path $LogDir)) { New-Item -ItemType Directory -Path $LogDir | Out-Null }

if ((Test-Path $LogFile) -and ((Get-Item $LogFile).Length -gt $MaxLogSizeKB * 1KB)) {
    Move-Item -Path $LogFile -Destination "$LogFile.1" -Force
}

$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)

function Hide-Rahasia([string]$Teks) {
    # Jangan pernah menulis kredensial ke log.
    $Teks = [regex]::Replace($Teks, '(https?://)[^/@\s]+@', '$1***@')
    $Teks = [regex]::Replace($Teks, 'gh[pousr]_[A-Za-z0-9]{36}|github_pat_[A-Za-z0-9_]{22,}', '***')
    return $Teks
}

function Write-Log([string]$Level, [string]$Pesan) {
    # Satu kejadian = satu baris log (output git yang multi-baris digabung dengan " | ").
    $Pesan = ($Pesan -split '\r?\n' | Where-Object { $_.Trim() }) -join ' | '
    $baris = '{0} [{1}] {2}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Level, (Hide-Rahasia $Pesan)
    [IO.File]::AppendAllText($LogFile, $baris + [Environment]::NewLine, $Utf8NoBom)
    $warna = switch ($Level) { 'ERROR' { 'Red' } 'WARN' { 'Yellow' } 'OK' { 'Green' } default { 'Gray' } }
    Write-Host $baris -ForegroundColor $warna
}

# ------------------------------------------------------------------
# Menjalankan git tanpa interaksi (tidak pernah memunculkan prompt login)
# ------------------------------------------------------------------
$GitExe = (Get-Command git -ErrorAction SilentlyContinue)
if ($GitExe) { $GitExe = $GitExe.Source }

function ConvertTo-Argumen([string]$a) {
    if ($a -eq '') { return '""' }
    if ($a -notmatch '[\s"]') { return $a }
    $e = [regex]::Replace($a, '(\\*)"', '$1$1\"')
    $e = [regex]::Replace($e, '(\\+)$', '$1$1')
    return '"' + $e + '"'
}

function Invoke-Git([string[]]$Argumen, [int]$TimeoutSec = 60) {
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName               = $GitExe
    $psi.Arguments              = (@('-c', 'core.quotepath=false') + $Argumen | ForEach-Object { ConvertTo-Argumen $_ }) -join ' '
    $psi.WorkingDirectory       = $RepoPath
    $psi.UseShellExecute        = $false
    $psi.CreateNoWindow         = $true
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError  = $true
    $psi.StandardOutputEncoding = [Text.Encoding]::UTF8
    $psi.StandardErrorEncoding  = [Text.Encoding]::UTF8
    # Gagal dengan pesan error alih-alih menunggu input login.
    $psi.EnvironmentVariables['GIT_TERMINAL_PROMPT'] = '0'
    $psi.EnvironmentVariables['GCM_INTERACTIVE']     = 'never'

    $p = [Diagnostics.Process]::Start($psi)
    $out = $p.StandardOutput.ReadToEndAsync()
    $err = $p.StandardError.ReadToEndAsync()
    if (-not $p.WaitForExit($TimeoutSec * 1000)) {
        try { $p.Kill() } catch { }
        return [pscustomobject]@{ Kode = -1; Out = ''; Err = "Perintah git melewati batas waktu $TimeoutSec detik" }
    }
    $p.WaitForExit()
    return [pscustomobject]@{ Kode = $p.ExitCode; Out = $out.Result; Err = $err.Result }
}

function Get-GitText([string[]]$Argumen) {
    $r = Invoke-Git $Argumen
    if ($r.Kode -ne 0) { return $null }
    return $r.Out.Trim()
}

# ------------------------------------------------------------------
# Pemeriksaan file sensitif
# ------------------------------------------------------------------
function Test-NamaSensitif([string]$Path) {
    $p = $Path -replace '\\', '/'
    foreach ($aman in $PolaNamaAman) { if ($p -match $aman) { return $null } }
    foreach ($aturan in $PolaNamaSensitif) {
        if ($p -match $aturan.Pola) { return $aturan.Alasan }
    }
    return $null
}

function Test-IsiSensitif([string]$FullPath) {
    $ext = [IO.Path]::GetExtension($FullPath).ToLowerInvariant()
    if ($EkstensiTeks -notcontains $ext) { return $null }
    if ((Get-Item -LiteralPath $FullPath).Length -gt $MaxScanSizeMB * 1MB) { return $null }
    $isi = [IO.File]::ReadAllText($FullPath)
    foreach ($aturan in $PolaIsiSensitif) {
        if ($isi -match $aturan.Pola) { return $aturan.Alasan }
    }
    return $null
}

# ------------------------------------------------------------------
# Proses utama
# ------------------------------------------------------------------
$mutex = $null
$punyaMutex = $false
$kodeKeluar = 0

try {
    $mutex = New-Object System.Threading.Mutex($false, $MutexName)
    try { $punyaMutex = $mutex.WaitOne(0) } catch [System.Threading.AbandonedMutexException] { $punyaMutex = $true }
    if (-not $punyaMutex) {
        Write-Log 'WARN' 'Backup lain sedang berjalan. Proses ini dilewati.'
        exit 0
    }

    Write-Log 'INFO' "=== Mulai backup: $RepoPath"

    # --- 1. Git & repository ------------------------------------------
    if (-not $GitExe) { throw 'Git tidak ditemukan di PATH.' }
    if ((Get-GitText @('rev-parse', '--is-inside-work-tree')) -ne 'true') { throw "Folder bukan repository Git: $RepoPath" }
    $gitDir = Get-GitText @('rev-parse', '--absolute-git-dir')

    foreach ($penanda in @('MERGE_HEAD', 'rebase-merge', 'rebase-apply', 'CHERRY_PICK_HEAD', 'REVERT_HEAD', 'BISECT_LOG')) {
        if (Test-Path (Join-Path $gitDir $penanda)) {
            throw "Repository sedang dalam proses merge/rebase/cherry-pick ($penanda). Selesaikan secara manual terlebih dahulu."
        }
    }

    $branch = Get-GitText @('symbolic-ref', '--short', '-q', 'HEAD')
    if (-not $branch) { throw 'HEAD tidak berada di branch (detached). Checkout branch utama terlebih dahulu.' }

    $remote      = Get-GitText @('config', "branch.$branch.remote")
    $mergeRef    = Get-GitText @('config', "branch.$branch.merge")
    if (-not $remote -or -not $mergeRef) { throw "Branch '$branch' belum memiliki upstream. Jalankan sekali: git push -u origin $branch" }
    $remoteBranch = $mergeRef -replace '^refs/heads/', ''

    if (-not (Get-GitText @('config', 'user.name')) -or -not (Get-GitText @('config', 'user.email'))) {
        throw 'git config user.name / user.email belum diatur.'
    }

    $urlRemote = Get-GitText @('remote', 'get-url', $remote)
    if ($urlRemote -match '^https?://[^/]*@') {
        Write-Log 'WARN' 'URL remote mengandung kredensial. Sebaiknya hapus dan gunakan Git Credential Manager.'
    }
    Write-Log 'INFO' "Branch: $branch -> $remote/$remoteBranch"

    # --- 2. Data perubahan ----------------------------------------------
    $status = Invoke-Git @('status', '--porcelain=v1', '-z', '--untracked-files=all')
    if ($status.Kode -ne 0) { throw "git status gagal: $($status.Err.Trim())" }

    $token = $status.Out -split "`0"
    $entri = New-Object System.Collections.Generic.List[object]
    for ($i = 0; $i -lt $token.Count; $i++) {
        $t = $token[$i]
        if ($t.Length -lt 4) { continue }
        $x = $t[0]; $y = $t[1]; $path = $t.Substring(3); $asal = $null
        if ($x -eq 'R' -or $x -eq 'C') { $i++; $asal = $token[$i] }
        $entri.Add([pscustomobject]@{ X = $x; Y = $y; Path = $path; Asal = $asal })
    }

    $konflik = @($entri | Where-Object { "$($_.X)$($_.Y)" -match '^(DD|AU|UD|UA|DU|AA|UU)$' })
    if ($konflik.Count -gt 0) {
        throw ('Ada file konflik yang belum diselesaikan: ' + (($konflik | ForEach-Object { $_.Path }) -join ', '))
    }

    # --- 3. Saring file sensitif ----------------------------------------
    $aman = New-Object System.Collections.Generic.List[string]
    $dilewati = 0
    foreach ($e in $entri) {
        $full    = Join-Path $RepoPath $e.Path
        $ada     = Test-Path -LiteralPath $full -PathType Leaf
        $alasan  = Test-NamaSensitif $e.Path
        if (-not $alasan -and $ada) {
            if ((Get-Item -LiteralPath $full).Length -gt $MaxFileSizeMB * 1MB) { $alasan = "ukuran file > $MaxFileSizeMB MB" }
            else { $alasan = Test-IsiSensitif $full }
        }
        # Penghapusan file selalu boleh di-commit (tidak menambah data baru).
        if ($alasan -and -not $ada) { $alasan = $null }

        if ($alasan) {
            $dilewati++
            if ($e.X -ne ' ' -and $e.X -ne '?') {
                throw "File sensitif '$($e.Path)' ($alasan) sudah di-stage secara manual. Jalankan: git restore --staged -- `"$($e.Path)`""
            }
            Write-Log 'WARN' "Dilewati (tidak di-commit): $($e.Path) -- $alasan"
            continue
        }
        $aman.Add($e.Path)
        if ($e.Asal) { $aman.Add($e.Asal) }
    }
    if ($dilewati -gt 0) { $kodeKeluar = 2 }

    # --- 4. git add + commit --------------------------------------------
    $adaCommitBaru = $false
    if ($aman.Count -gt 0) {
        $fileDaftar = Join-Path $LogDir 'git-backup-pathspec.tmp'
        [IO.File]::WriteAllText($fileDaftar, (($aman | Select-Object -Unique) -join "`0"), $Utf8NoBom)
        try {
            $add = Invoke-Git @('--literal-pathspecs', 'add', '-A', "--pathspec-from-file=$fileDaftar", '--pathspec-file-nul')
        } finally {
            Remove-Item -LiteralPath $fileDaftar -ErrorAction SilentlyContinue
        }
        if ($add.Kode -ne 0) { throw "git add gagal: $($add.Err.Trim())" }

        $cekStaged = Invoke-Git @('diff', '--cached', '--quiet')
        if ($cekStaged.Kode -eq 1) {
            $pesan  = '{0} - {1}' -f $CommitPrefix, (Get-Date -Format 'yyyy-MM-dd HH:mm')
            $commit = Invoke-Git @('commit', '-q', '-m', $pesan)
            if ($commit.Kode -ne 0) { throw "git commit gagal: $(($commit.Err + $commit.Out).Trim())" }
            $hash = Get-GitText @('rev-parse', '--short', 'HEAD')
            $jumlah = (Get-GitText @('diff-tree', '--no-commit-id', '--name-only', '-r', 'HEAD')) -split "`n" | Where-Object { $_ } | Measure-Object
            Write-Log 'OK' "Commit $hash dibuat: '$pesan' ($($jumlah.Count) file)"
            $adaCommitBaru = $true
        }
    }
    if (-not $adaCommitBaru) {
        Write-Log 'INFO' 'Tidak ada perubahan source code yang perlu di-commit.'
    }

    # --- 5. Cek remote, lalu push ---------------------------------------
    $fetch = Invoke-Git @('fetch', '--quiet', $remote) $GitTimeoutSec
    if ($fetch.Kode -ne 0) {
        throw "git fetch gagal (koneksi/autentikasi). Commit lokal tetap aman dan akan di-push pada backup berikutnya. Detail: $($fetch.Err.Trim())"
    }

    $hitung = Get-GitText @('rev-list', '--left-right', '--count', "HEAD...$remote/$remoteBranch")
    if (-not $hitung) { throw "Tidak dapat membandingkan dengan $remote/$remoteBranch." }
    $ahead, $behind = $hitung -split '\s+' | ForEach-Object { [int]$_ }

    if ($behind -gt 0) {
        throw "Remote $remote/$remoteBranch memiliki $behind commit yang belum ada di lokal. Push dihentikan agar tidak terjadi konflik. Integrasikan manual (git pull), lalu jalankan backup lagi. Commit lokal tetap aman."
    }
    if ($ahead -eq 0) {
        Write-Log 'INFO' 'Lokal sudah sama dengan remote. Tidak ada yang perlu di-push.'
    } else {
        $push = Invoke-Git @('push', $remote, "HEAD:refs/heads/$remoteBranch") $GitTimeoutSec
        if ($push.Kode -ne 0) {
            throw "git push gagal. Commit lokal tetap aman dan akan dicoba lagi pada backup berikutnya. Detail: $(($push.Err + $push.Out).Trim())"
        }
        Write-Log 'OK' "Push berhasil: $ahead commit -> $remote/$remoteBranch"
    }

    if ($kodeKeluar -eq 2) { Write-Log 'WARN' 'Selesai dengan peringatan: sebagian file sensitif dilewati.' }
    else { Write-Log 'OK' 'Backup selesai.' }
}
catch {
    $kodeKeluar = 1
    Write-Log 'ERROR' $_.Exception.Message
}
finally {
    if ($punyaMutex) { $mutex.ReleaseMutex() }
    if ($mutex) { $mutex.Dispose() }
}

exit $kodeKeluar
