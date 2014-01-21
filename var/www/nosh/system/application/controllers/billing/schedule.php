<?php

class Schedule extends Application
{

	function Schedule()
	{
		parent::Application();
		$this->load->database();
		$this->load->library('session');
		$this->load->library('email');
		$this->auth->restrict('billing');
		$this->load->model('practiceinfo_model');
		$this->load->model('schedule_model');
		$this->load->model('audit_model');
	}

	// --------------------------------------------------------------------

	function index()
	{
		$query = $this->practiceinfo_model->getProviders($this->session->userdata('practice_id'));
		
		$data['providers'] = '<option value="">Choose...</option>';
		if ($query->num_rows() > 0) {
			foreach ($query->result_array() as $row) {
				$data['providers'] .= '<option value="' . $row['id'] . '">' . $row['displayname'] . '</option>';
			}
		}
		
		if ($this->session->userdata('provider_id') != '') {
			$data['provider_id'] = '';
		} else {
			$data['provider_id'] = $this->session->userdata('provider_id');
		}
		
		$this->auth->view('billing/schedule', $data);
	}
	
	function schedule_view()
	{
		$query1 = $this->practiceinfo_model->get($this->session->userdata('practice_id'));
		$schedule = $query1->row();
		if ($schedule->weekends == 'yes') {
			$data['weekends'] = 'true';
		} else {
			$data['weekends'] = 'false';
		}
		
		$data['minTime'] = ltrim($schedule->minTime,"0");
		$data['maxTime'] = ltrim($schedule->maxTime,"0");
		
		$this->db->select('visit_type');
		$this->db->where('active', 'y');
		$this->db->where('practice_id', $this->session->userdata('practice_id'));
		$query2 = $this->db->get('calendar');
		$data['visit_type_select'] = '<option value = "">None</option>';
		if ($query2->num_rows() > 0) {
			foreach ($query2->result_array() as $row2) {
				$data['visit_type_select'] .= '<option value="' . $row2['visit_type'] . '">' . $row2['visit_type'] . '</option>';
			}
		}
		$this->db->where('id', $this->session->userdata('provider_id'));
		$provider = $this->db->get('providers')->row_array();
		$data['schedule_increment'] = $provider['schedule_increment'];
		$this->load->view('auth/pages/billing/provider_schedule1', $data);
	}
	
	function exceptions()
	{
		$this->load->view('auth/pages/billing/provider_exceptions');
	}
	
	function set_provider()
	{
		if ($this->session->userdata('provider_id') != '') {
			$this->session->unset_userdata('provider_id');
		}
		$provider_id = $this->input->post('id');
		$this->session->set_userdata('provider_id', $provider_id);
		echo 'Set';
	}
	
	function get_last_provider()
	{
		$result['message'] = "N";
		if ($this->session->userdata('provider_id') != '') {
			$result['message'] = "Y";
			$result['id'] = $this->session->userdata('provider_id');
		}
		echo json_encode($result);
	}
	
	// --------------------------------------------------------------------
	
	function add_closed1($day, $minTime, $day2, $events, $start, $end)
	{
		$repeat_start = strtotime('this ' . $day . ' ' . $minTime, $start); 
		$repeat_end = strtotime('this ' . $day . ' ' . $day2, $start);
		while ($repeat_start <= $end) {
			$repeat_start1 = date('c', $repeat_start);
			$repeat_end1 = date('c', $repeat_end);
			$event1 = array(
				'id' => $day,
				'title' => 'Closed',
				'start' => $repeat_start1,
				'end' => $repeat_end1,
				'className' => 'colorblack',
				'editable' => false,
				'reason' => 'Closed',
				'status' => 'Closed'
			);
			$events[] = $event1;
			$repeat_start = $repeat_start + 604800;
			$repeat_end = $repeat_end + 604800;
		}
		return $events;
	}
	
