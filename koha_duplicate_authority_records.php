<?php

/*
 * koha_duplicate_authority_records.php -- Displays a list of authority
 * records and attempts to automatically detect and flag duplicate entries.
 * Copyright (C) 2011  Metagnosi Pliroforiki / Nikos Markopoulos
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
 *
 * Changes by a-roussos:
 * 2013-01-02 Include authority id in output
 * 2017-02-07 Include authority fields 676$a and 686$a in output
 * 2017-07-25 Include number of occurences per authority record in output
 * 2018-06-04 Attempt to auto-detect dupes by measuring Levenshtein distance
 */

require 'File/MARCXML.php' ;

// Please fill in the next five variables.
$dbhost = '' ;
$dbuser = '' ;
$dbpass = '' ;
$dbname = '' ;
$kohastaffurl = 'http://<IP_ADDRESS>:<PORT>' ;

$urlsuffix1 = '/cgi-bin/koha/authorities/detail.pl?authid=' ;
$urlsuffix2 =
  '/cgi-bin/koha/catalogue/search.pl?type=intranet&op=do_search&q=an=' ;

$threshold = 0.900 ;

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

if ( ! isset ( $_POST [ 'submit' ] ) ) {
?>
<html>
 <body>
  <form action="koha_duplicate_authority_records.php" method="post">
   <table>
    <tr>
     <td>Select authority type:</td>
     <td>
      <select name="type">
<?php
    $query =
        "SELECT
            authtypecode,
            authtypetext
        FROM
            auth_types
        WHERE
            authtypecode <> ''
        ORDER BY
            authtypetext" ;
    $result = mysqli_query ( $conn, $query ) ;
    if ( $result )
        if ( mysqli_num_rows ( $result ) > 0 )
            while ( $row = mysqli_fetch_assoc ( $result ) )
                echo '<option value="'
                    . $row [ 'authtypecode' ] . '">'
                    . $row [ 'authtypetext' ] . '</option>' ;
?>
      </select>
     </td>
     <td><input type="submit" value="Create HTML report" name="submit"></td>
    </tr>
   </table>
  </form>
 </body>
</html>
<?php
} else if ( isset ( $_POST [ 'submit' ] ) ) {

    $myarr = array ( ) ;

    $query =
        "SELECT
            authid,
            marcxml
        FROM
            auth_header
        WHERE
            authtypecode = '" . $_POST [ 'type' ] . "'" ;
    $result = mysqli_query ( $conn, $query ) ;

    if ( $result ) {
        if ( mysqli_num_rows ( $result ) > 0 ) {
            while ( $row = mysqli_fetch_assoc ( $result ) ) {
                $authid = $row [ 'authid' ] ;
                $journals = new File_MARCXML (
                    $row [ 'marcxml' ], File_MARC::SOURCE_STRING ) ;

                while ( $record = $journals -> next ( ) ) {
                    foreach ( $record -> getFields ( ) as $tag => $subfields ) {
                        if ( substr ( $tag, 0, 1 ) == '2' ) {
                            $sf = "" ;
                            foreach ( $subfields -> getSubfields ( ) as $code => $value )
                                $sf .= " " . $value ;
                            $myarr [ ] = array ( 'authid' => $authid, 'tag' => $tag, 'val' => $sf ) ;
                        } elseif ( $tag == '676' ) {
                            foreach ( $subfields -> getSubfields ( ) as $code => $value )
                                if ( $code == 'a' )
                                    $myarr1 [ ] = array ( 'authid' => $authid, 'tag' => $tag, 'val' => $value -> getData ( ) ) ;
                        } elseif ( $tag == '686' ) {
                            foreach ( $subfields -> getSubfields ( ) as $code => $value )
                                if ( $code == 'a' )
                                    $myarr2 [ ] = array ( 'authid' => $authid, 'tag' => $tag, 'val' => $value -> getData ( ) ) ;
                        }
                    }
                }
            }
        }
    }

    foreach ( $myarr as $key => $row ) {
        $tags [ $key ] = $row [ 'tag' ] ;
        $vals [ $key ] = $row [ 'val' ] ;
    }

    if ( ! empty ( $myarr ) )
        array_multisort ( $vals, SORT_ASC, $myarr ) ;

    $f676a_val = array ( ) ;
    if ( ! empty ( $myarr1 ) ) {
        foreach ( $myarr1 as $k => $v )
           $f676a_val [ $v [ 'authid' ] ] = $v [ 'val' ] . ' ; ' ;
    }

    $f686a_val = array ( ) ;
    if ( ! empty ( $myarr2 ) ) {
       foreach ( $myarr2 as $k => $v )
         $f686a_val [ $v [ 'authid' ] ] = $v [ 'val' ] ;
    }

    $z3950host = '127.0.0.1:9998/biblios' ;
    $z3950connid = yaz_connect ( $z3950host ) ;
    yaz_syntax ( $z3950connid, 'unimarc' ) ;

    $prev = null ;
    foreach ( $myarr as $k => $v ) {

        printf ( "(<a href=\"%s%s%d\" target=\"_blank\">%04d</a>) %s ",
          $kohastaffurl, $urlsuffix1,
          $v [ 'authid' ], $v [ 'authid' ], $v [ 'tag' ] ) ;

        $str1 = substr ( $v [ 'val' ], 0, 255 ) ;
        $str2 = substr ( $prev, 0, 255 ) ;

        // References for the quotient method used below:
        // https://github.com/codeforkjeff/refine_viaf/blob/master/src/main/java/com/codefork/refine/StringUtil.java#L60-L78
        // http://infomotions.com/blog/2016/06/levenshtein/
        $maxlength = max ( strlen ( $str1 ), strlen ( $str2 ) ) ;
        $quotient = (
          ( $maxlength - levenshtein ( $str1, $str2 ) ) / $maxlength ) ;
        if ( $quotient > $threshold )
            echo '<span style="border: 2px solid red;">'
              . $v [ 'val' ] . '</span>' ;
        else
            echo $v [ 'val' ] ;

        if ( array_key_exists ( $v [ 'authid' ], $f676a_val ) )
            echo ' <font color="#ff0000">'
                . rtrim ( $f676a_val [ $v [ 'authid' ] ], ' ; ' )
                . '</font>' ;

        if ( array_key_exists ( $v [ 'authid' ], $f686a_val ) )
            echo ' <font color="#017c22">' . $f686a_val [ $v [ 'authid' ] ]
                . "</font>" ;

        $z3950query =
            '@attrset Bib-1 @attr 1=Koha-Auth-Number ' . $v [ 'authid' ] ;
        yaz_search ( $z3950connid, 'rpn', $z3950query ) ;
        yaz_wait ( ) ;
        $z3950hits = yaz_hits ( $z3950connid ) ;

        echo ' <i>(in <a href="' . $kohastaffurl . $urlsuffix2
            . $v [ 'authid' ] . '" target="_blank">' . $z3950hits
            . '</a> records)</i>' ;
        echo "<br>\n" ;

        $prev = $v [ 'val' ] ;

    }

    yaz_close ( $z3950connid ) ;
}
?>
