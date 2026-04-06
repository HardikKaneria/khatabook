<?php

namespace KBS\Media;

use WP_Error;

defined('ABSPATH') || exit;

class ManagedImageUpload
{
    private const DEFAULT_MAX_BYTES = 2097152;
    private const DEFAULT_ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public static function upload(array $file, array $options = []): array|WP_Error
    {
        if (empty($file['tmp_name'])) {
            return new WP_Error('vy_logo_missing', 'Upload a PNG, JPG, or WEBP logo.', ['status' => 400]);
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return new WP_Error('vy_logo_upload_failed', 'Logo upload failed. Try again with a valid image file.', ['status' => 400]);
        }

        $maxBytes = (int) ($options['max_bytes'] ?? self::DEFAULT_MAX_BYTES);
        $allowedMimes = $options['allowed_mimes'] ?? self::DEFAULT_ALLOWED_MIMES;
        $fileSize = (int) ($file['size'] ?? 0);
        if ($fileSize <= 0) {
            return new WP_Error('vy_logo_empty', 'The uploaded logo file is empty.', ['status' => 400]);
        }

        if ($fileSize > $maxBytes) {
            return new WP_Error('vy_logo_too_large', 'Logo must be 2 MB or smaller.', ['status' => 400]);
        }

        $typeCheck = wp_check_filetype_and_ext((string) $file['tmp_name'], (string) $file['name']);
        $mimeType = (string) ($typeCheck['type'] ?? '');
        if (!in_array($mimeType, $allowedMimes, true)) {
            return new WP_Error('vy_logo_invalid_type', 'Only PNG, JPG, and WEBP logo files are allowed.', ['status' => 400]);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $upload = wp_handle_upload($file, [
            'test_form' => false,
            'mimes'     => [
                'jpg|jpeg' => 'image/jpeg',
                'png'      => 'image/png',
                'webp'     => 'image/webp',
            ],
        ]);

        if (!is_array($upload) || !empty($upload['error']) || empty($upload['file']) || empty($upload['url'])) {
            return new WP_Error('vy_logo_store_failed', 'Unable to store the uploaded logo.', ['status' => 500]);
        }

        $attachmentId = wp_insert_attachment([
            'post_mime_type' => $upload['type'] ?? $mimeType,
            'post_title'     => sanitize_file_name(pathinfo((string) $file['name'], PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $upload['file']);

        if (!$attachmentId || is_wp_error($attachmentId)) {
            @unlink((string) $upload['file']);
            return new WP_Error('vy_logo_attachment_failed', 'Unable to register the uploaded logo.', ['status' => 500]);
        }

        $metadata = wp_generate_attachment_metadata($attachmentId, $upload['file']);
        if (is_array($metadata)) {
            wp_update_attachment_metadata($attachmentId, $metadata);
        }

        foreach (($options['meta'] ?? []) as $metaKey => $metaValue) {
            update_post_meta($attachmentId, (string) $metaKey, $metaValue);
        }

        return [
            'attachment_id' => (int) $attachmentId,
            'file'          => (string) $upload['file'],
            'url'           => esc_url_raw((string) $upload['url']),
            'mime_type'     => (string) ($upload['type'] ?? $mimeType),
        ];
    }

    public static function deleteByUrl(string $url, array $options = []): void
    {
        $url = trim($url);
        if ($url === '' || !function_exists('attachment_url_to_postid')) {
            return;
        }

        $attachmentId = (int) attachment_url_to_postid($url);
        if ($attachmentId <= 0) {
            return;
        }

        $managedMetaKey = (string) ($options['managed_meta_key'] ?? '');
        if ($managedMetaKey !== '' && !get_post_meta($attachmentId, $managedMetaKey, true)) {
            return;
        }

        $orgMetaKey = (string) ($options['org_meta_key'] ?? '');
        $orgId = isset($options['org_id']) ? (int) $options['org_id'] : 0;
        if ($orgMetaKey !== '' && $orgId > 0 && (int) get_post_meta($attachmentId, $orgMetaKey, true) !== $orgId) {
            return;
        }

        wp_delete_attachment($attachmentId, true);
    }
}
