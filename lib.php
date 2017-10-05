<?php

// lib for xlsgd file -- Moodle Gradebook Export module 
// Derived from the grade/export/lib.php  provided in Moodle distribution
// Modified: 07/01/2013 Carina Martinez, martinez.carina1@gmail.com
// This file is part of Moodle - http://moodle.org/
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

/**
 * Functions used by gradebook plugins and reports.
 *
 * @package   moodlecore
 * @copyright 2009 Petr Skoda and Nicolas Connault
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once $CFG->libdir.'/gradelib.php';
require_once $CFG->dirroot.'/grade/lib.php';
/**
 * This class iterates over all users that are graded in a course.
 * Returns detailed info about users and their grades, including their groups' names and dates of item graded.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class graded_users_iterator_gd extends graded_users_iterator{
    public $course;
    public $courseid;
    public $grade_items;
    public $groupid;
    public $users_rs;
    public $grades_rs;
    public $gradestack;
    public $sortfield1;
    public $sortorder1;
    public $sortfield2;
    public $sortorder2;

    /**
     * Constructor
     *
     * @param object $course A course object
     * @param int $courseid the id of the course
     * @param array  $grade_items array of grade items, if not specified only user info returned
     * @param int    $groupid iterate only group users if present
     * @param string $sortfield1 The first field of the users table by which the array of users will be sorted
     * @param string $sortorder1 The order in which the first sorting field will be sorted (ASC or DESC)
     * @param string $sortfield2 The second field of the users table by which the array of users will be sorted
     * @param string $sortorder2 The order in which the second sorting field will be sorted (ASC or DESC)
     */
    public function graded_users_iterator_gd($course, $grade_items=null, $groupid=0,
                                          $sortfield1='lastname', $sortorder1='ASC',
                                          $sortfield2='firstname', $sortorder2='ASC') {
        $this->course      = $course;
        $this->courseid    = $course->id;
        $this->grade_items = $grade_items;
        $this->groupid     = $groupid;
        $this->sortfield1  = $sortfield1;
        $this->sortorder1  = $sortorder1;
        $this->sortfield2  = $sortfield2;
        $this->sortorder2  = $sortorder2;

        $this->gradestack  = array();
    }

    /**
     * Initialise the iterator
     * @return boolean success
     */
     public function init() {
        global $CFG, $DB;

        $this->close();

        grade_regrade_final_grades($this->course->id);
        $course_item = grade_item::fetch_course_item($this->course->id);
        if ($course_item->needsupdate) {
            // can not calculate all final grades - sorry
            return false;
        }

        $coursecontext = CONTEXT_COURSE::instance($this->courseid);
        $relatedcontexts = $coursecontext->get_parent_context_ids(true);

        list($gradebookroles_sql, $params1) =
            $DB->get_in_or_equal(explode(',', $CFG->gradebookroles), SQL_PARAMS_NAMED, 'grbr');
		list($relatedcontexts, $params2) =
            $DB->get_in_or_equal($relatedcontexts, SQL_PARAMS_NAMED, 'grbr');
		$params = array_merge($params1, $params2);

        //limit to users with an active enrolment
        list($enrolledsql, $enrolledparams) = get_enrolled_sql($coursecontext);

        $params = array_merge($params, $enrolledparams);
        
        // adds joins for groups tables 
        if ($this->groupid) {
            $groupsql = "INNER JOIN {groups_members} gm ON gm.userid = u.id
                                INNER JOIN {groups} grinner ON grinner.id = gm.groupid ";
            $groupwheresql = "AND gm.groupid = :groupid";
            // $params contents: gradebookroles
            $params['groupid'] = $this->groupid;
        } else {
            $groupsql = "LEFT JOIN ({groups_members} gm 
                           INNER JOIN (
                                        SELECT * FROM {groups} gr  
                                        WHERE gr.courseid = :courseid
                                     ) grinner ON gm.groupid = grinner.id                                     
                                   ) ON gm.userid = u.id ";
            $groupwheresql = " ";           
            $params['courseid'] = $this->courseid;
        }

        if (empty($this->sortfield1)) {
            // we must do some sorting even if not specified
            $ofields = ", u.id AS usrt";
            $order   = "usrt ASC";

        } else {
            $ofields = ", u.$this->sortfield1 AS usrt1";
            $order   = "usrt1 $this->sortorder1";
            if (!empty($this->sortfield2)) {
                $ofields .= ", u.$this->sortfield2 AS usrt2";
                $order   .= ", usrt2 $this->sortorder2";
            }
            if ($this->sortfield1 != 'id' and $this->sortfield2 != 'id') {
                // user order MUST be the same in both queries,
                // must include the only unique user->id if not already present
                $ofields .= ", u.id AS usrt";
                $order   .= ", usrt ASC";
            }
        }

        // $params contents: gradebookroles and groupid (for $groupwheresql)
        $users_sql = "SELECT u.*, grinner.name AS groupname $ofields
                        FROM {user} u 
                        JOIN ($enrolledsql) je ON je.id = u.id
                             $groupsql                                                                    
                        JOIN (
                                  SELECT DISTINCT ra.userid
                                    FROM {role_assignments} ra
                                   WHERE ra.roleid $gradebookroles_sql
                                     AND ra.contextid $relatedcontexts
                             ) rainner ON rainner.userid = u.id
                         WHERE u.deleted = 0 
                             $groupwheresql
                    ORDER BY $order";
        $this->users_rs = $DB->get_recordset_sql($users_sql, $params);

        if (!empty($this->grade_items)) {
            $itemids = array_keys($this->grade_items);
            list($itemidsql, $grades_params) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'items');
            $params = array_merge($params, $grades_params);
            // $params contents: gradebookroles, enrolledparams, groupid (for $groupwheresql) and itemids

            $grades_sql = "SELECT g.* $ofields
                             FROM {grade_grades} g
                             JOIN {user} u ON g.userid = u.id
                             JOIN ($enrolledsql) je ON je.id = u.id
                                  $groupsql
                             JOIN (
                                      SELECT DISTINCT ra.userid
                                        FROM {role_assignments} ra
                                       WHERE ra.roleid $gradebookroles_sql
                                         AND ra.contextid $relatedcontexts
                                  ) rainner ON rainner.userid = u.id
                              WHERE u.deleted = 0
                              AND g.itemid $itemidsql
                              $groupwheresql
                         ORDER BY $order, g.itemid ASC";
            $this->grades_rs = $DB->get_recordset_sql($grades_sql, $params);
        } else {
            $this->grades_rs = false;
        }

        return true;
    }
   
}

