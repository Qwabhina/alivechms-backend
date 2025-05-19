<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ORM.php';
require_once __DIR__ . '/Helpers.php';

class ExpenseCategory
{
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