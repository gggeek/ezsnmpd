<?php
/**
 * Abstract SNMP Handler class.
 *
 * Implements basic oid tree => oid array conversion logic and array-based getnext
 * logic for all handlers with a fixed list of oids
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2009-2012
 * @license code licensed under the GPL License: see README
 */

abstract class eZsnmpdHandler implements eZsnmpdHandlerInterface {

    var $_oidlist = array();

    /**
    * Must return an array of all the OIDs handled, properly sorted.
    * Called internally by getnext.
    * If you implement the getMIBTree function, no need to reimplement this one.
    * Use usort( $array, 'version_compare' ) for sorting the array.
    * NB: for scalar objects, the trailing .0 is to be omitted
    * Trailing wildcards are NOT accepted: this class must return a finite list of oids.
    * @return array
    */
    public function oidList()
    {
        if ( is_callable( array( $this, 'getMIBTree' ) ) )
        {
            $this->_oidlist = array();
            eZMIBTree::walk( $this->getMIBTree(), array( $this, 'oidListBuilder' ) );
            return $this->_oidlist;
        }
        return array();
    }

    /**
    * Used by oidList to build the array of oids out of the tree.
    * Needs to be public because it is invoked by eZMIBTree tree traversal
    */
    public function oidListBuilder( $oid, $prefix )
    {
        if ( !isset( $oid['syntax'] ) || $oid['syntax'] != 'SEQUENCE' )
        {
            $this->_oidlist[] = preg_replace( '/^\./', '', $prefix );
        }
    }

    /**
    * As long as the oidList() function is implemented correctly, there is no need
    * to reimplement this in subclasses.
    * Take care: the logic in here comes from reverse engineerring of the snmpwalk
    * command and takes into account all corner cases.
    * @return an array with an oid value, null or true
    */
    public function getnext( $oid )
    {
        $array = $this->oidList();

        // if it is not a scalar value, look first to see if the object exists
        // if it does, return its scalar value
        if ( !preg_match( '/\.0$/', $oid ) )
        {
            if ( ( $pos = array_search( $oid, $array ) ) !== false )
            {
                //return array( $array[$pos], $oid . '.0' );
                return $this->get( $oid . '.0' );
            }
        }
        else
        {
            // looking for next of a scalar val: remove the .0 suffix
            $oid = eZSNMPd::removeSuffix( $oid );
        }
        // now search for an exact match with a known oid
        // if found, return the next oid in the list
        if ( ( $pos = array_search( $oid, $array ) ) !== false )
        {
            if ( ( $pos + 1 ) < count( $array ) )
            {
                return $this->get( $array[$pos+1] . '.0' );
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
        foreach( $array as $anOid )
        {
            if ( preg_match( $match, $anOid ) )
            {
                return $this->get( $anOid . '.0' );
            }
        }
        // but what about snmp walking the complete mib?
        if ( ( ( '.' . $this->oidRoot() ) == ( $oid . '.' ) ) && count( $array ) )
        {
            return $this->get( $array[0] . '.0' );
        }

        return null;

    }

    /**
    * Override this in subclasses to add write support.
    */
    public function set( $oid, $value, $type )
    {
        return self::ERROR_NOT_WRITEABLE;
    }

    /**
    * Please implement getMIBTree in subclasses to provide a tree of OIDs that
    * are managed by this handler
    *
    * OR (deprecated)
    *
    * override this in subclasses to provide directly a mib (textual) description of
    * the oids managed b y the handler, so that automated mib parsing tools can
    * be used by monitoring plugins.
    */
    public function getMIB()
    {
        if ( is_callable( array( $this, 'getMIBTree' ) ) )
        {
            $out = '';
            $out .= eZMIBTree::toMIB( $this->getMIBTree() );
            return $out;
        }
        return '';
    }

    /**
     * Make sure we use a separate DB connection from the standard one (courtesy function to be used by subclasses).
     * This allows us to:
     * - catch the exception raised if db is down and keep the script going
     * - run the script in daemon mode without keeping a connection open (as we can close as soon as it is not needed anymore)
     */
    protected static function eZDBinstance()
    {
        try
        {
            $db = eZDB::instance( false, false, true );
            // eZP 4.0 will not raise an exception on connection errors
            if ( !$db->isConnected() )
            {
                return false;
            }
            return $db;
        }
        catch ( Exception $e )
        {
            return false;
        }
    }
}

?>