	function add_closed2($day, $maxTime, $day2, $events, $start, $end)
	{
		$repeat_start = strtotime('this ' . $day . ' ' . $day2, $start); 
		$repeat_end = strtotime('this ' . $day . ' ' . $maxTime, $start);
		while ($repeat_start <= $end) {
			$repeat_start1 = date('c', $repeat_start);
			$repeat_end1 = date('c', $repeat_end);
			$event1 = array(
				'id' => $day,
				'title' => 'Closed',
				'start' => $repeat_start1,
				'end' => $repeat_end1,
				'className' => 'colorblack',
				'editable' => false,
				'reason' => 'Closed',
				'status' => 'Closed'
			);
			$events[] = $event1;
			$repeat_start = $repeat_start + 604800;
			$repeat_end = $repeat_end + 604800;
		}
		return $events;
	}
	
	function add_closed3($day, $minTime, $maxTime, $events, $start, $end)
	{
		$repeat_start = strtotime('this ' . $day . ' ' . $minTime, $start); 
		$repeat_end = strtotime('this ' . $day . ' ' . $maxTime, $start);
		while ($repeat_start <= $end) {
			$repeat_start1 = date('c', $repeat_start);
			$repeat_end1 = date('c', $repeat_end);
			$event1 = array(
				'id' => $day,
				'title' => 'Closed',
				'start' => $repeat_start1,
				'end' => $repeat_end1,
				'className' => 'colorblack',
				'editable' => false,
				'reason' => 'Closed',
				'status' => 'Closed'
			);
			$events[] = $event1;
			$repeat_start = $repeat_start + 604800;
			$repeat_end = $repeat_end + 604800;
		}
		return $events;
	}
	
	// --------------------------------------------------------------------
	
	function exception_list()
	{
		$page = $this->input->post('page');
		$limit = $this->input->post('rows');
		$sidx = $this->input->post('sidx');
		$sord = $this->input->post('sord');
		$provider_id = $this->session->userdata('user_id');
		$query = $this->db->query("SELECT * FROM repeat_schedule WHERE provider_id=$provider_id");
		$count = $query->num_rows(); 
		if($count > 0) { 
			$total_pages = ceil($count/$limit); 
		} else { 
			$total_pages = 0; 
		}
		if ($page > $total_pages) $page=$total_pages;
		$start = $limit*$page - $limit;
		if($start < 0) $start = 0;
		$query1 = $this->db->query("SELECT * FROM repeat_schedule WHERE provider_id=$provider_id ORDER BY $sidx $sord LIMIT $start , $limit");
		$response['page'] = $page;
		$response['total'] = $total_pages;
		$response['records'] = $count;
		$records = $query1->result_array();
		$response['rows'] = $records;
		echo json_encode($response);
		exit( 0 );
	}
	
	function edit_exception_list()
	{
		$data = array(
			'repeat_day' => $this->input->post('repeat_day'),
			'repeat_start_time' => $this->input->post('repeat_start_time'),
			'repeat_end_time' => $this->input->post('repeat_end_time'),
			'title' => $this->input->post('title'),
			'reason' => $this->input->post('reason'),
			'repeat' => '604800',
			'until' => '0',
			'provider_id' => $this->session->userdata('user_id'),
		);
		
		$action = $this->input->post('oper');
							
		if ($action == 'edit')
		{		
			$this->schedule_model->update_repeat($this->input->post('id'), $data);
		}
		
		if ($action == 'add')
		{
			$this->schedule_model->add_repeat($data);
		}
		
		if ($action == 'del')
		{
			$this->schedule_model->del_repeat($this->input->post('id'));
		}
	}
	
	// --------------------------------------------------------------------
	
