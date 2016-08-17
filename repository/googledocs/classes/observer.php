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
        $courseid = $event->courseid;
        $course = $DB->get_record('course', array ('id'=>$courseid));
        
        $catsql = "SELECT cc.*
                  FROM {course_categories} cc
                  LEFT JOIN {course} c
                       ON cc.id = c.category
                 WHERE c.id = :courseid";
        $category = $DB->get_record_sql($catsql, array('courseid' => $courseid));
        
        switch($event->eventname) {
            case '\core\event\course_module_created':
            case '\core\event\course_module_updated':
                $cmid = ([$event->contextinstanceid]);
                $userids = self::get_google_authenticated_userids($courseid);
                if ($event->other['modulename'] == 'resource' || $event->other['modulename'] == 'folder') {
                    self::update_course_modules($category, $course, $repo, $courseid, $cmid, $userids);
                }
                break;
            case '\core\event\course_updated':
                if ($course->visible == 1) {
                    // Update module permissions
                }
                else {
                    // Remove module permissions
                }
                break;
        }
        return true;
    }

    private static function remove_permission($repo, $fileid, $email) {
        $permissionid = $repo->print_permission_id_for_email($email);
        $repo->remove_permission($fileid, $permissionid);
    }
    
    private static function update_course_modules($category, $course, $repo, $courseid, $cmids, $userids) {
        foreach ($cmids as $cmid) {
            foreach ($userids as $userid) {
                $email = self::get_google_authenticated_users_email($userid);
                $modinfo = get_fast_modinfo($courseid, $userid);
                $sections = $modinfo->get_sections();
                $cm = $modinfo->get_cm($cmid);
                $fileId = self::get_resource($cmid);
                
                foreach ($sections as $secnum=>$cms) {
                    if (in_array($cmid, $cms)) {
                        $section = $modinfo->get_section_info($secnum);
                        if ($category->visible == 1 && $course->visible == 1 && $section->uservisible && $cm->uservisible && !is_null($fileId)) { 
                            // Will need to modify to check for roles and assign permissions accordingly
                            try {
                                $repo->insert_permission($fileId, $email,  'user', 'reader');
                            } catch (Exception $e) {
                                print "An error occurred: " . $e->getMessage();
                            }
                        }
                        else if (!is_null($fileId)) {
                            // Will need to modify to check for roles and remove permissions accordingly
                            $permissionid = $repo->print_permission_id_for_email($email);
                            try {
                                $repo->remove_permission($fileId, $permissionid);
                            } catch (Exception $e) {
                                print "An error occurred: " . $e->getMessage();
                            }
                        }
                    }
                }
            }
        }
    }
    
    private static function get_resource($cmid) {
        global $DB;
        $googledocsrepo = $DB->get_record('repository', array ('type'=>'googledocs'));
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
        return $filerecord->reference;
    }

    private static function get_google_authenticated_users_email($userid) {
        global $DB;
        $googlerefreshtoken = $DB->get_record('google_refreshtokens', array ('userid'=> $userid));
        return $googlerefreshtoken->gmail;
    }
    
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

    private static function get_google_docs_repo() {
        global $DB;
        $googledocsrepo = $DB->get_record('repository', array ('type'=>'googledocs'));
        return new repository_googledocs($googledocsrepo->id);
    }
}