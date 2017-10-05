<?php

// 
// This file is derived of grade_export_xls.php part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once($CFG->dirroot.'/grade/export/lib.php');
require_once 'lib.php';

class grade_export_xlsgd extends grade_export {

    public $plugin = 'xlsgd';
    public $groupid = "";
  
      
      
    public function print_grades() {
        global $CFG;
        require_once($CFG->dirroot.'/lib/excellib.class.php');

        $export_tracking = $this->track_exports();

        $strgrades = get_string('grades');

    /// Calculate file name
        $shortname = format_string($this->course->shortname, true, array('context' => get_context_instance(CONTEXT_COURSE, $this->course->id)));
        $downloadfilename = clean_filename("$shortname $strgrades.xls");          
    /// Creating a workbook
        $workbook = new MoodleExcelWorkbook("-");
    /// Sending HTTP headers
        $workbook->send($downloadfilename);
    /// Adding the worksheet
        $myxls =& $workbook->add_worksheet($strgrades);

    /// Print names of all the fields
        $myxls->write_string(0,0,get_string("firstname"));
        $myxls->write_string(0,1,get_string("lastname"));
        $myxls->write_string(0,2,get_string("idnumber"));        
        $myxls->write_string(0,3,get_string("department"));        
        $myxls->write_string(0,4,get_string("institution"));
        $myxls->write_string(0,5,get_string("email"));        
        $myxls->write_string(0,6,get_string("group"));    
        $pos=7;
        
        foreach ($this->columns as $grade_item) {
            $myxls->write_string(0, $pos++, $this->format_column_name($grade_item));
           //adds date name 
            if ($grade_item->itemtype<>"course"){
               $myxls->write_string(0, $pos++, get_string("date").' '.$this->format_column_name($grade_item));
           }else{
               $myxls->write_string(0, $pos++, "");
           }

            /// add a column_feedback column
            if ($this->export_feedback) {
                $myxls->write_string(0, $pos++, $this->format_column_name($grade_item, true));
                
            }
            
        }
   
    /// Print all the lines of data.
        $i = 0;
        $prev_userid = ' ';
        $geub = new grade_export_update_buffer();       
        $gui = new graded_users_iterator_gd($this->course, $this->columns, $this->groupid);
        $gui->init();
     
            while ($userdata = $gui->next_user()) {
                $i++;
                $user = $userdata->user;     
                $myxls->write_string($i,0,$user->firstname);
                $myxls->write_string($i,1,$user->lastname);
                $myxls->write_string($i,2,$user->idnumber);                
                $myxls->write_string($i,3,$user->department);            
                $myxls->write_string($i,4,$user->institution);
                $myxls->write_string($i,5,$user->email);                        
                $myxls->write_string($i,6,$user->groupname);            
                $j=7;
                
                //comparison of previous and current users, perhaps he was in more of one group
                if ($user->id != $prev_userid) {	
                   $usergrades = $userdata->grades;
                    }
         
                 foreach ($usergrades as $itemid => $grade) {
                    if ($export_tracking) {
                        $status = $geub->track($grade);
                    }

                    $gradestr = $this->format_grade($grade);
                    if (is_numeric($gradestr)) {
                        $myxls->write_number($i,$j++,$gradestr);
                    }
                    else {
                        $myxls->write_string($i,$j++,$gradestr);
                    }

                    //adds date of graded item (last modify)
                    if(is_numeric($grade->timemodified)){ 
                        $myxls->write_string($i,$j++,date("Y.m.d",$grade->timemodified));
                        } else {
                            $myxls->write_string($i,$j++,$grade->timemodified);
                        }

                    // writing feedback if requested
                    if ($this->export_feedback) {
                        $myxls->write_string($i, $j++, $this->format_feedback($userdata->feedbacks[$itemid]));
                    }

                }
                $prev_userid = $user->id;  //assigning userid for comparison
            }
            $gui->close();        
        
        $geub->close();

    /// Close the workbook
        $workbook->close();

        exit;
    }
  /**
     * Prints preview of exported grades on screen as a feedback mechanism
     * @param bool $require_user_idnumber true means skip users without idnumber
     */
    public function display_previewgd($require_user_idnumber=false) {
        global $OUTPUT;
        echo $OUTPUT->heading(get_string('previewrows', 'grades'));
                      
        echo '<table>';
        echo '<tr>';
        echo '<th>'.get_string("firstname")."</th>".
             '<th>'.get_string("lastname")."</th>".
             '<th>'.get_string("idnumber")."</th>".          
             '<th>'.get_string("department")."</th>".                
             '<th>'.get_string("institution")."</th>".             
             '<th>'.get_string("email")."</th>".               
             '<th>'.get_string("group")."</th>";
       
               
                
        foreach ($this->columns as $grade_item) {
            echo '<th>'.$this->format_column_name($grade_item).'</th>';
            
            //add a date column
            if ($grade_item->itemtype<>"course"){
               echo '<th>'.get_string("date"). ' '.$this->format_column_name($grade_item)."</th>";
               }else{
                    echo '<th> </th>';
                   }
                   
            /// add a column_feedback column
            if ($this->export_feedback) {
                echo '<th>'.$this->format_column_name($grade_item, true).'</th>';
            }
        }
        echo '</tr>';
        
        /// Print all the lines of data.

        $i = 0;
        $prev_userid = ' ';
        $gui = new graded_users_iterator_gd($this->course, $this->columns, $this->groupid);
        $gui->init();
        while ($userdata = $gui->next_user()) {
            // number of preview rows
            if ($this->previewrows and $this->previewrows <= $i) {
                break;
            }
            $user = $userdata->user;
            if ($require_user_idnumber and empty($user->idnumber)) {
                // some exports require user idnumber so we can match up students when importing the data
                continue;
            }
             
                    
            $gradeupdated = false; // if no grade is update at all for this user, do not display this row
            $rowstr = '';
           
            //comparison of previous and current users 
            if ($user->id != $prev_userid) {	
                   $usergrades = $userdata->grades;
                    }
           foreach ($this->columns as $itemid=>$unused) {
                $gradetxt = $this->format_grade($usergrades[$itemid]);

                // get the status of this grade, and put it through track to get the status
                $g = new grade_export_update_buffer();
                $grade_grade = new grade_grade(array('itemid'=>$itemid, 'userid'=>$user->id));
                $status = $g->track($grade_grade);

                if ($this->updatedgradesonly && ($status == 'nochange' || $status == 'unknown')) {
                    $rowstr .= '<td>'.get_string('unchangedgrade', 'grades').'</td>';
                } else {
                    $rowstr .= "<td>$gradetxt</td>";
                    $gradeupdated = true;
                }
                
                //adds date of item was graded    
                    if(is_numeric($usergrades[$itemid]->timemodified)){ 
                        $rowstr .= '<td>'.date("Y.m.d",$usergrades[$itemid]->timemodified).'</td>';
                        } else {
                            $rowstr .= '<td>'.$usergrades[$itemid]->timemodified.'</td>';
                        }

                if ($this->export_feedback) {
                    $rowstr .=  '<td>'.$this->format_feedback($userdata->feedbacks[$itemid]).'</td>';
                }
                $prev_userid = $user->id;  //assigning userid for comparison
            }

            // if we are requesting updated grades only, we are not interested in this user at all
            if (!$gradeupdated && $this->updatedgradesonly) {
                continue;
            }                 
                            
            
            echo '<tr>';
            echo "<td>$user->firstname</td><td>$user->lastname</td><td>$user->idnumber</td><td>$user->department</td><td>$user->institution</td><td>$user->email</td><td>$user->groupname</td>";
            echo $rowstr;
            echo "</tr>";

            $i++; // increment the counter
        }
        echo '</table>';
        $gui->close();
    }
}


