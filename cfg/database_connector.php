<?php
          $server= "localhost";
          $cuser= "root";
          $ppassword= "";
          $database= "opf_ref";
          MYSQL_CONNECT($server, $cuser, $ppassword) or die ( "<H3>Server unreachable</H3>");
          MYSQL_SELECT_DB($database) or die ( "<H3>Database non existent</H3>");
?>
