<?php

namespace Drupal\glenville_oracle\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\glenville_oracle\PdfCourseSchedule;
use Drupal\glenville_oracle\PdfCourseRotation;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;

/**
 * Class CourseScheduleForm.
 */
class CourseScheduleForm extends FormBase {

	/**
	* The key to the data.
	*/
	const GLENVILLE_API_KEY = '98xs-sf23-8sfs-12sa';

  	/**
   	* GuzzleHttp\Client definition.
   	*
   	* @var \GuzzleHttp\Client
   	*/
  	protected $httpClient;

  	/**
   	* Cache definition.
  	 *
   	* @var \Drupal\Core\Cache\CacheBackendInterface
   	*/
  	protected $cache;

  	/**
   	* Constructs a new CourseScheduleForm object.
   	*/
  	public function __construct(Client $http_client, CacheBackendInterface $cache) {
    		$this->httpClient = $http_client;
    		$this->cache = $cache;
  	}

  	/**
   	* {@inheritdoc}
   	*/
  	public static function create(ContainerInterface $container) {
    		return new static(
      			$container->get('http_client'),
      			$container->get('cache.default')
    		);
  	}

  	/**
   	* {@inheritdoc}
   	*/
  	public function getFormId() {
    		return 'course_schedule_form';
  	}

