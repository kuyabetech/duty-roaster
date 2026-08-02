<?php
// =============================================
// Validator Class
// =============================================

class Validator {
    /**
     * Validate password strength
     */
    public static function password($password) {
        if (strlen($password) < PASSWORD_MIN_LENGTH) return false;
        if (PASSWORD_REQUIREMENTS['uppercase'] && !preg_match('/[A-Z]/', $password)) return false;
        if (PASSWORD_REQUIREMENTS['lowercase'] && !preg_match('/[a-z]/', $password)) return false;
        if (PASSWORD_REQUIREMENTS['numbers'] && !preg_match('/[0-9]/', $password)) return false;
        if (PASSWORD_REQUIREMENTS['special'] && !preg_match('/[^a-zA-Z0-9]/', $password)) return false;
        return true;
    }
}