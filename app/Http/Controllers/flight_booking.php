 Flight.php
<?php if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class Flight extends CI_Controller
{

	function __construct()
	{
		parent::__construct();

		$this->load->library('webservices/flight/flight_blender');
		$this->load->model('general_model');
		$this->load->model('flight_model'); 
		$this->load->model('insurance_model');
		$this->load->model('flight_airport_cab_model');
		$this->load->model('user_model');
        $this->load->library('translation');
        $this->load->library('yiron_promocode');
	}


	public function service($request_type = '')
	{
		$response['status'] = FAILURE_STATUS;
		$response['message'] = 'Server Error';

		$request = file_get_contents('php://input');
		if (!isJson($request)) {
			$response['Message'] = 'Invalid Request Format.';
			output_service_json_data($response);
		}

		$request = json_decode($request, true);

		switch ($request_type) {
			case 'PreSearch':
				$response = $this->PreSearch($request);
				break;
			case 'GetSearch':
				$response = $this->GetSearch($request);
				break;
			case 'Search':
				$this->Search($request);
				break;
			case 'UpSell':
				$response = $this->Upsell($request);
				break;
			case 'UpdateFareQuote':
				$response = $this->UpdateFareQuote($request);
				break;
			case 'ExtraServices':
				$response = $this->ExtraServices($request);
				break;	
			case 'Addons':
				$response = $this->Addons($request);
				break;
			case 'SearchAirportCabs':
				$response = $this->SearchAirportCabs($request);
				break;
			case 'PreBook':
				$response = $this->PreBook($request);
				break;
			case 'ProcessBooking':
				$response = $this->ProcessBooking($request);
				break;
			case 'GetBookingDetails':
				$response = $this->GetBookingDetails($request);
				break;
			case 'CancelBooking':
				$response = $this->CancelBooking($request);
				break;
			case 'RequestCancellation':
				$response = $this->RequestCancellation($request);
				break;
			case 'SupplierSeed':
				$response = $this->supplier_seed($request);
				break;
			case 'ValidatePromocode':
				$response = $this->ValidatePromocode($request);
				break;
			case 'InsuranceCreateClaim':
				$response = $this->InsuranceCreateClaim($request);
				break;
			case 'InsuranceUploadClaimDocument':
				$response = $this->InsuranceUploadClaimDocument($request);
				break;
			case 'InsuranceCancelPolicy':
				$response = $this->InsuranceCancelPolicy($request);
				break;
			case 'InsuranceClaimStatus':
				$response = $this->InsuranceClaimStatus($request);
				break;
				
			default:
				$response['message'] = 'Invalid Service';
		}
		output_service_json_data($response);
	}

	private function PreSearch($request)
	{
		$response['status'] = FAILURE_STATUS;
		$response['message'] = 'No data found';
		$response['data'] = [];
		$response['errors'] = [];

		// Validate search data first
		$validate_search_params = $this->validate_search_request($request);

		if ($validate_search_params['status'] == FAILURE_STATUS) {
			$response['message'] = $validate_search_params['message'];
			return $response;
		}

		$results = $this->flight_model->save_search_data($request);

		if (!empty($results) && $results['status'] == true) {
			$response['status'] = SUCCESS_STATUS;
			$response['message'] = 'Success';
			$response['data']['search_id'] = $results['search_id'];
		} else {
			$response['message'] = 'Failed';
		}

		return $response;
	}

	/**
	 * CancelBooking - B2C customer requests cancellation (ownership enforced).
	 */
	private function CancelBooking($request)
	{
		$response['status'] = FAILURE_STATUS;
		$response['message'] = '';
		$response['data'] = array();

		$app_reference = trim($request['app_reference'] ?? '');
		$remark = trim($request['remark'] ?? '');

		if (empty($app_reference) || $remark === '') {
			$response['message'] = 'app_reference and remark are required';
			return $response;
		}

		$booking_row = $this->custom_db->single_table_records(
			'flight_booking_details',
			'id, app_reference, status, created_by_id, start_date',
			array('app_reference' => $app_reference)
		);

		if (empty($booking_row['data'])) {
			$response['message'] = 'Invalid app_reference';
			return $response;
		}

		$booking = $booking_row['data'][0];

		$current_user_id = $this->entity_user_id ?? 0;
		if ((int)$booking['created_by_id'] !== (int)$current_user_id) {
			$response['message'] = 'Unauthorized: not the creator of this booking';
			return $response;
		}

		return $this->submit_flight_cancellation_request($app_reference, $remark, $booking, false);
	}

	/**
	 * RequestCancellation - Supervision requests cancellation (same queue flow as B2C).
	 */
	private function RequestCancellation($request)
	{
		$response['status'] = FAILURE_STATUS;
		$response['message'] = '';
		$response['data'] = array();

		$app_reference = trim($request['app_reference'] ?? '');
		$remark = trim($request['remark'] ?? '');

		if (empty($app_reference) || $remark === '') {
			$response['message'] = 'app_reference and remark are required';
			return $response;
		}

		$booking_row = $this->custom_db->single_table_records(
			'flight_booking_details',
			'id, app_reference, status, created_by_id, start_date',
			array('app_reference' => $app_reference)
		);

		if (empty($booking_row['data'])) {
			$response['message'] = 'Invalid app_reference';
			return $response;
		}

		$booking = $booking_row['data'][0];

		return $this->submit_flight_cancellation_request($app_reference, $remark, $booking, true);
	}

	/**
	 * Shared cancellation-request handler for B2C and supervision.
	 */
	private function submit_flight_cancellation_request($app_reference, $remark, $booking, $from_supervision = false)
	{
		$response['status'] = FAILURE_STATUS;
		$response['message'] = '';
		$response['data'] = array();

		if ($booking['status'] !== 'Confirmed') {
			$response['message'] = 'Only confirmed bookings can be cancelled';
			return $response;
		}

		$departure_datetime = $this->get_flight_departure_datetime($booking);
		if (!$this->is_future_flight_departure($departure_datetime)) {
			$response['message'] = 'Cancellation is only allowed before departure';
			return $response;
		}

		$pending_queue = $this->custom_db->single_table_records(
			'flight_cancellation_queue',
			'origin',
			array('app_reference' => $app_reference, 'request_status' => 'PENDING')
		);
		if (!empty($pending_queue['data'])) {
			$response['message'] = 'Cancellation request already pending';
			return $response;
		}

		if ($from_supervision) {
			$remark = 'Supervision: ' . $remark;
		}

		$this->custom_db->update_record(
			'flight_booking_details',
			array('status' => 'CancellationInProcess'),
			array('app_reference' => $app_reference)
		);

		$queue_data = array(
			'app_reference' => $app_reference,
			'remark' => $remark,
			'request_datetime' => date('Y-m-d H:i:s'),
			'admin_remark' => null,
			'request_status' => 'PENDING',
			'admin_update_time' => null,
		);
		$this->custom_db->insert_record('flight_cancellation_queue', $queue_data);

		$this->send_cancellation_email($app_reference, $remark);

		$response['status'] = SUCCESS_STATUS;
		$response['message'] = 'Cancellation request submitted';
		$response['data'] = array(
			'app_reference' => $app_reference,
			'status' => 'CancellationInProcess'
		);

		return $response;
	}

	/**
	 * Resolve earliest departure from itinerary, falling back to booking start_date.
	 */
	private function get_flight_departure_datetime($booking)
	{
		if (!empty($booking['id'])) {
			$itinerary = $this->custom_db->single_table_records(
				'flight_booking_itinerary_details',
				'departure_datetime',
				array('flight_booking_details_id' => intval($booking['id'])),
				0,
				1,
				array('departure_datetime' => 'ASC')
			);
			if (!empty($itinerary['data'][0]['departure_datetime'])) {
				return $itinerary['data'][0]['departure_datetime'];
			}
		}

		return $booking['start_date'] ?? '';
	}

	/**
	 * True when journey departure is still in the future (same rule as B2C dashboard).
	 */
	private function is_future_flight_departure($departure_datetime)
	{
		if (empty($departure_datetime)) {
			return false;
		}
		return strtotime($departure_datetime) > time();
	}

	private function GetSearch($request)
	{
		$response['status'] = FAILURE_STATUS;
		$response['message'] = 'No data found';
		if (!empty($request['search_id'])) {
			$response['status'] = SUCCESS_STATUS;
			$search_id = $request['search_id'];
			if (is_numeric($search_id)) {
				$condition = array();
				$condition[] = array('origin', '=', intval($search_id));
				$searchData = $this->flight_model->get_search_data($condition);
				if (!empty($searchData)) {
					$response['message'] = 'Search data found';
					$response['data'] = $searchData;
				} else {
					$response['message'] = 'No search data found for the given ID';
				}
			} else {
				$response['message'] = 'Invalid Search ID';
			}
		} else {
			$response['message'] = 'Search ID is required';
		}

		return $response;
	}

	function Search($request)
	{
		// setup headers for NDJSON streaming
		while (ob_get_level() > 0)
			ob_end_flush();
		ini_set('output_buffering', 'off');
		ini_set('zlib.output_compression', false);
		ini_set('implicit_flush', 1);
		ob_implicit_flush(true);

		header('Content-Type: application/x-ndjson');
		header('Cache-Control: no-cache');
		header('X-Accel-Buffering: no');

		$search_id = $request['search_id'];

		$this->flight_blender->stream_flight_list($search_id, function ($flightData) {

			$response = [
				'status' => true,
				'message' => 'Inprogress',
				'data' => [
					'flights' => $flightData['data'] ?? [],
					'moreResults' => true,
				],
				'errors' => null,
			];

			$chunkData = json_encode($response);

			// debug($chunkData);die;

			// Add padding (~5KB) to force flush
			$padding_amount = 2048 - strlen($chunkData);
			if($padding_amount >= 0){
				$padding = str_repeat(' ', $padding_amount);
			} else {
				$padding = str_repeat(' ', 0);
			}
			echo $chunkData . $padding . "\n";
			flush();
		});

		// send final marker
		echo json_encode([
			'status' => true,
			'message' => 'Completed',
			'data' => [
				'flights' => [],
				'moreResults' => false,
			],
			'errors' => null,
		]) . "\n";
		flush();
		exit;
	}
   

	public function UpSell($request)
	{	
		$data['status'] = FAILURE_STATUS;
		$data['message'] = array();
		$data['data'] = array();

		$updated_fare_quote = $this->flight_blender->upsell_flight_list($request);
		if ($updated_fare_quote['status'] == SUCCESS_STATUS) {

			if ($this->app_language != 'en') {
				$updated_fare_quote['data'] = $this->translateFareAttributes($updated_fare_quote['data'], $this->app_language);
			}
			$data['status'] = $updated_fare_quote['status'];
			$data['data'] = $updated_fare_quote['data'];
		} else {
			$data['message'] = $updated_fare_quote['message'];
		}

		return $data;
	}


	private function translateFareAttributes(&$flightData, $lang_code)
	{
		$texts_to_translate = [];
		
		// Iterate through each flight data and collect relevant fare attributes text
		foreach ($flightData as &$flight) {
			if (isset($flight['Attr']['fareAttributes'])) {
				$fareAttributes = $flight['Attr']['fareAttributes'];

				// Collect each category (e.g., Baggage, Flexibility, Seats, Meals & More)
				foreach ($fareAttributes as $category => $values) {
					if (is_array($values)) {
						foreach ($values as $value) {
							if (!empty($value)) {
								$texts_to_translate[] = $value;
							}
						}
					}
				}
			}
		}

		// Deduplicate and filter out invalid strings
		$texts_to_translate = array_unique(array_filter($texts_to_translate, 'is_string'));

		// Translate the collected texts in bulk
		$translated_map = $this->translation->translate($texts_to_translate, $lang_code);

		// Replace the original values with the translated ones
		foreach ($flightData as &$flight) {
			if (isset($flight['Attr']['fareAttributes'])) {
				$fareAttributes = &$flight['Attr']['fareAttributes'];

				foreach ($fareAttributes as $category => &$values) {
					if (is_array($values)) {
						foreach ($values as &$value) {
							// Translate each value if it exists in the translated map
							$lower_value = strtolower($value); // use lowercase to match keys in translated_map
							if (isset($translated_map[$lower_value])) {
								$value = $translated_map[$lower_value];
							}
						}
					}
				}
			}
		}

		unset($flight); // Clean up reference
		return $flightData;
	}

	public function UpdateFareQuote($request)
	{
		$data['status'] = FAILURE_STATUS;
		$data['message'] = array();
		$data['data'] = array();

		$updated_fare_quote = $this->flight_blender->update_fare_quote($request);
		if ($updated_fare_quote['status'] == SUCCESS_STATUS) {
			$data['status'] = $updated_fare_quote['status'];
			$data['data'] = $updated_fare_quote['data'];

			$this->load->model('transaction');

			$booking_cost = floatval($updated_fare_quote['data']['Price']['TotalDisplayFare'] ?? 0);
            $_payment_gateways = $this->transaction->active_payment_gateways();

            $payment_gateways = array_map(function ($pg) use ($booking_cost) {
				$convenience_details = $this->transaction->calculate_convenience_fee_by_code($pg['code'], $booking_cost);

                return [
                    'code' => $pg['code'],
                    'name' => $pg['name'],
                    'convinience_fee' => $convenience_details['fee_value'],
                    'convinience_fee_type' => $convenience_details['fee_type'],
					'convinience_amount' => $convenience_details['fee_amount'],
					'booking_cost' => $booking_cost,
                    'logo' => $this->template->template_images('payment_gateways/' . $pg['code'] . '.png'),
                ];
            }, $_payment_gateways);

			// $page_data['currency_obj'] = $currency_obj;

  
			$promo_code = $this->custom_db->single_table_records('promo_code_list', 'promo_code,description,value,value_type,minimum_amount,maximum_amount,start_date,expiry_date', [
					'status' => 1,
					'module' => META_AIRLINE_COURSE,
					'display_home_page' => 'Yes',
					'is_anonymous' => 0,
					'expiry_date >=' => date('Y-m-d'),
				]
			);

			$active_promocodes = [];
			if (!empty($promo_code['data']) && is_array($promo_code['data'])) {
				$today = date('Y-m-d');
				foreach ($promo_code['data'] as $promo_row) {
					$start_date = $promo_row['start_date'] ?? '';
					if (!empty($start_date) && $start_date !== '0000-00-00 00:00:00' && $start_date !== '0000-00-00') {
						if (date('Y-m-d', strtotime($start_date)) > $today) {
							continue;
						}
					}
					$active_promocodes[] = $promo_row;
				}
			}

            $data['data']['promocode'] = $active_promocodes;
			
            $data['data']['payment_gateways'] = $payment_gateways;

		} else {
			$data['message'] = $updated_fare_quote['message'];
		}

		return $data;
	}

	private function PreBook($request)
    {
        $response['status'] = FAILURE_STATUS;
        $response['message'] = 'No data found';
        $response['data'] = [];
        $response['errors'] = [];
        // Validate search data first
        $validationErrors = $this->validate_booking_params($request);
        if ($validationErrors['status'] == FAILURE_STATUS) {
            $response['message'] = 'Validation failed';
            $response['errors'] = $validationErrors['message'];
            return $response;
        }
	
        $preBookdata = $this->flight_blender->pre_book($request);
	
        if (is_array($preBookdata) && isset($preBookdata['status']) && $preBookdata['status'] == FAILURE_STATUS) {
            $response['status'] = FAILURE_STATUS;
            $response['message'] = $preBookdata['message'] ?? 'Pre booking failed';
            $response['errors'] = $preBookdata['errors'] ?? [];
            $response['data'] = $preBookdata['data'] ?? [];
            return $response;
        }

        if (!empty($preBookdata)) {
            $response['status'] = SUCCESS_STATUS;
            $response['message'] = 'Pre Booking data Saved';
            $response['data'] = $preBookdata;
        }

        return $response;
    }

	/**
	 * ProcessBooking - Holds the ticket and updates database
	 * Returns only app_reference and status
	 */
	private function ProcessBooking($request)
	{
		$response['status'] = FAILURE_STATUS;
		$response['message'] = 'fghfhf';
		$response['data'] = array();

		$ResultToken = trim($request['ResultToken'] ?? '');

		if ($ResultToken) {
			
			$book_response = $this->flight_blender->hold_ticket($request);
			
			if ($book_response['status'] == SUCCESS_STATUS || $book_response['status'] == true) {
				$response['status'] = SUCCESS_STATUS;
				$response['message'] = 'Booking Successfull';
				$response['data'] = $book_response['data'];
				
				// Send booking confirmation email
				$app_reference = $book_response['data']['app_reference'] ?? null;
				if ($app_reference) {
					$this->user_model->track_affiliate_booking_by_header($app_reference, META_AIRLINE_COURSE);
					$this->load->library('Loyalty/loyalty');
					$points = $this->loyalty->get_loyalty_points($app_reference, META_AIRLINE_COURSE);

					if ($points) {
						$response['data']['points'] = $points;
					}
					$this->send_booking_confirmation_email($app_reference);
				}
			} else {
				$response['status'] = FAILURE_STATUS;
				$response['message'] = $book_response['message'] ?? 'Failed to process booking';
				if (!empty($book_response['data']) && is_array($book_response['data'])) {
					$response['data'] = $book_response['data'];
				}
			}
		} else {
			$response['message'] = 'ResultToken is required';
		}

		return $response;
	}

	/**
	 * GetBookingDetails - Returns full booking details from database
	 */
	private function GetBookingDetails($request)
	{
		$response['status'] = FAILURE_STATUS;
		$response['message'] = '';
		$response['data'] = array();

		$app_reference = trim($request['app_reference'] ?? '');

		if (empty($app_reference)) {
			$response['message'] = 'App reference is required';
			return $response;
		}
		$booking_data = $this->flight_model->get_booking_data_for_guest($app_reference);

		if ($booking_data) {
			$response['status'] = SUCCESS_STATUS;
			$response['message'] = 'Booking details retrieved successfully';
			$response['data']['BookingDetails'] = $booking_data;
		} else {
			$response['status'] = FAILURE_STATUS;
			$response['message'] = 'Booking not found or invalid app reference';
		}

		return $response;
	}

	/*Validate Search Request*/
	function validate_search_request($request)
	{
		$success_status = true;
		$message = array();
		$data = array();
		$trip_array = array('OneWay', 'Return', 'Multicity');
		$cabin_class = $request['CabinClass'];
		$cabin_array = array("Economy", "PremiumEconomy", "Business", "PremiumBusines", "First");
		//AdultCount
		if ($success_status == true) {
			if (isset($request['AdultCount']) == true) {
				if (is_numeric($request['AdultCount']) == false) {
					$success_status = false;
					$message = 'AdultCount must be a Integer';
				} else {
					if ($request['AdultCount'] <= 0) {
						$success_status = false;
						$message = 'AdultCount Should be greater than zero';
					}
				}
			} else {
				$success_status = false;
				$message = 'AdultCount is Required';
			}
		}
		if ($success_status == true) {
			if (isset($request['ChildCount']) == true) {
				if (is_numeric($request['ChildCount']) == false) {
					$success_status = false;
					$message = 'ChildCount must be a Integer';
				}
			} else {
				$success_status = false;
				$message = 'ChildCount is Required';
			}
		}
		if ($success_status == true) {
			if (isset($request['InfantCount']) == true) {
				if (is_numeric($request['InfantCount']) == false) {
					$success_status = false;
					$message = 'InfantCount must be a Integer';
				}
			} else {
				$success_status = false;
				$message = 'InfantCount is Required';
			}
		}
		if ($success_status == true) {
			if (isset($request['JourneyType']) == true) {
				if (is_string($request['JourneyType']) == false) {
					$success_status = false;
					$message = 'JourneyType must be a String';
				} else {
					if (!in_array($request['JourneyType'], $trip_array)) {
						$success_status = false;
						$message = 'JourneyType is invalid, it must be(OneWay,Return,Multicity)';
					}
				}
			} else {
				$success_status = false;
				$message = 'JourneyType is Required';
			}
		}

		if ($success_status == true) {
			if (isset($request['CabinClass']) == true) {
				if (is_string($request['CabinClass']) == false) {
					$success_status = false;
					$message = 'CabinClass must be a String';
				} else {
					if (!in_array($cabin_class, $cabin_array)) {
						$success_status = false;
						$message = 'CabinClass is invalid, it must be(Economy,PremiumEconomy,Business,PremiumBusines,First)';
					}
				}
			} else {
				$success_status = false;
				$message = 'CabinClass is Required';
			}
		}

		if ($success_status == true) {
			if (valid_array($request['Segments']) == true) {
				foreach ($request['Segments'] as $s_key => $segment) {
					//debug($request['Segments']);exit;
					if ($success_status == true) {
						if ((isset($segment['Origin']) == true) && (isset($segment['Destination']) == true)) {
							if ($segment['Origin'] == $segment['Destination']) {
								$success_status = false;
								$message = 'Origin and Destination Should be different';
							}
						} else {
							$success_status = false;
							$message = 'Origin & Destination is Required';
						}
					}
					if ($success_status == true) {
						if (isset($segment['DepartureDate']) == true) {
							$dept_date = explode('T', $segment['DepartureDate']);
							if (isset($dept_date[0])) {
								$dept_date1 = date("Y-m-d", strtotime($dept_date[0]));
								$current_date = date("Y-m-d");
								if (strtotime($dept_date1) < strtotime($current_date)) {
									$success_status = false;
									$message = "Departure Date is greater than are equal to Today's Date";
								}
							}
						} else {
							$success_status = false;
							$message = 'DepartureDate is Required';
						}
					}
					if ($success_status == true) {
						if ($request['JourneyType'] == 'Return') {
							if (isset($segment['ReturnDate']) == true) {
								$return_date = explode('T', $segment['ReturnDate']);
								if (isset($dept_date[0])) {
									$return_date1 = date("Y-m-d", strtotime($return_date[0]));
									$current_date = date("Y-m-d");
									if (strtotime($return_date1) < strtotime($current_date)) {
										$success_status = false;
										$message = "ReturnDate Date is greater than are equal to Today's Date";
									} else {
										if (strtotime($return_date1) < strtotime($dept_date1)) {
											$success_status = false;
											$message = "ReturnDate Date is greater than are equal to Departure Date";
										}
									}
								}
							} else {
								$success_status = false;
								$message = 'ReturnDate is Required';
							}
						}
					}
				}
				// debug($request['Segments']);exit;
			} else {
				$success_status = false;
				$message = 'Segments is Required';
			}
		}
		if ($success_status == true) {
			$success_status = SUCCESS_STATUS;
		} else {
			$success_status = FAILURE_STATUS;
		}
		$data['status'] = $success_status;
		$data['message'] = $message;
	
		return $data;
	}

	private function validate_booking_params(&$request)
	{
		$success_status = true;
		$message = null;
		$data = array();

		// Passenger Data Validation
		if (isset($request['Passengers']) && is_array($request['Passengers']) && !empty($request['Passengers'])) {
			foreach ($request['Passengers'] as $index => &$passenger) {
				$required_fields = [
					'FirstName',
					'LastName',
					'PaxType',
					'Gender',
					'ContactNo',
					'PhoneCountryCode',
					'Email'
				];

				foreach ($required_fields as $field) {
					if (!isset($passenger[$field]) || trim($passenger[$field]) === '') {
						$success_status = false;
						$message = "Missing or empty field '$field' for passenger" . ($index + 1) . ".";
						break 2; // break out of both loops
					}
				}

				// Additional field-specific validations
				if (!filter_var($passenger['Email'], FILTER_VALIDATE_EMAIL)) {
					$success_status = false;
					$message = "Invalid email format for passenger " . ($index + 1) . ".";
					break;
				}

				if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $passenger['DateOfBirth'])) {
					$success_status = false;
					$message = "Invalid DateOfBirth format (YYYY-MM-DD) for passenger " . ($index + 1) . ".";
					break;
				}

				if (isset($passenger['PassportExpiry']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $passenger['PassportExpiry'])) {
					$success_status = false;
					$message = "Invalid PassportExpiry format (YYYY-MM-DD) for passenger " . ($index + 1) . ".";
					break;
				}

				if (!in_array($passenger['Gender'], ['Male', 'Female'])) {
					$success_status = false;
					$message = "Invalid Gender for passenger " . ($index + 1) . ".";
					break;
				}

				if (!in_array($passenger['PaxType'], ['Adult', 'Child', 'Infant'])) {
					$success_status = false;
					$message = "Invalid PaxType for passenger " . ($index + 1) . ".";
					break;
				}

				if (!preg_match('/^\d{2,5}$/', $passenger['PhoneCountryCode'])) {
					$success_status = false;
					$message = "Invalid phone country code for passenger " . ($index + 1) . ".";
					break;
				}

				if (!preg_match('/^\d+$/', $passenger['ContactNo'])) {
					$success_status = false;
					$message = "Invalid contact number for passenger " . ($index + 1) . ".";
					break;
				}

				if (isset($passenger['PinCode']) && !preg_match('/^\d{6}$/', $passenger['PinCode'])) {
					$success_status = false;
					$message = "Invalid PinCode (should be 6 digits) for passenger " . ($index + 1) . ".";
					break;
				}

				// Define default values for optional/static fields
				$default_fields = [
					'IsLeadPax' => '0',
					'Title' => ['Male' => 'Mr', 'Female' => 'Ms'][$passenger['Gender']],
					'DateOfBirth' => date('Y-m-d', strtotime('-' . (['Adult' => 18, 'Child' => 6, 'Infant' => 1][$passenger['PaxType']] ?? 18) . ' years')),
					'PassportNumber' => 'K8745302',
					'PassportExpiry' => date('Y-m-d', strtotime('+2 years')),
					'PassportIssueCountry' => 'IN',
					'Nationality' => 'IN',
					'CountryName' => 'India',
					'City' => 'Delhi',
					'PinCode' => '120000',
					'AddressLine1' => 'Not Provided',
					'AddressLine2' => 'Not Provided',
					"FFAirlineCode" => null,
					"FFNumber" => "",
				];

				// Add default values for missing optional fields
				foreach ($default_fields as $key => $value) {
					if (!isset($passenger[$key]) || trim($passenger[$key]) === '') {
						$passenger[$key] = $value;
					}
				}
								// -------------------- SeatDetails VALIDATION --------------------
				if (isset($passenger['SeatDetails']) && !empty($passenger['SeatDetails'])) {

					if (!is_array($passenger['SeatDetails'])) {
						$success_status = false;
						$message = "SeatDetails must be an array for passenger " . ($index + 1) . ".";
						break;
					}

					foreach ($passenger['SeatDetails'] as $sectorIndex => $sectorLegs) {

						if (!is_array($sectorLegs) || empty($sectorLegs)) {
							$success_status = false;
							$message = "SeatDetails[$sectorIndex] must be an array of legs for passenger " . ($index + 1) . ".";
							break 2;
						}

						foreach ($sectorLegs as $legIndex => $leg) {

							$required_leg_fields = ['Origin', 'Destination', 'SeatId'];

							foreach ($required_leg_fields as $field) {
								if (!isset($leg[$field]) || trim($leg[$field]) === '') {
									$success_status = false;
									$message = "Missing or empty '$field' in SeatDetails[$sectorIndex][$legIndex] for passenger " . ($index + 1) . ".";
									break 3;
								}
							}

							// Validate Airport Codes
							if (!preg_match('/^[A-Z]{3}$/', $leg['Origin'])) {
								$success_status = false;
								$message = "Invalid Origin airport code in SeatDetails[$sectorIndex][$legIndex] for passenger " . ($index + 1) . ".";
								break 2;
							}

							if (!preg_match('/^[A-Z]{3}$/', $leg['Destination'])) {
								$success_status = false;
								$message = "Invalid Destination airport code in SeatDetails[$sectorIndex][$legIndex] for passenger " . ($index + 1) . ".";
								break 2;
							}

							// Validate SeatId & SeatKey format (NN-A)
							if (!preg_match('/^\d{1,2}-[A-Z]$/', $leg['SeatId'])) {
								$success_status = false;
								$message = "Invalid SeatId format in SeatDetails[$sectorIndex][$legIndex] for passenger " . ($index + 1) . ". Expected format: 17-A";
								break 2;
							}

							/*if (!preg_match('/^\d{1,2}-[A-Z]$/', $leg['SeatKey'])) {
								$success_status = false;
								$message = "Invalid SeatKey format in SeatDetails[$sectorIndex][$legIndex] for passenger " . ($index + 1) . ". Expected format: 17-A";
								break 2;
							}*/
						}
					}
				}
				// -------------------- End SeatDetails VALIDATION --------------------	

			}
		} else {
			$success_status = false;
			$message = 'Passengers information is required and must be a non-empty array.';
		}

		// Insurance selection validation (optional, only when user opted-in).
		if ($success_status === true && isset($request['InsuranceSelection']) && is_array($request['InsuranceSelection'])) {
			$ins = $request['InsuranceSelection'];
			$buy_insurance = filter_var($ins['buy_insurance'] ?? false, FILTER_VALIDATE_BOOLEAN);
			if ($buy_insurance) {
				if (!is_active_insurance_module()) {
					$success_status = false;
					$message = 'Insurance service is currently inactive for this domain.';
				} elseif (empty($ins['plan_token']) || !is_string($ins['plan_token'])) {
					$success_status = false;
					$message = 'Insurance plan token is required.';
				} else {
					$terms_accepted = filter_var($ins['consent']['terms_accepted'] ?? false, FILTER_VALIDATE_BOOLEAN);
					if ($terms_accepted !== true) {
						$success_status = false;
						$message = 'Please accept insurance terms and conditions.';
					}
				}
			}
		}

		$data['status'] = $success_status ? SUCCESS_STATUS : FAILURE_STATUS;
		$data['message'] = $message;
		return $data;
	}
	public function ExtraServices($request)
	{
		$data['status'] = FAILURE_STATUS;
		$data['message'] = 'Invalid Seat Request';
		$data['data'] = array();
		$ResultToken = trim($request['ResultToken']);

		if ($ResultToken) {
			$extra_services_response = $this->flight_blender->get_extra_services($request);

			$data = $extra_services_response;
		} else {
			$data['message'] = 'Invalid Seat Request';
		} 
		return $data;
	}

	/**
	 * Airport cab search for flight checkout (Mozio search; no cab booking created).
	 */
	private function SearchAirportCabs($request)
	{
		$response = array(
			'status' => FAILURE_STATUS,
			'message' => 'Invalid request',
			'data' => array('cars' => array()),
		);

		if (!is_active_flight_airport_cab_module()) {
			$response['message'] = 'Airport cabs are not enabled for this domain.';
			return $response;
		}

		$result = $this->flight_airport_cab_model->search_cars($request);
		if (!empty($result['status'])) {
			$response['status'] = SUCCESS_STATUS;
			$response['message'] = $result['message'] ?? 'Success';
			$response['data'] = $result['data'] ?? array('cars' => array());
		} else {
			$response['message'] = $result['message'] ?? 'Search failed';
			$response['data'] = $result['data'] ?? array('cars' => array());
		}

		return $response;
	}

	/**
	 * Addons - Returns Insurance/Protocol/Lounge addon options.
	 * Designed to be parallel to ExtraServices but focused on non-SSR extras.
	 */
	private function Addons($request)
	{
		$response = array(
			'status' => FAILURE_STATUS,
			'message' => 'Invalid request',
			'data' => array(),
		);

		$raw_token = $request['ResultToken'] ?? '';
		$ResultToken = trim(is_scalar($raw_token) ? (string)$raw_token : '');
		if ($ResultToken === '') {
			$response['message'] = 'ResultToken is required';
			return $response;
		}

		$addons = $this->flight_blender->get_addons($request);
		if (!empty($addons['status']) && $addons['status'] === SUCCESS_STATUS) {
			return $addons;
		}

		return $addons;
	}

	/**
	 * Send booking confirmation email
	 */
	private function send_booking_confirmation_email($app_reference)
	{
		try {
			$this->load->library('provab_mailer');
			$this->load->model('flight_model');
			
			$booking_data = $this->flight_model->get_booking_data_for_guest($app_reference);
			 
			if ($booking_data && !empty($booking_data['BookingInfo']['email'])) {
				$email = $booking_data['BookingInfo']['email'];
				$user_email = $booking_data['BookingInfo']['login_user_email'];
				
				// Flatten FlightDetails for email template
				$itinerary_flat = [];
				if (!empty($booking_data['FlightDetails']) && is_array($booking_data['FlightDetails'])) {
					foreach ($booking_data['FlightDetails'] as $journey) {
						if (is_array($journey)) {
							foreach ($journey as $segment) {
								if (isset($segment['Origin']) && isset($segment['Destination'])) {
									$itinerary_flat[] = [
										'airline_name' => $segment['OperatorName'] ?? '',
										'flight_number' => $segment['FlightNumber'] ?? '',
										'from_airport_code' => $segment['Origin']['AirportCode'] ?? '',
										'from_airport_city' => $segment['Origin']['CityName'] ?? '',
										'from_airport_name' => $segment['Origin']['AirportName'] ?? '',
										'from_airport_terminal' => $segment['Origin']['OriginTerminal'] ?? '',
										'to_airport_code' => $segment['Destination']['AirportCode'] ?? '',
										'to_airport_city' => $segment['Destination']['CityName'] ?? '',
										'to_airport_name' => $segment['Destination']['AirportName'] ?? '',
										'to_airport_terminal' => $segment['Destination']['DestinationTerminal'] ?? '',
										'departure_datetime' => $segment['Origin']['DateTime'] ?? '',
										'arrival_datetime' => $segment['Destination']['DateTime'] ?? '',
										'cabin_baggage' => null,
										'checked_in_baggage' => null,
									];
								}
							}
						}
					}
				}
				
				// Format passengers for email template
				$formatted_passengers = [];
				if (!empty($booking_data['PassengerDetails']) && is_array($booking_data['PassengerDetails'])) {
					foreach ($booking_data['PassengerDetails'] as $passenger) {
						$formatted_passengers[] = [
							'title' => $passenger['title'] ?? '',
							'first_name' => $passenger['first_name'] ?? '',
							'last_name' => $passenger['last_name'] ?? '',
							'pax_type' => $passenger['passenger_type'] ?? '',
							'ticket_number' => $passenger['TicketNumber'] ?? '',
						];
					}
				}
				
				// Format data for email template
				$mail_data = [
					'booking' => [
						'app_reference' => $booking_data['app_reference'] ?? '',
						'status' => $booking_data['status'] ?? '',
						'gdspnr' => $booking_data['gdspnr'] ?? '',
						'email' => $email,
					],
					'itinerary' => $itinerary_flat,
					'passengers' => $formatted_passengers,
					'transaction' => $booking_data['Price'],
				];
				
				$mail_template_data = [
					'page_data' => $mail_data,
					'email_template' => 'flight_booking_success'
				];
				
				$mail_template = $this->template->isolated_view('email_templates/main', $mail_template_data);
				$subject = domain_name() . ' - Flight Booking Confirmed';

				// One email per unique address (booking email + login email when different).
				$recipients = array();
				$booking_email = trim((string)$email);
				if ($booking_email !== '' && filter_var($booking_email, FILTER_VALIDATE_EMAIL)) {
					$recipients[strtolower($booking_email)] = $booking_email;
				}
				$account_email = trim((string)($user_email ?? ''));
				if ($account_email !== '' && filter_var($account_email, FILTER_VALIDATE_EMAIL)) {
					$key = strtolower($account_email);
					if (!isset($recipients[$key])) {
						$recipients[$key] = $account_email;
					}
				}
				foreach ($recipients as $recipient) {
					$this->provab_mailer->send_mail($recipient, $subject, $mail_template);
				}
			}
		} catch (Exception $e) {
			// Log error but don't break the booking flow
			log_message('error', 'Failed to send flight booking confirmation email: ' . $e->getMessage());
		}
	}

	/**
	 * Send cancellation confirmation email
	 */
	private function send_cancellation_email($app_reference, $remark)
	{
		try {
			$this->load->library('provab_mailer');
			$this->load->model('flight_model');
			
			$booking_data = $this->flight_model->get_booking_data_for_guest($app_reference);
			
			if ($booking_data && !empty($booking_data['BookingInfo']['email'])) {
				$email = $booking_data['BookingInfo']['email'];
				
				// Format passengers for email template
				$formatted_passengers = [];
				if (!empty($booking_data['PassengerDetails']) && is_array($booking_data['PassengerDetails'])) {
					foreach ($booking_data['PassengerDetails'] as $passenger) {
						$formatted_passengers[] = [
							'title' => $passenger['title'] ?? '',
							'first_name' => $passenger['first_name'] ?? '',
							'last_name' => $passenger['last_name'] ?? '',
							'pax_type' => $passenger['passenger_type'] ?? '',
							'ticket_number' => $passenger['TicketNumber'] ?? '',
						];
					}
				}

				$mail_data = [
					'booking' => [
						'app_reference' => $booking_data['app_reference'] ?? '',
						'status' => $booking_data['status'] ?? '',
						'email' => $email,
					],
					'passengers' => $formatted_passengers,
					'remark' => $remark,
				];
				
				$mail_template_data = [
					'page_data' => $mail_data,
					'email_template' => 'flight_cancellation'
				];
				
				$mail_template = $this->template->isolated_view('email_templates/main', $mail_template_data);
				$subject = domain_name() . ' - Flight Cancellation Request Received';
				$this->provab_mailer->send_mail($email, $subject, $mail_template);
			}
		} catch (Exception $e) {
			// Log error but don't break the cancellation flow
			log_message('error', 'Failed to send flight cancellation email: ' . $e->getMessage());
		}
	}

	function seed_facts_supplier_map()
    {

		
        $this->load->library('facts_tally/facts');     
        // 1. Fetch booking sources with required fields
       $booking_sources = $this->db
        ->select('booking_source.origin, booking_source.name, api_config.currency')
        ->from('booking_source')
        ->join('api_config', 'api_config.booking_source_fk = booking_source.origin', 'left') // use 'inner' if you want only matching records
        ->get();
       

        if (empty($booking_sources->result_array())) {
            return;
        }

        $booking_sources = $booking_sources->result_array();
        // Index booking sources by origin for easy access
        $booking_sources_by_id = [];
        foreach ($booking_sources as $source) {
            $booking_sources_by_id[$source['origin']] = $source;
        }

        $booking_source_ids = array_keys($booking_sources_by_id);

        // 2. Get already mapped booking_source IDs
        $existing_maps = $this->db
            ->select('booking_source_id')
            ->where_in('booking_source_id', $booking_source_ids)
            ->get('facts_supplier_map')
            ->result_array();
        $existing_booking_source_ids = array_column($existing_maps, 'booking_source_id');

        // 3. Find missing booking_source IDs
        $missing_booking_source_ids = array_diff(
            $booking_source_ids,
            $existing_booking_source_ids
        );


        if (empty($missing_booking_source_ids)) {
            return;
        }

        $insert_data = [];

        // 4. Use DB transaction for consistency

        foreach ($missing_booking_source_ids as $booking_source_id) {

            $source = $booking_sources_by_id[$booking_source_id];

            // Generate unique, deterministic email
            $email = 'supplier'.$booking_source_id. '@gmail.com';  
            $contact_person_no = str_replace(' ', '', $this->entity_domain_phone);

            $supplier_data = [
                'phone_code' => $this->entity_domain_phone_code,
                'first_name' => str_replace('-', '', $source['name']),
                'email'      => $email,
                'currency'  => $source['currency'],
                'phone'      => $contact_person_no,
            ];
            // 4a. Call FACTS API
            $supplier_info = $this->facts->create_user_account(
                $supplier_data, "supplier"               
            );

            // Validate API response
            if (isset($supplier_info['status'], $supplier_info['UserId']) && $supplier_info['status'] === true) {
                $insert_data[] = [
                    'booking_source_id' => $booking_source_id,
                    'supplier_id'       => $supplier_info['UserId'],
                    'supplier_currency' => $source['currency'],
                    'created_at'  => date('Y-m-d H:i:s'),
                ];
            } 
        }
    
        if (!empty($insert_data)) {
            $this->db->insert_batch('facts_supplier_map', $insert_data);
        }else{
            $get_supplier = $this->facts->get_supplier();
          
            if($get_supplier['status'] == true){
                // $uniqueSuppliers = [];
				foreach ($get_supplier['data'] as $supplier) {

				    $key = $this->normalizeName($supplier['account_name']);

				    if (!isset($uniqueSuppliers[$key])) {
				        $uniqueSuppliers[$key] = $supplier;
				    }
				}

				 
            }
        }

    }

	function normalizeName($value)
	{
		$value = strtolower(trim($value));
		$value = str_replace('-', ' ', $value);
		$value = preg_replace('/\s+/', '', $value);
		$value = preg_replace('/[^a-z0-9]/', '', $value);
		return $value;
	}

	/**
	 * Validate Promocode
	 * @param array $request - Contains: promocode, module, booking_amount, currency
	 * @return array
	 */
	private function ValidatePromocode($request)
	{
		$response = [
			'status' => FAILURE_STATUS,
			'message' => $this->translation->translateText('Server Error'),
			'data' => null
		];

		try {
			// Validate required parameters
			if (empty($request['promocode'])) {
				$response['message'] = $this->translation->translateText('Please Enter Promo Code');
				return $response;
			}

			if (empty($request['booking_amount']) || floatval($request['booking_amount']) <= 0) {
				$response['message'] = $this->translation->translateText('Invalid booking amount');
				return $response;
			}

			$promocode = trim($request['promocode']);
			$booking_amount = floatval($request['booking_amount']);
			$currency = $request['currency'] ?? null;

			// Use flight-specific promocode validation
			$validation_result = $this->yiron_promocode->validateFlightPromocode($promocode, $booking_amount, $currency);

			if ($validation_result['status'] == true) {
				// Get the promocode details from database for normalization
				$condition = [
					'promo_code' => $promocode,
					'status' => 1
				];
				$promo_code_data = $this->custom_db->single_table_records('promo_code_list', '*', $condition);

				if ($promo_code_data['status'] == 1 && !empty($promo_code_data['data'])) {
					$promo_code_info = $promo_code_data['data'][0];

					// Normalize response similar to frontend structure
					$normalized_data = [
						'code' => $promo_code_info['promo_code'] ?? $promocode,
						'description' => $promo_code_info['description'] ?? '',
						'value' => floatval($promo_code_info['value'] ?? 0),
						'value_type' => $promo_code_info['value_type'] ?? 'plus',
						'minimum_amount' => floatval($promo_code_info['minimum_amount'] ?? 0),
						'maximum_amount' => floatval($promo_code_info['maximum_amount'] ?? 0),
						'start_date' => $promo_code_info['start_date'] ?? '',
						'discount' => floatval($validation_result['data']['discount'] ?? 0),
						'discount_actual' => floatval($validation_result['data']['discount_actual'] ?? 0),
						'update_price' => floatval($validation_result['data']['update_price'] ?? $booking_amount),
					];

					$response['status'] = SUCCESS_STATUS;
					$response['message'] = $this->translation->translateText('Promocode applied successfully');
					$response['data'] = $normalized_data;
				} else {
					$response['message'] = $this->translation->translateText('Invalid Promo Code');
				}
			} else {
				$response['message'] = $validation_result['message'] ?? $this->translation->translateText('Invalid Promo Code');
			}
		} catch (Exception $e) {
			$response['message'] = $this->translation->translateText('Server Error: ') . $e->getMessage();
		}

		return $response;
	}

	private function InsuranceCreateClaim($request)
	{
		$result = $this->insurance_model->create_insurance_claim($request);
		return $this->format_insurance_service_response($result);
	}

	private function InsuranceUploadClaimDocument($request)
	{
		$result = $this->insurance_model->upload_insurance_claim_document($request);
		return $this->format_insurance_service_response($result);
	}

	private function InsuranceCancelPolicy($request)
	{
		$result = $this->insurance_model->cancel_insurance_policy($request);
		return $this->format_insurance_service_response($result);
	}

	private function InsuranceClaimStatus($request)
	{
		$result = $this->insurance_model->get_insurance_claim_status($request);
		return $this->format_insurance_service_response($result);
	}

	private function format_insurance_service_response($result)
	{
		$response = array(
			'status' => !empty($result['status']) ? SUCCESS_STATUS : FAILURE_STATUS,
			'message' => $result['message'] ?? '',
			'data' => $result['data'] ?? array(),
		);
		return $response;
	}
}
////////////////////////////////////////////////////////////////////////////////////////////////////
flight_model.php
<?php

