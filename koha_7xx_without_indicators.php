<?php

/*
 * koha_7xx_without_indicators.php -- Displays biblionumbers that have items
 * associated with them where a 7xx field has no indicators set (field 720 -
 * 'Family Name' is excluded as no indicators are required for it).
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

// Please fill in the next five variables.
$dbhost = '' ;
$dbuser = '' ;
$dbpass = '' ;
$dbname = '' ;
$kohastaffurl = 'http://<IP_ADDRESS>:<PORT>' ;

$urlsuffix = '/cgi-bin/koha/catalogue/detail.pl?biblionumber=' ;

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
        items.biblionumber,
        biblioitems.marcxml
    FROM
        items,
        biblioitems
    WHERE
        items.biblionumber = biblioitems.biblionumber
    GROUP BY
        items.biblionumber
    ORDER BY
        items.biblionumber ASC' ;

if ( ! $res = mysqli_query ( $conn, $query ) ) {
    printf ( "mysqli_query failed: %s\n", mysqli_error ( $conn ) ) ;
    exit ;
}

if ( mysqli_num_rows ( $res ) != 0 ) {

    echo "<pre>\n" ;

    $count = 0 ;
    while ( $row = mysqli_fetch_assoc ( $res ) ) {
        $journals = new File_MARCXML (
            $row [ 'marcxml' ],
            File_MARC::SOURCE_STRING ) ;
        $record = $journals -> next ( ) ;
        $fields = $record -> getFields ( '^7', true ) ;
        foreach ( $fields as $key => $datafield ) {
            // field 720 requires no indicators and is therefore excluded
            if ( $datafield -> getTag ( ) != '720' &&
                $datafield -> getIndicator ( 1 ) == ' ' &&
                $datafield -> getIndicator ( 2 ) == ' ' ) {
                $count ++ ;
                echo 'biblionumber '
                    . '<a href="' . $kohastaffurl . $urlsuffix
                    . $row [ 'biblionumber' ] . '" target="_blank">'
                    . $row [ 'biblionumber' ] . '</a> field '
                    . $datafield -> getTag ( ) . " has no indicators set\n" ;
            }
        }
    }
    echo "\n" . $count . " records found\n" ;
} else
    exit ;

mysqli_close ( $conn ) ;

$time_end = microtime ( true ) ;
$time = $time_end - $time_start ;
printf ( "\nProcess time: %.3f seconds\n", $time ) ;

echo "</pre>\n" ;

?>
