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
 * The gradebook grader report
 *
 * @package   gradereport_grader
 * @copyright 2007 Moodle Pty Ltd (http://moodle.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/user/renderer.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->dirroot.'/grade/report/morozov/lib.php');
echo '<script src="http://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>';

$courseid      = required_param('id', PARAM_INT);        // course id
$page          = optional_param('page', 0, PARAM_INT);   // active page
$edit          = optional_param('edit', -1, PARAM_BOOL); // sticky editting mode

$sortitemid    = optional_param('sortitemid', 0, PARAM_ALPHANUM); // sort by which grade item
$action        = optional_param('action', 0, PARAM_ALPHAEXT);
$move          = optional_param('move', 0, PARAM_INT);
$type          = optional_param('type', 0, PARAM_ALPHA);
$target        = optional_param('target', 0, PARAM_ALPHANUM);
$toggle        = optional_param('toggle', null, PARAM_INT);
$toggle_type   = optional_param('toggle_type', 0, PARAM_ALPHANUM);

$graderreportsifirst  = optional_param('sifirst', null, PARAM_NOTAGS);
$graderreportsilast   = optional_param('silast', null, PARAM_NOTAGS);

// The report object is recreated each time, save search information to SESSION object for future use.
if (isset($graderreportsifirst)) {
    $SESSION->gradereport['filterfirstname'] = $graderreportsifirst;
}
if (isset($graderreportsilast)) {
    $SESSION->gradereport['filtersurname'] = $graderreportsilast;
}

$PAGE->set_url(new moodle_url('/grade/report/morozov/index.php', array('id'=>$courseid)));
$PAGE->requires->yui_module('moodle-gradereport_grader-gradereporttable', 'Y.M.gradereport_morozov.init', null, null, true);

// basic access checks
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('nocourseid');
}
require_login($course);
$context = context_course::instance($course->id);

require_capability('gradereport/morozov:view', $context);
require_capability('moodle/grade:viewall', $context);

// return tracking object
$gpr = new grade_plugin_return(array('type'=>'report', 'plugin'=>'morozov', 'courseid'=>$courseid, 'page'=>$page));

// last selected report session tracking
if (!isset($USER->grade_last_report)) {
    $USER->grade_last_report = array();
}
$USER->grade_last_report[$course->id] = 'morozov';

// Build editing on/off buttons

if (!isset($USER->gradeediting)) {
    $USER->gradeediting = array();
}

if (has_capability('moodle/grade:edit', $context)) {
    if (!isset($USER->gradeediting[$course->id])) {
        $USER->gradeediting[$course->id] = 0;
    }

    if (($edit == 1) and confirm_sesskey()) {
        $USER->gradeediting[$course->id] = 1;
    } else if (($edit == 0) and confirm_sesskey()) {
        $USER->gradeediting[$course->id] = 0;
    }

    // page params for the turn editting on
    $options = $gpr->get_options();
    $options['sesskey'] = sesskey();

    if ($USER->gradeediting[$course->id]) {
        $options['edit'] = 0;
        $string = get_string('turneditingoff');
    } else {
        $options['edit'] = 1;
        $string = get_string('turneditingon');
    }

    $buttons = new single_button(new moodle_url('index.php', $options), $string, 'get');
} else {
    $USER->gradeediting[$course->id] = 0;
    $buttons = '';
}

$gradeserror = array();

// Handle toggle change request
if (!is_null($toggle) && !empty($toggle_type)) {
    set_user_preferences(array('grade_report_show'.$toggle_type => $toggle));
}

//first make sure we have proper final grades - this must be done before constructing of the grade tree
grade_regrade_final_grades($courseid);

// Perform actions
if (!empty($target) && !empty($action) && confirm_sesskey()) {
    grade_report_grader::do_process_action($target, $action, $courseid);
}

$reportname = get_string('pluginname', 'gradereport_morozov');

// Print header
print_grade_page_head($COURSE->id, 'report', 'morozov', $reportname, false, $buttons);

//Initialise the grader report object that produces the table
//the class grade_report_grader_ajax was removed as part of MDL-21562
$report = new grade_report_grader($courseid, $gpr, $context, $page, $sortitemid);
$numusers = $report->get_numusers(true, true);

