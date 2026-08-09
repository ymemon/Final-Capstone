# AZWebCorp SSH connect helper  —  LOCAL ONLY, NEVER UPLOAD THIS FILE.
#
# Keeps the credential on your machine so it never travels with the deliverables
# and never lands in the web root. Run from anywhere:
#
#     .\connect.ps1              # open an interactive session
#     .\connect.ps1 -DryRun      # upload + dry-run the remediation script
#     .\connect.ps1 -Apply       # upload + apply it
#
# FIRST RUN: the password is read once and stored in Windows Credential Manager,
# encrypted to your Windows account. It is not written into this file.

param(
    [switch]$DryRun,
    [switch]$Apply,
    [switch]$ResetPassword
)

$SshUser   = 'client_9d34da8b_644762'
$SshHost   = '644762.us8.ssh.myftpupload.com'
$RemoteDir = 'public_html'          # adjust if wp-config.php lives elsewhere
$Script    = 'azw-fix-all.sh'
$CredName  = 'AZWebCorp-SSH'

# --- credential handling ---------------------------------------------------
# Stored via the Windows DPAPI-backed credential store, so it is encrypted at
# rest and readable only by your Windows user.
$CredFile = Join-Path $env:LOCALAPPDATA 'azwebcorp-ssh.cred'

if ($ResetPassword -and (Test-Path $CredFile)) { Remove-Item $CredFile }

if (-not (Test-Path $CredFile)) {
    Write-Host "First run - enter the SSH password for $SshUser (stored encrypted, not in plain text)."
    $secure = Read-Host -AsSecureString "Password"
    $secure | ConvertFrom-SecureString | Set-Content $CredFile
    Write-Host "Saved to $CredFile" -ForegroundColor Green
}

$secure = Get-Content $CredFile | ConvertTo-SecureString
$plain  = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
              [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))

# --- require a non-interactive ssh client ---------------------------------
# Windows' built-in OpenSSH cannot take a password on the command line, so
# non-interactive runs need plink (PuTTY) or an SSH key. See notes at the end.
$plink = Get-Command plink.exe -ErrorAction SilentlyContinue

if ($DryRun -or $Apply) {
    if (-not $plink) {
        Write-Host "plink.exe not found - install PuTTY, or set up key auth (see below)." -ForegroundColor Yellow
        Write-Host "Falling back to an interactive session; run the script by hand." -ForegroundColor Yellow
        ssh "$SshUser@$SshHost"
        return
    }

    $flag = if ($Apply) { '--apply' } else { '' }

    Write-Host "Uploading $Script ..." -ForegroundColor Cyan
    & pscp.exe -pw $plain $Script "$SshUser@$SshHost`:$RemoteDir/$Script"

    Write-Host "Running $Script $flag ..." -ForegroundColor Cyan
    & $plink.Source -ssh -batch -pw $plain "$SshUser@$SshHost" `
        "cd $RemoteDir && chmod +x $Script && ./$Script $flag"
}
else {
    if ($plink) { & $plink.Source -ssh -pw $plain "$SshUser@$SshHost" }
    else        { ssh "$SshUser@$SshHost" }
}

# ---------------------------------------------------------------------------
# BETTER OPTION - SSH KEYS (no password anywhere, works non-interactively):
#
#   1. ssh-keygen -t ed25519 -C "azwebcorp"        (accept the defaults)
#   2. Copy the contents of  ~/.ssh/id_ed25519.pub
#   3. GoDaddy dashboard -> your Managed WordPress site -> Settings -> SSH
#      access -> add the public key
#   4. ssh client_9d34da8b_644762@644762.us8.ssh.myftpupload.com
#
# After that no password is needed at all, plink is unnecessary, and you can
# delete this file along with the stored credential.
# ---------------------------------------------------------------------------
