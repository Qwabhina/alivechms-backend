<?php

/**
 * Validation Service
 *
 * Enhanced validation with:
 * - Reusable rule sets
 * - Custom validators
 * - Better error messages
 * - Conditional validation
 * - Array validation
 *
 * @package  AliveChMS\Core
 * @version  2.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-December
 */

declare(strict_types=1);

class Validator
{
   private array $data = [];
   private array $rules = [];
   private array $errors = [];
   private array $customMessages = [];
   private static array $rulesets = [];

   /**
    * Create new validator instance
    * 
    * @param array $data Data to validate
    * @param array $rules Validation rules
    * @param array $customMessages Custom error messages
    */
   public function __construct(array $data, array $rules, array $customMessages = [])
   {
      $this->data = $data;
      $this->rules = $rules;
      $this->customMessages = $customMessages;
   }

   /**
    * Static validation method
    * 
    * @param array $data Data to validate
    * @param array $rules Validation rules
    * @param array $customMessages Custom messages
    * @return self
    */
   public static function make(array $data, array $rules, array $customMessages = []): self
   {
      return new self($data, $rules, $customMessages);
   }

   /**
    * Register reusable ruleset
    * 
    * @param string $name Ruleset name
    * @param array $rules Rules
    */
   public static function registerRuleset(string $name, array $rules): void
   {
      self::$rulesets[$name] = $rules;
   }

   /**
    * Get registered ruleset
    * 
    * @param string $name Ruleset name
    * @return array Rules
    */
   public static function getRuleset(string $name): array
   {
      return self::$rulesets[$name] ?? [];
   }

   /**
    * Validate data
    * 
    * @return bool True if valid
    */
   public function validate(): bool
   {
      $this->errors = [];

      foreach ($this->rules as $field => $ruleString) {
         $value = $this->data[$field] ?? null;
         $rules = $this->parseRules($ruleString);

         foreach ($rules as $rule => $param) {
            if (!$this->validateRule($field, $value, $rule, $param)) {
               break; // Stop on first error for this field
            }
         }
      }

      return empty($this->errors);
   }

   /**
    * Check if validation passed
    * 
    * @return bool
    */
   public function passes(): bool
   {
      return $this->validate();
   }

   /**
    * Check if validation failed
    * 
    * @return bool
    */
   public function fails(): bool
   {
      return !$this->validate();
   }

   /**
    * Get validation errors
    * 
    * @return array Errors
    */
   public function errors(): array
   {
      return $this->errors;
   }

   /**
    * Get validated data (only fields with rules)
    * 
    * @return array
    */
   public function validated(): array
   {
      $validated = [];
      foreach (array_keys($this->rules) as $field) {
         if (isset($this->data[$field])) {
            $validated[$field] = $this->data[$field];
         }
      }
      return $validated;
   }

   /**
    * Validate if fails, otherwise return validated data
    * 
    * @throws Exception If validation fails
    * @return array Validated data
    */
   public function validateOrFail(): array
   {
      if ($this->fails()) {
         throw new Exception($this->getErrorMessage());
      }
      return $this->validated();
   }

   /**
    * Parse rule string into array
    * 
    * @param string $ruleString Rule string
    * @return array Parsed rules
    */
   private function parseRules(string $ruleString): array
   {
      $rules = [];
      $parts = explode('|', $ruleString);

      foreach ($parts as $part) {
         if (str_contains($part, ':')) {
            [$rule, $param] = explode(':', $part, 2);
            $rules[$rule] = $param;
         } else {
            $rules[$part] = null;
         }
      }

      return $rules;
   }

   /**
    * Validate single rule
    * 
    * @param string $field Field name
    * @param mixed $value Field value
    * @param string $rule Rule name
    * @param mixed $param Rule parameter
    * @return bool True if valid
    */
   private function validateRule(string $field, $value, string $rule, $param): bool
   {
      // Skip if nullable and value is null
      if ($rule === 'nullable' && $value === null) {
         return true;
      }

      $method = 'validate' . str_replace('_', '', ucwords($rule, '_'));

      if (method_exists($this, $method)) {
         $valid = $this->$method($field, $value, $param);
         if (!$valid) {
            $this->addError($field, $rule, $param);
         }
         return $valid;
      }

      // Unknown rule - skip
      return true;
   }

   /**
    * Add validation error
    * 
    * @param string $field Field name
    * @param string $rule Rule name
    * @param mixed $param Rule parameter
    */
   private function addError(string $field, string $rule, $param): void
   {
      $key = "{$field}.{$rule}";

      if (isset($this->customMessages[$key])) {
         $message = $this->customMessages[$key];
      } else {
         $message = $this->getDefaultMessage($field, $rule, $param);
      }

      if (!isset($this->errors[$field])) {
         $this->errors[$field] = [];
      }

      $this->errors[$field][] = $message;
   }

