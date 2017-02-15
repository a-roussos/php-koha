<?php

/*
 * koha_610a_duplicates.php -- Identifies duplicate subfields in field 610.
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

// The following dependency can be installed using `pear`.
require 'File/MARCXML.php' ;

$time_start = microtime ( true ) ;

echo "<PRE>\n" ;

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
        "Error loading character set utf8: %s\n", mysqli_error ( $conn ) ) ;
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

        // returns a File_MARCXML object from an XML file
        $journals = new File_MARCXML (
            $row [ 'marcxml' ], File_MARC::SOURCE_STRING ) ;
        //print_r ( $journals ) ;

        // decodes the next record and returns a File_MARC_Record object
        $record = $journals -> next ( ) ;
        //print "$record\n" ;

        // returns an array containing all File_MARC_Data_Field objects
        // that match the specified tag name
        $fields = $record -> getFields ( '610' ) ;
        //print_r ( $fields ) ;

        // iterate over the array
        foreach ( $fields as $key => $datafield ) {

            //print_r ( $datafield ) ;

            if ( ( $fields [ $key ] -> getIndicator ( 1 ) == '0' ) &&
                ( $fields [ $key ] -> getIndicator ( 2 ) == ' ' ) ) {

                // returns a File_MARC_List object that contains all of the
                // subfields
                $subfields = $datafield -> getSubfields ( ) ;
                //print_r ( $subfields ) ;

                $arr [ ] = $subfields [ 0 ] -> getData ( ) ;
            }
        }
        // Now that the foreach has completed, we can check for values > 1.
        // This allows us to detect dupes like:
        // 610 0  _aValue
        //        _aValue
        //
        // and also:
        //
        // 610 0  _aValue
        // 610 0  _aOtherValue
        // 610 0  _aValue
        if ( ! empty ( $arr ) ) {
            foreach ( array_count_values ( $arr ) as $key => $value ) {
                if ( $value > 1 ) {
                    echo 'biblio ' . $row [ 'biblionumber' ]
                        . " field 610: $value occurrences of " . $key . "\n" ;
                }
            }
            unset ( $arr ) ;
        }
    }
} else
    exit ;

mysqli_close ( $conn ) ;

echo "</PRE>\n" ;

$time_end = microtime ( true ) ;
$time = $time_end - $time_start ;
printf ( "\nProcess time: %.3f seconds\n", $time ) ;

?>
