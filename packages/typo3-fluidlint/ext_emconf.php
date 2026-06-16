<?php

$_EXTKEY = 'fluidlint';

$EM_CONF[$_EXTKEY] = [
    'title' => 'Fluidlint',
    'description' => 'Linting, cyclomatic complexity and dead-code analysis for Fluid templates',
    'category' => 'misc',
    'author' => 'CRU',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
        ],
    ],
];
