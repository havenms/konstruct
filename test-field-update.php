<?php
/**
 * Test script to verify field name updates are working correctly
 * This simulates the flow of updating a field name in a form
 */

// Simulate WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

// Mock WordPress functions for testing
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags($str)); }
}
if (!function_exists('sanitize_title')) {
    function sanitize_title($str) { return strtolower(str_replace(' ', '-', trim($str))); }
}
if (!function_exists('current_time')) {
    function current_time($type) { return date('Y-m-d H:i:s'); }
}

echo "=== Field Name Update Test ===\n\n";

// Test 1: Verify JSON encoding/decoding preserves field names
echo "Test 1: JSON encoding/decoding\n";
$test_form_config = array(
    'name' => 'Test Form',
    'pages' => array(
        array(
            'pageNumber' => 1,
            'fields' => array(
                array(
                    'id' => 'field_1',
                    'type' => 'text',
                    'label' => 'Email',
                    'name' => 'original_name',
                    'placeholder' => 'Enter email',
                    'required' => true
                )
            )
        )
    )
);

echo "Original field name: " . $test_form_config['pages'][0]['fields'][0]['name'] . "\n";

// Simulate saving to database (JSON encoding)
$json_config = json_encode($test_form_config);
echo "JSON encoded length: " . strlen($json_config) . "\n";

// Simulate loading from database (JSON decoding)
$decoded_config = json_decode($json_config, true);
echo "Decoded field name: " . $decoded_config['pages'][0]['fields'][0]['name'] . "\n";

// Simulate updating the field name
$decoded_config['pages'][0]['fields'][0]['name'] = 'updated_name';
echo "Updated field name: " . $decoded_config['pages'][0]['fields'][0]['name'] . "\n";

// Simulate saving again
$json_config_2 = json_encode($decoded_config);
$decoded_config_2 = json_decode($json_config_2, true);
echo "Field name after re-save: " . $decoded_config_2['pages'][0]['fields'][0]['name'] . "\n";

if ($decoded_config_2['pages'][0]['fields'][0]['name'] === 'updated_name') {
    echo "✓ Test 1 PASSED: Field name persists through encoding/decoding\n\n";
} else {
    echo "✗ Test 1 FAILED: Field name was not preserved\n\n";
}

// Test 2: Verify sanitization doesn't clear field names
echo "Test 2: Sanitization check\n";
$field = array(
    'label' => 'Test Label',
    'name' => 'test_field_name',
    'placeholder' => 'Test placeholder',
    'required' => true,
    'options' => array()
);

// Simulate the sanitization in builder.js lines 1576-1584
$field['label'] = $field['label'] ?: '';
$field['name'] = $field['name'] ?: '';  // This should NOT clear existing value
$field['placeholder'] = $field['placeholder'] ?: '';
$field['required'] = (bool)$field['required'];
$field['options'] = is_array($field['options']) ? $field['options'] : array();

echo "Field name after sanitization: " . $field['name'] . "\n";
if ($field['name'] === 'test_field_name') {
    echo "✓ Test 2 PASSED: Sanitization preserves field name\n\n";
} else {
    echo "✗ Test 2 FAILED: Sanitization cleared field name\n\n";
}

// Test 3: Empty field name handling
echo "Test 3: Empty field name handling\n";
$field_empty = array(
    'label' => 'Test Label',
    'name' => '',  // Empty name
    'type' => 'text'
);

echo "Empty field name is: '" . $field_empty['name'] . "'\n";
$field_empty['name'] = $field_empty['name'] ?: '';
echo "After sanitization: '" . $field_empty['name'] . "'\n";
if ($field_empty['name'] === '') {
    echo "✓ Test 3 PASSED: Empty names stay empty\n\n";
} else {
    echo "✗ Test 3 FAILED: Unexpected value\n\n";
}

echo "=== All Basic Tests Complete ===\n";
echo "\nConclusion: If all tests pass, the issue is likely:\n";
echo "1. Database-level caching (MySQL query cache, WordPress object cache)\n";
echo "2. Frontend not properly updating formData object reference\n";
echo "3. Browser/server caching preventing updates from being seen\n";
echo "4. Database update returning 0 (no rows changed) when data is identical\n";
