<?php
/**
 * Handles eZFind tests
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license code licensed under the GPL License: see README
 */

class eZsnmpdeZFindHandler extends eZsnmpdFlexibleHandler
{

    function get( $oid )
    {
        $oidroot = $this->oidRoot();
        $oidroot = $oidroot[0];
        switch( preg_replace( '/\.0$/', '', $oid ) )
        {
            case $oidroot . '1.1':
                if ( in_array( 'ezfind', eZExtension::activeExtensions() )  )
                {
                    $ini = eZINI::instance( 'solr.ini' );
                    $data = eZHTTPTool::getDataByURL( $ini->variable( 'SolrBase', 'SearchServerURI' )."/admin/ping", false );
                    if ( stripos( $data, '<str name="status">OK</str>' ) !== false )
                    {
                        $status = 1;
                    }
                    else
                    {
                        $status = 0;
                    }
                }
                else
                {
                    $status = -1;
                }
                return array(
                    'oid' => $oid,
                    'type' => eZSNMPd::TYPE_INTEGER,
                    'value' => $status );

            case $oidroot . '1.2':
                if ( in_array( 'ezfind', eZExtension::activeExtensions() )  )
                {
                    $ini = eZINI::instance( 'solr.ini' );
                    $data = eZHTTPTool::getDataByURL( $ini->variable( 'SolrBase', 'SearchServerURI' )."/admin/stats.jsp", false );
                    if ( preg_match( '#<stat +name="numDocs" +>[ \t\r\n]*(\d+)[ \t\r\n]*</stat>#', $data, $status ) )
                    {
                        $status = $status[1];
                    }
                    else
                    {
                        $status = -2;
                    }
                }
                else
                {
                    $status = -1;
                }
                return array(
                    'oid' => $oid,
                    'type' => eZSNMPd::TYPE_INTEGER,
                    'value' => $status );

        }

        return self::NO_SUCH_OID;
     }

    function getMIBTree()
    {
        $oidroot = $this->oidRoot();
        $oidroot = rtrim( $oidroot[0], '.' );
        return array(
            'name' => 'eZPublish',
            'children' => array(
                $oidroot => array(
                    'name' => 'eZFind',
                    'children' => array(
                        1 => array(
                            'name' => 'solr',
                            'children' => array(
                                1 => array(
                                    'name' => 'ezfindSolrStatus',
                                    'syntax' => 'INTEGER',
                                    'description' => 'Availability of the SOLR server (-1 if eZFind not enabled).'
                                ),
                                2 => array(
                                    'name' => 'ezfindSolrCount',
                                    'syntax' => 'INTEGER',
                                    'description' => 'Number of documents indexed in the SOLR server  (-1 if eZFind not enabled, -2 if connection error).'
                                )
                            )
                        )
                    )
                )
            )
        );
    }

}
?>