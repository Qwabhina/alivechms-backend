<?php

/**
 * Expense Category Management
 *
 * Full CRUD operations for expense categories with uniqueness,
 * usage protection, and audit trail.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class ExpenseCategory
{
    /**
     * Create a new expense category
     *
     * @param array $data Category payload
     * @return array ['status' => 'success', 'category_id' => int]
     * @throws Exception On validation or database failure
     */
    public static function create(array $data): array
    {
        $orm = new ORM();

        Helpers::validateInput($data, [
            'name' => 'required|max:50'
        ]);

        $name = trim($data['name']);

        if (!empty($orm->getWhere('expense_category', ['CategoryName' => $name]))) {
            Helpers::sendFeedback('Category name already exists', 400);
        }

        $categoryId = $orm->insert('expense_category', [
            'CategoryName' => $name
        ])['id'];

        return ['status' => 'success', 'category_id' => $categoryId];
    }

    /**
     * Update an existing expense category
     *
     * @param int   $categoryId Category ID
     * @param array $data       Updated data
     * @return array ['status' => 'success', 'category_id' => int]
     */
    public static function update(int $categoryId, array $data): array
    {
        $orm = new ORM();

        $category = $orm->getWhere('expense_category', ['ExpenseCategoryID' => $categoryId]);
        if (empty($category)) {
            Helpers::sendFeedback('Category not found', 404);
        }

        if (empty($data['name'])) {
            return ['status' => 'success', 'category_id' => $categoryId];
        }

        $name = trim($data['name']);
        Helpers::validateInput(['name' => $name], ['name' => 'required|max:50']);

        if (!empty($orm->getWhere('expense_category', [
            'CategoryName'         => $name,
            'ExpenseCategoryID <>' => $categoryId
        ]))) {
            Helpers::sendFeedback('Category name already exists', 400);
        }

        $orm->update('expense_category', ['CategoryName' => $name], ['ExpenseCategoryID' => $categoryId]);
        return ['status' => 'success', 'category_id' => $categoryId];
    }

    /**
     * Delete an expense category (only if unused)
     *
     * @param int $categoryId Category ID
     * @return array ['status' => 'success']
     */
    public static function delete(int $categoryId): array
    {
        $orm = new ORM();

        $category = $orm->getWhere('expense_category', ['ExpenseCategoryID' => $categoryId]);
        if (empty($category)) {
            Helpers::sendFeedback('Category not found', 404);
        }

        if (!empty($orm->getWhere('expense', ['ExpenseCategoryID' => $categoryId]))) {
            Helpers::sendFeedback('Cannot delete category used in expenses', 400);
        }

        $orm->delete('expense_category', ['ExpenseCategoryID' => $categoryId]);
        return ['status' => 'success'];
    }

    /**
     * Retrieve a single expense category
     *
     * @param int $categoryId Category ID
     * @return array Category data
     */
    public static function get(int $categoryId): array
    {
        $orm = new ORM();

        $category = $orm->getWhere('expense_category', ['ExpenseCategoryID' => $categoryId]);
        if (empty($category)) {
            Helpers::sendFeedback('Category not found', 404);
        }

        return $category[0];
    }

    /**
     * Retrieve all expense categories
     *
     * @return array ['data' => array]
     */
    public static function getAll(): array
    {
        $orm = new ORM();
        $categories = $orm->getAll('expense_category');

        return ['data' => $categories];
    }
}