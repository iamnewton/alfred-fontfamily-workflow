<?php
// ****************
error_reporting(0);
require_once( 'workflows.php' );
$w = new Workflows();

/**
* Description:
* Reformats the version string from the data, specifically targeted at the Windows formmating,
* and strips out everything but the number.
* i.e. XP (NT 5.1 SP3) => XP 5.1
*
* @param $version - required string to reformat
* @return string with all extra text removed
*/
function reformatWindowsVersionNo( $version ) {
	$pattern = '/\(NT (\d\.\d)\s*\w*\)/i';
	$replacement = '$1';
	return preg_replace( $pattern, $replacement, $version );
}

/**
* Description:
* Reformats the version string from the data, specifically targeted at the OSX formmating,
* and strips out everything but the number.
* i.e. Snow Leopard 10.6.8 => 10.6.8
*
* @param $version - required string to reformat
* @return string with all extra text removed
*/
function normalizeVersion( $version ) {
	$str = explode( " ", $version );
	if ( 'XP' === $str[0] ) {
		$str = $version;
	} else {
		$str = $str[count( $str ) - 1];
	}
	return $str;
}

/**
* Description:
* Reformats the OS string from the data, specifically targeted at the Windows
* Phone formmating, and strips out everything but the number.
* i.e. [Windows, Phone 8.0] => [Windows Phone, 8.0]
*
* @param $version - required string to reformat
* @return string with all extra text removed
*/
function normalizeOS( $os, $version ) {
	$str = $os;
	$arr = explode( " ", $version );
	if ( 'Phone' === $arr[0] ) {
		$str = $os . ' ' . $arr[0];
	}
	return $str;
}

/**
* Description:
* Given the font, checks what is the lowest supported OS for each of the OSes
* provided from the API and returns an associated array. If not supported
* version is found, returns a string as "n/a".
* i.e. Array( [mac-os-x] => n/a, [windows] => n/a, [ios] => n/a, [android] => n/a, [windows-phone] => n/a, [linux] => n/a )
*
* @param $font - required string to check against
* @return array of supported OSes
*/
function osStats( $font ) {
	// entire list of fonts from the API
	$families = json_decode( file_get_contents( 'https://raw.githubusercontent.com/zachleat/font-family-reunion/master/results/font-families-results.json' ) );
	$previousOS = '';
	$arr = array();
	for ( $i = 0; $i < count( $families->families ); $i++ ) {
		$family = $families->families[$i];
		$version = normalizeVersion( reformatWindowsVersionNo( $family->version ) );
		$os = normalizeOS( $family->os, $family->version );
		$key = str_replace( ' ', '-', strtolower( $os ) );
		if ( in_array( $font, $family->families ) ) {
			if ( $previousOS !== $key ) {
				$arr[ $key ] = $version;
			} else {
				continue;
			}
		}
		$previousOS = $key;
	}
	// print_r( $arr );
	return $arr;
}

if ( filemtime( "data.json" ) <= time() - 86400 * 7 || 1 ) {
	$families = json_decode( file_get_contents( "https://raw.githubusercontent.com/zachleat/font-family-reunion/master/results/export/results.json" ) );
	$arr = array();
	for ( $i = 0; $i < count( $families->families ); $i++ ) {
		$family = $families->families[$i];
		$title = $family;
		$url = "http://fontfamily.io/" . $family;
		//$description = $val->description;

		$stats = osStats( $family );

		$temp = array();
		if ( array_key_exists( 'mac-os-x', $stats ) ) {
			array_push( $temp, "OSX:" . $stats['mac-os-x'] );
		}
		if ( array_key_exists( 'windows', $stats ) ) {
			array_push( $temp, "Win:" . $stats['windows'] );
		}
		if ( array_key_exists( 'linux', $stats ) ) {
			array_push( $temp, "Linux:" . $stats['linux'] );
		}
		if ( array_key_exists( 'ios', $stats ) ) {
			array_push( $temp, "iOS:" . $stats['ios'] );
		}
		if ( array_key_exists( 'windows-phone', $stats ) ) {
			array_push( $temp, "WinPh:" . $stats['windows-phone'] );
		}
		if ( array_key_exists( 'android', $stats ) ) {
			array_push( $temp, "Droid:" . $stats['android'] );
		}
		$statStr = implode( ", ", $temp );

		// @TODO: trying to only include only the fonts that are actually a
		// match as the file is taking too long to load in the Alfred app
		// !!! going to have to create the data myself I think :(
		echo $title . " stats: " . $statStr . "\n";

		if ( count( $stats ) > 0 ) {
			$arr[] = array(
				"url" => $url ,
				"title" => $title,
				//"description" =>str_replace("&mdash;","-",html_entity_decode(trim(str_replace("\n"," ",strip_tags($val->description))))),
				"description" => "",
				"stats" => "[{$statStr}]"
			);
		}
	}
	if ( count( $arr ) ) {
		file_put_contents( "data.json", json_encode( $arr ) );
	}
}
if ( ! isset( $query ) ) {
	$query = urlencode( "Helvetica" );
}

$data = json_decode( file_get_contents( "data.json" ) );

$extras = array();
$extras2 = array();
$found = array();

foreach ( $data as $key => $result ) {
	$value = strtolower(trim($result->title));
	$description = utf8_decode( strip_tags( $result->description ) );

	if ( strpos( $value, $query ) === 0 ) {
		if ( ! isset( $found[$value] ) ) {
			$found[$value] = true;
			$w->result(
				$result->title,
				$result->url,
				$result->title . " " . $result->stats,
				//$result->description,
				"icon.png"
			);
		}
	}
	else if ( strpos( $value, $query ) > 0 ) {
		if ( ! isset( $found[$value] ) ) {
			$found[$value] = true;
			$extras[$key] = $result;
		}
	}

	else if ( strpos( $description, $query ) !== false ) {
		if ( ! isset( $found[$value] ) ) {
			$found[$value] = true;
			$extras2[$key] = $result;
		}
	}
}

foreach ( $extras as $key => $result ) {
	$w->result(
		$result->title,
		$result->url,
		$result->title . " " . $result->stats,
		$result->description,
		"icon.png"
	);
}

foreach ( $extras2 as $key => $result ) {
	$w->result(
		$result->title,
		$result->url,
		$result->title . " " . $result->stats,
		$result->description,
		"icon.png"
	);
}

echo $w->toxml();
// ****************
?>
