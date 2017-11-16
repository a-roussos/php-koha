<?php

/*
 * koha_610a_686c_998a_sar.php -- Field 610$a/686$c/998$a search & replace.
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

if ( isset ( $_POST [ 'searchfor' ] ) &&
    isset ( $_POST [ 'replacewith' ] ) &&
    isset ( $_POST [ 'dryrun' ] ) ) {
    $sf = $_POST [ 'searchfor' ] ;
    $rw = $_POST [ 'replacewith' ] ;
    $dr = $_POST [ 'dryrun' ] ;

    if ( ( $sf != "" ) && ( $rw != "" ) ) {
        if ( $sf != $rw ) {
            if ( ( $_POST [ 'f610a' ] == "" ) &&
                ( $_POST [ 'f686c' ] == "" ) &&
                ( $_POST [ 'f998a' ] == "" ) ) {
                echo "<pre>Please select at least one field to replace.</pre>" ;
            } else {
                $f610a = ( $_POST [ 'f610a' ] == "on" ) ? "on" : "off" ;
                $f686c = ( $_POST [ 'f686c' ] == "on" ) ? "on" : "off" ;
                $f998a = ( $_POST [ 'f998a' ] == "on" ) ? "on" : "off" ;
                echo "<xmp style=\"font: normal 9pt Courier New\">"
                    . "Fields: "
                    . "610a = " . $f610a . "\n        "
                    . "686c = " . $f686c . "\n        "
                    . "998a = " . $f998a . "\n\n"
                    . "Searching for: \"" . $sf
                    . "\" and replacing with: \"" . $rw . "\"\n" ;
                search_and_replace (
                    $sf,
                    $rw,
                    $f610a,
                    $f686c,
                    $f998a,
                    $dr ) ;
            }
        } elseif ( $sf === $rw )
            echo "<pre>Both fields contain the same value.</pre>" ;
    } else
        echo "<pre>Please fill in both fields.</pre>" ;
} else {
?>
<form action="koha_610a_686c_998a_sar.php" method="post">
 <table>
  <tr>
   <td align="right">Search for:</td>
   <td><input size="80" type="text" name="searchfor"></td>
  </tr>
  <tr>
   <td align="right">Replace with:</td>
   <td><input size="80" type="text" name="replacewith"></td>
  </tr>
  <tr>
   <td align="right" valign="top">Fields to replace:</td>
   <td rowspan=3><input type="checkbox" name="f610a" checked="checked">610a<br>
   <input type="checkbox" name="f686c">686c<br>
   <input type="checkbox" name="f998a">998a</td>
  </tr>
 </table>
 <input type="hidden" name="dryrun" value="1"></td>
 <p><input type="submit"></p>
</form>
<?php
}

function search_and_replace (
    $searchfor,
    $replacewith,
    $field610a,
    $field686c,
    $field998a,
    $dryrun ) {

    $conn = mysqli_connect (
        '',     // hostname
        '',     // username
        '',     // password
        '' ) ;  // database

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
        $count = 0 ;
        while ( $row = mysqli_fetch_assoc ( $res ) ) {
            $found = 0 ;
            $marcxmlbefore = $row [ 'marcxml' ] ;
            $record = simplexml_load_string ( $row [ 'marcxml' ] ) ;
            $record -> registerXPathNamespace (
                "ns",
                "http://www.loc.gov/MARC21/slim" ) ;

            if ( $field610a == "on" ) {
                $subfield = $record -> xpath(
                    '//ns:record/ns:datafield[@tag="610"]/ns:subfield') ;
                foreach ( $subfield as $key => $value ) {
                    if ( $subfield [ $key ] == $searchfor ) {
                        $subfield [ $key ] [ 0 ] = $replacewith ;
                        $found = 1 ;
                        $count ++ ;
                    }
                }
            }

            if ( $field686c == "on" ) {
                $subfield = $record -> xpath(
                    '//ns:record/ns:datafield[@tag="686"]/ns:subfield') ;
                foreach ( $subfield as $key => $value ) {
                    if ( $subfield [ $key ] == $searchfor ) {
                        $subfield [ $key ] [ 0 ] = $replacewith ;
                        $found = 1 ;
                        $count ++ ;
                    }
                }
            }

            if ( $field998a == "on" ) {
                $subfield = $record -> xpath(
                    '//ns:record/ns:datafield[@tag="998"]/ns:subfield') ;
                foreach ( $subfield as $key => $value ) {
                    if ( $subfield [ $key ] == $searchfor ) {
                        $subfield [ $key ] [ 0 ] = $replacewith ;
                        $found = 1 ;
                        $count ++ ;
                    }
                }
            }

            if ( $found == 1 ) {

                $str = implode (
                    "\n",
                    array_slice ( explode ( "\n", $record -> asXML ( ) ), 2 ) ) ;
                $marcxmlafter =
'<?xml version="1.0" encoding="UTF-8"?>
<record
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.loc.gov/MARC21/slim http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd"
    xmlns="http://www.loc.gov/MARC21/slim">
' . $str ;

                if ( $dryrun == 1 ) {
                    echo "\nI will update biblio " . $row [ 'biblionumber' ]
                        . " as follows:\n\n" ;
                    echo xdiff_string_diff (
                        str_replace ( "\r", "", $marcxmlbefore ),
                        $marcxmlafter ) ;
                    // echo xdiff_string_diff ( $marcxmlbefore, $marcxmlafter ) ;
                } elseif ( $dryrun == 0 ) {
                    $qry =
                        "UPDATE
                            biblioitems
                        SET
                            marcxml = \"" . mysqli_real_escape_string (
                                $conn, $marcxmlafter ) . "\"
                        WHERE
                            biblionumber = " . $row [ 'biblionumber' ] ;
                    // echo "<pre>" . $qry . "</pre>" ;
                    if ( ! mysqli_query ( $conn, $qry ) ) {
                        printf (
                            "mysqli_query failed: %s\n",
                            mysqli_error ( $conn ) ) ;
                        exit ;
                    } else {
                        echo "\nUpdated biblio " . $row [ 'biblionumber' ] ;
                    }

                    $qry = "INSERT INTO
                                zebraqueue (
                                    biblio_auth_number,
                                    operation,
                                    server,
                                    done )
                            VALUES ( "
                                . $row [ 'biblionumber' ] . ", "
                                . "\"specialUpdate\", "
                                . "\"biblioserver\", "
                                . "0 )" ;
                    // echo $qry . "\n" ;
                    if ( ! mysqli_query ( $conn, $qry ) ) {
                        printf (
                            "mysqli_query failed: %s\n",
                            mysqli_error ( $conn ) ) ;
                        exit ;
                    }
                }
            }
        }
    } else
        exit ;

    mysqli_close ( $conn ) ;

    if ( $dryrun == 1 ) {
        print "\n$count change(s) to be committed.\n</xmp>\n" ;
?>
<form action="koha_610a_686c_998a_sar.php" method="post">
 <input type="hidden" name="searchfor" value="<?php echo $searchfor ; ?>">
 <input type="hidden" name="replacewith" value="<?php echo $replacewith ; ?>">
 <input type="hidden" name="dryrun" value="0">
 <input type="hidden" name="f610a" value="<?php echo $field610a ; ?>">
 <input type="hidden" name="f686c" value="<?php echo $field686c ; ?>">
 <input type="hidden" name="f998a" value="<?php echo $field998a ; ?>">
 <input type="submit" value="Commit changes" style="color: #ff0000">
</form>
<?php
    } elseif ( $dryrun == 0 ) {
        print "\n\n$count change(s) have been committed.\n</xmp>\n" ;
        print "<a href=\"koha_610a_686c_998a_sar.php\">Start again</a><br><br>" ;
    }

}

$time_end = microtime ( true ) ;
$time = $time_end - $time_start ;
printf ( "\nProcess time: %.3f seconds", $time ) ;

?>