/**
 * Library which has generic functions to get data
 *
 * @package    Provab Application
 * @subpackage Flight Model
 * @author     Arjun J<arjunjgowda260389@gmail.com>
 * @version    V2
 */

class Flight_Model extends CI_Model
{


	/** 
	 * get search data and validate it
	 */
	function get_safe_search_data($search_id)
	{
		$search_data_condition = array();
		$search_data_condition[] = array('origin', '=', intval($search_id));
		$search_data = $this->get_search_data($search_data_condition);

		$success = true;
		$clean_search = array();
		if ($search_data != false) {
			$search_data['JourneyType'] = strtolower($search_data['JourneyType']);
			//validate
			$clean_search['trip_type'] = $search_data['JourneyType'];
			$clean_search['Sources'] = @$search_data['Sources'];
			$clean_search['carrier'] = $search_data['PreferredAirlines'];
			$clean_search['cabin_class'] = $search_data['CabinClass'];
			$clean_search['adult_config'] = $search_data['AdultCount'];
			$clean_search['child_config'] = $search_data['ChildCount'];
			$clean_search['infant_config'] = $search_data['InfantCount'];
			$clean_search['is_domestic'] = $search_data['IsDomestic'];
			$clean_search['total_pax'] = intval($clean_search['adult_config']) + intval($clean_search['child_config']) + intval($clean_search['infant_config']);

			if ($clean_search['trip_type'] == 'multicity') {
				$Segments = $search_data['Segments'];
				$clean_search['from'] = array_column($Segments, 'Origin');
				$clean_search['to'] = array_column($Segments, 'Destination');
				$clean_search['from_country'] = array_column($Segments, 'Origin_Country');
				$clean_search['to_country'] = array_column($Segments, 'Dest_Country');
				$clean_search['depature'] = array_column($Segments, 'DepartureDate');
			} else {
				$Segments = $search_data['Segments'][0];
				$clean_search['from'] = $Segments['Origin'];
				$clean_search['to'] = $Segments['Destination'];
				$clean_search['from_country'] = $Segments['Origin_Country'];
				$clean_search['to_country'] = $Segments['Dest_Country'];
				$clean_search['depature'] = $Segments['DepartureDate'];
				if ($clean_search['trip_type'] == 'return') {
					$clean_search['return'] = $Segments['ReturnDate'];
				}
			}
		} else {
			$success = false;
		}

		return array('status' => $success, 'data' => $clean_search);
	}
	/**
	 * Save Seaech Data
	 * Enter description here ...
	 * @param array $request
	 */
	function save_search_data($request)
	{
		$data['status'] = SUCCESS_STATUS;
		$origin = '';
		$destination = '';
		$journeyDate = null;
		foreach ($request['Segments'] as $s_key => $segment) {
			if ($origin == '') {
				$origin = $segment['Origin'];
			}
			if ($journeyDate == null) {
				$journeyDate = $segment['DepartureDate'];
			}
			$destination = $segment["Destination"];
			$org_airport_data = $this->get_airport_city_name($segment['Origin']);
			$dest_airport_data = $this->get_airport_city_name($segment['Destination']);
			$request['Segments'][$s_key]['Origin_Country'] = $org_airport_data->country;
			$request['Segments'][$s_key]['Dest_Country'] = $dest_airport_data->country;
		}
		//Checking is domest flight
		$from_loc = array_column($request['Segments'], 'Origin');
		$to_loc = array_column($request['Segments'], 'Destination');
		$is_domestic = $this->is_domestic_flight($from_loc, $to_loc);
		$request['IsDomestic'] = $is_domestic;		
		$search_history_data = array();
		$search_history_data['domain_origin'] = get_domain_auth_id();
		$search_history_data['cache_key'] = "000";
		$search_history_data['search_type'] = META_AIRLINE_COURSE;
		$search_history_data['search_data'] = json_encode($request);
		$search_history_data['created_datetime'] = db_current_datetime();
		$total_pax = $request['AdultCount'] + $request['ChildCount'] + $request['InfantCount'];
		$flight_search_history_data = [
			'domain_origin' => $search_history_data['domain_origin'],
			'search_type'   => META_AIRLINE_COURSE,
			'from_location' => $origin,
			'to_location'   => $destination,
			'from_code'     => $origin,
			'to_code'       => $destination,
			'trip_type'     => '',
			'journey_date'  => '',
			'total_pax'     => $total_pax,
			'created_by_id' => $this->entity_user_id ?? 0,
			'creation_source'  => $this->entity_user_type == B2B_USER ? 'agent' : 'b2c',
			'created_datetime' => date('Y-m-d H:i:s')
		];
		$this->custom_db->insert_record('search_history_flight', $flight_search_history_data);
		$insert_data = $this->custom_db->insert_record('webservices_search_history', $search_history_data);

		// $insert_data = $this->custom_db->insert
		if ($insert_data['status'] == QUERY_SUCCESS) {
			$data['search_id'] = $insert_data['insert_id'];
		} else {
			$data['status'] = FAILURE_STATUS;
		}
		return $data;
	}


	/**
	 * get search data without doing any validation
	 * @param $search_id
	 */
	function get_search_data($condition = array(), $count = false, $offset = 0, $limit = 100000000000)
	{
		$condition = $this->custom_db->get_custom_condition($condition);
		$query = 'select SH.* from webservices_search_history SH where search_type="' . META_AIRLINE_COURSE . '"' . $condition;
		$search_data = $this->db->query($query)->row_array();
		if (valid_array($search_data) == true) {
			return json_decode($search_data['search_data'], true);
		} else {
			return false;
		}
	}

	function is_domestic_flight($from_loc, $to_loc)
	{
		if (valid_array($from_loc) == true || valid_array($to_loc)) { //Multicity
			$airport_cities = array_merge($from_loc, $to_loc);
			$airport_cities = array_unique($airport_cities);
			$airport_city_codes = NULL;
			foreach ($airport_cities as $k => $v) {
				$airport_city_codes .= '"' . $v . '",';
			}
			$airport_city_codes = rtrim($airport_city_codes, ',');
			$query = 'SELECT count(*) total FROM flight_airport_list WHERE airport_code IN (' . $airport_city_codes . ') AND country != "India"';
		} else { //Oneway/RoundWay
			$query = 'SELECT count(*) total FROM flight_airport_list WHERE airport_code IN (' . $this->db->escape($from_loc) . ',' . $this->db->escape($to_loc) . ') AND country != "India"';
		}
		$data = $this->db->query($query)->row_array();
		if (intval($data['total']) > 0) {
			return false;
		} else {
			return true;
		}
	}

	public function get_airport_city_name($airport_code)
	{
		$query1 = 'select* from flight_airport_list WHERE airport_code="' . $airport_code . '"';
		$data = $this->db->query($query1)->row();
		return $data;
	}

	/**
	 * Build base bookings query for both listing and counting.
	 * Applies joins and filters. Caller controls select/order/limit via $for_count.
	 */
	private function build_bookings_query($opts = [], $for_count = false)
	{


		if ($for_count) {
			$this->db->select('COUNT(DISTINCT fbd.app_reference) AS cnt', false);
		} else {
			$this->db->select("
            fbd.app_reference AS reference,
			fbd.from_airport_code AS journey_from, 
			fbd.to_airport_code AS journey_to,
            CONCAT(fbd.from_airport_code, ' - ', fbd.to_airport_code) AS route,
            MIN(fid.airline_name) AS airline,
            MIN(fid.flight_number) AS flight_number,
            MAX(CASE WHEN fpd.is_lead = 1 THEN CONCAT(fpd.first_name, ' ', fpd.last_name) ELSE NULL END) AS lead_passenger,
            MIN(fbd.email) AS email,
            CONCAT(IFNULL(fbd.phone_code, ''), IF(fbd.phone_code IS NOT NULL AND fbd.phone_number IS NOT NULL, '-', ''), IFNULL(fbd.phone_number, '')) AS phone,
            MIN(fbd.cabin_class) AS cabin_class,
            SUM(CASE WHEN fpd.passenger_type = 'Adult' THEN 1 ELSE 0 END) AS adult_count,
            SUM(CASE WHEN fpd.passenger_type = 'Child' THEN 1 ELSE 0 END) AS child_count,
            SUM(CASE WHEN fpd.passenger_type = 'Infant' THEN 1 ELSE 0 END) AS infant_count,
            (MIN(ftd.total_fare - ftd.admin_discount) + IFNULL(fsd.total_seat_price, 0)) AS total_fare,
            MIN(fbd.created_datetime) AS booked_on,
            MIN(fbd.status) AS status
            ", false);
		}

		$this->db->from('flight_booking_details fbd');
		$this->db->join('flight_booking_transaction_details ftd', 'fbd.id = ftd.flight_booking_details_id', 'left');
		$this->db->join('flight_booking_passenger_details fpd', 'fbd.id = fpd.flight_booking_details_id', 'left');
		$this->db->join('flight_booking_itinerary_details fid', 'fbd.id = fid.flight_booking_details_id', 'left');
		$this->db->join( '(SELECT flight_booking_details_id, SUM(price) AS total_seat_price FROM flight_seat_details GROUP BY flight_booking_details_id) fsd', 'fbd.id = fsd.flight_booking_details_id', 'left', false );

		// Filters
		if (!empty($opts['start_date'])) {
			$this->db->where('fbd.created_datetime >=', $opts['start_date'] . ' 00:00:00');
		}
		if (!empty($opts['end_date'])) {
			$this->db->where('fbd.created_datetime <=', $opts['end_date'] . ' 23:59:59');
		}
		if (!empty($opts['status'])) {
			$this->db->where('fbd.status', $opts['status']);
		}
		if (!empty($opts['email'])) {
			$this->db->like('fbd.email', $opts['email']);
		}
		if (!empty($opts['email'])) {
            $email = strtolower(trim($opts['email']));
            $this->db->where('LOWER(TRIM(fbd.email))', $email);
        }

		
		if (!empty($opts['name'])) {
			// $this->db->group_start();
			// $this->db->like('fpd.first_name', $opts['name']);
			// $this->db->or_like('fpd.last_name', $opts['name']);
			// $this->db->group_end();
			$name = $this->db->escape_like_str($opts['name']);
			$this->db->where("(fbd.phone_number LIKE '%$name%'
				 OR fbd.email LIKE '%{$name}%')", NULL, FALSE);
		}


         // this check used to not pass used condition in guest query
		if (!isset($opts['guest_lookup']) || (int)$opts['guest_lookup'] !== 1) {
			$this->db->where('fbd.created_by_id', $this->entity_user_id ?? -1);
		}

		// $this->db->where('fbd.created_by_id', $this->entity_user_id ?? -1);

		if ($for_count === false) {
			$this->db->group_by('fbd.app_reference');
		}
	}


	// Returns array of booking summary rows
	public function get_bookings($opts = [])
	{
		$limit = isset($opts['limit']) ? (int) $opts['limit'] : 10;
		$offset = isset($opts['offset']) ? (int) $opts['offset'] : 0;
		$sort_by = isset($opts['sort_by']) ? $opts['sort_by'] : 'created_datetime';
		$sort_dir = isset($opts['sort_dir']) && strtolower($opts['sort_dir']) === 'asc' ? 'asc' : 'desc';
		

		// Build shared query and then add list-mode controls
		$this->build_bookings_query($opts, false);
		$this->db->order_by($sort_by, $sort_dir);
		$this->db->limit($limit, $offset);

		$q = $this->db->get();
		// debug($this->ci->last_query());die;

		$rows = [];
		foreach ($q->result_array() as $r) {
			$rows[] = [
				'reference' => $r['reference'],
				'from' => $r['journey_from'],
				'to' => $r['journey_to'],
				'route' => $r['route'],
				'airline' => $r['airline'],
				'flight_number' => $r['flight_number'],
				'passenger_name' => $r['lead_passenger'] ? $r['lead_passenger'] : '',
				'email' => $r['email'],
				'phone' => $r['phone'],
				'cabin_class' => $r['cabin_class'],
				'pax' => [
					'adult' => (int) $r['adult_count'],
					'child' => (int) $r['child_count'],
					'infant' => (int) $r['infant_count']
				],
				'total_fare' => (float) $r['total_fare'],
				'booked_on' => $r['booked_on'],
				'status' => $r['status'],
			];
		} 

		return $rows;
	}

	// count for pagination (apply same filters)
	public function count_bookings($opts = [])
	{
		$this->build_bookings_query($opts, true);
		$q = $this->db->get();
		$row = $q->row_array();
		return isset($row['cnt']) ? (int) $row['cnt'] : 0;
	}

	/**
	 * Get booking data from database for guest display
	 * Returns only guest-required data, excluding internal fields
	 * @param string $app_reference
	 * @return array|null
	 */
	public function get_booking_data_for_guest($app_reference)
	{

		if (empty($app_reference)) {
			return null;
		}

		// Get booking details
		$condition = array();
		$conditn1 = array();
		$condition['app_reference'] = $app_reference;
		$booking_details = $this->custom_db->single_table_records('flight_booking_details', '*', $condition); 
		$createdById = $booking_details['data'][0]['created_by_id']; 
		$conditn1['user_id'] = $createdById; 
		$login_user_email = $this->custom_db->single_table_records('user', '*', $conditn1); 
		$user_dec_email = provab_decrypt($login_user_email['data'][0]['email']);
 

		if (empty($booking_details['data']) || !is_array($booking_details['data']) || count($booking_details['data']) == 0) {
			return null;
		}

		$booking = $booking_details['data'][0];
		$booking_id = $booking['id'];

		// Get itinerary details
		$itinerary_condition = array();
		$itinerary_condition['flight_booking_details_id'] = $booking_id;
		$itinerary_details = $this->custom_db->single_table_records('flight_booking_itinerary_details', '*', $itinerary_condition, 0, 1000, array('journey_indicator' => 'ASC', 'segment_indicator' => 'ASC'));

		// debug($itinerary_details);die;
		// Get passenger details (without passport details)
		$passenger_condition = array();
		$passenger_condition['flight_booking_details_id'] = $booking_id;
		$passenger_details = $this->custom_db->single_table_records('flight_booking_passenger_details', '*', $passenger_condition, 0, 1000, array('id' => 'ASC'));

		$ticket_list = $this->custom_db->single_table_records('flight_booking_ticket_info', '*', array(), 0, 1000, array('id' => 'ASC'));
		// Index tickets by passenger_detail_id
		$ticket_map = array();

		if (!empty($ticket_list['data'])) {
			foreach ($ticket_list['data'] as $t) {
				$ticket_map[$t['flight_booking_passenger_details_id']] = $t;
			}
		}
		// debug($ticket_map);die;
		// Get transaction details
		$transaction_condition = array();
		$transaction_condition['flight_booking_details_id'] = $booking_id;
		$transaction_details = $this->custom_db->single_table_records('flight_booking_transaction_details', '*', $transaction_condition);

		// Get Seat details
		$seat_condition = array();
		$seat_condition['flight_booking_details_id'] = $booking_id;
		$seat_details = $this->custom_db->single_table_records('flight_seat_details', 'id, passenger_id, journey_index, segment_index, origin, destination, seat_id, price', $transaction_condition);

			// debug($seat_details);die;
		$total_seat_price = 0;

		foreach($seat_details['data'] as $row){
			if(!empty($row['price']) && $row['price'] > 0){
				$total_seat_price += floatval($row['price']);
			}
		}
		$seat_details['total_seat_price'] = $total_seat_price;

		$total_meal_price = 0;
		$total_baggage_price = 0;
		$meal_details_list = array();
		$baggage_details_list = array();
		if ($this->db->table_exists('flight_meal_details')) {
			$meal_details = $this->custom_db->single_table_records(
				'flight_meal_details',
				'id, passenger_id, journey_index, segment_index, origin, destination, description, price',
				$seat_condition
			);
			if (!empty($meal_details['data']) && is_array($meal_details['data'])) {
				$meal_details_list = $meal_details['data'];
				foreach ($meal_details_list as $row) {
					if (!empty($row['price']) && $row['price'] > 0) {
						$total_meal_price += floatval($row['price']);
					}
				}
			}
		}
		if ($this->db->table_exists('flight_baggage_details')) {
			$baggage_details = $this->custom_db->single_table_records(
				'flight_baggage_details',
				'id, passenger_id, journey_index, segment_index, origin, destination, description, price',
				$seat_condition
			);
			if (!empty($baggage_details['data']) && is_array($baggage_details['data'])) {
				$baggage_details_list = $baggage_details['data'];
				foreach ($baggage_details_list as $row) {
					if (!empty($row['price']) && $row['price'] > 0) {
						$total_baggage_price += floatval($row['price']);
					}
				}
			}
		}
		// Format booking details (exclude internal fields)
		$formatted_booking = array(
			'app_reference' => $booking['app_reference'],
			'status' => $booking['status'],
			'trip_type' => $booking['trip_type'],
			'from_airport_code' => $booking['from_airport_code'],
			'from_airport_city' => $booking['from_airport_city'],
			'from_airport_name' => $booking['from_airport_name'],
			'to_airport_code' => $booking['to_airport_code'],
			'to_airport_city' => $booking['to_airport_city'],
			'to_airport_name' => $booking['to_airport_name'],
			'start_date' => $booking['start_date'],
			'end_date' => $booking['end_date'],
			'cabin_class' => $booking['cabin_class'],
			'email' => $booking['email'],
			'phone_code' => $booking['phone_code'],
			'phone_number' => $booking['phone_number'],
			'gdspnr' => $booking['gdspnr'] ?? null,
			'created_datetime' => $booking['created_datetime'] ?? null,
			'booking_source' => $booking['booking_source'],
		);

		// Format itinerary details
		$formatted_itinerary = array();
		if (!empty($itinerary_details['data']) && is_array($itinerary_details['data'])) {
			foreach ($itinerary_details['data'] as $itinerary) {
				$formatted_itinerary[] = array(
					'journey_indicator' => $itinerary['journey_indicator'],
					'segment_indicator' => $itinerary['segment_indicator'],
					'airline_code' => $itinerary['airline_code'],
					'airline_name' => $itinerary['airline_name'],
					'flight_number' => $itinerary['flight_number'],
					'from_airport_code' => $itinerary['from_airport_code'],
					'from_airport_city' => $itinerary['from_airport_city'],
					'from_airport_name' => $itinerary['from_airport_name'],
					'from_airport_terminal' => $itinerary['from_airport_terminal'],
					'to_airport_code' => $itinerary['to_airport_code'],
					'to_airport_city' => $itinerary['to_airport_city'],
					'to_airport_name' => $itinerary['to_airport_name'],
					'to_airport_terminal' => $itinerary['to_airport_terminal'],
					'departure_datetime' => $itinerary['departure_datetime'],
					'arrival_datetime' => $itinerary['arrival_datetime'],
					'airpnr' => $itinerary['airpnr'] ?? null,
					'cabin_baggage' => $itinerary['cabin_baggage'] ?? null,
					'checked_in_baggage' => $itinerary['checked_in_baggage'] ?? ($itinerary['checkin_baggage'] ?? null),
					'is_refundable' => $itinerary['is_refundable'] ?? null,
					'duration' =>  $itinerary['duration'],

				);
			}
		}

		// Format passenger details (include required voucher fields)
		$formatted_passengers = array();
		if (!empty($passenger_details['data']) && is_array($passenger_details['data'])) {
			foreach ($passenger_details['data'] as $passenger) {

				$pid = $passenger['id'];
				$ticket_number = isset($ticket_map[$pid])
					? $ticket_map[$pid]['TicketNumber']
					: '';

				$formatted_passengers[] = array(
					'passenger_id' => $passenger['id'],
					'passenger_type' => $passenger['passenger_type'],
					'is_lead' => $passenger['is_lead'] == 1,
					'title' => $passenger['title'],
					'first_name' => $passenger['first_name'],
					'last_name' => $passenger['last_name'],
					'date_of_birth' => $passenger['date_of_birth'],
					'gender' => $passenger['gender'],
					'ff_airline' => $passenger['ff_airline'] ?? null,
					'ff_number' => $passenger['ff_number'] ?? null,
					'cabin_baggage' => $passenger['cabin_baggage'] ?? null,
					'checked_in_baggage' => $passenger['checked_in_baggage'] ?? null,
					'passport_number' => $passenger['passport_number'] ?? '',
					'TicketNumber' => $ticket_number,
					'barcode_url' => $this->generate_bcbp_content($passenger, $formatted_itinerary, $formatted_booking['gdspnr']),
				);
			}
		}

		// Format transaction/pricing details
		$formatted_pricing = null;
		if (!empty($transaction_details['data']) && is_array($transaction_details['data']) && count($transaction_details['data']) > 0) {
			$transaction = $transaction_details['data'][0];
			
			// Calculate total taxes and fees (airline_tax + admin_markup + admin_markup_tax)
			// This includes all markups and taxes as requested
			$total_taxes_and_fees = floatval($transaction['airline_tax'] ?? 0) + 
									floatval($transaction['admin_markup'] ?? 0) + 
									floatval($transaction['admin_markup_tax'] ?? 0) +
									floatval($transaction['agent_markup'] ?? 0) +
									floatval($transaction['agent_markup_tax'] ?? 0);

			$sub_total =  floatval($transaction['basic_fare'] ?? 0) +
			              $total_taxes_and_fees;

						  


			$formatted_pricing = array(
				'basic_fare' => floatval($transaction['basic_fare'] ?? 0),
				'taxes_and_fees' => $total_taxes_and_fees,
				'sub_total' => $sub_total,
				'total_seat_price' => $total_seat_price,
				'total_meal_price' => $total_meal_price,
				'total_baggage_price' => $total_baggage_price,
				'admin_discount' => floatval($transaction['admin_discount'] ?? 0),
				'discount_amount' => floatval($transaction['discount_amount'] ?? 0),
				'loyalty_amount' => floatval(($transaction['loyalty_amount'] ?? 0)),
				'promocode' => $transaction['promocode'] ?? null,
				'convenience_fee_amount' => floatval($transaction['convenience_fee_amount'] ?? ($transaction['convinence_amount'] ?? 0)),
				'currency' => $transaction['currency'] ?? admin_base_currency(),
				'currency_conversion_rate' => floatval($transaction['currency_conversion_rate'] ?? ($transaction['conversion_rate'] ?? 1)),
			);
		}
		
		// Build response structure similar to API response
		$response = array(
			'app_reference' => $formatted_booking['app_reference'],
			'status' => $formatted_booking['status'],
			'gdspnr' => $formatted_booking['gdspnr'],
			'BookingId' => $formatted_booking['gdspnr'], // For compatibility
			'PNR' => $formatted_booking['gdspnr'], // For compatibility
			'GDSPNR' => $formatted_booking['gdspnr'], // For compatibility
			'created_datetime' => $formatted_booking['created_datetime'],
			'FlightDetails' => $this->format_itinerary_as_flight_details($formatted_itinerary),
			'PassengerDetails' => $formatted_passengers,			
			'Price' => $formatted_pricing,
			'BookingInfo' => array(
				'trip_type' => $formatted_booking['trip_type'],
				'from_airport_code' => $formatted_booking['from_airport_code'],
				'from_airport_city' => $formatted_booking['from_airport_city'],
				'from_airport_name' => $formatted_booking['from_airport_name'],
				'to_airport_code' => $formatted_booking['to_airport_code'],
				'to_airport_city' => $formatted_booking['to_airport_city'],
				'to_airport_name' => $formatted_booking['to_airport_name'],
				'start_date' => $formatted_booking['start_date'],
				'end_date' => $formatted_booking['end_date'],
				'cabin_class' => $formatted_booking['cabin_class'],
				'email' => $formatted_booking['email'],
				'phone_code' => $formatted_booking['phone_code'],
				'phone_number' => $formatted_booking['phone_number'],
				'booking_source' => $formatted_booking['booking_source'],
				'login_user_email' => $user_dec_email,

			),
		);

		$response['seat_details'] = $seat_details['data'];
		$response['meal_details'] = $meal_details_list;
		$response['baggage_details'] = $baggage_details_list;

		if (is_active_insurance_module() && $this->db->table_exists('flight_insurance_policy')) {
			$this->load->model('insurance_model');
			$insurance_block = $this->insurance_model->get_booking_insurance_block($booking_id);
			if (!empty($insurance_block)) {
				$response['Insurance'] = $insurance_block;
				if (!empty($formatted_pricing) && is_array($formatted_pricing)) {
					$response['Price']['total_insurance_premium'] = floatval($insurance_block['total'] ?? 0);
				}
			}
		}

		if (is_active_flight_addon_module() && $this->db->table_exists('flight_extra_service_order')) {
			$this->load->model('flight_addon_model');
			$airport_block = $this->flight_addon_model->get_booking_airport_services_block($booking_id);
			if (!empty($airport_block)) {
				$response['AirportServices'] = $airport_block;
				if (!empty($formatted_pricing) && is_array($formatted_pricing)) {
					$response['Price']['total_airport_services'] = floatval($airport_block['total'] ?? 0);
				}
			}
		}

		if (is_active_flight_airport_cab_module() && $this->db->table_exists('flight_extra_service_order')) {
			$this->load->model('flight_airport_cab_model');
			$cab_block = $this->flight_airport_cab_model->get_booking_airport_cab_block($booking_id);
			if (!empty($cab_block)) {
				$response['AirportCab'] = $cab_block;
				if (!empty($formatted_pricing) && is_array($formatted_pricing)) {
					$response['Price']['total_airport_cab'] = floatval($cab_block['total'] ?? 0);
				}
			}
		}

		return $response;
	}

	/**
	 * Format itinerary details as FlightDetails structure (grouped by journey)
	 * @param array $itinerary_details
	 * @return array
	 */
	private function format_itinerary_as_flight_details($itinerary_details)
	{
			$flight_details = array();
			$journey_groups = array();

			// Group segments by journey_indicator
			foreach ($itinerary_details as $segment) {

			// $departure = $segment['departure_datetime'] ?? null;
			// $arrival = $segment['arrival_datetime'] ?? null;

			// // debug($arrival);die;
			// $duration = '';
			// if ($departure && $arrival) {
			// 	$dep_ts = strtotime($departure);
			// 	$arr_ts = strtotime($arrival);
			// 	$diff_seconds = $arr_ts - $dep_ts;
			// 	$hours = floor($diff_seconds / 3600);
			// 	$minutes = floor(($diff_seconds % 3600) / 60);
			// 	$duration = $hours . 'h ' . $minutes . 'm';
			// }
			
			// if (!empty($segment['duration'])) {
			// 	$duration = $segment['duration'];
			// }


			$journey = $segment['journey_indicator'];
		
			if (!isset($journey_groups[$journey])) {
				$journey_groups[$journey] = array();
			}
			$journey_groups[$journey][] = array(
				'Origin' => array(					
					'AirportCode' => $segment['from_airport_code'],
					'CityName' => $segment['from_airport_city'],
					'AirportName' => $segment['from_airport_name'],
					'DateTime' => $segment['departure_datetime'],
					'date' => date('Y-m-d', strtotime($segment['departure_datetime'])),
					'time' => date('H:i:s', strtotime($segment['departure_datetime'])),
					'OriginTerminal' => $segment['from_airport_terminal'],
				),
				'Destination' => array(
					'AirportCode' => $segment['to_airport_code'],
					'CityName' => $segment['to_airport_city'],
					'AirportName' => $segment['to_airport_name'],
					'DateTime' => $segment['arrival_datetime'],
					'date' => date('Y-m-d', strtotime($segment['arrival_datetime'])),
					'time' => date('H:i:s', strtotime($segment['arrival_datetime'])),
					'DestinationTerminal' => $segment['to_airport_terminal'],
				),
				'OperatorCode' => $segment['airline_code'],
				'OperatorName' => $segment['airline_name'],
				'FlightNumber' => $segment['flight_number'],
				'airpnr' => $segment['airpnr'],
				'journey_indicator' => $journey, 
				'duration' => $segment['duration'],

				
			);
		}

		// Convert to indexed array
		ksort($journey_groups);
		$flight_details = array_values($journey_groups);

		// debug($flight_details);die;
		return $flight_details;
	}

	private function base_query($user_id, $addition_condition = "")
    {
        $this->db->select("
            fbd.id, fbd.app_reference, fbd.status, fbd.status as status_translated, fbd.trip_type,
            fbd.from_airport_city, fbd.to_airport_city, fbd.cabin_class,
            fbd.start_date, fbd.end_date, fbd.created_datetime,
            fbd.bookingId, ftd.total_fare - ftd.admin_discount AS total_fare $addition_condition
        ", false);

        $this->db->from("flight_booking_details AS fbd");
        $this->db->join("flight_booking_transaction_details AS ftd",
            "ftd.flight_booking_details_id = fbd.id", "left");

        $this->db->where("fbd.created_by_id", $user_id);
    }


    /* --------------------------------------------------------
     | GET ALL BOOKINGS (Dashboard)
     -------------------------------------------------------- */
    public function get_dashboard_bookings($user_id, $flightBookingStatus = ['Confirmed'], $limit = null)
    {
        $addition_condition = ",
            CASE WHEN fbd.start_date > CURDATE() THEN 1 ELSE 0 END AS is_upcoming,
            CASE WHEN fbd.start_date <= CURDATE() AND fbd.end_date >= CURDATE() THEN 1 ELSE 0 END AS is_active";
        $this->base_query($user_id, $addition_condition);
        $this->db->where_in("fbd.status", $flightBookingStatus);
        $this->db->order_by("fbd.created_datetime", "DESC");

		if ($limit) {
			$this->db->limit($limit);
		}

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$r)
            $r['book_type'] = 'flight';

        return $rows;
    }


    /* --------------------------------------------------------
     | GET TRANSACTIONS (Transactions Page)
     -------------------------------------------------------- */
    public function get_transactions($filters, $flightBookingStatus = ['Confirmed'])
    {
        $this->base_query($filters['user_id']);
		if (empty($filters['status'])) {
			$this->db->where_in("fbd.status", $flightBookingStatus);
		} else {
			$this->db->where_in("fbd.status", [$filters['status']]);

		}

        if (!empty($filters['start_date']))
            $this->db->where("DATE(fbd.created_datetime) >=", $filters['start_date']);

        if (!empty($filters['end_date']))
            $this->db->where("DATE(fbd.created_datetime) <=", $filters['end_date']);

        if (!empty($filters['search'])) {
            $s = $this->db->escape_like_str($filters['search']);
            $this->db->where("(fbd.app_reference LIKE '%$s%' 
                               OR fbd.from_airport_city LIKE '%$s%' 
                               OR fbd.to_airport_city LIKE '%$s%')");
        }

        return $this->db->get()->result_array();
    }


	private function generate_bcbp_content($passenger, $itinerary, $pnr)
	{
		// debug($passenger);die;
		// assume 1st segment for now – same logic you're using already
		$seg = $itinerary[0];

		// BCBP components
		$format = "M1";
		$name = strtoupper($passenger['last_name'] . "/" . $passenger['first_name']);
		$name = str_pad($name, 20,'0', STR_PAD_LEFT); // fixed length 20

		// $ticket_indicator = "Y"; // Electronic ticket
		$pnr = str_pad($pnr ?? "XXXXX", 7, '0',STR_PAD_LEFT); // 7 chars

		$from = $seg['from_airport_code'];
		$to   = $seg['to_airport_code'];

		$carrier = $seg['airline_code'];
		// $flight  = str_pad($seg['flight_number'], 5,'0', STR_PAD_LEFT);
		$flight = (string) $seg['flight_number'];

		$date = new DateTime($seg['departure_datetime']);
		$julian = $date->format('z') + 1;	

		$class = $seg['cabin_baggage'] ?? "Y";
		$seat  = "0000"; // seat unknown → default
		$this->load->library('yiron_barcode');
		$barCodeData = $format
			. $name."         "			
			. $pnr." "
			. $from
			. $to
			. $carrier." "
			. $flight." "
			. $julian
			. $class
			. $seat
			. "00000000"; // filler
			
		$bar_code = $this->yiron_barcode->generateBarcode2D($barCodeData);
		// debug($bar_code);die;
		return $bar_code;
	}


	function booking($condition = array(), $count = false, $offset = 0, $limit = 100000000000)
	{

		$condition = $this->custom_db->get_custom_condition($condition);

		if ($count) {
			$query = 'select count(distinct(BD.app_reference)) AS total_records from flight_booking_details BD
					where BD.domain_origin=' . get_domain_auth_id() . ' and 1=1 ' . $condition;

			$data = $this->db->query($query)->result_array();
			return $data[0]['total_records'];
		} else {
			$this->load->library('booking_data_formatter');
			$response['status'] = SUCCESS_STATUS;
			$response['data'] = array();
		
			$bd_query = 'select * from flight_booking_details AS BD
						WHERE BD.domain_origin=' . get_domain_auth_id() . ' and 1=1' . $condition . '
						order by BD.created_datetime desc, BD.id desc limit ' . intval($offset) . ', ' . $limit;

			$booking_details = $this->db->query($bd_query)->result_array();
			$response['data']['booking_details'] = $booking_details;


			return $response;
		}
	}


}
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
flight_blender.php
<?php
//TODO: validate Client request

/**
 * Combines the Data from multiple API's
 * @author Jaganath
 *
 */
class Flight_Blender
{
	function __construct()
	{
	
		$this->CI = &get_instance();

		$this->CI->load->library('CurlMultiHandler');
		$this->CI->load->library('Discount/flight_discount');
		$this->CI->load->library('facts_tally/facts');
		$this->CI->load->library('Markup/markup');
		$this->CI->load->library('Markup/b2b_markup');

		$this->CI->load->library('Gds_Settings/Gds_flight_commission', null, 'gds_commission');
	}