	function provider_schedule()
	{
		$start = $this->input->post('start'); 
		$end = $this->input->post('end');
		$id = $this->session->userdata('provider_id');
		
		$events = array();
		$query = $this->db->query("SELECT * FROM schedule WHERE provider_id=$id AND start BETWEEN $start AND $end");
		foreach ($query->result_array() as $row) {
			if ($row['visit_type'] != '') {
				$this->db->select('classname');
				$this->db->where('visit_type', $row['visit_type']);
				$this->db->where('practice_id', $this->session->userdata('practice_id'));
				$query1 = $this->db->get('calendar');
				$row1 = $query1->row_array();
				$classname = $row1['classname'];
			} else {
				$classname = 'colorblack';
			}
			if ($row['pid'] == '0') {
				$pid = '';
			} else {
				$pid = $row['pid'];
			}
			if ($row['timestamp'] == '0000-00-00 00:00:00' || $row['user_id'] == '') {
				$timestamp = '';
			} else {
				$this->db->select('displayname');
				$this->db->where('id', $row['user_id']);
				$user_row = $this->db->get('users')->row_array();
				$timestamp = 'Appointment added by ' . $user_row['displayname'] . ' on ' . $row['timestamp'];
			}
			$row_start = date('c', $row['start']);
			$row_end = date('c', $row['end']);
			$event = array(
				'id' => $row['appt_id'],
				'title' => $row['title'],
				'start' => $row_start,
				'end' => $row_end,
				'visit_type' => $row['visit_type'],
				'className' => $classname,
				'provider_id' => $row['provider_id'],
				'pid'=> $pid,
				'reason' => $row['reason'],
				'status' => $row['status'],
				'timestamp' => $timestamp
			);
			$events[] = $event;
		}
		
		$this->db->where('provider_id', $id);
		$query2 = $this->db->get('repeat_schedule');
		foreach ($query2->result_array() as $row2) {
			if ($row2['start'] <= $end || $row2['start'] == "0") {
				if ($row2['repeat'] == "86400") {
					if ($row2['start'] <= $start) {
						$repeat_start = strtotime('this ' . strtolower(date('l', $start)) . ' ' . $row2['repeat_start_time'], $start); 
						$repeat_end = strtotime('this ' . strtolower(date('l', $start)) . ' ' . $row2['repeat_end_time'], $start);
					} else {
						$repeat_start = strtotime('this ' . $row2['repeat_day'] . ' ' . $row2['repeat_start_time'], $start); 
						$repeat_end = strtotime('this ' . $row2['repeat_day'] . ' ' . $row2['repeat_end_time'], $start);
					}
				} else {
					$repeat_start = strtotime('this ' . $row2['repeat_day'] . ' ' . $row2['repeat_start_time'], $start); 
					$repeat_end = strtotime('this ' . $row2['repeat_day'] . ' ' . $row2['repeat_end_time'], $start);
				}
				if ($row2['until'] == '0') {
					while ($repeat_start <= $end) {
						$repeat_id = 'R' . $row2['repeat_id'];
						$until = '';
						if ($row2['reason'] == '') {
							$row2['reason'] = $row2['title'];
						}
						$repeat_start1 = date('c', $repeat_start);
						$repeat_end1 = date('c', $repeat_end);
						$event1 = array(
							'id' => $repeat_id,
							'title' => $row2['title'],
							'start' => $repeat_start1,
							'end' => $repeat_end1,
							'repeat' => $row2['repeat'],
							'until' => $until,
							'className' => 'colorblack',
							'provider_id' => $row2['provider_id'],
							'reason' => $row2['reason'],
							'status' => 'Repeated Event'
						);
						$events[] = $event1;
						$repeat_start = $repeat_start + $row2['repeat'];
						$repeat_end = $repeat_end + $row2['repeat'];
					}
				} else {
					while ($repeat_start <= $end) {
						if ($repeat_start > $row2['until']) {
							break;
						} else {
							$repeat_id = 'R' . $row2['repeat_id'];
							$until = date('m/d/Y', $row2['until']);
							if ($row2['reason'] == '') {
								$row2['reason'] = $row2['title'];
							}
							$repeat_start1 = date('c', $repeat_start);
							$repeat_end1 = date('c', $repeat_end);
							$event1 = array(
								'id' => $repeat_id,
								'title' => $row2['title'],
								'start' => $repeat_start1,
								'end' => $repeat_end1,
								'repeat' => $row2['repeat'],
								'until' => $until,
								'className' => 'colorblack',
								'provider_id' => $row2['provider_id'],
								'reason' => $row2['reason'],
								'status' => 'Repeated Event'
							);
							$events[] = $event1;
							$repeat_start = $repeat_start + $row2['repeat'];
							$repeat_end = $repeat_end + $row2['repeat'];
						}
					}
				}
			}
		}
		
		$query3 = $this->practiceinfo_model->get($this->session->userdata('practice_id'));
		if ($query3->num_rows() > 0) {
			foreach ($query3->result_array() as $row3) {
				$sun_o = $row3['sun_o'];
				$sun_c = $row3['sun_c'];
				$mon_o = $row3['mon_o'];
				$mon_c = $row3['mon_c'];
				$tue_o = $row3['tue_o'];
				$tue_c = $row3['tue_c'];
				$wed_o = $row3['wed_o'];
				$wed_c = $row3['wed_c'];
				$thu_o = $row3['thu_o'];
				$thu_c = $row3['thu_c'];
				$fri_o = $row3['fri_o'];
				$fri_c = $row3['fri_c'];
				$sat_o = $row3['sat_o'];
				$sat_c = $row3['sat_c'];
				$minTime = $row3['minTime'];
				$maxTime = $row3['maxTime'];
			}
			
			$compminTime = strtotime($minTime);
			$compmaxTime = strtotime($maxTime);
		
			if ($sun_o != '') {
				$comp1o = strtotime($sun_o);
				$comp1c = strtotime($sun_c);
				if ($comp1o > $compminTime) {
					$events = $this->add_closed1('sunday', $minTime, $sun_o, $events, $start, $end);
				}
				if ($comp1c < $compmaxTime) {
					$events = $this->add_closed2('sunday', $maxTime, $sun_c, $events, $start, $end);
				}
			} else {
				$events = $this->add_closed3('sunday', $minTime, $maxTime, $events, $start, $end);
			}
		
			if ($mon_o != '') {
				$comp2o = strtotime($mon_o);
				$comp2c = strtotime($mon_c);
				if ($comp2o > $compminTime) {
					$events = $this->add_closed1('monday', $minTime, $mon_o, $events, $start, $end);
				}
				if ($comp2c < $compmaxTime) {
					$events = $this->add_closed2('monday', $maxTime, $mon_c, $events, $start, $end);
				}
			} else {
				$events = $this->add_closed3('monday', $minTime, $maxTime, $events, $start, $end);
			}
		
			if ($tue_o != '') {
				$comp3o = strtotime($tue_o);
				$comp3c = strtotime($tue_c);
				if ($comp3o > $compminTime) {
					$events = $this->add_closed1('tuesday', $minTime, $tue_o, $events, $start, $end);
				}
				if ($comp3c < $compmaxTime) {
					$events = $this->add_closed2('tuesday', $maxTime, $tue_c, $events, $start, $end);
				}
			} else {
				$events = $this->add_closed3('tuesday', $minTime, $maxTime, $events, $start, $end);
			}
		
			if ($wed_o != '') {
				$comp4o = strtotime($wed_o);
				$comp4c = strtotime($wed_c);
				if ($comp4o > $compminTime) {
					$events = $this->add_closed1('wednesday', $minTime, $wed_o, $events, $start, $end);
				}
				if ($comp4c < $compmaxTime) {
					$events = $this->add_closed2('wednesday', $maxTime, $wed_c, $events, $start, $end);
				}
			} else {
				$events = $this->add_closed3('wednesday', $minTime, $maxTime, $events, $start, $end);
			}
		
			if ($thu_o != '') {
				$comp5o = strtotime($thu_o);
				$comp5c = strtotime($thu_c);
				if ($comp5o > $compminTime) {
					$events = $this->add_closed1('thursday', $minTime, $thu_o, $events, $start, $end);
				}
				if ($comp5c < $compmaxTime) {
					$events = $this->add_closed2('thursday', $maxTime, $thu_c, $events, $start, $end);
				}
			} else {
				$events = $this->add_closed3('thursday', $minTime, $maxTime, $events, $start, $end);
			}	
		
			if ($fri_o != '') {
				$comp6o = strtotime($fri_o);
				$comp6c = strtotime($fri_c);
				if ($comp6o > $compminTime) {
					$events = $this->add_closed1('friday', $minTime, $fri_o, $events, $start, $end);
				}
				if ($comp6c < $compmaxTime) {
					$events = $this->add_closed2('friday', $maxTime, $fri_c, $events, $start, $end);
				}
			} else {
				$events = $this->add_closed3('friday', $minTime, $maxTime, $events, $start, $end);
			}	
		
			if ($sat_o != '') {
				$comp7o = strtotime($sat_o);
				$comp7c = strtotime($sat_c);
				if ($comp7o > $compminTime) {
					$events = $this->add_closed1('saturday', $minTime, $sat_o, $events, $start, $end);
				}
				if ($comp7c < $compmaxTime) {
					$events = $this->add_closed2('saturday', $maxTime, $sat_c, $events, $start, $end);
				}
			} else {
				$events = $this->add_closed3('saturday', $minTime, $maxTime, $events, $start, $end);
			}
		}
		
		echo json_encode($events);
		exit(0);
	}
	
