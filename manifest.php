<?php
$manifest = array (
    'acceptable_sugar_versions' => array (),
    'acceptable_sugar_flavors' => array(),
    'readme'=>'',
    'key'=>'ToughLove',
    'author' => 'Akshay Joshi',
    'description' => 'Checks to see if you\'re upgrade ready for Sugar 7.',
    'icon' => '',
    'is_uninstallable' => true,
    'name' => 'Tough Love',
    'published_date' => '2012-01-20 10:05:57',
    'type' => 'module',
    'version' => '1.0',
);
$installdefs = array(
    'id' => 'Tough Love',
    'copy' => array (
        array (
            'from' => '<basepath>/upgrade_check_tool',
            'to' => 'modules/upgrade_check_tool',
        ),
    ),
);
