$ErrorActionPreference = 'Continue'
$base = 'https://prestigewindowsaz.com'
$paths = @(
    '/', '/yith-compare/', '/testimonials/', '/suppliers/', '/terms/', '/privacy/',
    '/products/', '/faq/', '/contact/', '/gallery/', '/about/', '/custom-glass-options/',
    '/window-configurator/', '/blog/', '/5-signs-it-is-time-to-replace-your-windows/',
    '/how-to-choose-the-right-window-style-for-your-home/', '/category/uncategorized/'
)

foreach ($path in $paths) {
    $url = "$base$path"
    $bodyFile = [System.IO.Path]::GetTempFileName()
    $status = curl.exe -L --max-time 35 -sS -o $bodyFile -w '%{http_code}' $url
    $html = Get-Content -Raw -LiteralPath $bodyFile
    Remove-Item -LiteralPath $bodyFile -Force

    $title = [System.Net.WebUtility]::HtmlDecode(([regex]::Match($html, '<title>(.*?)</title>', 'IgnoreCase,Singleline').Groups[1].Value -replace '\s+', ' ').Trim())
    $description = [System.Net.WebUtility]::HtmlDecode(([regex]::Match($html, '<meta\s+name=["'']description["'']\s+content=["''](.*?)["'']', 'IgnoreCase,Singleline').Groups[1].Value -replace '\s+', ' ').Trim())
    $canonical = [regex]::Match($html, '<link\s+rel=["'']canonical["'']\s+href=["''](.*?)["'']', 'IgnoreCase').Groups[1].Value
    $h1 = [regex]::Matches($html, '<h1\b[^>]*>(.*?)</h1>', 'IgnoreCase,Singleline')
    $h1Text = @($h1 | ForEach-Object { [System.Net.WebUtility]::HtmlDecode(($_.Groups[1].Value -replace '<[^>]+>', ' ' -replace '\s+', ' ').Trim()) })
    $images = [regex]::Matches($html, '<img\b[^>]*>', 'IgnoreCase')
    $missingAlt = @($images | Where-Object { $_.Value -notmatch '\balt\s*=' }).Count
    $emptyAlt = @($images | Where-Object { $_.Value -match '\balt\s*=\s*["'']\s*["'']' }).Count
    $forms = [regex]::Matches($html, '<form\b', 'IgnoreCase').Count
    $placeholder = ($html -match '(?i)lorem ipsum|dummy text|your company|example\.com')
    $mixed = ($html -match 'http://prestigewindowsaz\.com')
    $oldTitleBanner = ($html -match 'main-title-section-wrapper')

    [pscustomobject]@{
        Path = $path
        Status = [string]$status
        Title = $title
        TitleLength = $title.Length
        DescriptionLength = $description.Length
        H1Count = $h1.Count
        H1 = ($h1Text -join ' | ')
        Images = $images.Count
        MissingAlt = $missingAlt
        EmptyAlt = $emptyAlt
        Forms = $forms
        Placeholder = $placeholder
        MixedHttp = $mixed
        ThemeTitleBanner = $oldTitleBanner
        Canonical = $canonical
    }
}