	function edit_event()
	{
		$start = strtotime($this->input->post('start_date') . " " . $this->input->post('start_time'));
		$visit_type = $this->input->post('visit_type');
		if ($visit_type != '') {
			$this->db->select('duration');
			$this->db->where('visit_type', $visit_type);
			$this->db->where('active','y');
			$this->db->where('practice_id', $this->session->userdata('practice_id'));
			$query = $this->db->get('calendar');
			$row = $query->row_array();
			$end = $start + $row['duration'];
		} else {
			$end = strtotime($this->input->post('start_date') . " " . $this->input->post('end'));
		}
		$provider_id = $this->session->userdata('provider_id');
		$title = $this->input->post('title');
		$reason = $this->input->post('reason');
		$id = $this->input->post('event_id');
		$repeat = $this->input->post('repeat');
		if ($id == '') {
			if ($this->input->post('pid') == '') {
				$status = '';
			} else {
				$status = 'Pending';
			}
		} else {
			$status = $this->input->post('status');
		}
		if ($repeat != '') {
			$rid = str_replace('R', '', $id);
			$repeat_day1 = date('l', $start);
			$repeat_day = strtolower($repeat_day1);
			$repeat_start_time = date('h:ia', $start);
			$repeat_end_time = date('h:ia', $end);
			if ($this->input->post('until') != '') {
				$until = strtotime($this->input->post('until'));
			} else {
				$until = '0';
			}
			$data1 = array(
				'repeat_day' => $repeat_day,
				'repeat_start_time' => $repeat_start_time,
				'repeat_end_time' => $repeat_end_time,
				'repeat' => $repeat,
				'until'	=> $until,
				'title'	=> $title,
				'reason' => $reason,
				'provider_id' => $provider_id,
				'start' => $start
			);
			if ($id == '') {
				$this->schedule_model->add_repeat($data1);
				$this->audit_model->add();
			} else {
				if (str_pos($id, 'N') !== false) {
					$nid = str_replace('N', '', $id);
					$this->schedule_model->add_repeat($data1);
					$this->audit_model->add();
					$this->schedule_model->del_event($nid);
					$this->audit_model->delete();
				} else {
					$rid = str_replace('R', '', $id);
					$this->schedule_model->update_repeat($rid, $data1);
					$this->audit_model->update();
				}
			}
		} else {
			$data = array(
				'pid' => $this->input->post('pid'),
				'start' => $start,
				'end' => $end,
				'title' => $title,
				'visit_type' => $visit_type,
				'reason' => $reason,
				'status' => $status,
				'provider_id' => $provider_id,
				'user_id' => $this->session->userdata('user_id')
			);
			if ($id == '') {
				$data['timestamp'] = null;
				$appt_id = $this->schedule_model->add_event($data);
				$this->audit_model->add();
				$this->schedule_notification($appt_id);
			} else {
				$id_check1 = strpbrk($id, 'NR');
				if ($id_check1 == TRUE) {
					$nid1 = str_replace('NR', '', $id);
					$this->schedule_model->add_event($data);
					$this->audit_model->add();
					$this->schedule_model->del_repeat($nid1);
					$this->audit_model->delete();
				} else {
					$this->schedule_model->update_event($id, $data);
					$this->audit_model->update();
					$this->schedule_notification($id);
				}
			}
		}
	}
	