  	/**
   	* {@inheritdoc}
   	*/
	// Build the Course Schedule Form
  	public function buildForm(array $form, FormStateInterface $form_state) {
		// Get Term List from Database
    		$term_data = $this->getOracleData(GLENVILLE_ORACLE_REMOTE_URL . '?resource=term', 'terms', 3600);
    		if (!$term_data) {
      			drupal_set_message('Unable to load courses at this time, please try again later', 'error');
      			return [];
    		}

		// Set Form wrapper divs
    		$form['#prefix'] = '<div id="course-schedule-form-wrapper" class="course-schedule">';
    		$form['#suffix'] = '</div>';

		// Populate Term Select Dropdown
    		$term_options = ['0' => $this->t("-- Select --")];
    		foreach ($term_data->data as $term) {
      			if ($term->STDNT_VIEW === 'Y') {
        			$term_options[$term->TERM_CODE . ':' . $term->STDNT_VIEW] = $term->TERM_DESC;
      			}
    		}

    		$ajax_options['#ajax'] = [
      			'callback' => '::ajaxSubmit',
      			'wrapper' => 'course-schedule-form-wrapper',
      			'effect' => 'fade',
    		];

      		// Course Rotation.
      		$form['pdf_submit_rotation'] = [
        		'#type' => 'submit',
        		'#value' => 'Course Rotation PDF',
        		'#attributes' => [
          			'class' => [
            				'col',
            				'mx-0',
            				'mt-1',
            				'mb-4',
            				'btn',
            				'btn-info'
          			],
        		],
        		'#id' => 'rotation-pdf-generate',
      		];

    		$form['term'] = [
      			'#type' => 'select',
      			'#title' => $this->t('Term'),
      			'#options' => $term_options,
      			'#attributes' => [
        			'autocomplete' => 'off',
      			],
    		];
    		$form['term'] += $ajax_options;
    		
    		if(empty($_GET)) {
    			$term_value = $form_state->getValue('term', FALSE);
    		} else {
    			$term_value = $_GET['term'];
    		}

    		if (!empty($term_value)) {
      			$term_value = explode(':', $term_value);
      			$term = $term_value[0];
      			$student_view = $term_value[1];
      			$form_state->setValue('student_view', $student_view);

			// If there is no course data for the term, display an error
      			$cid = 'term:' . $term;
      			if (!$course_data = $this->getOracleData(GLENVILLE_ORACLE_REMOTE_URL . '?resource=courses&term_id=' . $term, $cid)) {
        			$form['error'] = [
          				'#markup' => '<div class="bg-danger text-white m-2 p-2">The course schedule could not be loaded at this time, please try again later.</div>',
        			];
        		return $form;
      			}

			$pdf_course_data = $course_data;

			//Remove courses if not at Glenville
			foreach ($course_data->data as $key => $course) {
				if ($course->CAMP_CODE != '1') {
					unset($course_data->data[$key]);
				}
			}

      			// Build the Legend
      			$form['legend'] = [
        			'#theme' => 'item_list',
        			'#items' => [
          				'closed' => ['#markup' => '<span class="course-status-closed">Closed classes are displayed in ORANGE</span>'],
					'hybrid' => ['#markup' => '<span class="course-status-hybrid">Hybrid classes are displayed in BLUE</span>'],
					'online' => ['#markup' => '<span class="course-status-online">Online classes are displayed in GREEN; please contact the instructor for more information.</span>'],
          				'off_campus' => ['#markup' => '<span class="course-status-off-campus">Off-Campus classes are displayed in PINK.</span>'],
        			],
      			];

      			// Create Filters.
      			$form['filters'] = [
        			'#type' => 'container',
        			'#attributes' => [
          				'class' => ['row'],
        			],
      			];

	      		$departments = [];
	      		$instructors = [];
			$date_ranges = [];

			// Grab list of values for Filter dropdowns
	      		foreach ($course_data->data as $course) {
				// Set Department List
				$departments[$course->CRSE_SUBJ] = $course->CRSE_DEPT . ' (' . $course->CRSE_SUBJ . ')';

				// Set list based on course start/end dates
				$date_ranges[$course->CRSE_DATES] = date_format(date_create($course->CRSE_START),"M. d, Y") . ' - ' . date_format(date_create($course->CRSE_END),"M. d, Y");

				// Set instructor list if instructor email isn't empty
				if (!empty($course->INSTR_EMAIL)) {
		  			$instructors[$course->INSTR_NAME] = $course->INSTR_NAME;
				}
	      		}

			ksort($instructors);

			// Create department dropdown
	      		$form['filters']['department'] = [
				'#title' => t('Department'),
				'#type' => 'select',
				'#options' => $departments,
				'#default_value' => $form_state->getValue('department', ''),
				'#wrapper_attributes' => [
		  			'class' => ['col-lg'],
				],
				'#attributes' => [
		  			'data-placeholder' => '-- Select Department --',
				],
				'#multiple' => TRUE,
				'#chosen' => TRUE,
	      		];

			// Create Course Type dropdown
	      		$form['filters']['type'] = [
				'#title' => t('Course Type'),
				'#type' => 'select',
				'#options' => [
					'' => '-- Select Type --',
					'on' => 'On Campus',
					'hybrid' => 'Hybrid',
					'online-all' => 'Online (All)',
					'online-as' => 'Online (Asynchronous)',
					'online-s' => 'Online (Synchronous)',
					'off' => 'Off Campus',
				],
				'#default_value' => $form_state->getValue('type', ''),
				'#wrapper_attributes' => [
		  			'class' => ['col-lg'],
				],
	      		];

			// Create Date Range dropdown
			$form['filters']['date_ranges'] = [
				'#title' => t('Course Dates'),
				'#type' => 'select',
				'#options' => $date_ranges,
				'#default_value' => $form_state->getValue('date_ranges', ''),
				'#wrapper_attributes' => [
		  			'class' => ['col-lg'],
				],
				'#attributes' => [
		  			'data-placeholder' => '-- Course Dates --',
				],
				'#multiple' => TRUE,
				'#chosen' => TRUE,
	      		];

			// Create Course Status dropdown
	      		$form['filters']['status'] = [
				'#title' => t('Course Status'),
				'#type' => 'select',
				'#options' => [
		  			'' => '-- Select Status --',
		  			'open' => 'Open',
		  			'closed' => 'Closed',
				],
				'#default_value' => $form_state->getValue('status', ''),
				'#wrapper_attributes' => [
		  			'class' => ['col-lg'],
				],
	      		];

	      		$form['filters']['instructor'] = [
				'#title' => t('Course Instructor'),
				'#type' => 'select',
				'#options' => $instructors,
				'#default_value' => $form_state->getValue('instructor', ''),
				'#wrapper_attributes' => [
		  			'class' => ['col-lg'],
				],
				'#attributes' => [
		  			'data-placeholder' => '-- Select Instructor --',
				],
				'#multiple' => TRUE,
				'#chosen' => TRUE,
	      		];

	      		// Add ajax to all filters.
	      		foreach (Element::children($form['filters']) as $filter) {
				$form['filters'][$filter] += $ajax_options;
	      		}

	      		// Submit for PDF.
	      		$form['pdf_submit'] = [
				'#type' => 'submit',
				'#value' => 'Download PDF',
				'#attributes' => [
		  			'class' => [
		    				'col',
		    				'mx-0',
		    				'mt-1',
		    				'mb-4',
		    				'btn',
		    				'btn-info'
		  			],
				],
				'#id' => 'course-pdf-generate',
	      		];

	      		// Output.
	      		$courses = $this->applyFilters($course_data->data, $form, $form_state);
			$pdf_courses = $this->applyFilters($pdf_course_data->data, $form, $form_state);

			// If course array is empty, show an error
	      		if (empty($courses)) {
				$form['none'] = [
		  			'#markup' => '<p class="text-danger">There are no courses that meet your criteria.</p>',
				];
				return $form;
	      		}

	      		// Save for submit.
	      		$form['courses'] = [
				'#type' => 'value',
				'#value' => $pdf_courses,
	      		];

			// Set Table Headers
	      		$header = [
				'CRSE_REF' => 'CRN',
				'CRSE_INFO' => 'COURSE',
				'CRSE_TITLE' => 'TITLE',
				'CRED_HRS' => 'CR',
                                'TYPE_INFO' => 'TYPE',
				'CRSE_DAYS' => 'DAYS',
				'CRSE_TIME' => 'TIME',
				'INSTR_EMAIL' => 'INSTRUCTOR',
				'BLDG_ROOM' => 'BLD RM',
				'CLOSED_IND' => 'WL',
				//'FEE_AMOUNT' => 'FEE',
				'NEXT_KEY' => 'NXT',
	      		];

	      		$cur_dept = NULL;
	      		$cur_term_start = NULL;
	      		$cur_term_end = NULL;
	      		$collapse_class = count($form_state->getValue('department', [])) === 1 ? 'collapse show' : 'collapse';
	      		$building_array = [];

			// Cycle through all of the courses
			foreach ($courses as $course) {
				/* if($course->CAMP_CODE != '1') {
					continue;
				} */			
				
				// If the course isn't in the current Department, create a new department
				// ### I may need to work on this code for classes starting/ending on different dates ###
				if ($cur_dept != $course->CRSE_SUBJ) {

		  			$cur_dept = $course->CRSE_SUBJ;

			  		$form[$cur_dept] = [
			    			'#markup' => '
			      				<div class="card mb-3">
								<div class="card-header p-2">
				  					<a class="text-left d-block btn btn-link" href="#course-' . $course->CRSE_REF . '" data-toggle="collapse">' . $course->CRSE_DEPT . ' (' . $course->CRSE_SUBJ . ')</a>
								</div>
			      					<div class="card-body p-3 ' . $collapse_class . '" id="course-' . $course->CRSE_REF . '">
			      						' . $this->t('<strong>Course(s) listed below will meet: @tstart to @tend</strong>', [
										'@tstart' => $course->CRSE_START,
										'@tend' => $course->CRSE_END,
			      						]) . '
			    						',
			    				'#suffix' => '</div></div>',
			  		];

			  		$form[$cur_dept]['table'] = [
			    			'#type' => 'table',
			    			'#header' => $header,
			    			'#attributes' => [
			      				'class' => ['table', 'table-striped', 'table-sm'],
			    			],
			  		];

					$cur_term_start = $course->CRSE_START;
					$cur_term_end = $course->CRSE_END;
				}
				// If Course dates are different (hopefully)
				elseif ($course->CRSE_START != $cur_term_start || $course->CRSE_END != $cur_term_end) {
			  		$form[$cur_dept]['table'][]['term_change'] = [
			    			'#markup' => $this->t('<strong>Course(s) listed below will meet: @tstart to @tend</strong>', [
			      				'@tstart' => $course->CRSE_START,
			      				'@tend' => $course->CRSE_END,
			    			]),
			    			'#wrapper_attributes' => [
			      				'colspan' => count($header),
			    			],
			  		];

					$cur_term_start = $course->CRSE_START;
					$cur_term_end = $course->CRSE_END;
		       		}

				$classes = [];
				if ($course->OFF_CAMPUS == 'Y') {
			  		$classes[] = 'course-status-off-campus';
				}

				if ($course->CRSE_TYPE == 'Hybrid') {
			  		$classes[] = 'course-status-hybrid';
				}

				if ($course->CRSE_TYPE == 'Web') {
			  		$classes[] = 'course-status-online';
				}

				if ($course->CLOSED_IND != null) {
			  		$classes[] = 'course-status-closed';
				}

				$form[$cur_dept]['table']['course-' . $course->CRSE_REF]['#attributes']['class'] = $classes;

				foreach ($header as $course_key => $header_val) {
			  		$val = isset($course->{$course_key}) ? $course->{$course_key} : '--';

			  		switch($course_key) {
			    			case 'CRSE_INFO':
			      				if (substr($val, -1) == 'H') {
								$val = substr($val, 0, (strlen($val) -1)) . '<strong>H</strong>';
			      				}
							if (substr($val, -1) == 'W') {
								$val = substr($val, 0, (strlen($val) -1)) . '<strong>W</strong>';
			      				}
			      				break;

			    			case 'INSTR_EMAIL':
			      				$name = $val;
			      				if (!empty($course->INSTR_NAME)) {
								$name = $course->INSTR_NAME;
			      				}
			      				$val = '<a class="schedule-link" href="mailto:' . $val . "?Subject=" . $course->CRSE_SUBJ . ' ' . $course->CRSE_NUM . ' Question">' . $name . '</a>';
			      				break;

			    			//case 'FEE_AMOUNT':
			      			//	$val = $val != '--' ? '$' . $val : $val;
			      			//	break;

						case 'TYPE_INFO':
			      				if ($val == '--') {
								$val = "Traditional";
							}
			      				break;
						case 'WEB_TYPE':
			      				if ($val == '--') {
								$val = '';
							}
			      				break;
			  		}

			  		$form[$cur_dept]['table']['course-' . $course->CRSE_REF][$course_key] = [
			    			'data' => [
			      				'#markup' => $val,
			    			],
			  		];
				}

				if (!empty($course->CRSE_NARR) && $course->CRSE_NARR != ' ') {
			  		$form[$cur_dept]['table'][]['course_info'] = [
			    			'#markup' => $course->CRSE_NARR,
			    			'#wrapper_attributes' => [
			      				'class' => ['text-italic', 'pl-5'],
			      				'colspan' => count($header),
			    			],
			  		];
				}

				$cur_term_start = $course->CRSE_START;
				$cur_term_end = $course->CRSE_END;

				if (!empty($course->OFF_CAMPUS) && !empty($course->BLDG_CODE) && !empty($course->BLDG_DESC)) {
		  			$building_array[$course->OFF_CAMPUS][] = [
		    				$course->BLDG_CODE,
		    				$course->BLDG_DESC
		  			];
				}
      			}

