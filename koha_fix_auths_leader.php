<?php

/*
 * koha_fix_auths_leader.php -- Removes trailing space from authority leader
 * Copyright (C) 2017  Andreas Roussos
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/*
   The xdiff_string_diff() function requires xdiff-1.5.2, install it with:

   $ wget http://www.xmailserver.org/libxdiff-0.23.tar.gz
   $ tar -zxvf libxdiff-0.23.tar.gz
   $ cd libxdiff-0.23/
   $ ./configure
   $ make
   # make install
   # apt-get install php5-dev
   # pecl install xdiff-1.5.2

   ... and follow the instructions about updating your php.ini
*/

// The following dependency can be installed using `pear install File_MARC`.
require 'File/MARCXML.php' ;

$time_start = microtime ( true ) ;

update_sqldata ( ) ;

$time_end = microtime ( true ) ;
$time = $time_end - $time_start ;
printf ( "\nProcess time: %.3f seconds\n", $time ) ;

function update_sqldata ( ) {

    // Please fill in the next four variables.
    $dbhost = '' ;
    $dbuser = '' ;
    $dbpass = '' ;
    $dbname = '' ;

    $conn = mysqli_connect ( $dbhost, $dbuser, $dbpass, $dbname ) ;

    if ( mysqli_connect_errno ( $conn ) ) {
        printf ( "Connect failed: %s\n", mysqli_connect_error ( $conn ) ) ;
        exit ;
    }

    if ( ! mysqli_set_charset ( $conn, "utf8" ) ) {
        printf (
            "Error loading character set utf8: %s\n",
            mysqli_error ( $conn ) ) ;
        exit ;
    }

    $query =
        "SELECT
            authid,
            marcxml
        FROM
            auth_header" ;

    if ( ! $res = mysqli_query ( $conn, $query ) ) {
        printf ( "mysqli_query failed: %s\n", mysqli_error ( $conn ) ) ;
        exit ;
    }

    if ( mysqli_num_rows ( $res ) != 0 ) {

        while ( $row = mysqli_fetch_assoc ( $res ) ) {

            $marcxmlbefore = $row [ 'marcxml' ] ;
            $marcxmlafter = fix_leader ( $row [ 'marcxml' ] ) ;

            $udiff = xdiff_string_diff (
                str_replace ( "\r", "", $marcxmlbefore ),
                $marcxmlafter ) ;

            if ( ! empty ( $udiff ) ) {

                //echo $row [ 'authid' ] . "\n" ;
                //echo $udiff ;

                $qry =
                    "UPDATE
                        auth_header
                    SET
                        marcxml = \"" . mysqli_real_escape_string (
                            $conn, $marcxmlafter ) . "\"
                    WHERE
                        authid = " . $row [ 'authid' ] ;

                if ( ! mysqli_query ( $conn, $qry ) ) {
                    printf (
                        "mysqli_query failed: %s\n",
                        mysqli_error ( $conn ) ) ;
                    exit ;
                } else {
                    echo "Updated authority " . $row [ 'authid' ] . "\n" ;
                }

                $qry = "INSERT INTO
                            zebraqueue (
                                biblio_auth_number,
                                operation,
                                server,
                                done )
                        VALUES ( "
                            . $row [ 'authid' ] . ", "
                            . "\"specialUpdate\", "
                            . "\"authorityserver\", "
                            . "0 )" ;
                //echo "$qry\n" ;
                if ( ! mysqli_query ( $conn, $qry ) ) {
                    printf (
                        "mysqli_query failed: %s\n",
                        mysqli_error ( $conn ) ) ;
                    exit ;
                }

            }

        }

    }

    mysqli_close ( $conn ) ;

}

function fix_leader ( $marcxmlbefore ) {

    $journals = new File_MARCXML ( $marcxmlbefore, File_MARC::SOURCE_STRING ) ;

    $record = $journals -> next ( ) ;

    $leader = $record -> getLeader ( ) ;

    $newleader = rtrim ( $leader ) ;

    $record -> setLeader ( $newleader ) ;

    // Ugly hack because toXML() 1) does not preserve the namespace info
    //                           2) changes the original indentation
    $str1 = implode (
        "\n",
        array_slice (
            explode ( "\n", $record -> toXML ( "UTF-8", true, false ) ),
            2 ) ) ;
    $str2 =
'<?xml version="1.0" encoding="UTF-8"?>
<record
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.loc.gov/MARC21/slim http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd"
    xmlns="http://www.loc.gov/MARC21/slim">

' . $str1 ;
    $temp = str_replace ( " <leader>",      "  <leader>",      $str2 ) ;
    $temp = str_replace ( " <controlfield", "  <controlfield", $temp ) ;
    $temp = str_replace ( " <datafield",    "  <datafield",    $temp ) ;
    $temp = str_replace ( " </datafield>",  "  </datafield>",  $temp ) ;
    $temp = str_replace ( "  <subfield",    "    <subfield",   $temp ) ;
    $temp = html_entity_decode ( $temp ) ;
    $marcxmlafter = str_replace ( "&", "&amp;", $temp ) ;

    return $marcxmlafter ;

}

?>
