<?php

namespace KBS\PostTypes;

class CustomPostTypes
{
    public static function register_all(): void
    {
        self::register_post_type('invoice', 'Invoice', 'Invoices');
        self::register_post_type('quotation', 'Quotation', 'Quotations');
        self::register_post_type('expense', 'Expense', 'Expenses');
        self::register_post_type('income', 'Income', 'Income Entries');
    }

    /**
     * Register a custom post type with standard labels and options
     *
     * @param string $type Post type key (e.g. 'invoice')
     * @param string $singular Singular label (e.g. 'Invoice')
     * @param string $plural Plural label (e.g. 'Invoices')
     */
    private static function register_post_type(string $type, string $singular, string $plural): void
    {
        $labels = [
            'name' => $plural,
            'singular_name' => $singular,
            'add_new' => "Add New $singular",
            'add_new_item' => "Add New $singular",
            'edit_item' => "Edit $singular",
            'new_item' => "New $singular",
            'view_item' => "View $singular",
            'search_items' => "Search $plural",
            'not_found' => "No $plural found",
            'not_found_in_trash' => "No $plural found in Trash",
        ];

        $args = [
            'labels' => $labels,
            'public' => false, // not accessible on frontend
            'show_ui' => true, // show in WP admin
            'show_in_menu' => true,
            'menu_position' => 25,
            'supports' => ['title', 'editor', 'custom-fields'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'has_archive' => false,
            'menu_icon' => 'dashicons-media-document',
        ];

        register_post_type($type, $args);
    }
}
