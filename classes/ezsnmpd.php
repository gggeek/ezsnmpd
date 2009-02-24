<?php
/**
 *
 *
 * @author G. Giunta
 * @version $Id$
 * @copyright (C) G. Giunta 2009
 * @license code licensed under the GPL License: see README
 */


class eZSNMPd {

     const TYPE_INTEGER   = 'integer';
     const TYPE_GAUGE     = 'gauge';
     const TYPE_GAUGE32   = 'gauge';
     const TYPE_GAUGE64   = 'gauge';
     const TYPE_COUNTER   = 'counter';
     const TYPE_COUNTER32 = 'counter';
     const TYPE_COUNTER64 = 'counter';
     const TYPE_TIMETICKS = 'timeticks';
     const TYPE_IPADDRESS = 'ipaddress';
     const TYPE_OID       = 'objectid';
     const TYPE_STRING    = 'string';

     const VERSION        = '0.1';
/*

$oid = "";
$equalsign = "";
$type = "";
$value = "";
$oidTree = array();

function oidIsSmaller($a, $b) {
	list($a1, $a2) = explode(".", $a, 2);
	list($b1, $b2) = explode(".", $b, 2);

	if ($a1 < $b1) {
		// catches ($a1 == "")
		// echo $a1 . " < " . $b1 . "\n";
		return(false);
	} else if ($a1 > $b1) {
		// catches ($b1 == "")
		// echo $a1 . " > " . $b1 . "\n";
		return(true);
	} else {
		// echo $a1 . " = " . $b1 . "\n";
		return(oidIsSmaller($a2, $b2));
	}
}

*/

    private $OIDregexp = array();
    private $OIDstandard = array();
    private $prefix = '';
    private $prefixregexp = '';

    function __construct()
    {
        $ini = eZINI::instance( 'snmpd.ini' );

        foreach( $ini->variable( 'MIB', 'SNMPHandlerClasses' ) as $class )
        {
            if ( !class_exists( $class ) )
            {
                eZDebug::writeError( "SNMP command handler class $class not found" );
                continue;
            }
            $obj = new $class();
            if ( !is_subclass_of( $obj, 'eZsnmpdHandler' ) )
            {
                eZDebug::writeError( "SNMP command handler class $class is not correct subclass of eZsnmpdHandler" );
                continue;
            }
            $oids = $this->separateregexps( $obj->oidList(), $class );
            $this->OIDregexp = array_merge( $this->OIDregexp, $oids['regexp'] );
            $this->OIDstandard = array_merge( $this->OIDstandard, $oids['standard'] );
        }

        $this->prefix = $ini->variable( 'MIB', 'Prefix' );
        if ( $this->prefix != '' )
        {
            if ( $this->prefix[0] == '.')
            {
                $this->prefix = substr( $this->prefix, 1 );
            }
            if ( substr( $this->prefix, -1 ) != '.' )
            {
                $this->prefix =  $this->prefix . '.';
            }
            $this->prefixregexp = '/^\.?' . str_replace( '.', '\.', $this->prefix ) . '/'; // quote for regexp
        }
    }

    public function get( $oid, $mode='get' )
    {
        $response = 'NONE';
        $oid = $this->removePrefix( $oid );
        $handler = $this->getHandler( $oid, $mode );
        if ( is_object( $handler ) )
        {
	        $data = $handler->$mode( $oid );
	        if ( is_array( $data ) && array_key_exists( 'oid', $data ) && array_key_exists( 'type', $data ) && array_key_exists( 'value', $data ) )
	        {
		        $response = $this->prefix.$data['oid'] . "\n" . $data['type'] . "\n" . $data['value'];
	        }
	        else
	        {
			    if ( $data !== null )
			    {
			        eZDebug::writeError( "SNMP set command handler method returned unexpected result $ok (class $handler)" );
			    }
            }
        }
        return $response;
    }

    public function getnext( $oid )
    {
        return $this->get( $oid, 'getnext' );
    }

    public function set( $oid, $value, $type )
    {
        $response = "not-writable";
        $oid = $this->removePrefix( $oid );
        $handler = $this->getHandler( $oid, 'set' );
        if ( is_object( $handler ) )
        {
	        $ok = $handler->set( $oid, $value, $type );
	        switch( $ok )
	        {
		        case 0:
		            $response = "DONE";
		            break;
	            case ezsnmpdHandler::ERROR_WRONG_TYPE:
	                $response = "wrong-type";
	                break;
	            case ezsnmpdHandler::ERROR_WRONG_LENGHT:
	                $response = "wrong-lenght";
	                break;
	            case ezsnmpdHandler::ERROR_WRONG_VALUE:
	                $response = "wrong-value";
	                break;
	            case ezsnmpdHandler::ERROR_INCONSISTENT_VALUE:
	                $response = "inconsistent-value";
	                break;
	            case ezsnmpdHandler::ERROR_NOT_WRITRABLE:
	                $response = "not-writable";
	                break;
	            default:
		            eZDebug::writeError( "SNMP set command handler method returned unexpected result $ok (class $handler)" );
	                $response = "not-writable";
	        }
        }
        return $response;
    }

   /// @todo add somehow a way ro tell a mib of a running eZ from the others, addo,h maybe in comments a CRC or list of all handlers registered?
    public function getHandlerMIBs()
    {
        $mibs = '';
        $ini = eZINI::instance( 'snmpd.ini' );
        foreach( $ini->variable( 'MIB', 'SNMPHandlerClasses' ) as $class )
        {
            if ( !class_exists( $class ) )
            {
                eZDebug::writeError( "SNMP command handler class $class not found" );
                continue;
            }
            $obj = new $class();
            if ( !is_subclass_of( $obj, 'eZsnmpdHandler' ) )
            {
                eZDebug::writeError( "SNMP command handler class $class is not correct subclass of eZsnmpdHandler" );
                continue;
            }
            $mibs .= $obj->getMIB() . "\n";
        }
        return $mibs;
    }

    /**
    * Remove the prefix part from a full oid
    */
    protected function removePrefix( $oid )
    {
        return preg_replace( $this->prefixregexp, '', $oid );
    }

    /**
    * Given an (already shortened) oid, return the corresponding handler class, or null
    */
    protected function getHandler( $oid, $method )
    {
        $class = null;
        if ( array_key_exists( $oid, $this->OIDstandard ) )
        {
            $class = new $this->OIDstandard[$oid];
        }
        else
        {
            foreach( $this->OIDregexp as $regexp => $val )
            {
                if ( preg_match( $regexp, $oid ) )
                {
                    $class = new $val;
                    break;
                }
            }
        }
        return $class;
    }

    /**
    * Split an array of oids in 2, separating regep ones from plain ones
    * Currently only .* is accepted for regexps
    */
    protected function separateregexps( $oidarray, $class )
    {
        $results = array( 'standard' => array(), 'regexp' => array() );
        foreach( $oidarray as $oid )
        {
            if ( preg_match( '/\.\*$/', $oid ) )
            {
                $oid = '/^' . str_replace( '.', '\.', substr( $oid, 0, -2 ) ) . '\./';
                $results['regexp'][$oid] = $class;
            }
            else
            {
                $results['standard'][$oid] = $class;
            }
        }
        return $results;
    }

}

?>