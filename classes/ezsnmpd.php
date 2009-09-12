<?php
/**
 * The daemon class that implements the get/getnext/set snmp API
 *
 * @author G. Giunta
 * @version $Id$
 * @copyright (C) G. Giunta 2009
 * @license code licensed under the GPL License: see README
 */


class eZSNMPd {

    // these types are taken from snmpd.conf man pages, valid for pass and pass_persist
    // subagents, and do not correspond directly to mib base types, such as seen in mib files
    const TYPE_INTEGER    = 'integer';
    const TYPE_INTEGER32  = 'integer';
    const TYPE_INTEGER64  = 'integer';
    const TYPE_GAUGE      = 'gauge';
    const TYPE_GAUGE32    = 'gauge';
    const TYPE_GAUGE64    = 'gauge';
    const TYPE_COUNTER    = 'counter';
    const TYPE_COUNTER32  = 'counter';
    const TYPE_COUNTER64  = 'counter';
    const TYPE_TIMETICKS  = 'timeticks';
    const TYPE_IPADDRESS  = 'ipaddress';
    const TYPE_OID        = 'objectid';
    const TYPE_STRING     = 'string';

    const TYPE_UNSIGNED   = 'unsigned';
    const TYPE_UNSIGNED32 = 'unsigned';
    const TYPE_UNSIGNED64 = 'unsigned';

    const VERSION         = '0.2';
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

    /**
    * @todo add caching of list of Handler obejcts, in case we later want to get
    *       MIBs from them? uses more memory for a very small speed gain...
    */
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
        uksort( $this->OIDregexp, 'version_compare' );
        uksort( $this->OIDstandard, 'version_compare' );

        $this->prefix = $ini->variable( 'MIB', 'Prefix' );
        if ( $this->prefix != '' )
        {
            if ( $this->prefix[0] == '.')
            {
                $this->prefix = substr( $this->prefix, 1 );
            }
            if ( substr( $this->prefix, -1 ) != '.' )
            {
                $this->prefix = $this->prefix . '.';
            }
            $this->prefixregexp = '/^\.?' . str_replace( '.', '\.', $this->prefix ) . '/'; // quote for regexp
        }
    }

    /**
    * @return string|null null in case of error
    */
    public function get( $oid, $mode='get' )
    {
        $response = null;
        $oid = $this->removePrefix( $oid );
        if ( $mode == 'getnext' )
        {
            list( $handler, $oid ) = $this->getnextHandler( $oid );
        }
        else
        {
            $handler = $this->getHandler( $oid );
        }

        if ( is_object( $handler ) )
        {
	        $data = $handler->get( $oid );
	        if ( is_array( $data ) && array_key_exists( 'oid', $data ) && array_key_exists( 'type', $data ) && array_key_exists( 'value', $data ) )
	        {
		        $response = $this->prefix . $data['oid'] . "\n" . $data['type'] . "\n" . $data['value']. "\n";
	        }
	        else
	        {
			    if ( $data !== eZsnmpdHandler::NO_SUCH_OID )
			    {
			        eZDebug::writeError( "SNMP get command handler method returned unexpected result ($data) for oid $oid" );
			    }
            }
        }
        return $response;
    }

    public function getnext( $oid )
    {
        return $this->get( $oid, 'getnext' );
    }

    /*
    * @return string|true string in case of error
    */
    public function set( $oid, $value, $type )
    {
        $response = "not-writable";
        $oid = $this->removePrefix( $oid );
        $handler = $this->getHandler( $oid );
        if ( is_object( $handler ) )
        {
	        $ok = $handler->set( $oid, $value, $type );
	        switch( $ok )
	        {
		        case eZsnmpdHandler::SET_SUCCESFUL:
		            $response = true;
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
	            case ezsnmpdHandler::ERROR_NOT_WRITEABLE:
	                $response = "not-writable";
	                break;
	            default:
		            eZDebug::writeError( "SNMP set command handler method returned unexpected result ($ok) for oid $oid" );
	                $response = "not-writable";
	        }
        }
        return $response;
    }

    /**
    *  @todo add somehow a way to tell a mib of a running eZ from the others,
    *        adding maybe in comments a CRC or list of all handlers registered?
    *        Some handlers might generate have dynamic mibs...
    */
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
     * Remove the suffix part from a full oid.
     * This is used to simplify writing handlers - the .0 used for scalar values is removed
     */
    protected function removeSuffix( $oid )
    {
        return preg_replace( '/\.0$/', '', $oid );
    }

    /**
    * Given an oid, return the corresponding handler class, or null
    */
    protected function getHandler( $oid )
    {
        $class = null;
        $oid = $this->removeSuffix( $oid );
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
     * Given an oid, return the handler class corresponding to the next oid, or null
     *
     * @todo find a way to make this work with regexp handlers...
     * @bug will fail if there are a lot of oids: ksort sorts OIDstandard on lexicographic ordering, putting .109 before .11
     */
    protected function getnextHandler( $oid )
    {
        // if it is not a scalar value, look first to see if the object exists
        // if it does, return its scalar value
        if ( !preg_match( '/\.0$/', $oid ) )
        {
            if ( array_key_exists( $oid, $this->OIDstandard ) )
            {
                return array( new $this->OIDstandard[$oid], $oid . '.0' );
            }
        }
        else
        {
            // looking for next of a scalar val: remove the .0 suffix
            $oid = $this->removeSuffix( $oid );
        }
        // now search for an exact match with a known oid
        // if found, return the next oid in the list
        if ( array_key_exists( $oid, $this->OIDstandard ) )
        {
            $oids = array_keys( $this->OIDstandard );
            $pos = array_search( $oid, $oids );
            if ( ( $pos + 1 ) < count( $oids ) )
            {
                $next = $oids[$pos+1];
                return array( new $this->OIDstandard[$next], $next . '.0' );
            }
            else
            {
                // last oid in the tree: no more next
                return null;
            }
        }
        // last chance: maybe the searched oid is an node in the tree, not a leaf
        // a little bit of regexp magic here: if an oid begins with the searched
        // one, then it is its first ancestor
        $match = "/^" . str_replace( '.', '\.', $oid ) ."/";
        foreach( $this->OIDstandard as $anOid => $aClass )
        {
            if ( preg_match( $match, $anOid ) )
            {
                return array( new $aClass, $anOid . '.0' );
            }
        }
        // but what about snmp walking the complete mib?
        if ( ( ( '.' . $this->prefix ) == ( $oid . '.' ) ) && count( $this->OIDstandard ) )
        {
            reset( $this->OIDstandard );
            $class = current( $this->OIDstandard );
            return array( new $class(), key( $this->OIDstandard ) . '.0' );
        }

        return null;
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

    /// Filter variable names to make them valid asn.1 identifiers:
    /// - must start with lowercase letter
    /// - only a-z a-Z 0-9 are permitted
    public static function asncleanup( $name )
    {
        $name = str_replace( array( '_', '.' ), '-', $name );
        $name[0] = strtolower( $name[0] );
        if ( $name[0] >= '0' && $name[0] <= '9' )
        {
            $name = 'xxx' . $name;
        }
        return $name;
    }
}

?>