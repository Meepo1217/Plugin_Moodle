<?php
defined('MOODLE_INTERNAL') || die();

/** @var array $string */
$string['pluginname'] = 'Anti-Screenshot Protection (Chống chụp màn hình)';
$string['antishot_header'] = 'Anti-Screenshot Protection';

// Form Settings & Help Buttons
$string['enable_antishot'] = 'Enable anti-screenshot protection';
$string['enable_antishot_help'] = 'Blocks PrintScreen key, disables printing/context menu, and blurs the screen immediately when screen snipping tools (Win+Shift+S) or window switching are detected.';

$string['enable_watermark'] = 'Display user identification watermark';
$string['enable_watermark_help'] = 'Overlays a dynamic semi-transparent watermark (Full name, Username, IP address, Timestamp) across the screen to trace unauthorized photos.';

$string['maxviolations'] = 'Maximum allowed violations (n times)';
$string['maxviolations_help'] = 'Select the maximum number of times a student is allowed to violate before being locked out. Choose "Unlimited" if you only want to show warnings and log activities.';
$string['unlimited'] = 'Unlimited (Log & blur only)';
$string['violation_times'] = '{$a} times';

$string['lockscope'] = 'Action when violation limit is exceeded';
$string['lockscope_help'] = 'Choose between locking only the current activity or locking access to the entire course until unlocked by a teacher.';
$string['lockscope_cm'] = '1. Lock only this activity / lesson';
$string['lockscope_all'] = '2. Lock entire course (Block access completely until unlocked)';

// Capabilities
$string['antishot:manage'] = 'Manage anti-screenshot settings and unlock users';
$string['antishot:bypass'] = 'Bypass anti-screenshot screen blurring and locks';

// Client-side JavaScript Warnings
$string['warning_title'] = '⚠️ Content is temporarily hidden for security protection.';
$string['warning_subtitle'] = 'Click anywhere on the screen to resume your activity.';
$string['clipboard_blocked'] = 'Screenshots are disabled for this activity.';