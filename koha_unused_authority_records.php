<?php

/*
 * koha_unused_authority_records.php -- Displays authority records that aren't
 * being used in any bibliographic records.
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

$time_start = microtime ( true ) ;

$count = 0 ;
echo "<pre>\n" ;

// Please fill in the next five variables.
$dbhost = '' ;
$dbuser = '' ;
$dbpass = '' ;
$dbname = '' ;
$kohastaffurl = 'http://' ;

$urlsuffix = '/cgi-bin/koha/authorities/detail.pl?authid=' ;

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
        authid
    FROM
        auth_header
    ORDER BY
        authid ASC' ;

if ( ! $res = mysqli_query ( $conn, $query ) ) {
    printf ( "mysqli_query failed: %s\n", mysqli_error ( $conn ) ) ;
    exit ;
}

if ( mysqli_num_rows ( $res ) != 0 ) {

    $z3950host = '127.0.0.1:9998/biblios' ;
    $z3950connid = yaz_connect ( $z3950host ) ;
    yaz_syntax ( $z3950connid, 'unimarc' ) ;

    while ( $row = mysqli_fetch_assoc ( $res ) ) {

        $z3950query =
            '@attrset Bib-1 @attr 1=Koha-Auth-Number ' . $row [ 'authid' ] ;
        yaz_search ( $z3950connid, 'rpn', $z3950query ) ;
        yaz_wait ( ) ;
        $z3950hits = yaz_hits ( $z3950connid ) ;

        if ( $z3950hits == 0 ) {
            echo 'authid '
                . '<a href="' . $kohastaffurl . $urlsuffix
                . $row [ 'authid' ] . '" target="_blank">'
                . $row [ 'authid' ] . "</a> is used in 0 bib records\n" ;
            $count ++ ;
        }
    }

    yaz_close ( $z3950connid ) ;

}

echo "\n$count record(s) found\n\n" ;

mysqli_close ( $conn ) ;

$time_end = microtime ( true ) ;
$time = $time_end - $time_start ;
printf ( "Process time: %.3f seconds\n", $time ) ;

echo "<pre>\n" ;

?>
