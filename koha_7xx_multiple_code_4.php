<?php

/*
 * koha_7xx_multiple_code_4.php -- Displays biblionumbers where field
 * 7xx contains multiple '4' subfields.
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

require 'File/MARCXML.php' ;

$time_start = microtime ( true ) ;

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
    'SELECT
        biblionumber,
        marcxml
    FROM
        biblioitems' ;

if ( ! $res = mysqli_query ( $conn, $query ) ) {
    printf ( "mysqli_query failed: %s\n", mysqli_error ( $conn ) ) ;
    exit ;
}

if ( mysqli_num_rows ( $res ) != 0 ) {
    while ( $row = mysqli_fetch_assoc ( $res ) ) {
        $journals = new File_MARCXML (
            $row [ 'marcxml' ],
            File_MARC::SOURCE_STRING ) ;
        $record = $journals -> next ( ) ;
        $fields = $record -> getFields ( '^7', true ) ;
        foreach ( $fields as $key => $datafield ) {
            $subfields = $datafield -> getSubfields ( '4' ) ;
            if ( count ( $subfields ) > 1 )
                echo 'biblionumber ' . $row [ 'biblionumber' ] . ' field '
                    . $datafield -> getTag ( )
                    . ' has multiple subfields with code 4 ('
                    . count ( $subfields ) . ")\n" ;
        }
    }
} else
    exit ;

mysqli_close ( $conn ) ;

$time_end = microtime ( true ) ;
$time = $time_end - $time_start ;
printf ( "Process time: %.3f seconds\n", $time ) ;

?>