	function drag_event()
	{
		$start = $this->input->post('start');
		$end = $this->input->post('end');
		$id = $this->input->post('id');
		$id_check = strpbrk($id, 'R');
		if ($id_check == FALSE) {
			$data = array(
				'start' => $start,
				'end' => $end
			);
			$this->schedule_model->update_event($id, $data);
			$this->audit_model->update();
		} else {
			$rid = str_replace('R', '', $id);
			$repeat_day1 = date('l', $start);
			$repeat_day = strtolower($repeat_day1);
			$repeat_start_time = date('h:ia', $start);
			$repeat_end_time = date('h:ia', $end);
			$data1 = array(
				'repeat_day' => $repeat_day,
				'repeat_start_time' => $repeat_start_time,
				'repeat_end_time' => $repeat_end_time
			);
			$this->schedule_model->update_repeat($rid, $data1);
			$this->audit_model->update();
		}
	}
	
	function delete_event()
	{
		$id = $this->input->post('appt_id');
		$id_check = strpbrk($id, 'R');
		if ($id_check == FALSE) {
			$this->schedule_model->del_event($id);
			$this->audit_model->delete();
		} else {
			$rid = str_replace('R', '', $id);
			$this->schedule_model->del_repeat($rid);
			$this->audit_model->delete();
		}
	}
	
