<?php

namespace Gazelle\Enum;

/**
 * These are the allowed columns to order user_audit_trail rows
 */

enum UserAuditOrder: string {
    case created = 'created desc';
}
