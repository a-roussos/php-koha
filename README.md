# php-koha
Various PHP scripts that interact with the Koha ILS

Please note that these scripts refer to **UNIMARC** fields.

Script name | Description
----------- | -----------
koha_010a_ISBN_validator.php | Fetches the ISBN from field 010$a and validates it
koha_610a_686c_998a_sar.php | Performs search & replace in fields 610$a/686$c/998$a
koha_610a_duplicates.php | Identifies duplicate information in field 610 subfield 'a'
koha_610a_ind1_not_0.php | Finds biblios where field 610 indicator 1 is not 0
koha_7xx_multiple_code_4.php | Displays biblios where field 7xx contains multiple '4' (i.e. role code) subfields
koha_7xx_without_indicators.php | Displays biblios where a 7xx field has no indicators set
koha_fix_auths_leader.php | Removes trailing space from authority leader