// make sure separate group does not prevent view
if ($report->currentgroup == -2) {
    echo $OUTPUT->heading(get_string("notingroup"));
    echo $OUTPUT->footer();
    exit;
}

// processing posted grades & feedback here
if ($data = data_submitted() and confirm_sesskey() and has_capability('moodle/grade:edit', $context)) {
    $warnings = $report->process_data($data);
} else {
    $warnings = array();
}

// final grades MUST be loaded after the processing
$report->load_users();
$report->load_final_grades();
echo $report->group_selector;

// User search
/*$url = new moodle_url('/grade/report/morozov/index.php', array('id' => $course->id));
$firstinitial = isset($SESSION->gradereport['filterfirstname']) ? $SESSION->gradereport['filterfirstname'] : '';
$lastinitial  = isset($SESSION->gradereport['filtersurname']) ? $SESSION->gradereport['filtersurname'] : '';
$totalusers = $report->get_numusers(true, false);
$renderer = $PAGE->get_renderer('core_user');
echo $renderer->user_search($url, $firstinitial, $lastinitial, $numusers, $totalusers, $report->currentgroupname);*/

//show warnings if any
foreach ($warnings as $warning) {
    echo $OUTPUT->notification($warning);
}

$studentsperpage = $report->get_students_per_page();
// Don't use paging if studentsperpage is empty or 0 at course AND site levels
if (!empty($studentsperpage)) {
    echo $OUTPUT->paging_bar($numusers, $report->page, $studentsperpage, $report->pbarurl);
}

$displayaverages = true;
if ($numusers == 0) {
    $displayaverages = false;
}

$reporthtml = $report->get_grade_table($displayaverages);
/////////////////////////////////////////////////////////////////////////////////////////////////////////////;
/*$testright =$report->get_right_rows(true);
var_dump($testright[0]);
	for ($i = 1; $i <= count($testleft)-1; $i++) 
	{ 	
		$hinne[] = ($testright[$i]->cells[0]->text);
		$taisNimi[] = ($testleft[$i]->cells[0]->text);
	}
	echo "<table>";
    echo "<tr>";
    echo  "<td>Nimi ja perkonnanimi</td>";
    echo  "</tr>";
	for($j = 1; $j <= count($testleft)-1; $j++){
		echo '<tr>';
		echo "<td>";
		echo $taisNimi[$j];
		echo "</td>";
		echo "<td>";
		echo $hinne[$j];
		echo "</td>";
		echo '</tr>';
	}
    echo "</table>";
	echo $courseid;
    echo "<br>".'<input type="text" class="form-control" id="search" placeholder="Keda sa otsid?">';*/
/////////////////////////////////////////////////////////////////////////////////////////////////////////////
/*    echo "<script>";
    echo "      var pere=null;";
    echo "            $(document).ready(function(){";
    echo "            $('#myTextbox1').on('input', function() {";
    echo '                                    $.ajax({url: "asd.php?firstname="+this.value, success: function(result){';
    echo '                    $("#div1").html(result);';
    echo "                }";
    echo "            });";
    echo "                                    });";
    echo "            });";
    echo "    $(function () {";
    echo '            $("#myTextbox1").on("keypress keyup change", function () {';
    echo "                var show_flag = true;";
    echo "                $('#myTextbox1').each( function(i) {";
    echo '                    if ($(this).val() == "") {';
    echo "                        show_flag = false;} });";
    echo "               if (show_flag){";
    echo '                        $("#div1").show();} else {$("#div1").hide();}});});';
    echo "</script>";
    echo '    <div id="div1"><h2>Let jQuery AJAX Change This Text</h2></div>';
    echo "    <input id='myTextbox1' type='text'/>";*/
