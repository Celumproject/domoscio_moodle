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
 * This view displays student's results according to the Domoscio's API datas
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace domoscio with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(dirname(dirname(__FILE__))).'/calendar/lib.php');
require_once(dirname(__FILE__).'/classes/create_celltest_form.php');
require_once(dirname(__FILE__).'/lib.php');

$config = get_config('domoscio');
$PAGE->set_context(context_system::instance());
require_login();

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$q = optional_param('q', 0, PARAM_INT); // Course_module ID, or
$kn = optional_param('kn', 0, PARAM_INT);
$scorm = optional_param('scorm', '', PARAM_INT);
$end = optional_param('end', false, PARAM_INT);

if ($id) {
    $cm         = $DB->get_record('course_modules', array('id' => $id), '*', MUST_EXIST);
    $course     = get_course($cm->course);
    $domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($kn) {
    $module     = $DB->get_record('modules', array('name' => 'domoscio'), '*', MUST_EXIST);
    $instance   = $DB->get_record('knowledge_nodes', array('knowledge_node_id' => $kn), '*', MUST_EXIST);
    $domoscio   = $DB->get_record('domoscio', array('id' => $instance->instance), '*', MUST_EXIST);
    $course     = get_course($domoscio->course);
    $cm         = $DB->get_record('course_modules', array('instance' => $domoscio->id, 'module' => $module->id), '*', MUST_EXIST);
    $id         = $cm->id;
}

$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/index.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_heading(get_string('pluginname', 'domoscio'));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('results', 'domoscio'));

$rest = new mod_domoscio_client();

if ($q) {
    $selected = $DB->get_record('knowledge_node_questions', array('question_id' => $q, 'knowledge_node' => $kn), '*');

    $question = $DB->get_record_sql("SELECT *
                                       FROM {question}
                                      WHERE `id` = $q"
                                   );

    $qtype = $question->qtype;

    if ($qtype == "calculated" || $qtype == "numerical" || $qtype == "shortanswer") {
        $result = domoscio_get_input_result($question, $_POST, $domoscio->resource_type);
    } else if ($qtype == "multichoice" || $qtype == "calculatedmulti" || $qtype == "truefalse") {
        $result = domoscio_get_multi_choice_result($question, $_POST, $selected->type);
    } else if ($qtype == "multianswer") {
        $result = domoscio_get_multi_result($question, $_POST, $domoscio->resource_type);
    } else if ($qtype == "match") {
        $result = domoscio_get_match_result($question, $_POST, $domoscio->resource_type);
    }

    $qspan = html_writer::start_span('qno') . $question->id . html_writer::end_span();
    $qheader = html_writer::tag('h3', get_string('question', 'domoscio').$qspan, array('class' => 'no'));

    $qcontent = html_writer::tag('div', $result->output, array('class' => 'formulation'));

    $output = html_writer::tag('div', $qheader, array('class' => 'info'));
    $output .= html_writer::tag('div', $qcontent, array('class' => 'content'));
    $output = html_writer::tag('div', $output, array('class' => 'que '.$qtype.' deferredfeedback notyetanswered'));

    echo $output;
} else if ($scorm) {
    $redirect = optional_param('redirect', true, PARAM_BOOL);

    if ($redirect == true) {
        if (isset($_POST['scoid'])) {
            $_SESSION['scoid'] = $_POST['scoid'];
        }
        redirect("$CFG->wwwroot/mod/domoscio/results.php?id=$id&scorm=$scorm&kn=$kn&redirect=false");
        exit;
    } else {
        $score = $DB->get_record_sql("SELECT *
                                        FROM {scorm_scoes_track}
                                       WHERE `userid` = $USER->id
                                         AND `scormid` = $scorm
                                         AND `scoid` = ".$_SESSION['scoid']."
                                         AND `element` = 'cmi.score.scaled'
                                         AND (`attempt` = (SELECT MAX(`attempt`)
                                                             FROM {scorm_scoes_track}
                                                            WHERE `userid` = $USER->id
                                                              AND `scormid` = $scorm
                                                              AND `scoid` = ".$_SESSION['scoid']."))");
        if ($score) {
            $result = new stdClass;
            $result->score = $score->value * 100;
            unset($_SESSION['scoid']);
        }
    }
} else if ($end = true) {
    if (isset($_SESSION['start'])) {
        $finish = time();
        $runningtime = $finish - $_SESSION['start'];

        echo html_writer::tag('div',
                              html_writer::tag('h5',
                                               get_string('running_time', 'domoscio').date('i:s', $runningtime),
                                               array('class' => 'mod_introbox')),
                              array('class' => 'block'));

        unset($_SESSION['start']);

        foreach ($_SESSION['results'] as $rapport) {
            $kns = $DB->get_record('knowledge_node_students', array('kn_student_id' => $rapport->knowledge_node_student_id), '*');

            $resource = domoscio_get_resource_info($kns->knowledge_node_id);

            $kninfo = json_decode($rest->seturl($config, 'knowledge_nodes', $kns->knowledge_node_id)->get());

            if ($rapport->value == 100) {
                $state = get_string('notion_ok', 'domoscio');
                $class = "alert-success";
                $feedbackclass = "correct";
            } else {
                $state = html_writer::link($resource->url, "<i class='icon-book'></i>".get_string('notion_rvw', 'domoscio'), array('target' => '_blank'));
                $class = "alert-danger";
                $feedbackclass = "incorrect";
            }

            $attributes = array(
                'src' => $OUTPUT->pix_url('i/grade_' . $feedbackclass),
                'alt' => get_string($feedbackclass, 'question'),
                'class' => 'questioncorrectnessicon',
            );

            echo html_writer::tag('div', html_writer::empty_tag('img', $attributes).
                                         html_writer::tag('span', $resource->display." - ".$kninfo->name, array('class' => 'mod_introbox')).
                                         html_writer::tag('span', $rapport->value." ".$state, array('class' => 'pull-right')), array('class' => 'que '.$class));

        }

        unset($_SESSION['results']);
        unset($_SESSION['todo']);
    }

    echo html_writer::tag('button', get_string('home_btn', 'domoscio'), array('type' => 'button',
                                                                           'onclick' => "javascript:location.href='$CFG->wwwroot/'"));
}

if ($q || $scorm) {
    // Write json, send it and retrive result from API
    $knstudent = $DB->get_record('knowledge_node_students', array('user' => $USER->id,
                                                      'knowledge_node_id' => $kn), '*');
    $json = json_encode(array('knowledge_node_student_id' => intval($knstudent->kn_student_id),
                                                  'value' => intval($result->score)));

    $_SESSION['results'][] = json_decode($rest->seturl($config, 'results', null)->post($json));

    // Put a new event in calendar
    $knstudent = json_decode($rest->seturl($config, 'knowledge_node_students', $knstudent->kn_student_id)->get());

    $newevent = domoscio_create_event($domoscio, $course, $knstudent);
}

if (!empty($_SESSION['todo'])) {
    echo html_writer::tag('button',
                          get_string('next_btn', 'domoscio'),
                          array('type' => 'button',
                              'onclick' => "javascript:location.href='$CFG->wwwroot/mod/domoscio/doquiz.php?kn=".array_shift($_SESSION['todo'])."'"));
} else if (empty($_SESSION['todo']) && $end == false) {
    echo html_writer::tag('button',
                          get_string('end_btn', 'domoscio'),
                          array('type' => 'button',
                              'onclick' => "javascript:location.href='$CFG->wwwroot/mod/domoscio/results.php?end=true'"));
}

echo $OUTPUT->footer();
