<?php

/*
 * Sidebar menu for each role. Labels are translated with __() in the layout.
 * Only pages listed here appear in the menu; the routes also check the role,
 * so typing a URL by hand cannot open a page that is not allowed.
 */

return [

    'admin' => [
        'Overview' => [
            ['route' => 'dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
        ],
        'Attendance' => [
            ['route' => 'attendance.take', 'icon' => 'clipboard-check', 'label' => 'Take Attendance'],
            ['route' => 'attendance.monitoring', 'icon' => 'list-checks', 'label' => 'Attendance Monitoring'],
            ['route' => 'attendance.corrections', 'icon' => 'file-pen-line', 'label' => 'Corrections & Audit Log'],
        ],
        'Analytics' => [
            ['route' => 'insights', 'icon' => 'lightbulb', 'label' => 'Insights'],
            ['route' => 'at-risk', 'icon' => 'triangle-alert', 'label' => 'At-Risk Students'],
            ['route' => 'interventions', 'icon' => 'hand-heart', 'label' => 'Interventions'],
            ['route' => 'reports', 'icon' => 'chart-column', 'label' => 'Reports'],
        ],
        'School data' => [
            ['route' => 'students.index', 'icon' => 'users', 'label' => 'Students'],
            ['route' => 'classes.index', 'icon' => 'school', 'label' => 'Classes'],
            ['route' => 'subjects.index', 'icon' => 'book-open', 'label' => 'Subjects'],
            ['route' => 'teachers.index', 'icon' => 'graduation-cap', 'label' => 'Teachers'],
            ['route' => 'parents.index', 'icon' => 'contact', 'label' => 'Parents'],
            ['route' => 'assignments.index', 'icon' => 'calendar-check', 'label' => 'Teaching Assignments'],
        ],
        'Account' => [
            ['route' => 'profile', 'icon' => 'circle-user', 'label' => 'Profile'],
        ],
    ],

    'teacher' => [
        'Overview' => [
            ['route' => 'teacher.dashboard', 'icon' => 'layout-dashboard', 'label' => 'My Dashboard'],
        ],
        'Attendance' => [
            ['route' => 'attendance.take', 'icon' => 'clipboard-check', 'label' => 'Take Attendance'],
            ['route' => 'attendance.monitoring', 'icon' => 'history', 'label' => 'Attendance History'],
        ],
        'My teaching' => [
            ['route' => 'teacher.classes', 'icon' => 'school', 'label' => 'My Classes'],
        ],
        'Account' => [
            ['route' => 'profile', 'icon' => 'circle-user', 'label' => 'Profile'],
        ],
    ],

];
