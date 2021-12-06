<?php

namespace Drupal\glenville_oracle;

use Drupal\Core\Url;
use FPDF;


/**
 * Class PdfCourseSchedule
 *
 * @package Drupal\glenville_oracle
 */
class PdfCourseSchedule extends FPDF {

  /**
   * The courses passed from the form.
   *
   * @var array
   */
  protected $courses;

  /**
   * The current Term id.
   *
   * @var string
   */
  protected $term;

  /**
   * If this is the student view or not.
   *
   * @var string
   */
  protected $studentView;

  /**
   * The angle for rotation.
   *
   * @var int
   */
  protected $angle;

  /**
   * The current term header.
   *
   * @var mixed
   */
  protected $header_term;

  /**
   * PdfCourseSchedule constructor.
   *
   * @param $course
   * @param $term
   * @param string $student_view
   */
  public function __construct($course, $term, $student_view = 'N', $orientation = 'L', $unit = 'mm', $size = 'A4') {
    parent::__construct($orientation, $unit, $size);
    $this->courses = $course;
    $this->term = $term;
    $this->studentView = $student_view;
    $this->angle = 0;
  }

  /**
   * @param $angle
   * @param int $x
   * @param int $y
   */
  protected function Rotate($angle, $x = -1, $y = -1) {
    if ($x == -1) {
      $x = $this->x;
    }
    if ($y == -1) {
      $y = $this->y;
    }
    if ($this->angle != 0) {
      $this->_out('Q');
    }
    $this->angle = $angle;
    if ($angle != 0) {
      $angle *= M_PI / 180;
      $c = cos($angle);
      $s = sin($angle);
      $cx = $x * $this->k;
      $cy = ($this->h - $y) * $this->k;
      $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
    }
  }

  /**
   * Override for angled copy.
   */
  protected function _endpage() {
    if ($this->angle != 0) {
      $this->angle = 0;
      $this->_out('Q');
    }
    parent::_endpage();
  }

  /**
   * @param $term_desc
   */
  protected function setTerm($term_desc) {
    $this->header_term = $term_desc;
  }

  /**
   * @return mixed
   */
  protected function getTerm() {
    return $this->header_term;
  }

  /**
   * @param $x
   * @param $y
   * @param $txt
   * @param $angle
   */
  protected function RotatedText($x, $y, $txt, $angle) {
    //Text rotated around its origin
    $this->Rotate($angle, $x, $y);
    $this->Text($x, $y, $txt);
    $this->Rotate(0);
  }

  /**
   * Generate the course schedule heading.
   */
  public function Header() {

    //Put the watermark  http://www.fpdf.org/~~V/en/script/script9.php
    $this->SetFont('Arial', 'B', 50);
    $this->SetTextColor(255, 192, 203);
    if ($this->studentView == "N") {
      $this->RotatedText(35, 220, 'D R A F T   C O P Y', 45);
    }

    // Have to use "GLOBALS" to make variables visible inside the function
    $FirstPage_link = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
    $logo = drupal_get_path('theme', 'glenville') . '/dist/images/logo_blue_500w.png';
    $this->Image($logo, 5, 12, 45, '', '', $FirstPage_link);
    $this->Cell(60); //bounding box for logo
    $this->SetFont('Times', 'B', 20);
    $this->SetTextColor('0', '0', '0');
    $this->Cell(0, 8, $this->getTerm() . ' Course Schedule', 0, 2, 'C');
    $this->SetFont('Arial', '', 9);

    $this->SetTextColor('213', '94', '0');
    $this->Cell('0', '4', 'Closed courses are displayed in Orange.', 'LTR', 2, 'C');
    $this->SetTextColor('0', '114', '178');
    $this->Cell('0', '4', 'Hybrid courses are displayed in Blue.', 'LR', 2, 'C');
    $this->SetTextColor('0', '158', '115');
    $this->Cell('0', '4', 'Online classes are displayed in GREEN; please contact the instructor for more information.', 'LR', 2, 'C');
    $this->SetTextColor('204', '121', '167');
    $this->Cell('0', '4', 'Off-Campus classes are displayed in PINK.', 'LR', 2, 'C');
    $this->SetTextColor('0', '0', '0');
    $this->Cell('85', '4', 'Check the ', 'L', '0', 'R');
    $this->SetFont('Arial', 'U', 9);
    $this->SetTextColor('0', '0', '255');
    $this->Cell('9', '4', 'catalog', '', '0', 'C', '', 'https://www.glenville.edu/academics/college-catalog');
    $this->SetFont('Arial', '', 9);
    $this->SetTextColor('0', '0', '0');
    $this->Cell('133', '4', ' for prerequisite and course requirements.', 'R', 1, 'L');
    $this->Cell(60);
    $this->Cell('85', '4', 'Please see the ', 'LB', '0', 'R');
    $this->SetFont('Arial', 'U', 9);
    $this->SetTextColor('0', '0', '255');
    $this->Cell('40', '4', 'Course Rotation Information', 'B', '0', 'C', '', 'https://www.glenville.edu/academics/course-schedule');
    $this->SetFont('Arial', '', 9);
    $this->SetTextColor('0', '0', '0');
    $this->Cell('102', '4', ' for additional courses.', 'BR', '0', 'L');
    $this->Ln(8);
  }

