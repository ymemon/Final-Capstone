$edge = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe'
$output = 'C:\Users\yasir\prestige-site-audit'
$pages = [ordered]@{
    home='/'; about='/about/'; products='/products/'; configurator='/window-configurator/';
    gallery='/gallery/'; suppliers='/suppliers/'; blog='/blog/'; faq='/faq/';
    testimonials='/testimonials/'; contact='/contact/'; custom_glass='/custom-glass-options/'
}
foreach ($entry in $pages.GetEnumerator()) {
    $nonce = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds()
    $url = 'https://prestigewindowsaz.com' + $entry.Value + '?cachebust=' + $nonce
    $profile = Join-Path $env:TEMP ('pw-mobile-audit-' + $entry.Key)
    $shot = Join-Path $output ($entry.Key + '-mobile.png')
    & $edge --headless=new --disable-gpu --disable-application-cache --disk-cache-size=1 --hide-scrollbars --user-data-dir=$profile --window-size=390,2600 --screenshot=$shot $url 2>$null
    Start-Sleep -Seconds 4
}
