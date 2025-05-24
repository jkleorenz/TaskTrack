<?php
/**
 * CSRF (Cross-Site Request Forgery) Protection System
 * 
 * This file contains functions to protect our forms and actions from CSRF attacks.
 * CSRF attacks happen when a malicious website tricks your browser into making
 * requests to our website using your active session.
 */

// Start a session if one hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Creates or retrieves a CSRF token
 * 
 * This function:
 * 1. Checks if a token exists in the session
 * 2. If no token exists, creates a new one using a secure random number generator
 * 3. Returns the token
 * 
 * @return string The CSRF token
 */
function generateCSRFToken() {
    // Create a new token if one doesn't exist
    if (empty($_SESSION['csrf_token'])) {
        // Generate 32 bytes of random data and convert it to hexadecimal
        // This creates a secure, random token that's hard to guess
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a submitted CSRF token
 * 
 * This function:
 * 1. Checks if both the session token and submitted token exist
 * 2. Compares the tokens using a secure comparison function
 * 3. Returns true if they match, false otherwise
 * 
 * @param string $token The token to validate
 * @return boolean True if token is valid, false otherwise
 */
function validateCSRFToken($token) {
    // First, check if both tokens exist
    if (!isset($_SESSION['csrf_token']) || !isset($token)) {
        return false;
    }
    
    // Compare the tokens using a secure comparison function
    // hash_equals prevents timing attacks by taking the same amount of time
    // regardless of how much of the string matches
    if (hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    
    return false;
}

/**
 * Creates an HTML input field containing the CSRF token
 * 
 * This function:
 * 1. Gets a token using generateCSRFToken()
 * 2. Creates a hidden input field with the token
 * 3. Uses htmlspecialchars to prevent XSS attacks
 * 
 * @return string HTML input field with the CSRF token
 */
function getCSRFTokenField() {
    $token = generateCSRFToken();
    // Create a hidden form field with the token
    // htmlspecialchars prevents XSS attacks by encoding special characters
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Generates a URL-safe CSRF token parameter
 * 
 * This function:
 * 1. Gets a token using generateCSRFToken()
 * 2. URL encodes it for safe use in links
 * 
 * @return string URL-encoded CSRF token parameter
 */
function getCSRFTokenParameter() {
    return 'csrf_token=' . urlencode(generateCSRFToken());
}
?> 