	      		// Output the legends.
			if (isset($building_array['N']) || isset($building_array['Y'])) {
				$form['building_legend'] = [
		  			'#markup' => '
		      				<div class="schedule-legend card mb-3">
		        				<div class="p-2">
		          					<a class="text-left d-block btn btn-link bg-secondary text-white" href="#building-key" data-toggle="collapse">Building Key</a>
		        				</div>
		      					<div class="card-body p-2 collapse show" id="building-key">
		        					<div class="container">
					',
		  			'#suffix' => '</div></div></div>',
				];

				if (isset($building_array['N'])) {
		  			$on_campus = (array_map("unserialize", array_unique(array_map("serialize", $building_array['N']))));
		  			sort($on_campus);

		  			$form['building_legend']['on_campus'] = [
		    				'#markup' => '<div><strong>On Campus</strong><div class="row">',
		    				'#suffix' => '</div></div>',
		  			];

		  			foreach ($on_campus as $location) {
		    				$form['building_legend']['on_campus'][$location[0]] = [
		      					'#markup' => '<div class="col-4">' . $location[0] . ' - ' . $location[1] . '</div>',
		    				];
		  			}
				}

				if (isset($building_array['Y'])) {
		  			$off_campus = (array_map("unserialize", array_unique(array_map("serialize", $building_array['N']))));
		  			sort($off_campus);

		  			$form['building_legend']['off_campus'] = [
		    				'#markup' => '<div class="pt-2"><strong>Off Campus</strong><div class="row">',
		    				'#suffix' => '</div></div>',
		  			];

		  			foreach ($off_campus as $location) {
		    				$form['building_legend']['off_campus'][$location[0]] = [
		      					'#markup' => '<div class="col-4">' . $location[0] . ' - ' . $location[1] . '</div>',
		    				];
		  			}
				}
	      		}

