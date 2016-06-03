#!/usr/bin/php
<?php

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

$loader = require_once __DIR__ . '/../../vendor/autoload.php';
$command_name = getenv('PROJECT_NAME') . ": Uploader Bot";

$command = empty($argv[1]) ? null : $argv[1];
$params = prepare_params(array_slice($argv, 2));

//default value of params
$params += [
    '-param' => [], //unnamed params
    '-n' => null,
];
$wrong_params = false;
//check params -n 
if ($params['-n']) {
    $params['-n'] = current($params['-n']);//ignore duplicate
    if (!is_numeric($params['-n']) || $params['-n'] < 1) {
        $wrong_params = true;
        echo "$command_name\nUsage:\n"
            . "\twrong optional param: [-n count] must be >= 1\n";
    } else {
        $params['-n'] = (int) $params['-n'];
    }
}

if (!$wrong_params) {
    switch ($command) {
        case 'schedule':
            if (empty($params['-param'])) {
                echo "$command_name\nUsage:\n"
                    . "\t{$argv[0]} schedule path1 [path2 ...] [-copy] [-uniq]\n";
            } else {
                $queue = (new Climage\Proc\Queue());
                if (isset($params['-copy'])) {
                    $queue->setCopyFirst(true);
                }
                if (isset($params['-uniq'])) {
                    $queue->setUniqName(true);
                }
                $result = $queue->schedule($params['-param']);
                echo "$command_name\nadd to schedule: {$result['count']['add']}\n" ;
                if (!empty($result['errors'])) {
                    echo 'error: ' . var_export($result['errors'], true);
                }
            }
            break;
        case 'resize':
            (new Climage\Proc\Queue())->run(
                Climage\Proc\Queue::QUEUE_RESIZE, 
                $params['-n']
            );
            break;
        case 'upload':
            (new Climage\Proc\Queue())->run(
                Climage\Proc\Queue::QUEUE_UPLOAD, 
                $params['-n']
            );
            break;
        case 'retry':
            (new Climage\Proc\Queue())->run(
                Climage\Proc\Queue::QUEUE_FAILED, 
                $params['-n']
            );
            break;
        case 'status':
            $status = (new Climage\Proc\Queue())->status();
            foreach ($status as $channel=>$value) {
                echo "$channel:$value\n";
            }
            break;
        default:
            echo "$command_name\nUsage:\n"
                . "\t{$argv[0]} command [arguments]\n"
                . "Available commands:\n"
                . "\tstatus\t\tOutput current status in format %queue%:%number_of_images%\n"
                . "\tschedule\tAdd filenames to resize queue\n"
                . "\tresize\t\tResize next images from the queue\n"
                . "\tupload\t\tUpload next images to remote storage\n"
                . "\tretry\t\tRetry failed operations\n";

    }
}
echo "\n";

function prepare_params($params) {
    $param_last = null;
    $param_prepared = [];
    foreach ($params as $idx => $param) {
        if ($param_last != '-param' && preg_match('~^\-[a-z]~iu', $param)) {
            $param_prepared[$param] = [];
            $param_last = $param;
        } else {
            $param_last = is_null($param_last)
                ? '-param'
                : $param_last;
            $param_prepared[$param_last][] = $param;
            $param_last = null;
        }
    }

    return $param_prepared;
}