	private function search_data($search_id)
	{
		return $this->CI->flight_model->get_safe_search_data($search_id);
	}

	/**
	 * Flight Active Booking Sources
	 * 
	 */
	private function flight_active_booking_sources($condition = array())
	{
		$active_booking_source_condition = array();
		$active_booking_source_condition[] = array('BS.meta_course_list_id', '=', '"' . META_AIRLINE_COURSE . '"');
		$active_booking_source_condition[] = array('DL.origin', '=', get_domain_auth_id());
		$active_booking_source_condition = array_merge($active_booking_source_condition, $condition);
		// debug($active_booking_source_condition);die;
		$active_booking_sources = $this->CI->db_cache_api->get_active_api_booking_source($active_booking_source_condition);
		// debug($this->CI->db->last_query());die;
		return $active_booking_sources;
	}

	private function api_authentication($active_booking_sources = array()) {}



	public function stream_flight_list($search_id, callable $onResponse)
	{

		$search_data = $this->search_data($search_id);
		$flight_active_booking_sources = $this->flight_active_booking_sources();

		$search_handles = [];
		$flight_obj = [];

		foreach ($flight_active_booking_sources as $bs_k => $bs_v) {

			$flight_obj_ref = load_web_flight_lib($bs_v['booking_source'], NULL, true);
			$flight_obj[$bs_v['booking_source']] = $flight_obj_ref;

			$search_handles[$bs_v['booking_source']] = $this->CI->$flight_obj_ref->get_search_request($search_data['data']);
		}
		$search__data = $this;
		$this->CI->curlmultihandler->streamExecute($search_handles, function ($provider, $rawResponse) use ($flight_obj, $onResponse, $search__data, $search_data) {
			$fo_v = $flight_obj[$provider];
			$flight_data = $this->CI->$fo_v->format_search_response($rawResponse, $search_data['data']);

			foreach ($flight_data['data'] as &$flight) {
				$this->set_flight_markup($flight);
				$display_fare = $flight['Price']['TotalDisplayFare'];
				$discount_data = $this->get_flight_discount($flight, $display_fare);
				$flight['Price']['discount'] = $discount_data;
			}

			$onResponse($flight_data);
		});
	}



	/**
	 * 
	 * Merges the Flight Data	 

	 * @param array $flight_data
	 * @param array $formatted_seach_result
	 */
	private function merge_flight_list($flight_data, &$formatted_seach_result)
	{
		foreach ($flight_data as $fd_k => $fd_v) {
			$formatted_seach_result['data']['FlightDataList']['JourneyList'][$fd_k] = array_merge($formatted_seach_result['data']['FlightDataList']['JourneyList'][$fd_k], $fd_v);
		}
	}

	/*
	 * Sort the flights based on price
	 */
	private function sort_flight_list($JourneyList)
	{
		$sorted_journey_list = array();
		foreach ($JourneyList as $jl_k => $jl_v) {
			$sort_item = array();
			foreach ($jl_v as $row_k => $row_v) {
				// $sort_item [$row_k] = floatval ( $row_v ['Price'] ['TotalDisplayFare'] );
				$sort_item[$row_k] = floatval($row_v['Price']['TotalDisplayFare'] - $row_v['Price']['PriceBreakup']['AgentCommission'] + $row_v['Price']['PriceBreakup']['AgentTdsOnCommision']);
			}
			array_multisort($sort_item, SORT_ASC, $jl_v);
			$sorted_journey_list[$jl_k] = $jl_v;
		}
		return $sorted_journey_list;
	}

	public function upsell_flight_list($request)
	{
		$upsell_flight_list = array();
		$upsell_flight_list['data'] = array();
		$upsell_flight_list['status'] = FAILURE_STATUS;
		$upsell_flight_list['message'] = array();

		$ResultToken = trim(@$request['ResultToken']);

		if ($ResultToken) {
			$ResultToken = unserialized_data($ResultToken);

			$booking_source = $ResultToken['booking_source'];
			$active_booking_source_condition = array();
			$active_booking_source_condition[] = array('BS.source_id', '=', '"' . $booking_source . '"');
			$flight_active_booking_sources = $this->flight_active_booking_sources($active_booking_source_condition);

			//Authenticate the API's
			$this->api_authentication($flight_active_booking_sources);

			$flight_obj_ref = load_web_flight_lib($booking_source);

			$upsell_list = $this->CI->$flight_obj_ref->get_upsell($ResultToken['token']);
			foreach ($upsell_list['data'] as &$flight) {
				$this->set_flight_markup($flight);
				$display_fare = $flight['Price']['TotalDisplayFare'];
				$discount_data = $this->get_flight_discount($flight, $display_fare);
				$flight['Price']['discount'] = $discount_data;
			}

			if ($upsell_list['status'] == SUCCESS_STATUS) {

				$upsell_flight_list['status'] = SUCCESS_STATUS;

				$upsell_flight_list['data'] = $upsell_list['data'];
			} else {
				$update_fare_quote['message'] = $upsell_list['message'];
			}
		} else {

			$upsell_flight_list['message'] = 'Invalid updateFareQuote Request';
		}

		return $upsell_flight_list;
	}

	public function update_fare_quote($request)
	{

		$update_fare_quote = array();
		$update_fare_quote['data'] = array();
		$update_fare_quote['status'] = FAILURE_STATUS;
		$update_fare_quote['message'] = array();

		$ResultToken = trim(@$request['ResultToken']);

		if ($ResultToken) {
			$ResultToken = unserialized_data($ResultToken);

			$booking_source = $ResultToken['booking_source'];
			$active_booking_source_condition = array();
			$active_booking_source_condition[] = array('BS.source_id', '=', '"' . $booking_source . '"');
			$flight_active_booking_sources = $this->flight_active_booking_sources($active_booking_source_condition);

			//Authenticate the API's
			$this->api_authentication($flight_active_booking_sources);

			$flight_obj_ref = load_web_flight_lib($booking_source);

			$update_fare_quote_data = $this->CI->$flight_obj_ref->get_update_fare_quote($ResultToken['token']);

			$display_fare = $update_fare_quote_data['data']['Price']['TotalDisplayFare'];
	
			$this->set_flight_markup($update_fare_quote_data['data']);
			$discount_data = $this->get_flight_discount($update_fare_quote_data['data'], $display_fare);
			$update_fare_quote_data['data']['Price']['discount'] = $discount_data;

			if ($update_fare_quote_data['status'] == SUCCESS_STATUS) {

				$update_fare_quote['status'] = SUCCESS_STATUS;

				$update_fare_quote['data'] = $update_fare_quote_data['data'];
			} else {
				$update_fare_quote['message'] = $update_fare_quote_data['message'];
			}
		} else {

			$update_fare_quote['message'] = 'Invalid updateFareQuote Request';
		}
		return $update_fare_quote;
	}


	public function get_extra_services($request)
	{
		$extra_service_response = array();
		$extra_service_response['status'] = FAILURE_STATUS;
		$extra_service_response['message'] = '';

		$ResultToken = unserialized_data(trim($request['ResultToken']));

		if ($ResultToken) {

			$booking_source = $ResultToken['booking_source'];

			$active_booking_source_condition = array();
			$active_booking_source_condition[] = array('BS.source_id', '=', '"' . $booking_source . '"');
			$flight_active_booking_sources = $this->flight_active_booking_sources($active_booking_source_condition);

			//Authenticate the API's
			$this->api_authentication($flight_active_booking_sources);

			$flight_obj_ref = load_web_flight_lib($booking_source);

			$extra_service = $this->CI->$flight_obj_ref->get_extra_services($ResultToken['token']);

			$es = isset($extra_service['ExtraServiceDetails']) && is_array($extra_service['ExtraServiceDetails'])
				? $extra_service['ExtraServiceDetails']
				: array();

			$has_seat = !empty($es['Seat']);
			$has_meals = $this->extra_service_segment_matrix_has_rows($es['Meals'] ?? null);
			$has_baggage = $this->extra_service_segment_matrix_has_rows($es['Baggage'] ?? null);
			if ($has_seat || $has_meals || $has_baggage) {
				$extra_service_response['message'] = "";
				$extra_service_response['status'] = SUCCESS_STATUS;
				$extra_service_response['data'] = $extra_service;
			} else {
				$extra_service_response['message'] = $extra_service_response['message'];
			}
		} else {
			$extra_service_response['message'] = 'Invalid Seat Request';
		}
		return $extra_service_response;
	}

	/**
	 * Addons endpoint payload (Insurance + AirportServices when available).
	 *
	 * Response shape:
	 * {
	 *   "status": SUCCESS_STATUS,
	 *   "message": "",
	 *   "data": {
	 *     "Addon": {
	 *       "Insurance": { ... },
	 *       "AirportServices": { services, segment_meta, is_connecting }
	 *     }
	 *   }
	 * }
	 * Only keys with actual catalog data are included (no null/empty placeholders).
	 */
	public function get_addons($request)
	{
		$response = array(
			'status' => FAILURE_STATUS,
			'message' => 'No addons available',
			'data' => array(
				'Addon' => array(),
			),
		);

		$raw_token = isset($request['ResultToken']) ? $request['ResultToken'] : '';
		$ResultToken = unserialized_data(trim(is_scalar($raw_token) ? (string)$raw_token : ''));
		if (!$ResultToken) {
			$response['message'] = 'ResultToken is invalid';
			return $response;
		}

		$booking_source = $ResultToken['booking_source'];
		$active_booking_source_condition = array();
		$active_booking_source_condition[] = array('BS.source_id', '=', '"' . $booking_source . '"');
		$flight_active_booking_sources = $this->flight_active_booking_sources($active_booking_source_condition);

		$this->api_authentication($flight_active_booking_sources);

		// CRS addons: request + cached token only (no supplier library / no re-price call).
		$universal_context = $this->build_universal_addon_context_from_request($ResultToken, $request);

		if (is_active_insurance_module()) {
			try {
				$this->CI->load->model('insurance_model');
				$insurance_plans = $this->CI->insurance_model->get_eligible_flight_insurance_plans(
					$universal_context['insurance'] ?? array()
				);
				if (!empty($insurance_plans['plans']) && is_array($insurance_plans['plans'])) {
					$response['data']['Addon']['Insurance'] = $insurance_plans;
				}
			} catch (Throwable $e) {
				log_message('error', 'Addons insurance catalog failed: ' . $e->getMessage());
			}
		}

		if (is_active_flight_addon_module()) {
			try {
				$this->CI->load->model('flight_addon_model');
				$airport_services = $this->CI->flight_addon_model->get_eligible_airport_services(
					$universal_context['airport'] ?? array()
				);
				$has_offers = false;
				if (!empty($airport_services['services']) && is_array($airport_services['services'])) {
					foreach ($airport_services['services'] as $svc) {
						if (!empty($svc['offers'])) {
							$has_offers = true;
							break;
						}
					}
				}
				if ($has_offers) {
					$response['data']['Addon']['AirportServices'] = $airport_services;
				}
			} catch (Throwable $e) {
				log_message('error', 'Addons airport services catalog failed: ' . $e->getMessage());
			}
		}

		$has_insurance = !empty($response['data']['Addon']['Insurance']['plans']);
		$has_airport_services = !empty($response['data']['Addon']['AirportServices']);
		$has_any = $has_insurance || $has_airport_services;

		// Keep addon API shape stable for frontend; only message varies.
		$response['status'] = SUCCESS_STATUS;
		$response['message'] = $has_any ? '' : 'No addons available';
		// if (!$has_insurance) {
		// 	$debug = array(
		// 		'current_db' => null,
		// 		'insurance_plan_table' => $this->CI->db->table_exists('insurance_plan'),
		// 		'insurance_provider_table' => $this->CI->db->table_exists('insurance_provider'),
		// 		'insurance_plan_count' => 0,
		// 		'insurance_provider_count' => 0,
		// 		'published_plan_count' => 0,
		// 		'active_provider_count' => 0,
		// 		'domain_origin' => intval(get_domain_auth_id()),
		// 	);
		// 	try {
		// 		$db_row = $this->CI->db->query('SELECT DATABASE() AS db_name')->row_array();
		// 		$debug['current_db'] = $db_row['db_name'] ?? null;
		// 		if ($debug['insurance_plan_table']) {
		// 			$debug['insurance_plan_count'] = intval($this->CI->db->count_all('insurance_plan'));
		// 			$debug['published_plan_count'] = intval(
		// 				$this->CI->db
		// 					->where("LOWER(TRIM(status)) = 'published'", null, false)
		// 					->count_all_results('insurance_plan')
		// 			);
		// 		}
		// 		if ($debug['insurance_provider_table']) {
		// 			$debug['insurance_provider_count'] = intval($this->CI->db->count_all('insurance_provider'));
		// 			$debug['active_provider_count'] = intval(
		// 				$this->CI->db
		// 					->where("LOWER(TRIM(status)) = 'active'", null, false)
		// 					->count_all_results('insurance_provider')
		// 			);
		// 		}
		// 	} catch (Throwable $e) {
		// 		$debug['error'] = $e->getMessage();
		// 	}
		// 	$response['data']['AddonDebug'] = $debug;
		// }

		return $response;
	}

