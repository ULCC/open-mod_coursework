<?php
require_once("../../../config.php");

global $SESSION;


$assesorselect              =   required_param_array('assesorselect',PARAM_RAW);
$assesorselectvalue         =   required_param_array('assesorselectvalue',PARAM_RAW);
$pinnedchk             =   optional_param_array('pinned',array(),PARAM_RAW);
$pinnedchkval             =   optional_param_array('pinnedvalue',array(),PARAM_RAW);
$moderatorselect              =   optional_param_array('moderatorselect',array(),PARAM_RAW);
$moderatorselectvalue         =   optional_param_array('moderatorselectvalue',array(),PARAM_RAW);
$samplechk             =   optional_param_array('sample',array(),PARAM_RAW);
$samplechkvalue         =   optional_param_array('samplevalue',array(),PARAM_RAW);
$coursemoduleid             =   required_param('coursemoduleid', PARAM_INT);

if (!isset($SESSION->coursework_allocationsessions))    {
    $SESSION->coursework_allocationsessions =   array();
}

if (!isset($SESSION->coursework_allocationsessions[$coursemoduleid]))   {
    $SESSION->coursework_allocationsessions[$coursemoduleid]    =   array();
}



for($i = 0; $i < count($assesorselect); $i++) {
    $SESSION->coursework_allocationsessions[$coursemoduleid][$assesorselect[$i]]    =   $assesorselectvalue[$i];
}

for($i = 0; $i < count($pinnedchk); $i++) {
    $SESSION->coursework_allocationsessions[$coursemoduleid][$pinnedchk[$i]]  =   $pinnedchkval[$i];
}

for($i = 0; $i < count($moderatorselect); $i++) {
    $SESSION->coursework_allocationsessions[$coursemoduleid][$moderatorselect[$i]]    =   $moderatorselectvalue[$i];
}

for($i = 0; $i < count($samplechk); $i++) {
    $SESSION->coursework_allocationsessions[$coursemoduleid][$samplechk[$i]]  =   $samplechkvalue[$i];
}

