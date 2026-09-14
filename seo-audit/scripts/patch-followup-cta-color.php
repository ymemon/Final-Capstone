<?php

if ( PHP_SAPI !== 'cli' || empty( $argv[1] ) ) {
	fwrite( STDERR, "Usage: php patch-followup-cta-color.php /absolute/path/to/ui.php\n" );
	exit( 2 );
}

$target = $argv[1];
$source = file_get_contents( $target );
if ( false === $source ) {
	fwrite( STDERR, "Unable to read target.\n" );
	exit( 3 );
}

$replacements = array(
	'.azwc-fu-card.is-primary{background:#e6b84d;border-color:#e6b84d}' => '.azwc-fu-card.is-primary{background:rgba(230,184,77,.09);border-color:#e6b84d}',
	'.azwc-fu-card.is-primary b{color:#161208}' => '.azwc-fu-card.is-primary b{color:#f5ca61}',
	'.azwc-fu-card.is-primary span{color:#4a3d18}' => '.azwc-fu-card.is-primary span{color:#e3e7ed}',
	'.azwc-fu-card.is-primary:hover{background:#f5d47d;border-color:#f5d47d}' => '.azwc-fu-card.is-primary:hover{background:rgba(230,184,77,.18);border-color:#f5ca61}',
);

foreach ( $replacements as $old => $new ) {
	if ( 1 !== substr_count( $source, $old ) ) {
		fwrite( STDERR, "Expected anchor missing or duplicated: {$old}\n" );
		exit( 4 );
	}
	$source = str_replace( $old, $new, $source );
}

$temporary = $target . '.cta-color-tmp';
if ( false === file_put_contents( $temporary, $source ) ) {
	fwrite( STDERR, "Unable to write temporary file.\n" );
	exit( 5 );
}

$output = array();
$status = 0;
exec( 'php -l ' . escapeshellarg( $temporary ) . ' 2>&1', $output, $status );
if ( 0 !== $status ) {
	@unlink( $temporary );
	fwrite( STDERR, implode( "\n", $output ) . "\n" );
	exit( 6 );
}

$backup = $target . '.backup-before-cta-color-' . gmdate( 'Ymd-His' );
if ( ! copy( $target, $backup ) || ! rename( $temporary, $target ) ) {
	@unlink( $temporary );
	fwrite( STDERR, "Backup or atomic replacement failed.\n" );
	exit( 7 );
}

echo "PATCH_OK\nBACKUP={$backup}\n";
