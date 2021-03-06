<?php
/**
 * Functions for Event CPT editing / creating page 
 * @since 1.0.0
 */
/**
 * Initialises the plug-ins metaboxs on Event CPT
 * @since 1.0.0
 */
add_action('admin_init','eventorganiser_edit_init');
function eventorganiser_edit_init(){
	//If current user can delete this event
	if (current_user_can('delete_events')) add_action('delete_post', 'eventorganiser_event_delete', 10);
	
	// add a meta box to event post types.
	add_meta_box('eventorganiser_detail', __('Event Details','eventorganiser'), 'eventorganiser_details_metabox', 'event', 'normal', 'high');

	// add a callback function to save any data a user enters in
	add_action('save_post','eventorganiser_details_save');
}

add_action('add_meta_boxes', 'eventorganiser_author_meta_box_title',0);
function eventorganiser_author_meta_box_title() {
	global $wp_meta_boxes; // array of defined meta boxes
	//Change the name of 'author' to 'organiser'.
	if(isset($wp_meta_boxes['event']['normal']['core']['authordiv']))
		$wp_meta_boxes['event']['normal']['core']['authordiv']['title']= __('Organiser','eventorganiser');
}

/**
 * Sets up the event data metabox
* This allows user to enter date / time, reoccurrence and venue data for the event
 *
 * @since 1.0.0
 */
