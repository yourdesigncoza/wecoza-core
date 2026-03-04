<?php
declare(strict_types=1);

namespace WeCoza\Agents\Services;

use WeCoza\Agents\Repositories\AgentRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent WP User Service
 *
 * Dedicated service encapsulating all WP user lifecycle logic for agents.
 * Handles creation, linking, email sync, role management, and soft-delete cleanup.
 * Keeps AgentService clean of WP user concerns.
 *
 * @package WeCoza\Agents\Services
 * @since 7.0.0
 */
class AgentWpUserService
{
    private AgentRepository $repository;

    public function __construct(AgentRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Ensure agent has a linked WP user. Creates one if missing, updates email if changed.
     *
     * @param int        $agentId           Agent ID in PostgreSQL
     * @param array      $agentData         Agent data (needs email_address, first_name, surname)
     * @param array|null $previousAgent     Previous agent data (for email change detection on update)
     * @param bool       $sendNotification  Whether to send WP new-user email
     * @return int|null WP user ID on success, null on skip/failure
     */
    public function syncWpUser(int $agentId, array $agentData, ?array $previousAgent = null, bool $sendNotification = true): ?int
    {
        $email = trim($agentData['email_address'] ?? '');

        if (empty($email) || !is_email($email)) {
            wecoza_log("Agent {$agentId}: skipping WP user — invalid/empty email", 'warning');
            return null;
        }

        // Load current agent to check existing wp_user_id
        $agent = $this->repository->getAgent($agentId);
        $existingWpUserId = !empty($agent['wp_user_id']) ? (int) $agent['wp_user_id'] : null;

        // If agent already has a WP user, handle email sync
        if ($existingWpUserId) {
            return $this->handleExistingLink($existingWpUserId, $agentData, $previousAgent);
        }

        // No WP user yet — create or link
        return $this->createOrLinkWpUser($agentId, $agentData, $sendNotification);
    }

    /**
     * Handle email and display name sync for an already-linked WP user.
     */
    private function handleExistingLink(int $wpUserId, array $agentData, ?array $previousAgent): ?int
    {
        $wpUser = get_userdata($wpUserId);

        // WP user was manually deleted — stale link
        if (!$wpUser) {
            wecoza_log("WP user {$wpUserId} no longer exists — will recreate on next save", 'warning');
            return null;
        }

        // Check if email changed
        $newEmail = trim($agentData['email_address'] ?? '');
        $oldEmail = trim($previousAgent['email_address'] ?? $wpUser->user_email);

        if ($newEmail !== $oldEmail && !empty($newEmail)) {
            // Check new email isn't taken by another WP user
            $existingUser = get_user_by('email', $newEmail);
            if ($existingUser && $existingUser->ID !== $wpUserId) {
                wecoza_log("Cannot sync email to WP user {$wpUserId} — {$newEmail} already taken by WP user {$existingUser->ID}", 'warning');
                return $wpUserId; // Keep existing link, skip email update
            }

            wp_update_user([
                'ID'         => $wpUserId,
                'user_email' => $newEmail,
            ]);
            wecoza_log("Synced email to WP user {$wpUserId}: {$newEmail}", 'info');
        }

        // Sync display name
        $displayName = trim(($agentData['first_name'] ?? '') . ' ' . ($agentData['surname'] ?? ''));
        if (!empty($displayName)) {
            wp_update_user([
                'ID'           => $wpUserId,
                'first_name'   => $agentData['first_name'] ?? '',
                'last_name'    => $agentData['surname'] ?? '',
                'display_name' => $displayName,
            ]);
        }

        return $wpUserId;
    }

    /**
     * Create a new WP user or link to existing one by email.
     */
    private function createOrLinkWpUser(int $agentId, array $agentData, bool $sendNotification): ?int
    {
        $email = trim($agentData['email_address']);
        $displayName = trim(($agentData['first_name'] ?? '') . ' ' . ($agentData['surname'] ?? ''));

        // Check if WP user already exists with this email
        $existingUser = get_user_by('email', $email);

        if ($existingUser) {
            $wpUserId = $existingUser->ID;
            // Add wp_agent role if not already present
            $existingUser->add_role('wp_agent');
            wecoza_log("Linked agent {$agentId} to existing WP user {$wpUserId}", 'info');
        } else {
            // Create new WP user
            $username = $this->generateUsername($email, $agentData);
            $password = wp_generate_password(16, true, true);

            $wpUserId = wp_insert_user([
                'user_login'   => $username,
                'user_email'   => $email,
                'user_pass'    => $password,
                'first_name'   => $agentData['first_name'] ?? '',
                'last_name'    => $agentData['surname'] ?? '',
                'display_name' => $displayName ?: $username,
                'role'         => 'wp_agent',
            ]);

            if (is_wp_error($wpUserId)) {
                wecoza_log("Failed to create WP user for agent {$agentId}: " . $wpUserId->get_error_message(), 'error');
                return null;
            }

            wecoza_log("Created WP user {$wpUserId} for agent {$agentId}", 'info');

            if ($sendNotification) {
                wp_new_user_notification($wpUserId, null, 'user');
            }
        }

        // Store wp_user_id on agent record
        $this->repository->updateAgent($agentId, ['wp_user_id' => $wpUserId]);

        return $wpUserId;
    }

    /**
     * Remove wp_agent role when agent is deactivated/deleted.
     */
    public function removeAgentRole(int $agentId): void
    {
        $agent = $this->repository->getAgent($agentId);
        $wpUserId = !empty($agent['wp_user_id']) ? (int) $agent['wp_user_id'] : null;

        if (!$wpUserId) {
            return;
        }

        $wpUser = get_userdata($wpUserId);
        if ($wpUser) {
            $wpUser->remove_role('wp_agent');
            wecoza_log("Removed wp_agent role from WP user {$wpUserId} (agent {$agentId} deactivated)", 'info');
        }
    }

    /**
     * Generate a unique username from email or name.
     */
    private function generateUsername(string $email, array $agentData): string
    {
        // Try email prefix first
        $username = sanitize_user(strstr($email, '@', true), true);

        if (empty($username)) {
            $username = sanitize_user(
                strtolower(($agentData['first_name'] ?? 'agent') . '.' . ($agentData['surname'] ?? '')),
                true
            );
        }

        // Ensure uniqueness
        $baseUsername = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }
}
