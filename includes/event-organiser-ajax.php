<?php
	/*
	* Deals with the plug-in's AJAX requests
	*/

	/*
	 * Public full calendar:
	 * This returns events to be displayed on the front-end full calendar
	*/
	add_action( 'wp_ajax_eventorganiser-fullcal', 'eventorganiser_public_fullcalendar' ); 
	add_action( 'wp_ajax_nopriv_eventorganiser-fullcal', 'eventorganiser_public_fullcalendar' ); 
	function eventorganiser_public_fullcalendar() {
		$request = array(
			'event_start_before'=>$_GET['end'],
			'event_end_after'=>$_GET['start']
		);

		//Restrict by category and/or venue
		if(!empty($_GET['category'])){
			$cats = explode(',',esc_attr($_GET['category']));
			$request['tax_query'][] = array(
					'taxonomy' => 'event-category',
					'field' => 'slug',
					'terms' => $cats,
					'operator' => 'IN'
				);
		}
		if(!empty($_GET['venue'])){
			$venues = explode(',',esc_attr($_GET['venue']));
			$request['tax_query'][] = array(
					'taxonomy' => 'event-venue',
					'field' => 'slug',
					'terms' => $venues,
					'operator' => 'IN'
				);
		}

		$presets = array('numberposts'=>-1, 'group_events_by'=>'','showpastevents'=>true);

		//Retrieve events		
		$query = array_merge($request,$presets);
		$key = 'eo_fc_'.md5(serialize($query));
		$calendar = get_transient('eo_full_calendar_public');
		if( $calendar && is_array($calendar) && isset($calendar[$key]) ){
			echo json_encode($calendar[$key]);
			exit;
		}

		$events = eo_get_events($query);
		$eventsarray = array();

		//Blog timezone
		$tz = eo_get_blog_timezone();

		//Loop through events
		global $post;
		if ($events) : 
			foreach  ($events as $post) :
				setup_postdata( $post );
				$event=array();
				$event['className']=array('eo-event');

				//Title and url
				$event['title']=html_entity_decode(get_the_title($post->ID),ENT_QUOTES,'UTF-8');
				$event['url'] = apply_filters('eventorganiser_calendar_event_link',esc_js(get_permalink( $post->ID)),$post->ID,$post->event_id);

				//All day or not?
				$event['allDay'] = eo_is_all_day();
	
				//Get Event Start and End date, set timezone to the blog's timzone
				$event_start = new DateTime($post->StartDate.' '.$post->StartTime, $tz);
				$event_end = new DateTime($post->EndDate.' '.$post->FinishTime, $tz);
				$event['start']= $event_start->format('Y-m-d\TH:i:s\Z');
				$event['end']= $event_end->format('Y-m-d\TH:i:s\Z');	

				//Don't use get_the_excerpt as this adds a link
				$excerpt_length = apply_filters('excerpt_length', 55);
				$event['description']  = wp_trim_words( strip_shortcodes(get_the_content()), $excerpt_length, '...' );

				//Colour past events
				$now = new DateTIme(null,$tz);
				if($event_start <= $now)
					$event['className'][] = 'eo-past-event';
				else
					$event['className'][] = 'eo-future-event';
				
				//Include venue if this is set
				$venue = eo_get_venue($post->ID);

				if($venue && !is_wp_error($venue)){
					$event['className'][]= 'venue-'.eo_get_venue_slug($post->ID);
					$event['venue']=$venue;
				}
				
				//Event categories
				$terms = get_the_terms( $post->ID, 'event-category' );
				$event['category']=array();
				if($terms):
					foreach ($terms as $term):
						$event['category'][]= $term->slug;
						if(empty($event['color'])):
							$term_meta = get_option( "eo-event-category_$term->term_id");
							$event['color'] = (isset($term_meta['colour']) ? $term_meta['colour'] : '');
						endif;
						$event['className'][]='category-'.$term->slug;
					endforeach;
				endif;

				//Add event to array
				$eventsarray[]=$event;
			endforeach;
			wp_reset_postdata();
		endif;

		if( !$calendar || !is_array($calendar) )
			$calendar = array();
	
		$calendar[$key] = $eventsarray;

		set_transient('eo_full_calendar_public',$calendar, 60*60*24);

		//Echo result and exit
		echo json_encode($eventsarray);
		exit;
	}

	/*
	 * Admin calendar: Calendar View
	 * This gets events and generates summaries for events to be displayed
	 *  in the admin 'calendar view'
	*/
	add_action( 'wp_ajax_event-admin-cal', 'eventorganiser_admin_calendar' ); 
	function eventorganiser_admin_calendar() {
		//request
		$request = array(
			'event_end_after'=>$_GET['start'],
			'event_start_before'=>$_GET['end']
		);

		//Presets
		$presets = array( 
			'posts_per_page'=>-1,
			'post_type'=>'event',
			'group_events_by'=>'',
			'perm' => 'readable');

		$calendar = get_transient('eo_full_calendar_admin');
		$key = $_GET['start'].'--'.$_GET['end'];
		if( $calendar && is_array($calendar) && isset($calendar[$key]) ){
			echo json_encode($calendar[$key]);
			exit;
		}
	

		//Create query
		$query_array = array_merge($presets, $request);	
		$query = new WP_Query($query_array );

		//Retrieve events		
		$query->get_posts();
		$eventsarray = array();

		//Blog timezone
		$tz = eo_get_blog_timezone();

		//Loop through events
		global $post;
		if ( $query->have_posts() ) : 
			while ( $query->have_posts() ) : $query->the_post(); 
				$event=array();
				$colour='';
				//Get title, append status if applicable
				$title = get_the_title();
				if(!empty($post->post_password)){
					$title.=' - '.__('Protected');
				}elseif($post->post_status=='private'){
					$title.=' - '.__('Private');
				}elseif	($post->post_status=='draft'){
					$title.=' - '.__('Draft');
				}
				$event['title']= html_entity_decode ($title,ENT_QUOTES,'UTF-8');

				$schedule = eo_get_event_schedule($post->ID);

				//Check if all day, set format accordingly
				if( $schedule['all_day'] ){
					$event['allDay'] = true;
					$format = get_option('date_format');
				}else{
					$event['allDay'] = false;
					$format = get_option('date_format').'  '.get_option('time_format');
				}

				//Get author (or organiser)
				$organiser = get_userdata( $post->post_author)->display_name;
	
				//Get Event Start and End date, set timezone to the blog's timzone
				$event_start = new DateTime($post->StartDate.' '.$post->StartTime, $tz);
				$event_end = new DateTime($post->EndDate.' '.$post->FinishTime, $tz);
	
				$event['start']= $event_start->format('Y-m-d\TH:i:s\Z');
				$event['end']= $event_end->format('Y-m-d\TH:i:s\Z');
	
				//Produce summary of event
				$summary= "<table class='form-table' >"
								."<tr><th> ".__('Start','eventorganiser').": </th><td> ".eo_format_datetime($event_start,$format)."</td></tr>"
								."<tr><th> ".__('End','eventorganiser').": </th><td> ".eo_format_datetime($event_end, $format)."</td></tr>"
								."<tr><th> ".__('Organiser','eventorganiser').": </th><td>".$organiser."</td></tr>";
	
				$event['className']=array('event');

				 $now = new DateTIme(null,$tz);
				if($event_start <= $now)
					$event['className'][]='past-event';

				//Include venue if this is set
				$venue = eo_get_venue($post->ID);

				if($venue && !is_wp_error($venue)){
					$summary .="<tr><th>".__('Where','eventorganiser').": </th><td>".eo_get_venue_name($venue)."</td></tr>";
					$event['className'][]= 'venue-'.eo_get_venue_slug($post->ID);
					$event['venue']=$venue;
				}
						
				$summary = apply_filters('eventorganiser_admin_cal_summary',$summary,$post->ID,$post->event_id,$post);
	
				$summary .= "</table><p>";
							
				//Include schedule summary if event reoccurrs
			
				if( $schedule['schedule'] != 'once' )
					$summary .='<em>'.__('This event reoccurs','eventorganiser').' '.eo_get_schedule_summary().'</em>';
				$summary .='</p>';

				//Include edit link in summary if user has permission
				if (current_user_can('edit_event', $post->ID)){
					$edit_link = get_edit_post_link( $post->ID,'');
					$summary .= "<span class='edit'><a title='Edit this item' href='".$edit_link."'> ".__('Edit Event','eventorganiser')."</a></span>";
					$event['url']= $edit_link;
				}

				//Include a delete occurrence link in summary if user has permission
				if (current_user_can('delete_event', $post->ID)){
					$admin_url  = admin_url('edit.php');

					$delete_url = add_query_arg(array(
						'post_type'=>'event',
						'page'=>'calendar',
						'series'=>$post->ID,
						'event'=>$post->event_id,
						'action'=>'delete_occurrence'
					),$admin_url);

					$delete_url  = wp_nonce_url( $delete_url , 'eventorganiser_delete_occurrence_'.$post->event_id);

					$summary .= "<span class='delete'>
					<a class='submitdelete' style='color:red;float:right' title='".__('Delete this occurrence','eventorganiser')."' href='".$delete_url."'> ".__('Delete this occurrence','eventorganiser')."</a>
					</span>";

					if( $schedule['schedule'] !='once'){
						$break_url = add_query_arg(array(
							'post_type'=>'event',
							'page'=>'calendar',
							'series'=>$post->ID,
							'event'=>$post->event_id,
							'action'=>'break_series'
						),$admin_url);
						$break_url  = wp_nonce_url( $break_url , 'eventorganiser_break_series_'.$post->event_id);

						$summary .= "<span class='break'>
						<a class='submitbreak' style='color:red;float:right;padding-right: 2em;' title='".__('Break this series','eventorganiser')."' href='".$break_url."'> ".__('Break this series','eventorganiser')."</a>
						</span>";
					}

				}

				$terms = get_the_terms( $post->ID, 'event-category' );

				$event['category']=array();
				if($terms):
					foreach ($terms as $term):
						$event['category'][]= $term->slug;
						if(empty($event['color'])):
							$event['color'] = (isset($term->color) ? $term->color : '');
						endif;
						$event['className'][]='category-'.$term->slug;
					endforeach;
				endif;

				$event['summary'] = $summary;

				//Add event to array
				$eventsarray[]=$event;
			endwhile;
		endif;

		if( !$calendar || !is_array($calendar) )
			$calendar = array();
	
		$calendar[$key] = $eventsarray;

		set_transient('eo_full_calendar_admin',$calendar, 60*60*24);

		//Echo result and exit
		echo json_encode($eventsarray);
		exit;
}

	/*
	 * Widget and Shortcode calendar:
	 * This gets the month being viewed and generates the
	 * html code to view that month and its events. 
	*/
 	add_action( 'wp_ajax_nopriv_eo_widget_cal', 'eventorganiser_widget_cal' );
	add_action( 'wp_ajax_eo_widget_cal', 'eventorganiser_widget_cal' );
	function eventorganiser_widget_cal() {

		/*Retrieve the month we are after. $month must be a 
		DateTime object of the first of that month*/
		if(isset($_GET['eo_month'])){
			$month  = new DateTime($_GET['eo_month'].'-01'); 
		}else{
			$month = new DateTime('now');
			$month = date_create($month->format('Y-m-1'));
		}		

		//Options for the calendar
		$args=array(
			'showpastevents'=>(empty($_GET['showpastevents']) ? 0 : 1)
		);
	
		echo json_encode(EO_Calendar_Widget::generate_output($month,$args));
		exit;
	}

	/*
	 * Widget and Shortcode agenda:
	 * This gets the month being viewed and generates the
	 * html code to view that month and its events. 
	*/
 	add_action( 'wp_ajax_nopriv_eo_widget_agenda', 'eventorganiser_widget_agenda' );
	add_action( 'wp_ajax_eo_widget_agenda', 'eventorganiser_widget_agenda' );
	function eventorganiser_widget_agenda() {
		global $wpdb;

		$number = (int) $_GET['instance_number'];
		$wid = new EO_Events_Agenda_Widget();
		$settings =  $wid->get_settings();
		$instance = $settings[$number];
		$today= new DateTIme('now', eo_get_blog_timezone());
		$query=array();
		$return_array = array();

		$query['mode'] = !empty($instance['mode']) ? $instance['mode'] : 'day';
		$query['direction'] = intval($_GET['direction']);
		$query['date'] = ($query['direction'] <1? $_GET['start'] : $_GET['end']);
		$query['order'] = ($query['direction'] <1? 'DESC' : 'ASC');

		$key = 'eo_ag_'.md5(serialize($query));
		$agenda = get_transient('eo_widget_agenda');
		if( $agenda && is_array($agenda) && isset($agenda[$key]) ){
			echo json_encode($agenda[$key]);
			exit;
		}

		if( 'day' == $query['mode'] ){		
			//Day mode
			$selectDates="SELECT DISTINCT StartDate FROM {$wpdb->eo_events}";
			$whereDates = " WHERE {$wpdb->eo_events}.StartDate".( $query['order']=='ASC' ? " >= " : " <= ")."%s ";
			$whereDates .= " AND {$wpdb->eo_events}.StartDate >= %s ";
			$orderlimit = "ORDER BY  {$wpdb->eo_events}.StartDate {$query['order']} LIMIT 4";
			$dates = $wpdb->get_col($wpdb->prepare($selectDates.$whereDates.$orderlimit, $query['date'],$today->format('Y-m-d')));

			if(!$dates)
				return false;

			$query['date1']  = min($dates[0],$dates[count($dates)-1]);
			$query['date2'] = max($dates[0],$dates[count($dates)-1]);

		}else{
			//Month mode
			$selectDates="SELECT DISTINCT StartDate FROM {$wpdb->eo_events}";
			$whereDates = " WHERE {$wpdb->eo_events}.StartDate".( $query['order']=='ASC' ? " > " : " < ")."%s ";
			$whereDates .= " AND {$wpdb->eo_events}.StartDate >= %s ";
			$orderlimit = "ORDER BY  {$wpdb->eo_events}.StartDate {$query['order']} LIMIT 1";
			$date = $wpdb->get_row($wpdb->prepare($selectDates.$whereDates.$orderlimit, $query['date'],$today->format('Y-m-d')));

			if(!$date)
				return false;

			$datetime = new DateTime($date->StartDate, eo_get_blog_timezone());
			$query['date1']  = $datetime->format('Y-m-01');
			$query['date2'] = $datetime->format('Y-m-t'); 
		}

		$events = eo_get_events(array(
			'event_start_after'=>$query['date1'],
			'event_start_before'=>$query['date2']
		));

		global $post;
		foreach ($events as $post):
			$return_array[] = array(
				'StartDate'=>$post->StartDate,
				'display'=>eo_get_the_start($instance['group_format']),
				'time'=> ( ($instance['mode']=='day' && eo_is_all_day())  ? __('All Day','eventorganiser') : eo_get_the_start($instance['item_format']) ),
				'post_title'=>get_the_title(),
				'color'=>eo_event_color(),
				'link'=>'<a href="'.get_permalink().'">'.__('View','eventorganiser').'</a>',
				'Glink'=>'<a href="'.eo_get_the_GoogleLink().'" target="_blank">'.__('Add To Google Calendar','eventorganiser').'</a>'
			);
		endforeach;

		if( !$agenda || !is_array($agenda) )
			$agenda = array();
	
		$agenda[$key] = $return_array;

		set_transient('eo_widget_agenda',$agenda, 60*60*24);

		echo json_encode($return_array);
		exit;
	}


	/*
	 * Venue search
	 * Returns a list of venues that match the term
	 * Queries venue name.
	*/
	add_action( 'wp_ajax_eo-search-venue', 'eventorganiser_search_venues' ); 
	function eventorganiser_search_venues() {
		
		// Query the venues with the given term
		$value = trim(esc_attr($_GET["term"]));
		$venues = eo_get_venues(array('search'=>$value));

		foreach($venues as $venue){
			$venue_id = (int) $venue->term_id;
			if( !isset($term->venue_address) ){
				$address = eo_get_venue_address($venue_id);
				$venue->venue_address =  isset($address['address']) ? $address['address'] : '';
				$venue->venue_postal =  isset($address['postcode']) ? $address['postcode'] : '';
				$venue->venue_country =  isset($address['country']) ? $address['country'] : '';
			}

			if( !isset($venue->venue_lat) || !isset($venue->venue_lng) ){
				$venue->venue_lat =  number_format(floatval(eo_get_venue_lat($venue_id)), 6);
				$venue->venue_lng =  number_format(floatval(eo_get_venue_lng($venue_id)), 6);
			}

			if( !isset($venue->venue_description) ){
				$venue->venue_description = eo_get_venue_description($venue_id);
			}
		}
		
		$novenue = array('term_id'=>0,'name'=>__('No Venue','eventorganiser'));
		$venues =array_merge (array($novenue),$venues);

		//echo JSON to page  
		$response = $_GET["callback"] . "(" . json_encode($venues) . ")";  
		echo $response;  
		exit;
	}

	add_action( 'wp_ajax_eofc-format-time', 'eventorganiser_admin_cal_time_format' ); 
	function eventorganiser_admin_cal_time_format(){
		$is24 = (  $_POST['is24'] == 'false' ? 1: 0 );
		$user =wp_get_current_user();
		$is12hour = update_user_meta($user->ID,'eofc_time_format',$is24);
		exit();
	}
?>
