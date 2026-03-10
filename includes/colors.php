<?php
/**
 * Returns a hex color for a given department name.
 * Add or adjust mappings here as needed.
 */
function getDeptColor($departmentName) {
    if (empty($departmentName)) return '#334155';
    $name = strtolower(trim($departmentName));
    switch ($name) {
        case 'it department':
        case 'it':
            return '#2563eb'; // blue
        case 'marketing department':
        case 'marketing':
            return '#f59e0b'; // amber
        case 'inventory department':
        case 'inventory':
            return '#10b981'; // green
        case 'human resources':
        case 'hr':
            return '#8b5cf6'; // purple
        default:
            return '#334155'; // default slate
    }
}