	function provider_schedule1()
	{
		$start = $this->input->post('start'); 
		$end = $this->input->post('end');
		$id = $this->session->userdata('provider_id');
		
		$events = array();
		$query = $this->db->query("SELECT * FROM schedule WHERE provider_id=$id AND start BETWEEN $start AND $end");
		foreach ($query->result_array() as $row) {
			if ($row['visit_type'] != '') {
				$this->db->select('classname');
				$this->db->where('visit_type', $row['visit_type']);
				$this->db->where('practice_id', $this->session->userdata('practice_id'));
				$query1 = $this->db->get('calendar');
				$row1 = $query1->row_array();
				$classname = $row1['classname'];
			} else {
				$classname = 'colorblack';
			}
			if ($row['pid'] == '0') {
				$pid = '';
			} else {
				$pid = $row['pid'];
			}
			$event = array(
				'id' => $row['appt_id'],
				'title' => $row['title'],
				'start' => $row['start'],
				'end' => $row['end'],
				'visit_type' => $row['visit_type'],
				'className' => $classname,
				'provider_id' => $row['provider_id'],
				'pid'=> $pid,
				'reason' => $row['reason'],
				'status' => $row['status']
			);
			$events[] = $event;
		}
		
		echo json_encode($events);
		exit(0);
	}
	
	function schedule_notification($appt_id)
	{
		$this->db->where('appt_id', $appt_id);
		$row1 = $this->db->get('schedule')->row_array();
		$this->db->where('pid', $row1['pid']);
		$row = $this->db->get('demographics')->row_array();
		$this->db->where('practice_id', $this->session->userdata('practice_id'));
		$row2 = $this->db->get('practiceinfo')->row_array();
		$this->db->where('id', $row1['provider_id']);
		$row0 = $this->db->get('users')->row_array();
		$displayname = $row0['displayname'];
		$to = $row['reminder_to'];
		$phone = $row2['phone'];
		$startdate = date("F j, Y, g:i a", $row1['start']);
		if ($row1['start'] < now()) {
			if ($to != '') {
				if ($row['reminder_method'] == 'Cellular Phone') {
					$message = 'Reminder - medical appt with ' . $displayname . ' on ' . $startdate . '.';
					$message .= ' To cancel/reschedule, call ' . $phone . '.';
				} else {
					$message = 'This message is a courtesy reminder of your medical appointment with ' . $displayname . ' on ' . $startdate . '.';
					$message .= ' If you need to cancel or reschedule your appointment, please contact us at ' . $phone . ' or reply to this e-mail at ' . $row2['email'] . '.';
					$message .= $row2['additional_message'];
				}
				$config['protocol']='smtp';
				$config['smtp_host']='ssl://smtp.googlemail.com';
				$config['smtp_port']='465';
				$config['smtp_timeout']='30';
				$config['smtp_user']=$row2['smtp_user'];
				$config['smtp_pass']=$row2['smtp_pass'];
				$config['charset']='utf-8';
				$config['newline']="\r\n";
				$this->email->initialize($config);
				$this->email->from($row2['email'], $row2['practice_name']);
				$this->email->to($to);
				$this->email->subject('Appointment Reminder');
				$this->email->message($message);
				$this->email->send();
			}
		}
	}
} 
/* End of file: schedule.php */
/* Location: application/controllers/billing/schedule.php */
