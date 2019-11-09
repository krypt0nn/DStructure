<?php

require 'DStructure.php';

use DStructure\{
    Structure,
    Item
};

$dbs = new Structure;

if (($item = $dbs->get ('test')) !== null)
    print_r ($item);

else $dbs->set ('test', new Item ([
    'test' => 'Hello, World!'
]));

$dbs->save ();