  /**
   * Overriden footer.
   */
  public function Footer() {
    $this->SetY(-15);
    $this->SetFont('Times', 'I', 10);
    $this->SetTextColor('0', '0', '0');
    $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
  }

  public function generateCourseSchedule() {
    $classlist = $this->courses;
    $class_count = count($this->courses);

    // set up first record values so header can display them
    $first = reset($classlist);
    $this->setTerm($first->TERM_DESC);

    $this->AliasNbPages();
    $this->SetDisplayMode('real', 'default'); // 100% zoom and viewer's default layout
    $this->SetMargins(5, 10);
    $this->AddPage('L'); //P for portrait; L for landscape

    // Create the link identifier so we can use it in every header;
    // setting the identifier in the header itself might cause multiple identifiers.
    // At any rate, this way works.
    $FirstPage_link = $this->AddLink();

    // Make destination for all logos so that when the logo is clicked the user is returned to top of first page
    $this->SetLink($FirstPage_link);

    // Make links to jump to each section of report (ACCT, ART, BIOL...)
    // initialize the department so we know when it changes
    $old_subject = "ChangeMe";
    $old_camp_name = "ChangeMe";
    $counter = 0;

    foreach ($classlist as $class) {
      $subject = $class->CRSE_SUBJ;
      $camp_code = $class->CAMP_CODE;
      $camp_desc = $class->CAMP_DESC;
      if ($subject != $old_subject and $camp_code == '1') {
        $old_subject = $subject;
        $link[$subject] = $this->AddLink();
        $this->SetTextColor('0', '0', '255');
        $this->SetFont('', 'U');
        $counter += 1;
        if (($counter > 8) && ($counter % 9 == 0)) {
          $this->Cell(20, 4, $subject, 0, 1, 'L', 0, $link[$subject]);
        }
        else {
          $this->Cell(20, 4, $subject, 0, 0, 'L', 0, $link[$subject]);
        }
        $this->SetFont('');
        $this->SetTextColor('0', '0', '0');
      }
      elseif($camp_code != '1' and $camp_desc != $old_camp_name) {
          if ($old_camp_name == "ChangeMe") {
              $counter = 0;
              $this->Cell(10, 4, '', 0, 1);
          }
          $link[$camp_desc] = $this->AddLink();
          $this->SetTextColor('0', '0', '255');
          $this->SetFont('', 'U');
          $counter += 1;
          if (($counter > 2) && ($counter % 3 == 0)) {
              $this->Cell(60, 4, $camp_desc, 0, 1, 'L', 0, $link[$camp_desc]);
          }
          else {
              $this->Cell(60, 4, $camp_desc, 0, 0, 'L', 0, $link[$camp_desc]);
          }
          $this->SetFont('');
          $this->SetTextColor('0', '0', '0');
          $old_camp_name = $camp_desc;
      }
    }

    $this->Ln();

    // initialize fields used in loops for sections and headers
    $old_dept_name = "ChangeMe";
    $old_term_start = "ChangeMe";
    $old_term_end = "ChangeMe";
    $old_camp_name = "ChangeMe";

    foreach ($classlist as $class) {
      $term_desc = $class->TERM_DESC;
      $term_code = $class->TERM_CODE;
      $dept_name = $class->DEPT_NAME;
      $camp_code = $class->CAMP_CODE;
      $camp_desc = $class->CAMP_DESC;
      $term_start = $class->CRSE_START;
      $term_end = $class->CRSE_END;
      $course_ref_num = $class->CRSE_REF;
      $subject_long = $class->CRSE_DEPT;
      $subject = $class->CRSE_SUBJ;
      $course_num = $class->CRSE_NUM;
      $section_num = $class->CRSE_SEQ;
      $course_full = $class->CRSE_INFO;
      $course_title = $class->CRSE_TITLE;
      $cr_hrs = $class->CRED_HRS;
      $day = $class->CRSE_DAYS;
      $type = $class->CRSE_TYPE;
      $web_type = $class->WEB_TYPE;
      $type_info = $class->TYPE_INFO;
      $start_time = $class->START_TIME;
      $end_time = $class->END_TIME;
      $crse_time = $class->CRSE_TIME;
      $instructor = $class->INSTR_NAME;
      $bldg_code = $class->BLDG_CODE;
      $room_code = $class->ROOM_CODE;
      $bldg_room = $class->BLDG_ROOM;
      $fee_amount = $class->FEE_AMOUNT;
      $waitlist = $class->CLOSED_IND;
      $off_campus = $class->OFF_CAMPUS;
      $course_info = $class->CRSE_NARR;
      $next = $class->NEXT_KEY;
      $email = $class->INSTR_EMAIL;
      $bldg_desc = $class->BLDG_DESC;

      $building_array[] = [$off_campus, $bldg_code, $bldg_desc];

      if ((trim($course_info) != '') && ($this->GetY() > 185)) { // if we have an italicized comment don't split onto the next page
        $this->AddPage();
      }

      if ($camp_code != '1' and $camp_desc != $old_camp_name) {
        if ($this->GetY() >= 150) { // don't start a new campus at the bottom of the page
          $this->AddPage();
        }
        // Print the header for the new department
        $this->Ln();
        $this->SetFont('Arial', 'B', 15);
        $this->SetLink($link[$camp_desc]);
        $this->Cell(130, 4, 'Classes at ' . $camp_desc, 0, 1);
        $this->Ln();
        //$this->SetFont('Arial', 'B', 9);
        //$this->Cell(30, 4, 'Course(s) listed below will meet: ' . $term_start . ' to ' . $term_end, 0, 1);
	$old_camp_name = $camp_desc;
      }

      elseif ($subject_long != $old_dept_name) {
        if ($this->GetY() >= 150) { // don't start a new department at the bottom of the page
          $this->AddPage();
        }
        // Print the header for the new department
        $this->Ln();
        $this->SetFont('Arial', 'B', 12);
        $this->SetLink($link[$subject]);
        $this->Cell(130, 4, $subject_long . ' (' . $subject . ')', 0, 1);
        $this->Ln();
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 4, 'Course(s) listed below will meet: ' . $term_start . ' to ' . $term_end, 0, 1);
      }
      else if (($old_term_start != $term_start) || ($old_term_end != $term_end)) {
        // Print header for different sub-term within same department course offerings
        $this->Ln();
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 4, 'Course(s) listed below will meet: ' . $term_start . ' to ' . $term_end, 0, 1);
      }
      if (($subject_long != $old_dept_name) || (($old_term_start != $term_start) || ($old_term_end != $term_end)) || ($this->GetY() >185)) {
        $this->SetFont('Arial', 'B', 9);
        $old_dept_name = $subject_long;
        $old_term_start = $term_start;
        $old_term_end = $term_end;
        $this->Cell(12, 5, 'CRN');
        $this->Cell(28, 5, 'COURSE');
        //$this->Cell(12, 5, 'DEPT');
        //$this->Cell(9, 5, 'CRS');
        //$this->Cell(9, 5, 'SCN');
        $this->Cell(52, 5, 'TITLE');
        $this->Cell(9, 5, 'CR');
        $this->Cell(14, 5, 'DAYS');
        $this->Cell(25, 5, 'TYPE');
	//$this->Cell(13, 5, 'TYPE');
	//$this->Cell(13, 5, '');
        $this->Cell(20, 5, 'TIME');
        //$this->Cell(9, 5, 'STR');
        //$this->Cell(11, 5, 'END');
        $this->Cell(26, 5, 'INSTRUCTOR');
        $this->Cell(18, 5, 'ROOM');
        //$this->Cell(9, 5, 'BLD');
        //$this->Cell(9, 5, 'RM');
        $this->Cell(9, 5, 'WL');
        $this->Cell(11, 5, 'FEE');
        $this->Cell(9, 5, 'NXT', 0, 1, 'R');
      }
      if ($type == 'Web') {
        $this->SetTextColor('0', '158', '115');
      }
      if ($type == 'Hybrid') {
        $this->SetTextColor('0', '114', '178');
      }
      if ($off_campus == 'Y') {
        $this->SetTextColor('204', '121', '167');
      }
      if ($waitlist != null) {
        $this->SetTextColor('242', '105', '16');
      } // do waitlist last so that off campus and online do not overwrite the red

