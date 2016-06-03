<?php

$dot_env = new \Dotenv\Dotenv(__DIR__ .'/../..');
$dot_env->load();
$dot_env->required('CLIMAGE_DIR_RESIZED')->notEmpty();
