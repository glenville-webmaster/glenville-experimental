<?php

if (!isset($_POST)) {
  header("HTTP/1.0 404 Not Found");
  echo "Not found.\n";
  die();
}
$headerStringValue = $_SERVER['HTTP_GLENVILLE_KEY'];
if (!isset($headerStringValue) || $headerStringValue != '98xs-sf23-8sfs-12sa') {
  header("HTTP/1.0 403 Forbidden");
  print 'Not authorized';
  die();
}

class oracle {

  public function get_table($procedure, $args, $input_param) {
    // Connect to Oracle (Banner)
    $gsctest = "(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = gscprod.wvnet.edu)(PORT = 1522))(CONNECT_DATA = (SID = GSCWV)))";
    $conn = oci_connect('GSCWEB', 'W3bS!t3', $gsctest);
    if (!$conn) {
      return FALSE;
    }
    else {
      //Get cursor (SELECT statement results) from stored procedure in Oracle
      $stid = oci_parse($conn, "BEGIN baninst1.web_procedures." . $procedure . "(" . $args . "); END;");
      $curs = oci_new_cursor($conn);

      //Bind out parameter
      //$r=oci_bind_by_name($stid, $input_param, &$curs, -1, OCI_B_CURSOR);
      $r = oci_bind_by_name($stid, $input_param, $curs, -1, OCI_B_CURSOR);
      if (!$r) {
        return FALSE;
      }

      $r = oci_execute($stid);
      if (!$r) {
        return FALSE;
      }

      oci_execute($curs, OCI_DEFAULT);
      if (!$r) {
        return FALSE;
      }

      $count = oci_fetch_all($curs, $table, NULL, NULL, OCI_FETCHSTATEMENT_BY_ROW);
      if (!$count) {
        return FALSE;
      }

      $table_count_array = [$table, $count];
      return $table_count_array;
    }
  }
}

// Setup default fail response.
$response = [FALSE, FALSE];

// Check for a resource.
if (isset($_GET['resource'])) {

  $resource = $_GET['resource'];
  $oracle = new oracle();

  // Just get terms.
  if ($resource == 'term') {
    $response = $oracle->get_table("p_term_list", ":term_list", ":term_list");
  }
  // Get courses based on terms.
  elseif ($resource == 'courses') {
    // For some reason no term_id just bail.
    if (isset($_GET['term_id'])) {
      $response = $oracle->get_table("p_class_schedule", "'" . $_GET['term_id'] . "',:class_list", ":class_list");
    }
  }
  elseif ($resource == 'rotation') {
    $response = $oracle->get_table("p_course_rotation",":course_rotation",":course_rotation");
  }
}

// Return json.
print $json = json_encode(['data' => $response[0], 'count' => $response[1]]);;
die();

?>
