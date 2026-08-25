<?php
$readme = file_get_contents('README.md');

$old_text = "This README serves as the definitive syllabus and technical reference for the framework's design.";
$new_text = "This README serves as the technical reference for the framework's features. For the definitive, 15-module step-by-step Masterclass on how this architecture is constructed, please refer to the `textbook.md` file (or view the `/syllabus` route in the browser).";

$readme = str_replace($old_text, $new_text, $readme);

file_put_contents('README.md', $readme);
echo "README patched.\n";
