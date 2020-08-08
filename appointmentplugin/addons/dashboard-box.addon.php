<?php
/*
*/
require_once dirname( __FILE__ ).'/base.addon.php';

if( !class_exists( 'CPAPPB_DashboardWidget' ) )
{
    class CPAPPB_DashboardWidget extends CPAPPB_BaseAddon
    {

        /************* ADDON SYSTEM - ATTRIBUTES AND METHODS *************/
		protected $addonID = "addon-DashboardWidget-20181221";
		protected $name = "Dashboard Widget: Upcoming appointments";
		protected $description;
        protected $max_image_width = 250;

		public function get_addon_form_settings( $form_id )
		{
			global $wpdb;
			// Insertion in database			
			
		} // end get_addon_form_settings



		/************************ ADDON CODE *****************************/

        /************************ ATTRIBUTES *****************************/


        /************************ CONSTRUCT *****************************/

        function __construct()
        {
			$this->description = __("The add-on adds a widget to display a list of upcoming appointments in the dashboard welcome page.", 'appointment-hour-booking' );
            // Check if the plugin is active
			if( !$this->addon_is_active() ) return;
                      
            add_action('wp_dashboard_setup', array(&$this, 'add_dashboard_widgets') );
        } // End __construct


        public function add_dashboard_widgets() {
        	wp_add_dashboard_widget('dashboard_widget', 'Appointment Hour Booking: Upcoming appointments', array(&$this, 'pp_DashboardWidget'));
        }        

        /************************ PRIVATE METHODS *****************************/

	

		/************************ PUBLIC METHODS  *****************************/
        
        
		/**
         * print list
         */
		public function pp_DashboardWidget($atts = array())
		{
            global $wpdb, $cp_appb_plugin;           
            extract( shortcode_atts( array(
	        	'calendar' => '',
	        	'showdelete' => '0',
                'columnlabels' => 'TIME,SERVICE,data',
	        	'columns' => 'TIME,SERVICE,data',
                'datefrom' => 'today',
                'dateto' => 'today +1 month',
                'paidonly' => "",
                'maxitems' => "10",
                'datelabel' => __('Date', 'appointment-hour-booking'),
                'status' => "-1"
	        ), $atts ) ); 
            
            $buffer = '<div style="font-weight:bold;margin-bottom:3px;">'.__('Next','appointment-hour-booking')." ".$maxitems." ".__('upcoming appointments:','appointment-hour-booking')."</div>";
            wp_enqueue_script( "jquery" );
            
            $cond = '1=1';            
                
            if ($calendar)
                $cond .= " AND formid=".intval($calendar);     
                
            // calculate dates
            $from = date("Y-m-d",strtotime($datefrom));
            $to = date("Y-m-d",strtotime($dateto));
        
            $events_query = "SELECT * FROM ".$wpdb->prefix.$cp_appb_plugin->table_messages.
                           " WHERE ".$cond." ORDER BY time DESC";
            
            $events = $wpdb->get_results($events_query);

            // pre-select time-slots
            $selection = array();            
            foreach($events as $item)
            {        
                $data = unserialize($item->posted_data);
                if (!$paidonly || $data['paid'])
                {
                    foreach($data["apps"] as $app)                                       
                        if ($app["date"] >= $from && $app["date"] <= $to && ($status == '-1' || $status == $app["cancelled"]) 
						   && $app["cancelled"] != 'Cancelled' && $app["cancelled"] != 'Cancelled by customer')
                        {                      
                            $dt= $item->data;  
                            $selection[] = array($app["date"]." ".$app["slot"], $app["date"], $app["slot"], $data, sanitize_email($item->notifyto),   $dt , $app["cancelled"], $app["service"]);
                        }    
                }
            }
        
            // order time-slots
            function listgroupd_addon_appbkfastsortfn($a, $b) { return ($a[0] > $b[0]); }
            usort($selection, "listgroupd_addon_appbkfastsortfn" );        
            
            // clean fields IDs
            $fields = explode(",",trim($columns));
            for($j=0; $j<count($fields); $j++)
                $fields[$j] = strtolower(trim($fields[$j]));            
     
            $columnlabels = explode(",", $columnlabels);
            $columns = explode(",", $columns);

            if (!count($events))
            {
                $buffer .= __('No upcoming appointments found','appointment-hour-booking');
                echo $buffer;
                return;
            }
  
            $buffer .= '<style>.cpappbtable {  border-collapse: collapse;border-spacing: 0;width: 100%;} .cpappbtable .cpappbth { text-align: left; border: 1px solid #999; background-color:#BDD7EE; } .cpappbtable .cpappbth th, .cpappbtable td {border: 1px solid #999;text-align: left;padding: 8px;word-break: normal; }.cpappbheadermth,.cpappbheadermtd{ text-align: left; border: 1px solid #999 !important; background-color:#F8CBAD ;}</style>';            
            

            // list data rows
            $colnum = 0;
            $lastdate = '';
            for($i=0; $i<count($selection) && $i<$maxitems; $i++)
            {
                $colnum++;
                if ($lastdate != $selection[$i][1])
                {
                    if ($lastdate != '') $buffer .= '</table></div>';
                    $lastdate = $selection[$i][1];
                    $buffer .= '<div style="overflow-x:auto;"><table class="cpappbtable"><tr>';                    
                    // list header rows
                    $buffer .= '<th class="cpappbheadermth">'.$datelabel.'</th>';
                    $buffer .= '<td class="cpappbheadermtd" nowrap>'.$lastdate.'</td>';
                    $buffer .= '<tr>';
                    $colnum = 0;
                    foreach ($columnlabels as $item)
                    {
                        $colnum++;
                        $buffer .= '<th class="cpappbheader'.$colnum.' cpappbth">'.esc_html(trim($item)).'</th>';
                    }
                    if ($showdelete)
                        $buffer .= '<th class="cpappbheader'.($colnum++).' cpappbth"></th>';
                    $buffer .= '</tr>';                       
                }
                $buffer .= '<tr class="'.($selection[$i][6]!=''?' cpappb_cancelled':'').'">';            
                for($j=0; $j<count($fields); $j++)
                {    
                   switch ($fields[$j]) {
                        case 'date':
                            $value = esc_html($selection[$i][1]);
                            break;
                        case 'time':
                            $value = esc_html($selection[$i][2]);
                            break;
                        case 'email':
                            $value = sanitize_email($selection[$i][4])."&nbsp;";
                            break;  
                        case 'service':
                            $value = esc_html($selection[$i][7])."&nbsp;";
                            break;                         
                        case 'cancelled':
                            if ($selection[$i][6] == '') 
                                $value = __('Approved','appointment-hour-booking');
                            else
                                $value = esc_html($selection[$i][6]);
                            $value = '&nbsp;';
                            break;                         
                        case 'data':
                            $value = $selection[$i][5];
                            if($selection[$i][4] != ''){
                                $value .=  '<p style="font-size: 15px;line-height: 20px;display:block;margin:0;color: #073048;font-weight: 400;"><strong>Email: </strong>'.$selection[$i][4].'</p>';  
                            }
                            //substr($selection[$i][5],strpos($selection[$i][5],"\n\n")+2);
                            break;    
                        case 'paid':
                            $value = ($selection[$i][3]['paid']?__('Yes','appointment-hour-booking'):'&nbsp;');
                            break;
                        default:
                            $value = esc_html(($selection[$i][3][$fields[$j]]==''?'&nbsp;':$selection[$i][3][$fields[$j]]))."&nbsp;";
                    }         
                    $buffer .= '<td class="cpappbcol'.$colnum.'">'.$this->make_links_clickable(trim($value), is_array(@$params[$item]) ).'</td>';                
                }
                if ($showdelete)
                    $buffer .= '<td class="cpappbcol'.($colnum++).'">[<a href="javascript:cpappb_deleteitem('.$event->id.');">'.__('delete','appointment-hour-booking').'</a>]</td>';
                    $buffer .= '</tr>';               
            }   

            $buffer .= '</table></div>';
            
            echo $buffer;            
  
		} // end pp_DashboardWidget


        private function make_links_clickable($text, $is_upload)
        {
            $link = preg_replace('!(((f|ht)tp(s)?://)[-a-zA-Zа-яА-Я()0-9@:%_+.~#?&;//=]+)!i', '<a href="$1">*LLLK*</a>', $text);
			if ($is_upload)
				$link = str_replace('*LLLK*', basename($text), $link);
			else
				$link = str_replace('*LLLK*', $text, $link);
			return $link;
        }
       
		/**
		 * log
		 */
		private function _log($adarray = array())
		{
			$h = fopen( __DIR__.'/logs.txt', 'a' );
			$log = "";
			foreach( $_REQUEST as $KEY => $VAL )
			{
				$log .= $KEY.": ".$VAL."\n";
			}
			foreach( $adarray as $KEY => $VAL )
			{
				$log .= $KEY.": ".$VAL."\n";
			}
			$log .= "================================================\n";
			fwrite( $h, $log );
			fclose( $h );
		}


    } // End Class

    // Main add-on code
    $CPAPPB_DashboardWidget_obj = new CPAPPB_DashboardWidget();

	// Add addon object to the objects list
	global $cpappb_addons_objs_list;
	$cpappb_addons_objs_list[ $CPAPPB_DashboardWidget_obj->get_addon_id() ] = $CPAPPB_DashboardWidget_obj;
}


?>