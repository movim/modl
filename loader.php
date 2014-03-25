<?php

// Modl loader

namespace modl;

$base = __DIR__.'/';

require_once($base.'ModlUtils.php');
require_once($base.'ModlLogger.php');
require_once($base.'Modl.php');
require_once($base.'ModlSQL.php');
require_once($base.'ModlSmartDB.php');
require_once($base.'ModlModel.php');

/*
require_once($base.'ModlUtils.php');
require_once($base.'Modl.php');
require_once($base.'ModlDAO.php');

require_once($base.'ModlDatabase.php');

require_once($base.'ModlMySQL.php');

loadNewData('Contact');
loadNewData('Message');
loadNewData('Privacy');
loadNewData('Postn');
loadNewData('Node');
loadNewData('Caps');
loadNewData('Session');
loadNewData('Subscription');
loadNewData('Cache');
loadNewData('Presence');
loadNewData('RosterLink');
*/
