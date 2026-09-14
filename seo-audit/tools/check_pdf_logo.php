<?php
$uri = azwc_fu_logo_data_uri();
echo "logo data uri length: " . strlen($uri) . "\n";
echo "starts with: " . substr($uri, 0, 40) . "\n";

$report = azwc_fu_report('azwebcorp.com');
if (is_wp_error($report)) {
    echo "report error: " . $report->get_error_message() . "\n";
    exit;
}
$pdf = azwc_fu_report_pdf($report, 'Logo Check');
if (is_wp_error($pdf)) {
    echo "pdf error: " . $pdf->get_error_message() . "\n";
    exit;
}
echo "pdf bytes: " . strlen($pdf) . "\n";
echo "contains /Image XObject: " . (strpos($pdf, '/Image') !== false ? "YES" : "NO") . "\n";
echo "contains /DCTDecode or /FlateDecode near image: " . (preg_match('/\/Subtype\s*\/Image/', $pdf) ? "YES" : "NO") . "\n";
file_put_contents('/tmp/logo-check.pdf', $pdf);
echo "saved to /tmp/logo-check.pdf\n";