///////////////////////////////////////////////////////////////////////////////////////////////////////////////    
    /*echo "<script>";
    echo "Array.prototype.contains = function(v) {";
    echo "for(var i = 0; i < this.length; i++) {";
    echo " if(this[i] === v) return true;}";
    echo "return false;};";
    echo "Array.prototype.unique = function() {";
    echo "var arr = [];";
    echo "for(var i = 0; i < this.length; i++) {";
    echo "if(!arr.contains(this[i])) {";
    echo "arr.push(this[i]);}}return arr;}";
    echo "var ajaxResult=[];";
    echo "var names =[];";
    echo "var lastnames = [];";
    echo "var grades =[];";
    echo "var gradeNames =[];";
    echo "var ids =[];";
    echo "var pere=null;";
    echo "var out ='';";
    echo "$(document).ready(function(){";
    echo "$('#myTextbox1').on('input', function() {";
    echo "if(this.value.length>=2){";
    echo "$.ajax({";
    echo "type: 'GET'";
    echo "url: 'asd.php?firstname='+this.value,";
    echo "success: function(result){";
    echo "ajaxResult=[];";
    echo "ajaxResult.push(result);";
    echo "var arr = JSON.parse(ajaxResult);";
    echo "for(j = 0; j<arr.length; j++){";
    echo "var gradeResult = arr[j].finalgrade;";
    echo "var gradeNamesResult = arr[j].itemname;";
    echo "var namesResult = arr[j].firstname;";
    echo "var lastnamesResult = arr[j].lastname;";
    echo "var idsResult = arr[j].id;";
    echo "ids.push(idsResult);";
    echo "names.push(namesResult);";
    echo "lastnames.push(lastnamesResult);";
    echo "grades.push(gradeResult);";
    echo "grades.push(gradeResult);";
    echo "if(arr[j].itemname == ''){arr[j].itemname = 'Category(Test2)';}";
    echo "if(arr[j].itemname == null){arr[j].itemname = 'Summary';}";
    echo "names = names.unique();";
    echo "lastnames = lastnames.unique();";
    echo "var korda = names.length;}";
    echo "for(z = 0; z <korda; z++){";
    echo "out +='<h1>'+names[z]+' '+lastnames[z]+'</h1>';";
    echo "out += '<table class='table'>';";
    echo "out+= '<tr><th>Kategooria</th><th>Hinne</th></tr>';";
    echo "for(i = 0; i < arr.length; i++) {";
    echo "if(ids[z]==arr[i].userid){";
    echo "out += '<tr><td>' + ";
    echo "arr[i].itemname +";
    echo "'</td><td>'+";
    echo "arr[i].userid+";
    echo "'</td><td>'+";
    echo "arr[i].id+";
    echo "'</td><td>'+";
    echo "arr[i].finalgrade +";
    echo "'</td></tr>';";
    echo "grades=[];";
    echo "gradeNames=[];}}";
    echo "out += '</table>';";
    echo "}names=[];lastnames=[];ids=[];document.getElementById('div1').innerHTML = out;}";
    echo "});}out='';});});";/*
    echo "$(function () {";
    echo "$('#myTextbox1').on('keypress keyup change', function () {";
    echo "$('#myTextbox1').each( function(i) {";
    echo "if ($(this).val() == '') {";
    echo "show_flag = false;} });";
    echo "if (show_flag && this.value.length>=2){";
    echo '$("#div1").show();} else {$("#div1").hide();}});});';
    echo "</script>";
    echo "<input id='myTextbox1' type='text'/>";
    echo "<div id='panel'>";
    echo "<div class='container'>";
    echo "<div class='input-prepend'>";
    echo "<div id='div1'></div>";
    echo "</div></div></div>";*/
    echo "
    <style>
    table {
        border-collapse: collapse;
        width: 100%;
    }

    th, td {
        padding: 8px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    tr:hover{background-color:#f5f5f5}
    </style>
    ";
    echo "<script src='http://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js'></script>";
    echo "
                <script>
                Array.prototype.contains = function(v) {
                    for(var i = 0; i < this.length; i++) {
                        if(this[i] === v) return true;
                    }
                    return false;
                };

                Array.prototype.unique = function() {
                    var arr = [];
                    for(var i = 0; i < this.length; i++) {
                        if(!arr.contains(this[i])) {
                            arr.push(this[i]);
                        }
                    }
                    return arr; 
                }
                var ajaxResult=[];
                var names =[];
                var lastnames = [];
                var grades =[];
                var gradeNames =[];
                var ids =[];
                var pere=null;
                var out ='';
                $(document).ready(function(){
                $('#myTextbox1').on('input', function() {
                    if(this.value.length>=2){
                        $.ajax({
                            type: 'GET',
                            url: 'asd.php?firstname='+this.value,
                            success: function(result){
                                ajaxResult=[];
                                ajaxResult.push(result);
                                var arr = JSON.parse(ajaxResult);
                                //console.log(arr);
                                for(j = 0; j<arr.length; j++){
                                    var gradeResult = arr[j].finalgrade;
                                    var gradeNamesResult = arr[j].itemname;
                                    var namesResult = arr[j].firstname;
                                    var lastnamesResult = arr[j].lastname;
                                    var idsResult = arr[j].id;

                                    ids.push(idsResult);
                                    names.push(namesResult);
                                    lastnames.push(lastnamesResult);
                                    grades.push(gradeResult);
                                    gradeNames.push(gradeNamesResult);
                                    if(arr[j].itemname == ''){arr[j].itemname = 'Test2 total';}
                                    if(arr[j].itemname == null){arr[j].itemname = 'Course total';}
                                    
                                    names = names.unique();
                                    lastnames = lastnames.unique();
                                    console.log(arr[j].itemname);

                                    /*console.log(names);
                                    console.log(lastnames);
                                    console.log(gradeNames);
                                    console.log(grades);*/
                                    var korda = names.length;

                                }
                                for(z = 0; z <korda; z++){
                                out += '<h1>'+names[z]+' '+lastnames[z]+'</h1>';
                                out += '<table>';
                                out += '<tr><th>Kategooria</th><th>Hinne</th></tr>';
                                for(i = 0; i < arr.length; i++) {
                                    if(ids[z]==arr[i].userid){
                                    out += '<tr><td>' + 
                                    arr[i].itemname +
                                    '</td><td>'+
                                    arr[i].finalgrade +
                                    '</td></tr>';
                                    grades=[];
                                    gradeNames=[];}


                                }
                                out += '</table>';
                                }names=[];lastnames=[];ids=[];document.getElementById('div1').innerHTML = out;}
                                
                        });}out='';});});
                $(function () {
                $('#myTextbox1').on('keypress keyup change', function () {
                    var show_flag = true;
                    $('#myTextbox1').each( function(i) {
                        if ($(this).val() == '') {
                            show_flag = false;} });
                    if (show_flag && this.value.length>=2){";
echo '                        $("#div1").show();} else {$("#div1").hide();}});});

                </script>
                <input id="myTextbox1" type="text"/>
                <div id="panel">
                    <div class="container">
                        <div class="input-prepend">
                                    <div id="div1"></div>
                        </div>
                    </div>
                </div>


    ';
    

// print submit button
if ($USER->gradeediting[$course->id] && ($report->get_pref('showquickfeedback') || $report->get_pref('quickgrading'))) {
    echo '<form action="index.php" enctype="application/x-www-form-urlencoded" method="post" id="gradereport_morozov">'; // Enforce compatibility with our max_input_vars hack.
    echo '<div>';
    echo '<input type="hidden" value="'.s($courseid).'" name="id" />';
    echo '<input type="hidden" value="'.sesskey().'" name="sesskey" />';
    echo '<input type="hidden" value="'.time().'" name="timepageload" />';
    echo '<input type="hidden" value="morozov" name="report"/>';
    echo '<input type="hidden" value="'.$page.'" name="page"/>';
    //echo $reporthtml;
    echo '<div class="submit"><input type="submit" id="gradersubmit" value="'.s(get_string('savechanges')).'" /></div>';
    echo '</div></form>';
} else {
    echo '<script>';
    echo '$("#search").on("keyup", function() {';
        echo 'var value = $(this).val();';
        echo '$("table tr").each(function(index) {';
            echo 'if (index !== 0) {';
                echo '$row = $(this);';
                echo 'var id = $row.find("td:first").text();';
                echo 'if (id.indexOf(value) !== 0) {';
                echo '$row.hide();}';
                echo 'else {$row.show();}}});});';
                echo '</script>';
}

// prints paging bar at bottom for large pages
if (!empty($studentsperpage) && $studentsperpage >= 20) {
    echo $OUTPUT->paging_bar($numusers, $report->page, $studentsperpage, $report->pbarurl);
}

$event = \gradereport_morozov\event\grade_report_viewed::create(
    array(
        'context' => $context,
        'courseid' => $courseid,
    )
);
$event->trigger();

echo $OUTPUT->footer();
