$edge = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe'
$output = 'C:\Users\yasir\prestige-site-audit'
New-Item -ItemType Directory -Path $output -Force | Out-Null
$pages = [ordered]@{
    home='/'
    about='/about/'
    products='/products/'
    configurator='/window-configurator/'
    gallery='/gallery/'
    blog='/blog/'
    faq='/faq/'
    contact='/contact/'
    testimonials='/testimonials/'
    suppliers='/suppliers/'
    custom_glass='/custom-glass-options/'
    terms='/terms/'
    privacy='/privacy/'
    compare='/yith-compare/'
}
foreach ($entry in $pages.GetEnumerator()) {
    $nonce = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds()
    $url = 'https://prestigewindowsaz.com' + $entry.Value + '?site_audit=' + $nonce
    $profile = Join-Path $env:TEMP ('pw-audit-' + $entry.Key + '-' + $nonce)
    $shot = Join-Path $output ($entry.Key + '-desktop.png')
    & $edge --headless=new --disable-gpu --disable-application-cache --disk-cache-size=1 --hide-scrollbars --user-data-dir=$profile --window-size=1440,2600 --screenshot=$shot $url 2>$null
}
