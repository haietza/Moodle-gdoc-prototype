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

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/repository/googledocs/lib.php');

$fileid = "18jb3m2gmm_HEuwwFkUfe4kbszcKZ-8pVGIqYZpncIaA";
$role = "reader"; // Role can be "owner", "writer" or "reader".
$permaction = $_GET['permaction'];
// print_object($_POST);

get_user_permissions($fileid, $permaction);

function get_user_permissions($fileid, $permaction) {
    global $DB, $USER, $PAGE;

    $googledocsrepo = $DB->get_record('repository', array ('type ' => 'googledocs'));
    $context = context_user::instance($USER->id);
    $repooptions = array();
    $gdocrepo = new repository_googledocs($googledocsrepo->id, $context, $repooptions);

    $course = $PAGE->course;
    $ccontext = context_course::instance($course->id);

    // Get User info.
    $userinfo = $gdocrepo->get_user_info();
    if ($userinfo) {
        $gmail = $userinfo->email;
    }

    // Pull User ids and thier associative gmail account for moodle information.
    $table = 'google_refreshtokens';
    $select = 'gmail IS NOT NULL AND gmail_active = 1';
    $params = null;
    $fields = 'userid, gmail';
    $sort = 'userid';
    $gmailids = $DB->get_records_select_menu($table, $select, $params, $sort, $fields);

    // Type could be typically either "user", "group", "domain" or "default".
    $type = "user";

    // Get User permissions from moodle.
    if ($gmailids) {
        print("MOODLE PERMISSIONS: <br/>");
        foreach ($gmailids as $userid => $gmail) {
            print("<br/>UserId ".$userid);
            // Fetch users with writer capability.
            if (has_capability('moodle/course:manageactivities', $ccontext, $userid)) {
                print("(".$gmail."): WRITER.");
                $role = "writer";
                // Fetch users with reader capability.
            } else if (has_capability('moodle/block:view', $ccontext, $userid)) {
                print("(".$gmail."): READER.");
            }
        }
        print("<br/><br/>");
    }

    // Get user permissions from google for the given resource.
    $permissions = $gdocrepo->retrieve_file_permissions($fileid);

    // Set User permissions to given google resource based on the permaction.
    switch($permaction) {
        case 'insert':
            insert_permission($fileid, $gdocrepo, $gmail, $type, $role, $permissions);
            break;
        case 'update':
            update_permission($fileid, $gdocrepo, $gmail, $type, $role, $permissions);
            break;
        case 'patch':
            patch_permission($fileid, $gdocrepo, $gmail, $type, $role, $permissions);
            break;
        case 'delete':
            remove_permission($fileid, $gdocrepo, $gmail);
            break;
        default:
            print "No action has been selected. Please choose one.";
    }
}

function insert_permission($fileid, $gdocrepo=null, $gmail, $type, $role, $permissions) {
    if ($gdocrepo) {
        // Before permissions set.
        // print("BEFORE: <br/><br/>");
        // print_permissions($fileid, $gdocrepo);

        foreach ($permissions as $userperm) {
            if ($userperm->getEmailAddress() == $gmail) {
                print("<br/> The file has been shared with the user(".$gmail.") already as a ".
                    strtoupper($userperm->getRole())."<br/>");
                return;
            }
        }

        // Insert permissions for the given file.
        $insertedperm = $gdocrepo->insert_permission($fileid, $gmail, $type, $role);
        if ($insertedperm) {
            print("<br/>Successfully inserted the permissions for the user.<br/>");
        }

        // After permissions set.
        // print("AFTER: <br/><br/>");
        // print_permissions($fileid, $gdocrepo);
    }
}

function print_permissions($fileid, $gdocrepo=null) {
    // Get user permissions from google for the given resource.
    $permissions = $gdocrepo->retrieve_file_permissions($fileid);

    // Print User permissions from Google for the given resource id.
    if ($permissions) {
        print("****************************<br/>");
        foreach ($permissions as $userperm) {
            $permissionid = $gdocrepo->print_permission_id_for_email($userperm->getEmailAddress());
            print("Name: ");
            print($userperm->getName());
            print("<br/>Role: ");
            print($userperm->getRole());
            print("<br/>Permission Id: ");
            print($permissionid);
            print("<br/><br/>");
        }
        print("****************************<br/>");
    }
}

function update_permission($fileid, $gdocrepo=null, $gmail, $type, $role, $permissions) {
    if ($gdocrepo) {
        foreach ($permissions as $userperm) {
            if ($userperm->getEmailAddress() == $gmail) {
                $newrole = ($role == 'writer') ? 'reader' : 'writer';
                $permissionid = $gdocrepo->print_permission_id_for_email($gmail);

                // Before permissions set.
                // print("BEFORE: <br/><br/>");
                // print_permissions($fileid, $gdocrepo);

                $updateperm = $gdocrepo->update_permission($fileid, $permissionid, 'writer');
                // print_object($updateperm);
                if ($updateperm) {
                    print("<br/>Successfully updated the permissions for the user.<br/>");
                }
                // After permissions set.
                // print("AFTER: <br/><br/>");
                // print_permissions($fileid, $gdocrepo);
                return;
            }
        }
        print("<br/>The current user you specified doesn't have any permission for the file. So inserting now..<br/>");
        insert_permission($fileid, $gdocrepo, $gmail, $type, $role, $permissions);
    }
}

function patch_permission($fileid, $gdocrepo=null, $gmail, $type, $role, $permissions) {
    if ($gdocrepo) {
        foreach ($permissions as $userperm) {
            if ($userperm->getEmailAddress() == $gmail) {
                $newrole = ($role == 'writer') ? 'reader' : 'writer';
                $permissionid = $gdocrepo->print_permission_id_for_email($gmail);

                // Before permissions set.
                // print("BEFORE: <br/><br/>");
                // print_permissions($fileid, $gdocrepo);

                $patchcperm = $gdocrepo->patch_permission($fileid, $permissionid, 'reader');
                if ($patchcperm) {
                    print("<br/>Successfully updated the permissions for the user.<br/>");
                }
                // After permissions set.
                // print("AFTER: <br/><br/>");
                // print_permissions($fileid, $gdocrepo);
                return;
            }
        }
        print("<br/>The current user you specified doesn't have any permission for the file. So inserting now..<br/>");
        insert_permission($fileid, $gdocrepo, $gmail, $type, $role, $permissions);
    }
}

function remove_permission($fileid, $gdocrepo, $gmail) {
    $permissionid = $gdocrepo->print_permission_id_for_email($gmail);
    $gdocrepo->remove_permission($fileid, $permissionid);
}