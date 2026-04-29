<?php

namespace Gazelle\Enum;

/**
 * These are the allowed columns to order audit_trail_user rows
 */

enum UserAuditOrder: string {
    case created = 'created desc';
}