function eventorganiser_details_metabox($post){
	global $wp_locale;	

	//Sets the format as php understands it, and textual.
	$eo_settings_array= get_option('eventorganiser_options');
	if($eo_settings_array['dateformat']=='dd-mm'){
		$phpFormat = 'd-m-Y';
		$format = 'dd-mm-yyyy';
	}else{
		$phpFormat = 'm-d-Y';
		$format = 'mm-dd-yyyy';
	}

	//Get the starting day of the week
	$start_day=intval(get_option('start_of_week'));
	$ical_days =array('SU','MO','TU','WE','TH','FR','SA');

	//Retrieve event details
	extract(eo_get_event_schedule($post->ID));
	$venues = eo_get_venues();
	$venue_id= (int) eo_get_venue($post->ID);

	$sche_once = ($schedule =='once'||$schedule =='custom');
	//Start of meta box ?>	
	<p>
		<?php if(!$sche_once ):?>
			<strong><?php _e('This is a reoccurring event','eventorganiser');?></strong>. 
			<input type="checkbox" id="HWSEvent_rec" name="eo_input[AlterRe]" value="yes" /> 
			<?php _e('Check to edit this event and its reoccurrences','eventorganiser');?>.
		<?php endif;?>
	</p>

	<div class="<?php echo ( $sche_once ? 'onetime': 'reoccurence');?>">
		<p><?php echo sprintf(__('Ensure dates are entered in %1$s format and times in %2$s (24 hour) format','eventorganiser'),'<strong>'.$format.'</strong>',' <strong>hh:mm</strong>');?> </p>

		<table id="eventorganiser_event_detail" class="form-table">
				<tr valign="top"  class="event-date">
					<td><?php echo __("Start Date/Time",'eventorganiser').':';?> </td>
					<td> 
						<input class="ui-widget-content ui-corner-all" name="eo_input[StartDate]" size="10" maxlength="10" id="from_date" <?php disabled(!$sche_once);?> value="<?php echo $start->format($phpFormat); ?>"/>

						<input name="eo_input[StartTime]" class="eo_time ui-widget-content ui-corner-all" size="4" maxlength="5" id="HWSEvent_time" <?php disabled( (!$sche_once) || $all_day );?> value="<?php echo $start->format('H:i');?>"/>

					</td>
				</tr>

				<tr valign="top"  class="event-date">
					<td><?php echo __("End Date/Time",'eventorganiser').':';?> </td>
					<td> 
					<input class="ui-widget-content ui-corner-all" name="eo_input[EndDate]" size="10" maxlength="10" id="to_date" <?php disabled(!$sche_once);?>  value="<?php echo $end->format($phpFormat); ?>"/>
					<input name="eo_input[FinishTime]" class="eo_time ui-widget-content ui-corner-all " size="4" maxlength="5" id="HWSEvent_time2" <?php disabled( (!$sche_once) || $all_day );?> value=" <?php echo $end->format('H:i'); ?>""/>

					<label>
					<input type="checkbox" id="eo_allday"  <?php checked($all_day); ?> name="eo_input[allday]"  <?php  disabled( !$sche_once );?> value="1"/>
						<?php _e("All day",'eventorganiser');?>
					 </label>
			
					</td>
				</tr>

				<tr class="event-date">
					<td><?php _e("Reoccurence:",'eventorganiser');?> </td>
					<td> 
					<?php $reoccurrence_schedules= array('once'=> __('once','eventorganiser'),'daily'=> __('daily','eventorganiser'),'weekly'=> __('weekly','eventorganiser'),
														'monthly'=> __('monthly','eventorganiser'),'yearly'=> __('yearly','eventorganiser'),'custom'=>__('custom','eventorganiser'));?>

					<select id="HWSEventInput_Req" name="eo_input[schedule]">
						<?php foreach ($reoccurrence_schedules as $index=>$val): ?>
							<option value="<?php echo $index;?>" <?php selected($schedule, $index);?>><?php echo $val;?></option>
						<?php endforeach;  //End foreach $allowed_reoccurs?>
					</select>
					</td>
				</tr>

				<tr valign="top"  class="event-date reocurrence_row">
					<td></td>
					<td>
						<p><?php _e("Repeat every ",'eventorganiser');?>
						<input <?php  disabled( (!$sche_once) || $all_day );?> class="ui-widget-content ui-corner-all" name="eo_input[event_frequency]" id="HWSEvent_freq" type="number" min="1" max="365" maxlength="4" size="4" disabled="disabled" value="<?php echo $frequency;?>" /> 
						<span id="recpan" >  </span>				
						</p>

						<p id="dayofweekrepeat">
						<?php _e("on",'eventorganiser');?>	
						<?php for($i = 0; $i <= 6; $i++):
							$d = ($start_day + $i)%7;
							$ical_d = $ical_days[$d];
							$day =$wp_locale->weekday_abbrev[$wp_locale->weekday[$d]];
							$schedule_days = ( is_array($schedule_meta) ? $schedule_meta : array() );
						?>
							<input type="checkbox" id="day-<?php echo $day;?>"  <?php checked(in_array($ical_d,$schedule_days), true); ?>  value="<?php echo esc_attr($ical_d)?>" class="daysofweek" name="eo_input[days][]" disabled="disabled" />
							<label for="day-<?php echo $day;?>" > <?php echo $day;?></label>
						<?php endfor;  ?>
						</p>

						<p id="dayofmonthrepeat">
						<label for="bymonthday" >	
							<input type="radio" id="bymonthday" disabled="disabled" name="eo_input[schedule_meta]" <?php checked($occurs_by,'BYMONTHDAY'); ?> value="BYMONTHDAY=" /> 
							<?php _e("day of month",'eventorganiser');?>
						</label>
						<label for="byday" >
							<input type="radio" id="byday" disabled="disabled" name="eo_input[schedule_meta]"  <?php checked($occurs_by != 'BYMONTHDAY', true); ?> value="BYDAY=" /> 
							<?php _e("day of week",'eventorganiser');?>
						</label>
						</p>

						<p class="reoccurrence_label">
						<?php _e("until",'eventorganiser');?> 
						<input <?php  disabled( (!$sche_once) || $all_day ); ?> class="ui-widget-content ui-corner-all" name="eo_input[schedule_end]" id="recend" size="10" maxlength="10" disabled="disabled" value="<?php echo $schedule_last->format($phpFormat); ?>"/>
						</p>

						<p id="event_summary"> </p>
					</td>
				</tr>

				<tr valign="top" id="eo_occurrence_picker_row">
					<td class="eo-label">	<a href="#" class="eo_occurrence_toogle"><?php _e("Include/Exclude occurrences",'eventorganiser');?>: </a></td>
					<td>	
						<div id="eo_occurrence_datepicker"></div>
						<?php 	
						//var_dump($include);
						if( !empty($include) ){
							$include_str = array_map('eo_format_datetime', $include, array_fill(0, count($include), 'Y-m-d') );
							$include_str = esc_textarea(sanitize_text_field(implode(',',$include_str))); 
						}else{
							$include_str = '';
						}?>
						<textarea style="display:none;" name="eo_input[include]" id="eo_occurrence_includes"><?php echo $include_str; ?></textarea>

						<?php 	
						if( !empty($exclude) ){
							$exclude_str = array_map('eo_format_datetime', $exclude, array_fill(0, count($exclude), 'Y-m-d') );
							$exclude_str = esc_textarea(sanitize_text_field(implode(',',$exclude_str))); 
						}else{
							$exclude_str = '';
						}?>
						<textarea style="display:none;" name="eo_input[exclude]" id="eo_occurrence_excludes"><?php echo $exclude_str; ?></textarea>

					</td>
				</tr>

				<tr>	
					<td class="label"> <?php _e("Venue",'eventorganiser');?>: </td>
					<td> 	
						<select size="50" id="venue_select" name="eo_input[event-venue]">
							<option><?php _e("Select a venue",'eventorganiser');?></option>
							<?php foreach ($venues as $venue):?>
								<option <?php  selected($venue->term_id,$venue_id);?> value="<?php echo $venue->term_id;?>"><?php echo $venue->name; ?></option>
							<?php endforeach;?>
						</select>
						<span style="font-size:0.8em;line-height:0.8em;"> 
							<?php _e("Search for a venue. To add a venues go to the venue page.",'eventorganiser');?>
						</span>
					</td>
				</tr>

				<tr valign="top"  class="venue_row <?php if (!$venue_id) echo 'novenue';?>" >
					<td></td>
					<td>
						<div id="eventorganiser_venue_meta" style="display:none;">
							<input type="hidden" id="eo_venue_Lat" value="<?php eo_venue_lat($venue_id);?>" />
							<input type="hidden" id="eo_venue_Lng" value="<?php eo_venue_lng($venue_id); ?>" />
						</div>
						<div id="venuemap" class="ui-widget-content ui-corner-all gmap3"></div>
						<div class="clear"></div>
					</td>
				</tr>
			</table>
		</div>
	<?php 

	// create a custom nonce for submit verification later
	wp_nonce_field('eventorganiser_event_update_'.get_the_ID(),'_eononce');	
}	


