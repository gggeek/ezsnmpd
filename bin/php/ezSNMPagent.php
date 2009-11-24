#!/usr/bin/env php
<?php
/**
 * ezSNMPagent - PHP script to be invoked from the SNMP agent
 * Should work when invoked with both 'pass' or 'pass_persist'
 *
 * Based on mySNMPagent - Copyright (C) 2008 Guenther Mair [guenther.mair@hoslo.ch]
 *
 * @author G. Giunta
 * @version $Id$
 * @copyright (C) G. Giunta 2009
 * @license code licensed under the GPL License: see README
 *
 * @todo resolve clash between -s used by eZP standard options and -s used by snmpd for set...
 */

// *** bootstrap ***

if ( isset( $_SERVER['REQUEST_METHOD'] ) )
{
    // this script is not meant to be accessed via the web!
    // note: ezscript class later does the same check, but after intializing a lot of stuff
    die();
}

// following moved to wrapper shell script

// try to move to eZ Publish root dir if called in different dirs
// to ease being called directly from snmp agent
/// @bug does not work when symlinks are involved
/*if ( !file_exists( getcwd() . '/autoload.php' ) )
{
    $dir = dirname( __FILE__ );
    chdir( $dir . '/../../../..' );
}*/

// Set a default time zone if none is given to avoid "It is not safe to rely
// on the system's timezone settings" warnings. The time zone can be overriden
// in config.php or php.ini.
if ( !ini_get( "date.timezone" ) )
{
    date_default_timezone_set( "UTC" );
}

require 'autoload.php';

// magic global vars
$useLogFiles = true;

$script = eZScript::instance( array( 'description' => ( "SNMP agent daemon" ),
                                     'use-extensions' => true ) );
$script->startup();
if ( $argc > 1 )
{
    // if no options passed on cli, do not waste time with parsing stuff
    $options = $script->getOptions( '[a:|siteaccess:][g:|get:][n:|getnext:][s:|set:][m|mib][w|walk:]',
                                    '',
                                    array(
                                        'get' => 'get oid value, ex: --get=a.b.c',
                                        'getnext' => 'get next oid value, ex: --getnext=a.b.c',
                                        'set' => 'set oid value, ex: --set=a.b.c type value',
                                        'walk' => 'walk the mib tree',
                                        'mib' => 'get the complete MIB'
                                    ),
                                    false,
                                    array( 'siteaccess' => false ) );
}
else
{
    $options = array();
}
$script->initialize();

// *** init mibs and start answering loop ***

$server = new eZSNMPd();

if ( isset( $options['get'] ) )
{
    echo snmpget( 'get', $options['get'], $server );
}
elseif ( isset( $options['getnext'] ) )
{
    echo snmpget( 'getnext', $options['getnext'], $server );
}
elseif( isset( $options['set'] ) )
{
    /// @todo validate presence of the 3 params?
    $response = snmpset( $options['set'], $options['arguments'][0], $options['arguments'][1], $server );
    if ( $response !== 'DONE' )
    {
        echo $response;
    }
}
elseif ( isset( $options['mib'] ) )
{
    eZDebugSetting::writeDebug( 'snmp-access', "mib", 'command' );
    $response = $server->getFullMIB();
    eZDebugSetting::writeDebug( 'snmp-access', str_replace( "\n", " ", $response), 'response' );
    echo "$response\n";
}
elseif ( isset( $options['walk'] ) )
{
    eZDebugSetting::writeDebug( 'snmp-access', "walk", 'command' );
    if ( $options['walk'] === true )
    {
        $next = '';
    }
    else
    {
        $next = $options['walk'];
    }
    while( ( $response = snmpget( 'getnext', $next, $server ) ) !== null )
    {
        $parts = explode( "\n", $response );
        $parts[1] = strtoupper( $parts[1] );
        /// @todo more decoding of snmpd.conf formats to snmpwalk ones?
        if ( $parts[1] == 'STRING' )
        {
            /// @todo verify if we nedd substitution for " and other chars (which one?)
            $parts[2] = '"' . $parts[2] . '"';
        }
        echo "{$parts[0]} = {$parts[1]}: {$parts[2]}\n";
        $next = $parts[0];
    }
    eZDebugSetting::writeDebug( 'snmp-access', str_replace( "\n", " ", $response), 'response' );
    echo "$response\n";
}
else
{

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
    		    $response = snmpget( $mode, $buffer, $server, true );
    		    echo $response === null ? "NONE\n" : "$response\n";
    			$mode = "command";
    			break;

    		case "set":
        		$oid = $buffer;
    			$mode = "set2";
    			break;
    		case "set2":
                if ( strpos( $buffer, ' ' ) === false )
                {
                    $type = $buffer;
                    $mode = "set3";
                    break;
                }
                else // If the type and the value are on the same line (as with snmpset)
                {
                    list( $type, $buffer ) = explode( ' ', $buffer, 2 );
                    // fall through voluntarily
                }
    		case "set3":
    		    $response = snmpset( $oid, $type, $buffer, $server ) . "\n";
    		    echo $response === true ? "DONE\n" : $response . "\n";
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

}

function snmpset( $oid, $type, $value, $server )
{
    eZDebugSetting::writeDebug( 'snmp-access', "set $oid $type $value", 'command' );
    $response = $server->set( $oid, $value, $type );
    eZDebugSetting::writeDebug( 'snmp-access', $response, 'response' );
    return $response;
}

function snmpget( $mode, $buffer, $server )
{
    eZDebugSetting::writeDebug( 'snmp-access', "$mode $buffer", 'command' );
    $response = $server->$mode( $buffer );
    eZDebugSetting::writeDebug( 'snmp-access', str_replace( "\n", " ", $response ), 'response' );
    return $response;
}

$script->shutdown();

?>