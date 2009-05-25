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
    // note: ezscript class later does the same check, but after intializing a lot of stuff
    die();
}

// try to move to eZ Publish root dir if called in different dirs
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

// magic global vars
$useLogFiles = true;

$script = eZScript::instance( array( 'description' => ( "SNMP agent daemon" ),
                                     'use-extensions' => true ) );
$script->startup();
if ( $argc > 1 )
{
    // if no options passed on cli, do not waste time with parsing stuff
    $options = $script->getOptions( '[G:|GET:][S:|SET:][M|MIB]',
                                    '',
                                    array(
                                        'GET' => 'get oid value, ex: --GET a.b.c',
                                        'SET' => 'set oid value, ex: --SET a.b.c/type/value',
                                        'MIB' => 'get the complete MIB'
                                    ) );
}
else
{
    $options = array();
}
$script->initialize();

// *** init mibs and start answering loop ***

$server = new eZSNMPd();

if ( isset( $options['GET'] ) )
{
    eZDebugSetting::writeDebug( 'snmp-access', "get {$options['GET']}", 'command' );
    $response = $server->get( $options['GET'] );
    eZDebugSetting::writeDebug( 'snmp-access', str_replace( "\n", " ", $response), 'response' );
    echo "$response\n";
}
elseif( isset( $options['SET'] ) )
{
    list( $oid, $type, $value ) = explode( '/', $options['SET'], 3 );
    eZDebugSetting::writeDebug( 'snmp-access', "set $oid $type $value", 'command' );
    $response = $server->set( $oid, $value, $type );
    eZDebugSetting::writeDebug( 'snmp-access', $response, 'response' );
    echo "$response\n";
}
elseif ( isset( $options['MIB'] ) )
{
    eZDebugSetting::writeDebug( 'snmp-access', "mib", 'command' );
    $response = $server->getHandlerMIBs();
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
                if ( strpos( $buffer, ' ' ) === false )
                {
                    $type = $buffer;
                    $mode = "set3";
                }
                else // If the type and the value are on the same line (as with snmpset)
                {
                    list( $type, $value ) = explode( ' ', $buffer, 2 );
                    eZDebugSetting::writeDebug( 'snmp-access', "set $oid $type $value", 'command' );
                    $response = $server->set( $oid, $value, $type );
                    eZDebugSetting::writeDebug( 'snmp-access', $response, 'response' );
                    echo "$response\n";
                    $mode = "command";
                }
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

}

$script->shutdown();

?>