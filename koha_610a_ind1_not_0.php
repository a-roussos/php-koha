<?php

/*
 * koha_610a_ind1_not_0.php -- Finds biblios where field 610 indicator 1 != 0.
 * Copyright (C) 2016  Andreas Roussos
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

// No external dependencies -- uses SimpleXML to parse MARCXML data.

$time_start = microtime ( true ) ;

// Please fill in the next variable.
$staffurl = 'http://<IP_ADDRESS>:<PORT>' ;

$staffurlsuffix = 'cgi-bin/koha/catalogue/detail.pl?biblionumber=%d' ;
$href = $staffurl . '/' . $staffurlsuffix ;

if ( isset ( $_GET [ 'bn' ] ) ) {
    if ( filter_var( $_GET [ 'bn' ], FILTER_VALIDATE_INT ) == TRUE ) {
        print_biblionumber_field610a ( $_GET [ 'bn' ] ) ;
    } else {
        exit ;
    }
} else {
    print_biblionumber_field610a ( 0 ) ;
}

function print_biblionumber_field610a ( $biblio ) {
    $conn = mysqli_connect (
        "",
        "",
        "",
        "" ) ;

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

    if ( $biblio != 0 )
        $query .= " WHERE biblionumber = " . $biblio ;

    if ( ! $res = mysqli_query ( $conn, $query ) ) {
        printf ( "mysqli_query failed: %s\n", mysqli_error ( $conn ) ) ;
        exit ;
    }

    if ( mysqli_num_rows ( $res ) != 0 ) {
        while ( $row = mysqli_fetch_assoc ( $res ) ) {
            $record = simplexml_load_string ( $row [ 'marcxml' ] ) ;
            foreach ( $record -> children ( ) as $datafield ) {
                foreach ( $datafield -> children ( ) as $subfield ) {
                    if ( $datafield [ 'tag' ] == "610" &&
                        $datafield [ 'ind1' ] != "0" ) {
                        $arr [ ] = array (
                            'biblionumber' => $row [ 'biblionumber' ],
                            'field610a' => ( string ) $subfield ) ;
                    }
                }
            }
        }
    } else
        exit ;

    mysqli_close ( $conn ) ;

    if ( isset ( $arr ) ) {

        echo "<PRE>\n" ;

        if ( $biblio == 0 ) {
            foreach ( $arr as $key => $row ) {
                $field610a [ $key ] = $row [ 'field610a' ] ;
                $biblionumber [ $key ] = $row [ 'biblionumber' ] ;
            }
            array_multisort (
                $biblionumber, SORT_ASC,
                $field610a, SORT_ASC, $arr ) ;
        }

        foreach ( $arr as $var ) {
            if ( $biblio != 0 )
                printf ( "%05d %s\n",
                    $var [ 'biblionumber' ],
                    $var [ 'field610a' ] ) ;
            else
                printf ( "<A HREF=\"report_610a-no0.php?bn=%d\">%05d</A> "
                    . '<A HREF="' . $href . "\">(staff view)</A> %s\n",
                    $var [ 'biblionumber' ],
                    $var [ 'biblionumber' ],
                    $var [ 'biblionumber' ],
                    $var [ 'field610a' ] ) ;
        }

    }

    echo "</PRE>" ;
}

$time_end = microtime ( true ) ;
$time = $time_end - $time_start ;
printf ( "Process time: %.3f seconds", $time ) ;

?>
