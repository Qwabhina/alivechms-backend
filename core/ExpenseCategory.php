<?php

/** Expense Category Management Class
 * Handles creation, updating, deletion, and retrieval of expense categories
 * Validates input data and checks for uniqueness of category names
 * Implements error handling and transaction management
 * @package ExpenseCategory
 */
class ExpenseCategory
{
    /**
     * Create a new expense category
     * @param array $data The category data containing 'name'
     * @return array The created category ID and status
     * @throws Exception if validation fails or database errors occur
     */
    public static function create($data)
    {
        $orm = new ORM();
        try {
            Helpers::validateInput($data, [
                'name' => 'required'
            ]);

            // Validate name length and uniqueness
            if (strlen($data['name']) > 50) {
                throw new Exception('Category name must not exceed 50 characters');
            }
            $existing = $orm->getWhere('expensecategory', ['ExpCategoryName' => $data['name']]);
            if (!empty($existing)) {
                throw new Exception('Category name already exists');
            }

            $orm->beginTransaction();
            $categoryId = $orm->insert('expensecategory', [
                'ExpCategoryName' => $data['name']
            ])['id'];
            $orm->commit();

            return ['status' => 'success', 'category_id' => $categoryId];
        } catch (Exception $e) {
            $orm->rollBack();
            Helpers::logError('Expense category create error: ' . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Update an existing expense category
     * @param int $categoryId The ID of the category to update
     * @param array $data The new category data containing 'name'
     * @return array The updated category ID and status
     * @throws Exception if validation fails or database errors occur
     */
    public static function update($categoryId, $data)
    {
        $orm = new ORM();
        try {
            Helpers::validateInput($data, [
                'name' => 'required'
            ]);

            // Validate name length and uniqueness
            if (strlen($data['name']) > 50) {
                throw new Exception('Category name must not exceed 50 characters');
            }
            $existing = $orm->getWhere('expensecategory', ['ExpCategoryName' => $data['name']]);
            if (!empty($existing) && $existing[0]['ExpCategoryID'] != $categoryId) {
                throw new Exception('Category name already exists');
            }

            // Validate category exists
            $category = $orm->getWhere('expensecategory', ['ExpCategoryID' => $categoryId]);
            if (empty($category)) {
                throw new Exception('Category not found');
            }

            $orm->beginTransaction();
            $orm->update('expensecategory', [
                'ExpCategoryName' => $data['name']
            ], ['ExpCategoryID' => $categoryId]);
            $orm->commit();

            return ['status' => 'success', 'category_id' => $categoryId];
        } catch (Exception $e) {
            $orm->rollBack();
            Helpers::logError('Expense category update error: ' . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Delete an expense category
     * @param int $categoryId The ID of the category to delete
     * @return array Status of the deletion
     * @throws Exception if the category is used in expenses or does not exist
     */
    public static function delete($categoryId)
    {
        $orm = new ORM();
        try {
            // Validate category exists
            $category = $orm->getWhere('expensecategory', ['ExpCategoryID' => $categoryId]);
            if (empty($category)) {
                throw new Exception('Category not found');
            }

            // Check if category is used in expenses
            $used = $orm->getWhere('expense', ['ExpCategoryID' => $categoryId]);
            if (!empty($used)) {
                throw new Exception('Cannot delete category used in expenses');
            }

            $orm->beginTransaction();
            $orm->delete('expensecategory', ['ExpCategoryID' => $categoryId]);
            $orm->commit();

            return ['status' => 'success'];
        } catch (Exception $e) {
            $orm->rollBack();
            Helpers::logError('Expense category delete error: ' . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Get an expense category by ID
     * @param int $categoryId The ID of the category to retrieve
     * @return array The category data
     * @throws Exception if the category does not exist
     */
    public static function get($categoryId)
    {
        $orm = new ORM();
        try {
            $category = $orm->getWhere('expensecategory', ['ExpCategoryID' => $categoryId])[0] ?? null;
            if (!$category) {
                throw new Exception('Category not found');
            }
            return $category;
        } catch (Exception $e) {
            Helpers::logError('Expense category get error: ' . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Get all expense categories
     * @return array List of all categories
     * @throws Exception if there is an error retrieving categories
     */
    public static function getAll()
    {
        $orm = new ORM();
        try {
            $categories = $orm->getAll('expensecategory');
            return ['data' => $categories];
        } catch (Exception $e) {
            Helpers::logError('Expense category getAll error: ' . $e->getMessage());
            throw $e;
        }
    }
}
?>