	public function pre_book($request)
	{
		// debug($request); die;
		$response = null;
		$ResultToken = trim(@$request['ResultToken']);
		if ($ResultToken) {

			$ResultToken = unserialized_data($ResultToken);

			$booking_source = $ResultToken['booking_source'];

			$active_booking_source_condition = array();
			$active_booking_source_condition[] = array('BS.source_id', '=', '"' . $booking_source . '"');
			$flight_active_booking_sources = $this->flight_active_booking_sources($active_booking_source_condition);

			//Authenticate the API's
			$this->api_authentication($flight_active_booking_sources);

			$flight_obj_ref = load_web_flight_lib($booking_source, NULL, true);

			$app_reference = 'YTS-F' . substr($booking_source, -3) . '-' . time();
			// debug($app_reference);die;
			// Get lead passenger (first passenger)
			$leadPassenger = !empty($request['Passengers'][0]) ? $request['Passengers'][0] : null;
			if (!$leadPassenger) {
				return null;
			}

			$tokenDataResponse = $this->CI->$flight_obj_ref->getpreBookData($ResultToken['token'], $app_reference, $request['Passengers']);
			// debug($tokenDataResponse);die;
			if (!$tokenDataResponse || $tokenDataResponse['status'] != true || empty($tokenDataResponse['data'])) {
				return null;
			}
			$tokenData = $tokenDataResponse['data'];

		

			// Save flight_booking_transaction_details
			$supervision_user = is_supervision_user();
			if ($supervision_user && isset($request['admin_markup'])) {
				$this->set_flight_markup($tokenData, true, $request['admin_markup']);
			}else{
				$this->set_flight_markup($tokenData, true);
			}
			
			$display_fare = $tokenData['Price']['TotalDisplayFare'];

			$discount_data = $this->get_flight_discount($tokenData, $display_fare);

			// Extract data from the formatted response (same format as get_update_fare_quote)
			$flightDetails = $tokenData['FlightDetails'] ?? [];
			$priceData = $tokenData['Price'] ?? [];
			$priceData['discount'] = $discount_data;


			// Extract flight data from FlightDetails
			$firstJourney = $flightDetails[0] ?? [];
			$firstSegment = $firstJourney[0] ?? [];
			$lastJourney = $flightDetails[count($flightDetails) - 1] ?? [];
			$lastSegment = $lastJourney[count($lastJourney) - 1] ?? [];

			// Determine trip type
			$journeyCount = count($flightDetails);
			$tripType = 'OneWay';
			if ($journeyCount > 1) {
				$firstOrigin = $firstSegment['Origin']['AirportCode'] ?? '';
				$firstDest = $firstSegment['Destination']['AirportCode'] ?? '';
				$lastOrigin = $lastSegment['Origin']['AirportCode'] ?? '';
				$lastDest = $lastSegment['Destination']['AirportCode'] ?? '';

				if ($firstOrigin == $lastDest && $firstDest == $lastOrigin && $journeyCount == 2) {
					$tripType = 'Return';
				} else {
					$tripType = 'Multicity';
				}
			}

			$fromAirportCode = $firstSegment['Origin']['AirportCode'] ?? '';
			$toAirportCode = $lastSegment['Destination']['AirportCode'] ?? '';
			$startDate = !empty($firstSegment['Origin']['date']) ? $firstSegment['Origin']['date'] : null;
			$endDate = !empty($lastSegment['Destination']['date']) ? $lastSegment['Destination']['date'] : null;
			$cabinClass = $firstSegment['CabinClass'] ?? 'Economy';

			// Extract fare data from Price
			$baseFare = $priceData['PriceBreakup']['BasicFare'] ?? 0;
			$taxes = $priceData['PriceBreakup']['Tax'] ?? 0;
			$totalFare = $priceData['TotalDisplayFare'] ?? 0;
			$currency = $priceData['Currency'] ?? admin_base_currency();
			$admin_discount = $priceData['discount']['amount'] ?? 0;
			$admin_discount_type = ($priceData['discount']['isPercentage'] == true) ? 'Percentage' : 'Flat';
			$admin_discount_value = $priceData['discount']['value'] ?? 0;

			// Build segments array from FlightDetails for database storage
			$segments = [];
			foreach ($flightDetails as $journeyIndex => $journey) {
				foreach ($journey as $segmentIndex => $segment) {
					$segments[] = [
						'journey_index' => $journeyIndex + 1,
						'segment_index' => $segmentIndex + 1,
						'segment_data' => $segment,
					];
				}
			}

			$flight_data = [
				'trip_type' => $tripType,
				'from_airport_code' => $fromAirportCode,
				'to_airport_code' => $toAirportCode,
				'start_date' => $startDate,
				'end_date' => $endDate,
				'cabin_class' => $cabinClass,
				'segments' => $segments,
			];

			$fare_data = [
				'BaseFare' => $baseFare,
				'Tax' => $taxes,
				'TotalFare' => $totalFare,
				'Currency' => $currency,
				'PriceBreakup' => $priceData['PriceBreakup'] ?? [],
				'PassengerBreakup' => $priceData['PassengerBreakup'] ?? [],
			];

			$domain_origin = get_domain_auth_id();
			$status = 'Started';
			$productinfo = META_AIRLINE_COURSE;

			// Get airport details
			$this->CI->load->model('flight_model');
			$from_airport = $this->CI->flight_model->get_airport_city_name($flight_data['from_airport_code']);
			$to_airport = $this->CI->flight_model->get_airport_city_name($flight_data['to_airport_code']);

			$currency = $fare_data['Currency'] ?? admin_base_currency();
			$base_fare = $fare_data['BaseFare'];
			$taxes = $fare_data['Tax'];

			$admin_markup = $priceData['PriceBreakup']['AdminMarkup'] ?? 0;
			$admin_markup_tax = 0;
			// Agent markup is an internal B2B margin; record it but do not collect it in payment.
			$agent_markup = $priceData['PriceBreakup']['AgentMarkup'] ?? 0;
			$agent_markup_tax = 0;

			// `PriceBreakup.Tax` includes admin markup for non-B2B flows (set_flight_markup adds it).
			// Agent markup is NOT added to Tax (it's a B2B internal margin), so do not subtract it here.
			$taxes = $taxes - $admin_markup - $admin_markup_tax - $agent_markup - $agent_markup_tax;

			// Amount used for promo/loyalty/convenience/payment must EXCLUDE agent markup.
			$amount = $base_fare + $taxes + $admin_markup + $admin_markup_tax;

			/** ******* Promocode Start ******* */
			$promocode_discount = 0;
			$applied_promo_code = '';
			$promocode_type = null;
			$promocode_value = 0;

			if (isset($request['promo_code'])) {
				$this->CI->load->library('yiron_promocode');

				$promocodeValidate = $this->CI->yiron_promocode->validateFlightPromocode($request['promo_code'], $amount, $currency);

				if ($promocodeValidate['status'] == true) {
					$promocode_discount = $promocodeValidate['data']['discount_actual'];
					$applied_promo_code = $promocodeValidate['data']['promocode'];
					$promocode_type = $promocodeValidate['data']['discount_type'] ?? 'Flat';
					$promocode_value = $promocodeValidate['data']['discount_value'] ?? 0;

					// $promo_code_doscount_applied_data = array(
					// 	'discount_value' => $promocodeValidate['data']['discount_actual'],
					// 	'promocode' => $promocodeValidate['data']['promocode'],
					// 	'module' => $productinfo,
					// 	'search_key' => '0',
					// 	'created_datetime' => date('Y-m-d H:i:s')
					// );

					// $this->CI->custom_db->insert_record('promo_code_doscount_applied', $promo_code_doscount_applied_data);
				}
			}

			/** ******* Promocode End ******* */


			$insurance_total = 0;
			$insurance_tax_amount = 0;
			$insurance_base_amount = 0;
			$insurance_selection = isset($request['InsuranceSelection']) && is_array($request['InsuranceSelection'])
				? $request['InsuranceSelection']
				: array();
			$insurance_validation = null;
			$buy_insurance = filter_var($insurance_selection['buy_insurance'] ?? false, FILTER_VALIDATE_BOOLEAN);

			$airport_services_total = 0;
			$airport_services_validation = null;
			$airport_services_selection = isset($request['AirportServicesSelection']) && is_array($request['AirportServicesSelection'])
				? $request['AirportServicesSelection']
				: array();
			$airport_service_items = isset($airport_services_selection['selections']) && is_array($airport_services_selection['selections'])
				? $airport_services_selection['selections']
				: array();

			$airport_cab_total = 0;
			$airport_cab_validation = null;
			$airport_cab_selection = isset($request['AirportCabSelection']) && is_array($request['AirportCabSelection'])
				? $request['AirportCabSelection']
				: array();

			// SSR starts (seats + meals + baggage totals for payment)
			$totalSeatPrice = 0;
			$totalMealPrice = 0;
			$totalBaggagePrice = 0;
			foreach ($request['Passengers'] as $pax) {
				if (!empty($pax['SeatDetails'])) {
					foreach ($pax['SeatDetails'] as $segmentSeats) {
						foreach ($segmentSeats as $legSeat) {
							if (!empty($legSeat['SeatKey'])) {
								$decoded = base64_decode($legSeat['SeatKey']);
								$data = @unserialize($decoded);
								// debug($data[0]['Price']); 
								if (is_array($data) && isset($data[0]['Price'])) {
									$totalSeatPrice += floatval($data[0]['Price']);
								}
							}
						}
					}
				}
				foreach ($this->flatten_passenger_ancillary_field($pax['MealId'] ?? null) as $meal_row) {
					$totalMealPrice += floatval($meal_row['Price'] ?? $meal_row['TotalPrice'] ?? 0);
				}
				foreach ($this->flatten_passenger_ancillary_field($pax['BaggageId'] ?? null) as $bag_row) {
					$totalBaggagePrice += floatval($bag_row['Price'] ?? $bag_row['TotalPrice'] ?? 0);
				}
			}
			// SSR ends

			// Insurance selection validation + pricing (module-gated by Services & APIs).
			if ($buy_insurance) {
				if (!is_active_insurance_module()) {
					return array(
						'status' => FAILURE_STATUS,
						'message' => 'Insurance service is currently inactive for this domain.',
						'errors' => array('Insurance module is inactive'),
						'data' => array(),
					);
				}

				$plan_token = trim((string)($insurance_selection['plan_token'] ?? ''));
				if ($plan_token === '') {
					return array(
						'status' => FAILURE_STATUS,
						'message' => 'Insurance plan selection is required.',
						'errors' => array('Missing insurance plan token'),
						'data' => array(),
					);
				}

				$terms_accepted = filter_var($insurance_selection['consent']['terms_accepted'] ?? false, FILTER_VALIDATE_BOOLEAN);
				if ($terms_accepted !== true) {
					return array(
						'status' => FAILURE_STATUS,
						'message' => 'Please accept insurance terms and conditions.',
						'errors' => array('Insurance consent missing'),
						'data' => array(),
					);
				}

				$trip_scope = 'both';
				$origin_country = trim((string)($firstSegment['Origin']['country'] ?? ''));
				$dest_country = trim((string)($firstSegment['Destination']['country'] ?? ''));
				if ($origin_country !== '' && $dest_country !== '') {
					$trip_scope = (strtoupper($origin_country) === strtoupper($dest_country)) ? 'domestic' : 'international';
				}

				$this->CI->load->model('insurance_model');
				$insurance_context = array(
					'domain_origin' => $domain_origin,
					'fare_amount' => max(0, ($amount - $promocode_discount - $admin_discount)),
					'trip_scope' => $trip_scope,
					'trip_type' => $tripType,
					'travel_date' => $startDate ?: date('Y-m-d'),
					'pax_count' => count($request['Passengers']),
				);
				$insurance_validation = $this->CI->insurance_model->validate_and_price_plan_token($plan_token, $insurance_context);
				if (empty($insurance_validation['status'])) {
					return array(
						'status' => FAILURE_STATUS,
						'message' => $insurance_validation['message'] ?? 'Invalid insurance plan selection.',
						'errors' => array($insurance_validation['message'] ?? 'Insurance plan validation failed'),
						'data' => array(),
					);
				}

				$insurance_base_amount = floatval($insurance_validation['data']['pricing']['premium'] ?? 0);
				$insurance_tax_amount = floatval($insurance_validation['data']['pricing']['tax'] ?? 0);
				$insurance_total = floatval($insurance_validation['data']['pricing']['total'] ?? 0);
			}

			if (!empty($airport_service_items) && is_active_flight_addon_module()) {
				$this->CI->load->model('flight_addon_model');
				$addon_context = $this->build_universal_addon_context_from_request($ResultToken, $request);
				$addon_context = $addon_context['airport'] ?? array();
				if (empty($addon_context['domain_origin'])) {
					$addon_context['domain_origin'] = $domain_origin;
				}
				if (empty($addon_context['currency'])) {
					$addon_context['currency'] = $currency;
				}
				$airport_services_validation = $this->CI->flight_addon_model->validate_and_price_selections($airport_service_items, $addon_context);
				if (empty($airport_services_validation['status'])) {
					return array(
						'status' => FAILURE_STATUS,
						'message' => $airport_services_validation['message'] ?? 'Invalid airport service selection.',
						'errors' => array($airport_services_validation['message'] ?? 'Airport services validation failed'),
						'data' => array(),
					);
				}
				$airport_services_total = floatval($airport_services_validation['data']['total'] ?? 0);
			}

			if (!empty($airport_cab_selection['result_token']) && is_active_flight_airport_cab_module()) {
				$this->CI->load->model('flight_airport_cab_model');
				$airport_cab_validation = $this->CI->flight_airport_cab_model->validate_and_price_selection($airport_cab_selection);
				if (empty($airport_cab_validation['status'])) {
					return array(
						'status' => FAILURE_STATUS,
						'message' => $airport_cab_validation['message'] ?? 'Invalid airport cab selection.',
						'errors' => array($airport_cab_validation['message'] ?? 'Airport cab validation failed'),
						'data' => array(),
					);
				}
				$airport_cab_total = floatval($airport_cab_validation['data']['amount'] ?? 0);
			}

			$total_transaction_amount = $amount - $promocode_discount - $admin_discount + $totalSeatPrice + $totalMealPrice + $totalBaggagePrice + $insurance_total + $airport_services_total + $airport_cab_total;

			/** ******* Loyalty Start ******* */
			$this->CI->load->library('Loyalty/loyalty');
			$redeem_response = $this->CI->loyalty->initRedemption([
				'loyalty'			=> $request['loyalty'] ?? [],
				'user_id'			=> (int)($this->CI->entity_user_id ?? 0),
				'module_type'		=> META_AIRLINE_COURSE,
				'booking_amount'	=> $total_transaction_amount,
				'booking_currency'	=> $currency,
			]);

			$loyalty_redeem_amount = (float)($redeem_response['redeem_amount'] ?? 0);
			$attributes = [];
			if (!empty($redeem_response) && !empty($redeem_response['applied'])) {
				$attributes = $redeem_response['attributes'] ?? [];
			}
			$total_transaction_amount = $total_transaction_amount - $loyalty_redeem_amount;
			if ($total_transaction_amount < 0) {
				$total_transaction_amount = 0;
			}
			/** ******* Loyalty End ******* */
			$pg_code = @$request['pg_code'];

			/** ******* Convinience Fee Start ******* */
			$convinence_amount = 0;
			$convinence_value = 0;
			$convinence_type = null;
			$this->CI->load->model('transaction');
			$convinience = $this->CI->transaction
				->calculate_convenience_fee_by_code(
					$pg_code,
					$total_transaction_amount
				);

			$convinence_amount = $convinience['fee_amount'];
			$convinence_value  = $convinience['fee_value'];
			$convinence_type   = $convinience['fee_type'];
			// $payment_gateways = $this->CI->transaction->active_payment_gateways();
			// if (count($payment_gateways) > 0) {

			// 	$pg_configs = $payment_gateways[0];

			// 	if (isset($pg_code)) {
			// 		foreach ($payment_gateways as $payment_gateway) {
			// 			if ($payment_gateway['code'] == $pg_code) {
			// 				$pg_configs = $payment_gateway;
			// 			}
			// 		}
			// 	}

			// 	$convinence_value = $pg_configs['convinience_fee'];
			// 	$convinence_type = $pg_configs['convinience_fee_type'];
			// }

			// if ($convinence_type === 'plus') {
			// 	$convinence_amount = floatval($convinence_value);
			// } elseif ($convinence_type === 'percentage') {
			// 	$convinence_amount = (floatval($total_transaction_amount) * floatval($convinence_value)) / 100;
			// }
			/** ******* Convinience Fee End ******* */

			$final_total_fare = $total_transaction_amount + $convinence_amount;

			// Get lead passenger contact details
			$email = $leadPassenger['Email'] ?? '';
			$phone_code = $leadPassenger['PhoneCountryCode'] ?? '';
			$phone_number = $leadPassenger['ContactNo'] ?? '';

			// Get billing address from lead passenger
			$billing_address_line_1 = $leadPassenger['AddressLine1'] ?? '';
			$billing_address_line_2 = $leadPassenger['AddressLine2'] ?? '';
			$billing_address_city = $leadPassenger['City'] ?? '';
			$billing_address_pincode = $leadPassenger['PinCode'] ?? '';
			$billing_address_country = $leadPassenger['CountryName'] ?? '';

			// Save flight_booking_details
			$flight_booking_details = array();
			$flight_booking_details['domain_origin'] = $domain_origin;
			$flight_booking_details['status'] = $status;
			$flight_booking_details['app_reference'] = $app_reference;
			$flight_booking_details['trip_type'] = $flight_data['trip_type'];
			$flight_booking_details['from_airport_code'] = $flight_data['from_airport_code'];
			$flight_booking_details['from_airport_city'] = $from_airport->airport_city ?? '';
			$flight_booking_details['from_airport_name'] = $from_airport->airport_name ?? '';
			$flight_booking_details['to_airport_code'] = $flight_data['to_airport_code'];
			$flight_booking_details['to_airport_city'] = $to_airport->airport_city ?? '';
			$flight_booking_details['to_airport_name'] = $to_airport->airport_name ?? '';
			$flight_booking_details['start_date'] = $flight_data['start_date'];
			$flight_booking_details['end_date'] = $flight_data['end_date'];
			$flight_booking_details['cabin_class'] = $flight_data['cabin_class'];
			$flight_booking_details['booking_source'] = $booking_source;
			$flight_booking_details['email'] = $email;
			$flight_booking_details['phone_code'] = $phone_code;
			$flight_booking_details['phone_number'] = $phone_number;
			$flight_booking_details['billing_address_line_1'] = $billing_address_line_1;
			$flight_booking_details['billing_address_line_2'] = $billing_address_line_2;
			$flight_booking_details['billing_address_city'] = $billing_address_city;
			$flight_booking_details['billing_address_pincode'] = $billing_address_pincode;
			$flight_booking_details['billing_address_country'] = $billing_address_country;
			$flight_booking_details['attributes'] = json_encode([]);
			$flight_booking_details['created_by_id'] = $this->CI->entity_user_id ?? 0;

	
			$flight_booking_details_id = $this->CI->custom_db->insert_record('flight_booking_details', $flight_booking_details);

			// Save flight_booking_itinerary_details
			$segments = $flight_data['segments'];

			$journey_segment_map = [];

			foreach ($segments as $segmentInfo) {

				$segment = $segmentInfo['segment_data'];
				$journeyIndicator = $segmentInfo['journey_index'];
				$segmentIndicator = $segmentInfo['segment_index'];



				// Extract data from formatted segment structure
				$origin = $segment['Origin']['AirportCode'] ?? '';
				$destination = $segment['Destination']['AirportCode'] ?? '';
				$departure_datetime = $segment['Origin']['DateTime'] ?? null;
				$arrival_datetime = $segment['Destination']['DateTime'] ?? null;
				$airline_code = $segment['OperatorCode'] ?? '';
				$airline_name = $segment['OperatorName'] ?? '';
				$flight_number = $segment['FlightNumber'] ?? '';
				$from_terminal = $segment['Origin']['OriginTerminal'] ?? null;
				$to_terminal = $segment['Destination']['DestinationTerminal'] ?? null;
				$duration = $segment['Duration'] ?? null;

				$from_airport_seg = $this->CI->flight_model->get_airport_city_name($origin);
				$to_airport_seg = $this->CI->flight_model->get_airport_city_name($destination);

				$itinerary_details_attributes = [
					'Attr' => $segment['Attr'] ?? [],
					'rbd' => $segment['rbd'] ?? '',
				];

				$itinerary_details = array();
				$itinerary_details['flight_booking_details_id'] = $flight_booking_details_id['insert_id'];
				$itinerary_details['journey_indicator'] = $journeyIndicator;
				$itinerary_details['segment_indicator'] = $segmentIndicator;
				$itinerary_details['airline_code'] = $airline_code;
				$itinerary_details['airline_name'] = $airline_name;
				$itinerary_details['flight_number'] = $flight_number;
				$itinerary_details['from_airport_code'] = $origin;
				$itinerary_details['from_airport_city'] = $from_airport_seg->airport_city ?? '';
				$itinerary_details['from_airport_name'] = $from_airport_seg->airport_name ?? '';
				$itinerary_details['from_airport_terminal'] = $from_terminal;
				$itinerary_details['to_airport_code'] = $destination;
				$itinerary_details['to_airport_city'] = $to_airport_seg->airport_city ?? '';
				$itinerary_details['to_airport_name'] = $to_airport_seg->airport_name ?? '';
				$itinerary_details['to_airport_terminal'] = $to_terminal;
				$itinerary_details['departure_datetime'] = $departure_datetime;
				$itinerary_details['arrival_datetime'] = $arrival_datetime;
				$itinerary_details['duration'] = $duration;
				
				$itinerary_details['attributes'] = json_encode($itinerary_details_attributes);

				$this->CI->custom_db->insert_record('flight_booking_itinerary_details', $itinerary_details);

				$journey_segment_map[$origin . '-' . $destination] = [
					'journey_index' => $journeyIndicator,
					'segment_index' => $segmentIndicator
				];
			}

			// Save flight_booking_passenger_details
			$inserted_passenger_rows = array();
			$pax_type_counter = array('Adult' => 0, 'Child' => 0, 'Infant' => 0);
			if (!empty($request['Passengers']) && is_array($request['Passengers'])) {
				$isLeadPax = true;
				foreach ($request['Passengers'] as $passenger) {
					$passenger_details = array();
					$passenger_details['flight_booking_details_id'] = $flight_booking_details_id['insert_id'];
					$passenger_details['passenger_type'] = $passenger['PaxType'];
					$passenger_details['is_lead'] = $isLeadPax ? 1 : 0;
					$passenger_details['title'] = $passenger['Title'] ?? '';
					$passenger_details['first_name'] = $passenger['FirstName'];
					$passenger_details['last_name'] = $passenger['LastName'];
					$passenger_details['date_of_birth'] = $passenger['DateOfBirth'];
					$passenger_details['gender'] = $passenger['Gender'];
					$passenger_details['nationality'] = $passenger['Nationality'] ?? '';
					$passenger_details['passport_number'] = $passenger['PassportNumber'] ?? '';
					$passenger_details['passport_issuing_country'] = $passenger['PassportIssueCountry'] ?? '';
					$passenger_details['passport_expiry_date'] = $passenger['PassportExpiry'] ?? '';
					$passenger_details['ff_airline'] = $passenger['FFAirlineCode'] ?? null;
					$passenger_details['ff_number'] = $passenger['FFNumber'] ?? '';

					$insert_pax = $this->CI->custom_db->insert_record('flight_booking_passenger_details', $passenger_details);


					$passenger_id = $insert_pax['insert_id'];
					$pax_type_value = $passenger['PaxType'];
					$pax_type_counter[$pax_type_value] = intval($pax_type_counter[$pax_type_value] ?? 0) + 1;
					$type_code_map = array('Adult' => 'ADT', 'Child' => 'CHD', 'Infant' => 'INF');
					$pax_type_code = $type_code_map[$pax_type_value] ?? strtoupper(substr($pax_type_value, 0, 3));
					$passenger_client_key = $pax_type_code . '_' . $pax_type_counter[$pax_type_value];
					$inserted_passenger_rows[] = array(
						'id' => $passenger_id,
						'passenger_type' => $pax_type_value,
						'client_key' => $passenger_client_key,
						'name' => trim(($passenger['Title'] ?? '') . ' ' . ($passenger['FirstName'] ?? '') . ' ' . ($passenger['LastName'] ?? '')),
						'dob' => $passenger['DateOfBirth'] ?? null,
					);

					if (!empty($passenger['SeatDetails'])) {

						foreach ($passenger['SeatDetails'] as $journeyIndex => $segments) {   // journey_index

							foreach ($segments as $segmentIndex => $legInfo) {               // segment_index

								$decoded = base64_decode($legInfo['SeatKey']);
								$data = unserialize($decoded);

								$seat_data = array(
									'flight_booking_details_id' => $flight_booking_details_id['insert_id'],
									'passenger_id'              => $passenger_id,
									'journey_index'             => $journey_segment_map[$legInfo['Origin'] . '-' . $legInfo['Destination']]['journey_index'],       // SAME as itinerary table
									'segment_index'             => $journey_segment_map[$legInfo['Origin'] . '-' . $legInfo['Destination']]['segment_index'],       // SAME as itinerary table
									'origin'                    => $legInfo['Origin'],
									'destination'               => $legInfo['Destination'],
									'seat_id'                   => $legInfo['SeatId'],
									'seat_key'                  => $legInfo['SeatKey'] ?? '',
									'price'                     => $data[$segmentIndex]['Price'] ?? ''
								);

								$this->CI->custom_db->insert_record('flight_seat_details', $seat_data);
							}
						}
					}
					//	Insert Seat data..ends

					if ($this->CI->db->table_exists('flight_meal_details')) {
						foreach ($this->flatten_passenger_ancillary_field($passenger['MealId'] ?? null) as $meal_row) {
							$o = strtoupper(trim((string) ($meal_row['Origin'] ?? '')));
							$d = strtoupper(trim((string) ($meal_row['Destination'] ?? '')));
							$key = $o . '-' . $d;
							if ($o === '' || $d === '' || !isset($journey_segment_map[$key])) {
								continue;
							}
							$anc_code = trim((string) ($meal_row['Code'] ?? ''));
							$title = trim((string) ($meal_row['Name'] ?? $meal_row['Description'] ?? ''));
							$meal_desc = ($anc_code !== '' ? '[' . $anc_code . '] ' : '') . ($title !== '' ? $title : $anc_code);
							$meal_data = array(
								'flight_booking_details_id' => $flight_booking_details_id['insert_id'],
								'passenger_id' => $passenger_id,
								'journey_index' => $journey_segment_map[$key]['journey_index'],
								'segment_index' => $journey_segment_map[$key]['segment_index'],
								'origin' => $o,
								'destination' => $d,
								'description' => substr($meal_desc, 0, 512),
								'price' => round(floatval($meal_row['Price'] ?? $meal_row['TotalPrice'] ?? 0), 2),
							);
							$this->CI->custom_db->insert_record('flight_meal_details', $meal_data);
						}
					}

					if ($this->CI->db->table_exists('flight_baggage_details')) {
						foreach ($this->flatten_passenger_ancillary_field($passenger['BaggageId'] ?? null) as $bag_row) {
							$o = strtoupper(trim((string) ($bag_row['Origin'] ?? '')));
							$d = strtoupper(trim((string) ($bag_row['Destination'] ?? '')));
							$key = $o . '-' . $d;
							if ($o === '' || $d === '' || !isset($journey_segment_map[$key])) {
								continue;
							}
							$anc_code = trim((string) ($bag_row['Code'] ?? ''));
							$title = trim((string) ($bag_row['Name'] ?? $bag_row['Description'] ?? ''));
							$bag_desc = ($anc_code !== '' ? '[' . $anc_code . '] ' : '') . ($title !== '' ? $title : $anc_code);
							$bag_data = array(
								'flight_booking_details_id' => $flight_booking_details_id['insert_id'],
								'passenger_id' => $passenger_id,
								'journey_index' => $journey_segment_map[$key]['journey_index'],
								'segment_index' => $journey_segment_map[$key]['segment_index'],
								'origin' => $o,
								'destination' => $d,
								'description' => substr($bag_desc, 0, 512),
								'price' => round(floatval($bag_row['Price'] ?? $bag_row['TotalPrice'] ?? 0), 2),
							);
							$this->CI->custom_db->insert_record('flight_baggage_details', $bag_data);
						}
					}

					$isLeadPax = false;
				}
			}

			// Persist insurance selection snapshot in dedicated phase-1 tables.
			if ($buy_insurance && !empty($insurance_validation['status']) && !empty($insurance_validation['data'])) {
				$insurance_data = $insurance_validation['data'];
				$snapshot_reference = 'INS-SNAP-' . time() . '-' . mt_rand(1000, 9999);
				$selection_snapshot = array(
					'InsuranceSelection' => $insurance_selection,
					'validation' => $insurance_data,
					'pricing' => array(
						'base_premium' => $insurance_base_amount,
						'tax_amount' => $insurance_tax_amount,
						'total_premium' => $insurance_total,
						'currency' => $insurance_data['pricing']['currency'] ?? $currency,
					),
					'created_at' => date('Y-m-d H:i:s'),
				);

				$extra_service_order_id = null;
				if ($this->CI->db->table_exists('flight_extra_service_order')) {
					$order_insert = $this->CI->custom_db->insert_record('flight_extra_service_order', array(
						'flight_booking_details_id' => $flight_booking_details_id['insert_id'],
						'service_type' => 'insurance',
						'service_status' => 'SELECTED',
						'total_amount' => $insurance_total,
						'currency' => $insurance_data['pricing']['currency'] ?? $currency,
						'provider_type' => $insurance_data['provider_mode'] ?? 'internal_crs',
						'provider_reference' => $insurance_data['provider_code'] ?? null,
						'selection_snapshot_json' => json_encode($selection_snapshot, JSON_PARTIAL_OUTPUT_ON_ERROR),
						'created_by_id' => $this->CI->entity_user_id ?? 0,
					));
					$extra_service_order_id = $order_insert['insert_id'] ?? null;
				}

				if ($this->CI->db->table_exists('flight_insurance_policy')) {
					$policy_insert = $this->CI->custom_db->insert_record('flight_insurance_policy', array(
						'flight_booking_details_id' => $flight_booking_details_id['insert_id'],
						'extra_service_order_id' => $extra_service_order_id,
						'provider_id' => intval($insurance_data['provider_id'] ?? 0),
						'plan_id' => intval($insurance_data['plan_id'] ?? 0),
						'snapshot_reference' => $snapshot_reference,
						'policy_status' => 'PREBOOKED',
						'premium_amount' => $insurance_base_amount,
						'tax_amount' => $insurance_tax_amount,
						'total_amount' => $insurance_total,
						'currency' => $insurance_data['pricing']['currency'] ?? $currency,
						'coverage_start_datetime' => !empty($startDate) ? ($startDate . ' 00:00:00') : null,
						'coverage_end_datetime' => !empty($endDate) ? ($endDate . ' 23:59:59') : null,
						'issuer_response_json' => json_encode(array('prebook' => true), JSON_PARTIAL_OUTPUT_ON_ERROR),
					));
					$policy_id = $policy_insert['insert_id'] ?? null;

					if ($policy_id && $this->CI->db->table_exists('flight_insurance_policy_traveller')) {
						$all_passenger_ids = array_column($inserted_passenger_rows, 'id');
						$passenger_map_by_key = array();
						$passenger_map_by_id = array();
						foreach ($inserted_passenger_rows as $ipr) {
							$passenger_map_by_key[$ipr['client_key']] = $ipr;
							$passenger_map_by_id[$ipr['id']] = $ipr;
						}

						$covered_requested = (isset($insurance_selection['covered_passengers']) && is_array($insurance_selection['covered_passengers']))
							? $insurance_selection['covered_passengers']
							: array();
						$covered_resolved_ids = array();
						foreach ($covered_requested as $covered_ref) {
							if (is_numeric($covered_ref)) {
								$covered_id = intval($covered_ref);
								if (isset($passenger_map_by_id[$covered_id])) {
									$covered_resolved_ids[] = $covered_id;
								}
								continue;
							}
							$ref_key = strtoupper(trim((string)$covered_ref));
							if (isset($passenger_map_by_key[$ref_key])) {
								$covered_resolved_ids[] = intval($passenger_map_by_key[$ref_key]['id']);
							}
						}
						$covered_resolved_ids = array_values(array_unique($covered_resolved_ids));
						if (empty($covered_resolved_ids)) {
							$covered_resolved_ids = $all_passenger_ids;
						}
						if (empty($insurance_data['allow_partial_traveller'])) {
							$covered_resolved_ids = $all_passenger_ids;
						}

						foreach ($covered_resolved_ids as $covered_pax_id) {
							if (!isset($passenger_map_by_id[$covered_pax_id])) {
								continue;
							}
							$pax_info = $passenger_map_by_id[$covered_pax_id];
							$this->CI->custom_db->insert_record('flight_insurance_policy_traveller', array(
								'policy_id' => $policy_id,
								'flight_passenger_id' => $covered_pax_id,
								'passenger_type' => $pax_info['passenger_type'],
								'traveller_name_snapshot' => $pax_info['name'],
								'dob_snapshot' => $pax_info['dob'],
								'coverage_amount' => 0,
								'status' => 'ACTIVE',
							));
						}
					}
				}
			}

			// Passenger map (client_key -> inserted pax row), used by addons for pax attribution.
			$passenger_map_by_key = array();
			if (!empty($inserted_passenger_rows) && is_array($inserted_passenger_rows)) {
				foreach ($inserted_passenger_rows as $ipr) {
					if (!empty($ipr['client_key'])) {
						$passenger_map_by_key[strtoupper(trim((string) $ipr['client_key']))] = $ipr;
					}
				}
			}

			// Persist airport cab selection (Mozio search snapshot only — no cab booking).
			if (!empty($airport_cab_validation['status']) && !empty($airport_cab_validation['data'])) {
				$cab_data = $airport_cab_validation['data'];
				$search_snapshot = $cab_data['search_meta'] ?? array();
				if (!empty($airport_cab_selection['search'])) {
					$search_snapshot = $airport_cab_selection['search'];
				}
				$snapshot = array(
					'AirportCabSelection' => $airport_cab_selection,
					'cab' => $cab_data['car'] ?? array(),
					'search' => $search_snapshot,
					'direction' => $cab_data['direction'] ?? ($airport_cab_selection['direction'] ?? ''),
					'pricing' => array(
						'total' => round(floatval($cab_data['amount'] ?? 0), 2),
						'currency' => $cab_data['currency'] ?? $currency,
					),
					'created_at' => date('Y-m-d H:i:s'),
				);

				if ($this->CI->db->table_exists('flight_extra_service_order')) {
					$this->CI->custom_db->insert_record('flight_extra_service_order', array(
						'flight_booking_details_id' => $flight_booking_details_id['insert_id'],
						'service_type' => 'airport_cab',
						'service_status' => 'SELECTED',
						'total_amount' => round(floatval($cab_data['amount'] ?? 0), 2),
						'currency' => $cab_data['currency'] ?? $currency,
						'provider_type' => 'mozio',
						'provider_reference' => 'flight_airport_cab',
						'selection_snapshot_json' => json_encode($snapshot, JSON_PARTIAL_OUTPUT_ON_ERROR),
						'created_by_id' => $this->CI->entity_user_id ?? 0,
					));
				}
			}

			// Persist airport addon selections (Meet & Assist, Lounge, Wheelchair).
			if (!empty($airport_services_validation['status']) && !empty($airport_services_validation['data']['items'])) {
				// Map offer_token -> passenger_ref (client key like ADT_1) if provided by frontend.
				$offer_ref_map = array();
				$airport_service_items_raw = isset($airport_services_selection['selections']) && is_array($airport_services_selection['selections'])
					? $airport_services_selection['selections']
					: array();
				foreach ($airport_service_items_raw as $sel) {
					$token = trim((string)($sel['offer_token'] ?? ''));
					$pref = $this->normalize_frontend_passenger_ref(
						$sel['passenger_ref'] ?? '',
						$passenger_map_by_key
					);
					if ($token !== '' && $pref !== '') {
						$offer_ref_map[$token] = $pref;
					}
				}

				$items_by_service = array();
				foreach ($airport_services_validation['data']['items'] as $priced_item) {
					$offer = $priced_item['offer'] ?? array();
					$stype = trim((string)($offer['service_type'] ?? 'meet_assist'));
					if (!isset($items_by_service[$stype])) {
						$items_by_service[$stype] = array();
					}
					$items_by_service[$stype][] = $priced_item;
				}

				foreach ($items_by_service as $service_type => $service_items) {
					$service_total = 0;
					foreach ($service_items as $si) {
						$service_total += floatval($si['amount'] ?? 0);
					}
					$snapshot = array(
						'AirportServicesSelection' => $airport_services_selection,
						'selections' => array_map(function ($si) use ($offer_ref_map, $passenger_map_by_key) {
							$offer = $si['offer'] ?? array();
							$token = trim((string)($offer['offer_token'] ?? ''));
							$pref = $token !== '' && isset($offer_ref_map[$token]) ? $offer_ref_map[$token] : '';
							$frontend_pref = '';
							if ($token !== '' && isset($airport_service_items_raw)) {
								foreach ($airport_service_items_raw as $raw_sel) {
									if (trim((string)($raw_sel['offer_token'] ?? '')) === $token) {
										$frontend_pref = strtoupper(trim((string)($raw_sel['passenger_ref'] ?? '')));
										break;
									}
								}
							}
							$pax_id = null;
							$lookup_pref = strtoupper(trim((string)$pref));
							if ($lookup_pref !== '' && isset($passenger_map_by_key[$lookup_pref])) {
								$pax_id = intval($passenger_map_by_key[$lookup_pref]['id']);
							}
							return array(
								'offer' => $offer,
								'amount' => floatval($si['amount'] ?? 0),
								'passenger_ref' => $pref,
								'frontend_passenger_ref' => $frontend_pref,
								'passenger_id' => $pax_id,
							);
						}, $service_items),
						'pricing' => array(
							'total' => round($service_total, 2),
							'currency' => $airport_services_validation['data']['currency'] ?? $currency,
						),
						'created_at' => date('Y-m-d H:i:s'),
					);

					if ($this->CI->db->table_exists('flight_extra_service_order')) {
						$order_insert = $this->CI->custom_db->insert_record('flight_extra_service_order', array(
							'flight_booking_details_id' => $flight_booking_details_id['insert_id'],
							'service_type' => $service_type,
							'service_status' => 'SELECTED',
							'total_amount' => round($service_total, 2),
							'currency' => $airport_services_validation['data']['currency'] ?? $currency,
							'provider_type' => 'internal_crs',
							'provider_reference' => 'flight_addon_catalog',
							'selection_snapshot_json' => json_encode($snapshot, JSON_PARTIAL_OUTPUT_ON_ERROR),
							'created_by_id' => $this->CI->entity_user_id ?? 0,
						));
						$order_id = $order_insert['insert_id'] ?? null;

						if ($order_id && $this->CI->db->table_exists('flight_extra_service_order_item')) {
							foreach ($service_items as $si) {
								$offer = $si['offer'] ?? array();
								$token = trim((string)($offer['offer_token'] ?? ''));
								$pref = $token !== '' && isset($offer_ref_map[$token]) ? $offer_ref_map[$token] : '';
								$pax_id = null;
								$lookup_pref = strtoupper(trim((string)$pref));
								if ($lookup_pref !== '' && isset($passenger_map_by_key[$lookup_pref])) {
									$pax_id = intval($passenger_map_by_key[$lookup_pref]['id']);
								}
								$this->CI->custom_db->insert_record('flight_extra_service_order_item', array(
									'order_id' => $order_id,
									'passenger_id' => $pax_id,
									'journey_index' => intval($offer['journey_index'] ?? 0),
									'segment_index' => intval($offer['segment_index'] ?? 0),
									'item_code' => trim((string)($offer['catalog_id'] ?? '')),
									'item_name' => trim((string)($offer['display_name'] ?? '')),
									'amount' => floatval($si['amount'] ?? 0),
									'currency' => trim((string)($si['currency'] ?? $currency)),
									'attributes_json' => json_encode($offer, JSON_PARTIAL_OUTPUT_ON_ERROR),
								));
							}
						}
					}
				}
			}

			$transaction_details = array();
			$transaction_details['flight_booking_details_id'] = $flight_booking_details_id['insert_id'];
			$transaction_details['basic_fare'] = $base_fare;
			$transaction_details['airline_tax'] = $taxes;
			$transaction_details['total_fare'] = $totalFare = $base_fare + $taxes;
			$transaction_details['admin_commission'] = 0;
			$transaction_details['admin_tds'] = 0;
			$transaction_details['admin_markup'] = $admin_markup;
			$transaction_details['admin_markup_tax'] = $admin_markup_tax;
			$transaction_details['agent_markup']     = $agent_markup;
			$transaction_details['agent_markup_tax'] = $agent_markup_tax;
			$transaction_details['admin_discount'] = $admin_discount;
			$transaction_details['admin_discount_type'] = $admin_discount_type;
			$transaction_details['admin_discount_value'] = $admin_discount_value;
			$transaction_details['promocode'] = $applied_promo_code;
			$transaction_details['promocode_type'] = $promocode_type;
			$transaction_details['promocode_value'] = $promocode_value;
			$transaction_details['discount_amount'] = $promocode_discount;
			$transaction_details['loyalty_points'] = $redeem_response['redeem_points'] ?? 0;
			$transaction_details['loyalty_amount'] = $loyalty_redeem_amount;
			$transaction_details['convenience_fee_type'] = $convinence_type;
			$transaction_details['convenience_fee_value'] = $convinence_value;
			$transaction_details['convenience_fee_amount'] = $convinence_amount;
			// $transaction_details['pax_wise_fare_breakdown'] = json_encode($fare_data['PassengerBreakup'] ?? array());
			$transaction_details['pax_wise_fare_breakdown'] = json_encode(
				$fare_data['PassengerBreakup'],
				JSON_PARTIAL_OUTPUT_ON_ERROR
			);

			$transaction_details['currency'] = $this->CI->app_currency;
			$currency_obj = new master_currency(array(
				'module_type' => 'flight',
				'to' => $this->CI->app_currency,
				'from' => admin_base_currency()
			));
			$transaction_details['currency_conversion_rate'] = $currency_obj->getConversionRate() ?? 1;

			$this->CI->custom_db->insert_record('flight_booking_transaction_details', $transaction_details);

			$loyalty_txn = null;
			if (!empty($redeem_response) && !empty($redeem_response['applied'])) {
				$loyalty_txn = $this->CI->loyalty->applyRedemption([
					'user_id'		=> (int)($this->CI->entity_user_id ?? 0),
					'module_type'	=> META_AIRLINE_COURSE,
					'booking_id'	=> $flight_booking_details_id['insert_id'],
					'reference_id'	=> $app_reference . '-REDEEM',
					'redeem_points'	=> (int)($redeem_response['redeem_points'] ?? 0),
					'redeem_amount'	=> (float)($redeem_response['redeem_amount'] ?? 0),
					'redeem_currency' => $redeem_response['redeem_currency'] ?? $currency,
					'base_currency'	=> $redeem_response['redeem_currency'] ?? $currency, // FIXME: We have to get base currency from loyalty configuration
					'redeem_conversion_rate' => $redeem_response['redeem_conversion_rate'] ?? 1,
					'description'	=> 'Points redeemed for flight booking',
				]);
				if (! empty($loyalty_txn['id'])) {
					$attributes['loyalty_redeem']['transaction_id'] = $loyalty_txn['id'];
				}
				if (! empty($attributes)) {
					$this->CI->custom_db->update_record(
						'flight_booking_details',
						['attributes' => json_encode($attributes)],
						['id' => $flight_booking_details_id['insert_id']]
					);
				}
			}
			// Create payment record
			$firstname = $leadPassenger['FirstName'] . ' ' . $leadPassenger['LastName'];
			$pg_record = $this->CI->transaction->create_payment_record($app_reference, $pg_code, $currency, $final_total_fare, $final_total_fare, $firstname, $email, $phone_number, $productinfo);

			if ($pg_record['status'] == true) {
				$response['ResultToken'] = $tokenDataResponse['data']['ResultToken'];
				$response['app_reference'] = $app_reference;
				$response['transaction_id'] = $pg_record['transaction_id'];
				$response['payment_url'] = base_url() . 'index.php/payment_gateway/payment/' . $pg_record['transaction_id'];
				if ($buy_insurance && !empty($insurance_validation['status']) && !empty($insurance_validation['data'])) {
					$response['InsurancePreview'] = array(
						'plan_code' => $insurance_validation['data']['plan_code'] ?? '',
						'plan_name' => $insurance_validation['data']['plan_name'] ?? '',
						'premium' => $insurance_base_amount,
						'tax' => $insurance_tax_amount,
						'total' => $insurance_total,
						'currency' => $insurance_validation['data']['pricing']['currency'] ?? $currency,
					);
				}
			}
		}
		// debug($response);die;
		return $response;
	}


	/**
	 * Hold Ticket
	 * @param unknown_type $request 
	 */
	public function hold_ticket($request)
	{ 

		$booking_response = array();
		$booking_response['status'] = FAILURE_STATUS;
		$booking_response['message'] = '';
		$booking_response['data'] = array();
		$ResultToken = unserialized_data(trim($request['ResultToken']));

		if ($ResultToken) {

			$booking_source = $ResultToken['booking_source'];

			$active_booking_source_condition = array();
			$active_booking_source_condition[] = array('BS.source_id', '=', '"' . $booking_source . '"');
			$flight_active_booking_sources = $this->flight_active_booking_sources($active_booking_source_condition);

			//Authenticate the API's
			$this->api_authentication($flight_active_booking_sources);

			$flight_obj_ref = load_web_flight_lib($booking_source);

			$booking_data = $this->CI->$flight_obj_ref->getBookingData($ResultToken['token']);
			
			

			if (!$booking_data) {
				$response = [
					'status' => false,
					'message' => 'Invalid Request'
				];
			} else {
				$book_id = $booking_data['app_reference'];

				$this->CI->load->model('transaction');
				$payment_status = $this->CI->transaction->is_payment_accepted_for_booking($book_id);
				$update_condition['app_reference'] = $book_id;
				$booking_details = $this->CI->custom_db->single_table_records('flight_booking_details', 'id,status', $update_condition);

				$this->CI->load->library('Loyalty/loyalty');
				$loyalty_txn_id = $this->CI->loyalty->getLoyaltyTransactionId($booking_details['data'][0], META_AIRLINE_COURSE);

				$loyalty_txn_status = 'FAILED';
				$should_commit_loyalty = false;
				
				if ($payment_status == false) {
					$booking_response = [
						'status' => false,
						'message' => 'Payment Not Completed'
					];
					$should_commit_loyalty = true;
				} elseif (@$booking_details['data'][0]['status'] != 'Started') {
					$booking_response = [
						'status' => false,
						'message' => 'Invalid Request'
					];
					$should_commit_loyalty = false;
				} else {
					$should_commit_loyalty = true;
					$process_booking_response = $this->CI->$flight_obj_ref->hold_ticket($ResultToken['token']);
				
					
					if ($process_booking_response['status'] == true && !empty($process_booking_response['data'])) {
						$booking_data = $process_booking_response['data'];
						$app_reference = $booking_data['app_reference'] ?? null;

						if ($app_reference) {
							// Extract booking information
							$gdspnr = $booking_data['gdspnr'] ?? $booking_data['GDSPNR'] ?? $booking_data['BookingId'] ?? null;
							$bookingId = $booking_data['bookingId'] ?? $booking_data['BookingId'] ?? $gdspnr;
							$hold_time = $booking_data['hold_time'] ?? null;

							// Determine status based on GDS PNR
							$booking_status = 'Failed';
							if (!empty($gdspnr)) {
								$booking_status = 'Confirmed';
								$loyalty_txn_status = 'SUCCESS';
							}

							// Prepare update data
							$update_data = array();
							$update_data['status'] = $booking_status;

							if (!empty($gdspnr)) {
								$update_data['gdspnr'] = $gdspnr;
							}

							if (!empty($bookingId)) {
								$update_data['bookingId'] = $bookingId;
							}

							if (!empty($hold_time)) {
								// Convert hold_time to DATETIME format
								// hold_time might be in different formats, try to parse it
								$last_ticket_time = null;
								if (is_string($hold_time)) {
									// Try to parse the date string
									$parsed_time = strtotime($hold_time);
									if ($parsed_time !== false) {
										$last_ticket_time = date('Y-m-d H:i:s', $parsed_time);
									} else {
										// If parsing fails, try to extract date from various formats
										// Travelport typically returns ISO 8601 format
										$last_ticket_time = date('Y-m-d H:i:s', strtotime($hold_time));
									}
								} elseif (is_numeric($hold_time)) {
									// If it's a timestamp
									$last_ticket_time = date('Y-m-d H:i:s', $hold_time);
								}

								if ($last_ticket_time) {
									$update_data['last_ticket_time'] = $last_ticket_time;
								}
							}

							// Update condition
							$update_condition = array();
							$update_condition['app_reference'] = $app_reference;

							// Update flight_booking_details
							$update_result = $this->CI->custom_db->update_record('flight_booking_details', $update_data, $update_condition);

							if ($update_result) {
								$booking_response['status'] = SUCCESS_STATUS;
								$booking_response['message'] = 'Booking successfull';
								$booking_response['data'] = array(
									'app_reference' => $app_reference,
									'status' => $booking_status,
								);

								if ($booking_status === 'Confirmed' && is_active_insurance_module()) {
									$flight_booking_id = intval($booking_details['data'][0]['id'] ?? 0);
									if ($flight_booking_id > 0 && $this->CI->db->table_exists('flight_insurance_policy')) {
										$this->CI->load->model('insurance_model');
										$issuance = $this->CI->insurance_model->trigger_policy_issuance_for_booking($flight_booking_id);
										if (!empty($issuance['status'])) {
											$booking_response['data']['Insurance'] = $issuance['data'];
										}
									}
								}

								if($this->CI->facts_config['status']){
									$a = $this->CI->facts->flightInvoice($app_reference, "b2c");
									
								}
								//auto issue ticket
								$issue_ticket_request['bookingId'] = $bookingId;
								$issue_ticket_request['booking_sourece'] = $booking_source;
	
								$flight_details_for_comission = $this->CI->flight_model->get_booking_data_for_guest($app_reference);

								$this->issue_ticket($issue_ticket_request, $booking_details['data'][0]['id']);		

							} else {
								$booking_response['status'] = FAILURE_STATUS;
								$booking_response['message'] = 'Failed';
								$booking_response['data'] = array(
									'app_reference' => $app_reference,
									'status' => $booking_status,
								);
							}
						} else {
							$booking_response['status'] = FAILURE_STATUS;
							$booking_response['message'] = 'App reference not found';
						}
					} else {
						// Handle failure cases
						$booking_response['status'] = FAILURE_STATUS;

						// Extract error message if available
						$error_message = 'Unknown error occurred during booking';
						if (isset($process_booking_response['message'])) {
							$error_message = $process_booking_response['message'];
						} elseif (isset($process_booking_response['data']['message'])) {
							$error_message = $process_booking_response['data']['message'];
						} elseif (empty($process_booking_response['data'])) {
							$error_message = 'No booking data received from API';
						} elseif (isset($process_booking_response['data']) && empty($process_booking_response['data']['gdspnr'])) {
							$error_message = 'GDS PNR not received from booking API';
						}

						$booking_response['message'] = $error_message;

						// Try to get app_reference from token data to update status to Failed
						$app_reference = null;
						if (isset($process_booking_response['data']['app_reference'])) {
							$app_reference = $process_booking_response['data']['app_reference'];
						} else {
							// Try to get from cached token
							$tokenData = json_decode($this->CI->redis_server->read_string($ResultToken['token']), true);
							$app_reference = $tokenData['app_reference'] ?? null;
						}

						if ($app_reference) {
							$update_data = array();
							$update_data['status'] = 'Failed';
							$update_condition = array();
							$update_condition['app_reference'] = $app_reference;
							$this->CI->custom_db->update_record('flight_booking_details', $update_data, $update_condition);

							$booking_response['data'] = array(
								'app_reference' => $app_reference,
								'status' => 'Failed',
							);
						}
					}
				}

				if (! empty($loyalty_txn_id) && $should_commit_loyalty) {
					$this->CI->load->library('Loyalty/loyalty');
					$this->CI->loyalty->commitRedemption([
						'transaction_id' => $loyalty_txn_id,
						'status' => $loyalty_txn_status,
						'user_id' => $this->CI->entity_user_id,
					]);
				}
			}
		} else {
			$booking_response['message'] = 'Invalid Request';
		}


		return $booking_response;
	}

	public function getCommission(array $flight)
	{

		/**  required flight array for comission
		*/
		// $flight = [
		// 	'airline'   => 'AI',          // REQUIRED (rule filter + condition)
		// 	'class'     => 'Y',           // REQUIRED (RBD)
		// 	'date'      => '2026-05-10',  // REQUIRED (date validation)
		
		// 	'departure' => 'DEL',         // from condition: departure airport
		// 	'arrival'   => 'BOM',         // from condition: arrival airport
		// 	'codeshare' => '0',           // "0" = No, "1" = Yes
		// 	'trip_type' => 'SITI',        // SITI / SOTO / SITO
		// ];
		return $this->CI->gds_commission->getCommission($flight);
	}

	function getBookingCommission($flight_booking_details_id)
	{
		$commissions = 0;

		$flight_booking_itinerary_details = $this->CI->custom_db->single_table_records('flight_booking_itinerary_details','id',['flight_booking_details_id' => $flight_booking_details_id,]);

		if($flight_booking_itinerary_details['status'] == true){
			$flight_booking_itinerary_details = $flight_booking_itinerary_details['data'];
			foreach($flight_booking_itinerary_details as $flight_booking_itinerary){

			}
		}

		return  $commissions;
	}

	/**
	 * Issue Ticket
	 * @param unknown_type $request
	 */
	public function issue_ticket($request, $flight_booking_details_id)
	{

		$ticketing_response = array();
		$ticketing_response['status'] = FAILURE_STATUS;
		$ticketing_response['message'] = '';
		$ticketing_response['TicketDetails'] = array();

		if ($request) {

			$booking_source = $request['booking_sourece'];


			$active_booking_source_condition = array();
			$active_booking_source_condition[] = array('BS.source_id', '=', '"' . $booking_source . '"');

			// debug($booking_source);die;
			$flight_active_booking_sources = $this->flight_active_booking_sources($active_booking_source_condition);

			//Authenticate the API's
			$this->api_authentication($flight_active_booking_sources);

			$flight_obj_ref = load_web_flight_lib($booking_source);


			$commission = $this->getBookingCommission($flight_booking_details_id);


			// debug($flight_obj_ref);die;
			$process_ticketing_response = $this->CI->$flight_obj_ref->issue_ticket($request['bookingId'], $commission);


			if ($process_ticketing_response['status'] == SUCCESS_STATUS) {
				//Get Flight Booking Details
				$ticketing_response['status'] = SUCCESS_STATUS;
				$ticketing_response['TicketDetails'] = $process_ticketing_response['data'];
			} else {
				@$ticketing_response['message'] = $process_ticketing_response['message'];
			}
		} else {
			$ticketing_response['message'] = 'Invalid IssueTicket Request';
		}


		if($ticketing_response['status'] == 1){

			$passengers = $ticketing_response['TicketDetails']['Passengers'];

			if(!empty($passengers)){

				$gender_map = [
					"M" => "Male",
					"F" => "Female"
				];

				$pax_type_map = [
					"ADT" => "Adult",
					"CHD" => "Child",
					"INF" => "Infant"
				];

				foreach ($passengers as $pax) {
					
					$first = $pax['FirstName'];
					$last  = $pax['LastName'];
					$dob =  $pax['DOB'];
					$gender = isset($gender_map[$pax['Gender']]) ? $gender_map[$pax['Gender']] : $pax['Gender'];
					$paxType = isset($pax_type_map[$pax['PaxType']]) ? $pax_type_map[$pax['PaxType']] : $pax['PaxType'];
			
					$match = $this->CI->custom_db->single_table_records(
						'flight_booking_passenger_details',
						'id',
						[
							'flight_booking_details_id' => $flight_booking_details_id,
							'first_name' => $first,
							'last_name'  => $last,
							'date_of_birth'     => $dob,
							'gender'            => $gender,
							'passenger_type'    => $paxType
						]
					);

		


					if ($match['status'] == 1) {
	
						$passenger_id = $match['data'][0]['id'];
							$insert_data = [
								'flight_booking_passenger_details_id' => $passenger_id,
								'journey_indicator'                  => 0, 
								'TicketId'                           => $pax['Tickets'][0]['TicketId'],
								'TicketNumber'                       => $pax['Tickets'][0]['TicketNumber'],
								'IssueDate'                          => $pax['Tickets'][0]['IssueDate'] 
							];

							$insert_response = $this->CI->custom_db->insert_record('flight_booking_ticket_info',$insert_data);

							if ($insert_response['status'] == 1) {

								$update_data = ['queued' => 0];
								$update_response = $this->CI->custom_db->update_record('flight_booking_details',$update_data,['id' => $flight_booking_details_id]);
								if ($update_response != 1) {
									$ticketing_response['status']  = FAILURE_STATUS; 
									$ticketing_response['message'] = "Passenger added but queue update failed!";
								}

								//Email send functionality
								$email_template = 'ticketing_confirmation_mail'; 
								$subject = domain_name() . ' - Your Flight Ticket Confirmation';

								$mail_data = [
									'cust_name' => $first. ' ' . $last, 
									'booking' => [
										'app_reference' => $app_reference ?? ' ',
										'email' => $email,
										'status' => 'CONFIRMED'
									],
									'TicketDetails' => $ticketing_response['TicketDetails']
								];
									
								$mail_template_data = [
										'page_data' => $mail_data,
										'email_template' =>	$email_template			
								];

								$this->CI->load->library('provab_mailer');
								$mail_template = $this->CI->template->isolated_view('email_templates/main', $mail_template_data);
								$mail_status = $this->CI->provab_mailer->send_mail($email, $subject, $mail_template);


								if (empty($mail_status['status']) || $mail_status['status'] !== true) {
									// Ticketing success but email failed
									$ticketing_response['status']  = SUCCESS_STATUS;
									$ticketing_response['message'] = "Ticket Generated Successfully but Email Sending Failed.";
								}

								$ticketing_response['status']  = SUCCESS_STATUS;
								$ticketing_response['message'] = "Ticket Generated & Email Sent Successfully!";

							} else {
								$ticketing_response['status'] = FAILURE_STATUS;
								$ticketing_response['message'] = "Ticketing Failed !!";
							}
					}else {
						$ticketing_response['status'] = FAILURE_STATUS;
						$ticketing_response['message'] = "Ticketing Failed !!";
					}

				}
			}else {
				$ticketing_response['status'] = FAILURE_STATUS;
				$ticketing_response['message'] = "Issue ticket Not Found !!";
			}
		}else{
			$ticketing_response['status'] = FAILURE_STATUS;
			$ticketing_response['message'] = "Ticketing Failed From Api";
		}

		return $ticketing_response;
	}


