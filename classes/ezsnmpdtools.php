<?php
/**
 * Various helper routines that could be useful in many handlers
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2009-2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 *
 * @todo add support for clustered configs
 */

class eZsnmpdTools
{

    static function countFilesInDir( $cachedir )
    {
        if (  eZSys::osType() != 'win32' )
        {
            /// @todo use a command that escapes newlines in file names?
            exec( "find \"$cachedir\" -type f 2>/dev/null | wc -l", $result );
            /// @todo test properly to see if we have received a number or an error...
            return $result[0];
        }
        else
        {
            $return = 0;
            $files = @scandir( $cachedir );
            if ( $files === false )
                return false;
            foreach( $files as $file )
            {
                if ( $file != '.' && $file != '..' )
                {
                    if ( is_dir( $cachedir . '/' . $file ) )
                    {
                        $return += self::countFilesInDir( $cachedir . '/' . $file );
                    }
                    else
                    {
                        $return++;
                    }
                }
            }
            return $return;
        }
    }

    static function countFilesSizeInDir( $cachedir )
    {
        if (  eZSys::osType() != 'win32' )
        {
            /// @todo verify that we got no error?
            exec( "du -b -c -s \"$cachedir\" 2>/dev/null", $result );
            $result = @explode( ' ', $result[1] );
            return $result[0];
        }
        else
        {
            $return = 0;
            $files = @scandir( $cachedir );
            if ( $files === false )
                return false;
            foreach( $files as $file )
            {
                if ( $file != '.' && $file != '..' )
                {
                    if ( is_dir( $cachedir . '/' . $file ) )
                    {
                        $return += self::countFilesSizeInDir( $cachedir . '/' . $file );
                    }
                    else
                    {
                        $return += filesize( $cachedir . '/' . $file );
                    }
                }
            }
            return $return;
        }
    }

    /**
     * Search text in files using regexps (recursively through folders).
     * Assumes egrep is available if not on windows
     */
    static function searchInFiles( $searchtext, $cachedir, $is_regexp = true )
    {
        //$fileHandler = eZClusterFileHandler::instance();
        $result = array();

        if (  eZSys::osType() != 'win32' )
        {
            if ( $is_regexp )
            {
                exec( 'egrep -s -R -l "' . str_replace( '"', '\"', $searchtext ) . "\" \"$cachedir\"", $result );
            }
            else
            {
                exec( 'fgrep -s -R -l "' . str_replace( '"', '\"', $searchtext ) . "\" \"$cachedir\"", $result );
            }
        }
        else
        {
            $files = @scandir( $cachedir );
            if ( $files === false )
                return false;
            foreach( $files as $file )
            {
                if ( $file != '.' && $file != '..' )
                {
                    if ( is_dir( $cachedir . '/' . $file ) )
                    {
                        $result = array_merge( $result, self::searchInFiles( $searchtext, $cachedir . '/' . $file, $is_regexp ) );
                    }
                    else
                    {
                        $txt = @file_get_contents( $cachedir. '/'. $file );
                        /// @todo escape properly #
                        if ( $is_regexp )
                        {
                            if( preg_match( "#". $searchtext . "#", $txt ) )
                            {
                                $result[] = $cachedir. '/'. $file;
                            }
                        }
                        else
                        {
                            if( strpos( $txt, $searchtext ) !== false )
                            {
                                $result[] = $cachedir. '/'. $file;
                            }
                        }
                        $txt = false; // free memory asap
                    }
                }
            }
        }

        return $result;
    }

        /**
     * Wrapper method to translate labels and eventually takes advantage of new 4.3 i18n API
     * @param $context
     * @param $message
     * @param $comment
     * @param $argument
     * @return string
     */
    public static function ezpI18ntr( $context, $message, $comment = null, $argument = null )
    {
        // eZ Publish < 4.3 => use old i18n system
        if( eZPublishSDK::majorVersion() >= 4 && eZPublishSDK::minorVersion() < 3 )
        {
            include_once( 'kernel/common/i18n.php' );
            return ezi18n( $context, $message, $comment, $argument );
        }
        else
        {
            return ezpI18n::tr( $context, $message, $comment, $argument );
        }
    }

    /**
     * Wrapper method to initialize a template and eventually takes advantage of new 4.3 TPL API
     * @return eZTemplate
     */
    public static function eZTemplateFactory()
    {
        if( eZPublishSDK::majorVersion() >= 4 && eZPublishSDK::minorVersion() < 3 )
        {
            include_once( 'kernel/common/template.php' );
            return templateInit();
        }
        else
        {
            return eZTemplate::factory();
        }
    }
}
?>