      $this->SetFont('Arial', '', 9);
      $this->Cell(12, 4, $course_ref_num);
      if (substr($course_full, -1) == 'W') {
        $course_full = substr($course_full, 0, (strlen($course_full) - 1));
        $this->Cell(21, 4, $course_full);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(7, 4, 'W');
        $this->SetFont('Arial', '', 9);
      }
      elseif (substr($course_full, -1) == 'H') {
        $course_full = substr($course_full, 0, (strlen($course_full) - 1));
        $this->Cell(21, 4, $course_full);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(7, 4, 'H');
        $this->SetFont('Arial', '', 9);
      }
      else {
        $this->Cell(28, 4, $course_full);
      }
      /*$this->Cell(12, 4, $subject);
      $this->Cell(9, 4, $course_num);
      if (substr($section_num, -1) == 'W') {
        $section_num = substr($section_num, 0, (strlen($section_num) - 1));
        $this->Cell(5, 4, $section_num);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(4, 4, 'W');
        $this->SetFont('Arial', '', 9);
      }
      elseif (substr($section_num, -1) == 'H') {
        $section_num = substr($section_num, 0, (strlen($section_num) - 1));
        $this->Cell(5, 4, $section_num);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(4, 4, 'H');
        $this->SetFont('Arial', '', 9);
      }
      else {
        $this->Cell(9, 4, $section_num);
      } */
      $this->Cell(52, 4, $course_title);
      $this->Cell(9, 4, $cr_hrs);
      $this->Cell(14, 4, $day);
      $this->Cell(25, 4, $type_info);
      /*$this->Cell(13, 4, $type);
      $this->Cell(13, 4, $web_type);*/
      $this->Cell(20, 4, $crse_time);
      /* $this->Cell(9, 4, $start_time);
      $this->Cell(11, 4, $end_time);*/
      // make instructor's name an email link if we have email addy for them
      if (isset($email)) {
        $this->SetFont('Arial', 'U', 9);
        $this->SetTextColor('0', '0', '255');
        $mail_link = "mailto:" . $email . "?Subject=" . $subject . " " . $course_num . " Question";
        $this->Cell(26, 4, substr($instructor, 0, 12), '', '0', 'L', '', $mail_link);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor('0', '0', '0');
      }
      else {
        $this->Cell(26, 4, substr($instructor, 0, 12), '', '0', 'L');
      }
      // set special colors again now that we're done with email link.
      if ($type == 'Web') {
        $this->SetTextColor('0', '158', '115');
      }
      if ($type == 'Hybrid') {
        $this->SetTextColor('0', '114', '178');
      }
      if ($off_campus == 'Y') {
        $this->SetTextColor('204', '121', '167');
      }
      if ($waitlist != ' ') {
        $this->SetTextColor('242', '105', '16');
      } // do waitlist last so that off campus and online do not overwrite the red