	function get_flight_discount($flight, $amount)
	{
		$endJourny = $flight['FlightDetails'][count($flight['FlightDetails']) - 1];

		$flight_discount_param = [];
		$flight_discount_param['airline'] = $flight['FlightDetails'][0][0]['OperatorCode'];
		$flight_discount_param['origin'] = $flight['FlightDetails'][0][0]['Origin']['AirportCode'];
		$flight_discount_param['destination'] = $endJourny[count($endJourny) - 1]['Destination']['AirportCode'];
		$flight_discount_param['cabinClass'] = $flight['FlightDetails'][0][0]['CabinClass'];
		$flight_discount_param['travelDate'] = $flight['FlightDetails'][0][0]['Origin']['date'];


		$discount_data = $this->CI->flight_discount->getDiscountAmountForFlight($flight_discount_param, $amount);
		unset($discount_data['name']);
		return $discount_data;
	}

	function set_flight_markup(&$flight, $return_markup = false, $markup_amount = NULL)
	{
		$endJourny = $flight['FlightDetails'][count($flight['FlightDetails']) - 1];

		$flight_markup_param = [];
		$flight_markup_param['airline']     = $flight['FlightDetails'][0][0]['OperatorCode'];
		$flight_markup_param['origin']      = $flight['FlightDetails'][0][0]['Origin']['AirportCode'];
		$flight_markup_param['destination'] = $endJourny[count($endJourny) - 1]['Destination']['AirportCode'];
		$flight_markup_param['cabinClass']  = $flight['FlightDetails'][0][0]['CabinClass'];
		$flight_markup_param['travelDate']  = $flight['FlightDetails'][0][0]['Origin']['date'];

		$amount = $flight['Price']['TotalDisplayFare'];
		$user_type = $this->CI->entity_user_type;
		// -- Get the markup price --
		$is_b2b_user = ($user_type == B2B_USER);
		$supervision_user = is_supervision_user();

		/** For B2B bookings we have TWO markups:
		 *  - Admin markup (company commission): included in payable totals
		 *  - Agent markup (agent margin): recorded only, must NOT be collected via payment
		 */

		// Admin markup: allow override via $markup_amount (supervision/testing flow)
		if ($markup_amount === NULL) {
			if ($is_b2b_user) {
				// get b2b markup setup by admin as AdminMarkup. Table to refer: b2b_markup_flight
				$admin_markup_data = $this->CI->b2b_markup->getMarkupAmountForFlight($flight_markup_param, $amount, true);
			} else {
				$admin_markup_data = $this->CI->markup->getMarkupAmountForFlight($flight_markup_param, $amount);
			}
		} else {
			$admin_markup_data = [
				'amount' => $markup_amount,
				'isPercentage' => false,
			];
		}

		$agent_markup_data = ['amount' => 0, 'isPercentage' => false, 'isPerPax' => false];
		if ($is_b2b_user) {
			// get agent markup setup by agent user. Table to refer: b2b_flight_markup
			$agent_markup_data = $this->CI->b2b_markup->getMarkupAmountForFlight($flight_markup_param, $amount);
		}

		// -- Get pax count --
		$pax_cnt = 0;
		$pax_data = $flight['Price']['PassengerBreakup'] ?? []; // returns array of ADT, CHD and INF
		foreach ($pax_data as $pax_entity) {
			$pax_cnt += (int) ($pax_entity['PassengerCount'] ?? 0);
		}
		if ($pax_cnt <= 0) {
			$pax_cnt = 1;
		}

		// Normalize per-pax flags separately
		if (isset($agent_markup_data['isPerPax']) && ($agent_markup_data['isPerPax'] == 1)) {
			$agent_markup_data['amount'] = round($agent_markup_data['amount'] * $pax_cnt, 2);
		}

		// Record agent markup for B2B (never add to Tax/TotalDisplayFare)
		if ($is_b2b_user) {
			$flight['Price']['PriceBreakup']['AgentMarkup'] = (float) ($agent_markup_data['amount'] ?? 0);
		}

		// Apply admin markup into passenger breakup + totals (payable)
		$admin_markup_amount  = (float) ($admin_markup_data['amount'] ?? 0);
		$per_pax_admin_markup = round($admin_markup_amount / $pax_cnt, 2);
		$per_pax_agent_markup = round($agent_markup_data['amount'] / $pax_cnt, 2);
		foreach ($flight['Price']['PassengerBreakup'] as &$pax_entity) {
			$pax_entity['Tax'] += $per_pax_admin_markup;
			$pax_entity['Tax'] += $per_pax_agent_markup;
			$pax_entity['Tax'] = round($pax_entity['Tax'], 2);
			$pax_entity['TotalPrice'] = $pax_entity['BasePrice'] + $pax_entity['Tax'];
		}

		if ($supervision_user || $return_markup) {
			$flight['Price']['PriceBreakup']['AdminMarkup'] = $admin_markup_amount;
		}
		$tax = $flight['Price']['PriceBreakup']['Tax'];
		$flight['Price']['PriceBreakup']['Tax'] = round((float) $tax + $admin_markup_amount + $agent_markup_data['amount'], 2);
		$flight['Price']['TotalDisplayFare']    = round($flight['Price']['PriceBreakup']['BasicFare'] + $flight['Price']['PriceBreakup']['Tax'], 2);
	}

