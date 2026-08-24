$ErrorActionPreference = 'Continue'
$base = 'https://prestigewindowsaz.com'
$seed = @('/', '/about/', '/products/', '/window-configurator/', '/gallery/', '/blog/', '/faq/', '/contact/', '/testimonials/', '/suppliers/', '/custom-glass-options/', '/terms/', '/privacy/', '/yith-compare/')
$links = [System.Collections.Generic.HashSet[string]]::new()
foreach ($path in $seed) {
    $lines = curl.exe -L --max-time 25 -sS "$base$path"
    $html = [string]::Join("`n", $lines)
    foreach ($m in [regex]::Matches($html, '<a\b[^>]*href=["''](.*?)["'']', 'IgnoreCase')) {
        $href = [System.Net.WebUtility]::HtmlDecode($m.Groups[1].Value)
        if ($href -match '^/') { $href = $base + $href }
        if ($href.StartsWith($base) -and $href -notmatch '/wp-admin|#|\?add-to-cart') { [void]$links.Add(($href -split '#')[0]) }
    }
}
foreach ($url in $links) {
    $code = curl.exe -L --max-time 25 -sS -o NUL -w '%{http_code}' $url
    if ([int]$code -ge 400 -or [int]$code -eq 0) { [pscustomobject]@{ Status=$code; URL=$url } }
}
