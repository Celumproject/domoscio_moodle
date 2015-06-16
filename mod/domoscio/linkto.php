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
 * This is the view where an editing teacher can link a freshly created quiz
 * to an existing resource
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_domoscio
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace domoscio with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/classes/linkto_form.php');

$config = get_config('domoscio');
$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$q = optional_param('q', 0, PARAM_INT);
$kn = optional_param('notion', 0, PARAM_INT);

if ($id) {
    $cm         = $DB->get_record('course_modules', array('id' => $id), '*', MUST_EXIST);
    $course     = get_course($cm->course);
    $domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
}


require_course_login($course);


$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/index.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_title($domoscio->name);
$PAGE->set_heading($course->fullname." > ".$domoscio->name);
$PAGE->set_pagelayout('incourse');

$rest = new domoscio_client();

$resource = json_decode($rest->setUrl($config, 'knowledge_nodes', $domoscio->resource_id)->get());
$notion = json_decode($rest->setUrl($config, 'knowledge_nodes', $kn)->get());

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('choose_q', 'domoscio'));

$linked_module = get_resource_info($resource->id);

echo html_writer::tag('div', get_string('linkto_intro', 'domoscio').html_writer::tag('b', $linked_module->display." - ".$notion->name, array('class' => '')), array('class' => 'block mod_introbox'));

$mform = new linkto_form("$CFG->wwwroot/mod/domoscio/linkto.php?id=$cm->id&notion=$kn", array('course' => $course->id, 'kn_id' => $notion->id));


if ($mform->is_cancelled()) {

  redirect("$CFG->wwwroot/mod/domoscio/view.php?id=".$cm->id);
  exit;

} else if ($fromform = $mform->get_data()) {

    foreach($fromform as $k => $value)
    {
        if(is_numeric($k))
        {
            $check = $DB->get_record_sql("SELECT * FROM ".$CFG->prefix."knowledge_node_questions WHERE `question_id` = $k AND knowledge_node = $notion->id");

            if($value == 1)
            {
                if($check == null)
                {
                    $entry = new stdClass;
                    $entry->instance = $domoscio->id;
                    $entry->knowledge_node = $kn;
                    $entry->question_id = $k;
                    $write = $DB->insert_record('knowledge_node_questions', $entry);
                }
            }
            elseif($value == 0)
            {
                if(!empty($check))
                {
                    $DB->delete_records('knowledge_node_questions', array('question_id' => $k, 'knowledge_node' => $notion->id));
                }
            }
        }
    }

    echo "La liste des questions est mise à jour.<hr/>";
    echo html_writer::tag('button', 'Continue', array('type' => 'button','onclick'=>"javascript:location.href='$CFG->wwwroot/mod/domoscio/view.php?id=$cm->id'"));

} else {

$mform->display();

}
echo $OUTPUT->footer();