      $this->Cell(18, 4, $bldg_room);
      /*$this->Cell(9, 4, $bldg_code);
      $this->Cell(9, 4, $room_code);*/
      $this->Cell(9, 4, $waitlist);
      if (isset($fee_amount)) {
        $this->Cell(11, 4, '$' . $fee_amount);
        $this->Cell(14, 4, $next, 0, 1);
      }
      else {
        $this->Cell(11);
        $this->Cell(14, 4, $next, 0, 1);
      }
      $this->SetTextColor('0', '0', '0');
      $this->SetFont('Arial', 'I', 7);
      if (trim($course_info) != '') {
        $this->SetLeftMargin(15);
        $this->SetRightMargin(15);
        $this->Write(4, $course_info);
        $this->SetLeftMargin(5);
        $this->SetRightMargin(5);
        $this->Cell(0, 4, '', 0, 1);
      }
    }
    //if ($this->GetY() > 200) { // need enough room for our building key
      $this->AddPage();
    //}

    $this->Ln(15);
    $this->SetFont('Arial', 'BU', 11);
    $this->Cell('0', '12', 'Building Key', 'LTR', 1, 'L');
    $this->SetFont('Arial', 'IU', 9);
    $this->Cell('0', '10', 'On Campus', 'LR', 1, 'L');
    $this->SetFont('Arial', '', 9);
    $locations = (array_map("unserialize", array_unique(array_map("serialize", $building_array))));
    sort($locations);

    $line = 3;
    $check_campus = 'Y'; // so we know when to change from "on campus" to "off campus" section of key
    for ($j = 0; $j < $class_count; $j++) {
      if (!empty($locations[$j][1])) {
        if ($check_campus == 'Y') {  // we aren't yet in the off campus section of the key
          if ($locations[$j][0] == 'Y') { // if we're now reading off-campus records, print new header
            $check_campus = 'N';
            if ($line % 3 == 0) { // if last line was complete (3 entries), put left and right borders
              $this->Cell('0', 6, '', 'LR', 1);
            }
            else { // otherwise just finish out this line
              $this->Cell('0', 6, '', 'R', 1);
            }
            $this->SetFont('Arial', 'IU', 9);
            $this->SetTextColor('133', '16', '237');
            $this->Cell('0', '10', 'Off Campus', 'LR', 1, 'L');
            $this->SetTextColor('0', '0', '0');
            $this->SetFont('Arial', '', 9);
            $line = 3;
          }
        }

        $line += 1;
        if ($line % 3 == 1) {
          $this->Cell('67', '6', $locations[$j][1] . ' - ' . $locations[$j][2], 'L', 0, 'L'); // left border
        }
        else if ($line % 3 == 2) {
          $this->Cell('67', '6', $locations[$j][1] . ' - ' . $locations[$j][2], '', 0, 'L');  // no border (middle entry)
        }
        else {
          $this->Cell('153', '6', $locations[$j][1] . ' - ' . $locations[$j][2], 'R', 1, 'L'); // right border
        }
      }
    }
    if ($line % 3 != 0) { // if last line was incomplete (1 or 2 entries) finish out the line with a right border
      $this->Cell('0', 6, '', 'R', 1);
    }
    $this->Cell('0', 6, '', 'LBR');

    //if ($this->GetY() > 400) { // need enough room for our building key
     // $this->AddPage();
    //}
    $this->Ln(15);
    $this->SetFont('Arial', 'BU', 11);
    $this->Cell('0', '12', 'Next (NXT) Time Offered Key', 'LTR', 1, 'L');
    $this->SetFont('Arial', '', 9);
    $this->Cell('50', '6', 'FA - Fall Only', 'L', 0, 'L');
    $this->Cell('84', '6', 'FAE - Fall Even Numbered Years', '', 0, 'L');
    $this->Cell('153', '6', 'FAO - Fall Odd Numbered Years', 'R', 1, 'L');
    $this->Cell('50', '6', 'SP - Spring Only', 'L', 0, 'L');
    $this->Cell('84', '6', 'SPE - Spring Even Numbered Years', '', 0, 'L');
    $this->Cell('153', '6', 'SPO - Spring Odd Numbered Years', 'R', 1, 'L');
    $this->Cell('50', '6', 'SU - Summer Only', 'L', 0, 'L');
    $this->Cell('84', '6', '18M - 18 Month Rotation (ex: FA14, SP16, FA18)', '', 0, 'L');
    $this->Cell('153', '6', 'IRR - Irregular Rotation', 'R', 1, 'L');
    $this->Cell('0', '6', 'ES - Every Semester (Fall & Spring)', 'RL', 1, 'L');
    $this->Cell('0', '6', '', 'LR', 1, 'L');
    $this->SetTextColor('204', '121', '167');
    $this->Cell('0', '6', '**Off Campus courses are not on a set rotation', 'LBR', 1, 'L');
    $this->SetTextColor('0', '0', '0');

    $this->Output('D');
  }

}
