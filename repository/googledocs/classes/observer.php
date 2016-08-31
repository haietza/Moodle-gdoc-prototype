<?php
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
 * Observer class containing methods monitoring various events.
 *
 * @package    tool_monitor
 * @copyright  2014 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/repository/googledocs/lib.php');

/**
 * Observer class containing methods monitoring various events.
 *
 * @since      Moodle 3.0
 * @package    repository_googledocs
 * @copyright  2016 Gedion Woldeselassie <gedion@umn.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_googledocs_observer {
    
    /**
     * Sync google resource permissions based on various events.
     *
     * @param \core\event\* $event The event fired.
     */
    public static function manage_resources($event) {
        global $DB;
        $repo = self::get_google_docs_repo();
        
        switch($event->eventname) {
            case '\core\event\course_category_updated':
                $categoryid = $event->objectid;
                $courses = self::get_courses($categoryid);
                foreach ($courses as $course) {
                    $courseid = $course->id;
                    $userids = self::get_google_authenticated_userids($courseid);
                    $coursemodinfo = get_fast_modinfo($courseid, -1);
                    $coursemods = $coursemodinfo->get_cms();
                    $cms = array();
                    $cmids = array();
                    foreach ($coursemods as $cm) {
                        if ($cm->modname == 'resource') {
                            $cmids[] = $cm->id;
                            $cms[] = $cm;
                        }
                    }
                    if ($course->visible == 1) {
                        foreach ($cms as $cm) {
                            $cmid = $cm->id;
                            if ($cm->visible == 1) {
                                rebuild_course_cache($courseid, true);
                                foreach ($userids as $userid) {
                                    $modinfo = get_fast_modinfo($courseid, $userid);
                                    $cminfo = $modinfo->get_cm($cmid);
                                    $sectionnumber = self::get_cm_sectionnum($cmid);
                                    $secinfo = $modinfo->get_section_info($sectionnumber);
                                    if ($cminfo->uservisible && $secinfo->available) {
                                        self::insert_cm_permission($cmid, $userid, $repo);
                                    }
                                    else {
                                        self::remove_cm_permission($cmid, $userid, $repo);
                                    }
                                }
                            }
                            else {
                                foreach ($userids as $userid) {
                                    self::remove_cm_permission($cmid, $userid, $repo);
                                }
                            }
                        }
                    }
                    else {
                        foreach ($cmids as $cmid) {
                            foreach ($userids as $userid) {
                                self::remove_cm_permission($cmid, $userid, $repo);
                            }
                        }
                    }
                }
                break;
            case '\core\event\course_updated':
                $courseid = $event->courseid;
                $course = $DB->get_record('course', array('id'=>$courseid), 'visible');
                $userids = self::get_google_authenticated_userids($courseid);
                $coursemodinfo = get_fast_modinfo($courseid, -1);
                $cms = $coursemodinfo->get_cms();
                $cmids = array();
                foreach ($cms as $cm) {
                    $cmids[] = $cm->id;
                }
                if ($course->visible == 1) {
                    foreach ($cms as $cm) {
                        $cmid = $cm->id;
                        if ($cm->visible == 1) {
                            rebuild_course_cache($courseid, true);
                            foreach ($userids as $userid) {
                                $modinfo = get_fast_modinfo($courseid, $userid);
                                $cminfo = $modinfo->get_cm($cmid);
                                $sectionnumber = self::get_cm_sectionnum($cmid);
                                $secinfo = $modinfo->get_section_info($sectionnumber);
                                if ($cminfo->uservisible && $secinfo->available) {
                                    self::insert_cm_permission($cmid, $userid, $repo);
                                }
                                else {
                                    self::remove_cm_permission($cmid, $userid, $repo);
                                }
                            }
                        }
                        else {
                            foreach ($userids as $userid) {
                                self::remove_cm_permission($cmid, $userid, $repo);
                            }
                        }
                    }   
                }
                else {
                    foreach ($cmids as $cmid) {
                        foreach ($userids as $userid) {
                            self::remove_cm_permission($cmid, $userid, $repo);
                        }
                    }
                }
                break;
            case '\core\event\course_section_updated':
                $courseid = $event->courseid;
                $course = $DB->get_record('course', array('id'=>$courseid), 'visible');
                $userids = self::get_google_authenticated_userids($courseid);
                //$modinfo = get_fast_modinfo($courseid, -1);
                $sectionnumber = $event->other['sectionnum'];
                $cms = self::get_section_course_modules($sectionnumber);
                if ($course->visible == 1) {
                    foreach ($cms as $cm) {
                        $cmid = $cm->cmid;
                        if ($cm->cmvisible == 1) {
                            rebuild_course_cache($courseid, true);
                            foreach ($userids as $userid) {
                                $modinfo = get_fast_modinfo($courseid, $userid);
                                $cminfo = $modinfo->get_cm($cmid);
                                $sectionnumber = self::get_cm_sectionnum($cmid);
                                $secinfo = $modinfo->get_section_info($sectionnumber);
                                if ($cminfo->uservisible && $secinfo->available) {
                                    self::insert_cm_permission($cmid, $userid, $repo);
                                }
                                else {
                                    self::remove_cm_permission($cmid, $userid, $repo);
                                }
                            }
                        }
                        else {
                            foreach ($userids as $userid) {
                                self::remove_cm_permission($cmid, $userid, $repo);
                            }
                        }
                    }
                }
                else {
                    foreach ($cms as $cm) {
                        $cmid = $cm->id;
                        foreach ($userids as $userid) {
                            self::remove_cm_permission($cmid, $userid, $repo);
                        }
                    }
                }
                break;
            case '\core\event\course_module_created':
                // Deal with file permissions
                $courseid = $event->courseid;
                $course = $DB->get_record('course', array('id'=>$courseid), 'visible');
                $userids = self::get_google_authenticated_userids($courseid);
                $cmid = $event->contextinstanceid;
                if ($course->visible == 1) {
                    $cm = self::get_course_module($cmid);
                    if ($cm->visible == 1) {
                        rebuild_course_cache($courseid, true);
                        foreach ($userids as $userid) {
                            $modinfo = get_fast_modinfo($courseid, $userid);
                            $sectionnumber = self::get_cm_sectionnum($cmid);
                            $secinfo = $modinfo->get_section_info($sectionnumber);
                            $cminfo = $modinfo->get_cm($cmid);
                            if ($cminfo->uservisible && $secinfo->available) {
                                self::insert_cm_permission($cmid, $userid, $repo);
                            }
                            else {
                                self::remove_cm_permission($cmid, $userid, $repo);
                            }
                        }
                    }
                    else {
                        foreach ($userids as $userid) {
                            self::remove_cm_permission($cmid, $userid, $repo);
                        }
                    }
                }
                else {
                    foreach ($userids as $userid) {
                        self::remove_cm_permission($cmid, $userid, $repo);
                    }
                }
                
                // Store cmid and reference
                $newdata = new stdClass();
                $newdata->cmid = $cmid;
                $newdata->reference = self::get_resource($cmid);
                if ($newdata->reference) {
                    $DB->insert_record('google_files_reference', $newdata);
                }
                break;
            case '\core\event\course_module_updated':
                // Deal with file permissions
                $courseid = $event->courseid;
                $course = $DB->get_record('course', array('id'=>$courseid), 'visible');
                $userids = self::get_google_authenticated_userids($courseid);
                $cmid = $event->contextinstanceid;
                if ($course->visible == 1) {
                    $cm = self::get_course_module($cmid);
                    if ($cm->visible == 1) {
                        rebuild_course_cache($courseid, true);
                        foreach ($userids as $userid) {
                            $modinfo = get_fast_modinfo($courseid, $userid);
                            $sectionnumber = self::get_cm_sectionnum($cmid);
                            $secinfo = $modinfo->get_section_info($sectionnumber);
                            $cminfo = $modinfo->get_cm($cmid);
                            if ($cminfo->uservisible && $secinfo->available) {
                                self::insert_cm_permission($cmid, $userid, $repo);
                                $existing = $DB->get_record('google_files_reference', array('cmid' => $cmid), 'id');
                                if ($existing) {
                                    self::remove_cm_permission($cmid, $userid, $repo);
                                }
                            }
                            else {
                                self::remove_cm_permission($cmid, $userid, $repo);
                            }
                        }
                    }
                    else {
                        foreach ($userids as $userid) {
                            self::remove_cm_permission($cmid, $userid, $repo);
                        }
                    }
                }
                else {
                    foreach ($userids as $userid) {
                        self::remove_cm_permission($cmid, $userid, $repo);
                    }
                }
                
                // Update course module reference
                $newdata = new stdClass();
                $newdata->cmid = $cmid;
                $newdata->reference = self::get_resource($cmid);
                
                if (!is_null($newdata->cmid) && $newdata->reference) {
                    $reference = $DB->get_record('google_files_reference', array ('cmid'=>$cmid), 'id, reference');
                    if ($reference) {
                        $newdata->id = $reference->id;
                        if ($newdata->reference != $reference->reference) {
                            $DB->update_record('google_files_reference', $newdata);
                        }
                    }
                }
                break;
            case '\core\event\course_module_deleted':
                if ($event->other['modulename'] == 'resource') {
                    $courseid = $event->courseid;
                    $userids = self::get_google_authenticated_userids($courseid);
                    $cmid = $event->contextinstanceid;
                    $gcmid = $DB->get_record('google_files_reference', array('cmid' => $cmid), 'id');
                    if ($gcmid) {
                        foreach ($userids as $userid) {
                            self::remove_cm_permission($cmid, $userid, $repo);
                        }
                    }
                    
                    // Delete course module reference
                    $gcmid = $DB->get_record('google_files_reference', array('cmid' => $cmid), 'id');
                    if ($gcmid) {
                        $DB->delete_records('google_files_reference', array('cmid' => $cmid));
                    }
                }
                break;
        }
        return true;
    }
    
    // Get course records for category
    private static function get_courses($categoryid) {
        global $DB;
        $courses = $DB->get_records('course', array('category' => $categoryid), 'id', 'id, visible');
        return $courses;
    }
    
    // Get section number for course module
    private static function get_cm_sectionnum($cmid) {
        global $DB;
        $sql = "SELECT cs.section
                FROM {course_sections} cs
                LEFT JOIN {course_modules} cm
                ON cm.section = cs.id
                WHERE cm.id = :cmid";
        $section = $DB->get_record_sql($sql, array('cmid' => $cmid));
        return $section->section;
    }
    
    // Get course module record
    private static function get_course_module($cmid) {
        global $DB;
        $cm = $DB->get_record('course_modules', array('id' => $cmid), 'visible');
        return $cm;
    }
    
    // Get course module records for section
    private static function get_section_course_modules($sectionnumber) {
        global $DB;
        $sql = "SELECT cm.id as cmid, cm.visible as cmvisible, cs.id as csid, cs.visible as csvisible
                FROM {course_modules} cm
                LEFT JOIN {course_sections} cs 
                ON cm.section = cs.id 
                WHERE cs.section = :sectionnum;";
        $cms = $DB->get_records_sql($sql, array('sectionnum' => $sectionnumber));
        return $cms;
    }
    
    // Add permission for specified user for specified module
    // Assumes all visibility and availability checks have been done before calling
    private static function insert_cm_permission($cmid, $userid, $repo) {
        $email = self::get_google_authenticated_users_email($userid);
        $fileid = self::get_resource($cmid);
        if ($fileid) {
            try {
                $repo->insert_permission($fileid, $email,  'user', 'reader');
            } catch (Exception $e) {
                print "An error occurred: " . $e->getMessage();
            }
        }
    }
    
    // Remove permission for specified user for specified module
    private static function remove_cm_permission($cmid, $userid, $repo) {
        global $DB;
        $email = self::get_google_authenticated_users_email($userid);
        //$fileid = self::get_resource($cmid);
        //if (!$fileid) {
            $filerec = $DB->get_record('google_files_reference', array('cmid' => $cmid), 'reference');
            $fileid = $filerec->reference;
        //}
        if ($fileid) {
            try {
                $permissionid = $repo->print_permission_id_for_email($email);
                $repo->remove_permission($fileid, $permissionid);
            } catch (Exception $e) {
                print "An error occurred: " . $e->getMessage();
            }
        }
    }
    
    // Get fileid for course module
    private static function get_resource($cmid) {
        global $DB;
        $googledocsrepo = $DB->get_record('repository', array ('type'=>'googledocs'), 'id');
        $id = $googledocsrepo->id;
        if (empty($id)) {
            // We did not find any instance of googledocs.
            mtrace('Could not find any instance of the repository');
            return;
        }
        
        $sql = "SELECT DISTINCT r.reference
                FROM {files_reference} r
                LEFT JOIN {files} f
                ON r.id = f.referencefileid
                LEFT JOIN {context} c
                ON f.contextid = c.id
                LEFT JOIN {course_modules} cm
                ON c.instanceid = cm.id
                WHERE cm.id = :cmid
                AND r.repositoryid = :repoid
                AND f.referencefileid IS NOT NULL
                AND not (f.component = :component and f.filearea = :filearea)";
        $filerecord = $DB->get_record_sql($sql, array('component' => 'user', 'filearea' => 'draft', 'repoid' => $id, 'cmid' => $cmid));
        if ($filerecord) {
            return $filerecord->reference;
        }
        else {
            return false;
        }
    }
    
    // Get gmail address for user
    private static function get_google_authenticated_users_email($userid) {
        global $DB;
        $googlerefreshtoken = $DB->get_record('google_refreshtokens', array ('userid'=> $userid), 'gmail');
        if ($googlerefreshtoken) {
            return $googlerefreshtoken->gmail;
        }
        else {
            return false;
        }
    }
    
    // Get Google authenticated userids for course
    private static function get_google_authenticated_userids($courseid) {
        global $DB;
        $sql = "SELECT DISTINCT grt.userid
                FROM {user} eu1_u
                JOIN {google_refreshtokens} grt
                ON eu1_u.id = grt.userid
                JOIN {user_enrolments} eu1_ue
                ON eu1_ue.userid = eu1_u.id
                JOIN {enrol} eu1_e
                ON (eu1_e.id = eu1_ue.enrolid AND eu1_e.courseid = :courseid)
                WHERE eu1_u.deleted = 0 AND eu1_u.id <> :guestid ";
        $users = $DB->get_recordset_sql($sql, array('courseid' => $courseid, 'guestid' => '1'));
        $usersarray = array();
        foreach($users as $user) {
            $usersarray[] = $user->userid;
        }
        return $usersarray;
    }
    
    // Get Google Drive repo id
    private static function get_google_docs_repo() {
        global $DB;
        $googledocsrepo = $DB->get_record('repository', array ('type'=>'googledocs'), 'id');
        return new repository_googledocs($googledocsrepo->id);
    }
}