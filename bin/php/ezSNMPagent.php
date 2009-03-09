#!/usr/bin/env php
<?php
/**
 * ezSNMPagent - PHP script to be invoked from the SNMP agent
 *
 * Based on mySNMPagent - Copyright (C) 2008 Guenther Mair [guenther.mair@hoslo.ch]
 *
 * @author G. Giunta
 * @version $Id$
 * @copyright (C) G. Giunta 2009
 * @license code licensed under the GPL License: see README
 */

// *** bootstrap ***

if ( isset( $_SERVER['REQUEST_METHOD'] ) )
{
    // this script is not meant to be accessed via the web!
    die();
}

// try move to eZ Publish root dir if called in different dirs
if ( !file_exists( getcwd() . '/autoload.php' ) )
{
    $dir = dirname( __FILE__ );
    chdir( $dir . '/../../../..' );
}

// Set a default time zone if none is given to avoid "It is not safe to rely
// on the system's timezone settings" warnings. The time zone can be overriden
// in config.php or php.ini.
if ( !ini_get( "date.timezone" ) )
{
    date_default_timezone_set( "UTC" );
}

require 'autoload.php';

if ( $argc > 2 )
{
	echo "SYNTAX: " . $argv[0] . " [siteaccess]\n";
	exit( 1 );
}

if ( $argc > 1 )
{
	// @todo switch siteaccess ...
}

// magic global vars
$useLogFiles = true;

//$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => ( "SNMP agent daemon" ),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true ) );
$script->startup();
$script->initialize();

function changeSiteAccessSetting( $siteaccess )
{
    if ( file_exists( 'settings/siteaccess/' . $siteaccess ) )
    {
        return true;
    }
    elseif ( isExtensionSiteaccess( $optionData ) )
    {
        eZExtension::prependExtensionSiteAccesses( $siteaccess );
        return true;
    }
    else
    {
        return false;
    }
}

function isExtensionSiteaccess( $siteaccessName )
{
    $ini = eZINI::instance();
    $extensionDirectory = $ini->variable( 'ExtensionSettings', 'ExtensionDirectory' );
    $activeExtensions = $ini->variable( 'ExtensionSettings', 'ActiveExtensions' );
    foreach ( $activeExtensions as $extensionName )
    {
        $possibleExtensionPath = $extensionDirectory . '/' . $extensionName . '/settings/siteaccess/' . $siteaccessName;
        if ( file_exists( $possibleExtensionPath ) )
            return true;
    }
    return false;
}


// *** init mibs and start answering loop ***

$server = new eZSNMPd();
$mode = "command";
$buffer = "";
$quit = false;

$fh = fopen('php://stdin', 'r');
do
{
	$buffer = rtrim( fgets( $fh, 4096 ) );
	switch ( $mode )
    {

		case "command":
    		$response = '';
			switch ( strtoupper( $buffer ) )
            {
				case "GET":
				case "GETNEXT":
				case "SET":
					$mode = strtolower( $buffer );
					break;
				case "PING":
					// this is for startup handshake
					$response = "PONG";
					break;
				case "QUIT":
					// this is for telnet-tests ;-)
					$quit = true;
					$response = "Terminating.";
					break;
				default:
    				// unrecognized command
					$response = "NONE";
					break;
			}
			if ( $response !== '' )
			{
    			eZDebugSetting::writeDebug( 'snmp-access', $buffer, 'command' );
			    eZDebugSetting::writeDebug( 'snmp-access', $response, 'response' );
				echo "$response\n";
			}
			break;

		case "getnext":
		case "get":

            eZDebugSetting::writeDebug( 'snmp-access', "$mode $buffer", 'command' );
            $response = $server->$mode( $buffer );
            eZDebugSetting::writeDebug( 'snmp-access', str_replace( "\n", " ", $response), 'response' );
			echo "$response\n";
			$mode = "command";
			break;

		case "set":
    		$oid = $buffer;
			$mode = "set2";
			break;
		case "set2":
    		$type = $buffer;
			$mode = "set3";
			break;
		case "set3":
            $value = $buffer;
            eZDebugSetting::writeDebug( 'snmp-access', "set $oid $type $value", 'command' );
            $response = $server->set( $oid, $value, $type );
            eZDebugSetting::writeDebug( 'snmp-access', $response, 'response' );
			echo "$response\n";
			$mode = "command";
			break;

		default:
    		// assert false...
			eZDebugSetting::writeDebug( 'snmp-access', 'NONE', 'response' );
			echo "NONE\n";
			$mode = "command";
			break;
	}
} while ( !$quit );

$script->shutdown();

?>