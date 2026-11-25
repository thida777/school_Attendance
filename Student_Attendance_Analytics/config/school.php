<?php

return [
    // Teaching Assignments: a teacher with more lessons than this per week is marked as overloaded
    'teacher_max_hours' => (int) env('TEACHER_MAX_HOURS', 30),
];
