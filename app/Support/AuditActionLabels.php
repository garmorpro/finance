<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Turns a raw audit_logs.action string ("login.failed",
 * "transaction.bulk_category_set") into something a household member
 * reads without needing to know the codebase. Only the actions worth a
 * hand-written phrasing are listed explicitly; anything else — new
 * actions added later included — falls back to a generic formatter
 * (dots and underscores become spaces, first letter capitalized) rather
 * than needing this list kept in lockstep with every ->log() call in
 * the app.
 */
final class AuditActionLabels
{
    private const LABELS = [
        'login.success' => 'Signed in',
        'login.failed' => 'Failed sign-in attempt',
        'login.rate_limited' => 'Sign-in blocked (too many attempts)',
        'login.blocked_unverified_email' => 'Sign-in blocked (email not verified)',
        'login.webauthn_failed' => 'Failed passkey sign-in',
        'login.2fa_failed' => 'Failed two-factor code',
        'logout' => 'Signed out',
        'password.changed' => 'Password changed',
        'password_reset.requested' => 'Password reset requested',
        'password_reset.completed' => 'Password reset completed',
        '2fa.enabled' => 'Two-factor authentication turned on',
        '2fa.disabled' => 'Two-factor authentication turned off',
        'webauthn.registered' => 'Passkey added',
        'webauthn.removed' => 'Passkey removed',
        'quick_add_key.generated' => 'Quick Add key generated',
        'quick_add_key.revoked' => 'Quick Add key revoked',
        'quick_add_key.unlocked' => 'Quick Add key used to unlock a device',
        'quick_add_key.unlock_failed' => 'Failed Quick Add key attempt',
        'quick_add_key.rate_limited' => 'Quick Add unlock blocked (too many attempts)',
        'session.revoked' => 'Signed out of a device',
        'session.revoked_all_others' => 'Signed out of every other device',
        'email.verified' => 'Email address verified',
        'profile.updated' => 'Profile updated',
        'household.registered' => 'Household created (self-registration)',
        'household.bootstrap' => 'Household created (server setup)',
        'household.budget_reminder_settings_updated' => 'Budget reminder settings updated',
        'invitation.sent' => 'Household invitation sent',
        'invitation.accepted' => 'Household invitation accepted',
        'account.created' => 'Account created',
        'account.updated' => 'Account updated',
        'account.archived' => 'Account archived',
        'account.restored' => 'Account restored',
        'account.balance_adjusted' => 'Account balance adjusted',
        'transaction.created' => 'Transaction added',
        'transaction.created_via_quick_add_key' => 'Transaction added via Quick Add key',
        'transaction.updated' => 'Transaction edited',
        'transaction.deleted' => 'Transaction deleted',
        'transaction.transfer_created' => 'Transfer added',
        'transaction.bulk_category_set' => 'Bulk category change',
        'transaction.bulk_tag_added' => 'Bulk tag added',
        'transactions.imported' => 'Transactions imported from CSV',
        'import.csv' => 'CSV import',
        'attachment.uploaded' => 'Attachment uploaded',
        'attachment.deleted' => 'Attachment deleted',
        'category.created' => 'Category created',
        'category.updated' => 'Category updated',
        'category.merged' => 'Categories merged',
        'category_group.created' => 'Category group created',
        'category_group.renamed' => 'Category group renamed',
        'category_group.deleted' => 'Category group deleted',
        'tag.created' => 'Tag created',
        'tag.updated' => 'Tag updated',
        'tag.deleted' => 'Tag deleted',
        'rule.created' => 'Rule created',
        'rule.updated' => 'Rule updated',
        'rule.deleted' => 'Rule deleted',
        'rule.applied_retroactively' => 'Rule applied to existing transactions',
        'budget.item_set' => 'Budget amount set',
        'budget.copied_previous' => "Copied previous month's budget",
        'budget_review_link.opened' => 'Budget review link opened',
        'goal.created' => 'Goal created',
        'goal.updated' => 'Goal updated',
        'goal.contribution_added' => 'Goal contribution added',
        'goal.contribution_deleted' => 'Goal contribution deleted',
        'recurring.created' => 'Recurring item created',
        'recurring.updated' => 'Recurring item updated',
        'recurring.marked_paid' => 'Recurring item marked paid',
        'recurring.price_changed' => 'Recurring item price changed',
        'recurring.canceled' => 'Recurring item canceled',
        'recurring.reactivated' => 'Recurring item reactivated',
        'registration.rate_limited' => 'Registration blocked (too many attempts)',
    ];

    public static function label(string $action): string
    {
        if (isset(self::LABELS[$action])) {
            return self::LABELS[$action];
        }

        $readable = str_replace(['.', '_'], ' ', $action);

        return mb_strtoupper(mb_substr($readable, 0, 1)) . mb_substr($readable, 1);
    }
}
