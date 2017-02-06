<?php

/*
 * koha_ISBN_validator.php -- Fetches field 010$a and validates the ISBN.
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

// The following two dependencies can be installed with `pear`.
require 'File/MARCXML.php' ;
require 'Validate/ISPN.php' ;

$time_start = microtime ( true ) ;

echo "<PRE>\n" ;

// Please fill in the next five variables.
$dbhost = '' ;
$dbuser = '' ;
$dbpass = '' ;
$dbname = '' ;
$kohastaffurl = 'http://' ;

$urlsuffix = 'cgi-bin/koha/catalogue/detail.pl?biblionumber=' ;
$isbncheckurl = 'http://www.isbn-check.de/checkisbn.pl?isbn=' ;

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
        biblionumber,
        marcxml
    FROM
        biblioitems" ;

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
        $fields = $record -> getFields ( '010' ) ;
        foreach ( $fields as $key => $datafield ) {
            $subfields = $datafield -> getSubfields ( ) ;
            foreach ( $subfields as $code => $data ) {
                if ( $code == 'a' ) {
                    $ISBN = $data -> getData ( ) ;
                    if ( ! Validate_ISPN::isbn ( $ISBN ) )
                        echo "biblio <A HREF=\"$kohastaffurl/$urlsuffix"
                            . $row [ 'biblionumber' ]
                            . "\" target=\"_blank\">"
                            . $row [ 'biblionumber' ] . "</A> ISBN: "
                            . "<A HREF=\"$isbncheckurl" . $ISBN
                            . "\" target=\"_blank\">" . $ISBN . "</A>\n" ;
                }
            }
        }
    }
} else
    exit ;

mysqli_close ( $conn ) ;

echo "</PRE>\n" ;

$time_end = microtime ( true ) ;
$time = $time_end - $time_start ;
printf ( "Process time: %.3f seconds\n", $time ) ;

?>