/**
 * Saves the event data posted from the event metabox.
 * Hooked to the 'save_post' action
 * 
 * @since 1.0.0
 *
 * @param int $post_id the event post ID
 * @return int $post_id the event post ID
 */
function eventorganiser_details_save( $post_id ) {

	//make sure data came from our meta box
	if(!isset($_POST['_eononce']) || !wp_verify_nonce($_POST['_eononce'],'eventorganiser_event_update_'.$post_id)) return;

	// verify this is not an auto save routine. 
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;

	//authentication checks
	if (!current_user_can('edit_event', $post_id)) return;


	$raw_data = (isset($_POST['eo_input']) ? $_POST['eo_input'] : array());

	//Update venue
	$venue_id = !empty($raw_data['event-venue']) ? intval($raw_data['event-venue']) : null;
	$r = wp_set_post_terms( $post_id, array( $venue_id ), 'event-venue', false );

	$raw_data =wp_parse_args($raw_data,array(
		'StartDate'=>'','EndDate'=>'','StartTime'=>'00:00','FinishTime'=>'23:59','schedule'=>'once','event_frequency'=>1,
		'schedule_end'=>'','allday'=>0,'schedule_meta'=>'','days'=>array(),'include'=>'','exclude'=>'',));

	if( eo_reoccurs($post_id) && (!isset($raw_data['AlterRe']) || 'yes' != $raw_data['AlterRe']) ){
		do_action('eventorganiser_save_event', $post_id);//Need this to update cache
		return;
	}

	$all_day = empty($raw_data["allday"]) ? 0 : 1;

	if( $all_day ){
		$raw_data['StartTime']='00:00';
		$raw_data['FinishTime']='23:59';
	}

	$start = _eventorganiser_check_datetime(trim($raw_data["StartDate"]).' '.trim($raw_data["StartTime"]));
	$end = _eventorganiser_check_datetime(trim($raw_data["EndDate"]).' '.trim($raw_data["FinishTime"]));
	$schedule_last =  _eventorganiser_check_datetime(trim($raw_data["schedule_end"]).' '.trim($raw_data["StartTime"]));

	$schedule = $raw_data["schedule"];
	if(  'weekly' == $schedule ){
		$schedule_meta = $raw_data["days"];
		$occurs_by='';
	}elseif(  'monthly' == $schedule ){
		$schedule_meta = $raw_data['schedule_meta'];
		$occurs_by = trim($schedule_meta,'=');
	}else{
		$schedule_meta = '';
		$occurs_by='';
	}


	$in_ex=array();
	foreach (array('include','exclude') as $key ):
		$in_ex[$key]=array();
		$arr = explode(',',sanitize_text_field($raw_data[$key])); 
		if( !empty($arr ) ){
			foreach($arr as $date):
				$date_obj = _eventorganiser_check_datetime($date.' '.$raw_data['StartTime'], true);
				if( $date_obj )
					$in_ex[$key][] = $date_obj;
			endforeach;
		}
	endforeach;

	$event_data = array(
		'start'=>$start,
		'end'=>$end,
		'all_day'=>$all_day,
		'schedule'=>$schedule,
		'frequency'=>(int) $raw_data["event_frequency"],
		'schedule_last'=>$schedule_last,
		'schedule_meta'=>$schedule_meta,
		'occurs_by' => $occurs_by,
		'include'=>$in_ex['include'],
		'exclude'=>$in_ex['exclude'],
	);

	$response = eo_update_event($post_id,$event_data);	

	if( is_wp_error($response) ){
		global $EO_Errors;
		$code = $response->get_error_code();
		$message = $response->get_error_message($code);
		$errors[$post_id][] = "Event dates were not saved.";
		$errors[$post_id][] = $message;
		$EO_Errors->add('eo_error',$message);
		update_option('eo_notice',$errors);
	}

	return;
}



/**
 * Display custom error or alert messages on events CPT page
 *
 * @since 1.0.0
 */
add_action('admin_notices', 'event_edit_admin_notice',0);
function event_edit_admin_notice(){
	//print the message
	global $post;
	$notice = get_option('eo_notice');
	if (empty($notice)) return '';
	foreach($notice as $pid => $messages){
		if (!empty($post->ID) && $post->ID == $pid ){
			echo '<div id="message" class="error">';
			foreach ($messages as $m):
				echo '<p>'.$m.'</p>';
			endforeach;
			echo '</div>';
			//make sure to remove notice after its displayed so its only displayed when needed.
			unset($notice[0]);
			unset($notice[$pid]);
			update_option('eo_notice',$notice);
        	}
	}	
}
?>
