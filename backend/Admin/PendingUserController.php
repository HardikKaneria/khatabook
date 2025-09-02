<?php
/**
 * PendingUserController
 *
 * Approve/Decline actions for KBS Pending Users (admin only).
 */

namespace KBS\Admin;

use KBS\Core\SystemLogger;
use Exception;

class PendingUserController {

    /**
     * Handle approve/decline actions on pending users.
     */
    public static function handle_actions(): void {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            return;
        }

        // Nonce (form must include: wp_nonce_field('kbs_pending_user_action','kbs_pending_user_nonce'))
        $nonce = isset( $_POST['kbs_pending_user_nonce'] ) ? wp_unslash( $_POST['kbs_pending_user_nonce'] ) : '';
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'kbs_pending_user_action' ) ) {
            self::admin_notice( 'Security check failed.', 'error' );
            return;
        }

        global $wpdb;

        $pending_id = isset( $_POST['user_id'] ) ? (int) wp_unslash( $_POST['user_id'] ) : 0;
        if ( ! $pending_id ) {
            return;
        }

        $table_pending = $wpdb->prefix . 'kbs_pending_users';
        $table_orgs    = $wpdb->prefix . 'kbs_organizations';
        $table_roles   = $wpdb->prefix . 'kbs_user_org_roles';

        $pending_user = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table_pending} WHERE id = %d", $pending_id )
        );
        if ( ! $pending_user ) {
            return;
        }

        // Normalize values
        $email   = sanitize_email( (string) $pending_user->email );
        $name    = sanitize_text_field( (string) $pending_user->name );
        $company = sanitize_text_field( (string) $pending_user->company );

        // DECLINE
        if ( isset( $_POST['kbs_decline_user'] ) ) {
            error_log( "Declining pending user row ID: {$pending_id}" );

            $updated = $wpdb->update(
                $table_pending,
                [ 'status' => 'declined' ],
                [ 'id' => $pending_id ],
                [ '%s' ],
                [ '%d' ]
            );

            if ( false === $updated ) {
                self::admin_notice( 'Failed to update pending user status.', 'error' );
                return;
            }

            if ( class_exists( SystemLogger::class ) ) {
                SystemLogger::log_registration( $email, 'User declined' );
            }

            self::admin_notice( "User {$email} declined.", 'warning' );
            return;
        }

        // APPROVE
        if ( isset( $_POST['kbs_approve_user'] ) ) {
            error_log( "Approving pending user row ID: {$pending_id}" );

            // Reuse or create WP user
            $existing = get_user_by( 'email', $email );
            if ( $existing ) {
                $new_user_id = (int) $existing->ID;
                error_log( "Email exists. Reusing WP user ID: {$new_user_id}" );

                // Make sure display name set (optionally)
                if ( $name && $existing->display_name !== $name ) {
                    wp_update_user( [ 'ID' => $new_user_id, 'display_name' => $name ] );
                }
            } else {
                // Safer username base (avoid raw emails)
                $base_login = sanitize_user( current( explode( '@', $email ) ), true );
                if ( '' === $base_login ) {
                    $base_login = 'user';
                }

                $user_login = $base_login;
                $suffix     = 1;
                while ( username_exists( $user_login ) ) {
                    $user_login = "{$base_login}_{$suffix}";
                    $suffix++;
                }

                $password    = wp_generate_password( 20, true, true );
                $new_user_id = wp_insert_user( [
                    'user_login'   => $user_login,
                    'user_pass'    => $password,
                    'user_email'   => $email,
                    'display_name' => $name ?: $user_login,
                    'role'         => 'company_admin',
                ] );

                if ( is_wp_error( $new_user_id ) ) {
                    error_log( 'Failed to create user: ' . $new_user_id->get_error_message() );
                    self::admin_notice( 'Failed to create user: ' . $new_user_id->get_error_message(), 'error' );
                    return;
                }
            }

            // Ensure role (also for reused accounts)
            $user = get_userdata( $new_user_id );
            if ( $user ) {
                $user->set_role( 'company_admin' );
            } else {
                error_log( "User data not found after creation for ID: {$new_user_id}" );
            }

            // Begin transaction (if tables are InnoDB)
            $wpdb->query( 'START TRANSACTION' );

            try {
                // Create or reuse organization (only if company present)
                $org_id = 0;
                if ( $company !== '' ) {
                    $org_id = (int) $wpdb->get_var(
                        $wpdb->prepare( "SELECT id FROM {$table_orgs} WHERE org_name = %s LIMIT 1", $company )
                    );

                    if ( ! $org_id ) {
                        $org_created = $wpdb->insert(
                            $table_orgs,
                            [ 'org_name' => $company ],
                            [ '%s' ]
                        );

                        if ( ! $org_created ) {
                            throw new Exception( "Failed to create organization for: {$company}" );
                        }
                        $org_id = (int) $wpdb->insert_id;
                    }

                    // Map user -> org role (idempotent)
                    $role_exists = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$table_roles} WHERE user_id = %d AND org_id = %d AND role = %s",
                            $new_user_id,
                            $org_id,
                            'company_admin'
                        )
                    );

                    if ( ! $role_exists ) {
                        $role_inserted = $wpdb->insert(
                            $table_roles,
                            [
                                'user_id'    => $new_user_id,
                                'org_id'     => $org_id,
                                'role'       => 'company_admin',
                                'is_primary' => 1,
                            ],
                            [ '%d', '%d', '%s', '%d' ]
                        );
                        if ( ! $role_inserted ) {
                            throw new Exception( 'Failed to assign user to organization role mapping' );
                        }
                    }
                }

                // Mark pending row approved
                $pending_updated = $wpdb->update(
                    $table_pending,
                    [ 'status' => 'approved' ],
                    [ 'id' => $pending_id ],
                    [ '%s' ],
                    [ '%d' ]
                );
                if ( false === $pending_updated ) {
                    throw new Exception( 'Failed to update pending user status.' );
                }

                // ✅ Store meta on the NEW WP user, not the pending row ID
                update_user_meta( $new_user_id, 'kbs_account_status', 'approved' );

                // Commit
                $wpdb->query( 'COMMIT' );

                if ( class_exists( SystemLogger::class ) ) {
                    SystemLogger::log_registration( $email, 'User approved and account created' );
                }

                self::admin_notice( "User {$email} approved successfully.", 'success' );

            } catch ( Exception $e ) {
                $wpdb->query( 'ROLLBACK' );
                error_log( 'Approval failed: ' . $e->getMessage() );
                self::admin_notice( 'Approval failed: ' . $e->getMessage(), 'error' );
            }
        }
    }

    /**
     * Helper to enqueue an admin notice message.
     */
    private static function admin_notice( string $message, string $type = 'success' ): void {
        add_action(
            'admin_notices',
            static function () use ( $message, $type ) {
                echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
            }
        );
    }
}