	/**
	 * ExtraServiceDetails.Meals / Baggage are segment-indexed lists of option rows.
	 *
	 * @param mixed $matrix
	 * @return bool
	 */
	private function extra_service_segment_matrix_has_rows($matrix)
	{
		if (!is_array($matrix) || empty($matrix)) {
			return false;
		}
		foreach ($matrix as $segment) {
			if (is_array($segment) && !empty($segment)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize MealId / BaggageId from API (flat list or nested per segment).
	 *
	 * @param mixed $raw
	 * @return array<int,array>
	 */
	private function flatten_passenger_ancillary_field($raw)
	{
		if (empty($raw) || !is_array($raw)) {
			return array();
		}
		if (!empty($raw['CatalogOfferingId']) || !empty($raw['ProductId']) || !empty($raw['CatalogOfferingUuid']) || !empty($raw['ProductUuid'])) {
			return array($raw);
		}
		$out = array();
		foreach ($raw as $item) {
			if (!is_array($item)) {
				continue;
			}
			if (!empty($item['CatalogOfferingId']) || !empty($item['ProductId']) || !empty($item['CatalogOfferingUuid']) || !empty($item['ProductUuid'])) {
				$out[] = $item;
				continue;
			}
			foreach ($item as $inner) {
				if (is_array($inner) && (!empty($inner['CatalogOfferingId']) || !empty($inner['ProductId']) || !empty($inner['CatalogOfferingUuid']) || !empty($inner['ProductUuid']))) {
					$out[] = $inner;
				}
			}
		}

		return $out;
	}

	/**
	 * Build a lightweight context object for insurance pricing/eligibility.
	 * Works even if fare quote retrieval fails.
	 *
	 * @param array $result_token
	 * @param string $flight_obj_ref
	 * @return array
	 */
	/**
	 * Build addon context from booking request + redis token (never calls supplier libraries).
	 */
	private function build_universal_addon_context_from_request($result_token, $request = array())
	{
		$token_cache = $this->read_flight_token_cache($result_token['token'] ?? '');
		try {
			$this->CI->load->model('flight_addon_model');
			return $this->CI->flight_addon_model->build_universal_addon_context($request, $token_cache);
		} catch (Throwable $e) {
			log_message('error', 'Universal addon context build failed: ' . $e->getMessage());
			return array(
				'insurance' => array(
					'domain_origin' => get_domain_auth_id(),
					'fare_amount' => floatval($request['FareAmount'] ?? 0),
					'currency' => trim((string)($request['Currency'] ?? admin_base_currency())),
					'trip_scope' => 'both',
					'trip_type' => 'Any',
					'travel_date' => date('Y-m-d'),
					'pax_count' => 1,
				),
				'airport' => array(
					'domain_origin' => get_domain_auth_id(),
					'currency' => trim((string)($request['Currency'] ?? admin_base_currency())),
					'segments' => array(),
					'trip_type' => 'OneWay',
				),
			);
		}
	}

	/** @deprecated Use build_universal_addon_context_from_request() */
	private function build_insurance_context_from_token($result_token, $flight_obj_ref, $request = array())
	{
		unset($flight_obj_ref);
		$built = $this->build_universal_addon_context_from_request($result_token, $request);
		return $built['insurance'] ?? array();
	}

	/** @deprecated Use build_universal_addon_context_from_request() */
	private function build_flight_addon_context_from_token($result_token, $flight_obj_ref, $request = array())
	{
		unset($flight_obj_ref);
		$built = $this->build_universal_addon_context_from_request($result_token, $request);
		return $built['airport'] ?? array();
	}

	/**
	 * Read cached flight token payload (shared across booking sources).
	 */
	private function read_flight_token_cache($token_key)
	{
		$token_key = trim((string)$token_key);
		if ($token_key === '' || !isset($this->CI->redis_server)) {
			return array();
		}

		$raw = $this->CI->redis_server->read_string($token_key);
		if ($raw === false || $raw === null || $raw === '') {
			return array();
		}

		$data = json_decode($raw, true);
		return is_array($data) ? $data : array();
	}

	/**
	 * Map frontend passenger keys (ADT_0, ADT_1) to PreBook client keys (ADT_1, ADT_2).
	 */
	private function normalize_frontend_passenger_ref($ref, $passenger_map_by_key = array())
	{
		$ref = strtoupper(trim((string) $ref));
		if ($ref === '' || !is_array($passenger_map_by_key) || empty($passenger_map_by_key)) {
			return $ref;
		}
		if (isset($passenger_map_by_key[$ref])) {
			return $ref;
		}
		if (preg_match('/^(ADT|CHD|INF)_(\d+)$/', $ref, $matches)) {
			$candidate = $matches[1] . '_' . (intval($matches[2]) + 1);
			if (isset($passenger_map_by_key[$candidate])) {
				return $candidate;
			}
		}
		return $ref;
	}

}
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
emt.php
<?php 

class Easemytrip
{
    protected $config;
    protected $config_currency;
    protected $currency_obj;
    protected $booking_source;
    protected $booking_source_name;
    protected $booking_source_system;
    protected $airport_list;
    protected $airline_list;
    protected $CI;
    protected $search_dataa = [];

    function __construct()
    {
        $this->CI = &get_instance(); 
        $this->CI->load->model('api_model'); 
        $module = META_AIRLINE_COURSE;
        $api = EASE_MY_TRIP_BOOKING_SOURCE; 
        $c = $this->CI->api_model->active_api_config($module, $api); 
        if ($c != false && empty($c['config']) == false) {
            $this->config = json_decode($c['config'], true);
            $this->config_currency = $c['currency'];
            $this->booking_source = $api;
            $this->booking_source_name = $c['remarks'];
            $this->booking_source_system = $c['system'];
        }

        $domain_base_currency = domain_base_currency();
        $this->currency_obj = new master_currency(['from' => $this->config_currency, 'to' => $domain_base_currency]); 
        $this->airport_list = [];
        $this->airline_list = [];

        $this->CI->load->library('CurlMultiHandler');
    }

    private function insert_cache_record($key, $value)
    {
        $index = $this->CI->redis_server->store_string($key, json_encode($value));
        return [
            'access_key' => $key . DB_SAFE_SEPARATOR . $index . DB_SAFE_SEPARATOR . random_string() . random_string(),
            'index' => $index,
        ];
    }

    private function read_cache_record($key)
    {
        return json_decode($this->CI->redis_server->read_string($key), true);
    }

    private function get_flight_result_token($token)
    {
        $token = [
            'booking_source' => $this->booking_source,
            'token' => $token,
            'time' => time(),
        ];

        return serialized_data($token);
    } 
    private function get_request($json, $remarks = '', $get_request = "")
    { 
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        // Default base url
        $base_url = rtrim($this->config['EndPointUrl'], '/');
        $endpoint = ltrim($get_request, '/');
        
        // Cancellation APIs (EMT)
        $cancellationApis = [
            'GetAuthKey',
            'flightbookingdetailv1',
            'cancelv1'
        ];

        if (in_array($get_request, $cancellationApis, true)) {
            $parsed   = parse_url($this->config['EndPointUrl']);
            $base_url = $parsed['scheme'] . '://' . $parsed['host'];
            $endpoint = 'cancellationjson/api/' . $get_request;
        }

        $url = $base_url . '/' . $endpoint;  

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => 'gzip,deflate',
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $request = [
            'ch' => $ch,
            'url' => $url,
            'requestBody' => $json,
            'remarks' => $remarks ?: 'Flight Search JSON Request'
        ]; 
        return $request;
    }
 
    /*private function get_request($json, $remarks = '',$get_request="")
    { 
     
            $headers = [
                'Content-Type: application/json',
                'Accept: application/json'
            ];
 
            $base_url = rtrim($this->config['EndPointUrl'], '/');
            $endpoint = ltrim($get_request, '/');
            $url = $base_url . '/' . $endpoint;  
 
            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_ENCODING => 'gzip,deflate',
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
 
            $request = [
                'ch' => $ch,
                'url' => $url,
                'requestBody' => $json,
                'remarks' => $remarks ?: 'Flight Search JSON Request'
            ]; 
            return $request;
    }*/

    private function format_search_request($search_data)
    {  
        $trip_type_map = [
            'oneway' => 0,
            'return' => 1,
            'multicity' => 2
        ];

        $cabin_map = [
            'Economy' => 0,
            'Premium Economy' => 1,
            'Business' => 2,
            'First' => 3
        ];
 
        $flight_search_details = []; 

        if ($search_data['trip_type'] === 'oneway' || $search_data['trip_type'] === 'return') {
             
            $flight_search_details[] = [
                "BeginDate" => date('Y-m-d', strtotime($search_data['depature'])),
                "Origin" => $search_data['from'],
                "Destination" => $search_data['to']
            ];
 
            if ($search_data['trip_type'] === 'return' && !empty($search_data['return'])) {
                $flight_search_details[] = [
                    "BeginDate" => date('Y-m-d', strtotime($search_data['return'])),
                    "Origin" => $search_data['to'],
                    "Destination" => $search_data['from']
                ];
            }
        }
 
        if ($search_data['trip_type'] === 'multicity' && !empty($search_data['segments'])) {
            foreach ($search_data['segments'] as $segment) {
                $flight_search_details[] = [
                    "BeginDate" => date('Y-m-d', strtotime($segment['depature'])),
                    "Origin" => $segment['from'],
                    "Destination" => $segment['to']
                ];
            }
        }
 
        $request = [
            "Adults" => (int)$search_data['adult_config'],
            "Authentication" => [
                        "Password" => $this->config['Password'],
                        "UserName" => $this->config['UserName'],
                        "IpAddress" => "10.10.10.10"
            ],
            "Cabin" => $cabin_map[$search_data['cabin_class']] ?? 0,
            "FaresIndicatior" => [6,12],
            "Childs" => (int)$search_data['child_config'],
            "FlightSearchDetails" => $flight_search_details,
            "TraceId" => $this->config['UserName'] . md5(uniqid(rand(), true)),
            "Infants" => (int)$search_data['infant_config'],
            "TripType" => $trip_type_map[$search_data['trip_type']] ?? 0
        ];
         
        $json_request = json_encode($request, JSON_PRETTY_PRINT);
 
        $params= $json_request; 
        return $params;
    }

    // 1. flight blender 1st request

     
    public function get_search_request($search_data)
    { 
        $this->search_dataa = $search_data;  

        if ($search_data['is_domestic'] == 1 && $search_data['trip_type'] == "return") {
            return "";    
        }

        $tripType = strtolower($search_data['trip_type'] ?? ($search_data['type'] ?? 'oneway')); 

        $params = $this->format_search_request($search_data);  
        $request_type = "FlightSearch";

        return $this->get_request($params, 'flight_list(EaseMyTrip)', $request_type); 
    }


        public function format_search_response($search_response, $search_data)
        { 
            $search_response = json_decode($search_response, true);  
 
            $TraceId = $search_response['TraceId']; 
            $formatted_response = [
                "status" => false,
                "message" => "Inprogress",
                "data" => [
                    "flights" => []
                ]
            ];  

            $flightsList = []; 

            if (!empty($search_response['Journeys'])) {
                foreach ($search_response['Journeys'] as $journey) {
                    $segments = $journey['Segments'] ?? [];
                    if (empty($segments)) continue;

                    foreach ($segments as $segment) { 
                        $flightsList[] = $this->format_search_results($segment, $search_data,$TraceId);
                    }
                }
            }

            if (!empty($flightsList)) {
                $formatted_response["status"] = true;
                $formatted_response["data"] = $flightsList;
            }

            return $formatted_response;
        }



    private function format_search_results($rsp,$search_data,$TraceId="",$is_reprice="")
        {  
            $segmentsGroup = []; 
            if (isset($rsp['Bonds'])) { 
                foreach ($rsp['Bonds'] as $bond) { 
                    $totalDeuration= $bond['JourneyTime'];
                    $segmentsGroup[] = $this->format_search_flight_details(['Bonds' => [$bond]]);
                }
            } else { 
                // debug($bond); die;
                $segmentsGroup[] = $this->format_search_flight_details($rsp);
            }

            $pricePoint = $rsp['Fare'] ?? [];
            $FareRule = $rsp['FareRule'] ?? [];
            $Price = $this->format_itineray_price_details($pricePoint,$search_data,$is_reprice,$FareRule); 
            

            /* -------------------------------------------------------------
                BAGGAGE HANDLING (2 modes):
                - SEARCH:  "ALL" block
                - REPRICE: Segment-wise baggage like Travelport
            --------------------------------------------------------------*/
 
            if ($is_reprice == 1) { 
                    $segmentBaggage = []; 

                    foreach ($segmentsGroup as $groupIndex => $group) {
                        foreach ($group as $seg) {

                            $origin = $seg["Origin"]["AirportCode"];
                            $dest   = $seg["Destination"]["AirportCode"];
                            $key    = "{$origin}-{$dest}";
        
                            /*$checked = $seg["Attr"]["Baggage"];
                            $cabin   = $seg["Attr"]["CabinBaggage"];
        
                            $checkedPieces = 0;
                            $checkedWeight = 0;
                            $checkedUnit   = "Kilograms";*/

                            // if (!empty($checked) && preg_match('/(\d+)\s*/', $checked, $m)) {
                                /*$checkedPieces = 1;
                                $checkedWeight = (int)$m[1];
                            }

                            $cabinPieces = 0;
                            $cabinWeight = 0;
                            $cabinUnit   = "Kilograms";*/

                            // if (!empty($cabin) && preg_match('/(\d+)\s*/', $cabin, $m)) {
                            /*    $cabinPieces = 1;
                                $cabinWeight = (int)$m[1];
                            }

                            $segmentBaggage[$key] = [
                                "CheckedBaggage" => [
                                    "NumberOfPieces" => $checkedPieces,
                                    "MaxWeight"      => $checkedWeight,
                                    "Unit"           => $checkedUnit,
                                    "Notes"          => [],
                                ],
                                "CarryOnBaggage" => [
                                    "NumberOfPieces" => $cabinPieces,
                                    "MaxWeight"      => $cabinWeight,
                                    "Unit"           => $cabinUnit,
                                    "Notes"          => [],
                                ],
                            ];*/
                            $checked = $seg["Attr"]["Baggage"] ?? '';
                            $cabin   = $seg["Attr"]["CabinBaggage"] ?? ''; 
                            $checkedUnit = stripos($checked, 'PC') !== false ? 'Pieces' : 'Kilograms';
                            $cabinUnit   = stripos($cabin, 'PC') !== false ? 'Pieces' : 'Kilograms';

                            preg_match('/(\d+)/', $checked, $m1);
                            preg_match('/(\d+)/', $cabin, $m2);

                            $checkedValue = (int)($m1[1] ?? 0);
                            $cabinValue   = (int)($m2[1] ?? 0);
                            $segmentBaggage[$key] = [
                            "CheckedBaggage" => [
                            "NumberOfPieces" => $checkedUnit === 'Pieces' ? $checkedValue : ($checkedValue > 0 ? 1 : 0),
                            "MaxWeight"      => $checkedUnit === 'Kilograms' ? $checkedValue : 0,
                            "Unit"           => $checkedUnit
                            ],
                            "CarryOnBaggage" => [
                            "NumberOfPieces" => $cabinUnit === 'Pieces' ? $cabinValue : ($cabinValue > 0 ? 1 : 0),
                            "MaxWeight"      => $cabinUnit === 'Kilograms' ? $cabinValue : 0,
                            "Unit"           => $cabinUnit
                            ]
                            ];
                        }
                    }


                    foreach ($Price['PassengerBreakup'] as $pType => &$detail) {
                        if ($pType !== 'INF') {
                            $detail['BaggageAllowance'] = $segmentBaggage;
                        } else {
                            $detail['BaggageAllowance'] = [];
                        }
                    }
                    unset($detail); 


            } else {
    
                    $firstSegment = $segmentsGroup[0][0] ?? [];
                    $checkedBaggage = $firstSegment['Attr']['Baggage'] ?? null;
                    $cabinBaggage   = $firstSegment['Attr']['CabinBaggage'] ?? null;

                    $baggageWeight = 0;
                    $baggageUnit = 'Kg';

                    if (!empty($checkedBaggage) && preg_match('/(\d+)\s*(Kg|KG|kg|Lbs|lbs)?/', $checkedBaggage, $matches)) {
                        $baggageWeight = (int)$matches[1];
                        $baggageUnit   = $matches[2] ?? 'Kg';
                    }

                    $cabinWeight = 0;
                    $cabinUnit = 'Kg';

                    if (!empty($cabinBaggage) && preg_match('/(\d+)\s*(Kg|KG|kg|Lbs|lbs)?/', $cabinBaggage, $matches)) {
                        $cabinWeight = (int)$matches[1];
                        $cabinUnit   = $matches[2] ?? 'Kg';
                    }
            
                    $baggageBlock = [
                        "ALL" => [
                            "CheckedBaggage" => [
                                "NumberOfPieces" => ($baggageWeight > 0 ? 1 : 0),
                                "MaxWeight"      => ($baggageWeight > 0 ? $baggageWeight : null),
                                "Unit"           => ($baggageWeight > 0 ? $baggageUnit : null),
                                "Notes"          => [],
                            ],
                            "CarryOnBaggage" => [
                                "NumberOfPieces" => ($cabinWeight > 0 ? 1 : 0),
                                "MaxWeight"      => ($cabinWeight > 0 ? $cabinWeight : null),
                                "Unit"           => ($cabinWeight > 0 ? $cabinUnit : null),
                                "Notes"          => [],
                            ]
                        ]
                    ];

                    foreach ($Price['PassengerBreakup'] as $pType => &$detail) {
                        if ($pType !== 'INF') {
                            $detail['BaggageAllowance'] = $baggageBlock;
                        } else {
                            $detail['BaggageAllowance'] = [
                                "ALL" => [
                                    "CheckedBaggage" => [
                                        "NumberOfPieces" => 0,
                                        "MaxWeight" => null,
                                        "Unit" => null,
                                        "Notes" => []
                                    ]
                                ]
                            ];
                        }
                    }
                    unset($detail);
        }


        
        $resultToken = 'EaseMyTrip_flight_search_result_' . generate_uuid();
        $resultTokenData = [
            'segments' => @$rsp,
            'cabinClass' => 'Economy', 
            'search_data' => $search_data,
            'TraceId' => $TraceId,
        ];
        $this->insert_cache_record($resultToken, $resultTokenData);  
        $formatted = [
            "FlightDetails" =>  $segmentsGroup ,
            "totalDuration" =>  $totalDeuration,
            "Price" => $Price,
            "Attr" => [
                "TestMode" => ($this->booking_source_system == 'test'),
                "IsRefundable" => $Price['PassengerBreakup']['ADT']['Refundable'],
                "AirlineRemark" => $rsp['Remark'],
                "IsLCC" => false,
                'fareAttributes' => $this->format_fare_attributes_easemytrip($rsp), 
            ],
            "HoldTicket" => $rsp['IsHoldBooking'],
            "ResultToken" => $this->get_flight_result_token($resultToken),
            "APIIDENTIFY" => EASE_MY_TRIP_BOOKING_SOURCE
        ]; 
        
        return $formatted;
        }


    private function format_search_flight_details($segAttr): array
    {
        // debug($segAttr); die;
        $segments = []; 
        foreach ($segAttr['Bonds'] as $Legs) {  
            foreach ($Legs['Legs'] as $Legs_det) { 
                $raw_departure = $Legs_det['DepartureDate'] ?? '';
                $raw_arrival = $Legs_det['ArrivalDate'] ?? '';
                 
                $raw_arrival_clean = preg_replace('/^[A-Za-z]{3}-/', '', $raw_arrival);  
                $date_arrival = date('Y-m-d', strtotime($raw_arrival_clean));
 
                $raw_departure_clean = preg_replace('/^[A-Za-z]{3}-/', '', $raw_departure);  
                $date_departure = date('Y-m-d', strtotime($raw_departure_clean)); 
                 
                $departureTime  = date('H:i', strtotime($Legs_det['DepartureTime'] ?? '00:00'));
                $arrivalTime    = date('H:i', strtotime($Legs_det['ArrivalTime'] ?? '00:00'));

                $origin_code = $Legs_det['Origin'] ?? '';
                $destination_code = $Legs_det['Destination'] ?? '';

                $depTimestamp = strtotime("{$date_departure} {$departureTime}");
                $arrTimestamp = strtotime("{$date_arrival} {$arrivalTime}");

                $duration_raw = trim($Legs['JourneyTime'] ?? '');
                $duration = preg_replace('/\s*Hrs?\s*/i', ' Hrs ', $duration_raw);
                $duration = preg_replace('/\s*Mins?/i', ' Mins ', $duration);

                $durations = $Legs_det['Duration'];

                preg_match('/(\d+)h\s*(\d+)m/', $durations, $matches);

                $formatted = intval($matches[1]) . " Hrs " . intval($matches[2]) . " Mins";
 

                $segments[] = [
                    "Origin" => [
                        "AirportCode" => $origin_code,
                        "CityName" => get_airport_city($origin_code, 'CN', $this->airport_list),
                        "AirportName" => get_airport_city($origin_code, 'AN', $this->airport_list),
                        "date_time" => "{$date_departure}T{$departureTime}",
                        "DateTime" => "{$date_departure} {$departureTime}:00",
                        "date" => $date_departure,
                        "time" => $departureTime,
                        "FDTV" => $depTimestamp,
                        "OriginTerminal" => $Legs_det['DepartureTerminal'] ?? null
                    ],
                    "Destination" => [
                        "AirportCode" => $destination_code,
                        "CityName" => get_airport_city($destination_code, 'CN', $this->airport_list),
                        "AirportName" => get_airport_city($destination_code, 'AN', $this->airport_list),
                        "date_time" => "{$date_arrival}T{$arrivalTime}",
                        "DateTime" => "{$date_arrival} {$arrivalTime}:00",
                        "date" => $date_arrival,
                        "time" => $arrivalTime,
                        "FATV" => $arrTimestamp,
                        "DestinationTerminal" => $Legs_det['ArrivalTerminal'] ?? null
                    ],
                    // "Duration" => $duration,
                    "Duration" => $formatted,
                    
                    "OperatorCode" => $Legs_det['CarrierCode'] ?? '',
                    "OperatorName" => get_airline_name($Legs_det['CarrierCode'] ?? '', $this->airline_list),
                    "OperatingCarrierCode" => null,
                    "OperatingCarrierName" => null,
                    "FlightNumber" => $Legs_det['FlightNumber'] ?? '',
                    "is_leg" => false,
                    "CabinClass" => $Legs_det['Cabin'] ?? 'Economy',
                    "Attr" => [
                        "Baggage" => $this->generate_checked_baggage_small_text(
                            $Legs_det['BaggageWeight'] ?? '',
                            $Legs_det['Baggages'] ?? '',
                            $Legs_det['BaggageUnit'] ?? ''
                        ),
                        "CabinBaggage" => trim(($Legs_det['CabinBagWT'] ?? '0') . " " . ($Legs_det['CabinBagUT'] ?? 'KG')),
                        "AvailableSeats" => $Legs_det['AvailableSeat'] ?? "0"
                    ]
                ];
                }
            }  
         return $segments;
        }           


        private function format_itineray_price_details($airPricingPoint,$seach_data="",$is_reprice="",$FareRule="")
        {  
            if (isset($airPricingPoint['Fare'])) {
                $fare = $airPricingPoint['Fare'];
            } else {
                $fare = $airPricingPoint;  
            } 
            $paxFares = $fare['PaxFares'] ?? [];
            $passengerBreakup = [];

            foreach ($paxFares as $paxFare) {
                
                $type = 'ADT';
                if (isset($paxFare['PaxType'])) {
                    if ($paxFare['PaxType'] == 1) $type = 'CHD';
                    elseif ($paxFare['PaxType'] == 2) $type = 'INF';
                } 
                $base  = floatval($this->parseAmount($paxFare['BasicFare']) ?? 0);
                 
                $tax   = floatval($this->parseAmount($paxFare['TotalTax']) ?? 0);
                $total = floatval($paxFare['TotalFare'] ?? ($base + $tax));

                $cancelPenalty = floatval($this->parseAmount($paxFare['CancelPenalty']) ?? 0);
                $changePenalty = floatval($this->parseAmount($paxFare['ChangePenalty']) ?? 0);
                $refundable = $paxFare['Refundable'];

            if ($refundable === false || $refundable === 0) {
                // Explicit non-refundable
                $cancelApplies = 'Non-refundable';

            } elseif ($refundable === true || $refundable === 1) {
                // Explicit refundable
                $cancelApplies = $this->extract_penalty_hours_emt($FareRule, 'CANCEL') ?? 'Before Departure';

            } else {
                // Refundable is missing → fallback using CancelPenalty
                if ($cancelPenalty > 0) {
                    $cancelApplies = $this->extract_penalty_hours_emt($FareRule, 'CANCEL') ?? 'Before Departure';
                } else {
                    $cancelApplies = 'Non-refundable';
                }
            }

            $changeApplies = $paxFare['Changeable']
                ? ($this->extract_penalty_hours_emt($FareRule, 'CHANGE') ?? 'Anytime')
                : 'Not allowed';
                $cancelPenaltyFormatted = $this->format_penalties_emt($cancelPenalty, $cancelApplies);
                $changePenaltyFormatted = $this->format_penalties_emt($changePenalty, $changeApplies);    

                $penalties = [
                'CancelPenalties' => $cancelPenaltyFormatted,
                'ChangePenalties' => $changePenaltyFormatted,
                ];
                    if($type=="ADT"){
                        $pass_c = $seach_data['adult_config'];
                    }
                    if($type=="CHD"){
                        $pass_c = $seach_data['child_config'];
                    }
                     if($type=="INF"){
                        $pass_t_c = $seach_data['infant_config'];
                    }
                $passengerBreakup[$type] = [
                    'PassengerCount' => $pass_c,
                    'BasePrice'      => $base,
                    'Tax'            => $tax,
                    'TotalPrice'     => $total,
                    'Penalties'      => $penalties,
                    'Refundable'      => $paxFare['Refundable'],
                ];
            }
  
            $basicFare = floatval($this->parseAmount($fare['BasicFare']) ?? 0);
            $tax       = floatval($this->parseAmount($fare['TotalTaxWithOutMarkUp']) ?? 0);
            $totalFare = floatval($this->parseAmount($fare['TotalFareWithOutMarkUp']) ?? $this->parseAmount(($basicFare + $tax)));

            $_price = [
                'Fare_Type' => 'Regular Fare',
                'PassengerBreakup' => $passengerBreakup,
                'Currency' => 'INR',  
                'TotalDisplayFare' => $totalFare,
                'PriceBreakup' => [
                    'Tax' => $tax,
                    'BasicFare' => $basicFare,
                    'AgentCommission' => 0,
                    'AgentTdsOnCommision' => 0,
                ] 
            ]; 
            return $_price;
        } 

    private function extract_penalty_hours_emt($FareRule, $type = 'CANCEL')

        {
            if (empty($FareRule)) {
                return null;
            }

            $rules = explode('|', $FareRule);

            foreach ($rules as $rule) {

                if ($type === 'CANCEL') {
                    $pattern = '/^(CAN|CANCEL)-BEF\s+([\d_]+)/';
                } else {
                    $pattern = '/^(CHG|CHANGE)-BEF\s+([\d_]+)/';
                }

                if (preg_match($pattern, trim($rule), $m)) {

                    // m[2] example: 8760_4 or 72_4
                    $parts = explode('_', $m[2]);
                    $lastHour = end($parts); // 4 or 72

                    return $lastHour . ' Hours Before Departure';
                }
            }

            return null;
        }

        private function format_penalties_emt($amount, $applies = "Anytime")
        { 
            return [
                $applies => [
                    "type"            => "amount",
                    "value"           => floatval($amount),
                    "penaltyApplies"  => $applies,
                    "noShow"          => false
                ]
            ];
        }

    private function format_fare_attributes_easemytrip($rsp)
        {
            $paxFare = $rsp['Fare']['PaxFares'][0] ?? [];
            $firstLeg = $rsp['Bonds'][0]['Legs'][0] ?? [];
            $fareRule = $rsp['FareRule'] ?? '';
            $penaltyFromRule = $this->extract_penalty_from_rule($fareRule);    

            $checkedBaggageWeight = $paxFare['BaggageWeight'] ?? $firstLeg['BaggageWeight'] ?? 0;
            $checkedBaggageUnit   = $paxFare['BaggageUnit'] ?? $firstLeg['BaggageUnit'] ?? 'Kg';
            $cabinBagWeight       = $firstLeg['CabinBagWT'] ?? 0;
            $cabinBagUnit         = $firstLeg['CabinBagUT'] ?? 'Kg';
            $refundable           = $paxFare['Refundable'] ?? 0;
            $changeable           = $paxFare['Changeable'] ?? 0;
            $cancelPenalty        = $paxFare['CancelPenalty'] ?? 0;
            $changePenalty        = $paxFare['ChangePenalty'] ?? 0;

             
            $fareAttributes = [];

            // 1. Checked baggage
            $fareAttributes['1'] = ($checkedBaggageWeight > 0) ? 'I' : 'N';

            // 2. Cabin baggage
            $fareAttributes['2'] = ($cabinBagWeight > 0) ? 'I' : 'N';

            // 3. Rebooking
            if (!$changeable) {
                $fareAttributes['3'] = 'N';
            } elseif ($changePenalty == 0) {
                $fareAttributes['3'] = 'I';
            } else {
                $fareAttributes['3'] = 'A';
            }

            // 4. Refund
            /*if (!$refundable) {
                $fareAttributes['4'] = 'N';
            } elseif ($cancelPenalty == 0) {
                $fareAttributes['4'] = 'I';
            } else {
                $fareAttributes['4'] = 'A';
            }*/
            /*if (!$refundable) {
                $fareAttributes['4'] = 'N'; // Non-refundable
            } elseif ($cancelPenalty == 0) {
                $fareAttributes['4'] = 'I'; // Fully refundable
            } elseif ($cancelPenalty > 0) {
                $fareAttributes['4'] = 'A'; // Refund with fee
            } else {
                $fareAttributes['4'] = 'N'; // fallback safety
            }*/

            if (!$refundable) {
                $fareAttributes['4'] = 'N'; // Non-refundable

            } elseif ($penaltyFromRule > 0) {
                $fareAttributes['4'] = 'A'; // Refund with fee

            } elseif ($penaltyFromRule === 0) {
                $fareAttributes['4'] = 'I'; // Fully refundable

            } elseif ($cancelPenalty > 0) {
                $fareAttributes['4'] = 'A';

            } else {
                $fareAttributes['4'] = 'I'; // fallback
            }
            // 5. Seat selection (Not getting from API)
            $fareAttributes['5'] = 'N';

            // 6. Meals (Not getting from API)
            $fareAttributes['6'] = 'N';

            // 7. WiFi (Not getting from API)
            $fareAttributes['7'] = 'N';
 
            $baggageAllowance = [
                'MaxWeight' => [
                    '@attributes' => [
                        'Value' => $checkedBaggageWeight,
                        'Unit'  => $checkedBaggageUnit,
                    ]
                ]
            ];
 
            return $this->format_fare_attributes(
                implode('|', array_map(fn($k, $v) => "$k,$v", array_keys($fareAttributes), $fareAttributes)),
                $baggageAllowance
            );
        }
    
    private function format_fare_attributes($fareAttributesStr, $baggageAllowance = null)
    {
        $attributes = [];
        if (!empty($fareAttributesStr)) {
            foreach (explode('|', $fareAttributesStr) as $pair) {
                [$key, $value] = explode(',', $pair); 
                 if ($value === 'N' && !in_array($key, ['1', '2', '4'])) {
                // if ($value === 'N' && !in_array($key, ['1', '2'])) {
                    continue;
                }
                $attributes[$key] = $value;
            }
        }
        if (empty($attributes) && !empty($baggageAllowance)) {
            $attributes['1'] = 'I';  
            $attributes['2'] = 'N';  
        }

        $attributeMap = [
            '1' => ['label' => 'Checked baggage', 'group' => 'Baggage'],
            '2' => ['label' => 'Cabin baggage', 'group' => 'Baggage'],
            '3' => ['label' => 'Rebooking', 'group' => 'Flexibility'],
            '4' => ['label' => 'Refund', 'group' => 'Flexibility'],
            '5' => ['label' => 'Seat selection', 'group' => 'Seats, Meals & More'],
            '6' => ['label' => 'Meals', 'group' => 'Seats, Meals & More'],
            '7' => ['label' => 'WiFi', 'group' => 'Seats, Meals & More'],
        ];

        $valueText = [
            'I' => [
                'Cabin baggage' => 'Cabin baggage included',
                'Rebooking' => 'Free rebooking allowed',
                // 'Refund' => 'Fully refundable',
                'Refund' => 'Refundable fare (as per airline rules)', 
                'Seat selection' => 'Free seat selection',
                'Meals' => 'Complimentary meal included',
                'WiFi' => 'Free WiFi available onboard',
            ],
            'A' => [
                'Cabin baggage' => 'Cabin baggage available at extra cost',
                'Rebooking' => 'Rebooking available with a fee',
                'Refund' => 'Refund available with a fee',
                'Seat selection' => 'Seat selection available for a fee',
                'Meals' => 'Meal available for purchase',
                'WiFi' => 'WiFi available onboard',
            ],
            'N' => [
                'Checked baggage' => 'No checked baggage included',
                'Cabin baggage' => 'No cabin baggage included',
               'Refund' => 'Non-refundable', // Add this new line

            ],
        ];

        $output = [];

        foreach ($attributes as $key => $value) {
            if (!isset($attributeMap[$key])) {
                continue;
            }

            $label = $attributeMap[$key]['label'];
            $group = $attributeMap[$key]['group'];

            if ($key == '1' && $value == 'I') { 
                $text = $this->generate_checked_baggage_text($baggageAllowance);
            } else {
                $text = $valueText[$value][$label] ?? "$label: $value";
            }

            if (!empty($text)) {
                $output[$group][] = $text;
            }
        }

        return $output;
    }    

    private function extract_penalty_from_rule($fareRule)
    {
        if (empty($fareRule)) return null;

        // Match CANCEL-BEF or CAN-BEF
        if (preg_match('/CANCEL-BEF.*?:(\d+(\.\d+)?)/', $fareRule, $match)) {
            return (float) $match[1];
        }

        if (preg_match('/CAN-BEF.*?:(\d+(\.\d+)?)/', $fareRule, $match)) {
            return (float) $match[1];
        }

        return null;
    }
    private function generate_checked_baggage_text($baggage)
    {
        if (!$baggage || empty($baggage)) {
            return 'Checked baggage included';
        }

        if (!empty($baggage['NumberOfPieces'])) {
            $pieces = (int) $baggage['NumberOfPieces'];
            if ($pieces > 0) {
                return "$pieces piece" . ($pieces > 1 ? 's' : '') . ' of checked baggage included';
            } else {
                return 'No checked baggage included';
            }
        }

        if (!empty($baggage['MaxWeight']['@attributes']['Value'])) {
            $weight = $baggage['MaxWeight']['@attributes']['Value'];
            $unit = $baggage['MaxWeight']['@attributes']['Unit'] ?? 'Kg';
            return "$weight $unit of checked baggage included";
        }

        return 'Checked baggage included';
    }

    private function generate_checked_baggage_small_text($baggage,$baggage_,$BaggageUnit)
    {
        if (!$baggage || empty($baggage)) {
            return '0 pieces';
        }

        if (!empty($baggage['NumberOfPieces'])) {
            $pieces = (int) $baggage['NumberOfPieces'];
            if ($pieces > 0) {
                return "$pieces piece" . ($pieces > 1 ? 's' : '');
            } else {
                return '0 pieces';
            }
        }

        if (!empty($baggage)) {
            $weight = $baggage['MaxWeight']['@attributes']['Value'];
            $unit = $BaggageUnit ?? 'Kg';
            return "$baggage $unit";
        }

        return '0 pieces';
    }

    private function parseAmount($value)
    {
        return $this->currency_obj->get_value(floatval(str_replace([$this->config_currency, ' '], '', $value)));
    }

     /* Upsell starts */
 
        /*public function get_upsell($token)
        {
            $tokenData  = $this->read_cache_record($token); 
            $searchData = $tokenData['search_data'];
            $TraceId    = $tokenData['TraceId'] ?? "";

            $journeyList = $this->format_search_results_upsel($tokenData['segments'], $searchData, $TraceId);

            return [
                'status' => !empty($journeyList),
                'message' => [],
                'data' => $journeyList  
            ];
        }
        private function format_search_results_upsel($rsp, $search_data, $TraceId = "")
        {
            $finalUpsellList = [];    
            $upsellOptions = $rsp['UpsellOptions'] ?? [$rsp];  

            foreach ($upsellOptions as $option) { 
                        
                $segmentsGroup = [];
 
                if (isset($option['Bonds'])) {
                    foreach ($option['Bonds'] as $bond) {
                        $segmentsGroup[] = $this->format_search_flight_details([
                            'Bonds' => [$bond]
                        ]);
                    }
                } else {
                    $segmentsGroup[] = $this->format_search_flight_details($option);
                }

                $Price = $this->format_itineray_price_details($option['Fare'], $search_data,"", $option['FareRule']); 
                $segmentBaggage = [];  

                foreach ($segmentsGroup as $group) {
 
                    if (isset($group['Origin']) && isset($group['Destination'])) {
                        $segments = [ $group ];
                    } else {
                        $segments = is_array($group) ? $group : [];
                    }

                    foreach ($segments as $seg) {
 
                        if (!isset($seg["Origin"]["AirportCode"], $seg["Destination"]["AirportCode"], $seg["Attr"])) {
                            continue;
                        }

                        $origin = $seg["Origin"]["AirportCode"];
                        $dest   = $seg["Destination"]["AirportCode"];
                        $key    = "{$origin}-{$dest}";

                        $checked = $seg["Attr"]["Baggage"];
                        $cabin   = $seg["Attr"]["CabinBaggage"];

                        $checkedPieces = 0;
                        $checkedWeight = 0;
                        $cabinPieces   = 0;
                        $cabinWeight   = 0;

                        if (!empty($checked) && preg_match('/(\d+)/', $checked, $m)) {
                            $checkedPieces = 1;
                            $checkedWeight = (int)$m[1];
                        }

                        if (!empty($cabin) && preg_match('/(\d+)/', $cabin, $m)) {
                            $cabinPieces = 1;
                            $cabinWeight = (int)$m[1];
                        }

                        $segmentBaggage[$key] = [
                            "CheckedBaggage" => [
                                "NumberOfPieces" => $checkedPieces,
                                "MaxWeight"      => $checkedWeight,
                                "Unit"           => "Kilograms"
                            ],
                            "CarryOnBaggage" => [
                                "NumberOfPieces" => $cabinPieces,
                                "MaxWeight"      => $cabinWeight,
                                "Unit"           => "Kilograms"
                            ]
                        ];
                    }
                }

                foreach ($Price['PassengerBreakup'] as $pType => &$detail) {
                    if ($pType !== 'INF') {
                        $detail['BaggageAllowance'] = $segmentBaggage;
                    } else {
                        $detail['BaggageAllowance'] = [];
                    }
                }
                unset($detail);

                $resultToken = 'EaseMyTrip_flight_search_result_' . generate_uuid();
                $resultTokenData = [
                    'segments'    => $option,
                    'cabinClass'  => 'Economy',
                    'search_data' => $search_data,
                    'TraceId'     => $TraceId,
                ];
                $this->insert_cache_record($resultToken, $resultTokenData);

                $formatted = [
                    "FlightDetails" => [$segmentsGroup],
                    "Price"         => $Price,
                    "Attr" => [
                        "IsRefundable"  => $Price['PassengerBreakup']['ADT']['Refundable'],
                        "AirlineRemark" => $option['Remark'],
                        "BrandName"     => $option['Remark'],
                        "IsLCC"         => false,
                        "fareAttributes" => $this->format_fare_attributes_easemytrip($option),
                        "TestMode"       => ($this->booking_source_system == 'test')
                    ],
                    "HoldTicket"  => $option['IsHoldBooking'],
                    "ResultToken" => $this->get_flight_result_token($resultToken),
                    "APIIDENTIFY" => EASE_MY_TRIP_BOOKING_SOURCE
                ];

                $finalUpsellList[] = $formatted;
            }

            return $finalUpsellList;   
        }*/

         public function get_upsell($token)
    {    
        $tokenData  = $this->read_cache_record($token); 
        $searchData = $tokenData['search_data'];
        $TraceId    = $tokenData['TraceId'] ?? ""; 
        $journeyList = [];  

        if (!defined('EASE_MY_TRIP_BOOKING_SOURCE')) {
            define('EASE_MY_TRIP_BOOKING_SOURCE', 'EASE_MY_TRIP');
        }

        $request_handle = [];  
        $request_handle[EASE_MY_TRIP_BOOKING_SOURCE] =  $this->get_upsell_request($tokenData);   
        $result = $this->CI->curlmultihandler->execute($request_handle);  
        $fare_qoute_response = $result[EASE_MY_TRIP_BOOKING_SOURCE] ?? ''; 
        $search_response = json_decode($fare_qoute_response, true); 
        $journeys = $search_response['Journeys'] ?? [];
        $traceId  = $search_response['TraceId'] ?? null;

        $journeyList = [];
        $totalSegments = 0;

        foreach ($journeys as $journey) {

            if (!empty($journey['Segments']) && is_array($journey['Segments'])) {
                $totalSegments += count($journey['Segments']);
            }
        }

        // RULE: 0 segment  → use tokenData , 1 segment  → use tokenData , >1 segment → loop segments 

        if ($totalSegments <= 1) {

        // This will run for: Segments empty , Segments not set , Segments count = 1

            $journeyList = $this->format_search_results_upsel(
                $tokenData['segments'],
                $searchData,
                $traceId
            );

        } else {

            foreach ($journeys as $journey) {

                if (empty($journey['Segments'])) continue;

                foreach ($journey['Segments'] as $segment) {

                    $formatted = $this->format_search_results_upsel(
                        $segment,
                        $searchData,
                        $traceId
                    );

                    if (!empty($formatted)) {
                        $journeyList = array_merge($journeyList, $formatted);
                    }
                }
            }
        }

        return [
            'status'  => !empty($journeyList),
            'message' => [],
            'data'    => $journeyList  
        ];
    }
     
    private function format_search_results_upsel($rsp, $search_data, $TraceId = "")
    {
        $finalUpsellList = [];    
        $upsellOptions = $rsp['UpsellOptions'] ?? [$rsp];  

        foreach ($upsellOptions as $option) { 

            $segmentsGroup = [];

            if (!empty($option['Bonds'])) {
                foreach ($option['Bonds'] as $bond) {
                    $segmentsGroup[] = $this->format_search_flight_details([
                        'Bonds' => [$bond]
                    ]);
                }
            } else {
                $segmentsGroup[] = $this->format_search_flight_details($option);
            }

            $Price = $this->format_itineray_price_details(
                $option['Fare'] ?? [],
                $search_data,
                "",
                $option['FareRule'] ?? []
            ); 
            $segmentBaggage = [];  

            foreach ($segmentsGroup as $group) {

                $segments = isset($group['Origin']) ? [$group] : (array)$group;

                foreach ($segments as $seg) {

                    if (
                        empty($seg["Origin"]["AirportCode"]) ||
                        empty($seg["Destination"]["AirportCode"]) ||
                        empty($seg["Attr"])
                    ) {
                        continue;
                    }

                    $origin = $seg["Origin"]["AirportCode"];
                    $dest   = $seg["Destination"]["AirportCode"];
                    $key    = "{$origin}-{$dest}";

                    $checked = $seg["Attr"]["Baggage"] ?? '';
                    $cabin   = $seg["Attr"]["CabinBaggage"] ?? '';

                    // preg_match('/(\d+)/', $checked, $m1);
                    // preg_match('/(\d+)/', $cabin, $m2);

                    // $segmentBaggage[$key] = [
                    //     "CheckedBaggage" => [
                    //         "NumberOfPieces" => !empty($m1) ? 1 : 0,
                    //         "MaxWeight"      => !empty($m1) ? (int)$m1[1] : 0,
                    //         "Unit"           => "Kilograms"
                    //     ],
                    //     "CarryOnBaggage" => [
                    //         "NumberOfPieces" => !empty($m2) ? 1 : 0,
                    //         "MaxWeight"      => !empty($m2) ? (int)$m2[1] : 0,
                    //         "Unit"           => "Kilograms"
                    //     ]
                    // ];

                    $checked = $seg["Attr"]["Baggage"] ?? '';
                    $cabin   = $seg["Attr"]["CabinBaggage"] ?? ''; 
                    $checkedUnit = stripos($checked, 'PC') !== false ? 'Pieces' : 'Kilograms';
                    $cabinUnit   = stripos($cabin, 'PC') !== false ? 'Pieces' : 'Kilograms';

                    preg_match('/(\d+)/', $checked, $m1);
                    preg_match('/(\d+)/', $cabin, $m2);

                    $checkedValue = (int)($m1[1] ?? 0);
                    $cabinValue   = (int)($m2[1] ?? 0);
                    $segmentBaggage[$key] = [
                            "CheckedBaggage" => [
                                "NumberOfPieces" => $checkedUnit === 'Pieces' ? $checkedValue : ($checkedValue > 0 ? 1 : 0),
                                "MaxWeight"      => $checkedUnit === 'Kilograms' ? $checkedValue : 0,
                                "Unit"           => $checkedUnit
                            ],
                            "CarryOnBaggage" => [
                                "NumberOfPieces" => $cabinUnit === 'Pieces' ? $cabinValue : ($cabinValue > 0 ? 1 : 0),
                                "MaxWeight"      => $cabinUnit === 'Kilograms' ? $cabinValue : 0,
                                "Unit"           => $cabinUnit
                            ]
                        ];
                }
            }

            if (!empty($Price['PassengerBreakup'])) {
                foreach ($Price['PassengerBreakup'] as $pType => &$detail) {
                    $detail['BaggageAllowance'] = ($pType !== 'INF')
                        ? $segmentBaggage
                        : [];
                }
                unset($detail);
            } 

            $resultToken = 'EaseMyTrip_flight_search_result_' . generate_uuid();

            $this->insert_cache_record($resultToken, [
                'segments'    => $option,
                'cabinClass'  => 'Economy',
                'search_data' => $search_data,
                'TraceId'     => $TraceId,
            ]);


            $formatted = [
                "FlightDetails" => $segmentsGroup,    
                "Price"         => $Price,
                "Attr" => [
                    "IsRefundable"  => $Price['PassengerBreakup']['ADT']['Refundable'] ?? false,
                    "AirlineRemark" => $option['Remark'] ?? "",
                    "BrandName"     => $option['Fare']['BrandName'] ?? "",   
                    "IsLCC"         => false,
                    "fareAttributes" => $this->format_fare_attributes_easemytrip($option),
                    "TestMode"       => ($this->booking_source_system == 'test')
                ],
                "HoldTicket"  => $option['IsHoldBooking'] ?? false,
                "ResultToken" => $this->get_flight_result_token($resultToken),
                "APIIDENTIFY" => EASE_MY_TRIP_BOOKING_SOURCE
            ];

            $finalUpsellList[] = $formatted;
        }

        return $finalUpsellList;   
    }
            
    private function get_upsell_request($data)
    { 
        $request = null;  
        $params = $this->format_farefamily_request($data);  
        $get_request = "FamilyFareAvailability";
        $request = $this->get_request($params, 'Flight_FareFamily(EMT-Flight)',$get_request);   
        return $request;
    }   
     public function format_farefamily_request($data)
    { 
        $traceId   = $data['TraceId'];
        $segments  = $data['segments'];
        $searchData = $data['search_data'];

        $cabin_map = [
            'Economy' => 0,
            'Premium Economy' => 1,
            'Business' => 2,
            'First' => 3
        ]; 
        $flight_search_details = [];

        if (!empty($segments['Bonds'])) {

            foreach ($segments['Bonds'] as $bond) {

                $airline_details = [];
                $legs = $bond['Legs'];

                $tripOrigin = '';
                $tripDestination = '';
                $tripStartDate = '';
                $tripEndDate = '';

                foreach ($legs as $index => $leg) {  
                    $departureDateTime = date(
                        'Y-m-d Hi',
                        strtotime(str_replace('-', ' ', $leg['DepartureDate']) . ' ' . $leg['DepartureTime'])
                    );

                    $arrivalDateTime = date(
                        'Y-m-d Hi',
                        strtotime(str_replace('-', ' ', $leg['ArrivalDate']) . ' ' . $leg['ArrivalTime'])
                    );

                    if ($index === 0) {
                        $tripOrigin = $leg['Origin'];
                        $tripStartDate = date(
                            'Y-m-d',
                            strtotime(str_replace('-', ' ', $leg['DepartureDate']))
                        );
                    }

                    if ($index === count($legs) - 1) {
                        $tripDestination = $leg['Destination'];
                        $tripEndDate = date(
                            'Y-m-d',
                            strtotime(str_replace('-', ' ', $leg['ArrivalDate']))
                        );
                    }

                    $airline_details[] = [
                        "AirLineCode"  => $leg['CarrierCode'],
                        "FlightNumber" => $leg['FlightNumber'],
                        "Origin"       => $leg['Origin'],
                        "Destination"  => $leg['Destination'],
                        "BeginDate"    => $departureDateTime,
                        "EndDate"      => $arrivalDateTime
                    ];
                } 

                $flight_search_details[] = [
                    "AirlineSearchDetails" => $airline_details,
                    "BeginDate" => $tripStartDate,
                    "EndDate" => $tripEndDate,
                    "Origin" => $tripOrigin,
                    "Destination" => $tripDestination
                ];
            }
        } 
        $fare_family_request = [
            "TraceId" => $traceId,
            "Authentication" => [
                "UserName" => $this->config['UserName'],
                "Password" => $this->config['Password'],
                "IpAddress" => "10.10.10.10"
            ],
            "FaresIndicatior" => [12],
            "Cabin" => $cabin_map[$searchData['cabin_class']] ?? 0,
            "Adults" => (int)$searchData['adult_config'],
            "Childs" => (int)$searchData['child_config'],
            "Infants" => (int)$searchData['infant_config'],
            // "SearchId" => $segments['SearchId'] ?? "",
            "SearchId" => "",
            "FlightSearchDetails" => $flight_search_details
        ]; 
        return json_encode($fare_family_request, JSON_PRETTY_PRINT);
    }    


  
    /*upsell ends  */ 
    public function format_fare_qoute_request($search_data)
    {   
         
        $seg = $search_data['segments']; 
        $TraceId =$search_data['TraceId'];
        $EngineID= $seg['EngineID']; 

        $search_data = $search_data['search_data']; 
        $trip_type_map = [
            'oneway' => 0,
            'return' => 1,
            'multicity' => 2
        ];

        $cabin_map = [
            'Economy' => 0,
            'Premium Economy' => 1,
            'Business' => 2,
            'First' => 3
        ];
    
        $flight_search_details = []; 

        if ($search_data['trip_type'] === 'oneway' || $search_data['trip_type'] === 'return') {
            $flight_search_details[] = [
                "BeginDate" => date('Y-m-d', strtotime($search_data['depature'])),
                "Origin" => $search_data['from'],
                "Destination" => $search_data['to']
            ];

            if ($search_data['trip_type'] === 'return' && !empty($search_data['return'])) {
                $flight_search_details[] = [
                    "BeginDate" => date('Y-m-d', strtotime($search_data['return'])),
                    "Origin" => $search_data['to'],
                    "Destination" => $search_data['from']
                ];
            }
        }

        if ($search_data['trip_type'] === 'multicity' && !empty($search_data['segments'])) {
            foreach ($search_data['segments'] as $segment) {
                $flight_search_details[] = [
                    "BeginDate" => date('Y-m-d', strtotime($segment['depature'])),
                    "Origin" => $segment['from'],
                    "Destination" => $segment['to']
                ];
            }
        }
    
        $flight_availability_rq = [
            "Adults" => (int)$search_data['adult_config'],
            "Childs" => (int)$search_data['child_config'],
            "Infants" => (int)$search_data['infant_config'],                
            "Authentication" => [
                "Password" => $this->config['Password'],    
                "UserName" => $this->config['UserName'],
                "IpAddress" => "10.10.10.10"
            ],
            "Cabin" => $cabin_map[$search_data['cabin_class']] ?? 0,
            "EngineID" => [$EngineID],
            "FlightSearchDetails" => $flight_search_details,
            "AirpricePosition" => "1",
            "SaveSessionStatus" => true, 
             "TraceId" =>$TraceId,
            "TripType" => $trip_type_map[$search_data['trip_type']] ?? 0
            ]; 
            if (isset($seg[0]) && is_array($seg[0])) {
            $segment = $seg;  
            } else {
            $segment = [$seg]; 
            } 
            $final_request = [
                "FlightAvailabilityRQ" => $flight_availability_rq,
                "Segment" => $segment
            ];  
            $json_request = json_encode($final_request, JSON_PRETTY_PRINT);   
            return $json_request;
    } 

    private function get_fare_qoute_request($data)
    {
        $request = null;  
        $params = $this->format_fare_qoute_request($data); 
        $get_request = "AirRePriceRQ";
        $request = $this->get_request($params, 'flight_pricing(EMT Flight)',$get_request);  
        return $request;
    }

    public function get_update_fare_quote($token)
    { 

        $is_reprice=1;
        $tokenData = $this->read_cache_record($token);    
        $request_handle[EASE_MY_TRIP_BOOKING_SOURCE] = $this->get_fare_qoute_request($tokenData);  
        $result = $this->CI->curlmultihandler->execute($request_handle); 
        $fare_qoute_response = $result[EASE_MY_TRIP_BOOKING_SOURCE]; 
        $json_response = json_decode($fare_qoute_response,true); 
        if (!empty($json_response['Errors'])) {
        return [
        'status' => false,
        'message' => $json_response['Errors']['Description']."— Please try searching again..!!" ?? 'Reprice failed',
        'error_code' => $json_response['Errors']['Code'] ?? null,
        'data' => null
        ];
        }
        $search_data=$tokenData['search_data']; 
        $TraceId=$json_response['TraceId']; 
        foreach ($json_response['Journeys'] as $json_response) { 
            $response_body = @$json_response['Segments'];
            if (empty($response_body)) continue;

            $journey = []; 
            foreach ($response_body as $response_inner) { 
                $journey[] = $this->format_search_results($response_inner,$search_data,$TraceId,$is_reprice);
            }
    
            if (!empty($journey)) {
                $journeyList[] = $journey;  
            }
        } 
        $formatted_response = [
            'status' => false,
            'data' => null,
        ]; 
        $formatted_response_data = $journeyList; 
        if ($formatted_response_data) {
        $formatted_response['status'] = true;
        $formatted_response['data'] = $formatted_response_data;
        }  
        if (isset($formatted_response_data[0][0])) {
        $formatted_response_data = $formatted_response_data[0][0];
        } elseif (isset($formatted_response_data[0])) {
        $formatted_response_data = $formatted_response_data[0];
        } 
        $formatted_response['data'] = $formatted_response_data; 
        return $formatted_response;
    }

      private function get_ticket_request($data)
    {
        $request = null;  
        $params = $this->format_ticket_request($data);   
        $get_request = "AirBookRQ";
        $request = $this->get_request($params, 'Book_ticket(EaseMyTrip Flight)',$get_request);  
        return $request;
    }

    // Actually this below function is for book ticket , 
    // EMT do not have holdbooking option which i have received API collection.


    public function getpreBookData($token, $app_reference, $pax_details)
    { 
        $tokenData = $this->read_cache_record($token);

        if (empty($tokenData['segments'])) {
            return [
                'status' => false,
                'data' => null
            ];
        }

        $rsp = $tokenData['segments'];
        $fare = $rsp['Fare'] ?? null;

        if (!$fare) {
            return [
                'status' => false,
                'data' => null
            ];
        } 

        $newTokenData = $tokenData;
        $newTokenData['pax_details'] = ['Passengers' => $pax_details];
        $newTokenData['app_reference'] = $app_reference; 

        $newToken = 'emt_pre_book_token_' . generate_uuid();
        $this->insert_cache_record($newToken, $newTokenData);
 
        $segmentsGroup = [];

        if (isset($rsp['Bonds'])) {
            foreach ($rsp['Bonds'] as $bond) { 
                $totalDeuration= $bond['JourneyTime'];
                $segmentsGroup[] = $this->format_search_flight_details(['Bonds' => [$bond]]);
            }
        } else {
            $segmentsGroup[] = $this->format_search_flight_details($rsp);
        } 

        $Price = $this->format_itineray_price_details($fare, $rsp);
 
        $fareAttributes = $this->format_fare_attributes_easemytrip($rsp);

        $Attr = [
            'TestMode'      => ($this->booking_source_system == 'test'),
            'IsRefundable'  => $fare['IsRefundable'] ?? 0,
            'AirlineRemark' => $rsp['Remark'] ?? "",
            'BrandName'     => $rsp['Remark'] ?? "",
            'IsLCC'         => false,
            'fareAttributes'=> $fareAttributes
        ];
 
        $formatted_response = [
            'FlightDetails' => $segmentsGroup,
            "totalDuration" => $totalDeuration,
            'Price'         => $Price,
            'Attr'          => $Attr,
            'HoldTicket'    => $rsp['IsHoldBooking'] ?? false,
            'ResultToken'   => $this->get_flight_result_token($newToken),
            'APIIDENTIFY'   => EASE_MY_TRIP_BOOKING_SOURCE
        ]; 
        return [
            'status' => true,
            'data'   => $formatted_response
        ];
    }


    public function getBookingData($token)
    {
        $tokenData = $this->read_cache_record($token); 
        return $tokenData;
    }


    public function hold_ticket($params)
    { 
        $ResultToken = $params;  
        $tokenData = $this->read_cache_record($ResultToken);  
        $request_handle[EASE_MY_TRIP_BOOKING_SOURCE] = $this->get_ticket_request($tokenData);  
        $result = $this->CI->curlmultihandler->execute($request_handle);  
        // $result[EASE_MY_TRIP_BOOKING_SOURCE] = '{"BookingDetail":{"CurrencyCode":"INR","PaymentAmount":13624,"PnrDetail":{"Pnr":[{"Destination":"KWI","EngineID":7,"Origin":"DEL","PNR":"DW1596","TYPE":0},{"Destination":"KWI","EngineID":7,"Origin":"DEL","PNR":"DBAJRA","TYPE":1},{"Destination":"KWI","EngineID":7,"Origin":"DEL","PNR":"32GILT","TYPE":3},{"Destination":"KWI","EngineID":7,"Origin":"DEL","PNR":"32GILU","TYPE":4},{"Destination":"KWI","EngineID":7,"Origin":"DEL","PNR":"IN","TYPE":10},{"Destination":"KWI","EngineID":7,"Origin":"DEL","PNR":"","TYPE":5},{"Destination":"KWI","EngineID":7,"Origin":"DEL","PNR":"13624","TYPE":6},{"Destination":"KWI","EngineID":7,"Origin":"DEL","PNR":"","TYPE":9}],"Tickets":[{"Destination":"KWI","EngineID":7,"FirstName":"Mohan","IsBaggBooked":false,"IsMealBooked":false,"IsSeatBooked":false,"LastName":"Kumar","Origin":"DEL","PassengerNumber":1,"PaxType":0,"Prefix":"MR","TicketNumber":"135570381","TicketNumberCopy":""}]},"TravelInsurance":null},"BookingId":"","BookingStatus":0,"CheckInStatus":false,"CodeShareFlightStatus":false,"EMTTransactionId":"EMT600257337","Errors":null,"PayStatus":0,"PaymentUrl":null,"PnrQueueStatus":false,"PostAddonStatus":false,"SSRDetails":[],"SearchDetails":null,"TransactionId":"693ff0af7305d#EMT600257337"}';
        $fare_qoute_response = $result[EASE_MY_TRIP_BOOKING_SOURCE]; 
        $json_response = json_decode($fare_qoute_response,true);  
        $response = @$json_response;

        $formatted_response = [
            'status' => false,
            'data' => null,
        ];
 
        if (!empty($response) && $response['BookingDetail'] !== null) {
            $formatted_response['data'] = $this->format_hold_ticket_response($response,$tokenData);
            $formatted_response['data']['app_reference'] = $tokenData['app_reference'];
            if ($formatted_response['data']) {
                $formatted_response['status'] = true;
            }
        
        $booking_id= array(); 
        $pass_details= $formatted_response['data']['PassengerDetails'];
         
        $booking_id = $this->CI->api_model->save_passenger_tickets($tokenData['app_reference'],$pass_details);  
        }  
        return $formatted_response;
    }
    private function format_ticket_request($params)
{
    $trip_type_map = [
        'oneway' => 0,
        'return' => 1,
        'multicity' => 2
    ];

    $cabin_map = [
        'Economy' => 0,
        'Premium Economy' => 1,
        'Business' => 2,
        'First' => 3
    ];

    $gender_map = [
        "Male" => 1, "Female" => 2, "M" => 1, "F" => 2
    ];

    $paxTypeMap = [
        'Adult'  => 0,
        'Child'  => 1,
        'Infant' => 2
    ]; 

     
    $Traveller = [
        "AdultTraveller"  => [],
        "ChildTraveller"  => [],
        "InfantTraveller" => []
    ];

    foreach ($params["pax_details"]["Passengers"] as $pax) {

        $pax_data = [
            "DOB"                => $pax["DateOfBirth"],
            "LastName"           => $pax["LastName"],
            "ResidentCountry"    => $pax["CountryName"],
            "Title"              => $pax["Title"],
            "Nationality"        => $pax["Nationality"],
            "MiddleName"         => "",
            "Gender"             => $gender_map[$pax["Gender"]] ?? 1,
            "EmailAddress"       => $pax["Email"],
            "FrequentFlierNumber"=> "",
            "FirstName"          => $pax["FirstName"],
            "CountryCode"        => "IN",
            "PassportExpiryDate" => $pax["PassportExpiry"],
            "PassportNo"         => $pax["PassportNumber"],
            "MobileNumber"       => $pax["ContactNo"]
        ];

        $type = strtolower($pax["PaxType"]);
        if ($type === "adult")  $Traveller["AdultTraveller"][]  = $pax_data;
        if ($type === "child")  $Traveller["ChildTraveller"][]  = $pax_data;
        if ($type === "infant") $Traveller["InfantTraveller"][] = $pax_data;
    }

    $Traveller = array_filter($Traveller);

    
    $allowed_leg_fields = [
        "AircraftCode","AirlineName","ArrivalDate","ArrivalTime","AvailableSeat",
        "BaggageUnit","BaggageWeight","BoundType","Cabin","CarrierCode",
        "DepartureDate","DepartureTime","Destination","Duration",
        "FareClassOfService","FlightDetailRefKey","FlightName",
        "FlightNumber","Group","OperatedBy","Origin","ProviderCode"
    ];

    $filter_leg = function ($leg) use ($allowed_leg_fields) {
   
        return array_intersect_key($leg, array_flip($allowed_leg_fields));
    };

     
    $Bonds = [];
    foreach ($params["segments"]["Bonds"] as $bond) {
        $Bonds[] = [
            "BoundType"     => $bond["BoundType"],
            "IsBaggageFare" => (bool)($bond["IsBaggageFare"] ?? false),
  
            "Legs"          => array_map($filter_leg, $bond["Legs"])
        ];
    }
 
    $SSRDetails = [];
    $paxCounter = [0 => 1, 1 => 1, 2 => 1]; 
    $segments   = $params["segments"]["Bonds"];

    foreach ($params["pax_details"]["Passengers"] as $pax) {

        $paxType = $paxTypeMap[$pax['PaxType']] ?? 0;

        if ($paxType === 2 || empty($pax['SeatDetails'])) {
            continue;
        }

        foreach ($pax['SeatDetails'] as $segmentIndex => $segmentSeats) {
            foreach ($segmentSeats as $legIndex => $legSeat) {

                if (empty($segments[$segmentIndex]['Legs'][$legIndex])) continue;

                $bond = $segments[$segmentIndex];
 
                if (count($bond['Legs']) > 1) {
                    continue;
                }

                if (empty($legSeat['SeatKey'])) continue;

                $seat_data = @unserialize(base64_decode($legSeat['SeatKey']));
                if (empty($seat_data[0])) continue;

                $seat = $seat_data[0];
                $leg  = $bond['Legs'][$legIndex];

                $SSRDetails[] = [
                    "FlightNo"     => trim($leg['FlightNumber']),
                    "AirlineCode"  => $leg['CarrierCode'],
                    "Origin"       => $leg['Origin'],
                    "Destination"  => $leg['Destination'],
                    "PaxType"      => $paxType,
                    "PaxId"        => $paxCounter[$paxType]++,
                    "SCode"        => str_replace('-', '', $seat['Code']),
                    "SChargeCode"  => str_replace('-', '', $seat['Code']),
                    "SCharge"      => (float)$seat['apiPrice'],
                    "SStatus"      => true,
                    "BoundType"    => $bond['BoundType']
                ];
            }
        }
    }

     
    $BookSegment = [[
        "BondType"        => "OutBound",
        "Bonds"           => $Bonds,
        "EngineID"        => $params["segments"]["EngineID"],
        "Fare"            => $params["segments"]["Fare"],
        "FareRule"        => $params["segments"]["FareRule"] ?? "",
        "IsBaggageFare"   => (bool)$params["segments"]["IsBaggageFare"],
        "IsInternational" => (bool)$params["segments"]["IsInternational"],
        "IsRoundTrip"     => (bool)$params["segments"]["IsRoundTrip"],
        "ItineraryKey"    => $params["segments"]["ItineraryKey"],
        "SearchId"        => $params["segments"]["SearchId"]
    ]];
 
    if (!empty($SSRDetails)) {
        $BookSegment[0]["SSRDetails"] = $SSRDetails;
    }

     
    $search = $params["search_data"];

    $FlightSearchDetails = [[
        "BeginDate"   => substr($search["depature"], 0, 10),
        "Origin"      => $search["from"],
        "Destination" => $search["to"]
    ]];

    if (!empty($search["return"])) {
        $FlightSearchDetails[] = [
            "BeginDate"   => substr($search["return"], 0, 10),
            "Origin"      => $search["to"],
            "Destination" => $search["from"]
        ];
    }

     
    $data = [
        "Authentication" => [
            "UserName"  => $this->config['UserName'],
            "Password"  => $this->config['Password'],
            "IpAddress" => "10.10.10.10",
            "PortalID"  => 26
        ],
        "BookSegment"         => $BookSegment,
        "EngineID"            => $params["segments"]["EngineID"],
        "EngineIDList"        => [$params["segments"]["EngineID"]],
        "PaymentDetails" => [
            "BookingCurrencyCode" => $params["segments"]["CurrencyCode"],
            "BookingAmount"       => $params["segments"]["Fare"]["TotalFareWithOutMarkUp"]
        ],
        "FlightSearchDetails" => $FlightSearchDetails,
        "Cabin"               => $cabin_map[$search['cabin_class']] ?? 0,
        "TripType"            => $trip_type_map[$search['trip_type']] ?? 0,
        "TraceId"             => $params["TraceId"],
        "TransactionId"       => uniqid(),
        "Traveller"           => $Traveller
    ];

    return json_encode($data, JSON_PRETTY_PRINT);
}

  /* private function format_ticket_request($params)
    {
        $trip_type_map = [
            'oneway' => 0,
            'return' => 1,
            'multicity' => 2
        ];

        $cabin_map = [
            'Economy' => 0,
            'Premium Economy' => 1,
            'Business' => 2,
            'First' => 3
        ];

        $gender_map = [
            "Male" => 1, "Female" => 2, "M" => 1, "F" => 2
        ];

        $paxTypeMap = [
            'Adult'  => 0,
            'Child'  => 1,
            'Infant' => 2
        ]; 
        $Traveller = [
            "AdultTraveller"  => [],
            "ChildTraveller"  => [],
            "InfantTraveller" => []
        ];

        foreach ($params["pax_details"]["Passengers"] as $pax) {

            $pax_data = [
                "DOB"                => $pax["DateOfBirth"],
                "LastName"           => $pax["LastName"],
                "ResidentCountry"    => $pax["CountryName"],
                "Title"              => $pax["Title"],
                "Nationality"        => $pax["Nationality"],
                "MiddleName"         => "",
                "Gender"             => $gender_map[$pax["Gender"]] ?? 1,
                "EmailAddress"       => $pax["Email"],
                "FrequentFlierNumber"=> "",
                "FirstName"          => $pax["FirstName"],
                "CountryCode"        => "IN",
                "PassportExpiryDate" => $pax["PassportExpiry"],
                "PassportNo"         => $pax["PassportNumber"],
                "MobileNumber"       => $pax["ContactNo"]
            ];

            $type = strtolower($pax["PaxType"]);
            if ($type === "adult")  $Traveller["AdultTraveller"][]  = $pax_data;
            if ($type === "child")  $Traveller["ChildTraveller"][]  = $pax_data;
            if ($type === "infant") $Traveller["InfantTraveller"][] = $pax_data;
        }

        $Traveller = array_filter($Traveller);
 
        $allowed_leg_fields = [
            "AircraftCode","AirlineName","ArrivalDate","ArrivalTime","AvailableSeat",
            "BaggageUnit","BaggageWeight","BoundType","Cabin","CarrierCode",
            "DepartureDate","DepartureTime","Destination","Duration",
            "FareClassOfService","FlightDetailRefKey","FlightName",
            "FlightNumber","Group","OperatedBy","Origin","ProviderCode"
        ];

        $filter_leg = function ($leg) use ($allowed_leg_fields) {
            $filtered = array_intersect_key($leg, array_flip($allowed_leg_fields));
 
            if (!empty($leg['AircraftType'])) {
                $filtered['AircraftCode'] = (string)$leg['AircraftType'];
            }

            return $filtered;
        };
 
        $Bonds = [];
        foreach ($params["segments"]["Bonds"] as $bond) {
            $Bonds[] = [
                "BoundType"     => $bond["BoundType"],
                "IsBaggageFare" => (bool)($bond["IsBaggageFare"] ?? false),
                "IsSSR"         => true, 
                "Legs"          => array_map($filter_leg, $bond["Legs"])
            ];
        }
 
        $SSRDetails = [];
        $paxCounter = [0 => 1, 1 => 1, 2 => 1]; 
        $segments   = $params["segments"]["Bonds"];

        foreach ($params["pax_details"]["Passengers"] as $pax) {

            $paxType = $paxTypeMap[$pax['PaxType']] ?? 0;
 
            if ($paxType === 2 || empty($pax['SeatDetails'])) {
                continue;
            }

            foreach ($pax['SeatDetails'] as $segmentIndex => $segmentSeats) {
                foreach ($segmentSeats as $legIndex => $legSeat) {

                    if (empty($segments[$segmentIndex]['Legs'][$legIndex])) continue;

                    $bond = $segments[$segmentIndex];
                    $leg  = $bond['Legs'][$legIndex];
 
                    if (count($bond['Legs']) > 1 || empty($legSeat['SeatKey'])) {
                        continue;
                    }

                    $seat_data = @unserialize(base64_decode($legSeat['SeatKey']));
                    if (empty($seat_data[0])) continue;

                    $seat = $seat_data[0];

                    $SSRDetails[] = [
                        "FlightNo"     => trim($leg['FlightNumber']),
                        "AirlineCode"  => $leg['CarrierCode'],
                        "Origin"       => $leg['Origin'],
                        "Destination"  => $leg['Destination'],
                        "PaxType"      => $paxType,
                        "PaxId"        => $paxCounter[$paxType]++,
                        "SCode"        => str_replace('-', '', $seat['Code']),
                        "SChargeCode"  => str_replace('-', '', $seat['Code']),
                        "SCharge"      => (float)$seat['apiPrice'],
                        "SStatus"      => true,
                        "BoundType"    => $bond['BoundType']
                    ];
                }
            }
        }

  
        if (!empty($params["segments"]["Fare"]["PaxFares"])) {
            $params["segments"]["Fare"]["PaxFares"] = [
                $params["segments"]["Fare"]["PaxFares"][0]
            ];
        }
 
        $BookSegment = [[
            "BondType"        => "OutBound",
            "Bonds"           => $Bonds,
            "EngineID"        => $params["segments"]["EngineID"],
            "Fare"            => $params["segments"]["Fare"],
            "FareRule"        => $params["segments"]["FareRule"] ?? "",
            "IsBaggageFare"   => (bool)$params["segments"]["IsBaggageFare"],
            "IsInternational" => (bool)$params["segments"]["IsInternational"],
            "IsRoundTrip"     => (bool)$params["segments"]["IsRoundTrip"],
            "ItineraryKey"    => $params["segments"]["ItineraryKey"],
            "SearchId"        => $params["segments"]["SearchId"]
        ]];

        if (!empty($SSRDetails)) {
            $BookSegment[0]["SSRDetails"] = $SSRDetails;
        }
 
        $search = $params["search_data"];

        $FlightSearchDetails = [[
            "BeginDate"   => substr($search["depature"], 0, 10),
            "Origin"      => $search["from"],
            "Destination" => $search["to"]
        ]];

        if (!empty($search["return"])) {
            $FlightSearchDetails[] = [
                "BeginDate"   => substr($search["return"], 0, 10),
                "Origin"      => $search["to"],
                "Destination" => $search["from"]
            ];
        }
 
        $data = [
            "Authentication" => [
                "UserName"  => $this->config['UserName'],
                "Password"  => $this->config['Password'],
                "IpAddress" => "10.10.10.10",
                "PortalID"  => 26
            ],
            "BookSegment"         => $BookSegment,
            "EngineID"            => $params["segments"]["EngineID"],
            "EngineIDList"        => [$params["segments"]["EngineID"]],
            "PaymentDetails" => [
                "BookingCurrencyCode" => $params["segments"]["CurrencyCode"],
                "BookingAmount"       => $params["segments"]["Fare"]["TotalFareWithOutMarkUp"]
            ],
            "FlightSearchDetails" => $FlightSearchDetails,
            "Cabin"               => $cabin_map[$search['cabin_class']] ?? 0,
            "TripType"            => $trip_type_map[$search['trip_type']] ?? 0,
            "TraceId"             => $params["TraceId"],
            "TransactionId"       => uniqid(),
            "Traveller"           => $Traveller
        ];

        return json_encode($data, JSON_PRETTY_PRINT);
    }*/


    /* 
     private function format_ticket_request($params)
{
    $trip_type_map = [
        'oneway'    => 0,
        'return'    => 1,
        'multicity' => 2
    ];

    $cabin_map = [
        'Economy'          => 0,
        'Premium Economy'  => 1,
        'Business'         => 2,
        'First'            => 3
    ];

    $gender_map = [
        "Male" => 1, "Female" => 2, "M" => 1, "F" => 2
    ];

    $paxTypeMap = [
        'Adult'  => 0,
        'Child'  => 1,
        'Infant' => 2
    ];
 
    $Traveller = [
        "AdultTraveller"  => [],
        "ChildTraveller"  => [],
        "InfantTraveller" => []
    ];

    foreach ($params["pax_details"]["Passengers"] as $pax) {

        $pax_data = [
            "DOB"                => $pax["DateOfBirth"],
            "LastName"           => $pax["LastName"],
            "ResidentCountry"    => $pax["CountryName"],
            "Title"              => $pax["Title"],
            "Nationality"        => $pax["Nationality"],
            "MiddleName"         => "",
            "Gender"             => $gender_map[$pax["Gender"]] ?? 1,
            "EmailAddress"       => $pax["Email"],
            "FrequentFlierNumber"=> "",
            "FirstName"          => $pax["FirstName"],
            "CountryCode"        => "IN",
            "PassportExpiryDate" => $pax["PassportExpiry"],
            "PassportNo"         => $pax["PassportNumber"],
            "MobileNumber"       => $pax["ContactNo"]
        ];

        $type = strtolower($pax["PaxType"]);
        if ($type === "adult")  $Traveller["AdultTraveller"][]  = $pax_data;
        if ($type === "child")  $Traveller["ChildTraveller"][]  = $pax_data;
        if ($type === "infant") $Traveller["InfantTraveller"][] = $pax_data;
    }

    $Traveller = array_filter($Traveller);
 
    $allowed_leg_fields = [
        "AircraftCode","AircraftType","AirlineName",
        "ArrivalDate","ArrivalTime","ArrivalTerminal",
        "AvailableSeat","BaggageUnit","BaggageWeight",
        "Baggages","BoundType","Cabin","CabinBagUT","CabinBagWT",
        "CarrierCode","CurrencyCode","DepartureDate",
        "DepartureTime","DepartureTerminal","Destination",
        "Duration","FareBasisCode","FareClassOfService",
        "FlightDetailRefKey","FlightName","FlightNumber","Origin"
    ];

    $filter_leg = function ($leg) use ($allowed_leg_fields) {
        $filtered = array_intersect_key($leg, array_flip($allowed_leg_fields));
        $filtered['AircraftCode'] = (string)($leg['AircraftType'] ?? '');
        return $filtered;
    };
 
    $Bonds = [];
    foreach ($params["segments"]["Bonds"] as $bond) {
        $Bonds[] = [
            "BoundType"     => $bond["BoundType"],
            "IsBaggageFare" => (bool)$bond["IsBaggageFare"],
            "IsSSR"         => false,
            "ItineraryKey"  => null,
            "JourneyTime"   => $bond["JourneyTime"] ?? null,
            "Legs"          => array_map($filter_leg, $bond["Legs"]),
            "addOnDetail"   => null
        ];
    } 
    $SSRDetails = [];
    $paxCounter = [0 => 1, 1 => 1, 2 => 1];

    foreach ($params["pax_details"]["Passengers"] as $pax) {

        $paxType = $paxTypeMap[$pax['PaxType']] ?? 0;

        if ($paxType === 2 || empty($pax['SeatDetails'])) {
            continue;
        }

        foreach ($pax['SeatDetails'] as $segmentIndex => $segmentSeats) {
            foreach ($segmentSeats as $legIndex => $legSeat) {

                if (empty($params["segments"]["Bonds"][$segmentIndex]['Legs'][$legIndex])) continue;
                if (empty($legSeat['SeatKey'])) continue;

                $seat_data = @unserialize(base64_decode($legSeat['SeatKey']));
                if (empty($seat_data[0])) continue;

                $seat = $seat_data[0];
                $leg  = $params["segments"]["Bonds"][$segmentIndex]['Legs'][$legIndex];

                $SSRDetails[] = [
                    "FlightNo"    => $leg['FlightNumber'],
                    "AirlineCode" => $leg['CarrierCode'],
                    "Origin"      => $leg['Origin'],
                    "Destination" => $leg['Destination'],
                    "PaxType"     => $paxType,
                    "PaxId"       => $paxCounter[$paxType]++,
                    "SCode"       => str_replace('-', '', $seat['Code']),
                    "SChargeCode" => str_replace('-', '', $seat['Code']),
                    "SCharge"     => (float)$seat['apiPrice'],
                    "SStatus"     => true,
                    "BoundType"   => $params["segments"]["Bonds"][$segmentIndex]['BoundType']
                ];
            }
        }
    }
 
    $BookSegment = [[
        "AddonBundle"    => 0,
        "BondType"       => "OutBound",
        "Bonds"          => $Bonds,
        "CurrencyCode"   => $params["segments"]["CurrencyCode"],
        "EngineID"       => $params["segments"]["EngineID"],
        "Fare"           => $params["segments"]["Fare"],
        "FareRule"       => $params["segments"]["FareRule"] ?? "",
        "IsBaggageFare"  => (bool)$params["segments"]["IsBaggageFare"],
        "IsInternational"=> (bool)$params["segments"]["IsInternational"],
        "IsRoundTrip"    => (bool)$params["segments"]["IsRoundTrip"],
        "ItineraryKey"   => $params["segments"]["ItineraryKey"]
    ]];

    if (!empty($SSRDetails)) {
        $BookSegment[0]["SSRDetails"] = $SSRDetails;
    }
 
    $search = $params["search_data"];

    $FlightSearchDetails = [[
        "BeginDate"   => substr($search["depature"], 0, 10),
        "Origin"      => $search["from"],
        "Destination" => $search["to"]
    ]];

    if ($search['trip_type'] === 'return') {
        $FlightSearchDetails[] = [
            "BeginDate"   => substr($search["return"], 0, 10),
            "Origin"      => $search["to"],
            "Destination" => $search["from"]
        ];
    } 
    $data = [
        "Authentication" => [
            "UserName"  => $this->config['UserName'],
            "Password"  => $this->config['Password'],
            "IpAddress" => "10.10.10.10",
            "PortalID"  => 26
        ],
        "BookSegment"         => $BookSegment,
        "PaymentDetails" => [
            "BookingCurrencyCode" => $params["segments"]["CurrencyCode"],
            "BookingAmount"       => $params["segments"]["Fare"]["TotalFareWithOutMarkUp"]
        ],
        "FlightSearchDetails" => $FlightSearchDetails,
        "Cabin"               => $cabin_map[$search['cabin_class']] ?? 0,
        "TripType"            => $trip_type_map[$search['trip_type']] ?? 0,
        "TraceId"             => $params["TraceId"],
        "TransactionId"       => uniqid(),
        "Traveller"           => $Traveller
    ]; 
    return json_encode($data, JSON_PRETTY_PRINT);
}
// for multipax    
*/ 


    

  /*private function format_ticket_request($params)
    { 
        $trip_type_map = [
            'oneway' => 0,
            'return' => 1,
            'multicity' => 2
        ];

        $cabin_map = [
            'Economy' => 0,
            'Premium Economy' => 1,
            'Business' => 2,
            'First' => 3
        ];

        $gender_map = [
            "Male" => 1, "Female" => 2, "M" => 1, "F" => 2
        ];
 
        $Traveller = [
            "AdultTraveller"  => [],
            "ChildTraveller"  => [],
            "InfantTraveller" => []
        ];

        foreach ($params["pax_details"]["Passengers"] as $pax) {

            $pax_data = [
                "DOB"                => $pax["DateOfBirth"],
                "LastName"           => $pax["LastName"],
                "ResidentCountry"    => $pax["CountryName"],
                "Title"              => $pax["Title"],
                "Nationality"        => $pax["Nationality"],
                "MiddleName"         => "",
                "Gender"             => $gender_map[$pax["Gender"]] ?? 1,
                "EmailAddress"       => $pax["Email"],
                "FrequentFlierNumber"=> "",
                "FirstName"          => $pax["FirstName"],
                "CountryCode"        => "IN",
                "PassportExpiryDate" => $pax["PassportExpiry"],
                "PassportNo"         => $pax["PassportNumber"],
                "MobileNumber"       => $pax["ContactNo"]
            ];

            $type = strtolower($pax["PaxType"]);
            if ($type == "adult")  $Traveller["AdultTraveller"][]  = $pax_data;
            if ($type == "child")  $Traveller["ChildTraveller"][]  = $pax_data;
            if ($type == "infant") $Traveller["InfantTraveller"][] = $pax_data;
        }

        $Traveller = array_filter($Traveller);
 
        $allowed_leg_fields = [
            "AircraftCode","AirlineName","ArrivalDate","ArrivalTime","AvailableSeat",
            "BaggageUnit","BaggageWeight","BoundType","Cabin","CarrierCode",
            "DepartureDate","DepartureTime","Destination","Duration",
            "FareClassOfService","FlightDetailRefKey","FlightName",
            "FlightNumber","Group","OperatedBy","Origin","ProviderCode"
        ];

        $filter_leg = fn($leg) => array_intersect_key($leg, array_flip($allowed_leg_fields));

        $Bonds = [];
        foreach ($params["segments"]["Bonds"] as $bond) {
            $Bonds[] = [
                "BoundType"     => $bond["BoundType"],
                "IsBaggageFare" => (bool)($bond["IsBaggageFare"] ?? false),
                "Legs"          => array_map($filter_leg, $bond["Legs"])
            ];
        }
 
        $SSRDetails = [];
        $paxCounter = [1 => 1, 2 => 1];  
        $segments   = $params["segments"]["Bonds"]; 
        if (!empty($params["Passengers"])) {

            foreach ($params["Passengers"] as $pax) { 
                if ($pax['PaxType'] == 3 || empty($pax['SeatDetails'])) {
                    continue;
                }

                foreach ($pax['SeatDetails'] as $segmentIndex => $segmentSeats) {
                    foreach ($segmentSeats as $legIndex => $legSeat) {

                        if (empty($legSeat['SeatKey'])) continue;

                        $seat_data = @unserialize(base64_decode($legSeat['SeatKey']));
                        if (empty($seat_data[0])) continue;

                        if (empty($segments[$segmentIndex]['Legs'][$legIndex])) continue;

                        $seat = $seat_data[0];
                        $bond = $segments[$segmentIndex];
                        $leg  = $bond['Legs'][$legIndex];

                        $SSRDetails[] = [
                            "FlightNo"    => trim($leg['FlightNumber']),
                            "AirlineCode" => $leg['CarrierCode'],
                            "Origin"      => $leg['Origin'],
                            "Destination" => $leg['Destination'],
                            "PaxType"     => ($pax['PaxType'] == 1 ? 0 : 1),
                            "PaxId"       => $paxCounter[$pax['PaxType']]++,
                            "SCode"       => str_replace('-', '', $seat['Code']),
                            "SChargeCode" => str_replace('-', '', $seat['Code']),
                            "SCharge"     => (float)$seat['Price'],
                            "SStatus"     => true,
                            "BoundType"   => $bond['BoundType']
                        ];
                    }
                }
            }
        } 
        $BookSegment = [[
            "BondType"        => "OutBound",
            "Bonds"           => $Bonds,
            "EngineID"        => $params["segments"]["EngineID"],
            "Fare"            => $params["segments"]["Fare"],
            "FareRule"        => $params["segments"]["FareRule"] ?? "",
            "IsBaggageFare"   => (bool)$params["segments"]["IsBaggageFare"],
            "IsInternational" => (bool)$params["segments"]["IsInternational"],
            "IsRoundTrip"     => (bool)$params["segments"]["IsRoundTrip"],
            "ItineraryKey"    => $params["segments"]["ItineraryKey"],
            "SearchId"        => $params["segments"]["SearchId"]
        ]];
 
        if (!empty($SSRDetails)) {
            $BookSegment[0]["SSRDetails"] = $SSRDetails;
        }
 
        $search = $params["search_data"];
        $FlightSearchDetails = [[
            "BeginDate"   => substr($search["depature"], 0, 10),
            "Origin"      => $search["from"],
            "Destination" => $search["to"]
        ]];

        if (!empty($search["return"])) {
            $FlightSearchDetails[] = [
                "BeginDate"   => substr($search["return"], 0, 10),
                "Origin"      => $search["to"],
                "Destination" => $search["from"]
            ];
        }
 
        $data = [
            "Authentication" => [
                "UserName"  => $this->config['UserName'],
                "Password"  => $this->config['Password'],
                "IpAddress" => "10.10.10.10",
                "PortalID"  => 26
            ],
            "BookSegment"          => $BookSegment,
            "EngineID"             => $params["segments"]["EngineID"],
            "EngineIDList"         => [$params["segments"]["EngineID"]],
            "PaymentDetails" => [
                "BookingCurrencyCode" => $params["segments"]["CurrencyCode"],
                "BookingAmount"       => $params["segments"]["Fare"]["TotalFareWithOutMarkUp"]
            ],
            "FlightSearchDetails"  => $FlightSearchDetails,
            "Cabin"                => $cabin_map[$search['cabin_class']] ?? 0,
            "TripType"             => $trip_type_map[$search['trip_type']] ?? 0,
            "TraceId"              => $params["TraceId"],
            "TransactionId"        => uniqid(),
            "Traveller"            => $Traveller
        ]; 
        return json_encode($data, JSON_PRETTY_PRINT);
    }*/

       /*private function format_ticket_request($params)
        {
             
            $trip_type_map = [
                'oneway' => 0,
                'return' => 1,
                'multicity' => 2
            ];

            $cabin_map = [
                'Economy' => 0,
                'Premium Economy' => 1,
                'Business' => 2,
                'First' => 3
            ];

            $gender_map = [
                "Male"   => 1,
                "Female" => 2,
                "M"      => 1,
                "F"      => 2
            ];
 
            $allowed_leg_fields = [
                "AircraftCode","AirlineName","ArrivalDate","ArrivalTime","AvailableSeat",
                "BaggageUnit","BaggageWeight","BoundType","Cabin","CarrierCode","DepartureDate",
                "DepartureTime","Destination","Duration","FareClassOfService","FlightDetailRefKey",
                "FlightName","FlightNumber","Group","OperatedBy","Origin","ProviderCode"
            ];

            $filter_leg = function($leg) use ($allowed_leg_fields) {
                return array_intersect_key($leg, array_flip($allowed_leg_fields));
            };

             
            $Traveller = [
                "AdultTraveller" => [],
                "ChildTraveller" => [],
                "InfantTraveller" => []
            ];

            foreach ($params["pax_details"]["Passengers"] as $pax) {

                $pax_data = [
                    "DOB"                => $pax["DateOfBirth"],
                    "LastName"           => $pax["LastName"],
                    "ResidentCountry"    => $pax["CountryName"],
                    "Title"              => $pax["Title"],
                    "Nationality"        => $pax["Nationality"],
                    "MiddleName"         => "",
                    "Gender"             => $gender_map[$pax["Gender"]] ?? 1,
                    "EmailAddress"       => $pax["Email"],
                    "FrequentFlierNumber"=> "",
                    "FirstName"          => $pax["FirstName"],
                    "CountryCode"        => "IN",
                    "PassportExpiryDate" => $pax["PassportExpiry"],
                    "PassportNo"         => $pax["PassportNumber"],
                    "MobileNumber"       => $pax["ContactNo"]
                ];

                $type = strtolower($pax["PaxType"]);

                if ($type == "adult") {
                    $Traveller["AdultTraveller"][] = $pax_data;
                } elseif ($type == "child") {
                    $Traveller["ChildTraveller"][] = $pax_data;
                } elseif ($type == "infant") {
                    $Traveller["InfantTraveller"][] = $pax_data;
                }
            }

            $Traveller = array_filter($Traveller);

           
            $Bonds = [];

            foreach ($params["segments"]["Bonds"] as $bond) {

                $cleanLegs = array_map($filter_leg, $bond["Legs"]);

                $Bonds[] = [
                    "BoundType"     => $bond["BoundType"],
                    "IsBaggageFare" => (bool)($bond["IsBaggageFare"] ?? false),
                    "Legs"          => $cleanLegs
                ];
            }

            // ----------------------------
            // SINGLE BookSegment (correct EMT format)
            // ----------------------------
            $BookSegment = [
                [
                    "BondType"        => "OutBound",   
                    "Bonds"           => $Bonds,       
                    "EngineID"        => $params["segments"]["EngineID"],
                    "Fare"            => $params["segments"]["Fare"],
                    "FareRule"        => $params["segments"]["FareRule"] ?? "",
                    "IsBaggageFare"   => (bool)($params["segments"]["IsBaggageFare"] ?? false),
                    "IsInternational" => (bool)$params["segments"]["IsInternational"],
                    "IsRoundTrip"     => (bool)$params["segments"]["IsRoundTrip"],
                    "ItineraryKey"    => $params["segments"]["ItineraryKey"],
                    "SearchId"        => $params["segments"]["SearchId"]
                ]
            ];
 
            $search_data = $params["search_data"];

            $FlightSearchDetails = [
                [
                    "BeginDate"   => substr($search_data["depature"], 0, 10),
                    "Origin"      => $search_data["from"],
                    "Destination" => $search_data["to"]
                ]
            ];

            if (!empty($search_data["return"])) {
                $FlightSearchDetails[] = [
                    "BeginDate"   => substr($search_data["return"], 0, 10),
                    "Origin"      => $search_data["to"],
                    "Destination" => $search_data["from"]
                ];
            }
 
            $data = [
                "Authentication" => [
                    "Password"  => $this->config['Password'],
                    "UserName"  => $this->config['UserName'],
                    "IpAddress" => "10.10.10.10",
                    "PortalID"  => 26
                ],

                "BookSegment"   => $BookSegment,

                "EngineID"      => $params["segments"]["EngineID"],
                "EngineIDList"  => [ $params["segments"]["EngineID"] ],

                "PaymentDetails" => [
                    "BookingCurrencyCode" => $params["segments"]["CurrencyCode"],
                    "BookingAmount"       => $params["segments"]["Fare"]["TotalFareWithOutMarkUp"]
                ],

                "FlightSearchDetails" => $FlightSearchDetails,

                "Cabin"         => $cabin_map[$search_data['cabin_class']] ?? 0,
                "TripType"      => $trip_type_map[$search_data['trip_type']] ?? 0,
                "TraceId"       => $params["TraceId"],
                "TransactionId" => uniqid(),

                "Traveller" => $Traveller
            ];

            return json_encode($data, JSON_PRETTY_PRINT);
        }
        emnu: EMTB2B
        ssap: EMT@uytrFYTREt
        LRU : https://stagingapi.easemytrip.com/Flight.svc/json/    
        */

         private function format_booking_request($params)
        {   
            $BookingID = preg_replace('/^EMT/', '', $params['EMTTransactionId']);
            $data = [
                "Authentication" => [
                    "Password"  => $this->config['Password'],
                    "UserName"  => $this->config['UserName'],
                    "IpAddress" => "10.10.10.10",
                    "PortalID"  => 26
                ],

                "RequestType" => "GetFlightBookingDetails",
                "BookingID" => $BookingID,
                "TransactionScreenId" => $params['EMTTransactionId'],
 
            ]; 
            return json_encode($data, JSON_PRETTY_PRINT);
        }

        private function get_booking_request($data)
            {
                $request = null;  
                $params = $this->format_booking_request($data);   
                $get_request = "flightbookingdetail";
                $request = $this->get_request($params, 'Get_Booking(EaseMyTrip Flight)',$get_request);  
                return $request;
            }
    
        
        private function format_hold_ticket_response($response, $segments)
        {   
             /*$request_handle[EASE_MY_TRIP_BOOKING_SOURCE] = $this->get_booking_request($response); 
            $request_handle[EASE_MY_TRIP_BOOKING_SOURCE]['url']="https://stagingapi.easemytrip.com/cancellationjson/api/flightbookingdetail";
            // debug($request_handle);  
            $results = $this->CI->curlmultihandler->execute($request_handle);  
            debug($results); die;*/ 
            // debug($response); die;
            
            
            // GET BOOKING DETAILS starts 
            $request_handle[EASE_MY_TRIP_BOOKING_SOURCE] = $this->get_booking_request($response); 
            // $request_handle[EASE_MY_TRIP_BOOKING_SOURCE]['url'] = "https://stagingapi.easemytrip.com/cancellationjson/api/flightbookingdetail"; 
            $parsed = parse_url($this->config['EndPointUrl']); 
            $baseUrl = $parsed['scheme'] . '://' . $parsed['host']; 
            $Cancel_url = $baseUrl . '/cancellationjson/api/flightbookingdetail'; 
            $request_handle[EASE_MY_TRIP_BOOKING_SOURCE]['url'] = $Cancel_url;


            $handleData = $request_handle[EASE_MY_TRIP_BOOKING_SOURCE];

            $url = $handleData['url'];
            $postfields = $handleData['postfields'] ?? $handleData['requestBody'] ?? '{}';
            $headers = $handleData['header'] ?? [
                "Content-Type: application/json",
                "Accept: application/json",
                "User-Agent: PHP-cURL"
            ];
            $method = strtoupper($handleData['method'] ?? 'POST');
            $sslVerify = $handleData['ssl_verify'] ?? false; 
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
            } 
            $response_get_booking_detail = curl_exec($ch); 
            curl_close($ch); 
            
            // GET BOOKING DETAILS ends

            $ticket = json_decode($response_get_booking_detail, true);
   
            $TicketPassengers = $ticket['passengerDetails'] ?? [];
            $TicketTxnID =$ticket['transactionScreenId'] ?? ""; 
            $BookingDetail = $response['BookingDetail'] ?? [];
            $PnrDetails    = $BookingDetail['PnrDetail']['Pnr'] ?? [];
            $Tickets       = $BookingDetail['PnrDetail']['Tickets'] ?? [];
            $Fare          = $segments['segments']['Fare'] ?? []; 

            $GdsPnr = $PnrDetails[0]['PNR'] ?? "";
            // $BookingId = $response['BookingId'] ?? "";
            $BookingId = $ticket['transactionScreenId'] ?? "";
            

            if (!$GdsPnr) {
                return null;
            }
             
            $PassengerDetails = [];
            $passengerMap = []; 
            foreach ($TicketPassengers as $p) {
        
                $uniqueKey = $p['firstName'] . "_" . $p['lastName'] . "_" . $p['paxId'];

                if (!isset($passengerMap[$uniqueKey])) {

                    $passengerMap[$uniqueKey] = [
                        'PassengerId'    => $p['paxId'],
                        'PassengerType'  => $p['paxType'],     
                        'Title'          => $p['title'] ?? "",
                        'FirstName'      => $p['firstName'],
                        'LastName'       => $p['lastName'],
                        'DateOfBirth'    => "",
                        'Gender'         => "",
                        'TicketNumber'   => [],
                        'TicketId'       => $TicketTxnID,
                    ];
                } 
                if (!empty($p['ticketNumber'])) { 
                    $passengerMap[$uniqueKey]['TicketNumber'] = $p['ticketNumber'];
                }
            } 
            $PassengerDetails = array_values($passengerMap); 
            $segmentList = $segments['segments'];
            $flightDetails = []; 
            if (isset($segmentList['Bonds'])) {
                foreach ($segmentList['Bonds'] as $bond) {
                    $flightDetails[] = $this->format_search_flight_details(['Bonds' => [$bond]]);
                }
            }

            $Price = $this->format_itineray_price_details($Fare, $segments['search_data']); 
            $resultToken = 'EMT_hold_ticket_' . generate_uuid();

            $resultTokenData = [
                'segments'          => $segments,
                'PNR'               => $GdsPnr,
                'BookingResponse'   => $response,
                'EMTTransactionId'  => $response['EMTTransactionId']
            ];

            $this->insert_cache_record($resultToken, $resultTokenData);

            $formatted_response = [
                'BookingId' => $BookingId,
                'PNR'       => $GdsPnr,
                'GDSPNR'    => $GdsPnr,
                'hold_time' => "",

                'PassengerDetails' => $PassengerDetails, 

                'JourneyList' => [
                    'FlightDetails' => [
                        'Details' => [$flightDetails]
                    ],
                ],

                'Price' => $Price,

                'Attr' => [
                    'TestMode'      => ($this->booking_source_system == 'test'),
                    'IsRefundable'  => $Price['PassengerBreakup']['ADT']['Refundable'],
                    'AirlineRemark' => "",
                    'IsLCC'         => false,
                ],

                'ResultToken' => $this->get_flight_result_token($resultToken),
                'APIIDENTIFY' => EASE_MY_TRIP_BOOKING_SOURCE
            ];    
            return $formatted_response;
        }

 
        private function format_issue_ticket_response($response)
        {  
             
            $segments = $response['segments']['segments']['Bonds'] ?? []; 
            $segmentLegs = [];
            foreach ($segments as $bond) {
                foreach ($bond['Legs'] as $leg) {
                    $segmentLegs[] = $leg;
                }
            }

            $PNR     = $response['BookingResponse']['BookingDetail']['PnrDetail']['Pnr'][0]['PNR'] ?? ""; 
            $Tickets = $response['BookingResponse']['BookingDetail']['PnrDetail']['Tickets'] ?? [];

            if (empty($Tickets)) {
                return null;
            }

            $formatted = [
                'PNR' => $PNR,
                'Passengers' => [],
            ];
 
            $pnrList = $response['BookingResponse']['BookingDetail']['PnrDetail']['Pnr'] ?? [];
            $routePnrMap = [];  

            foreach ($pnrList as $entry) {
                $routeKey = strtoupper($entry['Origin'] . '-' . $entry['Destination']);
 
                if ($entry['TYPE'] == 0 && !empty($entry['PNR'])) {
                    $routePnrMap[$routeKey] = $entry['PNR'];
                }
            }
 
            $passengerMap = [];

            foreach ($Tickets as $t) {

                $key = $t['FirstName'] . '_' . $t['LastName'] . '_' . $t['PassengerNumber'];
 
                if (!isset($passengerMap[$key])) { 
                     
                    $gender = null;
                    if (!empty($t['Prefix'])) {
                        if (strtoupper($t['Prefix']) == 'MR') {
                            $gender = 'Male';
                        } elseif (strtoupper($t['Prefix']) == 'MS') {
                            $gender = 'Female';
                        }
                    }

                    $passengerMap[$key] = [
                        'Title'     => $t['Prefix'] ?: "",
                        'FirstName' => $t['FirstName'],
                        'LastName'  => $t['LastName'],
                        'PaxType'   => $t['PaxType'] == 1 ? "CHD" : "ADT",
                        'DOB'       => null,
                        'Gender'    => $gender,
                        'Tickets'   => []
                    ];
                } 
                $matchedSegment = null;

                foreach ($segmentLegs as $leg) {
                    if (
                        strtoupper($leg['Origin']) === strtoupper($t['Origin']) &&
                        strtoupper($leg['Destination']) === strtoupper($t['Destination'])
                    ) {
                        $matchedSegment = $leg;
                        break;
                    }
                }

                $MarketingCarrier      = $matchedSegment['CarrierCode'] ?? null;
                $MarketingFlightNumber = $matchedSegment['FlightNumber'] ?? null;

                 
                $DepartureTime = null;
                if (!empty($matchedSegment['DepartureDate']) && !empty($matchedSegment['DepartureTime'])) {
                    $DepartureTime = date(
                        'Y-m-d\TH:i:00',
                        strtotime($matchedSegment['DepartureDate'] . ' ' . $matchedSegment['DepartureTime'])
                    );
                }
 
                $routeKey = strtoupper($t['Origin'] . '-' . $t['Destination']);
                $AirlinePnr = $routePnrMap[$routeKey] ?? $PNR;

                 
                $passengerMap[$key]['Tickets'][] = [
                    'Origin'                => $t['Origin'],
                    'Destination'           => $t['Destination'],
                    'DepartureTime'         => $DepartureTime,
                    'MarketingCarrier'      => $MarketingCarrier,
                    'MarketingFlightNumber' => $MarketingFlightNumber,
                    'TicketId'              => $t['TicketNumber'],
                    'TicketNumber'          => $t['TicketNumber'],
                    'IssueDate'             => date('Y-m-d H:i:s'),
                    'AirlinePnr'            => $AirlinePnr, 
                ];
            }

            $formatted['Passengers'] = array_values($passengerMap);  
            return $formatted;
        }

 
    public function issue_ticket($ResultToken)
    {
        $tokenData = $this->read_cache_record($ResultToken); 
        $result[EASE_MY_TRIP_BOOKING_SOURCE] =$tokenData; 
        $response = @$result[EASE_MY_TRIP_BOOKING_SOURCE]; 

        $formatted_response = [
            'status' => false,
            'data' => null,
        ];

        if ($response) { 
                $formatted_response['status'] = true;
                $formatted_response['data'] = $this->format_issue_ticket_response($response); 
            } 
        return $formatted_response;
    }
 
    private function replace_attributes_recursive(&$arr)
    {
        if (is_array($arr)) {
            foreach ($arr as $key => &$value) {
                if (is_array($value)) { 
                    if (isset($value['@attributes']) && is_array($value['@attributes'])) {
                         
                        $value = array_merge($value, $value['@attributes']);
                        unset($value['@attributes']);
                    } 
                    $this->replace_attributes_recursive($value);
                }
            }
        }
    }

    // Extra sevices Starts

    function get_extra_services($ResultToken){ 
        $formatted_response['status'] = FAILURE_STATUS;  
        $formatted_response['message'] = '';  
        $formatted_response['data'] = [];  

        $tokenData = $this->read_cache_record($ResultToken);
        $request_handle['seat'] = $this->get_extra_service_request($tokenData); 
        $result = $this->CI->curlmultihandler->execute($request_handle); 
        $json_response = json_decode($result['seat'],true); 
        $response = @$json_response['Seats'];

        $formatted_response = [
            'status' => false,
            'data' => null,
        ];

        $formated_seat_data = $this->get_formated_seat_data($response);

        if ($response) {
            $formatted_response = [
                'ExtraServiceDetails' => [
                    'Meals' => [],
                    'Baggage' => [],
                    'Seat' => $formated_seat_data,
                ],
            ];
        }
        return $formatted_response;

    }
     private function get_extra_service_request($data)
    {
        $request = null; 
        $params = $this->format_get_extra_service_request($data); 
        $request_type ="GetAddonsService";
        $request = $this->get_request($params, 'seat_request(EMT Flight)',$request_type); 
        return $request;
    }

    private function format_get_extra_service_request($params)
    {
        $authentication = [
            "UserName"  => $this->config['UserName'],
            "Password"  => $this->config['Password'],
            "IpAddress" => "10.10.10.10"
        ];

         
        $rawSegments = $params['segments'];

        if (isset($rawSegments['Bonds'])) { 
            $rawSegments = [ $rawSegments ];
        }

        $segments = [];

        foreach ($rawSegments as $segment) {
 
            if (!empty($segment['Bonds']) && is_array($segment['Bonds'])) {
                foreach ($segment['Bonds'] as &$bond) {
                    $bond['IsSSR'] = true;
                    $bond['IsBaggageFare'] = false;
                }
                unset($bond);
            }
 
            if (!empty($segment['Fare'])) {
                $segment['Fare'] = [
                    "BasicFare"              => $segment['Fare']['BasicFare'] ?? 0,
                    "TotalTaxWithOutMarkUp"  => $segment['Fare']['TotalTaxWithOutMarkUp'] ?? 0,
                    "TotalFareWithOutMarkUp" => $segment['Fare']['TotalFareWithOutMarkUp'] ?? 0
                ];
            }

            $segment['IsInternational'] = true;

            $segments[] = $segment;
        }

        $request = [
            "AdultCount"     => (int)($params['search_data']['adult_config'] ?? 1),
            "ChildCount"     => (int)($params['search_data']['child_config'] ?? 0),
            "InfantCount"    => (int)($params['search_data']['infant_config'] ?? 0),
            "Authentication" => $authentication,
            "TraceID"        => $params['TraceId'],
            "Segments"       => $segments
        ];

        return json_encode($request, JSON_PRETTY_PRINT);
    }
 
    public function get_formated_seat_data($seat_res)
    { 
        $flightSeats = [];

        if (empty($seat_res) || !is_array($seat_res)) {
            return $flightSeats;
        }

        foreach ($seat_res as $segment) {

            $segmentSeatMap = [];

            $flightNumber = trim($segment['FlightNumber'] ?? '');
            $origin       = $segment['Origin'] ?? '';
            $destination  = $segment['Destination'] ?? '';
            $airlineCode  = $segment['AirlineCode'] ?? '';

            $planColumns = $segment['PlanColumn'] ?? [];

            if (empty($segment['LstRow'])) {
                continue;
            }

            foreach ($segment['LstRow'] as $rowData) {

                $rowNumber  = (string)($rowData['RowNumber'] ?? '');
                $rowSeatMap = [];

                foreach ($planColumns as $colIndex => $planColumn) {
 
                    if ($planColumn === '') {
                        $rowSeatMap[] = ['type_asile' => 'Asile'];
                        continue;
                    }

                    $seat = $rowData['lstColumn'][$colIndex] ?? [];

                    $seatStatus = $seat['SeatStatus'] ?? 0;
                    $seatCode   = $rowNumber . '-' . $planColumn; 
                    $isAvailable = (
                        !empty($seat['SeatNumber']) &&
                        isset($seat['SeatStatus']) &&
                        $seatStatus == 3
                    );

                    $price = !empty($seat['SeatFare']['TotalFare'])
                        ? (float)$seat['SeatFare']['TotalFare']
                        : 0;

                    $seat_id = [
                        [
                            'Type'        => 'dynamic',
                            'Code'        => $seatCode,
                            'Origin'      => $origin,
                            'Destination' => $destination,
                            'Price'       => $this->parseAmount($price),
                            'apiPrice'    => $price,
                            'SStatus'     => $isAvailable ? 1 : 0,
                            'FlightNo'    => $flightNumber,
                            'AirlineCode' => $airlineCode,
                        ]
                    ];

                    $rowSeatMap[] = [
                        'FlightNumber'    => $flightNumber,
                        'Origin'          => $origin,
                        'Destination'     => $destination,
                        'AirlineCode'     => $airlineCode,
                        'RowNumber'       => $rowNumber,
                        'SeatNumber'      => $seatCode,
                        'AvailablityType' => $isAvailable ? 1 : 0,
                        'Price'           => $this->parseAmount($price),
                        'SeatId'          => base64_encode(serialize($seat_id)),
                    ];
                }

                if (!empty($rowSeatMap)) {
                    $segmentSeatMap[] = $rowSeatMap;
                }
            }

            if (!empty($segmentSeatMap)) {
                $flightSeats[] = $segmentSeatMap;
            }
        } 
        return $flightSeats;
    }
    // Extra sevices Ends

    // Cancellation starts
     
    public function cancel_booking($app_reference, $api_booking_id, $api_email_id)
    {
        $parsed   = parse_url($this->config['EndPointUrl']);
        $baseUrl  = $parsed['scheme'] . '://' . $parsed['host'];

        $request_handle = [];

        //GET AUTH KEY
        $authParams = $this->format_get_GetAuthKey_request(
            $app_reference,
            $api_booking_id,
            $api_email_id
        );

        $authRequest = $this->get_request(
            $authParams,
            'cancel_booking_auth(EMT Flight)',
            'GetAuthKey'
        );

        $authRequest['url'] = $baseUrl . '/cancellationjson/api/GetAuthKey';
        $request_handle[EASE_MY_TRIP_BOOKING_SOURCE] = $authRequest; 
        $authResult = $this->CI->curlmultihandler->execute($request_handle);
        $authResponse = json_decode($authResult[EASE_MY_TRIP_BOOKING_SOURCE], true); 

        if (empty($authResponse['authKey'])) {
            throw new \Exception('AuthKey not returned. Cannot proceed with cancellation.');
        }

        $BId = $authResponse['authKey']; 

        //GET BOOKING DETAILS
        $bookingDetailParams = $this->format_get_booking_detail_request(
            $BId,
            $api_booking_id,
            $api_email_id
        );

        $bookingDetailRequest = $this->get_request(
            $bookingDetailParams,
            'get_booking_details(EMT Flight)',
            'flightbookingdetailv1'
        );

        $bookingDetailRequest['url'] = $baseUrl . '/cancellationjson/api/flightbookingdetailv1';
        $request_handle[EASE_MY_TRIP_BOOKING_SOURCE] = $bookingDetailRequest; 
        $bookingResult = $this->CI->curlmultihandler->execute($request_handle); 
        $bookingResponse = json_decode($bookingResult[EASE_MY_TRIP_BOOKING_SOURCE], true); 
        
        if (empty($bookingResponse)) {
            throw new \Exception('Booking details not found. Cannot proceed with cancellation.');
        }

        //CANCEL BOOKING
        $cancelParams = $this->format_cancel_booking_request(
            $BId,
            $bookingResponse,  
            $api_booking_id,
            $api_email_id
        );

        $cancelRequest = $this->get_request(
            $cancelParams,
            'cancel_booking_final(EMT Flight)',
            'cancelv1'
        );

        $cancelRequest['url'] = $baseUrl . '/cancellationjson/api/cancelv1';
        $request_handle[EASE_MY_TRIP_BOOKING_SOURCE] = $cancelRequest; 
        $cancelResult = $this->CI->curlmultihandler->execute($request_handle); 
        // $cancelResult[EASE_MY_TRIP_BOOKING_SOURCE] ='{"isRequested":true,"isCancelled":true,"isRefunded":false,"RequestId":400641,"msg":"Dear Customer, As per your instructions, your booking has been cancelled. Your refund will be processed soon.","IpAddress":"10.10.10.10","ResTime":0.0}';
        $cancelResponse = json_decode($cancelResult[EASE_MY_TRIP_BOOKING_SOURCE], true);

        return $cancelResponse;
    } 

    public function format_get_booking_detail_request($BId, $api_booking_id, $api_email_id)
    {
        $authentication = [
            "UserName"  => $this->config['UserName'],
            "Password"  => $this->config['Password'],
            "IpAddress" => "10.10.10.10",
            "PortalID"  => 26
        ];

        $request = [
            "Authentication"      => $authentication,
            "BId"                 => $BId,
            "EmailId"             => $api_email_id,
            "transactionScreenId" => $api_booking_id
        ];

        // return json_encode($request, JSON_PRETTY_PRINT);
        return json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    }
    public function format_cancel_booking_request($BId, $bookingResponse, $api_booking_id, $api_email_id)
    { 

        $authentication = [
            "UserName"  => $this->config['UserName'],
            "Password"  => $this->config['Password'],
            "IpAddress" => "10.10.10.10",
            "SubUserId" => $api_email_id
        ];

        $outBoundPaxIds = [];
        $inBoundPaxIds  = [];

        if (!empty($bookingResponse['passengerDetails'])) {
            foreach ($bookingResponse['passengerDetails'] as $pax) {

                if ($pax['tripType'] === 'OutBound') {
                    $outBoundPaxIds[] = $pax['paxId'];
                }

                if ($pax['tripType'] === 'InBound') {
                    $inBoundPaxIds[] = $pax['paxId'];
                }
            }
        }

        // Remove duplicates
        /*$outBoundPaxIds = array_unique($outBoundPaxIds);
        $inBoundPaxIds  = array_unique($inBoundPaxIds);

        $request = [
            "Authentication"       => $authentication,
            "outBoundPaxIds"       => implode(',', $outBoundPaxIds),
            "inBoundPaxIds"        => implode(',', $inBoundPaxIds),
            "isPartialCancel"      => false,
            "mode"                 => "1",
            "remark"               => "Full PNR cancellation",
            "BId"                  => $BId,
            "EmailId"              => $api_email_id,
            "transactionScreenId"  => $api_booking_id
        ]; */
        $outBoundPaxIds = array_unique(array_filter($outBoundPaxIds));
        $inBoundPaxIds  = array_unique(array_filter($inBoundPaxIds));

        $request = [
            "Authentication"       => $authentication,
            "outBoundPaxIds"       => implode('-', $outBoundPaxIds),
            "inBoundPaxIds"        => implode('-', $inBoundPaxIds),
            "isPartialCancel"      => false,
            "mode"                 => "1",
            "remark"               => "Full PNR cancellation",
            "BId"                  => $BId,
            "EmailId"              => $api_email_id,
            "transactionScreenId"  => $api_booking_id
        ];


        return json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /*public function format_cancel_booking_request($BId, $bookingResponse, $api_booking_id, $api_email_id)
        {
            $authentication = [
                "UserName"  => $this->config['UserName'],
                "Password"  => $this->config['Password'],
                "IpAddress" => "10.10.10.10",
                "SubUserId" => $api_email_id
            ]; 
            
            $paxIds = [];
            if (!empty($bookingResponse['passengerDetails'])) {
                foreach ($bookingResponse['passengerDetails'] as $pax) {
                    $paxIds[] = $pax['paxId'];
                }
            }

            $paxIds = array_unique($paxIds);

            $request = [
                "Authentication" => $authentication,
                "outBoundPaxIds" => implode(',', $paxIds),
                "isPartialCancel" => false,
                "mode"           => "1",
                "remark"         => "Full PNR cancellation",
                "BId"            => $BId,
                "EmailId"        => $api_email_id,
                "transactionScreenId" => $api_booking_id
            ]; 
            return json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }*/

    public function format_get_GetAuthKey_request(
            $app_reference,
            $api_booking_id,
            $api_email_id
        ) {
            $authentication = [
                "UserName"  => $this->config['UserName'],
                "Password"  => $this->config['Password'],
                "IpAddress" => "10.10.10.10",
                "PortalID"  => 26
            ];

            $request = [
                "Authentication"      => $authentication,
                "EmailId"             => $api_email_id,
                "transactionScreenId" => $api_booking_id 
            ];

            return json_encode($request, JSON_PRETTY_PRINT);
        } 
     // Cancellation ends.   

    public function get_authentication_details(){

         $authentication = [
                "UserName"  => $this->config['UserName'],
                "Password"  => $this->config['Password'],
                "IpAddress" => "10.10.10.10",
                "PortalID"  => 26
            ];
        return $authentication;    

    }

}
//////////////////////////////////////////////////////////////////////////////////////////////
multcurlhandler.php
<?php if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class CurlMultiHandler
{

    protected $CI;
    protected $cachedResponses;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->cachedResponses = [];
    }

    protected function getCacheKey(string $url, string $requestBody = ''): string
    {
        return md5($url . '|' . $requestBody);
    }

    protected function getCachedResponse(string $key)
    {
        $cached = $this->CI->redis_server->read_string($key);

        if ($cached === false || $cached === null || $cached === '') {
            return null;
        }
        $decoded = json_decode($cached, true);
        // If json_decode fails or returns null for empty string, return null
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        // If decoded value is an empty string, return null to force fresh request
        if ($decoded === '') {
            return null;
        }
        return $decoded;
    }

    protected function setCachedResponse(string $key, $value, $ttl = 24 * 60 * 60): void
    {
        // Only cache non-empty responses
        if ($value === null || $value === '' || $value === false) {
            return;
        }
        $encoded = json_encode($value);
        // Only store if encoding was successful
        if ($encoded !== false && $encoded !== 'null' && $encoded !== '""') {
            $this->CI->redis_server->store_string($key, $encoded, $ttl);
        }
    }


    protected function initializeMultiHandle()
    {
        return curl_multi_init();
    }

    /**
     * Add one or multiple curl handles to the multi handle
     * Stores API request info in DB, returns array of insert IDs
     */
    protected function addHandles(&$multi, array $curlHandles): array
    {
        $requestInsertIds = [];

        foreach ($curlHandles as $provider => $curlHandle) {
            if (is_array($curlHandle) && array_is_list($curlHandle)) {
                foreach ($curlHandle as $k => $_curlHandle) {
                    $this->storeAndAddHandles($_curlHandle, $provider, $multi, $requestInsertIds, $k);
                }
            } else {
                $this->storeAndAddHandles($curlHandle, $provider, $multi, $requestInsertIds);
            }
        }
        return $requestInsertIds;
    }


    private function storeAndAddHandles($curlHandle, $provider, &$multi, &$requestInsertIds, $index = null)
    {
        $ch = $curlHandle['ch'];
        $requestData = $curlHandle['requestBody'] ?? '';
        $apiUrl = $curlHandle['url'] ?? '';
        $remarks = $curlHandle['remarks'] ?? '';


        $setCache = @$curlHandle['setCache'] ?? false;
        $cacheTtl = @$curlHandle['cacheTtl'] ?? null;

        $cachedResponse = null;

        if ($setCache && $cacheTtl) {
            $cacheKey = $this->getCacheKey($apiUrl, $requestData);
            $cachedResponse = $this->getCachedResponse($cacheKey);
            
            // Only use cached response if it's not null and not empty
            if ($cachedResponse !== null && $cachedResponse !== '' && $cachedResponse !== false) {
                // store cached response, skip curl handle
                if ($index !== null) {
                    $this->cachedResponses[$provider][$index] = $cachedResponse;
                    $requestInsertIds[$provider][$index] = null; // no DB insert needed
                    return;
                } else {
                    $this->cachedResponses[$provider] = $cachedResponse;
                    $requestInsertIds[$provider] = null; // no DB insert needed
                    return;
                }
            }
        }

        $insert = $this->CI->api_model->store_api_request($apiUrl, $requestData, $remarks);

        if ($index !== null) {
            $requestInsertIds[$provider][$index] = intval(@$insert['insert_id']);
        } else {
            $requestInsertIds[$provider] = intval(@$insert['insert_id']);
        }


        curl_multi_add_handle($multi, $ch);
    }

    /**
     * Execute the multi curl handler until all are done
     */
    protected function executeMultiHandle($multi)
    {
        $running = null;
        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi);
        } while ($running > 0);
    }


