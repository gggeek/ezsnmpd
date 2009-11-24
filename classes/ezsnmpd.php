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
    //private $OIDstandard = array();
    private $prefix = '';
    private $prefixregexp = '';

    /**
    * @todo add caching of list of Handler objects, in case we later want to get
    *       MIBs from them? uses more memory for a very small speed gain...
    * @todo add a check for vailidity of oidRoot string format and for regexp clashes?
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
            $ref = new ReflectionClass( $class );
            if ( !$ref->implementsInterface( 'eZsnmpdHandlerInterface' ) )
            {
                eZDebug::writeError( "SNMP command handler class $class does not implement eZsnmpdHandlerInterface" );
                continue;
            }
            $obj = new $class();
            $this->OIDregexp['/^' . str_replace( '.', '\.', $obj->oidRoot() ) . '/'] = $class;
        }
        uksort( $this->OIDregexp, 'version_compare' );

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
    public function get( $oid )
    {
        $response = null;
        $oid = $this->removePrefix( $oid );
        $handler = $this->getHandler( $oid );

        if ( is_object( $handler ) )
        {
	        $data = $handler->get( $oid );
	        if ( is_array( $data ) && array_key_exists( 'oid', $data ) && array_key_exists( 'type', $data ) && array_key_exists( 'value', $data ) )
	        {
		        $response = $this->prefix . $data['oid'] . "\n" . $data['type'] . "\n" . $data['value']. "\n";
	        }
	        else
	        {
			    if ( $data !== eZsnmpdHandlerInterface::NO_SUCH_OID )
			    {
			        eZDebug::writeError( "SNMP get command handler method returned unexpected result ($data) for oid $oid" );
			    }
            }
        }
        return $response;
    }

    /**
    * Make sure that if handler X says this is last oid, we move on to next handler
    */
    public function getnext( $oid )
    {
        $oid = $this->removePrefix( $oid );
        $found = false;
        // check if we have to walk the mib starting from root
        if ( $oid == '' || $oid . '.' == $this->prefix )
        {
            $found = true;
        }

        foreach( $this->OIDregexp as $regexp => $class )
        {
            if ( !$found )
            {
                // the 2nd regexp is used to match the root of the mib, eg. user passed 2 and handler declares 2.
                $found = preg_match( $regexp, $oid ) || preg_match( $regexp, $oid . '.' ) ;
                if ( !$found )
                {
                    continue;
                }
                $handler = new $class();
            }
            else
            {
                $handler = new $class();
                $oid = $handler->oidRoot();
            }
            $data = $handler->getnext( $oid );
            if ( is_array( $data ) && array_key_exists( 'oid', $data ) && array_key_exists( 'type', $data ) && array_key_exists( 'value', $data ) )
            {
                return $this->prefix . $data['oid'] . "\n" . $data['type'] . "\n" . $data['value']. "\n";
            }
            else
            {
                if ( $data != eZsnmpdHandlerInterface::LAST_OID )
                {
                    if ( $data !== eZsnmpdHandlerInterface::NO_SUCH_OID )
                    {
                        eZDebug::writeError( "SNMP get command handler method returned unexpected result ($data) for oid $oid" );
                    }
                    break;
                }
            }
        }
        return null;
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
    * @return string
    */
    public function getFullMIB()
    {
        $out = $this->getHandlerMIBs();
        return $this->getRootMIB( md5( $out ) ) . $out . "\n\nEND\n";
    }

    /**
    * @param string $uniqid md5 or other identifier used to tell apart versions
    *                       of the mib that differ (typically because of the
    *                       variable handler part)
    * @return string
    *
    * @todo find a more appropriate piece of MODULE-IDENTITY to put the uniqid into
    *       than a simple comment
    */
    protected function getRootMIB( $uniqid )
    {
        return file_get_contents( './extension/ezsnmpd/share/EZPUBLISH-MIB' ) . "\n\n-- eZSNMPd mib uniqid: $uniqid\n\n";
    }

    /**
    * @return string
    */
    protected function getHandlerMIBs()
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
            $ref = new ReflectionClass( $class );
            if ( !$ref->implementsInterface( 'eZsnmpdHandlerInterface' ) )
            {
                eZDebug::writeError( "SNMP command handler class $class does not implement eZsnmpdHandlerInterface" );
                continue;
            }
            $obj = new $class();
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
    public static function removeSuffix( $oid )
    {
        return preg_replace( '/\.0$/', '', $oid );
    }

    /**
    * Given an oid, return the corresponding handler class, or null
    */
    protected function getHandler( $oid )
    {
        $class = null;
        $oid = self::removeSuffix( $oid );
        foreach( $this->OIDregexp as $regexp => $val )
        {
            if ( preg_match( $regexp, $oid ) )
            {
                $class = new $val;
                break;
            }
        }
        return $class;
    }

    /**
     * Given an oid, return the handler class corresponding to the next oid, or null
     *
     * To make this work with regexp handlers, the logic is inversed wrt the getHandler:
     * - first look if oid matches a regexp, and if we do ask the regexp handler to work
     *   for us
     * - if no regexp matches, then a scan within plain oid list is done
     * Of course overlapping regexp ranges with plain enuerated oids is not a good idea!
     *
     * @bug will fail if there are a lot of oids: ksort sorts OIDstandard on lexicographic ordering, putting .109 before .11
     */
    /*protected function getnextHandler( $oid )
    {
        foreach( $this->OIDregexp as $regexp => $val )
        {
            if ( preg_match( $regexp, $oid ) )
            {
                $class = new $val;
                $result = $class->getnext( $oid );
                if ( $result === true )
                {
                    // move to next handler in the list: either
                    /// @todo ...
                }
                return $result;
            }
        }

        $result = self::getNextInArray( $oid, $this->OIDstandard, $this->prefix );
        if ( is_array( $result ) )
        {
            $result[0] = new $result[0];
        }
        else if ( $result === true )
        {
            $rseult = null;
        }
        return $result;
    }*/

    /**
    * Implement this logic in a way that makes it easier to be shared with other code
    * @param string $oid
    * @param array $array sorted array of oid strings
    * @return array|null|true
    */
    /*public static function getNextInArray( $oid, $array, $prefix )
    {
        // if it is not a scalar value, look first to see if the object exists
        // if it does, return its scalar value
        if ( !preg_match( '/\.0$/', $oid ) )
        {
            if ( $pos = array_search( $oid, $array ) )
            {
                return array( $array[$pos], $oid . '.0' );
            }
        }
        else
        {
            // looking for next of a scalar val: remove the .0 suffix
            $oid = self::removeSuffix( $oid );
        }

        // now search for an exact match with a known oid
        // if found, return the next oid in the list
        if ( $pos = array_search( $oid, $array ) )
        {
            if ( ( $pos + 1 ) < count( $array ) )
            {
                $next = $oids[$pos+1];
                return array( $array[$next], $next . '.0' );
            }
            else
            {
                // last oid in the tree: no more next
                return true;
            }
        }
        // last chance: maybe the searched oid is a node in the tree, not a leaf
        // a little bit of regexp magic here: if an oid begins with the searched
        // one, then it is its first ancestor
        $match = "/^" . str_replace( '.', '\.', $oid ) ."/";
        foreach( $array as $anOid => $aClass )
        {
            if ( preg_match( $match, $anOid ) )
            {
                return array( $aClass, $anOid . '.0' );
            }
        }
        // but what about snmp walking the complete mib?
        if ( ( ( '.' . $prefix ) == ( $oid . '.' ) ) && count( $array ) )
        {
            reset( $array );
            $class = current( $array );
            return array( $class(), key( $array ) . '.0' );
        }

        return null;
    }*/

    /**
    * Split an array of oids in 2, separating regep ones from plain ones
    * Currently only .* is accepted for regexps
    */
    /*protected function separateregexps( $oidarray, $class )
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
    }*/

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