      			// Next offering. @TODO: make this configurable.
			$form['next_offered'] = [
				'#markup' => '
			  		<div class="schedule-legend card mb-3">
			    			<div class="p-2">
			      				<a class="text-left d-block btn btn-link bg-secondary text-white" href="#next-key" data-toggle="collapse">Next (NXT) Time Offered Key</a>
			    			</div>
			    			<div class="card-body p-2 collapse show" id="next-key">
			      				<div class="container">
								<div class="row">
				  					<div class="col-4">FA - Fall Only</div>
										<div class="col-4">FAE - Fall Even Numbered Years</div>
									  	<div class="col-4">FAO - Fall Odd Numbered Years</div>
									  	<div class="col-4">SP - Spring Only</div>
									  	<div class="col-4">SPE - Spring Even Numbered Years</div>
									  	<div class="col-4">SPO - Spring Odd Numbered Years</div>
									  	<div class="col-4">SU - Summer Only</div>
									 	<div class="col-4">18M - 18 Month Rotation (ex: FA14, SP16, FA18)</div>
									 	<div class="col-4">IRR - Irregular Rotation</div>
									  	<div class="col-4">ES - Every Semester (Fall & Spring)</div>
									</div>
									<div class="mt-4 text-danger">**Off Campus courses are not on a set rotation</div>
			      					</div>
			    				</div>
			  			</div>
				',
			];
		}

    		return $form;
  	}


  	/**
   	*  AJAX CALLBACK
   	*/
  	public function ajaxSubmit(array $form, FormStateInterface $form_state) {
    		return $form;
  	}

  	/**
   	* {@inheritdoc}
   	*/
  	public function validateForm(array &$form, FormStateInterface $form_state) {
    		parent::validateForm($form, $form_state);
  	}

  	/**
   	* {@inheritdoc}
   	*/
  	public function submitForm(array &$form, FormStateInterface $form_state) {

    		$triggerdElement = $form_state->getTriggeringElement();
    		if ($triggerdElement['#id'] == 'course-pdf-generate') {
      			$courses = $form_state->getValue('courses');
      			$term_value = $form_state->getValue('term');
      			$term_value = explode(':', $term_value);
      			$term = $term_value[0];
      			$student_view = $term_value[1];

      			$pdf = new PdfCourseSchedule($courses, $term, $student_view);
      			$pdf->generateCourseSchedule();
    		}

    		if ($triggerdElement['#id'] == 'rotation-pdf-generate') {
      			if ($course_data = $this->getOracleData(GLENVILLE_ORACLE_REMOTE_URL . '?resource=rotation', 'rotation', 60)) {
          			$pdfRotation = new PdfCourseRotation($course_data->data);
          			$pdfRotation->AliasNbPages();
          			$pdfRotation->generateCourseRotation();
      			}
    		}
	}

  	protected function getOracleData($path, $cid, $expire = 20) {
    		$cid = 'glenville_oracle:' . $cid;
    		if ($cache = $this->cache->get($cid)) {
      			$data = $cache->data;
    		}
    		else {
      			// Don't cache if bad response
      			if ($data = $this->callAPI($path)) {
        			$this->cache->set($cid, $data, time() + $expire);
      			}
    		}
    		return $data;
  	}

  	protected function callAPI($path) {
    		try {
      			$request = $this->httpClient->post($path, ['headers' => ['glenville-key' => self::GLENVILLE_API_KEY]]);
      			$response = json_decode((string) $request->getBody());
      			if (empty($response->count)) {
        			return FALSE;
      			}
    		} catch (RequestException $e) {
      			return FALSE;
    		}
    		return $response;
  	}

  	/**
   	* Applies selected filters to the course data.
   	*
   	* @param $courses
   	* @param array $form
   	* @param \Drupal\Core\Form\FormStateInterface $form_state
   	*
   	* @return array
   	*/
	// Apply the selected filters
  	protected function applyFilters($courses, array $form, FormStateInterface $form_state) {
		// Apply Department filter
    		if ($department_filter = $form_state->getValue('department')) {
      			$courses = array_filter($courses, function ($value) use ($department_filter) {
        			return in_array($value->CRSE_SUBJ, $department_filter);
      			});
    		}


		if ($type_filter = $form_state->getValue('type')) {
      			$courses = array_filter($courses, function($value) use ($type_filter) {
				switch ($type_filter) {
					case 'on':
						return $value->OFF_CAMPUS != 'Y' && $value->CRSE_TYPE != 'Hybrid' && $value->CRSE_TYPE != 'Web';
						break;
					case 'hybrid':
						return $value->CRSE_TYPE == 'Hybrid';
						break;
					case 'online-all':
						return $value->CRSE_TYPE == 'Web';
						break;
					case 'online-as':
						return $value->CRSE_TYPE == 'Web' && $value->WEB_TYPE == 'Async';
						break;
					case 'online-s':
						return $value->CRSE_TYPE == 'Web' && $value->WEB_TYPE == 'Sync';
					case 'off':
						return $value->OFF_CAMPUS == 'Y';
						break;
					default:
						return FALSE;
				}
			});
    		}

		if ($date_ranges_filter = $form_state->getValue('date_ranges')) {
      			$courses = array_filter($courses, function ($value) use ($date_ranges_filter) {
        			return in_array($value->CRSE_DATES, $date_ranges_filter);
      			});
    		}

    		if ($status_filter = $form_state->getValue('status')) {
      			$courses = array_filter($courses, function ($value) use ($status_filter) {
        			return $status_filter == 'open' ? $value->CLOSED_IND == null : $value->CLOSED_IND != null;
      			});
    		}

    		if ($instructor_filter = $form_state->getValue('instructor')) {
      			$courses = array_filter($courses, function ($value) use ($instructor_filter) {
				return in_array($value->INSTR_NAME, $instructor_filter);
      			});
    		}

    		return $courses;
  	}

}
