<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_create_sections' => [
        'classname'   => 'local_create_sections_external',
        'methodname'  => 'create_sections',
        'classpath'   => 'local/my_webservices/externallib.php', // Correct path to your file
        'description' => 'Creates one or more sections in a specified course.',
        'type'        => 'write',
        'capabilities'=> 'moodle/course:update', // Ensures the user has the right permission
    ],
    'local_create_label' => [
        'classname'   => 'local_create_sections_external', // We can use the same class
        'methodname'  => 'create_label',
        'classpath'   => 'local/my_webservices/externallib.php',
        'description' => 'Creates a text and media area (label) in a course section.',
        'type'        => 'write',
        'capabilities'=> 'moodle/course:manageactivities', // The required permission to add activities
    ],
    'local_create_empty_media_label' => [
        'classname'   => 'local_create_sections_external', // same class
        'methodname'  => 'create_empty_media_label',        // function name in externallib.php
        'classpath'   => 'local/my_webservices/externallib.php',
        'description' => 'Creates an empty media label in a course section and returns contextid.',
        'type'        => 'write',
        'capabilities'=> 'moodle/course:manageactivities',
    ],
    'local_create_quiz_from_xml' => [
        'classname'   => 'local_create_sections_external',
        'methodname'  => 'create_quiz_from_xml',
        'classpath'   => 'local/my_webservices/externallib.php',
        'description' => 'Creates a quiz from a GIFT file, adds it to a course section, creates a question category and imports questions into the quiz.',
        'type'        => 'write',
        'capabilities'=> 'moodle/course:manageactivities',
    ],
    'local_update_label' => [
        'classname'   => 'local_create_sections_external',  // same class as other functions
        'methodname'  => 'update_label',                    // name of the PHP function
        'classpath'   => 'local/my_webservices/externallib.php', // path to externallib.php
        'description' => 'Updates an existing label in a course using its cmid and new HTML content.',
        'type'        => 'write',
        'capabilities'=> 'moodle/course:manageactivities',  // same capability as create_label
    ],
    'local_update_quiz_from_xml' => [
        'classname'   => 'local_create_sections_external',
        'methodname'  => 'update_quiz_from_xml',
        'classpath'   => 'local/my_webservices/externallib.php',
        'description' => 'Updates a quiz with new questions from an XML file by replacing existing ones.',
        'type'        => 'write',
        'capabilities'=> 'moodle/course:manageactivities',
    ],
    'local_update_media_label' => [
        'classname'   => 'local_create_sections_external',      // Use the same class as your other functions
        'methodname'  => 'update_media_label',                  // The name of the function in externallib.php
        'classpath'   => 'local/my_webservices/externallib.php',// The path to your externallib.php file
        'description' => 'Updates an existing media label by replacing its file and embedding code.',
        'type'        => 'write',                               // It modifies data, so it's a 'write' operation
        'capabilities'=> 'moodle/course:manageactivities',      // The required permission to modify activities
    ],
    'local_fetch_quiz_questions' => [
        'classname'   => 'local_create_sections_external', // We use the same class as your other functions
        'methodname'  => 'fetch_quiz_questions',           // The name of the function you just added
        'classpath'   => 'local/my_webservices/externallib.php',
        'description' => 'Fetch all questions and answers from a quiz using its instance ID.',
        'type'        => 'read',                           // It is a READ operation, not WRITE
        'ajax'        => true,                             // Allow calling via AJAX if needed
        'capabilities'=> 'mod/quiz:view',                  // Permission required to see the quiz
    ],
];