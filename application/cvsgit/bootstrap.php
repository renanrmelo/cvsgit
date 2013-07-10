<?php
require_once __DIR__ . '/../../system/bootstrap.php';
require_once APPLICATION_DIR . 'cvsgit/CVSApplication.php';
require_once APPLICATION_DIR . 'cvsgit/CVSApplication.php';
require_once APPLICATION_DIR . 'cvsgit/command/AddCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/InitCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/PullCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/PushCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/RemoveCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/StatusCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/DiffCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/LogCommand.php';

use CVS\CVSApplication as CVS;

$cvs = new CVS();
$cvs->add( new \CVS\AddCommand() );
$cvs->add( new \CVS\InitCommand() );
$cvs->add( new \CVS\PushCommand() );
$cvs->add( new \CVS\RemoveCommand() );
$cvs->add( new \CVS\StatusCommand() );
$cvs->add( new \CVS\PullCommand() );
$cvs->add( new \CVS\DiffCommand() );
$cvs->add( new \CVS\LogCommand() );
$cvs->run();
