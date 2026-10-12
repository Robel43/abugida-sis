<?php
/**
 * Abugida configurable branding helpers.
 *
 * Branding values are stored in the existing config table so no schema
 * migration is required. Uploaded assets are stored under assets/branding.
 */

function AbugidaBrandingConfig( $key, $value = null )
{
	$key = (string) $key;

	if ( $key === '' )
	{
		return '';
	}

	if ( ! is_null( $value ) )
	{
		$exists = DBGetOne( "SELECT TITLE
			FROM config
			WHERE TITLE='" . DBEscapeString( $key ) . "'
			AND SCHOOL_ID='0'
			LIMIT 1" );

		DBUpsert(
			'config',
			[ 'CONFIG_VALUE' => $value ],
			[ 'TITLE' => $key, 'SCHOOL_ID' => 0 ],
			$exists ? 'update' : 'insert'
		);
	}

	return (string) DBGetOne( "SELECT CONFIG_VALUE
		FROM config
		WHERE TITLE='" . DBEscapeString( $key ) . "'
		AND SCHOOL_ID='0'
		LIMIT 1" );
}

function AbugidaBrandingHex( $value, $fallback )
{
	$value = trim( (string) $value );

	return preg_match( '/^#[0-9A-Fa-f]{6}$/', $value ) ? strtolower( $value ) : $fallback;
}

function AbugidaBranding()
{
	$platform_name = trim( (string) AbugidaBrandingConfig( 'BRAND_PLATFORM_NAME' ) );

	if ( $platform_name === '' )
	{
		$platform_name = trim( (string) ParseMLField( Config( 'TITLE' ) ) );
	}

	if ( $platform_name === '' )
	{
		$platform_name = trim( (string) Config( 'NAME' ) );
	}

	if ( $platform_name === '' )
	{
		$platform_name = 'Abugida SIS';
	}

	return [
		'platform_name' => $platform_name,
		'tagline' => trim( (string) AbugidaBrandingConfig( 'BRAND_TAGLINE' ) ),
		'primary' => AbugidaBrandingHex( AbugidaBrandingConfig( 'BRAND_PRIMARY_COLOR' ), '#2563eb' ),
		'secondary' => AbugidaBrandingHex( AbugidaBrandingConfig( 'BRAND_SECONDARY_COLOR' ), '#0f172a' ),
		'logo' => AbugidaBrandingAsset( AbugidaBrandingConfig( 'BRAND_LOGO' ), 'assets/themes/' . Config( 'THEME' ) . '/logo.png' ),
		'favicon' => AbugidaBrandingAsset( AbugidaBrandingConfig( 'BRAND_FAVICON' ), 'favicon.ico' ),
		'login_background' => AbugidaBrandingAsset( AbugidaBrandingConfig( 'BRAND_LOGIN_BACKGROUND' ), '' ),
	];
}

function AbugidaBrandingAsset( $configured, $fallback = '' )
{
	$configured = trim( (string) $configured );

	if ( $configured !== ''
		&& preg_match( '#^assets/branding/[A-Za-z0-9._/-]+$#', $configured )
		&& is_file( $configured ) )
	{
		return $configured;
	}

	return $fallback;
}

function AbugidaBrandingDeleteAsset( $config_key )
{
	$current = trim( (string) Config( $config_key ) );

	if ( $current !== ''
		&& preg_match( '#^assets/branding/[A-Za-z0-9._/-]+$#', $current )
		&& is_file( $current ) )
	{
		@unlink( $current );
	}

	AbugidaBrandingConfig( $config_key, '' );
}

function AbugidaBrandingUpload( $input_name, $config_key, $file_prefix, $allowed_mimes )
{
	if ( empty( $_FILES[ $input_name ] )
		|| $_FILES[ $input_name ]['error'] === UPLOAD_ERR_NO_FILE )
	{
		return '';
	}

	$file = $_FILES[ $input_name ];

	if ( $file['error'] !== UPLOAD_ERR_OK )
	{
		throw new RuntimeException( _( 'The branding image upload failed.' ) );
	}

	if ( (int) $file['size'] > 5 * 1024 * 1024 )
	{
		throw new RuntimeException( _( 'Branding images must be 5 MB or smaller.' ) );
	}

	$finfo = new finfo( FILEINFO_MIME_TYPE );
	$mime = $finfo->file( $file['tmp_name'] );

	if ( ! isset( $allowed_mimes[ $mime ] ) )
	{
		throw new RuntimeException( _( 'The uploaded branding image type is not supported.' ) );
	}

	$dir = 'assets/branding';

	if ( ! is_dir( $dir )
		&& ! mkdir( $dir, 0755, true )
		&& ! is_dir( $dir ) )
	{
		throw new RuntimeException( _( 'Unable to prepare branding storage.' ) );
	}

	foreach ( glob( $dir . '/' . $file_prefix . '.*' ) ?: [] as $old_file )
	{
		if ( is_file( $old_file ) )
		{
			@unlink( $old_file );
		}
	}

	$path = $dir . '/' . $file_prefix . '.' . $allowed_mimes[ $mime ];

	if ( ! move_uploaded_file( $file['tmp_name'], $path ) )
	{
		throw new RuntimeException( _( 'Unable to save the branding image.' ) );
	}

	AbugidaBrandingConfig( $config_key, $path );

	return $path;
}

function AbugidaBrandingUrl( $path )
{
	$path = trim( (string) $path );

	if ( $path === '' )
	{
		return '';
	}

	if ( preg_match( '#^(https?:)?//#i', $path ) || str_starts_with( $path, '/' ) )
	{
		return $path;
	}

	$script_dir = str_replace( '\\', '/', dirname( (string) $_SERVER['SCRIPT_NAME'] ) );
	$script_dir = $script_dir === '/' ? '' : rtrim( $script_dir, '/' );

	return $script_dir . '/' . ltrim( $path, '/' );
}

function AbugidaBrandingCssVariables()
{
	$brand = AbugidaBranding();
	$background_url = $brand['login_background'] ? AbugidaBrandingUrl( $brand['login_background'] ) : '';
	$background = $background_url ?
		"url('" . str_replace( "'", '%27', $background_url ) . "')" :
		'none';

	return '--abg-primary:' . $brand['primary'] . ';' .
		'--abg-secondary:' . $brand['secondary'] . ';' .
		'--abg-sidebar:' . $brand['secondary'] . ';' .
		'--abg-login-bg-image:' . $background . ';';
}