   /**
    * Get default error message
    * 
    * @param string $field Field name
    * @param string $rule Rule name
    * @param mixed $param Rule parameter
    * @return string Error message
    */
   private function getDefaultMessage(string $field, string $rule, $param): string
   {
      $fieldName = str_replace('_', ' ', $field);

      return match ($rule) {
         'required' => "The {$fieldName} field is required",
         'email' => "The {$fieldName} must be a valid email address",
         'numeric' => "The {$fieldName} must be numeric",
         'integer' => "The {$fieldName} must be an integer",
         'string' => "The {$fieldName} must be a string",
         'max' => "The {$fieldName} must not exceed {$param} characters",
         'min' => "The {$fieldName} must be at least {$param} characters",
         'in' => "The {$fieldName} must be one of: {$param}",
         'unique' => "The {$fieldName} already exists",
         'exists' => "The selected {$fieldName} is invalid",
         default => "The {$fieldName} is invalid"
      };
   }

   /**
    * Get formatted error message
    * 
    * @return string
    */
   private function getErrorMessage(): string
   {
      $messages = [];
      foreach ($this->errors as $field => $errors) {
         $messages = array_merge($messages, $errors);
      }
      return implode('; ', $messages);
   }

   // Validation methods

   private function validateRequired(string $field, $value, $param): bool
   {
      return $value !== null && $value !== '';
   }

   private function validateEmail(string $field, $value, $param): bool
   {
      return $value && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
   }

   private function validateNumeric(string $field, $value, $param): bool
   {
      return $value !== null && is_numeric($value);
   }

   private function validateInteger(string $field, $value, $param): bool
   {
      return $value !== null && filter_var($value, FILTER_VALIDATE_INT) !== false;
   }

   private function validateString(string $field, $value, $param): bool
   {
      return $value === null || is_string($value);
   }

   private function validateMax(string $field, $value, $param): bool
   {
      if ($value === null) return true;

      if (is_string($value)) {
         return strlen($value) <= (int)$param;
      }

      if (is_numeric($value)) {
         return $value <= (int)$param;
      }

      return true;
   }

   private function validateMin(string $field, $value, $param): bool
   {
      if ($value === null) return true;

      if (is_string($value)) {
         return strlen($value) >= (int)$param;
      }

      if (is_numeric($value)) {
         return $value >= (int)$param;
      }

      return true;
   }

   private function validateIn(string $field, $value, $param): bool
   {
      $allowed = explode(',', $param);
      return $value && in_array($value, $allowed, true);
   }

   private function validateUnique(string $field, $value, $param): bool
   {
      if (!$param || !str_contains($param, ',')) {
         return true;
      }

      [$table, $column] = explode(',', $param);
      $orm = new ORM();
      return empty($orm->getWhere($table, [$column => $value]));
   }

   private function validateExists(string $field, $value, $param): bool
   {
      if (!$param || !str_contains($param, ',')) {
         return true;
      }

      [$table, $column] = explode(',', $param);
      $orm = new ORM();
      return !empty($orm->getWhere($table, [$column => $value]));
   }

   private function validateDate(string $field, $value, $param): bool
   {
      if (!$value) return true;

      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
         return false;
      }

      $parts = explode('-', $value);
      return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
   }

   private function validatePhone(string $field, $value, $param): bool
   {
      if (!$value) return true;

      $cleaned = preg_replace('/[\s\-\(\)]/', '', $value);
      return (bool)preg_match('/^(\+?233|0)[2-5][0-9]{8}$/', $cleaned);
   }

   private function validateUrl(string $field, $value, $param): bool
   {
      return !$value || filter_var($value, FILTER_VALIDATE_URL) !== false;
   }

   private function validateArray(string $field, $value, $param): bool
   {
      return is_array($value);
   }

   private function validateBoolean(string $field, $value, $param): bool
   {
      return in_array($value, [true, false, 0, 1, '0', '1'], true);
   }
}

// Register common rulesets
Validator::registerRuleset('member_basic', [
   'first_name' => 'required|max:50',
   'family_name' => 'required|max:50',
   'email_address' => 'required|email',
   'gender' => 'in:Male,Female,Other|nullable'
]);

Validator::registerRuleset('financial', [
   'amount' => 'required|numeric|min:0',
   'date' => 'required|date',
   'fiscal_year_id' => 'required|integer|exists:fiscalyear,FiscalYearID'
]);
