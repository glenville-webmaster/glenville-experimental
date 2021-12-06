<?php

namespace Drupal\glenville_oracle;

use Drupal\Core\Url;
use FPDF;


/**
 * Class PdfCourseRotation
 *
 * @package Drupal\glenville_oracle
 */
class PdfCourseRotation extends FPDF {

  /**
   * The courses passed from the form.
   *
   * @var array
   */
  protected $courses;

  /**
   * PdfCourseRotation constructor.
   *
   * @param $courses
   */
  public function __construct($courses, $orientation = 'P', $unit = 'mm', $size = 'A4') {
    parent::__construct($orientation, $unit, $size);
    $this->courses = $courses;
  }

  /**
   * Generate the course schedule heading.
   */
  public function Header() {
    $FirstPage_link = Url::fromRoute('<front>', [], ['absolute' => TRUE])
      ->toString();
    $logo = drupal_get_path('theme', 'glenville') . '/dist/images/logo_blue_500w.png';
    $this->Image($logo, 5, 12, 45, '', '', $FirstPage_link);
    $this->Cell(60); //bounding box for logo
    $this->SetFont('Times', 'B', 20);
    $this->SetTextColor('0', '0', '0');
    $this->Cell(0, 10, 'Course Rotation Information', 0, 2, 'C');

    $this->SetFont('Arial', 'B', 9);
    $this->SetTextColor('255', '0', '0');
    $this->Cell('0', '4', 'Course Rotations are subject to change.', 'LTR', 2, 'C');
    $this->SetFont('Arial', '', 9);
    $this->Cell('0', '4', 'Please be sure to consult with your Academic Advisor to ensure accuracy.', 'LR', 2, 'C');
    $this->SetTextColor('0', '0', '0');
    $this->SetTextColor('0', '153', '0');
    $this->Cell('0', '4', 'Online courses have additional fees.', 'LR', 2, 'C');
    $this->SetTextColor('0', '0', '0');
    $this->Cell('0', '4', 'Please see the course schedule for course fee amounts.', 'LR', 2, 'C');

    $this->Cell('29', '4', 'Check the ', 'LB', '0', 'R');
    $this->SetFont('Arial', 'U', 9);
    $this->SetTextColor('0', '0', '255');
    $this->Cell('9', '4', 'catalog', 'B', '0', 'C', '', 'http://www.glenville.edu/academics/course-schedule');
    $this->SetFont('Arial', '', 9);
    $this->SetTextColor('0', '0', '0');
    $this->Cell('72', '4', ' for prerequisite and course requirements.', 'BR', 1, 'L');
    $this->Cell(60); //re-establish bounding box (space) for logo

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

  public function generateCourseRotation() {
    $rotation_list = $this->courses;

    $this->SetMargins(20, 5);
    $this->SetFillColor(224, 224, 224);
    $this->AddPage('P'); //P for portrait; L for landscape

    // Make links to jump to each section of report (ACCT, ART, BIOL...)
    // initialize the department so we know when it changes
    $old_subj_code = "ChangeMe";
    $counter = 0;
    foreach ($rotation_list as $rotation) {
      $subj_code = $rotation->SCBCRSE_SUBJ_CODE;
      if ($subj_code != $old_subj_code) {
        $old_subj_code = $subj_code;
        $link[$subj_code] = $this->AddLink();
        $this->SetTextColor('0', '0', '255');
        $this->SetFont('', 'U');
        $counter += 1;
        if (($counter > 8) && ($counter % 9 == 0)) {
          $this->Cell(20, 4, $subj_code, 0, 1, 'L', 0, $link[$subj_code]);
        }
        else {
          $this->Cell(20, 4, $subj_code, 0, 0, 'L', 0, $link[$subj_code]);
        }
        $this->SetFont('');
        $this->SetTextColor('0', '0', '0');
      }
    }
    $this->Ln();

    // initialize fields used in loops for sections and headers
    $old_subj_code = "ChangeMe";

    $counter = 0;
    foreach ($rotation_list as $rotation) {
      $subj_code = $rotation->SCBCRSE_SUBJ_CODE;
      $subj_desc = $rotation->STVSUBJ_DESC;
      $crse_numb = $rotation->SCBCRSE_CRSE_NUMB;
      $crse_title = $rotation->SCBCRSE_TITLE;
      $attr_code = $rotation->SCRATTR_ATTR_CODE;
      $attr_desc = $rotation->STVATTR_DESC;
      $credit_hrs = $rotation->CR_HR;
      $fee = $rotation->FEE_IND;

      if ($subj_code != $old_subj_code) {
        $old_subj_code = $subj_code;
        $this->Ln();
        $this->SetFont('Arial', 'B', 12);
        $this->SetLink($link[$subj_code]);
        $this->Cell(130, 4, $subj_desc . ' (' . $subj_code . ')', 0, 1);
        $this->Ln();
        $this->SetFont('Arial', 'B', 9);
        $this->SetFont('', 'B');
        $this->Cell(11, 4, 'DEPT');
        $this->Cell(10, 4, 'CRS');
        $this->Cell(55, 4, 'TITLE');
        $this->Cell(10, 4, 'CR');
        $this->Cell(15, 4, 'CODE');
        $this->Cell(50, 4, 'ROTATION');
        $this->Cell(10, 4, 'FEE', 0, 1);
        $this->SetFont('');
      }
      if ($counter % 2 == 0) { // shade every other row to make it easier to read
        $this->Cell(11, 4, $subj_code, 0, 0, '', 'true');
        $this->Cell(10, 4, $crse_numb, 0, 0, '', 'true');
        $this->Cell(55, 4, $crse_title, 0, 0, '', 'true');
        $this->Cell(10, 4, $credit_hrs, 0, 0, '', 'true');
        $this->Cell(15, 4, $attr_code, 0, 0, '', 'true');
        $this->Cell(50, 4, $attr_desc, 0, 0, '', 'true');
        $this->Cell(10, 4, $fee, 0, 1, '', 'true');
      }
      else {
        $this->Cell(11, 4, $subj_code);
        $this->Cell(10, 4, $crse_numb);
        $this->Cell(55, 4, $crse_title);
        $this->Cell(10, 4, $credit_hrs);
        $this->Cell(15, 4, $attr_code);
        $this->Cell(50, 4, $attr_desc);
        $this->Cell(10, 4, $fee, 0, 1);
      }

      $counter++;
    }

    $this->Output();
  }

}