    public function streamExecute(array $curlHandles, callable $onResponse): void
    {
        $multi = $this->initializeMultiHandle();
        $requestInsertIds = $this->addHandles($multi, $curlHandles);

        // Return cached responses immediately before making API calls
        if (!empty($this->cachedResponses)) {
            foreach ($this->cachedResponses as $provider => $cachedResponse) {
                if (is_array($cachedResponse) && array_is_list($cachedResponse)) {
                    // Handle array of responses (multiple requests for same provider)
                    foreach ($cachedResponse as $index => $response) {
                        if ($response !== null && $response !== '' && $response !== false) {
                            $onResponse($provider, $response);
                        }
                    }
                } else {
                    // Handle single response
                    if ($cachedResponse !== null && $cachedResponse !== '' && $cachedResponse !== false) {
                        $onResponse($provider, $cachedResponse);
                    }
                }
            }
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);

            while ($info = curl_multi_info_read($multi)) {
                $ch = $info['handle'];
                $response = curl_multi_getcontent($ch);

                // find provider + update DB + cache
                foreach ($curlHandles as $provider => $handle) {
                    if (is_array($handle) && array_is_list($handle)) {
                        // Handle array of handles
                        foreach ($handle as $index => $_handle) {
                            if (isset($_handle['ch']) && $_handle['ch'] === $ch) {
                                $insertId = $requestInsertIds[$provider][$index] ?? null;

                                // DB update
                                if ($insertId) {
                                    $this->CI->api_model->update_api_response($response, $insertId);
                                }

                                // cache
                                $apiUrl = $_handle['url'] ?? '';
                                $requestData = $_handle['requestBody'] ?? '';
                                $setCache = @$_handle['setCache'] ?? false;
                                $cacheTtl = @$_handle['cacheTtl'] ?? null;

                                if ($setCache && $cacheTtl) {
                                    $cacheKey = $this->getCacheKey($apiUrl, $requestData);
                                    $this->setCachedResponse($cacheKey, $response, $cacheTtl); 
                                }

                                // stream response up
                                $onResponse($provider, $response);

                                break 2; // Break out of both loops
                            }
                        }
                    } else if (isset($handle['ch']) && $handle['ch'] === $ch) {
                        $insertId = $requestInsertIds[$provider] ?? null;

                        // DB update
                        if ($insertId) {
                            $this->CI->api_model->update_api_response($response, $insertId);
                        }

                        // cache
                        $apiUrl = $handle['url'] ?? '';
                        $requestData = $handle['requestBody'] ?? '';
                        $setCache = @$handle['setCache'] ?? false;
                        $cacheTtl = @$handle['cacheTtl'] ?? null;

                        if ($setCache && $cacheTtl) {
                            $cacheKey = $this->getCacheKey($apiUrl, $requestData);
                            $this->setCachedResponse($cacheKey, $response, $cacheTtl); 
                        }

                        // stream response up
                        $onResponse($provider, $response);

                        break;
                    }
                }

                curl_multi_remove_handle($multi, $ch);
                curl_close($ch);
            }

            curl_multi_select($multi);
        } while ($running > 0);

