<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'CR TYPO3',
    'description' => 'CR TYPO3',
    'category' => 'misc',
    'author' => '',
    'author_email' => '',
    'state' => 'alpha',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];