        curl_multi_close($multi);
    }


    /**
     * Process responses, update DB, close individual handles
     */
    protected function processResponses(&$multi, array $curlHandles, array $requestInsertIds): array
    {
        $responses = $this->cachedResponses ?? [];

        foreach ($curlHandles as $provider => $curlHandle) {
            
            if (is_array($curlHandle) && array_is_list($curlHandle)) {
                foreach ($curlHandle as $k => $_curlHandle) {
                    $this->updateResponse($_curlHandle, $provider, $multi, $responses, $requestInsertIds, $k);
                }
            } else {
                $this->updateResponse($curlHandle, $provider, $multi, $responses, $requestInsertIds);
            }
        }

        return $responses;
    }

    private function updateResponse($curlHandle, $provider, &$multi, &$responses, $requestInsertIds, $index = null)
    {
        if ($index !== null) {
            // if response already cached, skip
            if (isset($responses[$provider][$index])) {
                return;
            }
        } else {
            // if response already cached, skip
            if (isset($responses[$provider])) {
                return;
            }
        }

        $ch = $curlHandle['ch'];
        $response = curl_multi_getcontent($ch);

        if ($index !== null) {
            $responses[$provider][$index] = $response;
            $insertId = $requestInsertIds[$provider][$index] ?? null;
        } else {
            $responses[$provider] = $response;
            $insertId = $requestInsertIds[$provider] ?? null;
        }

        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);

        if ($insertId) {
            $this->CI->api_model->update_api_response($response, $insertId);
        }

        // Save to cache
        $apiUrl = $curlHandle['url'] ?? '';
        $requestData = $curlHandle['requestBody'] ?? '';
        $setCache = @$curlHandle['setCache'] ?? false;
        $cacheTtl = @$curlHandle['cacheTtl'] ?? null;

        if ($setCache && $cacheTtl) {
            $cacheKey = $this->getCacheKey($apiUrl, $requestData);
            $this->setCachedResponse($cacheKey, $response, $cacheTtl);
        }
    }

    public function execute(array $curlHandles): array
    {
        $multi = $this->initializeMultiHandle();

        $requestInsertIds = $this->addHandles($multi, $curlHandles);

        $this->executeMultiHandle($multi);

        $responses = $this->processResponses($multi, $curlHandles, $requestInsertIds);

        curl_multi_close($multi);

        return $responses;
    }
}
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
markup.php
<?php

/**
 *
 * @package    Yiron Technologies
 * @subpackage Markup
 * @author     Balasab
 * @version    V1
 */
class Markup
{
    public function __construct()
    {
        $this->ci = &get_instance();
        $this->ci->load->model('markup_model');
    }

    public function get_markup_details($booking_source, $trip_type, $airline_code, $from_airport_code, $to_airport_code, $classes, $rbd, $departure_date, $arrival_date, $total_price)
    {
         
        $markup_data = $GLOBALS['CI']->markup_model->get_markup_details();
        // debug($markup_data); die;
        $markup = [];
        $markup['admin_markup'] = 0;
        $markup['agent_markup'] = 0;
        $markup['total_markup'] = 0;

        $currentDate = date('Y-m-d');
        // debug($markup_data); die;
        /*foreach ($markup_data as $key => $value) {
            $departureDate = $this->convertToDate($value['departure_date']);
            $arrivalDate = $this->convertToDate($value['arrival_date']);
            $markupStartDate = $this->convertToDate($value['markup_start_date']);
            $markupEndDate = $this->convertToDate($value['markup_end_date']);

            $dateStatus = FAILURE_STATUS;

            if ((empty($departureDate) || $departureDate == 'All') && (empty($arrivalDate) || $arrivalDate == 'All') && (empty($markupStartDate) || $markupStartDate == 'All') && (empty($markupEndDate) || $markupEndDate == 'All')) {
                $dateStatus = SUCCESS_STATUS;
            } elseif ((empty($departureDate) || $departureDate == 'All') && (!empty($arrivalDate) && $arrivalDate >= $arrival_date) && (empty($markupStartDate) || $markupStartDate == 'All') && (empty($markupEndDate) || $markupEndDate == 'All')) {
                $dateStatus = SUCCESS_STATUS;
            } elseif ((empty($arrivalDate) || $arrivalDate == 'All') && (!empty($departureDate) && $departureDate <= $departure_date) && (empty($markupStartDate) || $markupStartDate == 'All') && (empty($markupEndDate) || $markupEndDate == 'All')) {
                $dateStatus = SUCCESS_STATUS;
            } elseif ((empty($arrivalDate) || $arrivalDate == 'All') && (!empty($markupStartDate) && $markupStartDate <= $currentDate) && (empty($departureDate) || $departureDate == 'All') && (empty($markupEndDate) || $markupEndDate == 'All')) {
                $dateStatus = SUCCESS_STATUS;
            } elseif ((empty($arrivalDate) || $arrivalDate == 'All') && (!empty($markupEndDate) && $markupEndDate >= $currentDate) && (empty($departureDate) || $departureDate == 'All') && (empty($markupStartDate) || $markupStartDate == 'All')) {
                $dateStatus = SUCCESS_STATUS;
            } elseif (!empty($departureDate) && $departureDate <= $departure_date && (!empty($arrivalDate) && $arrivalDate >= $arrival_date) && (empty($markupStartDate) || $markupStartDate == 'All') && (empty($markupEndDate) || $markupEndDate == 'All')) {
                $dateStatus = SUCCESS_STATUS;
            } elseif (!empty($markupStartDate) && $markupStartDate <= $currentDate && (!empty($markupEndDate) && $markupEndDate >= $currentDate) && (empty($departureDate) || $departureDate == 'All') && (empty($arrivalDate) || $arrivalDate == 'All')) {
                $dateStatus = SUCCESS_STATUS;
            } elseif (!empty($markupStartDate) && $markupStartDate <= $currentDate && (!empty($departureDate) && $departureDate <= $departure_date) && (empty($markupEndDate) || $markupEndDate == 'All') && (empty($arrivalDate) || $arrivalDate == 'All')) {
                $dateStatus = SUCCESS_STATUS;
            } elseif (!empty($markupEndDate) && $markupEndDate >= $currentDate && (!empty($arrivalDate) && $arrivalDate >= $arrival_date) && (empty($markupStartDate) || $markupStartDate == 'All') && (empty($departureDate) || $departureDate == 'All')) {
                $dateStatus = SUCCESS_STATUS;
            } elseif (!empty($markupStartDate) && $markupStartDate <= $currentDate && (!empty($arrivalDate) && $arrivalDate >= $arrival_date) && (!empty($markupEndDate) && $markupEndDate >= $currentDate) && (empty($departureDate) || $departureDate == 'All')) {
                $dateStatus = SUCCESS_STATUS;
            }
 
            if ($dateStatus === SUCCESS_STATUS) {
                $new_data[] = $value;
            }
        }*/

            foreach ($markup_data as $key => $value) {
                $departureDate = $this->convertToDate($value['departure_date']); // markup departure date
                $arrivalDate = $this->convertToDate($value['arrival_date']);     // markup arrival date
                $markupStartDate = $this->convertToDate($value['markup_start_date']);
                $markupEndDate = $this->convertToDate($value['markup_end_date']);

                $dateStatus = FAILURE_STATUS;

                // Example condition simplified:
                if (
                    (empty($departureDate) || $departureDate == 'All') &&
                    (empty($arrivalDate) || $arrivalDate == 'All') &&
                    (empty($markupStartDate) || $markupStartDate == 'All') &&
                    (empty($markupEndDate) || $markupEndDate == 'All')
                ) {
                    $dateStatus = SUCCESS_STATUS;

                } elseif (
                    (!empty($departureDate) && $departureDate <= $departure_date) &&
                    (!empty($arrivalDate) && $arrivalDate >= $arrival_date)
                ) {
                    $dateStatus = SUCCESS_STATUS;

                } elseif (
                    (!empty($markupStartDate) && $markupStartDate <= $currentDate) &&
                    (!empty($markupEndDate) && $markupEndDate >= $currentDate)
                ) {
                    $dateStatus = SUCCESS_STATUS;

                } // etc. other conditions...

                if ($dateStatus === SUCCESS_STATUS) {
                    $new_data[] = $value;
                }
            }  
        if (valid_array($new_data)) {
            $api_data = $this->filter_markup($new_data, 'api', $booking_source);
        }
        if (valid_array($api_data)) {
            $trip_data = $this->filter_markup($api_data, 'trip_type', $trip_type);
        } 
        if (valid_array($trip_data)) {
            $class_data = $this->filter_markup($trip_data, 'class', $classes);
        }
        if (valid_array($class_data)) {
            $rbd_data = $this->filter_markup($class_data, 'rbd', $rbd);
        }

        if (valid_array($rbd_data)) {
            $from_data = $this->filter_markup($rbd_data, 'from', $from_airport_code);
        }
        if (valid_array($from_data)) {
            $to_data = $this->filter_markup($from_data, 'to', $to_airport_code);
        }
        if (valid_array($to_data)) {
            $markup_data = $this->filter_markup($to_data, 'airline', $airline_code);
        }
 
        if (valid_array($markup_data)) {
            $new_markp_without_priority = [];
            $new_markp_with_priority = [];
            foreach ($markup_data as $key_markup => $m_value) {
                if ($m_value['priority'] == 0) {
                    $new_markp_without_priority[] = $m_value;
                } else {
                    $new_markp_with_priority[] = $m_value;
                }
            }

            $new_markp = [];
            if (valid_array($new_markp_with_priority)) {
                $filter = array_column($new_markp_with_priority, 'priority');
                $data_neww = [];
                array_multisort($filter, SORT_ASC, $new_markp_with_priority);
                $new_markp = $new_markp_with_priority;
            } else {
                $filter = array_column($new_markp_without_priority, 'markup_id');
                $data_neww = [];
                array_multisort($filter, SORT_DESC, $new_markp_without_priority);
                $new_markp = $new_markp_without_priority;
            }
        }
        // apply priority each field
        // debug($new_markp);
        // $airline_data = $this->priority_markup($from_airport_code,$to_airport_code,$airline_code,$booking_source,$trip_type,$classes,$rbd,$departure_date,$arrival_date,$currentDate,$markup_data);
        // debug($airline_data); die;

        if (valid_array($new_markp)) {
            foreach ($new_markp as $keye => $values) {
                if ($values['value_type'] == 'percentage') {
                    $markup['admin_markup'] += ($total_price / 100) * $values['value'];
                } else {
                    $markup['admin_markup'] += $values['value'];
                }
                break;
            }
        }

        $markup['total_markup'] = round($markup['admin_markup'] + $markup['agent_markup'], 2);
         
        return $markup;
    }

    private function filter_markup($new_data, $colum, $val)
    {
        $filters = array_column($new_data, $colum);
        array_multisort($filters, SORT_DESC, $new_data);
        $data_neww = [];
        foreach ($new_data as $key => $value_) {
            if ($value_[$colum] == 'all' || $value_[$colum] == $val || $value_[$colum] == '' || $value_[$colum] == 'All' || $value_[$colum] == 'all' || $value_[$colum] == '0') {
                $data_neww[] = $value_;
            }
        }
        return $data_neww;
    }

    /*private function filter_markup($new_data, $column, $val) {
    $filters = array_column($new_data, $column);
    array_multisort($filters, SORT_DESC, $new_data);

    $data_neww = [];

    foreach ($new_data as $entry) {
        $class_str = trim($entry[$column]);

        // Normalize and explode class string
        $class_array = array_map('trim', explode(',', $class_str));

        // Match if class is empty, "all", or contains the requested class
        if (
            $class_str === '' ||
            in_array(strtolower($class_str), ['all']) ||
            in_array($val, $class_array) ||
            in_array("0", $class_array)
        ) {
            $data_neww[] = $entry;
        }
    }

    return $data_neww;
}*/

    private function filter_markup_priority($new_data, $colum, $value, $col = '', $val = '')
    {
        if ($col != '' && $val != '') {
            $filters = array_column($new_data, $colum);
            $filter = array_column($new_data, $col);
            $data_neww = [];
            array_multisort($filters, SORT_DESC, $filter, SORT_DESC, $new_data);
            foreach ($new_data as $key => $value_) {
                if ($colum == 'departure_date') {
                    $fro_date = 'departure_date';
                    $to_date = 'arrival_date';
                } else {
                    $fro_date = 'markup_start_date';
                    $to_date = 'markup_end_date';
                }
                if ($this->isValidDate($value) == true && $this->isValidDate($val) == true && $this->isValidDate($value_[$fro_date]) == true && $this->isValidDate($value_[$to_date]) == true) {
                    if ($this->convertToDate($value_[$colum]) >= $this->convertToDate($value) && $this->convertToDate($value_[$col]) <= $this->convertToDate($val)) {
                        $data_neww = []; 
                        $data_neww[] = $value_;
                        break;
                    } else {
                        $data_neww[] = $value_;
                    }
                } else {
                    if ($value_[$colum] == $value && $value_[$col] == $val) {
                        $data_neww = [];
                        $data_neww[] = $value_;
                        break;
                    } else {
                        $data_neww[] = $value_;
                    }
                }
            }
        } else {
            $filters = array_column($new_data, $colum);
            $data_neww = [];
            array_multisort($filters, SORT_DESC, $new_data);
            foreach ($new_data as $key => $value_) {
                if ($this->isValidDate($value) == true && $this->isValidDate($value_[$colum]) == true) {
                    if ($colum == 'departure_date' || $colum == 'markup_start_date') {
                        if ($this->convertToDate($value_[$colum]) >= $this->convertToDate($value)) {
                            $data_neww = [];
                            $data_neww[] = $value_;
                            break;
                        } else {
                            $data_neww[] = $value_;
                        }
                    } else {
                        if ($this->convertToDate($value_[$colum]) <= $this->convertToDate($value)) {
                            $data_neww = [];
                            $data_neww[] = $value_;
                            break;
                        } else {
                            $data_neww[] = $value_;
                        }
                    }
                } else {
                    if ($value_[$colum] == $value) {
                        $data_neww = [];
                        $data_neww[] = $value_;
                        break;
                    } else {
                        $data_neww[] = $value_;
                    }
                }
            }
        }
        return $data_neww;
    }

    private function convertToDate($date)
    {
        $dateTime = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateTime) {
            return $dateTime = DateTime::createFromFormat('d/m/Y', $date);
        }

        return $dateTime ? $dateTime->format('Y-m-d') : null;
    }

    private function isValidDate($dateString, $format = 'Y-m-d')
    {
        $date = DateTime::createFromFormat($format, $dateString);
        if ($date && $date->format($format) === $dateString) {
            return true;
        }
        return false;
    }
    private function priority_markup($from_airport_code, $to_airport_code, $airline_code, $booking_source, $trip_type, $classes, $rbd, $departure_date, $arrival_date, $currentDate, $airline_data)
    {
        if (valid_array($airline_data) && count($airline_data) != 1) {
            $airline_data = $this->filter_markup_priority($airline_data, 'from', $from_airport_code, 'to', $to_airport_code);
        }
        if (valid_array($airline_data) && count($airline_data) != 1) {
            $airline_data = $this->filter_markup_priority($airline_data, 'from', $from_airport_code);
        }
        if (valid_array($airline_data) && count($airline_data) != 1) {
            $airline_data = $this->filter_markup_priority($airline_data, 'to', $to_airport_code);
        }
        if (valid_array($airline_data) && count($airline_data) != 1) {
            $airline_data = $this->filter_markup_priority($airline_data, 'airline', $airline_code);
        }
        if (valid_array($airline_data) && count($airline_data) != 1) {
            $airline_data = $this->filter_markup_priority($airline_data, 'api', $booking_source);
        }
        if (valid_array($airline_data) && count($airline_data) != 1) {
            $airline_data = $this->filter_markup_priority($airline_data, 'trip_type', $trip_type);
        }
        if (valid_array($airline_data) && count($airline_data) != 1) {
            $airline_data = $this->filter_markup_priority($airline_data, 'class', $classes);
        }
        if (valid_array($airline_data) && count($airline_data) != 1) {
            $airline_data = $this->filter_markup_priority($airline_data, 'rbd', $rbd);
        }
        if (valid_array($airline_data) && count($airline_data) != 1) {
            $airline_data = $this->filter_markup_priority($airline_data, 'departure_date', $departure_date, 'arrival_date', $arrival_date);
        }
        if (valid_array($airline_data) && count($airline_data) != 1) {
            $airline_data = $this->filter_markup_priority($airline_data, 'departure_date', $departure_date);
        }
        if (valid_array($airline_data) && count($airline_data) != 1) {
            $airline_data = $this->filter_markup_priority($airline_data, 'arrival_date', $arrival_date);
        }
        if (valid_array($airline_data) && count($airline_data) != 1) {
            $airline_data = $this->filter_markup_priority($airline_data, 'markup_start_date', $currentDate, 'markup_end_date', $currentDate);
        }
        if (valid_array($airline_data) && count($airline_data) != 1) {
            $airline_data = $this->filter_markup_priority($airline_data, 'markup_start_date', $currentDate);
        }
        if (valid_array($airline_data) && count($airline_data) != 1) {
            $airline_data = $this->filter_markup_priority($airline_data, 'markup_end_date', $currentDate);
        }
        return $airline_data;
    }
}

  ]
}